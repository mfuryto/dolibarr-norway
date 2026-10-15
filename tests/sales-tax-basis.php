<?php
// SPDX-License-Identifier: GPL-3.0-or-later
if (PHP_SAPI!=='cli' || getenv('NORWAY_SYNTHETIC_TEST')!=='1' || getenv('DOLI_DB_NAME')!=='norway_test') { exit(1); }
require '/var/www/html/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/norway/class/NorwaySalesTaxBasis.php';
require_once DOL_DOCUMENT_ROOT.'/custom/norway/core/modules/modNorway.class.php';
function sbAssert($ok,$message){if(!$ok){throw new RuntimeException($message);}}
function sbSql($sql){global $db;$r=$db->query($sql);sbAssert((bool)$r,$db->lasterror());return $r;}
function sbReject($fn){try{$fn();}catch(InvalidArgumentException|RuntimeException $expected){return;}throw new LogicException('Invalid operation accepted');}
$user->fetch('','testadmin');$user->loadRights('',1);$entity=(int)$conf->entity;$p=$db->prefix();
$names="'MAIN_INFO_SIREN','MAIN_INFO_SOCIETE_COUNTRY','NORWAY_TAX_MAPPINGS_V1'";$original=array();$r=sbSql('SELECT * FROM '.$p.'const WHERE entity='.$entity.' AND name IN ('.$names.')');while($o=$db->fetch_object($r)){$original[]=(array)$o;}
$id=0;$party=0;
try {
 $rule=array('revision'=>1,'user'=>(int)$user->id,'at'=>'2026-01-01T00:00:00+00:00','source'=>'SYN-BASIS','target'=>'5','start'=>'2026-01-01','end'=>'2026-12-31','direction'=>'sale','id'=>1,'retire'=>false);
 $record=array('version'=>1,'entity'=>$entity,'org'=>'123456785','reference'=>NorwayTaxReference::SOURCE,'events'=>array($rule));
 sbSql('DELETE FROM '.$p.'const WHERE entity='.$entity.' AND name IN ('.$names.')');
 foreach(array('MAIN_INFO_SIREN'=>'123456785','MAIN_INFO_SOCIETE_COUNTRY'=>'173:NO:Norway',NorwayTaxMappings::SETTING=>json_encode($record)) as $name=>$value){sbSql("INSERT INTO ".$p."const (name,value,type,entity) VALUES ('".$name."','".$db->escape($value)."','chaine',".$entity.")");}
 $country=(int)$db->fetch_object(sbSql("SELECT rowid FROM ".$p."c_country WHERE code='NO'"))->rowid;
 sbSql("INSERT INTO ".$p."societe (nom,entity,fk_pays) VALUES ('SYNTHETIC DOCUMENTARY BASIS ONLY',".$entity.','.$country.')');$party=(int)$db->last_insert_id($p.'societe');
 sbSql("INSERT INTO ".$p."facture (ref,entity,type,fk_statut,fk_soc,datef,multicurrency_code,multicurrency_tx,total_ht,total_tva,total_ttc) VALUES ('SYN-BASIS-".bin2hex(random_bytes(5))."',".$entity.",0,0,".$party.",'2026-01-01','NOK',1,100,0,100)");$id=(int)$db->last_insert_id($p.'facture');
 sbSql("INSERT INTO ".$p."facturedet (fk_facture,description,product_type,qty,subprice,tva_tx,vat_src_code,info_bits,localtax1_tx,localtax2_tx,total_ht,total_tva,total_ttc,total_localtax1,total_localtax2) VALUES (".$id.",'SYNTHETIC DOCUMENTATION',1,1,100,0,'SYN-BASIS',0,0,0,100,0,100,0,0)");$line=(int)$db->last_insert_id($p.'facturedet');
 $input=array(array('line'=>$line,'target'=>'5','reason'=>'Synthetic documented domestic classification','reference'=>'SYN-AGREEMENT-001'));
 $basis=NorwaySalesTaxBasis::basis($db,$entity,$id,$user);$preview=NorwaySalesTaxBasis::preview($db,$entity,$id,$user,$input);
 $reader=clone $user;$reader->admin=0;sbReject(fn()=>NorwaySalesTaxBasis::preview($db,$entity,$id,$reader,$input));
 sbReject(fn()=>NorwaySalesTaxBasis::basis($db,$entity+1,$id,$user));
 foreach(array('missing','duplicate','foreign','target','reason','reference') as $case){$bad=$input;if($case==='missing'){$bad=array();}if($case==='duplicate'){$bad[]=$bad[0];}if($case==='foreign'){$bad[0]['line']++;}if($case==='target'){$bad[0]['target']='6';}if($case==='reason'){$bad[0]['reason']='short';}if($case==='reference'){$bad[0]['reference']='';}sbReject(fn()=>NorwaySalesTaxBasis::choices($basis,$entity,$bad));}
 sbAssert(NorwaySalesTaxBasis::save($db,$entity,$id,$user,$input,$preview['fingerprint'])===1,'Revision 1 missing');
 sbReject(fn()=>NorwaySalesTaxBasis::save($db,$entity,$id,$user,$input,$preview['fingerprint']));
 $history=NorwaySalesTaxBasis::history($db,$entity,$id,$user);$first=$history[0];
 sbSql('UPDATE '.$p.'facture SET fk_statut=2 WHERE rowid='.$id);sbAssert(NorwaySalesTaxBasis::basis($db,$entity,$id,$user)===$basis,'Payment status invalidated basis');
 $stale=NorwaySalesTaxBasis::preview($db,$entity,$id,$user,$input);sbSql("UPDATE ".$p."facturedet SET description='SYNTHETIC CHANGED DESCRIPTION' WHERE rowid=".$line);
 sbReject(fn()=>NorwaySalesTaxBasis::save($db,$entity,$id,$user,$input,$stale['fingerprint']));sbAssert(NorwaySalesTaxBasis::basis($db,$entity,$id,$user)!==$basis,'Description change hidden');
 $new=NorwaySalesTaxBasis::preview($db,$entity,$id,$user,$input);NorwaySalesTaxBasis::save($db,$entity,$id,$user,$input,$new['fingerprint']);
 sbAssert(NorwaySalesTaxBasis::history($db,$entity,$id,$user)[0]===$first,'Original overwritten');
 foreach(array('6','7','51','52') as $target){$b=$basis;$r=$record;$r['events'][0]['target']=$target;foreach($b['settings'] as &$s){if($s['name']===NorwayTaxMappings::SETTING){$s['value']=json_encode($r);}}unset($s);if(in_array($target,array('6','7'),true)){sbAssert(NorwaySalesTaxBasis::eligible($b,$entity)[$line]===$target,'Domestic category lost');}else{sbReject(fn()=>NorwaySalesTaxBasis::eligible($b,$entity));}}
 $b=$basis;$b['invoice']['type']='2';sbReject(fn()=>NorwaySalesTaxBasis::eligible($b,$entity));$b=$basis;$b['party']['country']='SE';sbReject(fn()=>NorwaySalesTaxBasis::eligible($b,$entity));
 // Changes beyond rates invalidate outstanding confirmation against freshly read database source.
 foreach(array(array('facture','datef',"'2026-02-01'","'2026-01-01'",$id),array('facturedet','qty','2','1',$line),array('societe','nom',"'SYNTHETIC CHANGED CUSTOMER'","'SYNTHETIC DOCUMENTARY BASIS ONLY'",$party)) as $mutation){
  $pending=NorwaySalesTaxBasis::preview($db,$entity,$id,$user,$input);[$table,$column,$value,$restore,$rowid]=$mutation;
  sbSql('UPDATE '.$p.$table.' SET '.$column.'='.$value.' WHERE rowid='.$rowid);sbReject(fn()=>NorwaySalesTaxBasis::save($db,$entity,$id,$user,$input,$pending['fingerprint']));
  sbSql('UPDATE '.$p.$table.' SET '.$column.'='.$restore.' WHERE rowid='.$rowid);
 }
 // Mixed invoices require reasons only for explicitly classified zero-rate lines.
 $b=$basis;$positive=$b['lines'][0];$positive['rowid']=$line+1;$positive['tva_tx']='25';$positive['total_tva']='25';$positive['vat_src_code']='SYN-POSITIVE';$b['lines'][]=$positive;
 sbAssert(NorwaySalesTaxBasis::choices($b,$entity,$input)===$input,'Positive rate line required zero-rate reason');
 // Exercise the actual invoice tab, authenticated preview/confirmation, CSRF and nonce replay.
 $cookie=tempnam(sys_get_temp_dir(),'norway-basis-http-');$curl=curl_init();
 curl_setopt_array($curl,array(CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_TIMEOUT=>20));
 $page=static function($path,$post=null) use($curl){curl_setopt($curl,CURLOPT_URL,'http://localhost'.$path);curl_setopt($curl,CURLOPT_POST,$post!==null);if($post!==null){curl_setopt($curl,CURLOPT_POSTFIELDS,http_build_query($post));}$body=curl_exec($curl);sbAssert($body!==false&&curl_getinfo($curl,CURLINFO_HTTP_CODE)<500,'HTTP failure');return $body;};
 $token=static function($body){sbAssert(preg_match('/name="token"[^>]*value="([^"]+)"/',$body,$m)===1,'Token missing');return html_entity_decode($m[1],ENT_QUOTES,'UTF-8');};
 try {
  $path='/custom/norway/admin/salestaxbasis.php?id='.$id;
  $body=$page($path);sbAssert(strpos($body,'SYNTHETIC CHANGED DESCRIPTION')===false&&strpos($body,'norway-sales-basis-form')===false,'Unauthenticated basis exposed');
  $page('/index.php',array('token'=>$token($body),'username'=>'testadmin','password'=>getenv('DOLI_ADMIN_PASSWORD'),'actionlogin'=>'login','loginfunction'=>'loginfunction'));
  foreach(array('nb_NO','en_US') as $locale){$body=$page($path.'&lang='.$locale);sbAssert(strpos($body,'norway-sales-basis-form')!==false&&strpos($body,'NorwaySalesBasis')===false,'Translated form missing');}
  $module=new modNorway($db);sbAssert($module->init()>0,'Tab activation failed');
  $body=$page('/compta/facture/card.php?facid='.$id);sbAssert(strpos($body,'/norway/admin/salestaxbasis.php?id='.$id)!==false,'Native invoice basis tab missing');
  $body=$page($path);$post=array('id'=>$id,'action'=>'preview','reason'=>array($line=>$input[0]['reason']),'reference'=>array($line=>$input[0]['reference']));
  $page($path,$post+array('token'=>'invalid'));sbAssert(count(NorwaySalesTaxBasis::history($db,$entity,$id,$user))===2,'CSRF wrote basis');
  $body=$page($path);$body=$page($path,$post+array('token'=>$token($body)));sbAssert(preg_match('/name="nonce" value="([^"]+)"/',$body,$m)===1,'Confirmation missing');
  $save=array('id'=>$id,'action'=>'save','token'=>$token($body),'nonce'=>$m[1],'confirmed'=>'1');$page($path,$save);
  sbAssert(count(NorwaySalesTaxBasis::history($db,$entity,$id,$user))===3,'HTTP save missing');$page($path,$save);sbAssert(count(NorwaySalesTaxBasis::history($db,$entity,$id,$user))===3,'Nonce replay wrote basis');
 } finally {curl_close($curl);unlink($cookie);}
 // Failed post-write verification must roll back the appended revision.
 $new=NorwaySalesTaxBasis::preview($db,$entity,$id,$user,$input);
 $failing=new class($db){private $inner;private $written=false;public function __construct($db){$this->inner=$db;}public function __call($name,$args){if($name==='query'&&strpos($args[0],'INSERT INTO '.$this->inner->prefix().'norway_tax_evidence')===0){$this->written=true;}elseif($name==='query'&&$this->written&&strpos($args[0],'SELECT revision,captured_at')===0){return false;}return $this->inner->$name(...$args);}};
 sbReject(fn()=>NorwaySalesTaxBasis::save($failing,$entity,$id,$user,$input,$new['fingerprint']));sbAssert(count(NorwaySalesTaxBasis::history($db,$entity,$id,$user))===3,'Failed write persisted');
 sbSql("UPDATE ".$p."norway_tax_evidence SET payload_sha256='".str_repeat('0',64)."' WHERE entity=".$entity." AND document_type='sales_tax_basis' AND fk_document=".$id.' AND revision=2');sbReject(fn()=>NorwaySalesTaxBasis::history($db,$entity,$id,$user));
} finally {
 if($id){sbSql("DELETE FROM ".$p."norway_tax_evidence WHERE entity=".$entity." AND document_type='sales_tax_basis' AND fk_document=".$id);sbSql('DELETE FROM '.$p.'facturedet WHERE fk_facture='.$id);sbSql('DELETE FROM '.$p.'facture WHERE rowid='.$id);}
 if($party){sbSql('DELETE FROM '.$p.'societe WHERE rowid='.$party);}
 sbSql('DELETE FROM '.$p.'const WHERE entity='.$entity.' AND name IN ('.$names.')');foreach($original as $row){unset($row['rowid']);$cols=array_keys($row);$values=array_map(fn($v)=>$v===null?'NULL':"'".$db->escape((string)$v)."'",array_values($row));sbSql('INSERT INTO '.$p.'const (`'.implode('`,`',$cols).'`) VALUES ('.implode(',',$values).')');}
}
print "PASS sales documentary basis revisions, domestic categories, permission/entity/coverage guards, stale/replay, unchanged payment state, immutable history, corruption and rollback\n";
