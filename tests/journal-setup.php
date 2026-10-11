<?php
// SPDX-License-Identifier: GPL-3.0-or-later
if (PHP_SAPI !== 'cli' || getenv('NORWAY_SYNTHETIC_TEST') !== '1' || getenv('DOLI_DB_NAME') !== 'norway_test') { exit(1); }
require '/var/www/html/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/norway/class/NorwayJournalSetup.php';
function journalAssert($ok,$message) { if (!$ok) { throw new RuntimeException($message); } }
function journalSql($sql) { global $db; $result=$db->query($sql); journalAssert($result!==false,'Synthetic journal query failed: '.$db->lasterror()); return $result; }
journalAssert($user->fetch('', 'testadmin')>0 && $user->admin,'Admin fixture missing');
$prefix=$db->prefix(); $previousEntity=$conf->entity; $conf->entity=987649; $entity=$conf->entity;
try {
    journalSql("INSERT INTO ".$prefix."accounting_journal (entity,code,label,nature,active) VALUES (".($entity+1).",'SALG','Synthetic foreign',2,1)");
    $empty=NorwayJournalSetup::preview($db,$entity);
    journalAssert(array_column($empty['rows'],'action')===array('Create','Create','Create','Create'),'Foreign journal leaked');
    $fingerprint=NorwayJournalSetup::fingerprint($empty);
    $failing=new class($db) {
        private $db; private $inserts=0;
        public function __construct($db) { $this->db=$db; }
        public function __call($name,$args) { return $this->db->$name(...$args); }
        public function __get($name) { return $this->db->$name; }
        public function query($sql,...$args) {
            if (strpos($sql,'INSERT INTO '.$this->db->prefix().'accounting_journal')===0 && ++$this->inserts===2) { return false; }
            return $this->db->query($sql,...$args);
        }
    };
    try { NorwayJournalSetup::apply($failing,$entity,$fingerprint,$user); throw new LogicException('Partial journal failure accepted'); }
    catch (RuntimeException $expected) {}
    journalAssert(NorwayJournalSetup::preview($db,$entity)===$empty,'Partial failure left journal');
    $reader=clone $user; $reader->admin=0;
    try { NorwayJournalSetup::apply($db,$entity,$fingerprint,$reader); throw new LogicException('Nonadmin accepted'); }
    catch (InvalidArgumentException $expected) {}
    try { NorwayJournalSetup::apply($db,$entity+1,$fingerprint,$user); throw new LogicException('Foreign entity accepted'); }
    catch (InvalidArgumentException $expected) {}
    journalSql("INSERT INTO ".$prefix."accounting_journal (entity,code,label,nature,active) VALUES (".$entity.",'salg','Synthetic inactive',2,0)");
    $blocked=NorwayJournalSetup::preview($db,$entity);
    journalAssert($blocked['rows'][0]['action']==='Blocked','Inactive code collision ignored');
    try { NorwayJournalSetup::apply($db,$entity,NorwayJournalSetup::fingerprint($blocked),$user); throw new LogicException('Blocked journal created'); }
    catch (InvalidArgumentException $expected) {}
    try { NorwayJournalSetup::apply($db,$entity,$fingerprint,$user); throw new LogicException('Stale preview accepted'); }
    catch (InvalidArgumentException $expected) {}
    journalSql("DELETE FROM ".$prefix."accounting_journal WHERE entity=".$entity);
    journalSql("INSERT INTO ".$prefix."accounting_journal (entity,code,label,nature,active) VALUES (".$entity.",'CUSTOM','Synthetic custom sales',2,1)");
    journalSql("INSERT INTO ".$prefix."accounting_journal (entity,code,label,nature,active) VALUES (".$entity.",'OTHER','Synthetic second sales',2,1)");
    $partial=NorwayJournalSetup::preview($db,$entity);
    journalAssert($partial['rows'][0]['action']==='Keep' && count($partial['rows'][0]['existing'])===2,'Custom journals not preserved');
    journalAssert(NorwayJournalSetup::apply($db,$entity,NorwayJournalSetup::fingerprint($partial),$user)===3,'Missing types not created');
    $complete=NorwayJournalSetup::preview($db,$entity);
    journalAssert(count($complete['existing'])===5 && array_column($complete['rows'],'action')===array('Keep','Keep','Keep','Keep'),'Completed journal types missing');
    journalAssert(NorwayJournalSetup::apply($db,$entity,NorwayJournalSetup::fingerprint($complete),$user)===0,'Repeated setup created journals');
    journalAssert(NorwayJournalSetup::preview($db,$entity)===$complete,'Repeated setup changed existing journals');
    journalSql("DELETE FROM ".$prefix."accounting_journal WHERE entity=".$entity);
    journalAssert(NorwayJournalSetup::apply($db,$entity,$fingerprint,$user)===4,'Empty setup creation failed');
    journalSql("UPDATE ".$prefix."accounting_journal SET nature=2 WHERE entity=".$entity." AND code='BANK'");
    journalAssert(NorwayJournalSetup::preview($db,$entity)['rows'][2]['action']==='Blocked','Wrong type with proposed code ignored');
    journalSql("UPDATE ".$prefix."accounting_journal SET code=' ',nature=4 WHERE entity=".$entity." AND code='BANK'");
    journalAssert(NorwayJournalSetup::preview($db,$entity)['rows'][2]['action']==='Create','Empty coded journal counted as usable');
} finally {
    $db->rollback();
    journalSql("DELETE FROM ".$prefix."accounting_journal WHERE entity IN (".$entity.",".($entity+1).")");
    $conf->entity=$previousEntity;
}
print "PASS confirmed journal creation, custom/foreign journals, collisions, stale preview, rollback and repeatability\n";
