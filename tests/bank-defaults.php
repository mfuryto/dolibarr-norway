<?php
// SPDX-License-Identifier: GPL-3.0-or-later
if (PHP_SAPI!=='cli' || getenv('NORWAY_SYNTHETIC_TEST')!=='1' || getenv('DOLI_DB_NAME')!=='norway_test') { exit(1); }
require '/var/www/html/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/norway/class/NorwayBankDefaults.php';
function bdAssert($ok,$message) { if (!$ok) { throw new RuntimeException($message); } }
function bdSql($sql) { global $db; $r=$db->query($sql); bdAssert((bool)$r,'Synthetic bank defaults query failed'); return $r; }
function bdRow($sql) { global $db; return (array)$db->fetch_object(bdSql($sql)); }
$oldEntity=$conf->entity; $oldChart=$conf->global->CHARTOFACCOUNTS ?? null;
$entity=987654; $p=$db->prefix(); $conf->entity=$entity;
$user=new User($db); bdAssert($user->fetch('','testadmin')>0,'Synthetic admin missing'); $user->admin=1;
$db->begin();
try {
    bdSql("INSERT INTO ".$p."accounting_system (pcg_version,label,active,fk_country) VALUES ('SYN-BD','Synthetic chart',1,173)");
    $chart=(int)$db->last_insert_id($p.'accounting_system');
    // Synthetic test chart temporarily carries the bundled version; all changes roll back.
    bdSql("UPDATE ".$p."accounting_system SET pcg_version='".NorwayChartProfile::VERSION."' WHERE rowid=".$chart);
    $conf->global->CHARTOFACCOUNTS=(string)$chart;
    bdSql("INSERT INTO ".$p."const (name,value,type,entity) VALUES ('CHARTOFACCOUNTS','".$chart."','chaine',".$entity.")");
    $labels=array_column(NorwayChartPreview::parse(NorwayChartProfile::csv()),'label','number');
    bdSql("INSERT INTO ".$p."accounting_account (account_number,entity,fk_pcg_version,pcg_type,label,active) VALUES ('1920',".$entity.",'".NorwayChartProfile::VERSION."','SYN','".$db->escape($labels['1920'])."',1)");
    bdSql("INSERT INTO ".$p."accounting_journal (entity,nature,active,code,label) VALUES (".$entity.",4,1,'CUSTOM','Synthetic bank journal')");
    $journal=(int)$db->last_insert_id($p.'accounting_journal');
    bdAssert(NorwayBankDefaults::preview($db,$entity)['reason']==='Single','Empty bank setup accepted');
    bdSql("INSERT INTO ".$p."bank_account (ref,label,entity,clos,courant,fk_pays,currency_code,number,iban_prefix,proprio) VALUES ('SYN-BD','Synthetic bank',".$entity.",0,1,173,'NOK','SYNTHETIC-NUMBER','SYNTHETIC-IBAN','Synthetic owner')");
    $id=(int)$db->last_insert_id($p.'bank_account');
    $preview=NorwayBankDefaults::preview($db,$entity); $fp=NorwayBankDefaults::fingerprint($preview);
    bdAssert(array_column($preview['rows'],'action')===array('Fill','Fill'),'Eligible bank mapping not offered');
    bdAssert(strpos(json_encode($preview),'SYNTHETIC-IBAN')===false,'Private bank data leaked into preview');
    $reader=clone $user; $reader->admin=0;
    try { NorwayBankDefaults::apply($db,$entity,$fp,$reader); throw new LogicException('Nonadmin accepted'); } catch (InvalidArgumentException $expected) {}
    try { NorwayBankDefaults::apply($db,$entity+1,$fp,$user); throw new LogicException('Foreign entity accepted'); } catch (InvalidArgumentException $expected) {}
    foreach (array("courant=2"=>'Type',"currency_code='EUR'"=>'Type',"clos=1"=>'Single') as $change=>$reason) {
        bdSql('UPDATE '.$p.'bank_account SET '.$change.' WHERE rowid='.$id);
        bdAssert(NorwayBankDefaults::preview($db,$entity)['reason']===$reason,'Unsupported bank accepted');
        bdSql("UPDATE ".$p."bank_account SET courant=1,currency_code='NOK',clos=0 WHERE rowid=".$id);
    }
    bdSql("INSERT INTO ".$p."bank_account (ref,label,entity,clos,courant,fk_pays,currency_code,account_number) VALUES ('SYN-SECOND','Synthetic second',".$entity.",0,1,173,'NOK','1920')");
    $second=(int)$db->last_insert_id($p.'bank_account');
    bdAssert(NorwayBankDefaults::preview($db,$entity)['reason']==='Single','Multiple banks accepted');
    bdSql('UPDATE '.$p.'bank_account SET clos=1 WHERE rowid='.$second);
    bdAssert(NorwayBankDefaults::preview($db,$entity)['rows'][0]['action']==='Blocked','Closed account sharing 1920 ignored');
    bdSql('DELETE FROM '.$p.'bank_account WHERE rowid='.$second);
    bdSql("INSERT INTO ".$p."accounting_journal (entity,nature,active,code,label) VALUES (".$entity.",4,1,'SECOND','Synthetic second journal')");
    $secondJournal=(int)$db->last_insert_id($p.'accounting_journal');
    bdAssert(NorwayBankDefaults::preview($db,$entity)['rows'][1]['action']==='Blocked','Ambiguous journal accepted');
    bdSql('DELETE FROM '.$p.'accounting_journal WHERE rowid='.$secondJournal);
    bdSql('UPDATE '.$p.'accounting_account SET active=0 WHERE entity='.$entity);
    bdAssert(NorwayBankDefaults::preview($db,$entity)['rows'][0]['action']==='Blocked','Inactive ledger accepted');
    bdSql('UPDATE '.$p.'accounting_account SET active=1 WHERE entity='.$entity);
    bdSql("INSERT INTO ".$p."bank (datev,dateo,amount,label,fk_account) VALUES (NOW(),NOW(),0,'Synthetic movement',".$id.")");
    $movement=(int)$db->last_insert_id($p.'bank');
    bdAssert(NorwayBankDefaults::preview($db,$entity)['reason']==='History','Zero bank movement ignored');
    bdSql('DELETE FROM '.$p.'bank WHERE rowid='.$movement);
    bdSql('UPDATE '.$p.'accounting_system SET active=0 WHERE rowid='.$chart);
    bdAssert(NorwayBankDefaults::preview($db,$entity)['reason']==='Chart','Inactive chart accepted');
    bdSql('UPDATE '.$p.'accounting_system SET active=1 WHERE rowid='.$chart);
    bdSql("UPDATE ".$p."bank_account SET account_number='Missing' WHERE rowid=".$id);
    $blocked=NorwayBankDefaults::preview($db,$entity);
    bdAssert($blocked['reason']==='Mappings','Invalid existing mapping accepted');
    try { NorwayBankDefaults::apply($db,$entity,NorwayBankDefaults::fingerprint($blocked),$user); throw new LogicException('Blocked mapping saved'); } catch (InvalidArgumentException $expected) {}
    bdSql("UPDATE ".$p."bank_account SET account_number='1920' WHERE rowid=".$id);
    $partial=NorwayBankDefaults::preview($db,$entity);
    bdAssert(array_column($partial['rows'],'action')===array('Keep','Fill'),'Partial mapping not preserved');
    bdAssert(NorwayBankDefaults::apply($db,$entity,NorwayBankDefaults::fingerprint($partial),$user)===1,'Missing journal not filled alone');
    bdSql('UPDATE '.$p.'bank_account SET account_number=NULL,fk_accountancy_journal=NULL WHERE rowid='.$id);
    $failing=new class($db) {
        private $db;
        public function __construct($db) { $this->db=$db; }
        public function __call($name,$args) { return $this->db->$name(...$args); }
        public function __get($name) { return $this->db->$name; }
        public function query($sql,...$args) { if (strpos($sql,'SELECT rowid FROM '.$this->db->prefix().'bank_account WHERE')===0) { return false; } return $this->db->query($sql,...$args); }
    };
    $fresh=NorwayBankDefaults::preview($db,$entity);
    try { NorwayBankDefaults::apply($failing,$entity,NorwayBankDefaults::fingerprint($fresh),$user); throw new LogicException('Database lock failure accepted'); } catch (RuntimeException $expected) {}
    bdAssert(NorwayBankDefaults::preview($db,$entity)===$fresh,'Failed database lock changed bank mapping');
    bdSql("UPDATE ".$p."bank_account SET label='Changed synthetic bank' WHERE rowid=".$id);
    try { NorwayBankDefaults::apply($db,$entity,$fp,$user); throw new LogicException('Stale preview accepted'); } catch (InvalidArgumentException $expected) {}
    $preview=NorwayBankDefaults::preview($db,$entity); $before=bdRow('SELECT * FROM '.$p.'bank_account WHERE rowid='.$id);
    bdAssert(NorwayBankDefaults::apply($db,$entity,NorwayBankDefaults::fingerprint($preview),$user)===2,'Bank mapping not saved');
    $after=bdRow('SELECT * FROM '.$p.'bank_account WHERE rowid='.$id);
    bdAssert($after['account_number']==='1920' && (int)$after['fk_accountancy_journal']===$journal,'Wrong mapping saved');
    foreach (array('account_number','fk_accountancy_journal','tms') as $key) { unset($before[$key],$after[$key]); }
    bdAssert($before===$after,'Unrelated bank fields changed');
    $complete=NorwayBankDefaults::preview($db,$entity);
    bdAssert(array_column($complete['rows'],'action')===array('Keep','Keep'),'Existing choices not retained');
    bdAssert(NorwayBankDefaults::apply($db,$entity,NorwayBankDefaults::fingerprint($complete),$user)===0,'Repeated mapping changed bank');
} finally { $db->rollback(); $conf->entity=$oldEntity; $conf->global->CHARTOFACCOUNTS=$oldChart; }
print "PASS confirmed bank mappings, private-field preservation, history, scope, conflicts, stale preview and repeatability\n";
