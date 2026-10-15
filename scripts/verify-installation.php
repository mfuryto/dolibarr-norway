<?php
// SPDX-License-Identifier: GPL-3.0-or-later
if (PHP_SAPI !== 'cli') {
    exit(1);
}
require '/var/www/html/master.inc.php';
// This file is piped to PHP during updates. STD* constants are not available in
// every PHP stdin invocation; php://stderr works for both files and stdin.
require_once DOL_DOCUMENT_ROOT.'/custom/norway/core/modules/modNorway.class.php';
$modulePayloadMatches = is_dir('/opt/norway-module');
if ($modulePayloadMatches) {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator('/opt/norway-module', FilesystemIterator::SKIP_DOTS)) as $file) {
        if (!$file->isFile()) { continue; }
        $relative = substr($file->getPathname(), strlen('/opt/norway-module/'));
        $active = DOL_DOCUMENT_ROOT.'/custom/norway/'.$relative;
        if (!is_file($active) || hash_file('sha256', $active) !== hash_file('sha256', $file->getPathname())) {
            $modulePayloadMatches = false;
            break;
        }
    }
}
require_once DOL_DOCUMENT_ROOT.'/custom/norway/class/NorwaySchema.php';
try { $schemaValid=NorwaySchema::verify($db); } catch (Throwable $error) { $schemaValid=false; }
$descriptor = new modNorway($db);
$menusValid = true;
foreach ($descriptor->menu as $menu) {
    $result = $db->query("SELECT COUNT(*) AS n FROM ".MAIN_DB_PREFIX."menu WHERE module='norway' AND entity=".(int)$conf->entity
        ." AND leftmenu='".$db->escape($menu['leftmenu'])."' AND url='".$db->escape($menu['url'])."'");
    $row = $result ? $db->fetch_object($result) : null;
    if (!$row || (int)$row->n !== 1) { $menusValid = false; }
}
$tabsValid = true;
foreach ($descriptor->tabs as $i => $tab) {
    if (getDolGlobalString('MAIN_MODULE_NORWAY_TABS_'.$i) !== $tab) { $tabsValid = false; }
}
$checks = array(
    'norway_schema_version' => $schemaValid,
    'core_version' => DOL_VERSION === '24.0.1',
    'norway_module' => isModEnabled('norway'),
    'norway_module_version' => (new modNorway($db))->version === '0.59.0',
    'norway_module_payload' => $modulePayloadMatches,
    'norway_menu_registration' => $menusValid,
    'norway_document_tabs' => $tabsValid,
    'accounting_module' => isModEnabled('accounting'),
    'proposal_module' => isModEnabled('propal'),
    'invoice_module' => isModEnabled('facture'),
    'multicurrency_disabled' => !isModEnabled('multicurrency'),
    'base_currency_nok' => $conf->currency === 'NOK',
    'cheque_deposits_disabled' => getDolGlobalInt('BANK_DISABLE_CHECK_DEPOSIT') === 1,
    'norway_dashboard_hook' => in_array('index', (array) ($conf->modules_parts['hooks']['norway'] ?? array()), true),
    'norway_company_hook' => in_array('admincompany', (array) ($conf->modules_parts['hooks']['norway'] ?? array()), true),
    'norway_supplier_hook' => in_array('invoicesuppliercard', (array) ($conf->modules_parts['hooks']['norway'] ?? array()), true),
    'norway_pdf_hook' => in_array('pdfgeneration', (array) ($conf->modules_parts['hooks']['norway'] ?? array()), true),
    'norway_numbering_models' => in_array('/norway/', (array) ($conf->modules_parts['models'] ?? array()), true),
    'norway_partner_model' => in_array('/norway/core/modules/societe/', (array) ($conf->modules_parts['societe'] ?? array()), true),
    'norway_layout_css' => in_array('/norway/css/layout-0.5.0.css', (array) ($conf->modules_parts['css']['norway'] ?? array()), true),
    'document_languages_enabled' => getDolGlobalInt('MAIN_MULTILANGS') === 1,
    'installer_locked' => file_exists('/var/www/documents/install.lock'),
    'instance_key_match' => getenv('DOLI_INSTANCE_UNIQUE_ID') !== false
        && getenv('DOLI_INSTANCE_UNIQUE_ID') !== ''
        && $conf->file->instance_unique_id === getenv('DOLI_INSTANCE_UNIQUE_ID'),
);
$failed = array_keys(array_filter($checks, static function ($passed) { return !$passed; }));
if ($failed) {
    // Only check names are logged: no configuration values or credentials.
    file_put_contents('php://stderr', "Installation verification failed; retain backup.\nFAIL ".implode("\nFAIL ", $failed)."\n");
    $db->close();
    exit(1);
}
print "PASS core version, enabled modules, instance key and installer lock\n";
$db->close();
