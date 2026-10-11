<?php
// SPDX-License-Identifier: GPL-3.0-or-later
if (PHP_SAPI !== 'cli' || getenv('NORWAY_SYNTHETIC_TEST') !== '1'
    || getenv('DOLI_DB_NAME') !== 'norway_test') { exit(1); }
require '/var/www/html/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/norway/class/NorwayBankSetup.php';
function bankSetupAssert($ok, $message) { if (!$ok) { throw new RuntimeException($message); } }
function bankSetupSql($sql) {
    global $db;
    $result = $db->query($sql);
    bankSetupAssert($result !== false, 'Synthetic bank setup query failed: '.$db->lasterror());
    return $result;
}
$prefix = $db->prefix(); $entity = 987650; $version = 'SYN-BANK-'.bin2hex(random_bytes(4));
$db->begin();
try {
    bankSetupSql("INSERT INTO ".$prefix."accounting_system (pcg_version,label,active) VALUES ('".$version."','Synthetic bank chart',1)");
    $chart = (int) $db->last_insert_id($prefix.'accounting_system');
    bankSetupSql("INSERT INTO ".$prefix."accounting_system (pcg_version,label,active) VALUES ('".$version."-OTHER','Synthetic other chart',1)");
    foreach (array(array('1920',$entity,$version,1), array('1921',$entity+1,$version,1),
        array('1922',$entity,$version.'-OTHER',1), array('1923',$entity,$version,0)) as $account) {
        bankSetupSql("INSERT INTO ".$prefix."accounting_account (account_number,entity,fk_pcg_version,pcg_type,label,active)"
            ." VALUES ('".$account[0]."',".$account[1].",'".$account[2]."','SYN','Synthetic ledger',".$account[3].")");
    }
    $journalIds = array();
    foreach (array(array($entity,4,1,'SYN-BANK'), array($entity,4,0,'SYN-OFF'),
        array($entity,2,1,'SYN-SALES'), array($entity+1,4,1,'SYN-OTHER'), array($entity,4,1,' ')) as $journal) {
        bankSetupSql("INSERT INTO ".$prefix."accounting_journal (entity,nature,active,code,label) VALUES ("
            .$journal[0].",".$journal[1].",".$journal[2].",'".$journal[3]."','Synthetic journal')");
        $journalIds[] = (int) $db->last_insert_id($prefix.'accounting_journal');
    }
    foreach (array(array('MAIN',$entity,0,1),array('CLOSED',$entity,1,1),array('FOREIGN',$entity+1,0,1)) as $bank) {
        bankSetupSql("INSERT INTO ".$prefix."bank_account (ref,label,entity,clos,courant,fk_pays,currency_code,account_number,fk_accountancy_journal)"
            ." VALUES ('".$bank[0]."','Synthetic ".$bank[0]."',".$bank[1].",".$bank[2].",".$bank[3].",173,'NOK','1920',".$journalIds[0].")");
        if ($bank[0] === 'MAIN') { $bankId = (int) $db->last_insert_id($prefix.'bank_account'); }
    }
    $service = new NorwayBankSetup($db,$entity);
    $rows = $service->report($chart);
    bankSetupAssert(count($rows)===1 && $rows[0]['id']===$bankId, 'Closed/foreign bank leaked');
    foreach (array('account_status','journal_status','currency_status','shared_status') as $key) {
        bankSetupAssert($rows[0][$key]==='Available','Valid bank mapping rejected: '.$key);
    }
    bankSetupAssert(!$rows[0]['cash'] && $rows[0]['journal']==='SYN-BANK','Bank journal/reference mismatch');
    bankSetupAssert($service->report(0)[0]['account_status']==='Missing','Missing chart accepted');
    bankSetupSql("UPDATE ".$prefix."accounting_system SET active=0 WHERE rowid=".$chart);
    bankSetupAssert($service->report($chart)[0]['account_status']==='Inactive','Inactive chart accepted');
    bankSetupSql("UPDATE ".$prefix."accounting_system SET active=1 WHERE rowid=".$chart);
    foreach (array('' => 'Unset', '-1' => 'Unset', '1921' => 'Missing', '1922' => 'Missing',
        '1923' => 'Inactive', "1920' OR 1=1 --" => 'Missing') as $number => $status) {
        bankSetupSql("UPDATE ".$prefix."bank_account SET account_number='".$db->escape((string) $number)."' WHERE rowid=".$bankId);
        bankSetupAssert($service->report($chart)[0]['account_status']===$status,'Ledger scope/status mismatch');
    }
    foreach (array(1 => 'Inactive',2 => 'Review',3 => 'Missing',4 => 'Review') as $index => $status) {
        bankSetupSql("UPDATE ".$prefix."bank_account SET fk_accountancy_journal=".$journalIds[$index]." WHERE rowid=".$bankId);
        $row = $service->report($chart)[0];
        bankSetupAssert($row['journal_status']===$status,'Journal scope/type/status mismatch');
        if ($index===3) { bankSetupAssert($row['journal']==='', 'Foreign journal code disclosed'); }
    }
    bankSetupSql("UPDATE ".$prefix."bank_account SET account_number='1920',fk_accountancy_journal=NULL,currency_code='EUR',courant=2 WHERE rowid=".$bankId);
    $row = $service->report($chart)[0];
    bankSetupAssert($row['journal_status']==='Unset' && $row['currency_status']==='Review' && $row['cash'],'Unset journal/cash/currency mismatch');
    bankSetupSql("INSERT INTO ".$prefix."bank_account (ref,label,entity,fk_pays,currency_code,account_number) VALUES ('SHARED','Synthetic shared',".$entity.",173,'NOK','1920')");
    bankSetupAssert($service->report($chart)[0]['shared_status']==='Review','Shared ledger account missed');
    bankSetupAssert($service->report($chart)[1]['shared_status']==='Review','Second shared account missed');
    bankSetupAssert((new NorwayBankSetup($db,$entity+2))->report($chart)===array(),'Empty entity leaked data');
} finally { $db->rollback(); }
$failedDb = new class {
    public function prefix() { return 'synthetic_'; }
    public function query($sql) { return false; }
};
try { (new NorwayBankSetup($failedDb,1))->report(1); throw new LogicException('Database error hidden'); }
catch (RuntimeException $expected) {}
try { new NorwayBankSetup($db,0); throw new LogicException('Invalid entity accepted'); }
catch (InvalidArgumentException $expected) {}
print "PASS bank setup mappings, chart/entity scope, closed/cash accounts, journals, currency and errors\n";
