<?php
// SPDX-License-Identifier: GPL-3.0-or-later
if (PHP_SAPI !== 'cli' || getenv('NORWAY_SYNTHETIC_TEST') !== '1') { exit(1); }
require '/var/www/html/custom/norway/class/NorwayIndicativeEuro.php';
function verify($value) { if (!$value) { throw new RuntimeException('Indicative pricing assertion failed'); } }
$header = "FREQ;BASE_CUR;QUOTE_CUR;TENOR;UNIT_MULT;TIME_PERIOD;OBS_VALUE\n";
$csv = $header."B;EUR;NOK;SP;0;2026-10-09;10\nB;EUR;NOK;SP;0;2026-10-08;11\nB;EUR;NOK;SP;0;2026-10-12;15\nB;USD;NOK;SP;0;2026-10-10;9\n";
$rate = NorwayIndicativeEuro::parse($csv, '2026-10-11');
verify($rate['date'] === '2026-10-09' && $rate['nok_per_eur'] === 10.0);
verify(NorwayIndicativeEuro::estimate('1000', $rate['nok_per_eur']) === 100.0);
verify(NorwayIndicativeEuro::estimate('0', 10) === 0.0);
foreach (array('', '<html>Bad gateway</html>', $header."B;EUR;NOK;SP;0;2026-09-01;10\n", $header."B;EUR;NOK;SP;0;2026-10-09;0\n", $header."B;EUR;NOK;SP;2;2026-10-09;1000\n") as $bad) {
    try { NorwayIndicativeEuro::parse($bad, '2026-10-11'); throw new LogicException('Bad rate accepted'); }
    catch (RuntimeException $expected) {}
}
foreach (array('-1', 'NaN', '<script>', '1000000001') as $bad) {
    try { NorwayIndicativeEuro::estimate($bad, 10); throw new LogicException('Bad amount accepted'); }
    catch (InvalidArgumentException $expected) {}
}
print "PASS indicative EUR rate direction, date, weekend, stale/malformed data and amount validation\n";
