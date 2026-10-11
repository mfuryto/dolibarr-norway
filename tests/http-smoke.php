<?php
// SPDX-License-Identifier: GPL-3.0-or-later
if (PHP_SAPI !== 'cli' || getenv('NORWAY_SYNTHETIC_TEST') !== '1') {
    exit(1);
}
$cookie = tempnam(sys_get_temp_dir(), 'norway-http-');
$curl = curl_init();
curl_setopt_array($curl, array(
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEJAR => $cookie,
    CURLOPT_COOKIEFILE => $cookie,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 20,
));
function request($path, $post = null)
{
    global $curl;
    curl_setopt($curl, CURLOPT_URL, 'http://localhost'.$path);
    curl_setopt($curl, CURLOPT_POST, $post !== null);
    if ($post !== null) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $body = curl_exec($curl);
    if ($body === false || curl_getinfo($curl, CURLINFO_HTTP_CODE) >= 500) {
        throw new RuntimeException('HTTP request failed: '.curl_error($curl));
    }
    return $body;
}
try {
    $preview = request('/custom/norway/admin/chart.php');
    if (strpos($preview, 'name="chart_csv"') !== false) { throw new RuntimeException('Chart preview visible before authentication'); }
    $accounting = request('/custom/norway/admin/accounting.php');
    if (strpos($accounting, 'Chart and accounting setup check') !== false
        || strpos($accounting, 'Kontroll av kontoplan og bokføringsoppsett') !== false) {
        throw new RuntimeException('Accounting setup visible before authentication');
    }
    $body = request('/custom/norway/admin/setup.php');
    if (strpos($body, 'Not ready for production accounting.') !== false
        || strpos($body, 'Ikke klart for produksjonsregnskap.') !== false
        || strpos($body, 'Company configuration checks') !== false
        || strpos($body, 'Kontroll av foretaksoppsett') !== false) {
        throw new RuntimeException('Status visible before authentication');
    }
    if (!preg_match('/name="token"[^>]*value="([^"]+)"/', $body, $match)) {
        throw new RuntimeException('Missing login CSRF token');
    }
    request('/index.php', array(
        'token' => html_entity_decode($match[1]),
        'actionlogin' => 'login',
        'loginfunction' => 'loginfunction',
        'username' => 'testadmin',
        'password' => getenv('DOLI_ADMIN_PASSWORD'),
    ));
    $accounting = request('/custom/norway/admin/accounting.php');
    if (curl_getinfo($curl, CURLINFO_HTTP_CODE) !== 200
        || (strpos($accounting, 'Chart and accounting setup check') === false
            && strpos($accounting, 'Kontroll av kontoplan og bokføringsoppsett') === false)
        || strpos($accounting, '3000') === false
        || strpos($accounting, 'accountancy/admin/defaultaccounts.php') === false
        || strpos($accounting, 'compta/bank/list.php') === false
        || (strpos($accounting, 'Bank and cash mapping checks') === false
            && strpos($accounting, 'Kontroll av bank- og kassekoblinger') === false)
        || strpos($accounting, '/norway/css/layout-0.4.0.css') === false
        || strpos($accounting, 'Could not read accounting setup') !== false
        || strpos($accounting, 'Kunne ikke lese bokføringsoppsettet') !== false) {
        throw new RuntimeException('Accounting setup report missing or failed');
    }
    $preview = request('/custom/norway/admin/chart.php');
    if (!preg_match('/name="token"[^>]*value="([^"]+)"/', $preview, $previewToken)
        || strpos($preview, 'chart_csv') === false) { throw new RuntimeException('Chart preview form missing'); }
    $preview = request('/custom/norway/admin/chart.php', array('action' => 'previewchart', 'token' => html_entity_decode($previewToken[1]),
        'chart_csv' => "account_number;label\n1500;Different label\n8888;Synthetic & label"));
    if (strpos($preview, 'Different label') === false || strpos($preview, 'norway-chart-csv/1 / SYN-') === false
        || strpos($preview, 'Synthetic &amp; label') === false
        || (strpos(html_entity_decode($preview, ENT_QUOTES | ENT_HTML5, 'UTF-8'), 'Conflict – existing account preserved') === false
            && strpos(html_entity_decode($preview, ENT_QUOTES | ENT_HTML5, 'UTF-8'), 'Konflikt – eksisterende konto bevares') === false)) {
        throw new RuntimeException('Chart preview failed or label HTML not escaped');
    }
    if (strpos($preview, 'value="importchart"') !== false) { throw new RuntimeException('Conflicting preview offers import'); }
    if (!preg_match('/name="token"[^>]*value="([^"]+)"/', $preview, $previewToken)) { throw new RuntimeException('Preview token missing'); }
    $number = (string) random_int(89000000, 89999999);
    $preview = request('/custom/norway/admin/chart.php', array('action' => 'previewchart', 'token' => html_entity_decode($previewToken[1]),
        'chart_csv' => "account_number;label\n".$number.';Synthetic HTTP import'));
    if (!preg_match('/name="preview_nonce"[^>]*value="([^"]+)"/', $preview, $nonce)
        || !preg_match('/name="token"[^>]*value="([^"]+)"/', $preview, $previewToken)) { throw new RuntimeException('Import confirmation missing'); }
    $preview = request('/custom/norway/admin/chart.php', array('action' => 'importchart', 'token' => html_entity_decode($previewToken[1]), 'preview_nonce' => $nonce[1]));
    if (strpos($preview, 'Created 1 new accounts') === false && strpos($preview, 'Opprettet 1 nye kontoer') === false) {
        throw new RuntimeException('Confirmed HTTP import failed');
    }
    if (!preg_match('/name="token"[^>]*value="([^"]+)"/', $preview, $previewToken)) { throw new RuntimeException('Import result token missing'); }
    $preview = request('/custom/norway/admin/chart.php', array('action' => 'importchart', 'token' => html_entity_decode($previewToken[1]), 'preview_nonce' => $nonce[1]));
    if (strpos($preview, 'Preview expired, changed') === false && strpos($preview, 'Forhåndsvisningen er utløpt, endret') === false) {
        throw new RuntimeException('Consumed preview can be replayed');
    }
    if (!preg_match('/name="token"[^>]*value="([^"]+)"/', $preview, $previewToken)) { throw new RuntimeException('Preview token missing'); }
    $preview = request('/custom/norway/admin/chart.php', array('action' => 'previewchart', 'token' => html_entity_decode($previewToken[1]), 'chart_csv' => 'invalid'));
    if (strpos($preview, 'Invalid CSV, duplicate') === false && strpos($preview, 'Ugyldig CSV, duplikat') === false) {
        throw new RuntimeException('Invalid chart CSV not rejected');
    }
    request('/custom/norway/admin/chart.php', array('action' => 'previewchart', 'token' => 'invalid', 'chart_csv' => 'invalid'));
    if (curl_getinfo($curl, CURLINFO_HTTP_CODE) !== 403) { throw new RuntimeException('Chart preview CSRF rejection missing'); }
    request('/custom/norway/admin/chart.php', array('action' => 'importchart', 'token' => 'invalid', 'preview_nonce' => 'invalid'));
    if (curl_getinfo($curl, CURLINFO_HTTP_CODE) !== 403) { throw new RuntimeException('Chart import CSRF rejection missing'); }
    $profile = request('/custom/norway/admin/profile.php');
    if (strpos($profile, 'installprofile') !== false
        || (strpos($profile, 'The Norwegian chart cannot be installed now') === false
            && strpos($profile, 'Kontoplanen kan ikke installeres nå') === false)) {
        throw new RuntimeException('Profile offers to replace existing chart');
    }
    request('/custom/norway/admin/profile.php', array('action' => 'installprofile', 'token' => 'invalid'));
    if (curl_getinfo($curl, CURLINFO_HTTP_CODE) !== 403) { throw new RuntimeException('Profile CSRF rejection missing'); }
    $registry = request('/custom/norway/admin/registry.php');
    if (curl_getinfo($curl, CURLINFO_HTTP_CODE) !== 200
        || !preg_match('/name="token"[^>]*value="([^"]+)"/', $registry, $registryToken)
        || strpos($registry, 'lookupregistry') === false) {
        throw new RuntimeException('Registry form missing');
    }
    $registry = request('/custom/norway/admin/registry.php', array('action' => 'lookupregistry', 'token' => html_entity_decode($registryToken[1])));
    if (strpos($registry, 'Set a valid Norwegian organisation number') === false
        && strpos($registry, 'Oppgi et gyldig norsk organisasjonsnummer') === false) {
        throw new RuntimeException('Registry invalid company must fail before network');
    }
    $registry = request('/custom/norway/admin/registry.php', array('action' => 'lookupregistry', 'token' => 'invalid'));
    if (curl_getinfo($curl, CURLINFO_HTTP_CODE) !== 403
        || strpos($registry, 'Set a valid Norwegian organisation number') !== false
        || strpos($registry, 'Oppgi et gyldig norsk organisasjonsnummer') !== false
        || strpos($registry, 'lookupregistry') !== false) {
        throw new RuntimeException('Registry CSRF rejection missing');
    }
    $body = request('/custom/norway/admin/setup.php');
    if (strpos($body, 'Not ready for production accounting.') === false
        && strpos($body, 'Ikke klart for produksjonsregnskap.') === false) {
        throw new RuntimeException('Authenticated status page missing');
    }
    if (strpos($body, 'Company configuration checks') === false
        && strpos($body, 'Kontroll av foretaksoppsett') === false) {
        throw new RuntimeException('Configuration checks missing');
    }
    if (strpos($body, 'Could not read VAT configuration') !== false
        || strpos($body, 'Kunne ikke lese MVA-oppsettet') !== false) {
        throw new RuntimeException('VAT configuration query failed');
    }
    if (strpos($body, 'SAF-T 1.40') === false || strpos($body, 'EHF/Peppol') === false) {
        throw new RuntimeException('Unfinished accounting requirements missing');
    }
    if (strpos($body, '/norway/css/layout-0.4.0.css') === false) {
        throw new RuntimeException('Module layout stylesheet not loaded');
    }
    $body = request('/custom/norway/admin/invoice.php?id=0');
    if (strpos($body, 'Fakturaen kunne ikke leses i aktivt foretak.') === false
        && strpos($body, 'Invoice could not be read in the active entity.') === false) {
        throw new RuntimeException('Invalid invoice ID accepted');
    }
    $body = request('/custom/norway/admin/audit.php?date_start=2026-01-01&date_end=2026-12-31');
    if ((strpos($body, 'Bilagskontroll') === false && strpos($body, 'Voucher checks') === false)
        || strpos($body, 'Could not read the ledger') !== false || strpos($body, 'Kunne ikke lese hovedboken') !== false) {
        throw new RuntimeException('Voucher control page missing or failed');
    }
    $body = request('/custom/norway/admin/audit.php?date_start=2026-02-30&date_end=2026-03-01');
    if (strpos($body, 'Velg gyldige datoer') === false && strpos($body, 'Select valid ordered dates') === false) {
        throw new RuntimeException('Invalid audit period accepted');
    }
    $body = request('/societe/index.php?lang=nb_NO');
    if (strpos(html_entity_decode($body, ENT_QUOTES, 'UTF-8'), 'Kunder og leverandører') === false) { throw new RuntimeException('Norwegian customer/supplier menu missing'); }
    if (strpos($body, '/compta/paiement/cheque/') !== false) {
        throw new RuntimeException('Cheque dashboard or menu still visible');
    }
    $body = request('/index.php?lang=nb_NO');
    $text = html_entity_decode($body, ENT_QUOTES, 'UTF-8');
    if (strpos($body, 'bg-infobox-bank_account') === false) { throw new RuntimeException('Bank dashboard absent; cheque assertion would be vacuous'); }
    if (stripos($text, 'sjekkinnskudd') !== false || stripos($text, 'sjekkinskudd') !== false
        || stripos($body, 'BankChecksToReceipt') !== false || strpos($body, '/compta/paiement/cheque/') !== false) {
        throw new RuntimeException('Cheque deposit still visible in populated bank dashboard');
    }
    foreach (array('index.php', 'list.php', 'card.php?action=create2') as $page) {
        request('/compta/paiement/cheque/'.$page);
        if (curl_getinfo($curl, CURLINFO_HTTP_CODE) !== 403) { throw new RuntimeException('Direct cheque page accessible'); }
    }
    $body = request('/custom/norway/admin/pricing.php');
    if (strpos($body, 'Veiledende EUR-pris') === false && strpos($body, 'Indicative EUR price') === false) {
        throw new RuntimeException('Indicative price calculator missing');
    }
    print "PASS login and authenticated module status and configuration checks\n";
} finally {
    curl_close($curl);
    unlink($cookie);
}
