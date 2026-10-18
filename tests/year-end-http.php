<?php
// SPDX-License-Identifier: GPL-3.0-or-later
if(PHP_SAPI!=='cli'||getenv('NORWAY_SYNTHETIC_TEST')!=='1'||getenv('DOLI_DB_NAME')!=='norway_test'){exit(1);}
require '/var/www/html/master.inc.php';require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
function yhAssert($ok,$message){if(!$ok){throw new RuntimeException($message);}}
function yhRows($sql){global $db;$r=$db->query($sql);yhAssert($r!==false,$db->lasterror());$out=array();if($r===true){return $out;}while($row=$db->fetch_object($r)){$out[]=$row;}return $out;}
function yhHttp($path,$post=null){global $curl;curl_setopt($curl,CURLOPT_URL,'http://localhost'.$path);curl_setopt($curl,CURLOPT_POST,$post!==null);if($post!==null){curl_setopt($curl,CURLOPT_POSTFIELDS,http_build_query($post));}$body=curl_exec($curl);yhAssert($body!==false && curl_getinfo($curl,CURLINFO_HTTP_CODE)<400,'Native closing HTTP');return $body;}
function yhToken($body){yhAssert((preg_match('/name="token"[^>]*value="([^"]+)"/',$body,$m)===1 || preg_match('/name="anti-csrf-newtoken" content="([^"]+)"/',$body,$m)===1),'Native token');return html_entity_decode($m[1]);}
yhAssert($user->fetch('','testadmin')>0,'Admin');$p=$db->prefix();$entity=(int)$conf->entity;$years=array();$journal=0;$saved=array();$run=bin2hex(random_bytes(3));
$cookie=tempnam(sys_get_temp_dir(),'norway-year-');$curl=curl_init();curl_setopt_array($curl,array(CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_TIMEOUT=>30));
try{
 foreach(array('ACCOUNTING_CLOSURE_ACCOUNTING_GROUPS_USED_FOR_BALANCE_SHEET_ACCOUNT'=>'1,2','ACCOUNTING_CLOSURE_ACCOUNTING_GROUPS_USED_FOR_INCOME_STATEMENT'=>'3,4,5,6,7,8','ACCOUNTING_CLOSURE_DEFAULT_JOURNAL'=>'') as $name=>$value){$saved[$name]=yhRows("SELECT * FROM {$p}const WHERE entity=$entity AND name='$name'");if($value!==''){dolibarr_set_const($db,$name,$value,'chaine',0,'',$entity);}}
 yhRows("INSERT INTO {$p}accounting_journal (entity,code,label,nature,active) VALUES ($entity,'YH$run','Synthetic HTTP close',9,1)");$journal=(int)$db->last_insert_id($p.'accounting_journal');dolibarr_set_const($db,'ACCOUNTING_CLOSURE_DEFAULT_JOURNAL',(string)$journal,'chaine',0,'',$entity);
 foreach(array(2068,2069,2071) as $year){yhRows("INSERT INTO {$p}accounting_fiscalyear (entity,label,date_start,date_end,statut,datec) VALUES ($entity,'SYNTHETIC HTTP $run $year','$year-01-01','$year-12-31',0,'2026-01-01')");$years[$year]=(int)$db->last_insert_id($p.'accounting_fiscalyear');}
 $body=yhHttp('/index.php');yhHttp('/index.php',array('token'=>yhToken($body),'actionlogin'=>'login','loginfunction'=>'loginfunction','username'=>'testadmin','password'=>getenv('DOLI_ADMIN_PASSWORD')));
 $path='/accountancy/closure/index.php?fiscal_period_id='.$years[2068];
 foreach(array('bad-year','missing-aux','busy','success','repeat') as $case){$page=yhHttp('/index.php');$body=yhHttp($path.'&action=step_2&token='.rawurlencode(yhToken($page)));$post=array('token'=>yhToken($body),'action'=>'confirm_step_2','confirm'=>'yes','fiscal_period_id'=>$years[2068],'new_fiscal_period_id'=>$case==='bad-year'?$years[2071]:$years[2069],'generate_bookkeeping_records'=>1,'separate_auxiliary_account'=>$case==='missing-aux'?0:1);
  if($case==='busy'){yhRows("SELECT GET_LOCK('norway-year-end-$entity',0)");}
  try{$body=yhHttp($path,$post);}finally{if($case==='busy'){yhRows("SELECT RELEASE_LOCK('norway-year-end-$entity')");}}
  $status=(int)yhRows("SELECT statut FROM {$p}accounting_fiscalyear WHERE rowid=".$years[2068])[0]->statut;
  yhAssert($status===(in_array($case,array('success','repeat'),true)?1:0),'Unsafe native close mutated status: '.$case);
  if($case!=='success'){$needle=$case==='busy'?'Another year close':($case==='missing-aux'?'Select separate auxiliary':'The year cannot be closed');yhAssert(strpos($body,$needle)!==false,'Hook error missing '.$case);}
 }
 print "PASS native closing HTTP: registered hook, invalid year, auxiliary retention, simultaneous close lock, successful close and replay blocked\n";
}finally{$db->rollback();yhRows("SELECT RELEASE_LOCK('norway-year-end-$entity')");if($years){yhRows("DELETE FROM {$p}accounting_fiscalyear WHERE rowid IN (".implode(',',$years).')');}if($journal){yhRows("DELETE FROM {$p}accounting_journal WHERE rowid=$journal");}foreach($saved as $name=>$rows){yhRows("DELETE FROM {$p}const WHERE entity=$entity AND name='$name'");foreach($rows as $row){$a=(array)$row;$v=array_map(fn($x)=>$x===null?'NULL':"'".$db->escape((string)$x)."'",array_values($a));yhRows("INSERT INTO {$p}const (".implode(',',array_keys($a)).') VALUES ('.implode(',',$v).')');}}curl_close($curl);unlink($cookie);}
