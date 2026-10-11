<?php
// SPDX-License-Identifier: GPL-3.0-or-later
if (PHP_SAPI !== 'cli' || getenv('NORWAY_SYNTHETIC_TEST') !== '1'
    || getenv('DOLI_DB_NAME') !== 'norway_test') { exit(1); }
require '/var/www/html/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/norway/class/NorwayAccountingSetup.php';
function insistSetup($condition, $message) { if (!$condition) { throw new RuntimeException($message); } }
function setupSql($sql) { global $db; $result = $db->query($sql); insistSetup($result !== false, 'Synthetic setup query failed: '.$db->lasterror()); return $result; }
function indexedSetup($rows) { return array_column($rows, null, 'key'); }
$prefix = $db->prefix();
$entity = 987654;
$version = 'SYN-SETUP-'.bin2hex(random_bytes(4));
$db->begin();
try {
    setupSql("INSERT INTO ".$prefix."accounting_system (fk_country,pcg_version,label,active) VALUES (173,'".$version."','Synthetic setup only',1)");
    $chart = $db->last_insert_id($prefix.'accounting_system');
    setupSql("INSERT INTO ".$prefix."accounting_system (fk_country,pcg_version,label,active) VALUES (173,'".$version."-OTHER','Synthetic other chart',1)");
    foreach (array(array('1500', $entity, $version, 1), array('2700', $entity, $version, 0),
        array('2400', $entity + 1, $version, 1), array('3000', $entity, $version.'-OTHER', 1)) as $fixture) {
        setupSql("INSERT INTO ".$prefix."accounting_account (entity,fk_pcg_version,pcg_type,account_number,label,active) VALUES ("
            .$fixture[1].",'".$fixture[2]."','SYN','".$fixture[0]."','Synthetic account',".$fixture[3].")");
    }
    foreach (array(array(2, $entity, 1, 'SYN-SALES'), array(3, $entity + 1, 1, 'SYN-FOREIGN'),
        array(4, $entity, 0, 'SYN-INACTIVE'), array(1, $entity, 1, '')) as $fixture) {
        setupSql("INSERT INTO ".$prefix."accounting_journal (entity,code,label,nature,active) VALUES ("
            .$fixture[1].",'".$fixture[3]."','Synthetic journal',".$fixture[0].",".$fixture[2].")");
    }
    $settings = array('ACCOUNTING_ACCOUNT_CUSTOMER' => '1500', 'ACCOUNTING_ACCOUNT_SUPPLIER' => '2400',
        'ACCOUNTING_SERVICE_SOLD_ACCOUNT' => '3000', 'ACCOUNTING_VAT_SOLD_ACCOUNT' => '2700');
    $service = new NorwayAccountingSetup($db, $entity);
    $report = indexedSetup($service->report($chart, $settings));
    insistSetup(count($report) === 14 && $report['Chart']['status'] === 'Available', 'Selected chart not found');
    insistSetup($report['Accounts']['value'] === '1 / 2', 'Counts leaked across chart/entity');
    insistSetup($report['ACCOUNTING_ACCOUNT_CUSTOMER']['status'] === 'Available', 'Active account missed');
    insistSetup($report['ACCOUNTING_ACCOUNT_SUPPLIER']['status'] === 'Missing', 'Other entity account leaked');
    insistSetup($report['ACCOUNTING_SERVICE_SOLD_ACCOUNT']['status'] === 'Missing', 'Other chart account leaked');
    insistSetup($report['ACCOUNTING_VAT_SOLD_ACCOUNT']['status'] === 'Inactive', 'Inactive account passed');
    insistSetup($report['ACCOUNTING_SERVICE_BUY_ACCOUNT']['status'] === 'Unset', 'Unset default treated as available');
    insistSetup($report['SalesJournal']['status'] === 'Available', 'Active sales journal missed');
    foreach (array('PurchaseJournal', 'BankJournal', 'GeneralJournal') as $key) {
        insistSetup($report[$key]['status'] === 'Missing', 'Foreign/inactive/empty journal counted');
    }
    $missing = indexedSetup($service->report(0, $settings));
    insistSetup($missing['Chart']['status'] === 'Missing' && $missing['ACCOUNTING_ACCOUNT_CUSTOMER']['status'] === 'Missing', 'Missing chart accepted');
    setupSql("UPDATE ".$prefix."accounting_system SET active=0 WHERE rowid=".(int) $chart);
    insistSetup(indexedSetup($service->report($chart, $settings))['Chart']['status'] === 'Inactive', 'Inactive chart accepted');
    setupSql("UPDATE ".$prefix."accounting_system SET active=1,fk_country=NULL WHERE rowid=".(int) $chart);
    insistSetup(indexedSetup($service->report($chart, $settings))['Chart']['status'] === 'Review', 'Unknown country passed');
    $settings['ACCOUNTING_ACCOUNT_CUSTOMER'] = "1500' OR 1=1 --";
    insistSetup(indexedSetup($service->report($chart, $settings))['ACCOUNTING_ACCOUNT_CUSTOMER']['status'] === 'Missing', 'Account number not escaped');
} finally { $db->rollback(); }
$failedDb = new class {
    public function prefix() { return 'synthetic_'; }
    public function query($sql) { return false; }
};
try { (new NorwayAccountingSetup($failedDb, 1))->report(1, array()); throw new LogicException('Database failure hidden'); }
catch (RuntimeException $expected) {}
try { new NorwayAccountingSetup($db, 0); throw new LogicException('Invalid entity accepted'); }
catch (InvalidArgumentException $expected) {}
print "PASS accounting setup chart/entity isolation, inactive/missing defaults, journal scope and database errors\n";
