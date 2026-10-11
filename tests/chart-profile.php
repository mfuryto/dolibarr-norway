<?php
// SPDX-License-Identifier: GPL-3.0-or-later
if (PHP_SAPI !== 'cli' || getenv('NORWAY_SYNTHETIC_TEST') !== '1' || getenv('DOLI_DB_NAME') !== 'norway_test') { exit(1); }
require '/var/www/html/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/norway/class/NorwayChartProfile.php';
function profileAssert($ok,$message) { if (!$ok) { throw new RuntimeException($message); } }
function profileSql($sql) { global $db; $result=$db->query($sql); profileAssert($result!==false,'Synthetic profile query failed: '.$db->lasterror()); return $result; }
profileAssert($user->fetch('', 'testadmin') > 0 && $user->admin, 'Admin fixture missing');
$prefix=$db->prefix(); $originalEntity=$conf->entity; $conf->entity=987651; $entity=$conf->entity;
$old=$db->fetch_object(profileSql("SELECT rowid FROM ".$prefix."accounting_system WHERE pcg_version='".NorwayChartProfile::VERSION."'"));
$otherVersion='SYN-OTHER-'.bin2hex(random_bytes(4));
try {
    profileAssert(count(NorwayChartPreview::parse(NorwayChartProfile::csv()))===745,'Source account count changed');
    profileSql("INSERT INTO ".$prefix."const (name,value,type,entity) VALUES ('CHARTOFACCOUNTS','2','chaine',".$entity.")");
    $preview=NorwayChartProfile::preview($db,$entity);
    $fingerprint=NorwayChartProfile::fingerprint($entity,$preview);
    $failing = new class($db) {
        private $db; private $inserts=0;
        public function __construct($db) { $this->db=$db; }
        public function __call($name,$args) { return $this->db->$name(...$args); }
        public function __get($name) { return $this->db->$name; }
        public function query($sql,...$args) {
            if (strpos($sql,'INSERT INTO '.$this->db->prefix().'accounting_account ')===0 && ++$this->inserts===2) { return false; }
            return $this->db->query($sql,...$args);
        }
    };
    try { NorwayChartProfile::install($failing,$entity,$fingerprint,$user); throw new LogicException('Failed profile import accepted'); }
    catch (RuntimeException $expected) {}
    profileAssert(NorwayChartProfile::preview($db,$entity)===$preview,'Failed install left chart, selection or partial accounts');
    profileAssert(NorwayChartProfile::install($db,$entity,$fingerprint,$user)===745,'Full profile install failed');
    $preview=NorwayChartProfile::preview($db,$entity);
    profileAssert($preview['selected']>0 && $preview['target']===$preview['selected'],'Profile was not selected');
    profileAssert(NorwayChartProfile::install($db,$entity,NorwayChartProfile::fingerprint($entity,$preview),$user)===0,'Profile reinstall duplicated accounts');
    try { NorwayChartProfile::install($db,$entity,$fingerprint,$user); throw new LogicException('Stale install accepted'); }
    catch (InvalidArgumentException $expected) {}
    $chart=$preview['target'];
    profileSql("UPDATE ".$prefix."accounting_account SET label='Synthetic conflict' WHERE entity=".$entity." AND fk_pcg_version='".NorwayChartProfile::VERSION."' AND account_number='1500'");
    try { NorwayChartProfile::preview($db,$entity); throw new LogicException('Profile conflict accepted'); }
    catch (InvalidArgumentException $expected) {}
    profileSql("INSERT INTO ".$prefix."accounting_system (fk_country,pcg_version,label,active) VALUES (173,'".$otherVersion."','Synthetic other plan',1)");
    $other=$db->last_insert_id($prefix.'accounting_system');
    profileSql("UPDATE ".$prefix."const SET value='".(int)$other."' WHERE name='CHARTOFACCOUNTS' AND entity=".$entity);
    try { NorwayChartProfile::preview($db,$entity); throw new LogicException('Other selected chart replaced'); }
    catch (InvalidArgumentException $expected) {}
    profileSql("DELETE FROM ".$prefix."const WHERE name='CHARTOFACCOUNTS' AND entity=".$entity);
    try { NorwayChartProfile::preview($db,$entity); throw new LogicException('Unselected existing accounts accepted'); }
    catch (InvalidArgumentException $expected) {}
} finally {
    $db->rollback();
    profileSql("DELETE FROM ".$prefix."accounting_account WHERE entity=".$entity);
    profileSql("DELETE FROM ".$prefix."const WHERE entity=".$entity." AND name='CHARTOFACCOUNTS'");
    profileSql("DELETE FROM ".$prefix."accounting_system WHERE pcg_version='".$otherVersion."'");
    if (!$old) { profileSql("DELETE FROM ".$prefix."accounting_system WHERE pcg_version='".NorwayChartProfile::VERSION."'"); }
    $conf->entity=$originalEntity;
}
print "PASS full Norwegian chart installation, selection, repeatability, stale preview, conflicts and existing plan protection\n";
