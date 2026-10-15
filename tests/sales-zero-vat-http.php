<?php
// SPDX-License-Identifier: GPL-3.0-or-later
if (PHP_SAPI!=='cli' || getenv('NORWAY_SYNTHETIC_TEST')!=='1' || getenv('DOLI_DB_NAME')!=='norway_test') { exit(1); }
require '/var/www/html/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/accountancy/class/bookkeeping.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/norway/class/NorwayPostingEvidence.php';
require_once DOL_DOCUMENT_ROOT.'/custom/norway/class/NorwaySalesZeroVat.php';
function zhAssert($ok,$message) { if (!$ok) { throw new RuntimeException($message); } }
function zhSql($sql) { global $db;$r=$db->query($sql);zhAssert((bool)$r,$db->lasterror());return $r; }
zhAssert($user->fetch('','testadmin')>0,'Synthetic admin missing');$user->loadRights('',1);
$p=$db->prefix();$entity=(int)$conf->entity;$names="'MAIN_INFO_SIREN','MAIN_INFO_SOCIETE_COUNTRY','NORWAY_TAX_MAPPINGS_V1'";
$r=zhSql('SELECT * FROM '.$p.'const WHERE entity='.$entity.' AND name IN ('.$names.')');$original=array();while($o=$db->fetch_object($r)){$original[]=(array)$o;}
$id=0;$posted=array();$cookie=tempnam(sys_get_temp_dir(),'norway-zero-http-');$curl=curl_init();
curl_setopt_array($curl,array(CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_TIMEOUT=>20));
function zhPage($path,$post=null){global $curl;curl_setopt($curl,CURLOPT_URL,'http://localhost'.$path);curl_setopt($curl,CURLOPT_POST,$post!==null);if($post!==null){curl_setopt($curl,CURLOPT_POSTFIELDS,http_build_query($post));}$body=curl_exec($curl);zhAssert($body!==false&&curl_getinfo($curl,CURLINFO_HTTP_CODE)<500,'HTTP failure');return $body;}
try {
 $rule=array('revision'=>1,'user'=>(int)$user->id,'at'=>'2026-01-01T00:00:00+00:00','source'=>'SYN-ZERO','target'=>'5','start'=>'2026-01-01','end'=>'2026-12-31','direction'=>'sale','id'=>1,'retire'=>false);
 $record=array('version'=>1,'entity'=>$entity,'org'=>'123456785','reference'=>NorwayTaxReference::SOURCE,'events'=>array($rule));
 zhSql('DELETE FROM '.$p.'const WHERE entity='.$entity.' AND name IN ('.$names.')');
 foreach(array('MAIN_INFO_SIREN'=>'123456785','MAIN_INFO_SOCIETE_COUNTRY'=>'173:NO:Norway',NorwayTaxMappings::SETTING=>json_encode($record)) as $name=>$value){zhSql("INSERT INTO ".$p."const (name,value,type,entity) VALUES ('".$name."','".$db->escape($value)."','chaine',".$entity.")");}
 $party=$db->fetch_object(zhSql('SELECT rowid FROM '.$p.'societe WHERE entity='.$entity.' ORDER BY rowid LIMIT 1'));zhAssert((bool)$party,'Synthetic party missing');
 $ref='SYN-ZERO-'.bin2hex(random_bytes(5));
 zhSql("INSERT INTO ".$p."facture (ref,entity,type,fk_statut,fk_soc,datef,multicurrency_code,total_ht,total_tva,total_ttc) VALUES ('".$ref."',".$entity.",0,1,".(int)$party->rowid.",'2026-01-01','NOK',100,0,100)");$id=(int)$db->last_insert_id($p.'facture');
 zhSql("INSERT INTO ".$p."facturedet (fk_facture,description,product_type,qty,subprice,tva_tx,vat_src_code,info_bits,localtax1_tx,localtax2_tx,total_ht,total_tva,total_ttc) VALUES (".$id.",'SYNTHETIC ZERO RATE ONLY',1,1,100,0,'SYN-ZERO',0,0,0,100,0,100)");
 $piece=(int)$db->fetch_object(zhSql('SELECT COALESCE(MAX(piece_num),0)+1 n FROM '.$p.'accounting_bookkeeping WHERE entity='.$entity))->n;
 $db->begin();
 foreach(array('1500'=>array(100,0),'3000'=>array(0,100)) as $account=>$amount){
  $o=new BookKeeping($db);$o->entity=$entity;$o->doc_date=strtotime('2026-01-01');$o->doc_type='customer_invoice';$o->doc_ref=$ref;$o->fk_doc=$id;$o->fk_docdet=0;
  $o->numero_compte=(string)$account;$o->label_compte='Synthetic zero rate';$o->label_operation='Synthetic zero rate';$o->debit=$amount[0];$o->credit=$amount[1];$o->montant=$amount[0]-$amount[1];$o->sens=$amount[0]?'D':'C';$o->code_journal='SYN-ZERO';$o->journal_label='Synthetic';$o->piece_num=$piece;$o->ref='SYN-ZERO';
  zhAssert($o->createStd($user)>0,'Native synthetic zero posting failed');$posted[]=(int)$o->id;
 }
 $db->commit();
 $observations=NorwayPostingEvidence::report($db,$entity,$id,$user);zhAssert(count($observations)===2,'Native zero observations missing');
 foreach($observations as $entry){zhAssert(NorwaySalesZeroVat::captured($entry['data']['basis'],$entity)['rows'][0]['category']==='DomesticExempt','Native retained zero classification failed');}
 $before=array();$r=zhSql("SELECT rowid,payload_sha256 FROM ".$p."norway_tax_evidence WHERE entity=".$entity." AND document_type='bookkeeping_event' AND fk_document IN (".implode(',',$posted).")");while($o=$db->fetch_object($r)){$before[(int)$o->rowid]=$o->payload_sha256;}
 $body=zhPage('/custom/norway/admin/invoice.php?id='.$id.'&ledger=1&mapping_revision=1');zhAssert(strpos($body,'norway-zero-vat')===false,'Unauthenticated zero basis exposed');
 zhAssert(preg_match('/name="token"[^>]*value="([^"]+)"/',$body,$m)===1,'Login token missing');
 zhPage('/index.php',array('token'=>html_entity_decode($m[1],ENT_QUOTES,'UTF-8'),'username'=>'testadmin','password'=>getenv('DOLI_ADMIN_PASSWORD'),'actionlogin'=>'login','loginfunction'=>'loginfunction'));
 $rule['revision']=2;$rule['target']='6';$record['events'][]=$rule;
 zhSql("UPDATE ".$p."const SET value='".$db->escape(json_encode($record))."' WHERE entity=".$entity." AND name='".NorwayTaxMappings::SETTING."'");
 foreach(array('nb_NO'=>array('Innenlands fritak (nullsats)','Omsetning utenfor MVA-loven'),'en_US'=>array('Domestic zero-rated exemption','Turnover outside VAT legislation')) as $locale=>$labels){
  $body=zhPage('/custom/norway/admin/invoice.php?id='.$id.'&ledger=1&mapping_revision=2&lang='.$locale);
  zhAssert(substr_count($body,'class="norway-zero-vat"')===3,'Current and retained zero controls missing');
  foreach($labels as $label){zhAssert(strpos($body,$label)!==false,'Current/retained distinction not rendered');}
  zhAssert(strpos($body,'NorwayZeroVat')===false,'Zero VAT translation missing');
 }
 foreach($observations as $entry){zhAssert(NorwaySalesZeroVat::captured($entry['data']['basis'],$entity)['rows'][0]['category']==='DomesticExempt','Current mapping rewrote retained category');}
 $after=array();$r=zhSql("SELECT rowid,payload_sha256 FROM ".$p."norway_tax_evidence WHERE entity=".$entity." AND document_type='bookkeeping_event' AND fk_document IN (".implode(',',$posted).")");while($o=$db->fetch_object($r)){$after[(int)$o->rowid]=$o->payload_sha256;}
 zhAssert($before===$after,'Read-only zero control changed observations');
 print "PASS native balanced zero sales capture, authenticated bilingual HTTP, current/retained mapping distinction and unchanged observation hashes\n";
} finally {
 $db->rollback();
 if($posted){zhSql("DELETE FROM ".$p."norway_tax_evidence WHERE entity=".$entity." AND document_type='bookkeeping_event' AND fk_document IN (".implode(',',$posted).")");zhSql('DELETE FROM '.$p.'accounting_bookkeeping WHERE rowid IN ('.implode(',',$posted).') AND entity='.$entity);}
 if($id){zhSql('DELETE FROM '.$p.'facturedet WHERE fk_facture='.$id);zhSql('DELETE FROM '.$p.'facture WHERE rowid='.$id.' AND entity='.$entity);}
 zhSql('DELETE FROM '.$p.'const WHERE entity='.$entity.' AND name IN ('.$names.')');
 foreach($original as $row){$values=array_map(static fn($v)=>$v===null?'NULL':"'".$db->escape((string)$v)."'",array_values($row));zhSql('INSERT INTO '.$p.'const ('.implode(',',array_keys($row)).') VALUES ('.implode(',',$values).')');}
 curl_close($curl);unlink($cookie);
}
