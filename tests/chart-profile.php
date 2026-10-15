<?php
// SPDX-License-Identifier: GPL-3.0-or-later
if (PHP_SAPI !== 'cli' || getenv('NORWAY_SYNTHETIC_TEST') !== '1' || getenv('DOLI_DB_NAME') !== 'norway_test') { exit(1); }
require '/var/www/html/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/norway/class/NorwayChartProfile.php';
require_once DOL_DOCUMENT_ROOT.'/custom/norway/class/NorwayDefaultAccounts.php';
function profileAssert($ok,$message) { if (!$ok) { throw new RuntimeException($message); } }
function profileSql($sql) { global $db; $result=$db->query($sql); profileAssert($result!==false,'Synthetic profile query failed: '.$db->lasterror()); return $result; }
profileAssert($user->fetch('', 'testadmin') > 0 && $user->admin, 'Admin fixture missing');
$prefix=$db->prefix(); $originalEntity=$conf->entity; $conf->entity=987651; $entity=$conf->entity;
$old=$db->fetch_object(profileSql("SELECT rowid FROM ".$prefix."accounting_system WHERE pcg_version='".NorwayChartProfile::VERSION."'"));
$runtimeBefore=array();
foreach (NorwayDefaultAccounts::mapping() as $key=>$number) {
    $runtimeBefore[$key]=array(property_exists($conf->global,$key),$conf->global->$key ?? null);
    unset($conf->global->$key);
}
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
    $defaults = NorwayDefaultAccounts::preview($db,$entity);
    profileAssert(array_column($defaults['rows'],'action')===array('Fill','Fill'),'Empty defaults not offered');
    $failingDefaults = new class($db) {
        private $db; private $inserts=0;
        public function __construct($db) { $this->db=$db; }
        public function __call($name,$args) { return $this->db->$name(...$args); }
        public function __get($name) { return $this->db->$name; }
        public function query($sql,...$args) {
            if (strpos($sql,'INSERT INTO '.$this->db->prefix().'const(')===0 && ++$this->inserts===2) { return false; }
            return $this->db->query($sql,...$args);
        }
    };
    try { NorwayDefaultAccounts::apply($failingDefaults,$entity,NorwayDefaultAccounts::fingerprint($defaults),$user); throw new LogicException('Partial defaults save accepted'); }
    catch (RuntimeException $expected) {}
    profileAssert(NorwayDefaultAccounts::preview($db,$entity)===$defaults,'Failed defaults save changed DB or runtime values');
    $reader=clone $user; $reader->admin=0;
    try { NorwayDefaultAccounts::apply($db,$entity,NorwayDefaultAccounts::fingerprint($defaults),$reader); throw new LogicException('Nonadmin defaults accepted'); }
    catch (InvalidArgumentException $expected) {}
    try { NorwayDefaultAccounts::apply($db,$entity+1,NorwayDefaultAccounts::fingerprint($defaults),$user); throw new LogicException('Foreign entity defaults accepted'); }
    catch (InvalidArgumentException $expected) {}
    profileSql("UPDATE ".$prefix."accounting_account SET active=0 WHERE entity=".$entity." AND account_number='1500'");
    try { NorwayDefaultAccounts::apply($db,$entity,NorwayDefaultAccounts::fingerprint($defaults),$user); throw new LogicException('Stale defaults accepted'); }
    catch (InvalidArgumentException $expected) {}
    profileAssert(NorwayDefaultAccounts::preview($db,$entity)['rows'][0]['action']==='Blocked','Inactive default account accepted');
    profileSql("UPDATE ".$prefix."accounting_account SET active=1 WHERE entity=".$entity." AND account_number='1500'");
    profileAssert(NorwayDefaultAccounts::apply($db,$entity,NorwayDefaultAccounts::fingerprint($defaults),$user)===2,'Defaults fill failed');
    $filled=NorwayDefaultAccounts::preview($db,$entity);
    profileAssert(array_column($filled['rows'],'current')===array('1500','2400'),'Defaults values incorrect');
    profileAssert(NorwayDefaultAccounts::apply($db,$entity,NorwayDefaultAccounts::fingerprint($filled),$user)===0,'Defaults repeated write');
    profileSql("UPDATE ".$prefix."const SET value='1999' WHERE entity=".$entity." AND name='ACCOUNTING_ACCOUNT_CUSTOMER'");
    profileAssert(NorwayDefaultAccounts::preview($db,$entity)['rows'][0]['action']==='Keep','Custom default replaced');
    profileSql("DELETE FROM ".$prefix."const WHERE entity=".$entity." AND name IN ('ACCOUNTING_ACCOUNT_CUSTOMER','ACCOUNTING_ACCOUNT_SUPPLIER')");
    foreach (NorwayDefaultAccounts::mapping() as $key=>$number) { unset($conf->global->$key); }
    $soc=$db->fetch_object(profileSql("SELECT rowid FROM ".$prefix."societe WHERE entity=".(int)$originalEntity.$db->plimit(1)));
    profileAssert((bool)$soc,'Invoice history fixture customer missing');
    foreach (array('facture','facture_fourn') as $invoiceTable) {
        $extraColumn=$invoiceTable==='facture_fourn' ? ',ref_supplier' : '';
        $extraValue=$invoiceTable==='facture_fourn' ? ",'SYN-SUPPLIER'" : '';
        profileSql("INSERT INTO ".$prefix.$invoiceTable." (ref,entity,fk_soc,datef,fk_statut".$extraColumn.") VALUES ('SYN-DEFAULTS-".$entity."',".$entity.",".(int)$soc->rowid.",'2026-01-01',0".$extraValue.")");
        $invoiceId=(int)$db->last_insert_id($prefix.$invoiceTable);
        profileAssert(!NorwayDefaultAccounts::preview($db,$entity)['history'],'Draft treated as posted history');
        profileSql("UPDATE ".$prefix.$invoiceTable." SET fk_statut=1 WHERE rowid=".$invoiceId);
        $historical=NorwayDefaultAccounts::preview($db,$entity);
        profileAssert(array_column($historical['rows'],'action')===array('Blocked','Blocked'),'Validated invoice history ignored');
        try { NorwayDefaultAccounts::apply($db,$entity,NorwayDefaultAccounts::fingerprint($historical),$user); throw new LogicException('Historical defaults changed'); }
        catch (InvalidArgumentException $expected) {}
        profileSql("DELETE FROM ".$prefix.$invoiceTable." WHERE rowid=".$invoiceId);
    }
    $conf->global->ACCOUNTING_ACCOUNT_CUSTOMER='1998';
    profileAssert(NorwayDefaultAccounts::preview($db,$entity)['rows'][0]['action']==='Keep','Runtime override replaced');
    unset($conf->global->ACCOUNTING_ACCOUNT_CUSTOMER);
    $globalDefault=$db->fetch_object(profileSql("SELECT value FROM ".$prefix."const WHERE entity=0 AND name='ACCOUNTING_ACCOUNT_CUSTOMER'"));
    if (!$globalDefault) {
        $db->begin();
        profileSql("INSERT INTO ".$prefix."const (name,value,type,entity) VALUES ('ACCOUNTING_ACCOUNT_CUSTOMER','1997','chaine',0)");
        profileAssert(NorwayDefaultAccounts::preview($db,$entity)['rows'][0]['action']==='Keep','Inherited choice replaced');
        $db->rollback();
    }
    profileSql("INSERT INTO ".$prefix."accounting_bookkeeping (entity,piece_num,doc_date,doc_type,doc_ref,fk_doc,fk_docdet,numero_compte,label_compte,debit,credit,fk_user_author,code_journal)"
        ." VALUES (".$entity.",1,'2026-01-01','SYN','SYN-DEFAULTS',0,0,'1500','Synthetic',0,0,".(int)$user->id.",'SYN')");
    profileAssert(NorwayDefaultAccounts::preview($db,$entity)['history'],'Ledger history ignored');
    profileSql("DELETE FROM ".$prefix."accounting_bookkeeping WHERE entity=".$entity);
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
    profileSql("DELETE FROM ".$prefix."accounting_bookkeeping WHERE entity=".$entity);
    profileSql("DELETE FROM ".$prefix."accounting_account WHERE entity=".$entity);
    profileSql("DELETE FROM ".$prefix."const WHERE entity=".$entity." AND name IN ('CHARTOFACCOUNTS','ACCOUNTING_ACCOUNT_CUSTOMER','ACCOUNTING_ACCOUNT_SUPPLIER')");
    profileSql("DELETE FROM ".$prefix."accounting_system WHERE pcg_version='".$otherVersion."'");
    if (!$old) { profileSql("DELETE FROM ".$prefix."accounting_system WHERE pcg_version='".NorwayChartProfile::VERSION."'"); }
    foreach (array('facture','facture_fourn') as $table) { profileSql("DELETE FROM ".$prefix.$table." WHERE entity=".$entity); }
    foreach ($runtimeBefore as $key=>$oldValue) {
        if ($oldValue[0]) { $conf->global->$key=$oldValue[1]; } else { unset($conf->global->$key); }
    }
    $conf->entity=$originalEntity;
}
print "PASS full Norwegian chart and confirmed defaults, history protection, rollback, selection, repeatability, stale preview, conflicts and existing plan protection\n";
