<?php
// SPDX-License-Identifier: GPL-3.0-or-later
if (PHP_SAPI !== 'cli') { exit(1); }
require '/var/www/html/custom/norway/class/NorwayInvoiceCheck.php';
function check($condition, $message) { if (!$condition) { throw new RuntimeException($message); } }
$seller = (object) array('name'=>'SYNTHETIC ONLY', 'idprof1'=>'123456785', 'address'=>'Synthetic', 'zip'=>'0000', 'town'=>'Synthetic');
$customer = clone $seller;
$invoice = (object) array('id'=>10, 'entity'=>1, 'socid'=>20, 'type'=>0, 'statut'=>1, 'date'=>1,
    'date_lim_reglement'=>2, 'multicurrency_code'=>'NOK', 'lines'=>array((object) array('desc'=>'Software licence')));
$before = serialize(array($invoice, $seller, $customer));
$report = NorwayInvoiceCheck::evaluate($invoice, $seller, $customer);
check(!in_array('Missing', $report, true), 'Complete fixture marked missing');
foreach (array('Numbering','VAT','PDF','Delivery') as $key) { check($report[$key] === 'Review', 'Unverified field approved'); }
check(serialize(array($invoice,$seller,$customer)) === $before, 'Read-only check changed input');
$customer->address = ''; $customer->idprof1 = '';
check(NorwayInvoiceCheck::evaluate($invoice,$seller,$customer)['Customer'] === 'Missing', 'Missing customer identity accepted');
$customer->idprof1 = 'FOREIGN-ID';
check(NorwayInvoiceCheck::evaluate($invoice,$seller,$customer)['Customer'] === 'Present', 'Foreign identifier rejected');
$seller->idprof1 = '123456786';
check(NorwayInvoiceCheck::evaluate($invoice,$seller,$customer)['SellerOrganisation'] === 'Missing', 'Bad seller checksum accepted');
$invoice->multicurrency_code = 'EUR'; $invoice->lines[0]->desc = '<p>&nbsp;</p>';
$report = NorwayInvoiceCheck::evaluate($invoice,$seller,$customer);
check($report['Currency'] === 'Missing' && $report['Descriptions'] === 'Missing', 'Invalid currency or blank rich text accepted');
$invoice->type = 2; $invoice->fk_facture_source = 30; $invoice->date_lim_reglement = null;
$source = (object) array('id'=>30, 'entity'=>1, 'socid'=>20, 'statut'=>1, 'type'=>0);
check(NorwayInvoiceCheck::evaluate($invoice,$seller,$customer,$source)['CreditSource'] === 'Present', 'Valid credit source rejected');
foreach (array('entity'=>2, 'socid'=>21, 'statut'=>0, 'type'=>2, 'id'=>31) as $field=>$value) {
    $invalid = clone $source; $invalid->$field=$value;
    check(NorwayInvoiceCheck::evaluate($invoice,$seller,$customer,$invalid)['CreditSource'] === 'Missing', 'Invalid credit source accepted: '.$field);
}
$report = NorwayInvoiceCheck::evaluate($invoice,$seller,$customer);
check($report['CreditSource'] === 'Missing' && $report['DueDate'] === 'Review', 'Credit note without source or due date misclassified');
print "PASS invoice presence checks, credit source isolation and mandatory manual review\n";
