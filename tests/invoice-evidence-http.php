<?php
// SPDX-License-Identifier: GPL-3.0-or-later
if (PHP_SAPI!=='cli' || getenv('NORWAY_SYNTHETIC_TEST')!=='1' || getenv('DOLI_DB_NAME')!=='norway_test') { exit(1); }
require '/var/www/html/master.inc.php';
function ehAssert($ok,$message) { if (!$ok) { throw new RuntimeException($message); } }
function ehSql($sql) { global $db; $r=$db->query($sql); ehAssert((bool)$r,'Synthetic HTTP fixture failed'); return $r; }
$p=$db->prefix(); $entity=(int)$conf->entity;
$r=ehSql("SELECT fk_doc FROM ".$p."accounting_bookkeeping WHERE entity=".$entity." AND doc_type='customer_invoice' ORDER BY rowid LIMIT 1"); $invoice=$db->fetch_object($r); ehAssert((bool)$invoice,'Synthetic invoice missing'); $id=(int)$invoice->fk_doc;
$names="'MAIN_INFO_SIREN','MAIN_INFO_SOCIETE_COUNTRY','NORWAY_TAX_MAPPINGS_V1'";
$r=ehSql('SELECT * FROM '.$p.'const WHERE entity='.$entity.' AND name IN ('.$names.')'); $original=array(); while ($row=$db->fetch_object($r)) { $original[]=(array)$row; }
$cookie=tempnam(sys_get_temp_dir(),'norway-evidence-http-'); $curl=curl_init();
curl_setopt_array($curl,array(CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_TIMEOUT=>20));
function ehPage($path,$post=null) {
 global $curl; curl_setopt($curl,CURLOPT_URL,'http://localhost'.$path); curl_setopt($curl,CURLOPT_POST,$post!==null);
 if ($post!==null) { curl_setopt($curl,CURLOPT_POSTFIELDS,http_build_query($post)); }
 $body=curl_exec($curl); ehAssert($body!==false && curl_getinfo($curl,CURLINFO_HTTP_CODE)<500,'HTTP failure'); return $body;
}
function ehToken($body,$name='token') { ehAssert(preg_match('/name="'.$name.'"[^>]*value="([^"]+)"/',$body,$m)===1,'Missing confirmation field '.$name); return html_entity_decode($m[1],ENT_QUOTES,'UTF-8'); }
function ehCount() { global $db,$p,$entity,$id; return (int)$db->fetch_object(ehSql("SELECT COUNT(*) AS n FROM ".$p."norway_tax_evidence WHERE entity=".$entity." AND document_type='customer_invoice' AND fk_document=".$id))->n; }
try {
 ehAssert(ehCount()===0,'Unexpected pre-existing synthetic copies');
 ehSql('DELETE FROM '.$p.'const WHERE entity='.$entity.' AND name IN ('.$names.')');
 require_once DOL_DOCUMENT_ROOT.'/custom/norway/class/NorwayTaxMappings.php';
 $record=array('version'=>1,'entity'=>$entity,'org'=>'123456785','reference'=>NorwayTaxReference::SOURCE,'events'=>array(array('source'=>'SYNHTTP','target'=>'3','start'=>'2026-01-01','end'=>'2026-12-31','direction'=>'sale','id'=>1,'retire'=>false,'revision'=>1,'user'=>1,'at'=>'2026-01-01T00:00:00+00:00')));
 foreach (array('MAIN_INFO_SIREN'=>'123456785','MAIN_INFO_SOCIETE_COUNTRY'=>'173:NO:Norway','NORWAY_TAX_MAPPINGS_V1'=>json_encode($record,JSON_THROW_ON_ERROR)) as $name=>$value) { ehSql("INSERT INTO ".$p."const (name,value,type,entity) VALUES ('".$name."','".$db->escape($value)."','chaine',".$entity.")"); }
 $path='/custom/norway/admin/evidence.php?id='.$id;
 $body=ehPage($path); ehAssert(strpos($body,'previewevidence')===false,'Unauthenticated source exposed');
 ehPage('/index.php',array('token'=>ehToken($body),'actionlogin'=>'login','loginfunction'=>'loginfunction','username'=>'testadmin','password'=>getenv('DOLI_ADMIN_PASSWORD')));
 foreach (array('nb_NO'=>'Kontrollkopier av fakturagrunnlag','en_US'=>'Invoice evidence control copies') as $lang=>$title) { $body=ehPage($path.'&lang='.$lang); ehAssert(strpos(html_entity_decode($body,ENT_QUOTES,'UTF-8'),$title)!==false && strpos($body,'NorwayEvidence')===false,'Evidence translation missing'); }
 $body=ehPage($path,array('token'=>ehToken($body),'action'=>'previewevidence','id'=>$id,'mapping_revision'=>1));
 $nonce=ehToken($body,'preview_nonce'); $token=ehToken($body);
 ehAssert(ehCount()===0,'Preview wrote a copy');
 ehPage($path,array('token'=>$token,'action'=>'saveevidence','id'=>$id,'preview_nonce'=>$nonce)); ehAssert(ehCount()===0,'Missing confirmation accepted');
 $body=ehPage($path); $body=ehPage($path,array('token'=>ehToken($body),'action'=>'previewevidence','id'=>$id,'mapping_revision'=>1));
 $save=array('token'=>ehToken($body),'action'=>'saveevidence','id'=>$id,'preview_nonce'=>ehToken($body,'preview_nonce'),'confirmed'=>'1');
 ehPage($path,$save); ehAssert(ehCount()===1,'Confirmed copy missing');
 ehPage($path,$save); ehAssert(ehCount()===1,'HTTP replay created copy');
 foreach (array('nb_NO'=>'Ingen forskjeller i de undersøkte feltene.','en_US'=>'No differences in the examined fields.') as $lang=>$message) {
  $body=ehPage($path.'&copy_revision=1&lang='.$lang);
  ehAssert(strpos(html_entity_decode($body,ENT_QUOTES,'UTF-8'),$message)!==false && strpos($body,'NorwayEvidence')===false,'Comparison translation/result missing');
 }
 ehAssert(ehCount()===1,'Comparison created a copy');
 ehSql('UPDATE '.$p.'facture SET total_ht=total_ht+1 WHERE rowid='.$id.' AND entity='.$entity);
 try {
  $body=ehPage($path.'&copy_revision=1&lang=en_US');
  ehAssert(strpos($body,'Changed value')!==false && strpos($body,'Amount excluding VAT')!==false,'HTTP difference missing');
 } finally { ehSql('UPDATE '.$p.'facture SET total_ht=total_ht-1 WHERE rowid='.$id.' AND entity='.$entity); }
 $body=ehPage($path.'&copy_revision=999&lang=en_US');
 ehAssert(strpos($body,'Comparison could not be completed.')!==false && strpos($body,'No differences in the examined fields.')===false,'Unknown copy presented as equal');

 $body=ehPage($path); $body=ehPage($path,array('token'=>ehToken($body),'action'=>'previewevidence','id'=>$id,'mapping_revision'=>1));
 $save['token']=ehToken($body); $save['preview_nonce']=ehToken($body,'preview_nonce');
 ehSql('UPDATE '.$p.'facture SET total_ht=total_ht+1 WHERE rowid='.$id.' AND entity='.$entity);
 try { ehPage($path,$save); ehAssert(ehCount()===1,'HTTP stale source accepted'); }
 finally { ehSql('UPDATE '.$p.'facture SET total_ht=total_ht-1 WHERE rowid='.$id.' AND entity='.$entity); }
 ehPage($path,array('token'=>'invalid','action'=>'saveevidence','id'=>$id,'confirmed'=>'1'));
 ehAssert(curl_getinfo($curl,CURLINFO_HTTP_CODE)===403 && ehCount()===1,'Evidence CSRF guard failed');
 print "PASS evidence HTTP authentication, bilingual rendering, preview-only, explicit confirmation, replay, stale source and CSRF\n";
} finally {
 ehSql("DELETE FROM ".$p."norway_tax_evidence WHERE entity=".$entity." AND document_type='customer_invoice' AND fk_document=".$id);
 ehSql('DELETE FROM '.$p.'const WHERE entity='.$entity.' AND name IN ('.$names.')');
 foreach ($original as $row) {
  $values=array_map(static fn($v)=>$v===null ? 'NULL' : "'".$db->escape((string)$v)."'",array_values($row));
  ehSql('INSERT INTO '.$p.'const ('.implode(',',array_keys($row)).') VALUES ('.implode(',',$values).')');
 }
 curl_close($curl); unlink($cookie);
}
