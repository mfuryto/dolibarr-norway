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
