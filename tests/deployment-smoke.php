<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// Read-only unless the guarded synthetic defaults test is explicitly requested.
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
              'MAIN_MONNAIE' => 'NOK', 'MAIN_INFO_SOCIETE_DEVISE' => 'NOK', 'MAIN_LANG_DEFAULT' => 'nb_NO') as $key => $expected) {
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
if (getenv('NORWAY_TEST_JOURNALS') === '1') {
    verify(getenv('DOLI_COMPANY_NAME') === 'SYNTHETIC INSTALLATION ONLY' && getenv('NORWAY_EXPECT_EMPTY') === '1', 'Synthetic journal guard missing');
    $banks=$db->query('SELECT COUNT(*) AS n FROM '.$db->prefix().'bank_account');
    verify($banks && (int)$db->fetch_object($banks)->n===0,'Journal fixture requires no bank accounts');
    verify((bool)$db->query('DELETE FROM '.$db->prefix().'accounting_journal WHERE entity='.(int)$conf->entity),'Journal fixture reset failed');
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
    $company=page('/admin/company.php');
    verify(strpos(html_entity_decode($company,ENT_QUOTES,'UTF-8'),'ENK — Enkeltpersonforetak')!==false
        && strpos($company,'Organisasjonsnummer')!==false && strpos($company,'Organisasjonsform')!==false,'Norwegian company fields or legal forms missing');
    $secure = false;
    foreach ($proxyCookies as $name => $cookieData) {
        if (strpos($name, 'DOLSESSID_') === 0 && $cookieData[1]) {
            $secure = true;
        }
    }
    verify($secure, 'Session cookie is not Secure behind HTTPS proxy');
    $profile = page('/custom/norway/admin/profile.php');
    verify(strpos($profile, 'NORWAY-NO-1') !== false && strpos($profile, 'value="installprofile"') !== false,
        'Norwegian chart preview or confirmation missing');
    if (getenv('NORWAY_TEST_DEFAULTS') === '1') {
        verify(getenv('DOLI_COMPANY_NAME') === 'SYNTHETIC INSTALLATION ONLY' && getenv('NORWAY_EXPECT_EMPTY') === '1', 'Synthetic defaults guard missing');
        $defaults = page('/custom/norway/admin/defaults.php');
        verify(preg_match('/name="token"[^>]*value="([^"]+)"/', $defaults, $defaultsToken) === 1
            && preg_match('/name="preview_nonce"[^>]*value="([^"]+)"/', $defaults, $defaultsNonce) === 1, 'Defaults confirmation missing');
        forwardProxyCookies();
        page('/custom/norway/admin/defaults.php', array('action'=>'savedefaults', 'token'=>html_entity_decode($defaultsToken[1]), 'preview_nonce'=>$defaultsNonce[1]));
        verify(curl_getinfo($curl,CURLINFO_HTTP_CODE)===302, 'Defaults save did not redirect');
        forwardProxyCookies();
        $defaults = page('/custom/norway/admin/defaults.php');
        verify(strpos($defaults,'value="savedefaults"')===false
            && (strpos($defaults,'Lagret 2 standardkontovalg')!==false || strpos($defaults,'Saved 2 account defaults')!==false), 'Confirmed defaults save failed');
        forwardProxyCookies();
        page('/custom/norway/admin/defaults.php', array('action'=>'savedefaults','token'=>html_entity_decode($defaultsToken[1]), 'preview_nonce'=>$defaultsNonce[1]));
        forwardProxyCookies();
        $defaults = page('/custom/norway/admin/defaults.php');
        verify(strpos($defaults,'Lagringen kunne ikke bekreftes')!==false || strpos($defaults,'Saving could not be confirmed')!==false, 'Defaults confirmation replay accepted');
    }
    if (getenv('NORWAY_TEST_JOURNALS') === '1') {
        $journals=page('/custom/norway/admin/journals.php');
        verify(preg_match('/name="token"[^>]*value="([^"]+)"/',$journals,$journalToken)===1
            && preg_match('/name="preview_nonce"[^>]*value="([^"]+)"/',$journals,$journalNonce)===1,'Journal confirmation missing');
        forwardProxyCookies();
        page('/custom/norway/admin/journals.php',array('action'=>'createjournals','token'=>html_entity_decode($journalToken[1]),'preview_nonce'=>$journalNonce[1]));
        verify(curl_getinfo($curl,CURLINFO_HTTP_CODE)===302,'Journal save did not redirect');
        forwardProxyCookies();
        $journals=page('/custom/norway/admin/journals.php');
        verify(strpos($journals,'value="createjournals"')===false
            && (strpos($journals,'Opprettet 4 journaler')!==false || strpos($journals,'Created 4 journals')!==false),'Confirmed journal creation failed');
        forwardProxyCookies();
        page('/custom/norway/admin/journals.php',array('action'=>'createjournals','token'=>html_entity_decode($journalToken[1]),'preview_nonce'=>$journalNonce[1]));
        forwardProxyCookies();
        $journals=page('/custom/norway/admin/journals.php');
        verify(strpos($journals,'Opprettingen kunne ikke bekreftes')!==false || strpos($journals,'Creation could not be confirmed')!==false,'Journal confirmation replay accepted');
    }
    print "PASS installed module, NOK, nb_NO, instance key, locked installer and HTTPS-aware login\n";
} finally {
    curl_close($curl);
    unlink($cookie);
}
