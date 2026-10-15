<?php
// SPDX-License-Identifier: GPL-3.0-or-later
if (PHP_SAPI!=='cli' || getenv('NORWAY_SYNTHETIC_TEST')!=='1' || getenv('DOLI_DB_NAME')!=='norway_test') { exit(1); }
require '/var/www/html/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/norway/class/NorwaySalesZeroVat.php';
function zvAssert($ok,$message) { if (!$ok) { throw new RuntimeException($message); } }
$entity=(int)$conf->entity;
$line=array('rowid'=>1,'vat_src_code'=>'SYN-ZERO','tva_tx'=>'0.000','total_ht'=>'100.00','total_tva'=>'0.000','info_bits'=>'0','localtax1_tx'=>'0','localtax2_tx'=>'0');
$rule=array('revision'=>1,'user'=>1,'at'=>'2026-01-01T00:00:00+00:00','source'=>'SYN-ZERO','target'=>'5','start'=>'2026-01-01','end'=>'2026-12-31','direction'=>'sale','id'=>1,'retire'=>false);
$record=array('version'=>1,'entity'=>$entity,'org'=>'123456785','reference'=>NorwayTaxReference::SOURCE,'events'=>array($rule));
$basis=array('source_complete'=>true,'posting'=>array('entity'=>$entity,'doc_type'=>'customer_invoice'),
 'invoice'=>array('entity'=>$entity,'datef'=>'2026-01-01','type'=>'0','multicurrency_code'=>'NOK'),
 'settings'=>array(array('entity'=>$entity,'name'=>'MAIN_INFO_SIREN','value'=>'123456785'),array('entity'=>$entity,'name'=>'MAIN_INFO_SOCIETE_COUNTRY','value'=>'173:NO:NORWAY'),array('entity'=>$entity,'name'=>NorwayTaxMappings::SETTING,'value'=>json_encode($record))), 'lines'=>array($line));
foreach (array('5'=>'DomesticExempt','6'=>'OutsideLaw','7'=>'NotTurnover','51'=>'DomesticReverse','52'=>'Export') as $target=>$category) {
 $r=$record;$r['events'][0]['target']=(string)$target;$b=$basis;$b['settings'][2]['value']=json_encode($r);
 $report=NorwaySalesZeroVat::captured($b,$entity);$row=$report['rows'][0];
 zvAssert($row['category']===$category && $row['status']===(in_array((string)$target,array('51','52'),true)?'SeparateScenario':'Stated'),'Explicit zero sales categories conflated');
}
$encoded=json_encode($basis);$context=NorwayPostingTaxContext::evaluate($basis,$entity);$saved=NorwaySalesZeroVat::captured($basis,$entity);
zvAssert(json_encode($basis)===$encoded && NorwayPostingTaxContext::evaluate($basis,$entity)===$context,'Existing observation interpretation mutated');
$b=$basis;$b['invoice']['type']='2';$b['lines'][0]['total_ht']='-100.00';zvAssert(NorwaySalesZeroVat::captured($b,$entity)['rows'][0]['status']==='Stated','Credit zero VAT rejected');
foreach (array('3','31','32','33') as $target) { zvAssert(NorwaySalesZeroVat::line($line,$target,true)['status']==='Conflict','Positive VAT code accepted zero rate'); }
foreach (array('0','1','11','85') as $target) { zvAssert(NorwaySalesZeroVat::line($line,$target,true)['status']==='UnsupportedCode','Purchase code accepted as sales exemption'); }
foreach (array('0.001','-0.001','25.00') as $vat) { $l=$line;$l['total_tva']=$vat;zvAssert(NorwaySalesZeroVat::line($l,'5',true)['status']==='Conflict','Zero-rate nonzero VAT hidden by rounding'); }
$l=$line;$l['tva_tx']='25';$l['total_tva']='0';zvAssert(NorwaySalesZeroVat::line($l,'3',true)['status']==='NotApplicable','Zero VAT money inferred zero rate');
zvAssert(NorwaySalesZeroVat::line($l,'5',true)['status']==='Conflict','Zero-rate mapping accepted positive rate');
foreach (array(null,true,'0e0','NaN','INF','') as $bad) { $l=$line;$l['tva_tx']=$bad;zvAssert(NorwaySalesZeroVat::line($l,'5',true)['status']==='Invalid','Invalid rate accepted'); }
foreach (array('info_bits','localtax1_tx','localtax2_tx') as $field) { foreach (array(null,'1') as $bad) { $l=$line;$l[$field]=$bad;zvAssert(NorwaySalesZeroVat::line($l,'5',true)['status']==='Markers','Missing or special marker accepted'); } }
foreach (array('date','code','purchase','identity','global') as $case) {
 $b=$basis;$r=$record;
 if ($case==='date') { $b['invoice']['datef']='2027-01-01'; }
 if ($case==='code') { $b['lines'][0]['vat_src_code']='5'; }
 if ($case==='purchase') { $r['events'][0]['direction']='purchase'; }
 if ($case==='identity') { $r['org']='987654321'; }
 $b['settings'][2]['value']=json_encode($r);if ($case==='global') { $b['settings'][2]['entity']=0; }
 zvAssert(NorwaySalesZeroVat::captured($b,$entity)['rows'][0]['status']==='Unresolved','Missing explicit captured basis guessed: '.$case);
}
foreach (array('currency','type','supplier','incomplete') as $case) {
 $b=$basis;if ($case==='currency') { $b['invoice']['multicurrency_code']='EUR'; }if ($case==='type') { $b['invoice']['type']='1'; }
 if ($case==='supplier') { $b['posting']['doc_type']='supplier_invoice'; }if ($case==='incomplete') { $b['source_complete']=false; }
 zvAssert(NorwaySalesZeroVat::captured($b,$entity)['status']==='Unsupported','Unsupported basis accepted');
}
$invoice=(object)array('entity'=>$entity,'type'=>0,'multicurrency_code'=>'NOK','lines'=>array((object)($line+array('id'=>1))));
$comparison=array('rows'=>array(array('status'=>'Candidate','rule'=>array('target'=>'6'))));
zvAssert(NorwaySalesZeroVat::invoice($invoice,$comparison)['rows'][0]['category']==='OutsideLaw','Current explicitly selected mapping ignored');
$comparison['rows'][0]['status']='UnknownDate';zvAssert(NorwaySalesZeroVat::invoice($invoice,$comparison)['rows'][0]['status']==='Unresolved','Unknown invoice date accepted');
zvAssert(NorwaySalesZeroVat::invoice($invoice,array('rows'=>array()))['status']==='Unsupported','Missing comparison coverage accepted');
foreach (array(array(),array($line,$line),array(array_replace($line,array('rowid'=>0)))) as $lines) {
 $b=$basis;$b['lines']=$lines;zvAssert(NorwaySalesZeroVat::captured($b,$entity)['status']==='Unsupported','Incomplete/duplicate line coverage accepted');
}
$b=$basis;$b['invoice']['entity']=$entity+1;zvAssert(NorwaySalesZeroVat::captured($b,$entity)['status']==='Unsupported','Foreign captured entity accepted');
// Current setup changes cannot change the result from a retained basis.
$conf->global->NORWAY_TAX_MAPPINGS_V1='changed current setup';zvAssert(NorwaySalesZeroVat::captured($basis,$entity)===$saved,'Current configuration rewrote retained zero-rate classification');
print "PASS explicit sales zero VAT categories, conflicts, rounding distinction, credits, markers, invalid values, date/direction/identity scope and unchanged captured semantics\n";
