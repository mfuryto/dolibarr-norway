<?php
// SPDX-License-Identifier: GPL-3.0-or-later
if (PHP_SAPI!=='cli' || getenv('NORWAY_SYNTHETIC_TEST')!=='1' || getenv('DOLI_DB_NAME')!=='norway_test') { exit(1); }
require '/var/www/html/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/norway/class/NorwaySalesTaxBasis.php';
require_once DOL_DOCUMENT_ROOT.'/custom/norway/class/NorwayPostingEvidence.php';
require_once DOL_DOCUMENT_ROOT.'/custom/norway/class/NorwayVatHistory.php';
require_once DOL_DOCUMENT_ROOT.'/accountancy/class/bookkeeping.class.php';
function sbAssert($ok,$message){if(!$ok){throw new RuntimeException($message);}}
function sbSql($sql){global $db;$r=$db->query($sql);sbAssert((bool)$r,$db->lasterror());return $r;}
function sbReject($fn){try{$fn();}catch(InvalidArgumentException|RuntimeException $expected){return;}throw new LogicException('Invalid operation accepted');}
$user->fetch('','testadmin');$user->loadRights('',1);$entity=(int)$conf->entity;$p=$db->prefix();
$names="'MAIN_INFO_SIREN','MAIN_INFO_SOCIETE_COUNTRY','NORWAY_TAX_MAPPINGS_V1','NORWAY_VAT_REGISTRATION_START','NORWAY_VAT_REGISTRATION_HISTORY_V1'";$original=array();$r=sbSql('SELECT * FROM '.$p.'const WHERE entity='.$entity.' AND name IN ('.$names.')');while($o=$db->fetch_object($r)){$original[]=(array)$o;}
$id=0;$party=0;$posted=array();
function sbCapture(){global $db,$p,$entity,$id,$user,$posted;$db->begin();$ids=array();
 try{foreach(array('1500'=>array(100,0),'3100'=>array(0,100)) as $account=>$amount){
  $o=new BookKeeping($db);$o->entity=$entity;$o->doc_date=strtotime('2026-01-01');$o->doc_type='customer_invoice';$o->doc_ref=$db->fetch_object(sbSql('SELECT ref FROM '.$p.'facture WHERE rowid='.$id))->ref;$o->fk_doc=$id;$o->fk_docdet=0;$o->numero_compte=(string)$account;$o->label_compte='Synthetic documentary capture';$o->label_operation='Synthetic';$o->debit=$amount[0];$o->credit=$amount[1];$o->montant=$amount[0]-$amount[1];$o->sens=$amount[0]?'D':'C';$o->code_journal='SYN-BASIS';$o->journal_label='Synthetic';$o->piece_num=987710+count($posted);$o->ref='SYN-BASIS';
  if($o->createStd($user)<=0){throw new RuntimeException('Native create failed');}$ids[]=(int)$o->id;
 }$db->commit();}catch(Throwable $error){$db->rollback();throw $error;}
 $posted=array_merge($posted,$ids);$all=NorwayPostingEvidence::report($db,$entity,$id,$user);return array_values(array_filter($all,fn($e)=>in_array($e['id'],$ids,true)));
}
function sbAssessment($input){global $db,$entity,$id,$user;$preview=NorwaySalesTaxBasis::preview($db,$entity,$id,$user,$input);return NorwaySalesTaxBasis::save($db,$entity,$id,$user,$input,$preview['fingerprint']);}

try {
 $rule=array('revision'=>1,'user'=>(int)$user->id,'at'=>'2026-01-01T00:00:00+00:00','source'=>'SYN-BASIS','target'=>'5','start'=>'2026-01-01','end'=>'2026-12-31','direction'=>'sale','id'=>1,'retire'=>false);
 $record=array('version'=>1,'entity'=>$entity,'org'=>'123456785','reference'=>NorwayTaxReference::SOURCE,'events'=>array($rule));
 sbSql('DELETE FROM '.$p.'const WHERE entity='.$entity.' AND name IN ('.$names.')');
 foreach(array('MAIN_INFO_SIREN'=>'123456785','MAIN_INFO_SOCIETE_COUNTRY'=>'173:NO:Norway',NorwayTaxMappings::SETTING=>json_encode($record),NorwayVatRegistration::SETTING=>json_encode(array('version'=>1,'entity'=>$entity,'org'=>'123456785','start'=>'2026-01-01','confirmed_by'=>(int)$user->id,'confirmed_at'=>'2026-01-01T00:00:00+00:00'))) as $name=>$value){sbSql("INSERT INTO ".$p."const (name,value,type,entity) VALUES ('".$name."','".$db->escape($value)."','chaine',".$entity.")");}
 $country=(int)$db->fetch_object(sbSql("SELECT rowid FROM ".$p."c_country WHERE code='NO'"))->rowid;
 sbSql("INSERT INTO ".$p."societe (nom,entity,fk_pays) VALUES ('SYNTHETIC DOCUMENTARY BASIS ONLY',".$entity.','.$country.')');$party=(int)$db->last_insert_id($p.'societe');
 sbSql("INSERT INTO ".$p."facture (ref,entity,type,fk_statut,fk_soc,datef,multicurrency_code,multicurrency_tx,total_ht,total_tva,total_ttc) VALUES ('SYN-BASIS-".bin2hex(random_bytes(5))."',".$entity.",0,0,".$party.",'2026-01-01','NOK',1,100,0,100)");$id=(int)$db->last_insert_id($p.'facture');
 sbSql("INSERT INTO ".$p."facturedet (fk_facture,description,product_type,qty,subprice,tva_tx,vat_src_code,info_bits,localtax1_tx,localtax2_tx,total_ht,total_tva,total_ttc,total_localtax1,total_localtax2) VALUES (".$id.",'SYNTHETIC DOCUMENTATION',1,1,100,0,'SYN-BASIS',0,0,0,100,0,100,0,0)");$line=(int)$db->last_insert_id($p.'facturedet');

 sbSql('UPDATE '.$p.'facture SET fk_statut=1 WHERE rowid='.$id);
 $input=array(array('line'=>$line,'target'=>'5','reason'=>'Synthetic documented domestic classification','reference'=>'SYN-AGREEMENT-001'));
 $legacy=sbCapture();foreach($legacy as $entry){sbAssert($entry['data']['version']===3&&!isset($entry['data']['sales_basis_context']),'Earlier event backfilled');}
 sbAssessment($input);$linked=sbCapture();foreach($linked as $entry){$c=$entry['data']['sales_basis_context'];sbAssert($entry['data']['version']===9&&$c['status']==='Linked'&&$c['assessment']['data']['revision']===1,'Native revision not linked');}
 sbSql("UPDATE ".$p."facturedet SET description='SYNTHETIC CHANGED DOCUMENTATION' WHERE rowid=".$line);
 $changed=sbCapture();foreach($changed as $entry){sbAssert($entry['data']['sales_basis_context']['status']==='Changed','Changed description linked');}
 sbAssessment($input);$reconfirmed=sbCapture();foreach($reconfirmed as $entry){sbAssert($entry['data']['sales_basis_context']['status']==='Linked'&&$entry['data']['sales_basis_context']['assessment']['data']['revision']===2,'Reconfirmed revision missing');}
 $historyInput=NorwayVatHistory::input(1,'2026-01-01','','Synthetic documented registration confirmation',false);$hp=NorwayVatHistory::preview($db,$entity,'123456785',$historyInput);NorwayVatHistory::save($db,$entity,'123456785',$historyInput,NorwayVatHistory::fingerprint($hp),$user);
 $withHistory=sbCapture();foreach($withHistory as $entry){sbAssert($entry['data']['registration_context']['version']===2&&$entry['data']['sales_basis_context']['status']==='Changed','History change hidden');}
 sbAssessment($input);$current=sbCapture();foreach($current as $entry){sbAssert($entry['data']['registration_context']['version']===2&&$entry['data']['sales_basis_context']['status']==='Linked','V9 registration history not decoded');}
 // Interpretation of earlier copies remains independent of current assessment/configuration.
 $all=NorwayPostingEvidence::report($db,$entity,$id,$user);foreach(array_merge($legacy,$linked,$changed,$reconfirmed) as $originalEntry){$found=array_values(array_filter($all,fn($e)=>$e['id']===$originalEntry['id']));sbAssert($found[0]['data']===$originalEntry['data'],'Later revision rewrote historical context');}
 $saved=$linked[0]['data'];$context=$saved['sales_basis_context'];
 foreach(array('identity','common','assessment','source') as $case){$c=$context;$posting=$saved['basis'];if($case==='identity'){$posting['posting']['fk_doc']=$id+1;}if($case==='common'){$posting['invoice']['total_ht']='999.00000000';}if($case==='assessment'){$c['assessment']['data']['choices'][0]['reason']='Changed embedded reason';}if($case==='source'){$c['source']['invoice']['rowid']=$id+1;}
  if($case==='common'){sbAssert(NorwaySalesTaxBasis::context($c['assessment'],$c['source'],$posting,$entity,$id)['status']==='Changed','Common source mismatch linked');}else{sbReject(fn()=>NorwaySalesTaxBasis::context($c['assessment'],$c['source'],$posting,$entity,$id));}
 }
 sbReject(fn()=>NorwaySalesTaxBasis::captureContext($db,$entity,$id,$saved['basis']));
 // Native rollback removes both the posting and its documentary observation.
 $db->begin();$o=new BookKeeping($db);$o->entity=$entity;$o->doc_type='customer_invoice';$o->doc_ref=$saved['basis']['posting']['doc_ref'];$o->fk_doc=$id;$o->numero_compte='1500';$o->doc_date=strtotime('2026-01-01');$o->debit=1;$o->credit=0;$o->montant=1;$o->sens='D';$o->code_journal='SYN-BASIS';$o->journal_label='Synthetic';$o->piece_num=987750;$o->ref='SYN-BASIS';$o->label_compte='Synthetic';$o->label_operation='Synthetic';sbAssert($o->createStd($user)>0,'Rollback create failed');$rolled=(int)$o->id;$db->rollback();
 sbAssert((int)$db->fetch_object(sbSql("SELECT COUNT(*) n FROM ".$p."norway_tax_evidence WHERE document_type='bookkeeping_event' AND fk_document=".$rolled))->n===0,'Observation survived rollback');
 // The user sees historical status and revisions at the invoice, in both languages.
 $cookie=tempnam(sys_get_temp_dir(),'norway-sales-posting-http-');$curl=curl_init();curl_setopt_array($curl,array(CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_TIMEOUT=>20));
 $page=static function($path,$post=null) use($curl){curl_setopt($curl,CURLOPT_URL,'http://localhost'.$path);curl_setopt($curl,CURLOPT_POST,$post!==null);if($post!==null){curl_setopt($curl,CURLOPT_POSTFIELDS,http_build_query($post));}$body=curl_exec($curl);sbAssert($body!==false&&curl_getinfo($curl,CURLINFO_HTTP_CODE)<500,'HTTP failure');return $body;};
 try{
  $body=$page('/custom/norway/admin/salestaxbasis.php?id='.$id);sbAssert(strpos($body,'norway-sales-posting')===false,'Unauthenticated retained basis exposed');sbAssert(preg_match('/name="token"[^>]*value="([^"]+)"/',$body,$m)===1,'Login token missing');
  $page('/index.php',array('token'=>html_entity_decode($m[1],ENT_QUOTES,'UTF-8'),'username'=>'testadmin','password'=>getenv('DOLI_ADMIN_PASSWORD'),'actionlogin'=>'login','loginfunction'=>'loginfunction'));
  foreach(array('nb_NO','en_US') as $lang){
   $body=$page('/custom/norway/admin/salestaxbasis.php?id='.$id.'&lang='.$lang);sbAssert(substr_count($body,'class="norway-sales-posting"')===count($posted)&&strpos($body,'SYN-AGREEMENT-001')!==false&&strpos($body,'NorwaySalesPosting')===false,'Historical documentary UI missing');
   $body=$page('/custom/norway/admin/invoice.php?id='.$id.'&ledger=1&lang='.$lang);sbAssert(strpos($body,'/norway/admin/salestaxbasis.php?id='.$id)!==false&&strpos($body,'NorwaySalesPosting')===false,'Invoice documentary context unavailable');
  }
 }finally{curl_close($curl);unlink($cookie);}
 // A rehashed envelope cannot silently alter the derived link status.
 $entry=$linked[0];$raw=$db->fetch_object(sbSql("SELECT payload,payload_sha256 FROM ".$p."norway_tax_evidence WHERE entity=".$entity." AND document_type='bookkeeping_event' AND fk_document=".$entry['id']));
 $tampered=$entry['data'];$tampered['sales_basis_context']['status']='Changed';$json=json_encode($tampered,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
 sbSql("UPDATE ".$p."norway_tax_evidence SET payload='".$db->escape($json)."',payload_sha256='".hash('sha256',$json)."' WHERE entity=".$entity." AND document_type='bookkeeping_event' AND fk_document=".$entry['id']);
 sbReject(fn()=>NorwayPostingEvidence::report($db,$entity,$id,$user));
 sbSql("UPDATE ".$p."norway_tax_evidence SET payload='".$db->escape($raw->payload)."',payload_sha256='".$raw->payload_sha256."' WHERE entity=".$entity." AND document_type='bookkeeping_event' AND fk_document=".$entry['id']);
 // Corrupt documentary history cannot silently become an absent assessment.
 sbSql("UPDATE ".$p."norway_tax_evidence SET payload_sha256='".str_repeat('0',64)."' WHERE entity=".$entity." AND document_type='sales_tax_basis' AND fk_document=".$id.' AND revision=3');
 sbReject(fn()=>sbCapture());sbAssert((int)$db->fetch_object(sbSql('SELECT COUNT(*) n FROM '.$p.'accounting_bookkeeping WHERE entity='.$entity." AND doc_type='customer_invoice' AND fk_doc=".$id))->n===count($posted),'Failed capture persisted posting');
} finally {
 if($posted){sbSql("DELETE FROM ".$p."norway_tax_evidence WHERE entity=".$entity." AND document_type='bookkeeping_event' AND fk_document IN (".implode(',',$posted).")");sbSql('DELETE FROM '.$p.'accounting_bookkeeping WHERE entity='.$entity.' AND rowid IN ('.implode(',',$posted).')');}
 if($id){sbSql("DELETE FROM ".$p."norway_tax_evidence WHERE entity=".$entity." AND document_type='sales_tax_basis' AND fk_document=".$id);sbSql('DELETE FROM '.$p.'facturedet WHERE fk_facture='.$id);sbSql('DELETE FROM '.$p.'facture WHERE rowid='.$id);}
 if($party){sbSql('DELETE FROM '.$p.'societe WHERE rowid='.$party);}
 sbSql('DELETE FROM '.$p.'const WHERE entity='.$entity.' AND name IN ('.$names.')');foreach($original as $row){unset($row['rowid']);$cols=array_keys($row);$values=array_map(fn($v)=>$v===null?'NULL':"'".$db->escape((string)$v)."'",array_values($row));sbSql('INSERT INTO '.$p.'const (`'.implode('`,`',$cols).'`) VALUES ('.implode(',',$values).')');}
}
print "PASS native sales documentary v9 linked/changed revisions, registration v1/v2, immutable earlier copies, identity/source/integrity guards, rollback and corrupt-history rejection\n";
