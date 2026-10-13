<?php
// SPDX-License-Identifier: GPL-3.0-or-later
if (PHP_SAPI!=='cli' || getenv('NORWAY_SYNTHETIC_TEST')!=='1' || getenv('DOLI_DB_NAME')!=='norway_test') { exit(1); }
require '/var/www/html/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/norway/class/NorwayPostingTaxContext.php';
function ptAssert($ok,$message) { if (!$ok) { throw new RuntimeException($message); } }
$entity=(int)$conf->entity;
$rule=array('revision'=>1,'user'=>1,'at'=>'2026-01-01T00:00:00+00:00','source'=>'SYNVAT','target'=>'3','start'=>'2026-01-01','end'=>'2026-12-31','direction'=>'sale','id'=>1,'retire'=>false);
$record=array('version'=>1,'entity'=>$entity,'org'=>'123456785','reference'=>NorwayTaxReference::SOURCE,'events'=>array($rule));
$basis=array('source_complete'=>true,'posting'=>array('entity'=>$entity),'invoice'=>array('entity'=>$entity,'datef'=>'2026-01-01'),
 'settings'=>array(array('entity'=>$entity,'name'=>'MAIN_INFO_SIREN','value'=>'123456785'),array('entity'=>$entity,'name'=>'MAIN_INFO_SOCIETE_COUNTRY','value'=>'173:NO:NORWAY'),array('entity'=>$entity,'name'=>NorwayTaxMappings::SETTING,'value'=>json_encode($record))),
 'lines'=>array(array('rowid'=>1,'vat_src_code'=>'SYNVAT','tva_tx'=>'25','info_bits'=>'0','localtax1_tx'=>'0.000','localtax2_tx'=>'0')));
$saved=NorwayPostingTaxContext::evaluate($basis,$entity);
ptAssert($saved['status']==='Linked' && $saved['lines'][0]['target']==='3' && $saved['mapping_revision']===1,'Explicit archived mapping failed');
foreach (array(array('15','31'),array('12','33'),array('0','5')) as $case) {
 $b=$basis;$r=$record;$r['events'][0]['target']=$case[1];$b['settings'][2]['value']=json_encode($r);$b['lines'][0]['tva_tx']=$case[0];
 ptAssert(NorwayPostingTaxContext::evaluate($b,$entity)['lines'][0]['target']===$case[1],'Explicit target was not retained');
}
$b=$basis;$b['lines'][0]['tva_tx']='12';ptAssert(NorwayPostingTaxContext::evaluate($b,$entity)['lines'][0]['target']==='3','Rate incorrectly inferred a mapping');
foreach (array('', '3', ' SYNVAT', 'SYNVAT ') as $code) {
 $b=$basis;$b['lines'][0]['vat_src_code']=$code;ptAssert(NorwayPostingTaxContext::evaluate($b,$entity)['status']==='Unresolved','Missing/direct/normalised code guessed');
}
foreach (array('2025-12-31','2027-01-01','2026-02-30','') as $date) {
 $b=$basis;$b['invoice']['datef']=$date;ptAssert(NorwayPostingTaxContext::evaluate($b,$entity)['status']==='Unresolved','Outside/invalid date accepted');
}
$b=$basis;$b['invoice']['datef']='2026-12-31';ptAssert(NorwayPostingTaxContext::evaluate($b,$entity)['status']==='Linked','Inclusive end rejected');
foreach (array('info_bits','localtax1_tx','localtax2_tx') as $field) {
 $b=$basis;$b['lines'][0][$field]='1';ptAssert(NorwayPostingTaxContext::evaluate($b,$entity)['lines'][0]['status']==='Markers','Special markers accepted');
}
foreach (array('purchase','retired','foreign','identity','reference','duplicate','corrupt','global','incomplete') as $case) {
 $b=$basis;$r=$record;
 if ($case==='purchase') { $r['events'][0]['direction']='purchase'; }
 if ($case==='retired') { $e=$rule;$e['revision']=2;$e['retire']=true;$r['events'][]=$e; }
 if ($case==='foreign') { $r['entity']=$entity+1; }
 if ($case==='identity') { $r['org']='987654321'; }
 if ($case==='reference') { $r['reference']='unsupported'; }
 if ($case==='duplicate') { $e=$rule;$e['id']=2;$e['revision']=2;$r['events'][]=$e; }
 $b['settings'][2]['value']=$case==='corrupt' ? '{invalid' : json_encode($r);
 if ($case==='global') { $b['settings'][2]['entity']=0; }
 if ($case==='incomplete') { $b['source_complete']=false; }
 ptAssert(NorwayPostingTaxContext::evaluate($b,$entity)['status']==='Unresolved','Unsafe mapping accepted: '.$case);
}
$b=$basis;$b['settings'][]=$b['settings'][2];ptAssert(NorwayPostingTaxContext::evaluate($b,$entity)['status']==='Unresolved','Duplicate setup accepted');
$changed=$record;$e=$rule;$e['revision']=2;$e['target']='31';$changed['events'][]=$e;$b=$basis;$b['settings'][2]['value']=json_encode($changed);
$after=NorwayPostingTaxContext::evaluate($b,$entity);ptAssert($after['lines'][0]['target']==='31' && $after['mapping_revision']===2 && $saved['lines'][0]['target']==='3','Correction rewrote previous interpretation');
ptAssert(NorwayPostingTaxContext::evaluate($basis,$entity)===$saved,'Current settings affected captured context');
// Purchase context is versioned separately; sales interpretation remains unchanged.
$purchase=$basis;$purchase['posting']['doc_type']='supplier_invoice';$purchase['invoice']['vat_reverse_charge']='0';$purchase['invoice']['revenuestamp']='0.000';
$r=$record;$r['events'][0]['direction']='purchase';$r['events'][0]['target']='1';$purchase['settings'][2]['value']=json_encode($r);
$resolved=NorwayPostingTaxContext::evaluatePurchase($purchase,$entity);
ptAssert($resolved['version']===2 && $resolved['direction']==='purchase' && $resolved['status']==='Linked' && $resolved['lines'][0]['target']==='1','Purchase mapping resolution failed');
ptAssert(NorwayPostingTaxContext::evaluate($purchase,$entity)['status']==='Unresolved','Purchase setup applied to sales');
$onlySale=$purchase;$onlySale['settings'][2]['value']=json_encode($record);ptAssert(NorwayPostingTaxContext::evaluatePurchase($onlySale,$entity)['status']==='Unresolved','Sale setup applied to purchases');
foreach(array('vat_reverse_charge','revenuestamp') as $field){$b=$purchase;$b['invoice'][$field]='1';ptAssert(NorwayPostingTaxContext::evaluatePurchase($b,$entity)['status']==='Unresolved','Special purchase header approved');unset($b['invoice'][$field]);ptAssert(NorwayPostingTaxContext::evaluatePurchase($b,$entity)['status']==='Unresolved','Missing purchase header field approved');}
foreach(array('info_bits','localtax1_tx','localtax2_tx') as $field){$b=$purchase;$b['lines'][0][$field]='1';ptAssert(NorwayPostingTaxContext::evaluatePurchase($b,$entity)['lines'][0]['status']==='Markers','Purchase special line approved');}
foreach(array('2025-12-31','2027-01-01','2026-02-30') as $date){$b=$purchase;$b['invoice']['datef']=$date;ptAssert(NorwayPostingTaxContext::evaluatePurchase($b,$entity)['status']==='Unresolved','Purchase date scope ignored');}
$b=$purchase;$b['lines'][0]['vat_src_code']='1';ptAssert(NorwayPostingTaxContext::evaluatePurchase($b,$entity)['status']==='Unresolved','Purchase code inferred without explicit mapping');
$b=$purchase;$b['posting']['doc_type']='customer_invoice';ptAssert(NorwayPostingTaxContext::evaluatePurchase($b,$entity)['status']==='Unresolved','Purchase interpretation accepted sales source');
ptAssert(NorwayPostingTaxContext::evaluate($basis,$entity)===$saved,'Purchase support changed sales v1 interpretation');
print "PASS captured mapping resolution: exact codes, directions, inclusive dates, explicit targets, markers, identity, corrupt/overlapping setup, no global fallback, corrections and incomplete sources\n";
