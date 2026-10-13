<?php
// SPDX-License-Identifier: GPL-3.0-or-later
if (PHP_SAPI!=='cli' || getenv('NORWAY_SYNTHETIC_TEST')!=='1' || getenv('DOLI_DB_NAME')!=='norway_test') { exit(1); }
require '/var/www/html/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/norway/class/NorwayPostingRegistrationContext.php';
require_once DOL_DOCUMENT_ROOT.'/custom/norway/class/NorwayInvoiceValidation.php';
function prAssert($ok,$message) { if (!$ok) { throw new RuntimeException($message); } }
$entity=(int)$conf->entity;
$record=array('version'=>1,'entity'=>$entity,'org'=>'123456785','start'=>'2024-02-29','confirmed_by'=>1,'confirmed_at'=>'2026-01-01T00:00:00+00:00');
$basis=array('source_complete'=>true,'posting'=>array('entity'=>$entity),'invoice'=>array('entity'=>$entity,'datef'=>'2024-02-28','type'=>0),
 'settings'=>array(array('entity'=>$entity,'name'=>'MAIN_INFO_SIREN','value'=>'123456785'),array('entity'=>$entity,'name'=>'MAIN_INFO_SOCIETE_COUNTRY','value'=>'173:NO:NORWAY'),array('entity'=>$entity,'name'=>NorwayVatRegistration::SETTING,'value'=>json_encode($record))));
foreach(array('2024-02-28'=>'Before','2024-02-29'=>'After','2024-03-01'=>'After') as $date=>$status) {
 $b=$basis;$b['invoice']['datef']=$date;$r=NorwayPostingRegistrationContext::evaluate($b,$entity);
 prAssert($r['status']===$status && $r['start']==='2024-02-29' && $r['confirmed_by']===1,'Registration date boundary failed');
}
foreach(array('missing'=>'Missing','global'=>'Missing','corrupt'=>'Invalid','identity'=>'Identity','foreign'=>'Invalid','date'=>'Invalid','timestamp'=>'Invalid','duplicate'=>'Unavailable','incomplete'=>'Unavailable','invoice'=>'Unavailable') as $case=>$status) {
 $b=$basis;$r=$record;
 if($case==='missing'){array_pop($b['settings']);}
 if($case==='global'){$b['settings'][2]['entity']=0;}
 if($case==='corrupt'){$b['settings'][2]['value']='bad';}
 if($case==='identity'){$r['org']='974760673';$b['settings'][2]['value']=json_encode($r);}
 if($case==='foreign'){$r['entity']=$entity+1;$b['settings'][2]['value']=json_encode($r);}
 if($case==='date'){$r['start']='2023-02-29';$b['settings'][2]['value']=json_encode($r);}
 if($case==='timestamp'){$r['confirmed_at']='2026-02-30T00:00:00+00:00';$b['settings'][2]['value']=json_encode($r);}
 if($case==='duplicate'){$b['settings'][]=$b['settings'][2];}
 if($case==='incomplete'){$b['source_complete']=false;}
 if($case==='invoice'){$b['invoice']['datef']='2024-02-30';}
 prAssert(NorwayPostingRegistrationContext::evaluate($b,$entity)['status']===$status,'Unsafe registration accepted: '.$case);
}
$b=$basis;$b['invoice']['type']=2;$credit=NorwayPostingRegistrationContext::evaluate($b,$entity);prAssert($credit['credit'] && $credit['status']==='Before','Credit lost historical review indication');
$saved=NorwayPostingRegistrationContext::evaluate($basis,$entity);$b=$basis;$r=$record;$r['start']='2024-02-01';$b['settings'][2]['value']=json_encode($r);
prAssert(NorwayPostingRegistrationContext::evaluate($b,$entity)['status']==='After' && $saved['status']==='Before','Current registration altered saved comparison');
// Native warning selection uses stored evidence; all fixture writes remain in an outer transaction.
$db->begin();
try {
 $p=$db->prefix();$name=NorwayVatRegistration::SETTING;
 prAssert((bool)$db->query("DELETE FROM ".$p."const WHERE entity=".$entity." AND name='".$name."'"),'Fixture cleanup failed');
 prAssert((bool)$db->query("INSERT INTO ".$p."const (name,value,type,entity) VALUES ('".$name."','".$db->escape(json_encode($record))."','chaine',".$entity.")"),'Fixture insert failed');
 $invoice=(object)array('type'=>0,'date'=>dol_mktime(12,0,0,2,28,2024),'total_tva'=>25,'lines'=>array());$seller=(object)array('idprof1'=>'123456785');
 prAssert(NorwayInvoiceValidation::registrationWarning($db,$invoice,$seller,$entity)==='Before','Native warning omitted prior date');
 $invoice->date=dol_mktime(12,0,0,2,29,2024);prAssert(NorwayInvoiceValidation::registrationWarning($db,$invoice,$seller,$entity)==='','Inclusive date warned incorrectly');
 $invoice->type=2;prAssert(NorwayInvoiceValidation::registrationWarning($db,$invoice,$seller,$entity)==='Credit','Credit warning missing');
 $invoice->total_tva=0;prAssert(NorwayInvoiceValidation::registrationWarning($db,$invoice,$seller,$entity)==='','Untaxed credit warned');
 $invoice->type=0;$invoice->total_tva=25;$seller->idprof1='974760673';prAssert(NorwayInvoiceValidation::registrationWarning($db,$invoice,$seller,$entity)==='Identity','Identity mismatch hidden');
} finally { $db->rollback(); }
print "PASS preserved registration dates, leap boundaries, credits, identity, missing/corrupt/global records, incomplete sources and native warning selection\n";
