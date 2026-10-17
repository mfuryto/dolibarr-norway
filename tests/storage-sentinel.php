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
// Synthetic storage marker only; deliberately has no accounting source and is never read as a voucher.
$manualHash=hash('sha256','SYNTHETIC MANUAL VAT STORAGE ONLY');
if(getenv('NORWAY_CREATE_SENTINEL')==='1') {
    if(!$db->query("INSERT INTO ".$db->prefix()."norway_manual_vat (entity,fk_bookkeeping,tax_code,tax_base,source_hash,fk_user,updated_at) VALUES (".(int)$conf->entity.",2147483000,'1',0,'".$manualHash."',1,'2026-01-01 00:00:00')")){throw new RuntimeException('Manual VAT storage marker');}
}
$r=$db->query('SELECT * FROM '.$db->prefix().'norway_manual_vat WHERE entity='.(int)$conf->entity.' AND fk_bookkeeping=2147483000');$marker=$r?$db->fetch_object($r):null;
if(!$marker || $marker->tax_code!=='1' || $marker->tax_base!=='0.00000000' || $marker->source_hash!==$manualHash || $marker->updated_at!=='2026-01-01 00:00:00' || (int)$marker->fk_user!==1){throw new RuntimeException('Manual VAT fields lost through update/restore');}
print "PASS manual VAT storage preserved through update/restore\n";
$zeroRows=array();$r=$db->query('SELECT * FROM '.$db->prefix()."c_tva WHERE entity=".(int)$conf->entity." AND fk_pays=173 AND code IN ('NO0','5','6','7','52','86','87') ORDER BY code");
while($r && $row=$db->fetch_object($r)){$zeroRows[]=(array)$row;}
if(count($zeroRows)!==7){throw new RuntimeException('Missing synthetic zero choices');}
$zeroMarker='/var/www/documents/norway-zero-code-marker.json';$zeroJson=json_encode($zeroRows,JSON_THROW_ON_ERROR);
if(getenv('NORWAY_CREATE_SENTINEL')==='1'){if(file_put_contents($zeroMarker,$zeroJson)===false){throw new RuntimeException('Zero marker write');}}
elseif(file_get_contents($zeroMarker)!==$zeroJson){throw new RuntimeException('Native zero choices changed through update/restore');}
print "PASS native zero-code rows/IDs preserved through update/restore\n";
// Version 0.77 repairs missing native classes while preserving explicit local grouping.
if(getenv('NORWAY_CREATE_SENTINEL')==='1'){
    if(!$db->query("UPDATE ".$db->prefix()."accounting_account SET pcg_type='RESK' WHERE entity=".(int)$conf->entity." AND fk_pcg_version='NORWAY-NO-1' AND account_number='1500'")
        || !$db->query("UPDATE ".$db->prefix()."accounting_account SET pcg_type='XXXXXX' WHERE entity=".(int)$conf->entity." AND fk_pcg_version='NORWAY-NO-1' AND account_number='1920'")){throw new RuntimeException('Legacy group fixture');}
    if(dolibarr_set_const($db,'ACCOUNTING_CLOSURE_ACCOUNTING_GROUPS_USED_FOR_BALANCE_SHEET_ACCOUNT','1,2,RESK','chaine',0,'',$conf->entity)<=0){throw new RuntimeException('Custom closing fixture');}
}
$r=$db->query("SELECT account_number,pcg_type FROM ".$db->prefix()."accounting_account WHERE entity=".(int)$conf->entity." AND fk_pcg_version='NORWAY-NO-1'");$classes=array();while($r&&$row=$db->fetch_object($r)){$classes[$row->account_number]=$row->pcg_type;}
if(count($classes)!==745){throw new RuntimeException('Chart classes lost');}
foreach($classes as $number=>$class){$expected=(string)$number==='1500'?'RESK':((string)$number==='1920'&&getenv('NORWAY_CREATE_SENTINEL')==='1'?'XXXXXX':substr((string)$number,0,1));if($class!==$expected){throw new RuntimeException('Class repair/preservation failed');}}
if(getDolGlobalString('ACCOUNTING_CLOSURE_ACCOUNTING_GROUPS_USED_FOR_BALANCE_SHEET_ACCOUNT')!=='1,2,RESK'){throw new RuntimeException('Custom closing group lost');}
print "PASS 0.77 native class repair and custom groups through upgrade/restore\n";
$result = $db->query("SELECT label,active FROM ".$db->prefix()."accounting_account WHERE entity=".(int) $conf->entity." AND fk_pcg_version='SYN-BACKUP' AND account_number='8891'");
$account = $result ? $db->fetch_object($result) : false;
if (!$account || $account->label !== 'Synthetic imported backup account' || (int) $account->active !== 1) {
    throw new RuntimeException('Imported chart account was not preserved by backup/recovery');
}
$result = $db->query("SELECT COUNT(*) AS total FROM ".$db->prefix()."accounting_account WHERE entity=".(int) $conf->entity." AND fk_pcg_version='NORWAY-NO-1' AND active=1");
if (!$result || (int) $db->fetch_object($result)->total !== 745) { throw new RuntimeException('Norwegian chart was not preserved'); }
print "PASS synthetic persistent content, Norwegian chart and imported chart account\n";
print "PASS legacy payloads and hashes preserved without the retired writers\n";


// Native line metadata has no document/ledger semantics in this storage-only sentinel.
if(getenv('NORWAY_CREATE_SENTINEL')==='1'){
    if(!$db->query('INSERT INTO '.$db->prefix().'facture_fourn_det_extrafields (fk_object,norway_vat_deduction) VALUES (2147482999,37.50)')){throw new RuntimeException('Deduction storage marker');}
}
$r=$db->query('SELECT norway_vat_deduction FROM '.$db->prefix().'facture_fourn_det_extrafields WHERE fk_object=2147482999');$deduction=$r?$db->fetch_object($r):null;
if(!$deduction || $deduction->norway_vat_deduction!=='37.50'){throw new RuntimeException('Deduction lost through update/restore');}
print "PASS native deduction percentage preserved through update/restore\n";

$db->close();
