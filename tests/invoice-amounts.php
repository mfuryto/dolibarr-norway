<?php
// SPDX-License-Identifier: GPL-3.0-or-later
if (PHP_SAPI !== 'cli') { exit(1); }
require '/var/www/html/custom/norway/class/NorwayInvoiceAmounts.php';
function amountCheck($condition, $message) { if (!$condition) { throw new RuntimeException($message); } }
$invoice = (object) array('total_ht'=>300, 'total_tva'=>25, 'lines'=>array(
    (object) array('id'=>1, 'total_ht'=>'100.00000000','total_tva'=>'25.00000000','tva_tx'=>'25'),
    (object) array('id'=>2, 'total_ht'=>200,'total_tva'=>0,'tva_tx'=>0)));
$before=serialize($invoice);
$r=NorwayInvoiceAmounts::report($invoice);
amountCheck($r['status']==='Match' && $r['net']===300.0 && $r['rows'][1]['status']==='ZeroReview', 'Mixed rates/zero VAT comparison failed');
amountCheck(serialize($invoice)===$before, 'Read-only report mutated invoice');
$invoice->total_tva=25.01;
amountCheck(NorwayInvoiceAmounts::report($invoice)['status']==='Match','One-cent tolerance rejected');
$invoice->total_tva=25.02;
amountCheck(NorwayInvoiceAmounts::report($invoice)['status']==='Different','Difference not detected');
$invoice->total_ht=-300; $invoice->total_tva=-25;
foreach ($invoice->lines as $line) { $line->total_ht=-$line->total_ht; $line->total_tva=-$line->total_tva; }
amountCheck(NorwayInvoiceAmounts::report($invoice)['status']==='Match','Signed credit totals changed');
$invoice->lines[0]->total_tva='unknown';
$r=NorwayInvoiceAmounts::report($invoice);
amountCheck($r['status']==='Invalid' && $r['vat']===null, 'Invalid line amount treated as zero');
$invoice->lines[0]->total_tva=INF;
amountCheck(NorwayInvoiceAmounts::report($invoice)['status']==='Invalid', 'Infinite amount accepted');
$invoice->lines[0]->total_tva=25; unset($invoice->total_ht);
amountCheck(NorwayInvoiceAmounts::report($invoice)['status']==='Invalid', 'Missing header accepted');
$invoice->lines=array();
amountCheck(NorwayInvoiceAmounts::report($invoice)['status']==='Missing', 'Missing lines approved');
print "PASS stored invoice amounts, mixed rates, signed credit totals, rounding and invalid inputs\n";
$line=(object)array('vat_src_code'=>'SYN<25>', 'info_bits'=>'8');
$before=serialize($line); $e=NorwayInvoiceAmounts::evidence($line);
amountCheck($e['code']==='SYN<25>' && $e['code_state']==='Recorded' && $e['flags']===8, 'Stored source evidence changed or inferred');
amountCheck(serialize($line)===$before,'Evidence inspection mutated input');
$line->vat_src_code='0'; $line->info_bits=0;
amountCheck(NorwayInvoiceAmounts::evidence($line)['code_state']==='Recorded','Zero-string code discarded');
$line->vat_src_code=null;
amountCheck(NorwayInvoiceAmounts::evidence($line)['code_state']==='Missing','Empty stored code misclassified');
unset($line->vat_src_code);
amountCheck(NorwayInvoiceAmounts::evidence($line)['code_state']==='Unknown','Unavailable source code treated as empty');
foreach (array(null,-1,'abc',1.5,true) as $value) {
    $line->info_bits=$value;
    amountCheck(NorwayInvoiceAmounts::evidence($line)['flags']===null,'Invalid marker treated as known');
}
print "PASS stored source codes, markers, missing/unavailable evidence and read-only preservation\n";

require '/var/www/html/custom/norway/class/NorwayTaxReference.php';
foreach (array('0','1','3','52','92') as $code) {
    $line=(object)array('vat_src_code'=>$code,'info_bits'=>0);
    $before=serialize($line);
    amountCheck(NorwayTaxReference::inspect(NorwayInvoiceAmounts::evidence($line))==='Listed','Official identifier not recognised');
    amountCheck(serialize($line)===$before,'Reference comparison changed source');
}
foreach (array('03','3 ',' 3','3.0','25','SYN<25>','NO_DEDUCTION') as $code) {
    amountCheck(NorwayTaxReference::inspect(array('code_state'=>'Recorded','code'=>$code))==='Unlisted','Local code automatically normalised or inferred');
}
foreach (array('Missing','Unknown') as $state) {
    amountCheck(NorwayTaxReference::inspect(array('code_state'=>$state,'code'=>'3'))==='Unavailable','Unavailable source treated as mapping');
}
amountCheck(NorwayTaxReference::inspect(array('code_state'=>'Recorded','code'=>3))==='Unlisted','Non-string input matched loosely');
print "PASS exact official reference membership, zero code, local aliases and unavailable sources; no mapping inferred\n";

require '/var/www/html/custom/norway/class/NorwayTaxMappingPreview.php';
$plan=NorwayTaxMappingPreview::plan('LOCAL25','3','2026-01-01','2026-12-31');
$e=array('code_state'=>'Recorded','code'=>'LOCAL25','flags'=>0);
$before=serialize(array($plan,$e));
foreach (array('2026-01-01','2026-06-30','2026-12-31') as $date) {
    amountCheck(NorwayTaxMappingPreview::compare($plan,$date,$e)==='Candidate','Inclusive mapping interval failed');
}
foreach (array('2025-12-31','2027-01-01') as $date) {
    amountCheck(NorwayTaxMappingPreview::compare($plan,$date,$e)==='Outside','Mapping leaked outside interval');
}
amountCheck(NorwayTaxMappingPreview::compare($plan,'',$e)==='UnknownDate','Missing invoice date accepted');
amountCheck(serialize(array($plan,$e))===$before,'Preview mutated plan or evidence');
$e['code']='LOCAL25 ';
amountCheck(NorwayTaxMappingPreview::compare($plan,'2026-06-30',$e)==='OtherCode','Local code normalised');
$e['code']='LOCAL25'; $e['flags']=8;
amountCheck(NorwayTaxMappingPreview::compare($plan,'2026-06-30',$e)==='Markers','Special marker mapped');
$e['flags']=null;
amountCheck(NorwayTaxMappingPreview::compare($plan,'2026-06-30',$e)==='Markers','Unavailable marker mapped');
$e['flags']=0; $e['code_state']='Missing';
amountCheck(NorwayTaxMappingPreview::compare($plan,'2026-06-30',$e)==='Unavailable','Missing source mapped');
$zero=NorwayTaxMappingPreview::plan('0','0','2026-01-01','2026-01-01');
amountCheck(NorwayTaxMappingPreview::compare($zero,'2026-01-01',array('code_state'=>'Recorded','code'=>'0','flags'=>0))==='Candidate','Zero identifier discarded');
foreach (array(
 array('','3','2026-01-01','2026-12-31'),array('LOCAL25','03','2026-01-01','2026-12-31'),
 array('LOCAL25','3','2026-02-30','2026-12-31'),array('LOCAL25','3','2027-01-01','2026-12-31'),
 array('LOCAL25','3','','2026-12-31'),array(str_repeat('x',11),'3','2026-01-01','2026-12-31'),
 array("x\n",'3','2026-01-01','2026-12-31'),array(array(),'3','2026-01-01','2026-12-31')
) as $input) {
    $blocked=false; try { NorwayTaxMappingPreview::plan(...$input); } catch (InvalidArgumentException $error) { $blocked=true; }
    amountCheck($blocked,'Invalid candidate plan accepted');
}
$plan['target']='INVALID'; $blocked=false;
try { NorwayTaxMappingPreview::compare($plan,'2026-06-30',$e); } catch (InvalidArgumentException $error) { $blocked=true; }
amountCheck($blocked,'Forged plan bypassed validation');
print "PASS unsaved mapping preview, exact local codes, inclusive dates, markers, missing fields and unchanged inputs\n";
