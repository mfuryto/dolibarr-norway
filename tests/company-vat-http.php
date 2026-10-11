<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// Core company-save regression in a disposable database only.
if (PHP_SAPI !== 'cli' || getenv('NORWAY_SYNTHETIC_TEST') !== '1' || getenv('DOLI_DB_NAME') !== 'norway_test') { exit(1); }
require '/var/www/html/master.inc.php';
function vatAssert($ok, $message) { if (!$ok) { throw new RuntimeException($message); } }
$entity=(int)$conf->entity; $prefix=$db->prefix();
$result=$db->query('SELECT name,value,type,visible,note FROM '.$prefix.'const WHERE entity='.$entity);
vatAssert((bool)$result,'Fixture snapshot failed'); $snapshot=array();
while ($row=$db->fetch_object($result)) { $snapshot[]=$row; }
$cookie=tempnam(sys_get_temp_dir(),'norway-vat-'); $curl=curl_init();
curl_setopt_array($curl,array(CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_TIMEOUT=>20));
function vatPage($post=null) {
    global $curl;
    curl_setopt($curl,CURLOPT_URL,'http://localhost/admin/company.php?lang=en_US');
    curl_setopt($curl,CURLOPT_POST,$post!==null);
    if ($post!==null) { curl_setopt($curl,CURLOPT_POSTFIELDS,http_build_query($post)); }
    $body=curl_exec($curl); vatAssert($body!==false && curl_getinfo($curl,CURLINFO_HTTP_CODE)===200,'Company HTTP request failed'); return $body;
}
function vatForm($body) {
    $dom=new DOMDocument(); libxml_use_internal_errors(true); $dom->loadHTML($body); libxml_clear_errors();
    $xpath=new DOMXPath($dom); $form=$xpath->query('//input[@name="siren"]/ancestor::form')->item(0);
    vatAssert($form!==null,'Company form missing'); $values=array();
    foreach ($xpath->query('.//input|.//select|.//textarea',$form) as $field) {
        $name=$field->getAttribute('name'); if (!$name || $field->hasAttribute('disabled')) { continue; }
        $type=$field->getAttribute('type');
        if (in_array($type,array('file','submit','button'),true) || (in_array($type,array('radio','checkbox'),true) && !$field->hasAttribute('checked'))) { continue; }
        if ($field->nodeName==='select') {
            $selected=$xpath->query('.//option[@selected]',$field)->item(0) ?: $xpath->query('.//option',$field)->item(0);
            $values[$name]=$selected ? $selected->getAttribute('value') : '';
        } else { $values[$name]=$field->nodeName==='textarea' ? $field->textContent : $field->getAttribute('value'); }
    }
    return $values;
}
function vatStored($name) {
    global $db,$prefix,$entity;
    $r=$db->query("SELECT value FROM ".$prefix."const WHERE name='".$db->escape($name)."' AND entity=".$entity);
    vatAssert((bool)$r,'Fixture read failed'); $row=$db->fetch_object($r); return $row ? $row->value : null;
}
try {
    $body=vatPage(); vatAssert(preg_match('/name="token"[^>]*value="([^"]+)"/',$body,$token)===1,'Login token missing');
    curl_setopt($curl,CURLOPT_URL,'http://localhost/index.php'); curl_setopt($curl,CURLOPT_POST,true);
    curl_setopt($curl,CURLOPT_POSTFIELDS,http_build_query(array('token'=>html_entity_decode($token[1]),'actionlogin'=>'login','loginfunction'=>'loginfunction','username'=>'testadmin','password'=>getenv('DOLI_ADMIN_PASSWORD'))));
    vatAssert(curl_exec($curl)!==false,'Login failed');
    $post=vatForm(vatPage()); $post['action']='update'; $post['siren']='974760673'; $post['optiontva']='1'; $post['tva']='';
    $body=vatPage($post);
    vatAssert(vatStored('MAIN_INFO_TVAINTRA')==='974760673MVA','Core did not save generated VAT identifier');
    vatAssert(vatStored('MAIN_INFO_SIREN')==='974760673' && vatStored('FACTURE_TVAOPTION')==='1','Core company settings not saved');
    $post=vatForm($body); $post['action']='update'; $post['tva']='Existing synthetic VAT'; $body=vatPage($post);
    vatAssert(vatStored('MAIN_INFO_TVAINTRA')==='Existing synthetic VAT','Existing identifier overwritten');
    $post=vatForm($body); $post['action']='update'; $post['tva']=''; $post['siren']='123456789'; $body=vatPage($post);
    vatAssert(vatStored('MAIN_INFO_TVAINTRA')==='Existing synthetic VAT' && vatStored('MAIN_INFO_SIREN')==='974760673','Invalid organisation number did not stop core save');
    $post=vatForm($body); $post['action']='update'; $post['tva']=''; $post['siren']='974760673'; $post['optiontva']='0'; $body=vatPage($post);
    vatAssert((string)vatStored('MAIN_INFO_TVAINTRA')==='' && vatStored('FACTURE_TVAOPTION')==='0','VAT disabled still filled identifier');
    $post=vatForm($body); $post['action']='update'; $post['optiontva']='1'; $post['token']='invalid';
    curl_setopt($curl,CURLOPT_FOLLOWLOCATION,false); curl_setopt($curl,CURLOPT_URL,'http://localhost/admin/company.php'); curl_setopt($curl,CURLOPT_POST,true); curl_setopt($curl,CURLOPT_POSTFIELDS,http_build_query($post)); curl_exec($curl);
    vatAssert(curl_getinfo($curl,CURLINFO_HTTP_CODE)===200 && (string)vatStored('MAIN_INFO_TVAINTRA')==='' && vatStored('FACTURE_TVAOPTION')==='0','Company CSRF guard bypassed');
} finally {
    // This test runs alone in the explicitly guarded disposable database; restore all core-save constants.
    $db->begin();
    try {
        vatAssert((bool)$db->query('DELETE FROM '.$prefix.'const WHERE entity='.$entity),'Fixture restore failed');
        foreach ($snapshot as $row) {
            $values=array(); foreach (array('name','value','type','note') as $key) { $values[$key]="'".$db->escape((string)$row->$key)."'"; }
            vatAssert((bool)$db->query('INSERT INTO '.$prefix.'const (name,value,type,visible,note,entity) VALUES ('.$values['name'].','.$values['value'].','.$values['type'].','.(int)$row->visible.','.$values['note'].','.$entity.')'),'Fixture restore failed');
        }
        vatAssert($db->commit()>0,'Fixture restore commit failed');
    } catch (Throwable $error) { $db->rollback(); throw $error; }
    curl_close($curl); unlink($cookie);
}
print "PASS core company HTTP save: automatic VAT identifier, existing value preserved, invalid organisation blocked, VAT off and CSRF rejection\n";
