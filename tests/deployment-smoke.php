<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// Read-only verification; no test users or accounting data are created.
if (PHP_SAPI !== 'cli') {
    exit(1);
}
require '/var/www/html/master.inc.php';
function verify($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
verify(DOL_VERSION === '24.0.1', 'Unexpected core version');
foreach (array('MAIN_MODULE_NORWAY' => '1', 'MAIN_MODULE_ACCOUNTING' => '1',
              'MAIN_INFO_SOCIETE_DEVISE' => 'NOK', 'MAIN_LANG_DEFAULT' => 'nb_NO') as $key => $expected) {
    $result = $db->query("SELECT value FROM ".$db->prefix()."const WHERE name='".$db->escape($key)."' AND entity=".(int) $conf->entity);
    verify((bool) $result, 'Configuration query failed');
    $row = $db->fetch_object($result);
    verify($row && $row->value === $expected, 'Missing or incorrect '.$key);
}
verify($conf->file->instance_unique_id === getenv('DOLI_INSTANCE_UNIQUE_ID'), 'Persistent instance key mismatch');
verify(file_exists('/var/www/documents/install.lock'), 'Installer is not locked');
if (getenv('NORWAY_EXPECT_EMPTY') === '1') {
    foreach (array('facture', 'facture_fourn', 'accounting_bookkeeping') as $table) {
        $result = $db->query('SELECT COUNT(*) AS n FROM '.$db->prefix().$table);
        verify((bool) $result && (int) $db->fetch_object($result)->n === 0, 'New installation contains accounting data');
    }
}
$db->close();
$cookie = tempnam(sys_get_temp_dir(), 'norway-deploy-http-');
$curl = curl_init();
$proxyCookies = array();
curl_setopt_array($curl, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $cookie,
    CURLOPT_COOKIEFILE => $cookie, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 20,
    CURLOPT_HEADERFUNCTION => function ($handle, $header) use (&$proxyCookies) {
        if (preg_match('/^Set-Cookie:\s*([^=;]+)=([^;]*)(.*)$/i', trim($header), $m)) {
            $proxyCookies[$m[1]] = array($m[2], stripos($m[3], '; secure') !== false);
        }
        return strlen($header);
    },
    CURLOPT_HTTPHEADER => array('Host: '.parse_url(getenv('DOLI_URL_ROOT'), PHP_URL_HOST), 'X-Forwarded-Proto: https')));
function page($path, $post = null)
{
    global $curl;
    curl_setopt($curl, CURLOPT_URL, 'http://localhost'.$path);
    curl_setopt($curl, CURLOPT_POST, $post !== null);
    if ($post !== null) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $body = curl_exec($curl);
    verify($body !== false, 'HTTP failure: '.curl_error($curl));
    return $body;
}
function forwardProxyCookies()
{
    global $curl, $proxyCookies;
    // Emulate a browser sending its Secure cookies through the TLS proxy.
    $forwardedCookies = array();
    foreach ($proxyCookies as $name => $cookieData) {
        $forwardedCookies[] = $name.'='.$cookieData[0];
    }
    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
        'Host: '.parse_url(getenv('DOLI_URL_ROOT'), PHP_URL_HOST),
        'X-Forwarded-Proto: https', 'Cookie: '.implode('; ', $forwardedCookies)));
}
try {
    $body = page('/index.php');
    verify(preg_match('/name="token"[^>]*value="([^"]+)"/', $body, $match) === 1, 'Login form missing');
    forwardProxyCookies();
    page('/index.php', array('token' => html_entity_decode($match[1]), 'actionlogin' => 'login',
        'loginfunction' => 'loginfunction', 'username' => getenv('DOLI_ADMIN_LOGIN'), 'password' => getenv('DOLI_ADMIN_PASSWORD')));
    forwardProxyCookies();
    $body = page('/custom/norway/admin/setup.php');
    verify(strpos($body, 'Ikke klart for produksjonsregnskap.') !== false
        || strpos($body, 'Not ready for production accounting.') !== false, 'Authenticated module page missing');
    $secure = false;
    foreach ($proxyCookies as $name => $cookieData) {
        if (strpos($name, 'DOLSESSID_') === 0 && $cookieData[1]) {
            $secure = true;
        }
    }
    verify($secure, 'Session cookie is not Secure behind HTTPS proxy');
    print "PASS installed module, NOK, nb_NO, instance key, locked installer and HTTPS-aware login\n";
} finally {
    curl_close($curl);
    unlink($cookie);
}
