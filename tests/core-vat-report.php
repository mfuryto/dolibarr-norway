<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// Characterise the core VAT report using the real posted fixtures, never production data.
if (PHP_SAPI !== 'cli' || getenv('NORWAY_SYNTHETIC_TEST') !== '1'
    || getenv('DOLI_DB_NAME') !== 'norway_test'
    || getenv('DOLI_COMPANY_NAME') !== 'SYNTHETIC NORWAY TEST ONLY') { exit(1); }
require '/var/www/html/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/tax.lib.php';
require_once DOL_DOCUMENT_ROOT.'/compta/paiement/class/paiement.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/paiementfourn.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/accountancy/class/bookkeeping.class.php';
function vrAssert($ok, $message) { if (!$ok) { throw new RuntimeException($message); } }
function vrSql($sql) { global $db; $r=$db->query($sql);vrAssert((bool)$r,'Synthetic VAT query: '.$db->lasterror());return $r; }
function vrNear($actual,$expected,$message) { vrAssert(abs($actual-$expected)<0.00001,$message.' ('.$actual.' / '.$expected.')'); }
vrAssert($user->fetch('','testadmin')>0 && $user->admin,'Synthetic admin missing');$user->loadRights('',1);
$p=$db->prefix();$entity=(int)$conf->entity;
$saleCredit=$db->fetch_object(vrSql("SELECT f.rowid,f.fk_facture_source,f.fk_soc FROM ".$p."facture f JOIN ".$p."societe s ON s.rowid=f.fk_soc WHERE f.entity=".$entity." AND s.nom='SYNTHETIC CUSTOMER ONLY' AND f.type=2 AND f.total_tva=-50 ORDER BY f.rowid DESC LIMIT 1"));
vrAssert((bool)$saleCredit,'Run invoice-workflow first');
$saleOther=$db->fetch_object(vrSql('SELECT rowid FROM '.$p.'facture WHERE entity='.$entity.' AND fk_soc='.(int)$saleCredit->fk_soc.' AND type=0 AND total_ht=1000 AND rowid<>'.(int)$saleCredit->fk_facture_source.' ORDER BY rowid DESC LIMIT 1'));
$purchaseCredit=$db->fetch_object(vrSql("SELECT rowid,fk_facture_source FROM ".$p."facture_fourn WHERE entity=".$entity." AND ref_supplier LIKE 'SYNB-%' AND type=2 AND total_tva=-5 ORDER BY rowid DESC LIMIT 1"));
vrAssert($saleOther && $purchaseCredit,'Run core-purchases first');
$sales=array((int)$saleCredit->fk_facture_source,(int)$saleOther->rowid,(int)$saleCredit->rowid);
$buys=array((int)$purchaseCredit->fk_facture_source,(int)$purchaseCredit->rowid);
$ids=array('sell'=>$sales,'buy'=>$buys);
foreach(array('customer_invoice'=>$sales,'supplier_invoice'=>$buys) as $type=>$list){
 foreach($list as $id){$row=$db->fetch_object(vrSql("SELECT COUNT(*) AS n,SUM(debit-credit) AS balance FROM ".$p."accounting_bookkeeping WHERE entity=".$entity." AND doc_type='".$type."' AND fk_doc=".$id));vrAssert((int)$row->n===3,'Fixture was not transferred through the native journal');vrNear((float)$row->balance,0,'Fixture not balanced');}
}
function vrReport($direction,$month,$paymentBasis=false)
{
 global $db,$ids;
 // Core 24.0.1 service/invoice branch reads datep without selecting it.
 // Record only this known warning; other PHP errors remain visible.
 set_error_handler(static function($severity,$message,$file){
  if($severity===E_WARNING && $message==='Undefined array key "datep"' && $file===DOL_DOCUMENT_ROOT.'/core/lib/tax.lib.php'){$GLOBALS['vrDateWarnings']=($GLOBALS['vrDateWarnings']??0)+1;return true;}return false;
 });
 try{$report=tax_by_rate('vat',$db,2026,0,0,0,0,$direction,$month);}finally{restore_error_handler();}
 vrAssert(is_array($report),'Core VAT query failed');
 $net=$vat=0;$seen=array();$groups=array();
 foreach($report as $key=>$group){foreach($group['facid'] as $index=>$id){if(!in_array((int)$id,$ids[$direction],true)){continue;}
  $ratio=$paymentBasis ? (float)$group['payment_amount'][$index]/(float)$group['ftotal_ttc'][$index] : 1;
  $net+=(float)$group['totalht_list'][$index]*$ratio;$vat+=(float)$group['vat_list'][$index]*$ratio;$seen[]=(int)$id;$groups[(string)$key]=true;
 }}return array('net'=>$net,'vat'=>$vat,'seen'=>$seen,'groups'=>array_keys($groups));
}
function vrManual($account,$debit,$credit,$piece)
{
 global $db,$entity,$user;
 $o=new BookKeeping($db);$o->entity=$entity;$o->doc_date=dol_mktime(12,0,0,2,10,2026);$o->doc_type='manual';$o->doc_ref='SYNTHETIC VAT REPORT ONLY';$o->fk_doc=0;$o->fk_docdet=0;
 $o->numero_compte=$account;$o->label_compte='Synthetic VAT report';$o->label_operation='Synthetic VAT report';$o->debit=$debit;$o->credit=$credit;$o->montant=$debit+$credit;$o->sens=$debit?'D':'C';$o->code_journal='SYNVAT';$o->journal_label='Synthetic';$o->piece_num=$piece;
 vrAssert($o->createStd($user)>0,'Native manual posting: '.$o->error);return (int)$o->id;
}
$before=array();foreach(array('facture','facturedet','facture_fourn','facture_fourn_det','accounting_bookkeeping','paiement','paiement_facture','paiementfourn','paiementfourn_facturefourn','norway_tax_evidence') as $table){$before[$table]=(int)$db->fetch_object(vrSql('SELECT COUNT(*) AS n FROM '.$p.$table))->n;}
function vrSnapshot()
{
 global $p,$entity,$sales,$buys,$db;
 $queries=array('facture'=>'rowid IN ('.implode(',',$sales).')','facturedet'=>'fk_facture IN ('.implode(',',$sales).')',
  'facture_fourn'=>'rowid IN ('.implode(',',$buys).')','facture_fourn_det'=>'fk_facture_fourn IN ('.implode(',',$buys).')',
  'accounting_bookkeeping'=>"entity=".$entity." AND ((doc_type='customer_invoice' AND fk_doc IN (".implode(',',$sales).")) OR (doc_type='supplier_invoice' AND fk_doc IN (".implode(',',$buys).")))");
 $snapshot=array();foreach($queries as $table=>$where){$rows=vrSql('SELECT * FROM '.$p.$table.' WHERE '.$where.' ORDER BY rowid');while($row=$db->fetch_object($rows)){$snapshot[$table][]=(array)$row;}}return hash('sha256',serialize($snapshot));
}
$originalSnapshot=vrSnapshot();
$oldGlobals=clone $conf->global;$db->begin();
try {
 foreach(array('sell','buy') as $direction){$supplier=$direction==='buy';$header=$supplier?'facture_fourn':'facture';$lines=$supplier?'facture_fourn_det':'facturedet';$fk=$supplier?'fk_facture_fourn':'fk_facture';$paymentLinks=$supplier?'paiementfourn_facturefourn':'paiement_facture';
  foreach($ids[$direction] as $i=>$id){vrSql('UPDATE '.$p.$header." SET datef='2026-02-10',paye=0 WHERE rowid=".$id);vrSql('DELETE FROM '.$p.$paymentLinks.' WHERE '.($supplier?'fk_facturefourn':'fk_facture').'='.$id);
   $code=$supplier?'SYNVP':($i===1?'SYNVB':'SYNVA');vrSql('UPDATE '.$p.$lines." SET vat_src_code='".$code."',date_start=NULL,date_end=NULL WHERE ".$fk.'='.$id);
   vrSql('UPDATE '.$p."accounting_bookkeeping SET doc_date='2026-02-10' WHERE entity=".$entity." AND doc_type='".($supplier?'supplier_invoice':'customer_invoice')."' AND fk_doc=".$id);
  }
 }
 foreach(array('SELL_PRODUCT','SELL_SERVICE','BUY_PRODUCT','BUY_SERVICE') as $key){$conf->global->{'TAX_MODE_'.$key}='invoice';}
 $conf->global->MAIN_NOT_INCLUDE_ZERO_VAT_IN_REPORTS=0;
 // Same real posted fixtures through both core product/service query branches.
 foreach(array(0,1) as $lineType){
  vrSql('UPDATE '.$p.'facturedet SET product_type='.$lineType.' WHERE fk_facture IN ('.implode(',',$sales).')');vrSql('UPDATE '.$p.'facture_fourn_det SET product_type='.$lineType.' WHERE fk_facture_fourn IN ('.implode(',',$buys).')');
  $sale=vrReport('sell',2);$buy=vrReport('buy',2);
  vrNear($sale['net'],1800,'Sale/credit net');vrNear($sale['vat'],450,'Sale/credit VAT');vrNear($buy['net'],80,'Purchase/credit net');vrNear($buy['vat'],20,'Purchase/credit VAT');
  vrAssert(count($sale['seen'])===3&&count($buy['seen'])===2,'Core lost/duplicated invoice or credit');
  vrAssert(count($sale['groups'])===2 && count($buy['groups'])===1,'Local source codes not separated at same rate');
  $ledgerSale=$db->fetch_object(vrSql("SELECT SUM(credit-debit) AS vat FROM ".$p."accounting_bookkeeping WHERE entity=".$entity." AND doc_type='customer_invoice' AND fk_doc IN (".implode(',',$sales).") AND numero_compte='2700'"));
  $ledgerBuy=$db->fetch_object(vrSql("SELECT SUM(debit-credit) AS vat FROM ".$p."accounting_bookkeeping WHERE entity=".$entity." AND doc_type='supplier_invoice' AND fk_doc IN (".implode(',',$buys).") AND numero_compte='2710'"));
  vrNear($sale['vat'],(float)$ledgerSale->vat,'Sale report differs from native ledger');vrNear($buy['vat'],(float)$ledgerBuy->vat,'Purchase report differs from native ledger');
  vrNear(vrReport('sell',3)['vat'],0,'Invoice basis shifted to later month');vrNear(vrReport('buy',3)['vat'],0,'Purchase invoice basis shifted to later month');
 }
 echo "PASS core invoice-basis goods/services, unpaid sales/purchases and credits, local-code separation, period and native-ledger agreement\n";
 // Core payment basis excludes unpaid invoices; reproduce its dashboard proration.
 foreach(array('SELL_PRODUCT','SELL_SERVICE','BUY_PRODUCT','BUY_SERVICE') as $key){$conf->global->{'TAX_MODE_'.$key}='payment';}
 vrNear(vrReport('sell',2,true)['vat'],0,'Unpaid sale in payment basis');vrNear(vrReport('buy',2,true)['vat'],0,'Unpaid purchase in payment basis');
 $method=$db->fetch_object(vrSql("SELECT id FROM ".$p."c_paiement WHERE code='VIR' AND active=1 AND entity=".$entity));vrAssert((bool)$method,'Transfer method missing');
 foreach(array(2,3) as $month){foreach(array('sell'=>array($sales[0],625),'buy'=>array($buys[0],62.5)) as $direction=>$case){
  $payment=$direction==='sell'?new Paiement($db):new PaiementFourn($db);$payment->datepaye=dol_mktime(12,0,0,$month,20,2026);$payment->paiementid=(int)$method->id;$payment->amounts=array($case[0]=>$case[1]);vrAssert($payment->create($user)>0,'Native payment failed: '.$payment->error);
 }}
 foreach(array(0,1) as $lineType){vrSql('UPDATE '.$p.'facturedet SET product_type='.$lineType.' WHERE fk_facture IN ('.implode(',',$sales).')');vrSql('UPDATE '.$p.'facture_fourn_det SET product_type='.$lineType.' WHERE fk_facture_fourn IN ('.implode(',',$buys).')');
  foreach(array(2,3) as $month){vrNear(vrReport('sell',$month,true)['vat'],125,'Partial sale payment period');vrNear(vrReport('buy',$month,true)['vat'],12.5,'Partial purchase payment period');}
 }
 echo "PASS core payment-basis unpaid exclusion and native split payments across months for goods/services\n";
 foreach(array('SELL_PRODUCT','SELL_SERVICE','BUY_PRODUCT','BUY_SERVICE') as $key){$conf->global->{'TAX_MODE_'.$key}='invoice';}
 vrNear(vrReport('sell',2)['vat'],450,'Payments altered invoice-basis sale VAT');vrNear(vrReport('buy',2)['vat'],20,'Payments altered invoice-basis purchase VAT');
 // A balanced native manual VAT entry is visible in the ledger, absent from tax_by_rate.
 $piece=(int)$db->fetch_object(vrSql('SELECT COALESCE(MAX(piece_num),0)+1 AS n FROM '.$p.'accounting_bookkeeping WHERE entity='.$entity))->n;
 $vatId=vrManual('2710',7,0,$piece);vrManual('1920',0,7,$piece);
 $manual=$db->fetch_object(vrSql('SELECT SUM(debit-credit) AS balance FROM '.$p.'accounting_bookkeeping WHERE entity='.$entity.' AND piece_num='.$piece));vrNear((float)$manual->balance,0,'Manual voucher unbalanced');
 $manualVat=$db->fetch_object(vrSql('SELECT debit-credit AS vat FROM '.$p.'accounting_bookkeeping WHERE rowid='.$vatId));vrNear((float)$manualVat->vat,7,'Manual VAT absent from ledger');vrNear(vrReport('buy',2)['vat'],20,'Core unexpectedly includes manual VAT; reassess documented gap');
 // Reclassification through native separate voucher changes ledger deduction, not invoice report.
 $piece++;vrManual('4000',25,0,$piece);vrManual('2710',0,25,$piece);
 $vatRow=$db->fetch_object(vrSql("SELECT SUM(debit-credit) AS vat FROM ".$p."accounting_bookkeeping WHERE entity=".$entity." AND numero_compte='2710' AND ((doc_type='supplier_invoice' AND fk_doc IN (".implode(',',$buys).")) OR (doc_type='manual' AND piece_num IN (".($piece-1).','.$piece.')))'));
 vrNear((float)$vatRow->vat,2,'Ledger reclassification amount');vrNear(vrReport('buy',2)['vat'],20,'Core unexpectedly recognises ledger reclassification; reassess gap');
 echo "PASS characterised gaps: manual VAT +7 and separate reclassification -25 affect ledger, not core invoice report\n";
} finally {$db->rollback();$conf->global=$oldGlobals;}
foreach($before as $table=>$count){vrAssert((int)$db->fetch_object(vrSql('SELECT COUNT(*) AS n FROM '.$p.$table))->n===$count,'Synthetic transaction leaked rows in '.$table);}
vrAssert(vrSnapshot()===$originalSnapshot,'Synthetic transaction changed original source/ledger bytes');
echo "PASS VAT characterisation rolled back; no new runtime controls, history or production settings\n";
if(!empty($GLOBALS['vrDateWarnings'])){echo "NOTE observed core 24.0.1 service/invoice datep warning; core unchanged\n";}
