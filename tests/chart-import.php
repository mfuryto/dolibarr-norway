<?php
// SPDX-License-Identifier: GPL-3.0-or-later
if (PHP_SAPI !== 'cli' || getenv('NORWAY_SYNTHETIC_TEST') !== '1' || getenv('DOLI_DB_NAME') !== 'norway_test') { exit(1); }
require '/var/www/html/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/norway/class/NorwayChartImport.php';
function importAssert($condition, $message) { if (!$condition) { throw new RuntimeException($message); } }
function importSql($sql) { global $db; $result=$db->query($sql); importAssert($result !== false,'Synthetic import query failed: '.$db->lasterror()); return $result; }
importAssert($user->fetch('', 'testadmin') > 0 && $user->admin, 'Synthetic admin missing');
$prefix=$db->prefix(); $entity=(int)$conf->entity;
$version='SYN-IMP-'.bin2hex(random_bytes(4));
$previous=$db->fetch_object(importSql("SELECT value FROM ".$prefix."const WHERE name='CHARTOFACCOUNTS' AND entity=".$entity))->value;
importSql("INSERT INTO ".$prefix."accounting_system (fk_country,pcg_version,label,active) VALUES (173,'".$version."','Synthetic import only',1)");
$chart=$db->last_insert_id($prefix.'accounting_system');
importSql("UPDATE ".$prefix."const SET value='".(int)$chart."' WHERE name='CHARTOFACCOUNTS' AND entity=".$entity);
$csv="account_number;label\n8801;Synthetic A\n8802;Synthetic B";
try {
    $report=NorwayChartPreview::report($db,$entity,$chart,$csv);
    $fingerprint=NorwayChartImport::fingerprint($entity,$chart,$report);
    importAssert(NorwayChartImport::apply($db,$entity,$chart,$csv,$fingerprint,$user)===2,'Initial import failed');
    $report=NorwayChartPreview::report($db,$entity,$chart,$csv);
    importAssert(NorwayChartImport::apply($db,$entity,$chart,$csv,NorwayChartImport::fingerprint($entity,$chart,$report),$user)===0,'Repeat import duplicated accounts');
    try { NorwayChartImport::apply($db,$entity,$chart,$csv,$fingerprint,$user); throw new LogicException('Stale preview accepted'); }
    catch (InvalidArgumentException $expected) {}
    $conflict="account_number;label\n8801;Changed\n8803;Synthetic C";
    $report=NorwayChartPreview::report($db,$entity,$chart,$conflict);
    try { NorwayChartImport::apply($db,$entity,$chart,$conflict,NorwayChartImport::fingerprint($entity,$chart,$report),$user); throw new LogicException('Conflict accepted'); }
    catch (InvalidArgumentException $expected) {}
    $account=$db->fetch_object(importSql("SELECT label FROM ".$prefix."accounting_account WHERE entity=".$entity." AND fk_pcg_version='".$version."' AND account_number='8801'"));
    importAssert($account->label==='Synthetic A','Existing account changed');
    $count=$db->fetch_object(importSql("SELECT COUNT(*) AS total FROM ".$prefix."accounting_account WHERE entity=".$entity." AND fk_pcg_version='".$version."'"));
    importAssert((int)$count->total===2,'Blocked import partially inserted');
    // Fail the second core account insert, after the first has been written.
    $failing=new class($db) {
        private $db; private $inserts=0;
        public function __construct($db) { $this->db=$db; }
        public function __call($name,$args) { return $this->db->$name(...$args); }
        public function __get($name) { return $this->db->$name; }
        public function query($sql, ...$args) {
            if (strpos($sql,'INSERT INTO '.$this->db->prefix().'accounting_account ')===0 && ++$this->inserts===2) { return false; }
            return $this->db->query($sql,...$args);
        }
    };
    $failureCsv="account_number;label\n8811;Synthetic rollback A\n8812;Synthetic rollback B";
    $report=NorwayChartPreview::report($db,$entity,$chart,$failureCsv);
    try { NorwayChartImport::apply($failing,$entity,$chart,$failureCsv,NorwayChartImport::fingerprint($entity,$chart,$report),$user); throw new LogicException('Injected failure accepted'); }
    catch (RuntimeException $expected) {}
    $count=$db->fetch_object(importSql("SELECT COUNT(*) AS total FROM ".$prefix."accounting_account WHERE entity=".$entity." AND fk_pcg_version='".$version."'"));
    importAssert((int)$count->total===2,'Mid-import failure left partial account');
    importSql("UPDATE ".$prefix."const SET value='".$db->escape($previous)."' WHERE name='CHARTOFACCOUNTS' AND entity=".$entity);
    try { NorwayChartImport::apply($db,$entity,$chart,$failureCsv,NorwayChartImport::fingerprint($entity,$chart,$report),$user); throw new LogicException('Changed selected chart accepted'); }
    catch (InvalidArgumentException $expected) {}
    try { NorwayChartImport::apply($db,$entity+1,$chart,$csv,$fingerprint,$user); throw new LogicException('Other entity accepted'); }
    catch (InvalidArgumentException $expected) {}
    $failedBegin = new class { public function begin() { return 0; } };
    try { NorwayChartImport::apply($failedBegin,$entity,$chart,$csv,$fingerprint,$user); throw new LogicException('Failed transaction start accepted'); }
    catch (RuntimeException $expected) {}
    $ordinary=clone $user; $ordinary->admin=0;
    try { NorwayChartImport::apply($db,$entity,$chart,$csv,$fingerprint,$ordinary); throw new LogicException('Non-admin accepted'); }
    catch (InvalidArgumentException $expected) {}
} finally {
    $db->rollback();
    importSql("UPDATE ".$prefix."const SET value='".$db->escape($previous)."' WHERE name='CHARTOFACCOUNTS' AND entity=".$entity);
    importSql("DELETE FROM ".$prefix."accounting_account WHERE fk_pcg_version='".$version."' AND entity=".$entity);
    importSql("DELETE FROM ".$prefix."accounting_system WHERE rowid=".(int)$chart);
}
print "PASS confirmed chart import, repeatability, stale/conflicting preview, admin restriction and atomic rollback\n";
