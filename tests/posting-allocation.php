<?php
// SPDX-License-Identifier: GPL-3.0-or-later
if(PHP_SAPI!=='cli' || getenv('NORWAY_SYNTHETIC_TEST')!=='1' || getenv('DOLI_DB_NAME')!=='norway_test'){exit(1);}
require '/var/www/html/master.inc.php';require_once DOL_DOCUMENT_ROOT.'/custom/norway/class/NorwayPostingAllocation.php';
function paAssert($ok,$message){if(!$ok){throw new RuntimeException($message);}}
$basis=array('source_complete'=>true,'invoice'=>array('entity'=>1,'ref'=>'SYN','type'=>0,'fk_statut'=>1,'multicurrency_code'=>'NOK','datef'=>'2026-01-01','total_ht'=>'1000','total_tva'=>'250','total_ttc'=>'1250'),
 'party'=>array('country'=>'NO'),'settings'=>array(array('entity'=>1,'name'=>'MAIN_INFO_SOCIETE_COUNTRY','value'=>'173:NO:Norway')),
 'lines'=>array(array('rowid'=>11,'fk_code_ventilation'=>1,'bound_number'=>'3000','bound_label'=>'Synthetic','tva_tx'=>'25','info_bits'=>'0','localtax1_tx'=>'0','localtax2_tx'=>'0','total_ht'=>'1000','total_tva'=>'250')));
$observations=array();$ledger=array('truncated'=>false,'rows'=>array());
foreach(array(array('1500','1250','0'),array('3000','0','1000'),array('2700','0','250')) as $i=>$values){
 $row=(object)array('rowid'=>$i+1,'code_journal'=>'SYN','piece_num'=>1,'doc_date'=>'2026-01-01','doc_ref'=>'SYN','fk_docdet'=>0,'numero_compte'=>$values[0],'debit'=>$values[1],'credit'=>$values[2],'trace'=>'Aggregated');$ledger['rows'][]=$row;
 $b=$basis;$b['posting']=(array)$row;$observations[]=array('status'=>'Same','data'=>array('basis'=>$b));
}
$result=NorwayPostingAllocation::evaluate($observations,$ledger,'domestic25');paAssert($result['status']==='Grouped' && !$result['source_changed'],'Retained grouped allocation failed');
foreach($observations as &$entry){$entry['status']='Changed';}unset($entry);
$result=NorwayPostingAllocation::evaluate($observations,$ledger,'domestic25');paAssert($result['status']==='Grouped' && $result['source_changed'],'Current-source change destroyed retained comparison');
foreach(array('missing','removed','incomplete','split','extra','duplicate','drift','reference','truncated') as $case){
 $o=$observations;$l=unserialize(serialize($ledger));
 if($case==='missing'){$o[0]['data']=null;}
 if($case==='removed'){$o[0]['status']='Removed';}
 if($case==='incomplete'){$o[0]['data']['basis']['source_complete']=false;}
 if($case==='split'){$o[0]['data']['basis']['invoice']['total_ht']='999';}
 if($case==='extra'){array_pop($o);}
 if($case==='duplicate'){$o[1]=$o[0];}
 if($case==='drift'){$l['rows'][1]->credit='999';}
 if($case==='reference'){$l['rows'][1]->doc_ref='OTHER';}
 if($case==='truncated'){$l['truncated']=true;}
 try{$r=NorwayPostingAllocation::evaluate($o,$l,'domestic25');paAssert($r['status']==='Review','Unsafe retained comparison: '.$case);}catch(InvalidArgumentException $e){paAssert($case==='truncated','Unexpected scope error');}
}
$o=$observations;$l=unserialize(serialize($ledger));$l['rows'][1]->credit='1250';$l['rows'][2]->credit='0';foreach($l['rows'] as $i=>$row){$o[$i]['data']['basis']['posting']=(array)$row;}
paAssert(NorwayPostingAllocation::evaluate($o,$l,'domestic25')['status']==='Different','Balanced VAT-as-sales defect hidden');
foreach(array(array(15,'2701','3030','150','1150'),array(12,'2703','3050','120','1120')) as $case){
 $o=$observations;$l=unserialize(serialize($ledger));$l['rows'][0]->debit=$case[4];$l['rows'][1]->numero_compte=$case[2];$l['rows'][2]->numero_compte=$case[1];$l['rows'][2]->credit=$case[3];
 foreach($o as $i=>&$entry){$b=&$entry['data']['basis'];$b['invoice']['total_tva']=$case[3];$b['invoice']['total_ttc']=$case[4];$b['lines'][0]['total_tva']=$case[3];$b['lines'][0]['tva_tx']=(string)$case[0];$b['lines'][0]['bound_number']=$case[2];$b['posting']=(array)$l['rows'][$i];}unset($entry,$b);
 paAssert(NorwayPostingAllocation::evaluate($o,$l,'domestic'.$case[0])['status']==='Grouped','Reduced retained allocation failed');
}
$o=$observations;$l=unserialize(serialize($ledger));foreach($l['rows'] as $row){$d=$row->debit;$row->debit=$row->credit;$row->credit=$d;}
foreach($o as $i=>&$entry){$b=&$entry['data']['basis'];$b['invoice']['type']=2;foreach(array('total_ht','total_tva','total_ttc') as $key){$b['invoice'][$key]='-'.$b['invoice'][$key];}foreach(array('total_ht','total_tva') as $key){$b['lines'][0][$key]='-'.$b['lines'][0][$key];}$b['posting']=(array)$l['rows'][$i];}unset($entry,$b);
paAssert(NorwayPostingAllocation::evaluate($o,$l,'domestic25')['status']==='Grouped','Retained credit signs failed');
print "PASS retained allocation: grouped 25/15/12, credit signs, current-source changes, missing/split/removed observations, drift, references, capacity and balanced VAT defects\n";
