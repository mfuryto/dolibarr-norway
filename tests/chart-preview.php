<?php
// SPDX-License-Identifier: GPL-3.0-or-later
if (PHP_SAPI !== 'cli' || getenv('NORWAY_SYNTHETIC_TEST') !== '1'
    || getenv('DOLI_DB_NAME') !== 'norway_test') { exit(1); }
require '/var/www/html/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/norway/class/NorwayChartPreview.php';
function chartAssert($value, $message) { if (!$value) { throw new RuntimeException($message); } }
function chartSql($sql) { global $db; $result = $db->query($sql); chartAssert($result !== false, 'Synthetic chart query failed: '.$db->lasterror()); return $result; }
$header = "account_number;label\n";
$parsed = NorwayChartPreview::parse("\xEF\xBB\xBF".$header.'1500;"Syntetisk; konto ""A"""');
chartAssert($parsed[0]['label'] === 'Syntetisk; konto "A"', 'Quoted CSV or BOM failed');
foreach (array('', $header, "account_number,label\n1500;Test", $header."1500;A\n1500;B", $header.'0150;A',
    $header.'1500;', $header.'1500;"Unclosed', $header.'1500;A;B', $header."1500;A\tB",
    $header.'1500;'.str_repeat('x',256), $header."1500;\xFF", str_repeat('x',262145),
    $header.implode("\n", array_map(static function ($n) { return $n.';Synthetic'; }, range(1000,2000)))) as $bad) {
    try { NorwayChartPreview::parse($bad); throw new LogicException('Invalid CSV accepted'); }
    catch (InvalidArgumentException $expected) {}
}
chartAssert(count(NorwayChartPreview::parse($header.implode("\n", array_map(static function ($n) { return $n.';Synthetic'; }, range(1000,1999))))) === 1000, '1000-row boundary failed');
$prefix = $db->prefix(); $entity = (int) $conf->entity;
$version = 'SYN-PRE-'.bin2hex(random_bytes(4));
$db->begin();
try {
    chartSql("INSERT INTO ".$prefix."accounting_system (fk_country,pcg_version,label,active) VALUES (173,'".$version."','Synthetic preview only',1)");
    $chart = $db->last_insert_id($prefix.'accounting_system');
    chartSql("INSERT INTO ".$prefix."accounting_system (fk_country,pcg_version,label,active) VALUES (173,'".$version."-OTHER','Synthetic other',1)");
    foreach (array(array('1500',$entity,$version,1,'Synthetic A'), array('2700',$entity,$version,0,'Synthetic B'),
        array('7771',999999,$version,1,'Foreign entity'), array('7772',$entity,$version.'-OTHER',1,'Other chart')) as $row) {
        chartSql("INSERT INTO ".$prefix."accounting_account (entity,fk_pcg_version,pcg_type,account_number,label,active) VALUES ("
            .$row[1].",'".$row[2]."','SYN','".$row[0]."','".$row[4]."',".$row[3].")");
    }
    $csv = $header."1500;Synthetic A\n2700;Synthetic B\n7771;New\n7772;New\n3000;Historical\n8888;New";
    $result = NorwayChartPreview::report($db,$entity,$chart,$csv);
    $rows = array_column($result['rows'],null,'number');
    chartAssert($rows[1500]['status'] === 'Keep' && $rows[1500]['usage'] > 0, 'Used matching account not preserved');
    chartAssert($rows[2700]['status'] === 'Inactive', 'Inactive account missed');
    chartAssert($rows[7771]['status'] === 'New' && $rows[7772]['status'] === 'New', 'Entity/chart leakage');
    chartAssert($rows[3000]['status'] === 'Historical' && $rows[3000]['usage'] > 0, 'Historical number not flagged');
    chartAssert($rows[8888]['status'] === 'New', 'New account missed');
    $conflict = NorwayChartPreview::report($db,$entity,$chart,$header.'1500;Different');
    chartAssert($conflict['rows'][0]['status'] === 'Conflict' && $conflict['rows'][0]['existing_label'] === 'Synthetic A', 'Used name conflict missed');
    chartAssert(NorwayChartPreview::report($db,$entity,$chart,$csv) === $result, 'Repeated preview differs or changes accounts');
    foreach (array(0, $chart) as $id) {
        if ($id) { chartSql("UPDATE ".$prefix."accounting_system SET active=0 WHERE rowid=".(int)$chart); }
        try { NorwayChartPreview::report($db,$entity,$id,$csv); throw new LogicException('Missing/inactive chart accepted'); }
        catch (InvalidArgumentException $expected) { chartAssert($expected->getMessage() === 'Chart', 'Wrong chart error'); }
    }
    chartSql("UPDATE ".$prefix."accounting_system SET active=1,fk_country=NULL WHERE rowid=".(int)$chart);
    try { NorwayChartPreview::report($db,$entity,$chart,$csv); throw new LogicException('Unknown country accepted'); }
    catch (InvalidArgumentException $expected) {}
} finally { $db->rollback(); }
$failedDb = new class { public function prefix() { return 'synthetic_'; } public function query($sql) { return false; } };
try { NorwayChartPreview::report($failedDb,1,1,$header.'1500;Synthetic'); throw new LogicException('DB failure hidden'); }
catch (RuntimeException $expected) {}
print "PASS chart preview CSV limits/quotes, repeatability, chart/entity isolation, conflicts and historical usage\n";
