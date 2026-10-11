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
    print "PASS login and authenticated module status and configuration checks\n";
} finally {
    curl_close($curl);
    unlink($cookie);
}
