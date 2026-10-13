<?php
// SPDX-License-Identifier: GPL-3.0-or-later
if(PHP_SAPI!=='cli'||getenv('NORWAY_SYNTHETIC_TEST')!=='1'||getenv('DOLI_DB_NAME')!=='norway_test'){exit(1);}
require '/var/www/html/master.inc.php';require_once DOL_DOCUMENT_ROOT.'/custom/norway/class/NorwayPurchaseAllocation.php';
function pcAssert($ok,$message){if(!$ok){throw new RuntimeException($message);}}
function pcFixture($rate=25,$vatAccount='2710'){
 $basis=array('source_complete'=>true,'invoice'=>array('entity'=>1,'ref'=>'SYNBUY','type'=>0,'fk_statut'=>1,'datef'=>'2026-01-01','total_ht'=>'1000','total_tva'=>(string)(10*$rate),'total_ttc'=>(string)(1000+10*$rate),'multicurrency_code'=>'NOK','multicurrency_tx'=>'1','vat_reverse_charge'=>'0','revenuestamp'=>'0','localtax1'=>'0','localtax2'=>'0'),
 'party'=>array('country'=>'NO'),'settings'=>array(array('entity'=>1,'name'=>'MAIN_INFO_SOCIETE_COUNTRY','value'=>'173:NO:Norway')),
 'lines'=>array(array('rowid'=>11,'fk_code_ventilation'=>1,'bound_number'=>'4000','tva_tx'=>(string)$rate,'info_bits'=>'0','localtax1_tx'=>'0','localtax2_tx'=>'0','total_localtax1'=>'0','total_localtax2'=>'0','total_ht'=>'1000','total_tva'=>(string)(10*$rate))));
 $ledger=array('truncated'=>false,'rows'=>array());$obs=array();foreach(array(array('2400','0',(string)(1000+10*$rate)),array('4000','1000','0'),array($vatAccount,(string)(10*$rate),'0')) as $i=>$value){$row=(object)array('rowid'=>$i+1,'code_journal'=>'SYN','piece_num'=>1,'doc_date'=>'2026-01-01','doc_ref'=>'SYNBUY','fk_docdet'=>0,'numero_compte'=>$value[0],'debit'=>$value[1],'credit'=>$value[2],'trace'=>'Aggregated');$ledger['rows'][]=$row;$b=$basis;$b['posting']=(array)$row+array('doc_type'=>'supplier_invoice');$obs[]=array('status'=>'Same','data'=>array('basis'=>$b));}return array($obs,$ledger);
}
foreach(array(array(25,'2710'),array(15,'2711'),array(12,'2713')) as $case){[$obs,$ledger]=pcFixture($case[0],$case[1]);pcAssert(NorwayPurchaseAllocation::evaluate($obs,$ledger,'purchase'.$case[0].'full')['status']==='Grouped','Reduced/full profile failed');}
[$obs,$ledger]=pcFixture();$ledger['rows'][1]->fk_docdet=11;$obs[1]['data']['basis']['posting']['fk_docdet']=11;pcAssert(NorwayPurchaseAllocation::evaluate($obs,$ledger,'purchase25full')['status']==='Match','Direct retained line failed');
foreach($obs as &$entry){$entry['status']='Changed';}unset($entry);$result=NorwayPurchaseAllocation::evaluate($obs,$ledger,'purchase25full');pcAssert($result['status']==='Match'&&$result['source_changed'],'Present sources replaced retained purchase');
foreach(array('missing','split','removed','foreign','currency','marker','reverse','bound','rate','drift','partial') as $case){[$o,$l]=pcFixture();
 if($case==='missing'){$o[0]['data']=null;}
 if($case==='split'){$o[0]['data']['basis']['invoice']['total_ht']='999';}
 if($case==='removed'){$o[0]['status']='Removed';}
 if($case==='drift'){$l['rows'][1]->debit='999';}
 if($case==='partial'){$l['rows'][1]->debit='1125';$l['rows'][2]->debit='125';foreach($l['rows'] as $i=>$row){$o[$i]['data']['basis']['posting']=(array)$row+array('doc_type'=>'supplier_invoice');}}
 foreach($o as &$e){if(!$e['data']){continue;}$b=&$e['data']['basis'];if($case==='foreign'){$b['party']['country']='SE';}if($case==='currency'){$b['invoice']['multicurrency_code']='EUR';}if($case==='marker'){$b['lines'][0]['info_bits']='1';}if($case==='reverse'){$b['invoice']['vat_reverse_charge']='1';}if($case==='bound'){$b['lines'][0]['bound_number']='';}if($case==='rate'){$b['lines'][0]['tva_tx']='0';}}unset($e,$b);
 pcAssert(NorwayPurchaseAllocation::evaluate($o,$l,'purchase25full')['status']===($case==='partial'?'Different':'Review'),'Unsafe full comparison: '.$case);
}
[$o,$l]=pcFixture();$l['rows'][1]->debit='1250';$l['rows'][2]->debit='0';foreach($l['rows'] as $i=>$row){$o[$i]['data']['basis']['posting']=(array)$row+array('doc_type'=>'supplier_invoice');}pcAssert(NorwayPurchaseAllocation::evaluate($o,$l,'purchase25full')['status']==='Different','Balanced VAT-as-cost defect hidden');
[$o,$l]=pcFixture();foreach($l['rows'] as $row){$d=$row->debit;$row->debit=$row->credit;$row->credit=$d;}foreach($o as $i=>&$e){$b=&$e['data']['basis'];$b['invoice']['type']=2;foreach(array('total_ht','total_tva','total_ttc') as $key){$b['invoice'][$key]='-'.$b['invoice'][$key];}foreach(array('total_ht','total_tva') as $key){$b['lines'][0][$key]='-'.$b['lines'][0][$key];}$b['posting']=(array)$l['rows'][$i]+array('doc_type'=>'supplier_invoice');}unset($e,$b);pcAssert(NorwayPurchaseAllocation::evaluate($o,$l,'purchase25full')['status']==='Grouped','Purchase credit sign failed');
// Correct total purchase amount must not conceal a swap between bound accounts.
[$o,$l]=pcFixture();$l['rows'][1]->debit='600';$extra=clone $l['rows'][1];$extra->rowid=4;$extra->numero_compte='6000';$extra->debit='400';$l['rows'][]=$extra;
foreach($o as &$e){$e['data']['basis']['lines'][0]['total_ht']='600';$e['data']['basis']['lines'][0]['total_tva']='150';$second=$e['data']['basis']['lines'][0];$second['rowid']=12;$second['fk_code_ventilation']=2;$second['bound_number']='6000';$second['total_ht']='400';$second['total_tva']='100';$e['data']['basis']['lines'][]=$second;}unset($e);
$e=$o[0];$e['data']['basis']['posting']=(array)$extra+array('doc_type'=>'supplier_invoice');$o[]=$e;foreach($l['rows'] as $i=>$row){$o[$i]['data']['basis']['posting']=(array)$row+array('doc_type'=>'supplier_invoice');}
pcAssert(NorwayPurchaseAllocation::evaluate($o,$l,'purchase25full')['status']==='Grouped','Multiple bound purchase accounts failed');
$l['rows'][1]->debit='400';$l['rows'][3]->debit='600';foreach($l['rows'] as $i=>$row){$o[$i]['data']['basis']['posting']=(array)$row+array('doc_type'=>'supplier_invoice');}pcAssert(NorwayPurchaseAllocation::evaluate($o,$l,'purchase25full')['status']==='Different','Swapped purchase bindings hidden by totals');
print "PASS explicit full-deduction purchase comparison: 25/15/12, direct/grouped lines, credits, no inferred partial deduction, drift, missing/split/foreign sources and balanced VAT defects\n";
