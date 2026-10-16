<?php
// SPDX-License-Identifier: GPL-3.0-or-later
if(PHP_SAPI!=='cli'||getenv('NORWAY_SYNTHETIC_TEST')!=='1'||getenv('DOLI_DB_NAME')!=='norway_test'){exit(1);}
require '/var/www/html/master.inc.php';require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';require_once DOL_DOCUMENT_ROOT.'/accountancy/class/accountingaccount.class.php';require_once DOL_DOCUMENT_ROOT.'/accountancy/class/bookkeeping.class.php';
function spAssert($ok,$message){if(!$ok){throw new RuntimeException($message);}}
function spSql($sql){global $db;$r=$db->query($sql);spAssert((bool)$r,'Synthetic supplier query: '.$db->lasterror());return $r;}
spAssert($user->fetch('','testadmin')>0,'Admin missing');$activation=activateModule('modFournisseur');spAssert(empty($activation['errors']),'Supplier module activation failed');require_once DOL_DOCUMENT_ROOT.'/custom/norway/core/modules/modNorway.class.php';$norway=new modNorway($db);spAssert($norway->init()>0,'Norway refresh failed');$conf->setValues($db);$user->loadRights('',1);$entity=(int)$conf->entity;$p=$db->prefix();$run=bin2hex(random_bytes(4));$evidenceBefore=(int)$db->fetch_object(spSql('SELECT COUNT(*) AS n FROM '.$p.'norway_tax_evidence WHERE entity='.$entity))->n;
$chart=$db->fetch_object(spSql('SELECT pcg_version FROM '.$p.'accounting_system WHERE rowid='.(int)getDolGlobalString('CHARTOFACCOUNTS')));spAssert((bool)$chart,'Chart missing');$accounts=array();
foreach(array('2400','4000','2710') as $number){$r=$db->fetch_object(spSql("SELECT rowid FROM ".$p."accounting_account WHERE entity=".$entity." AND fk_pcg_version='".$db->escape($chart->pcg_version)."' AND account_number='".$number."'"));if($r){$accounts[$number]=(int)$r->rowid;continue;}$a=new AccountingAccount($db);$a->fk_pcg_version=$chart->pcg_version;$a->account_number=$number;$a->label='Synthetic '.$number;$a->active=1;spAssert($a->create($user)>0,'Account failed');$accounts[$number]=(int)$a->id;}
foreach(array('ACCOUNTING_ACCOUNT_SUPPLIER'=>'2400','ACCOUNTING_PRODUCT_BUY_ACCOUNT'=>'4000','ACCOUNTING_SERVICE_BUY_ACCOUNT'=>'4000','ACCOUNTING_VAT_BUY_ACCOUNT'=>'2710') as $name=>$value){spAssert(dolibarr_set_const($db,$name,$value,'chaine',0,'',$entity)>0,'Setting failed');$conf->global->$name=$value;}
spSql("INSERT INTO ".$p."accounting_journal (entity,code,label,nature,active) VALUES (".$entity.",'SYNB-".$run."','Synthetic purchases',3,1)");$journal=(int)$db->last_insert_id($p.'accounting_journal');
$supplier=new Societe($db);$supplier->name='SYNTHETIC SUPPLIER ONLY '.$run;$supplier->fournisseur=1;$supplier->code_fournisseur='SYNB-'.$run;$supplier->code_compta_fournisseur='SYNB-RESK';$supplier->country_id=173;spAssert($supplier->create($user)>0,'Supplier failed');
$invoices=array();foreach(array(array(0,100),array(2,-20)) as $case){$invoice=new FactureFournisseur($db);$invoice->socid=$supplier->id;$invoice->ref_supplier='SYNB-'.$run.'-'.$case[0];$invoice->type=$case[0];$invoice->date=dol_mktime(12,0,0,1,10,2026);$invoice->multicurrency_code='NOK';$invoice->multicurrency_tx=1;if($case[0]===2){$invoice->fk_facture_source=$invoices[0]->id;}
 spAssert($invoice->create($user)>0,'Supplier invoice create: '.$invoice->error);spAssert($invoice->addline('Synthetic goods and services',$case[1],25,0,0,1,0,0,0,0,$accounts['4000'])>0,'Supplier line: '.$invoice->error);spAssert($invoice->validate($user,'SYNB-'.$run.'-'.$case[0])>0,'Supplier validate: '.$invoice->error);spAssert($invoice->fetch($invoice->id)>0,'Reload failed');$invoices[]=$invoice;}
$cookie=tempnam(sys_get_temp_dir(),'norway-supplier-');$curl=curl_init();curl_setopt_array($curl,array(CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_TIMEOUT=>30));
function spHttp($path,$post=null){global $curl;curl_setopt($curl,CURLOPT_URL,'http://localhost'.$path);curl_setopt($curl,CURLOPT_POST,$post!==null);if($post!==null){curl_setopt($curl,CURLOPT_POSTFIELDS,http_build_query($post));}$body=curl_exec($curl);spAssert($body!==false&&curl_getinfo($curl,CURLINFO_HTTP_CODE)<400,'Supplier HTTP failed');return $body;}
function spToken($body){spAssert(preg_match('/name="token"[^>]*value="([^"]+)"/',$body,$m)===1,'Token missing');return html_entity_decode($m[1]);}
try{$body=spHttp('/index.php');spHttp('/index.php',array('token'=>spToken($body),'actionlogin'=>'login','loginfunction'=>'loginfunction','username'=>'testadmin','password'=>getenv('DOLI_ADMIN_PASSWORD')));
 $params=array('id_journal'=>$journal,'date_startday'=>1,'date_startmonth'=>1,'date_startyear'=>2026,'date_endday'=>31,'date_endmonth'=>12,'date_endyear'=>2026,'in_bookkeeping'=>'notyet');$path='/accountancy/journal/purchasesjournal.php?'.http_build_query($params);$body=spHttp($path);spHttp($path,$params+array('token'=>spToken($body),'action'=>'writebookkeeping'));
 foreach($invoices as $invoice){
 $rows=spSql("SELECT numero_compte,SUM(debit-credit) AS amount,COUNT(*) AS n FROM ".$p."accounting_bookkeeping WHERE entity=".$entity." AND doc_type='supplier_invoice' AND fk_doc=".(int)$invoice->id." GROUP BY numero_compte");$actual=array();$balance=0;while($row=$db->fetch_object($rows)){$actual[$row->numero_compte]=(float)$row->amount;$balance+=(float)$row->amount;}
 $net=(int)$invoice->type===2?-20:100;$tax=$net*0.25;
 spAssert(count($actual)===3&&abs($balance)<0.001,'Native purchase voucher missing or unbalanced');
 foreach(array('4000'=>$net,'2710'=>$tax,'2400'=>-$net-$tax) as $account=>$amount){spAssert(isset($actual[$account])&&abs($actual[$account]-$amount)<0.001,'Native purchase account/sign incorrect');}
 foreach(array('nb_NO','en_US') as $locale){$card=spHttp('/fourn/facture/card.php?facid='.(int)$invoice->id.'&lang='.$locale);spAssert(strpos($card,'norway-purchase-allocation')===false&&strpos($card,'/norway/admin/purchasededuction.php')===false,'Retired purchase workflow remains on core card');}
 }
 $before=(int)$db->fetch_object(spSql('SELECT COUNT(*) AS n FROM '.$p.'accounting_bookkeeping WHERE entity='.$entity))->n;
 $body=spHttp($path);spHttp($path,$params+array('token'=>spToken($body),'action'=>'writebookkeeping'));
 spAssert((int)$db->fetch_object(spSql('SELECT COUNT(*) AS n FROM '.$p.'accounting_bookkeeping WHERE entity='.$entity))->n===$before,'Native purchase retransfer duplicated ledger');
 spAssert((int)$db->fetch_object(spSql('SELECT COUNT(*) AS n FROM '.$p.'norway_tax_evidence WHERE entity='.$entity))->n===$evidenceBefore,'Ordinary purchases created extra evidence');
 echo "Native purchase invoice and credit: balanced accounts, no duplicate transfer, no custom workflow or evidence\n";
 // Characterise mixed ordinary rates through the real purchase journal, then native corrections.
 require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
 spAssert(!getDolGlobalInt('ACCOUNTING_PURCHASES_DO_NOT_DISPATCH_TAXES'),'Global gross-only purchase mode must stay off');
 $rateAccounts=array(25=>'2710',15=>'2711',12=>'2713');$codes=array();
 foreach($rateAccounts as $rate=>$number){
  if(!isset($accounts[$number])){$existing=$db->fetch_object(spSql("SELECT rowid FROM ".$p."accounting_account WHERE entity=".$entity." AND fk_pcg_version='".$db->escape($chart->pcg_version)."' AND account_number='".$number."'"));
   if($existing){$accounts[$number]=(int)$existing->rowid;}else{$a=new AccountingAccount($db);$a->fk_pcg_version=$chart->pcg_version;$a->account_number=$number;$a->label='Synthetic '.$number;$a->active=1;spAssert($a->create($user)>0,'Mixed-rate account failed');$accounts[$number]=(int)$a->id;}}
  $codes[$rate]='D'.substr($run,0,4).$rate;
  spSql("INSERT INTO ".$p."c_tva(entity,fk_pays,code,type_vat,taux,recuperableonly,active,accountancy_code_buy) VALUES (".$entity.",173,'".$codes[$rate]."',2,".$rate.",0,1,'".$number."')");
 }
 $mixed=array();
 foreach(array(0,1) as $lineType){$original=0;foreach(array(0,2) as $documentType){
  $o=new FactureFournisseur($db);$o->socid=$supplier->id;$o->ref_supplier='SYND-'.$run.'-'.$lineType.'-'.$documentType;$o->type=$documentType;$o->date=dol_mktime(12,0,0,1,10,2026);$o->multicurrency_code='NOK';$o->multicurrency_tx=1;if($documentType===2){$o->fk_facture_source=$original;}
  spAssert($o->create($user)>0,'Mixed purchase create: '.$o->error);$net=$documentType===2?-20:100;
  foreach($rateAccounts as $rate=>$number){spAssert($o->addline('Synthetic mixed-rate '.$rate,$net,$rate.' ('.$codes[$rate].')',0,0,1,0,0,0,0,$accounts['4000'],0,'HT',$lineType)>0,'Mixed purchase line: '.$o->error);}
  spAssert($o->validate($user,$o->ref_supplier)>0,'Mixed purchase validate: '.$o->error);spAssert($o->fetch($o->id)>0,'Mixed reload');if(!$original){$original=(int)$o->id;}$mixed[]=array('invoice'=>$o,'net'=>$net,'share'=>$lineType===0?0.5:0.0);
 }}
 $body=spHttp($path);spHttp($path,$params+array('token'=>spToken($body),'action'=>'writebookkeeping'));
 function spMixedTotals($id,$pieces=array()){
  global $db,$p,$entity;
  $where="entity=".$entity." AND ((doc_type='supplier_invoice' AND fk_doc=".$id.")".($pieces?" OR (doc_type='manual' AND piece_num IN (".implode(',',$pieces)."))":'').')';
  $r=spSql('SELECT numero_compte,SUM(debit-credit) AS amount FROM '.$p.'accounting_bookkeeping WHERE '.$where.' GROUP BY numero_compte');$totals=array();while($row=$db->fetch_object($r)){$totals[$row->numero_compte]=(float)$row->amount;}spAssert(abs(array_sum($totals))<0.00001,'Mixed native voucher not balanced');return $totals;
 }
 foreach($mixed as $case){$o=$case['invoice'];$net=$case['net'];$totals=spMixedTotals((int)$o->id);
  spAssert(abs($totals['4000']-$net*3)<0.00001&&abs($totals['2400']+$net*3.52)<0.00001,'Mixed native cost/supplier amount');
  foreach($rateAccounts as $rate=>$number){spAssert(isset($totals[$number])&&abs($totals[$number]-$net*$rate/100)<0.00001,'Rate-specific purchase VAT account wrong');}
 }
 echo "PASS native mixed 25/15/12 purchases and credits, goods/services, explicit rate accounts and balanced vouchers\n";
 // Correct deduction in separate core vouchers; preserve the supplier invoice and initial posting.
 spSql("INSERT INTO ".$p."accounting_journal(entity,code,label,nature,active) VALUES (".$entity.",'SYND-".$run."','Synthetic deduction corrections',1,1)");
 function spDeductionLine($invoice,$piece,$account,$amount){
  global $db,$user,$entity,$run;
  $entry=new BookKeeping($db);$entry->entity=$entity;$entry->doc_date=dol_mktime(12,0,0,1,10,2026);$entry->doc_type='manual';$entry->doc_ref='SYND '.$invoice->ref;
  $entry->fk_doc=0;$entry->fk_docdet=0;$entry->numero_compte=$account;$entry->label_compte='Synthetic deduction';$entry->label_operation='Synthetic correction for '.$invoice->ref;
  $entry->debit=max(0,$amount);$entry->credit=max(0,-$amount);$entry->montant=abs($amount);$entry->sens=$amount<0?'C':'D';$entry->piece_num=$piece;$entry->code_journal='SYND-'.$run;$entry->journal_label='Synthetic deduction corrections';
  spAssert($entry->createStd($user)>0,'Native deduction correction failed: '.$entry->error);
 }
 foreach($mixed as $case){$o=$case['invoice'];$net=$case['net'];$share=$case['share'];
  $source=$db->fetch_object(spSql('SELECT total_ht,total_tva,total_ttc FROM '.$p.'facture_fourn WHERE rowid='.(int)$o->id));$initial=spMixedTotals((int)$o->id);
  $piece=(int)$db->fetch_object(spSql('SELECT COALESCE(MAX(piece_num),0)+1 AS n FROM '.$p.'accounting_bookkeeping WHERE entity='.$entity))->n;
  spDeductionLine($o,$piece,'4000',$net*0.52*(1-$share));foreach($rateAccounts as $rate=>$number){spDeductionLine($o,$piece,$number,-$net*$rate/100*(1-$share));}
  $after=spMixedTotals((int)$o->id,array($piece));spAssert(abs($after['4000']-($net*3+$net*0.52*(1-$share)))<0.00001,'Non-deducted cost wrong');
  foreach($rateAccounts as $rate=>$number){spAssert(abs($after[$number]-$net*$rate/100*$share)<0.00001,'Deduction fraction or credit sign wrong');}
  $now=$db->fetch_object(spSql('SELECT total_ht,total_tva,total_ttc FROM '.$p.'facture_fourn WHERE rowid='.(int)$o->id));spAssert((array)$source===(array)$now&&$initial===spMixedTotals((int)$o->id),'Correction rewrote source or initial purchase voucher');
 }
 echo "PASS native separate correction vouchers: 50/0 percent deduction across 25/15/12 and credits; source and initial entries unchanged\n";
 // NPR is a different price-calculation mode, not a deduction percentage.
 $npr=new FactureFournisseur($db);$npr->socid=$supplier->id;$npr->ref_supplier='SYNNPR-'.$run;$npr->date=dol_mktime(12,0,0,1,10,2026);$npr->multicurrency_code='NOK';$npr->multicurrency_tx=1;
 spAssert($npr->create($user)>0,'NPR draft failed');spAssert($npr->addline('Synthetic NPR mode',100,25,0,0,1,0,0,0,0,$accounts['4000'],1)>0,'NPR draft line failed');spAssert($npr->fetch($npr->id)>0,'NPR draft reload');
 spAssert(abs($npr->total_ht-100)<0.00001&&abs($npr->total_tva-25)<0.00001&&abs($npr->total_ttc-100)<0.00001,'Reassess NPR price semantics');
 foreach(array('nb_NO'=>'Fransk NPR-ordning','en_US'=>'French NPR scheme') as $locale=>$expected){$translation=new Translate('',$conf);$translation->setDefaultLang($locale);$translation->load('bills');$translation->load('main');
  foreach(array('NonPercuRecuperable','VATNPR') as $key){spAssert($translation->trans($key)===$expected,'Ambiguous NPR translation remains');}
 }
 $before=(int)$db->fetch_object(spSql('SELECT COUNT(*) AS n FROM '.$p.'accounting_bookkeeping WHERE entity='.$entity))->n;$body=spHttp($path);spHttp($path,$params+array('token'=>spToken($body),'action'=>'writebookkeeping'));
 spAssert((int)$db->fetch_object(spSql('SELECT COUNT(*) AS n FROM '.$p.'accounting_bookkeeping WHERE entity='.$entity))->n===$before,'Mixed invoice or manual correction retransfer duplicated ledger');
 spAssert((int)$db->fetch_object(spSql('SELECT COUNT(*) AS n FROM '.$p.'norway_tax_evidence WHERE entity='.$entity))->n===$evidenceBefore,'Mixed purchases created extra history');
 echo "PASS NPR changes gross price; bilingual label corrected, no global gross-only mode, no extra history or duplicate transfer\n";
}finally{curl_close($curl);@unlink($cookie);}
