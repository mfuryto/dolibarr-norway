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
$partnerId=0;
try {
 nhAssert(nhRecord()===null,'Pre-existing synthetic profile');
 $path='/custom/norway/admin/numbering.php'; $body=nhPage($path);
 nhAssert(strpos($body,'previewnumbering')===false,'Unauthenticated numbering exposed');
 nhPage('/index.php',array('token'=>nhToken($body),'actionlogin'=>'login','loginfunction'=>'loginfunction','username'=>'testadmin','password'=>getenv('DOLI_ADMIN_PASSWORD')));
 foreach (array('nb_NO'=>'Nummerserier','en_US'=>'Number sequences') as $lang=>$title) { $body=nhPage($path.'?lang='.$lang); nhAssert(strpos(html_entity_decode($body,ENT_QUOTES,'UTF-8'),$title)!==false && strpos($body,'NorwayNumbering')===false,'Numbering translation missing'); }
 $preview=array('action'=>'previewnumbering','invoice_prefix'=>'F','invoice_start'=>'1201','credit_prefix'=>'K','credit_start'=>'501','proposal_prefix'=>'T','proposal_start'=>'301','customer_start'=>'1','supplier_start'=>'1','customer_prefix'=>'K','supplier_prefix'=>'L','token'=>nhToken($body));
 $body=nhPage($path,$preview); nhAssert(strpos($body,'F001201')!==false && strpos($body,'K000501')!==false && strpos($body,'T000301')!==false && strpos($body,'K00001')!==false && strpos($body,'L00001')!==false && nhRecord()===null,'Preview wrote data or wrong start');
 nhPage($path,array('action'=>'savenumbering','token'=>nhToken($body),'preview_nonce'=>nhToken($body,'preview_nonce')));
 nhAssert(nhRecord()===null,'Unconfirmed numbering saved');
 $body=nhPage($path); $preview['token']=nhToken($body); $body=nhPage($path,$preview);
 $save=array('action'=>'savenumbering','token'=>nhToken($body),'preview_nonce'=>nhToken($body,'preview_nonce'),'confirmed'=>'1');
 nhPage($path,$save); $saved=nhRecord(); $record=json_decode($saved,true);
 nhAssert(($record['input']['invoice_start'] ?? null)===1201 && ($record['input']['proposal_start'] ?? null)===301,'Confirmed numbering missing');
 nhPage($path,$save); nhAssert(nhRecord()===$saved,'Replayed confirmation changed profile');
 require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';$conf->setValues($db);$user->fetch('','testadmin');$user->loadRights('',1);
 $partner=new Societe($db);$partner->name='SYNTHETIC HTTP PREFIX PARTNER ONLY';$partner->client=1;$partner->fournisseur=1;$partner->code_client='auto';$partner->code_fournisseur='auto';$partner->country_id=173;nhAssert($partner->create($user)>0,'HTTP profile native partner failed');$partnerId=(int)$partner->id;
 $body=nhPage($path);nhAssert(preg_match('/<input[^>]*name="customer_prefix"[^>]*>/',$body,$field)===1 && strpos($field[0],'readonly')===false,'Used prefix field is locked');
 $change=$preview;$change['customer_prefix']='C';$change['token']=nhToken($body);$body=nhPage($path,$change);nhAssert(strpos($body,'C00002')!==false,'HTTP prefix change reset sequence');
 nhPage($path,array('action'=>'savenumbering','token'=>nhToken($body),'preview_nonce'=>nhToken($body,'preview_nonce'),'confirmed'=>'1'));$saved=nhRecord();nhAssert(json_decode($saved,true)['input']['customer_prefix']==='C','HTTP prefix change not saved');
 $old=$db->fetch_object(nhSql('SELECT code_client,code_fournisseur FROM '.$p.'societe WHERE rowid='.$partnerId));nhAssert($old->code_client==='K00001'&&$old->code_fournisseur==='L00001','HTTP prefix save rewrote old codes');
 $body=nhPage($path); $preview['token']=nhToken($body); $preview['invoice_start']='1202'; $body=nhPage($path,$preview);
 $save['token']=nhToken($body); $save['preview_nonce']=nhToken($body,'preview_nonce');
 $r=nhSql('SELECT rowid,fk_statut FROM '.$p.'facture WHERE entity='.$entity.' ORDER BY rowid LIMIT 1'); $invoice=$db->fetch_object($r);
 nhSql('UPDATE '.$p.'facture SET fk_statut=99 WHERE rowid='.(int)$invoice->rowid);
 try { nhPage($path,$save); nhAssert(nhRecord()===$saved,'Changed invoice history accepted over HTTP'); }
 finally { nhSql('UPDATE '.$p.'facture SET fk_statut='.(int)$invoice->fk_statut.' WHERE rowid='.(int)$invoice->rowid); }
 nhPage($path,array('action'=>'savenumbering','token'=>'invalid','confirmed'=>'1')); nhAssert(curl_getinfo($curl,CURLINFO_HTTP_CODE)===403 && nhRecord()===$saved,'Numbering CSRF guard failed');
 print "PASS numbering HTTP authentication, bilingual F/K/T previews, no implicit activation, confirmation, replay, changed history and CSRF\n";
} finally {
 if($partnerId){nhSql('DELETE FROM '.$p.'societe_extrafields WHERE fk_object='.$partnerId);nhSql('DELETE FROM '.$p.'societe WHERE rowid='.$partnerId.' AND entity='.$entity);}
 nhSql('DELETE FROM '.$p.'const WHERE entity='.$entity.' AND name IN ('.$names.')');
 foreach ($original as $row) { $values=array_map(static fn($v)=>$v===null ? 'NULL' : "'".$db->escape((string)$v)."'",array_values($row)); nhSql('INSERT INTO '.$p.'const ('.implode(',',array_keys($row)).') VALUES ('.implode(',',$values).')'); }
 curl_close($curl); unlink($cookie);
}
