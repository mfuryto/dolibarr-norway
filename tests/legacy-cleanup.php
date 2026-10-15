<?php
// SPDX-License-Identifier: GPL-3.0-or-later
if(PHP_SAPI!=='cli'||getenv('NORWAY_SYNTHETIC_TEST')!=='1'||getenv('DOLI_DB_NAME')!=='norway_test'){exit(1);}
require '/var/www/html/master.inc.php';require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';require_once DOL_DOCUMENT_ROOT.'/accountancy/class/bookkeeping.class.php';require_once DOL_DOCUMENT_ROOT.'/custom/norway/class/NorwayLegacyArchive.php';
$user->fetch('','testadmin');$user->loadRights('',1);$p=$db->prefix();$entity=(int)$conf->entity;$legacy=json_decode(file_get_contents(__DIR__.'/legacy-evidence.json'),true,512,JSON_THROW_ON_ERROR);
function lcAssert($ok,$m){if(!$ok){throw new RuntimeException($m);}}
function lcSql($q){global $db;$r=$db->query($q);lcAssert((bool)$r,$db->lasterror());return $r;}
function lcReject($f){try{$f();}catch(InvalidArgumentException|RuntimeException $e){return;}throw new LogicException('Unexpected permission');}
lcAssert(($legacy['synthetic'] ?? null)===true,'Non-synthetic archive fixture');$inserted=array();$posted=array();$marker=0;
try{
 foreach($legacy['rows'] as $row){$row['entity']=$entity;$values=array_map(fn($v)=>"'".$db->escape((string)$v)."'",array_values($row));lcSql('INSERT INTO '.$p.'norway_tax_evidence ('.implode(',',array_keys($row)).') VALUES ('.implode(',',$values).')');$inserted[]=(int)$db->last_insert_id($p.'norway_tax_evidence');}
 $before=array('rows'=>array());foreach($inserted as $rowid){$retained=NorwayLegacyArchive::read($db,$entity,$user,0,$rowid);lcAssert(count($retained['rows'])===1,'Legacy record unreadable');$before['rows'][]=$retained['rows'][0];}foreach($before['rows'] as $r){lcAssert($r['intact'],'Historical payload/hash changed');}
 $reader=clone $user;$reader->admin=0;lcReject(fn()=>NorwayLegacyArchive::read($db,$entity,$reader));$external=clone $user;$external->socid=1;lcReject(fn()=>NorwayLegacyArchive::read($db,$entity,$external));lcReject(fn()=>NorwayLegacyArchive::read($db,$entity+1,$user));
 $foreign=$entity+12345;lcSql("INSERT INTO ".$p."norway_tax_evidence (entity,document_type,fk_document,revision,captured_at,fk_user,mapping_revision,payload,payload_sha256) VALUES (".$foreign.",'synthetic_archive',1,1,UTC_TIMESTAMP(),".(int)$user->id.",0,'{}','".hash('sha256','{}')."')");$foreignRow=(int)$db->last_insert_id($p.'norway_tax_evidence');$inserted[]=$foreignRow;lcAssert(!NorwayLegacyArchive::read($db,$entity,$user,0,$foreignRow)['rows'],'Foreign record exposed');
 // Ordinary native postings continue even with damaged unused legacy reasons, and create no new copies.
 lcSql("UPDATE ".$p."norway_tax_evidence SET payload_sha256=REPEAT('0',64) WHERE rowid=".$inserted[0]);
 $make=static function($doc,$piece) use($db,$entity){$o=new BookKeeping($db);$o->entity=$entity;$o->doc_date=strtotime('2026-01-01');$o->doc_type='supplier_invoice';$o->doc_ref='SYN-LEGACY';$o->fk_doc=$doc;$o->fk_docdet=0;$o->numero_compte='4000';$o->label_compte='Synthetic';$o->label_operation='Synthetic legacy transition';$o->debit=10;$o->credit=0;$o->montant=10;$o->sens='D';$o->code_journal='SYNLEG';$o->journal_label='Synthetic';$o->piece_num=$piece;$o->ref='SYNLEG';return $o;};
 $count=(int)$db->fetch_object(lcSql('SELECT COUNT(*) n FROM '.$p.'norway_tax_evidence'))->n;
 $normal=$make(987751,987751);lcAssert($normal->createStd($user)>0,'Normal native posting blocked by unused history');$posted[]=(int)$normal->id;
 lcAssert((int)$db->fetch_object(lcSql('SELECT COUNT(*) n FROM '.$p.'norway_tax_evidence'))->n===$count,'New copies still created');
 $payload='{"synthetic":true,"purpose":"retired-purchase-protection"}';lcSql("INSERT INTO ".$p."norway_tax_evidence (entity,document_type,fk_document,revision,captured_at,fk_user,mapping_revision,payload,payload_sha256) VALUES (".$entity.",'supplier_posting',987751,1,UTC_TIMESTAMP(),".(int)$user->id.",0,'".$db->escape($payload)."','".hash('sha256',$payload)."')");$marker=(int)$db->last_insert_id($p.'norway_tax_evidence');$inserted[]=$marker;
 $duplicate=$make(987751,987752);lcAssert($duplicate->createStd($user)<0,'Legacy invoice retransfer permitted');
 $collision=$make(987753,987751);lcAssert($collision->createStd($user)<0,'Protected voucher mixing permitted');
 $manual=$make(0,987754);$manual->doc_type='manual';$manual->label_operation='Synthetic correction referencing original SYN-LEGACY / 987751';lcAssert($manual->createStd($user)>0,'Separate core correction blocked');$posted[]=(int)$manual->id;
 // Restore old bytes and prove the archive has not been rewritten by posting or correction.
 lcSql("UPDATE ".$p."norway_tax_evidence SET payload_sha256='".$legacy['rows'][0]['payload_sha256']."' WHERE rowid=".$inserted[0]);
 foreach($before['rows'] as $row){$after=NorwayLegacyArchive::read($db,$entity,$user,0,(int)$row['rowid']);lcAssert($after['rows'][0]===$row,'Legacy metadata or payload changed');}
 $cookie=tempnam(sys_get_temp_dir(),'norway-archive-');$curl=curl_init();curl_setopt_array($curl,array(CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_TIMEOUT=>20));
 $page=static function($path,$post=null) use($curl){curl_setopt($curl,CURLOPT_URL,'http://localhost'.$path);curl_setopt($curl,CURLOPT_POST,$post!==null);if($post!==null){curl_setopt($curl,CURLOPT_POSTFIELDS,http_build_query($post));}$body=curl_exec($curl);lcAssert($body!==false,'Archive HTTP');return $body;};
 try{$body=$page('/custom/norway/admin/evidence.php');lcAssert(strpos($body,'SYN-AGREEMENT')===false,'Anonymous archive exposed');lcAssert(preg_match('/name="token"[^>]*value="([^"]+)"/',$body,$m)===1,'Login token');$page('/index.php',array('token'=>html_entity_decode($m[1],ENT_QUOTES,'UTF-8'),'username'=>'testadmin','password'=>getenv('DOLI_ADMIN_PASSWORD'),'actionlogin'=>'login','loginfunction'=>'loginfunction'));
  foreach(array('nb_NO','en_US') as $lang){$body=$page('/custom/norway/admin/evidence.php?lang='.$lang);lcAssert(strpos($body,'bookkeeping_event')!==false&&strpos($body,'NorwayArchive')===false&&strpos($body,'method="post"')===false,'Read archive unavailable or writable');}
  $bytes=$page('/custom/norway/admin/evidence.php?download=1&record='.$inserted[0]);lcAssert($bytes===$legacy['rows'][0]['payload'],'Download changed original bytes');
  $page('/custom/norway/admin/evidence.php?download=1&record='.$foreignRow);lcAssert(curl_getinfo($curl,CURLINFO_HTTP_CODE)===404,'Foreign archive download');
  $body=$page('/admin/company.php');lcAssert(preg_match('/name="token"[^>]*value="([^"]+)"/',$body,$m)===1,'Core form token');
  $page('/custom/norway/admin/evidence.php',array('token'=>html_entity_decode($m[1],ENT_QUOTES,'UTF-8'),'confirmed'=>'1'));lcAssert(curl_getinfo($curl,CURLINFO_HTTP_CODE)===405,'Retired write endpoint still active');
 }finally{curl_close($curl);unlink($cookie);}
}finally{if($inserted){lcSql('DELETE FROM '.$p.'norway_tax_evidence WHERE rowid IN ('.implode(',',$inserted).')');}if($posted){lcSql('DELETE FROM '.$p.'accounting_bookkeeping WHERE entity='.$entity.' AND rowid IN ('.implode(',',$posted).')');}}
print "PASS retired writers, native posting/correction, immutable legacy archive, entity/admin guards, original download, POST rejection and previous-voucher protection\n";
