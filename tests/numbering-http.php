<?php
// SPDX-License-Identifier: GPL-3.0-or-later
if (PHP_SAPI!=='cli' || getenv('NORWAY_SYNTHETIC_TEST')!=='1' || getenv('DOLI_DB_NAME')!=='norway_test') { exit(1); }
require '/var/www/html/master.inc.php';
function nhAssert($ok,$message) { if (!$ok) { throw new RuntimeException($message); } }
function nhSql($sql) { global $db; $r=$db->query($sql); nhAssert((bool)$r,'Synthetic numbering HTTP SQL'); return $r; }
$p=$db->prefix(); $entity=(int)$conf->entity; $names="'FACTURE_ADDON','PROPALE_ADDON','SOCIETE_CODECLIENT_ADDON','NORWAY_NUMBERING_V1'";
$r=nhSql('SELECT * FROM '.$p.'const WHERE entity='.$entity.' AND name IN ('.$names.')'); $original=array(); while ($row=$db->fetch_object($r)) { $original[]=(array)$row; }
$cookie=tempnam(sys_get_temp_dir(),'norway-numbering-http-'); $curl=curl_init();
curl_setopt_array($curl,array(CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_TIMEOUT=>20));
function nhPage($path,$post=null) { global $curl; curl_setopt($curl,CURLOPT_URL,'http://localhost'.$path); curl_setopt($curl,CURLOPT_POST,$post!==null); if ($post!==null) { curl_setopt($curl,CURLOPT_POSTFIELDS,http_build_query($post)); } $body=curl_exec($curl); nhAssert($body!==false && curl_getinfo($curl,CURLINFO_HTTP_CODE)<500,'HTTP failed'); return $body; }
function nhToken($body,$name='token') { nhAssert(preg_match('/name="'.$name.'"[^>]*value="([^"]+)"/',$body,$m)===1,'Missing token/confirmation '.$name); return html_entity_decode($m[1],ENT_QUOTES,'UTF-8'); }
function nhRecord() { global $db,$p,$entity; $r=nhSql("SELECT value FROM ".$p."const WHERE entity=".$entity." AND name='NORWAY_NUMBERING_V1'"); $row=$db->fetch_object($r); return $row ? $row->value : null; }
try {
 nhAssert(nhRecord()===null,'Pre-existing synthetic profile');
 $path='/custom/norway/admin/numbering.php'; $body=nhPage($path);
 nhAssert(strpos($body,'previewnumbering')===false,'Unauthenticated numbering exposed');
 nhPage('/index.php',array('token'=>nhToken($body),'actionlogin'=>'login','loginfunction'=>'loginfunction','username'=>'testadmin','password'=>getenv('DOLI_ADMIN_PASSWORD')));
 foreach (array('nb_NO'=>'Nummerserier','en_US'=>'Number sequences') as $lang=>$title) { $body=nhPage($path.'?lang='.$lang); nhAssert(strpos(html_entity_decode($body,ENT_QUOTES,'UTF-8'),$title)!==false && strpos($body,'NorwayNumbering')===false,'Numbering translation missing'); }
 $preview=array('action'=>'previewnumbering','invoice_prefix'=>'F','invoice_start'=>'1201','credit_prefix'=>'K','credit_start'=>'501','proposal_prefix'=>'T','proposal_start'=>'301','customer_start'=>'1','supplier_start'=>'1','token'=>nhToken($body));
 $body=nhPage($path,$preview); nhAssert(strpos($body,'F001201')!==false && strpos($body,'K000501')!==false && strpos($body,'T000301')!==false && nhRecord()===null,'Preview wrote data or wrong start');
 nhPage($path,array('action'=>'savenumbering','token'=>nhToken($body),'preview_nonce'=>nhToken($body,'preview_nonce')));
 nhAssert(nhRecord()===null,'Unconfirmed numbering saved');
 $body=nhPage($path); $preview['token']=nhToken($body); $body=nhPage($path,$preview);
 $save=array('action'=>'savenumbering','token'=>nhToken($body),'preview_nonce'=>nhToken($body,'preview_nonce'),'confirmed'=>'1');
 nhPage($path,$save); $saved=nhRecord(); $record=json_decode($saved,true);
 nhAssert(($record['input']['invoice_start'] ?? null)===1201 && ($record['input']['proposal_start'] ?? null)===301,'Confirmed numbering missing');
 nhPage($path,$save); nhAssert(nhRecord()===$saved,'Replayed confirmation changed profile');
 $body=nhPage($path); $preview['token']=nhToken($body); $preview['invoice_start']='1202'; $body=nhPage($path,$preview);
 $save['token']=nhToken($body); $save['preview_nonce']=nhToken($body,'preview_nonce');
 $r=nhSql('SELECT rowid,fk_statut FROM '.$p.'facture WHERE entity='.$entity.' ORDER BY rowid LIMIT 1'); $invoice=$db->fetch_object($r);
 nhSql('UPDATE '.$p.'facture SET fk_statut=99 WHERE rowid='.(int)$invoice->rowid);
 try { nhPage($path,$save); nhAssert(nhRecord()===$saved,'Changed invoice history accepted over HTTP'); }
 finally { nhSql('UPDATE '.$p.'facture SET fk_statut='.(int)$invoice->fk_statut.' WHERE rowid='.(int)$invoice->rowid); }
 nhPage($path,array('action'=>'savenumbering','token'=>'invalid','confirmed'=>'1')); nhAssert(curl_getinfo($curl,CURLINFO_HTTP_CODE)===403 && nhRecord()===$saved,'Numbering CSRF guard failed');
 print "PASS numbering HTTP authentication, bilingual F/K/T previews, no implicit activation, confirmation, replay, changed history and CSRF\n";
} finally {
 nhSql('DELETE FROM '.$p.'const WHERE entity='.$entity.' AND name IN ('.$names.')');
 foreach ($original as $row) { $values=array_map(static fn($v)=>$v===null ? 'NULL' : "'".$db->escape((string)$v)."'",array_values($row)); nhSql('INSERT INTO '.$p.'const ('.implode(',',array_keys($row)).') VALUES ('.implode(',',$values).')'); }
 curl_close($curl); unlink($cookie);
}
