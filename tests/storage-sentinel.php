<?php
// SPDX-License-Identifier: GPL-3.0-or-later
if (PHP_SAPI !== 'cli' || getenv('DOLI_DB_NAME') !== 'accounting'
    || getenv('DOLI_COMPANY_NAME') !== 'SYNTHETIC INSTALLATION ONLY'
    || getenv('NORWAY_EXPECT_EMPTY') !== '1') {
    exit(1);
}
require '/var/www/html/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/norway/class/NorwayChartImport.php';
require_once DOL_DOCUMENT_ROOT.'/custom/norway/class/NorwaySchema.php';
NorwaySchema::verify($db);
require_once DOL_DOCUMENT_ROOT.'/custom/norway/class/NorwayNumbering.php';
foreach (array('ACCOUNTING_ACCOUNT_CUSTOMER'=>'1500','ACCOUNTING_ACCOUNT_SUPPLIER'=>'2400') as $key=>$value) {
    if (getDolGlobalString($key)!==$value) { throw new RuntimeException('Confirmed default lost: '.$key); }
}
foreach (array('SALG'=>2,'KJOP'=>3,'BANK'=>4,'DIV'=>1) as $code=>$nature) {
    $journal=$db->query("SELECT nature,active FROM ".$db->prefix()."accounting_journal WHERE entity=".(int)$conf->entity." AND code='".$code."'");
    $journal=$journal ? $db->fetch_object($journal) : null;
    if (!$journal || (int)$journal->nature!==$nature || (int)$journal->active!==1) { throw new RuntimeException('Confirmed journal lost: '.$code); }
}
if (getenv('NORWAY_CREATE_SENTINEL') === '1') {
    $previousChart = getDolGlobalString('CHARTOFACCOUNTS');
    if ($user->fetch('', getenv('DOLI_ADMIN_LOGIN')) <= 0 || !$user->admin
        || !$db->query("INSERT INTO ".$db->prefix()."accounting_system (fk_country,pcg_version,label,active) VALUES (173,'SYN-BACKUP','Synthetic backup chart',1)")) {
        throw new RuntimeException('Synthetic chart backup setup failed');
    }
    $chart = $db->last_insert_id($db->prefix().'accounting_system');
    if (dolibarr_set_const($db, 'CHARTOFACCOUNTS', $chart, 'chaine', 0, '', $conf->entity) <= 0) { exit(1); }
    $csv = "account_number;label\n8891;Synthetic imported backup account";
    $report = NorwayChartPreview::report($db, $conf->entity, $chart, $csv);
    if (NorwayChartImport::apply($db, $conf->entity, $chart, $csv,
        NorwayChartImport::fingerprint($conf->entity, $chart, $report), $user) !== 1) { exit(1); }
    if (dolibarr_set_const($db, 'CHARTOFACCOUNTS', $previousChart, 'chaine', 0, '', $conf->entity) <= 0) { exit(1); }
    if (dolibarr_set_const($db, 'NORWAY_MIGRATION_SENTINEL', 'synthetic-only', 'chaine', 0, '', $conf->entity) <= 0
        || file_put_contents('/var/www/documents/norway-backup-marker', "synthetic backup marker\n") === false) {
        exit(1);
    }
} elseif (getDolGlobalString('NORWAY_MIGRATION_SENTINEL') !== 'synthetic-only'
    || file_get_contents('/var/www/documents/norway-backup-marker') !== "synthetic backup marker\n") {
    file_put_contents('php://stderr', "Synthetic database/document content was not preserved.\n");
    exit(1);
}
$numberingInput=NorwayNumbering::input('F',1201,'K',501,'T',301,1,1,'K','L');
if (getenv('NORWAY_CREATE_SENTINEL') === '1') {
    $preview=NorwayNumbering::preview($db,(int)$conf->entity,$numberingInput,$user);
    NorwayNumbering::save($db,(int)$conf->entity,$numberingInput,$preview['fingerprint'],$user);
}
if (NorwayNumbering::record($db,(int)$conf->entity)['input']!==$numberingInput
    || NorwayNumbering::record($db,(int)$conf->entity,'proposal')['input']!==$numberingInput
    || NorwayNumbering::next($db,(int)$conf->entity,$numberingInput,'invoice')!=='F001201'
    || NorwayNumbering::next($db,(int)$conf->entity,$numberingInput,'credit')!=='K000501'
    || NorwayNumbering::next($db,(int)$conf->entity,$numberingInput,'proposal')!=='T000301'
    || NorwayNumbering::next($db,(int)$conf->entity,$numberingInput,'customer')!=='K00001'
    || NorwayNumbering::next($db,(int)$conf->entity,$numberingInput,'supplier')!=='L00001') { throw new RuntimeException('Numbering/start offsets lost through update/restore'); }
$legacy=json_decode(file_get_contents('/tmp/norway-legacy-evidence.json'),true,512,JSON_THROW_ON_ERROR);
if (($legacy['synthetic'] ?? null)!==true || count($legacy['rows'])!==15) { throw new RuntimeException('Invalid synthetic legacy fixture'); }
$retiredSettings=array('NORWAY_WIZARD_STEP_1'=>'{"version":1,"entity":1,"step":1,"state":"Reviewed","synthetic":true}');
foreach($legacy['rows'] as $row){$data=json_decode($row['payload'],true,512,JSON_THROW_ON_ERROR);foreach($data['basis']['settings'] ?? array() as $setting){if(in_array($setting['name'],array('NORWAY_VAT_REGISTRATION_START','NORWAY_VAT_REGISTRATION_HISTORY_V1','NORWAY_TAX_MAPPINGS_V1'),true)){$retiredSettings[$setting['name']]=$setting['value'];}}}
foreach($retiredSettings as $name=>$value){
 if(getenv('NORWAY_CREATE_SENTINEL')==='1' && dolibarr_set_const($db,$name,$value,'chaine',0,'Synthetic retained setting',(int)$conf->entity)<=0){throw new RuntimeException('Legacy setting seed');}
 $r=$db->query("SELECT value FROM ".$db->prefix()."const WHERE entity=".(int)$conf->entity." AND name='".$db->escape($name)."'");$record=$r ? $db->fetch_object($r) : null;if(!$record || $record->value!==$value){throw new RuntimeException('Legacy setting changed through update/restore');}
}
foreach ($legacy['rows'] as $row) {
    $row['entity']=(int)$conf->entity;
    if(getenv('NORWAY_CREATE_SENTINEL')==='1') {
        $values=array_map(fn($v)=>"'".$db->escape((string)$v)."'",array_values($row));
        if(!$db->query('INSERT INTO '.$db->prefix().'norway_tax_evidence ('.implode(',',array_keys($row)).') VALUES ('.implode(',',$values).')')) { throw new RuntimeException('Legacy fixture insertion'); }
    }
    $r=$db->query("SELECT payload,payload_sha256 FROM ".$db->prefix()."norway_tax_evidence WHERE entity=".(int)$conf->entity." AND document_type='".$db->escape($row['document_type'])."' AND fk_document=".(int)$row['fk_document'].' AND revision='.(int)$row['revision']);$actual=$r ? $db->fetch_object($r) : null;
    if(!$actual || $actual->payload!==$row['payload'] || $actual->payload_sha256!==$row['payload_sha256'] || !hash_equals($actual->payload_sha256,hash('sha256',$actual->payload))) { throw new RuntimeException('Legacy record changed through update/restore'); }
}
$result = $db->query("SELECT label,active FROM ".$db->prefix()."accounting_account WHERE entity=".(int) $conf->entity." AND fk_pcg_version='SYN-BACKUP' AND account_number='8891'");
$account = $result ? $db->fetch_object($result) : false;
if (!$account || $account->label !== 'Synthetic imported backup account' || (int) $account->active !== 1) {
    throw new RuntimeException('Imported chart account was not preserved by backup/recovery');
}
$result = $db->query("SELECT COUNT(*) AS total FROM ".$db->prefix()."accounting_account WHERE entity=".(int) $conf->entity." AND fk_pcg_version='NORWAY-NO-1' AND active=1");
if (!$result || (int) $db->fetch_object($result)->total !== 745) { throw new RuntimeException('Norwegian chart was not preserved'); }
print "PASS synthetic persistent content, Norwegian chart and imported chart account\n";
print "PASS legacy payloads and hashes preserved without the retired writers\n";
$db->close();
