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
$result = $db->query("SELECT label,active FROM ".$db->prefix()."accounting_account WHERE entity=".(int) $conf->entity." AND fk_pcg_version='SYN-BACKUP' AND account_number='8891'");
$account = $result ? $db->fetch_object($result) : false;
if (!$account || $account->label !== 'Synthetic imported backup account' || (int) $account->active !== 1) {
    throw new RuntimeException('Imported chart account was not preserved by backup/recovery');
}
$result = $db->query("SELECT COUNT(*) AS total FROM ".$db->prefix()."accounting_account WHERE entity=".(int) $conf->entity." AND fk_pcg_version='NORWAY-NO-1' AND active=1");
if (!$result || (int) $db->fetch_object($result)->total !== 745) { throw new RuntimeException('Norwegian chart was not preserved'); }
print "PASS synthetic persistent content, Norwegian chart and imported chart account\n";
$db->close();
