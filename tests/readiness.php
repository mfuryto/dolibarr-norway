<?php
// SPDX-License-Identifier: GPL-3.0-or-later
if (PHP_SAPI !== 'cli') {
    exit(1);
}
require '/var/www/html/custom/norway/class/NorwayReadiness.php';
function expect($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
// Artificial numeric fixtures are not claims about registered businesses.
expect(NorwayReadiness::validOrganisationNumber('123456785'), 'Checksum fixture');
expect(NorwayReadiness::validOrganisationNumber('123 456 785'), 'Space formatting');
foreach (array('', '000000000', '123456786', '1234567850', 'NO123456785MVA', '<script>', "123456785\n", '123456785e0') as $value) {
    expect(!NorwayReadiness::validOrganisationNumber($value), 'Invalid organisation number accepted');
}
// Remainder 1 yields forbidden check digit 10; remainder 0 yields 0.
expect(!NorwayReadiness::validOrganisationNumber('100000030'), 'Forbidden check digit accepted');
expect(NorwayReadiness::validOrganisationNumber('100000040'), 'Zero check digit rejected');
$company = array('name' => 'SYNTHETIC ONLY', 'idprof1' => '123456785', 'country_code' => 'NO',
    'currency' => 'NOK', 'address' => 'Synthetic address', 'zip' => '0000', 'town' => 'Synthetic');
$rates = array();
foreach (array(25, 15, 12) as $rate) {
    $rates[] = array('taux' => $rate, 'active' => 1, 'type_vat' => 0);
}
expect(!in_array(false, NorwayReadiness::evaluate($company, $rates), true), 'Complete configuration flagged');
$missing = NorwayReadiness::evaluate(array(), array());
expect(!$missing['OrganisationNumber'] && !$missing['Country'] && !$missing['Currency'] && !$missing['Address'] && !$missing['Rate25'], 'Missing configuration passed');
$rates[0]['type_vat'] = 1;
expect(!NorwayReadiness::evaluate($company, $rates)['Rate25'], 'Sales-only rate passed for purchases');
$rates[] = array('taux' => 25, 'active' => 0, 'type_vat' => 2);
expect(!NorwayReadiness::evaluate($company, $rates)['Rate25'], 'Inactive purchase rate accepted');
$rates[] = array('taux' => 25, 'active' => 1, 'type_vat' => 2);
expect(NorwayReadiness::evaluate($company, $rates)['Rate25'], 'Separate purchase and sales rates rejected');
$rates[] = array('taux' => 14, 'active' => 1, 'type_vat' => 0);
expect(!NorwayReadiness::evaluate($company, $rates)['HistoricalRates'], 'Legacy rate missed');
$rates[count($rates)-1]['active'] = 0;
expect(NorwayReadiness::evaluate($company, $rates)['HistoricalRates'], 'Inactive historical rate flagged');
print "PASS organisation checksum, incomplete settings, purchase/sales coverage and historical rates\n";
