<?php
// SPDX-License-Identifier: GPL-3.0-or-later
if (PHP_SAPI !== 'cli') { exit(1); }
require '/var/www/html/custom/norway/class/NorwayInvoiceCheck.php';
function check($condition, $message) { if (!$condition) { throw new RuntimeException($message); } }
$seller = (object) array('name'=>'SYNTHETIC ONLY', 'country_code'=>'NO', 'idprof1'=>'123456785', 'address'=>'Synthetic', 'zip'=>'0000', 'town'=>'Synthetic');
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

require '/var/www/html/custom/norway/class/NorwayInvoiceValidation.php';
$seller->idprof1 = '123456785'; $seller->tva_assuj = 1;
$invoice->multicurrency_code = 'NOK'; $invoice->lines[0]->desc = 'Software';
$invoice->type = 0; $invoice->date_lim_reglement = 2;
$invoice->lines[0]->tva_tx = 25; $invoice->total_tva = '25.00000000';
check(NorwayInvoiceValidation::missing($invoice,$seller,$customer) === array(), 'Complete registered invoice blocked');
$seller->tva_assuj = 0;
check(in_array('UnregisteredVAT', NorwayInvoiceValidation::missing($invoice,$seller,$customer), true), 'VAT accepted with registration disabled');
$invoice->lines[0]->tva_tx = 0; $invoice->total_tva = 0;
check(NorwayInvoiceValidation::missing($invoice,$seller,$customer) === array(), 'Unregistered zero-VAT invoice rejected');
$invoice->lines[0]->desc = ''; $invoice->lines[0]->product_label = 'Software';
check(NorwayInvoiceValidation::missing($invoice,$seller,$customer) === array(), 'Product label not recognised');
$invoice->type = 2; $invoice->total_tva = -25;
check(NorwayInvoiceValidation::missing($invoice,$seller,$customer,$source) === array(), 'Historical linked VAT credit blocked after deregistration');
check(in_array('CreditSource', NorwayInvoiceValidation::missing($invoice,$seller,$customer), true), 'Credit without source accepted');
print "PASS minimum invoice validation, unregistered VAT guard and historical linked credit exception\n";

$invoice->fk_facture_source=0;$invoice->ref_ext='OLD-2025-0042';
check(NorwayInvoiceCheck::evaluate($invoice,$seller,$customer)['CreditSource']==='Present','External original reference rejected');
$invoice->fk_facture_source=31;
check(NorwayInvoiceCheck::evaluate($invoice,$seller,$customer,$source)['CreditSource']==='Missing','External reference bypassed invalid internal source');
$invoice->fk_facture_source=0;
foreach(array('',"OLD\n42",'<b>OLD42</b>',str_repeat('x',256)) as $bad){$invoice->ref_ext=$bad;check(NorwayInvoiceCheck::evaluate($invoice,$seller,$customer)['CreditSource']==='Missing','Invalid external reference accepted');}
print "PASS external original reference, internal source priority, invalid characters and bounded length\n";
