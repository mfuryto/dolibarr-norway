<?php
// SPDX-License-Identifier: GPL-3.0-or-later
if (PHP_SAPI!=='cli' || getenv('NORWAY_SYNTHETIC_TEST')!=='1' || getenv('DOLI_DB_NAME')!=='norway_test') { exit(1); }
require '/var/www/html/master.inc.php';require_once DOL_DOCUMENT_ROOT.'/custom/norway/class/NorwayInvoiceAllocation.php';
function iaAssert($ok,$message){if(!$ok){throw new RuntimeException($message);}}
$h=(object)array('type'=>0,'fk_statut'=>1,'datef'=>'2026-01-01','multicurrency_code'=>'NOK','total_ht'=>'1000','total_tva'=>'250','total_ttc'=>'1250','buyer_country'=>'NO','seller_country'=>'NO');
$line=(object)array('rowid'=>1,'total_ht'=>'1000','total_tva'=>'250','tva_tx'=>'25','info_bits'=>'0','localtax1_tx'=>'0','localtax2_tx'=>'0');
$l=array('truncated'=>false,'rows'=>array());
foreach(array(array('1500','1250','0'),array('3000','0','1000'),array('2700','0','250')) as $i=>$a){$l['rows'][]=(object)array('rowid'=>$i+1,'numero_compte'=>$a[0],'debit'=>$a[1],'credit'=>$a[2],'trace'=>'Aggregated','doc_date'=>'2026-01-01','code_journal'=>'SYN','piece_num'=>1);}
iaAssert(NorwayInvoiceAllocation::evaluate($h,array($line),$l,'domestic25')['status']==='Match','Correct allocation failed');
$l['rows'][1]->credit='1250';$l['rows'][2]->credit='0';
iaAssert(NorwayInvoiceReconciliation::evaluate($h,$l)['status']==='Match','Balanced defect fixture invalid');
iaAssert(NorwayInvoiceAllocation::evaluate($h,array($line),$l,'domestic25')['status']==='Different','VAT posted as sales hidden by balanced gross');
$l['rows'][1]->credit='1000';$l['rows'][2]->credit='250';$l['rows'][2]->numero_compte='2701';iaAssert(NorwayInvoiceAllocation::evaluate($h,array($line),$l,'domestic25')['status']==='Different','Wrong VAT account accepted');
$l['rows'][2]->numero_compte='2700';$l['rows'][0]->numero_compte='9999';iaAssert(NorwayInvoiceAllocation::evaluate($h,array($line),$l,'domestic25')['status']==='Different','Unknown account accepted');$l['rows'][0]->numero_compte='1500';
foreach(array(array(15,'2701','3030','150'),array(12,'2703','3050','120')) as $a){$h->total_tva=$line->total_tva=$a[3];$h->total_ttc=(string)(1000+(int)$a[3]);$line->tva_tx=(string)$a[0];$l['rows'][0]->debit=$h->total_ttc;$l['rows'][1]->numero_compte=$a[2];$l['rows'][2]->numero_compte=$a[1];$l['rows'][2]->credit=$a[3];iaAssert(NorwayInvoiceAllocation::evaluate($h,array($line),$l,'domestic'.$a[0])['status']==='Match','Reduced rate allocation failed');}
$h->type=2;$h->total_ht=$line->total_ht='-1000';$h->total_tva=$line->total_tva='-120';$h->total_ttc='-1120';foreach($l['rows'] as $r){$d=$r->debit;$r->debit=$r->credit;$r->credit=$d;}
iaAssert(NorwayInvoiceAllocation::evaluate($h,array($line),$l,'domestic12')['status']==='Match','Credit allocation failed');
foreach(array('domestic25','invalid') as $profile){try{NorwayInvoiceAllocation::evaluate($h,array($line),$l,$profile);throw new LogicException('Wrong profile accepted');}catch(InvalidArgumentException $expected){}}
$line->info_bits='1';try{NorwayInvoiceAllocation::evaluate($h,array($line),$l,'domestic12');throw new LogicException('Special tax flag accepted');}catch(InvalidArgumentException $expected){}$line->info_bits='0';
$line->localtax1_tx='1';try{NorwayInvoiceAllocation::evaluate($h,array($line),$l,'domestic12');throw new LogicException('Local tax accepted');}catch(InvalidArgumentException $expected){}$line->localtax1_tx='0';
$h->buyer_country='US';try{NorwayInvoiceAllocation::evaluate($h,array($line),$l,'domestic12');throw new LogicException('Foreign sale accepted');}catch(InvalidArgumentException $expected){}$h->buyer_country='NO';
$l['truncated']=true;try{NorwayInvoiceAllocation::evaluate($h,array($line),$l,'domestic12');throw new LogicException('Truncation accepted');}catch(RuntimeException $expected){}$l['truncated']=false;
$l['rows'][0]->trace='MissingLine';iaAssert(NorwayInvoiceAllocation::evaluate($h,array($line),$l,'domestic12')['status']==='Review','Missing trace accepted');
$boundLines=array((object)array('rowid'=>11,'fk_code_ventilation'=>1,'bound_number'=>'3000','bound_label'=>'Synthetic goods','bound_active'=>1,'total_ht'=>'600'),(object)array('rowid'=>12,'fk_code_ventilation'=>2,'bound_number'=>'3020','bound_label'=>'Synthetic service','bound_active'=>1,'total_ht'=>'400'));
$boundLedger=array('truncated'=>false,'rows'=>array((object)array('numero_compte'=>'3000','debit'=>'0','credit'=>'600','fk_docdet'=>11,'trace'=>'Linked'),(object)array('numero_compte'=>'3020','debit'=>'0','credit'=>'400','fk_docdet'=>12,'trace'=>'Linked')));
iaAssert(NorwayInvoiceAllocation::lineAccounts($boundLines,$boundLedger,'domestic25')['status']==='Match','Direct line bindings failed');
$boundLedger['rows'][0]->credit='400';$boundLedger['rows'][1]->credit='600';iaAssert(NorwayInvoiceAllocation::lineAccounts($boundLines,$boundLedger,'domestic25')['status']==='Different','Same sales total concealed wrong account allocation');
$boundLedger['rows'][0]->credit='600';$boundLedger['rows'][1]->credit='400';$boundLedger['rows'][0]->fk_docdet=12;$boundLedger['rows'][1]->fk_docdet=11;iaAssert(NorwayInvoiceAllocation::lineAccounts($boundLines,$boundLedger,'domestic25')['status']==='Different','Wrong source line reference accepted');
foreach($boundLedger['rows'] as $row){$row->fk_docdet=0;$row->trace='Aggregated';}iaAssert(NorwayInvoiceAllocation::lineAccounts($boundLines,$boundLedger,'domestic25')['status']==='Grouped','Aggregated rows approved as individual lines');
$boundLines[0]->fk_code_ventilation=0;$boundLines[0]->bound_number='';iaAssert(NorwayInvoiceAllocation::lineAccounts($boundLines,$boundLedger,'domestic25')['status']==='Review','Missing/foreign account binding accepted');$boundLines[0]->fk_code_ventilation=1;$boundLines[0]->bound_number='3000';
$boundLines[0]->bound_active=0;iaAssert(NorwayInvoiceAllocation::lineAccounts($boundLines,$boundLedger,'domestic25')['status']==='Grouped','Inactive historical binding discarded');
foreach($boundLines as $line){$line->total_ht='-'. $line->total_ht;}foreach($boundLedger['rows'] as $row){$row->debit=$row->credit;$row->credit='0';}iaAssert(NorwayInvoiceAllocation::lineAccounts($boundLines,$boundLedger,'domestic25')['status']==='Grouped','Bound credit note sign failed');
$p=$db->prefix();$entity=(int)$conf->entity;$r=$db->query('SELECT DISTINCT f.rowid,f.type FROM '.$p."facture f JOIN ".$p."accounting_bookkeeping b ON b.fk_doc=f.rowid AND b.entity=f.entity AND b.doc_type='customer_invoice' WHERE f.entity=".$entity." AND f.type IN (0,2) AND f.fk_statut IN (1,2) AND f.multicurrency_code='NOK' AND f.total_tva<>0");
iaAssert((bool)$r,'Core fixture read failed');$count=0;$credit=false;
while($f=$db->fetch_object($r)){$report=NorwayInvoiceAllocation::report($db,$entity,(int)$f->rowid,'domestic25');iaAssert(in_array($report['status'],array('Match','Grouped'),true) && in_array($report['line_accounts']['status'],array('Match','Grouped'),true),'Actual core allocation mismatch');$count++;$credit=$credit || (int)$f->type===2;}
iaAssert($count>=3 && $credit,'Core posting fixtures missing');
try{NorwayInvoiceAllocation::report($db,$entity+1,1,'domestic25');throw new LogicException('Foreign entity accepted');}catch(InvalidArgumentException $expected){}
$db->begin();try{NorwayInvoiceAllocation::report($db,$entity,1,'domestic25');throw new LogicException('Outer transaction consumed');}catch(RuntimeException $expected){}finally{$db->rollback();}
$r=$db->query("SELECT b.rowid,b.fk_doc FROM ".$p."accounting_bookkeeping b JOIN ".$p."facture f ON f.rowid=b.fk_doc AND f.entity=b.entity WHERE b.entity=".$entity." AND b.doc_type='customer_invoice' AND f.total_tva<>0 AND f.type IN (0,2) AND f.fk_statut IN (1,2) AND f.multicurrency_code='NOK' ORDER BY b.rowid LIMIT 1");$fixture=$db->fetch_object($r);
$r=$db->query('SHOW COLUMNS FROM '.$p.'accounting_bookkeeping');$columns=array();while($column=$db->fetch_object($r)){if($column->Field!=='rowid'){$columns[]=$column->Field;}}$names=implode(',',$columns);$extraId=0;
try{
 iaAssert((bool)$db->query('INSERT INTO '.$p.'accounting_bookkeeping ('.$names.') SELECT '.$names.' FROM '.$p.'accounting_bookkeeping WHERE rowid='.(int)$fixture->rowid),'Extra voucher fixture failed');$extraId=(int)$db->last_insert_id($p.'accounting_bookkeeping');
 iaAssert((bool)$db->query('UPDATE '.$p.'accounting_bookkeeping SET fk_doc=0 WHERE rowid='.$extraId),'Extra source fixture failed');
 try{NorwayInvoiceAllocation::report($db,$entity,(int)$fixture->fk_doc,'domestic25');throw new LogicException('Incomplete voucher approved');}catch(RuntimeException $expected){}
}finally{if($extraId){iaAssert((bool)$db->query('DELETE FROM '.$p.'accounting_bookkeeping WHERE rowid='.$extraId),'Fixture cleanup failed');}}
$failing=new class{public function query($sql){return false;}};
try{NorwayInvoiceAllocation::report($failing,$entity,1,'domestic25');throw new LogicException('Read error hidden');}catch(RuntimeException $expected){}
$r=$db->query('SELECT rowid,fk_code_ventilation FROM '.$p.'facturedet WHERE fk_facture='.(int)$fixture->fk_doc.' AND fk_code_ventilation>0 ORDER BY rowid LIMIT 1');$binding=$db->fetch_object($r);iaAssert((bool)$binding,'Bound source fixture missing');$foreignAccount=0;
try{
 iaAssert((bool)$db->query('INSERT INTO '.$p.'accounting_account (account_number,entity,fk_pcg_version,pcg_type,label,active) SELECT account_number,987646,fk_pcg_version,pcg_type,label,active FROM '.$p.'accounting_account WHERE rowid='.(int)$binding->fk_code_ventilation),'Foreign binding fixture failed');$foreignAccount=(int)$db->last_insert_id($p.'accounting_account');
 iaAssert((bool)$db->query('UPDATE '.$p.'facturedet SET fk_code_ventilation='.$foreignAccount.' WHERE rowid='.(int)$binding->rowid),'Foreign link fixture failed');
 $report=NorwayInvoiceAllocation::report($db,$entity,(int)$fixture->fk_doc,'domestic25');iaAssert($report['status']==='Review' && $report['line_accounts']['status']==='Review','Foreign account binding leaked into source comparison');
}finally{
 iaAssert((bool)$db->query('UPDATE '.$p.'facturedet SET fk_code_ventilation='.(int)$binding->fk_code_ventilation.' WHERE rowid='.(int)$binding->rowid),'Binding restore failed');
 if($foreignAccount){iaAssert((bool)$db->query('DELETE FROM '.$p.'accounting_account WHERE rowid='.$foreignAccount),'Foreign fixture cleanup failed');}
}
print "PASS posted allocation, balanced wrong VAT/revenue, wrong/unknown accounts, reduced rates, credit signs, scope/trace guards and actual core postings\n";
