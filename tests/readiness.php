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
print "PASS Norwegian organisation-number format and checksum\n";
