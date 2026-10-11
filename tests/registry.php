<?php
// SPDX-License-Identifier: GPL-3.0-or-later
if (PHP_SAPI !== 'cli' || getenv('NORWAY_SYNTHETIC_TEST') !== '1') { exit(1); }
require '/var/www/html/custom/norway/class/NorwayRegistry.php';
$number = '123456785';
$fixture = array('organisasjonsnummer' => $number, 'navn' => 'SYNTHETIC', 'registrertIMvaregisteret' => true);
foreach (array(true, false) as $vat) {
    $fixture['registrertIMvaregisteret'] = $vat;
    $result = NorwayRegistry::parse(200, json_encode($fixture), $number);
    if ($result['vat'] !== $vat || $result['number'] !== $number) { throw new LogicException('VAT boolean changed'); }
}
$cases = array(array(404, '', 'NotFound'), array(410, '', 'Removed'), array(500, '', 'Unavailable'),
    array(200, '<html>', 'Unavailable'), array(200, '{}', 'Unavailable'),
    array(200, json_encode(array_merge($fixture, array('organisasjonsnummer' => '000000000'))), 'Unavailable'),
    array(200, json_encode(array_merge($fixture, array('registrertIMvaregisteret' => 'false'))), 'Unavailable'),
    array(200, json_encode(array_merge($fixture, array('registrertIMvaregisteret' => null))), 'Unavailable'),
    array(200, json_encode(array_merge($fixture, array('slettedato' => '2026-01-01'))), 'Deleted'),
    array(200, str_repeat(' ', 262145), 'Unavailable'));
foreach ($cases as $case) {
    try { NorwayRegistry::parse($case[0], $case[1], $number); throw new LogicException('Invalid registry response accepted'); }
    catch (RuntimeException $error) { if ($error->getMessage() !== $case[2]) { throw $error; } }
}
if (NorwayRegistry::organisationNumber('123 456 785', 'NO') !== $number) { throw new LogicException('Normalisation failed'); }
foreach (array(array('123456784', 'NO'), array($number, 'SE'), array('https://localhost', 'NO')) as $case) {
    try { NorwayRegistry::fetch($case[0], $case[1]); throw new LogicException('Invalid lookup accepted'); }
    catch (RuntimeException $error) { if ($error->getMessage() !== 'Invalid') { throw $error; } }
}
print "PASS registry true/false, missing/malformed/mismatched/deleted/removed responses and invalid pre-network lookup\n";
