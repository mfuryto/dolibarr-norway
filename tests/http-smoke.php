<?php
// SPDX-License-Identifier: GPL-3.0-or-later
if(PHP_SAPI!=='cli'||getenv('NORWAY_SYNTHETIC_TEST')!=='1'||getenv('DOLI_DB_NAME')!=='norway_test'){exit(1);}
require '/var/www/html/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
$cookie=tempnam(sys_get_temp_dir(),'norway-http-');$curl=curl_init();curl_setopt_array($curl,array(CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_TIMEOUT=>25));
function request($path,$post=null){global $curl;curl_setopt($curl,CURLOPT_URL,'http://localhost'.$path);curl_setopt($curl,CURLOPT_POST,$post!==null);if($post!==null){curl_setopt($curl,CURLOPT_POSTFIELDS,http_build_query($post));}$body=curl_exec($curl);if($body===false||curl_getinfo($curl,CURLINFO_HTTP_CODE)>=500){throw new RuntimeException('HTTP failure');}return $body;}
function httpAssert($ok,$m){if(!$ok){throw new RuntimeException($m);}}
function overviewSnapshot(){global $db;$hash=hash_init('sha256');foreach(array('facture'=>'rowid','facture_fourn'=>'rowid','accounting_bookkeeping'=>'rowid','norway_manual_vat'=>'entity,fk_bookkeeping','norway_tax_evidence'=>'rowid') as $table=>$order){$r=$db->query('SELECT * FROM '.$db->prefix().$table.' ORDER BY '.$order);httpAssert((bool)$r,'Overview snapshot read failed');while($row=$db->fetch_object($r)){hash_update($hash,serialize($row));}}return hash_final($hash);}
try{
 $snapshot=overviewSnapshot();
 $body=request('/custom/norway/admin/wizard.php');httpAssert(strpos($body,'norway-setup-overview')===false,'Anonymous overview exposed');
 $body=request('/custom/norway/admin/setup.php');httpAssert(strpos($body,'norway-page')===false,'Anonymous setup exposed');httpAssert(preg_match('/name="token"[^>]*value="([^"]+)"/',$body,$m)===1,'Login token missing');
 request('/index.php',array('token'=>html_entity_decode($m[1],ENT_QUOTES,'UTF-8'),'actionlogin'=>'login','loginfunction'=>'loginfunction','username'=>'testadmin','password'=>getenv('DOLI_ADMIN_PASSWORD')));
 foreach(array('nb_NO','en_US') as $lang){
  foreach(array('setup','accounting','wizard','numbering','pricing') as $page){$body=request('/custom/norway/admin/'.$page.'.php?lang='.$lang);httpAssert(strpos($body,'norway-page')!==false,'Setup page unavailable');}
  // Old step bookmarks display the same complete overview; no persisted approvals.
  foreach(array('','&step=7') as $bookmark){$body=request('/custom/norway/admin/wizard.php?lang='.$lang.$bookmark);
   httpAssert(curl_getinfo($curl,CURLINFO_HTTP_CODE)===200 && substr_count($body,'data-setup-task=')===18,'Overview incomplete');
   $overview=substr($body,strpos($body,'class="norway-page norway-wizard"'));
   httpAssert(strpos($overview,'<form')===false,'Overview still contains a form');
   httpAssert(strpos($overview,'name="workstate"')===false && preg_match('/href="[^"]*[?&]step=/',$overview)!==1,'Overview still uses step navigation');
   httpAssert(preg_match('/data-setup-task="Saft"(?:(?!<\/tr>).)*data-setup-status="Review"/s',$body)===1,'SAF-T must link to qualified native export and explicit mapping');
   foreach(array('Ehf','Tax') as $task){httpAssert(preg_match('/data-setup-task="'.$task.'"(?:(?!<\/tr>).)*data-setup-status="Unavailable"/s',$body)===1,'Unavailable feature presented as ready: '.$task);}
   httpAssert(preg_match('/data-setup-task="Foreign".*?data-setup-status="Partial"/s',$body)===1,'Manual fallback presented as normal support');
   foreach(array('/admin/company.php','/accountancy/admin/defaultaccounts.php','/accountancy/admin/journals_list.php','/admin/dict.php?id=10','/compta/bank/list.php','/accountancy/admin/fiscalyear.php','/accountancy/admin/closure.php') as $core){httpAssert(strpos($body,$core)!==false,'Core setup link missing: '.$core);}
   foreach(array('defaults','standardaccounts','vataccounts','bankdefaults','journals','invoiceperiod','audit','salestaxbasis','taxmappings','registration','productaccounts') as $removed){httpAssert(strpos($body,'/norway/admin/'.$removed.'.php')===false,'Duplicate or retired wizard task remains: '.$removed);}
   httpAssert(strpos($body,'NorwaySetupHelp')===false && strpos($body,'NorwaySetupStatus')===false,'Overview translation missing');
  }
  if($lang==='nb_NO'){
   preg_match_all('/href="(\/[^"#]+)"/',$overview,$links);foreach(array_unique($links[1]) as $path){request(html_entity_decode($path,ENT_QUOTES,'UTF-8'));httpAssert(curl_getinfo($curl,CURLINFO_HTTP_CODE)===200,'Overview target unavailable: '.$path);}
  }
  $body=request('/accountancy/index.php?mainmenu=accountancy&leftmenu=accountancy_admin&lang='.$lang);httpAssert(strpos($body,'/norway/admin/accounting.php')!==false,'Norwegian settings missing');
 }
 // A status follows the actual settings without an independent completion state.
 $entity=(int)$conf->entity;$where="entity=".$entity." AND name IN ('ACCOUNTING_ACCOUNT_CUSTOMER','ACCOUNTING_ACCOUNT_SUPPLIER')";
 $stored=array();$q=$db->query('SELECT * FROM '.$db->prefix().'const WHERE '.$where);httpAssert((bool)$q,'Default snapshot');while($row=$db->fetch_object($q)){$stored[]=(array)$row;}
 try{
  foreach(array('Missing'=>array('',''),'Set'=>array('1500','2400')) as $expected=>$values){
   foreach(array('ACCOUNTING_ACCOUNT_CUSTOMER','ACCOUNTING_ACCOUNT_SUPPLIER') as $i=>$name){httpAssert(dolibarr_set_const($db,$name,$values[$i],'chaine',0,'',$entity)>0,'Synthetic default setting');}
   $body=request('/custom/norway/admin/wizard.php?lang=nb_NO');httpAssert(preg_match('/data-setup-task="Accounts"(?:(?!<\/tr>).)*data-setup-status="'.$expected.'"/s',$body)===1,'Overview did not follow changed settings');
  }
 }finally{
  httpAssert((bool)$db->query('DELETE FROM '.$db->prefix().'const WHERE '.$where),'Default restore');
  foreach($stored as $row){$values=array_map(fn($value)=>$value===null?'NULL':"'".$db->escape((string)$value)."'",array_values($row));httpAssert((bool)$db->query('INSERT INTO '.$db->prefix().'const ('.implode(',',array_keys($row)).') VALUES ('.implode(',',$values).')'),'Default restore row');}
  $conf->setValues($db);
 }
 foreach(array('defaults','standardaccounts','journals','vataccounts','bankdefaults','audit','periods','invoice','invoiceperiod','salestaxbasis','purchasededuction','productaccounts','documents','registration','taxmappings','vat','chart') as $retired){request('/custom/norway/admin/'.$retired.'.php');httpAssert(curl_getinfo($curl,CURLINFO_HTTP_CODE)===404,'Retired endpoint still callable');}
 $body=request('/index.php?lang=nb_NO');httpAssert(strpos($body,'bg-infobox-bank_account')!==false,'Bank dashboard missing');httpAssert(stripos(html_entity_decode($body,ENT_QUOTES,'UTF-8'),'sjekkinnskudd')===false&&strpos($body,'/compta/paiement/cheque/')===false,'Cheque dashboard remains');
 request('/compta/paiement/cheque/list.php');httpAssert(curl_getinfo($curl,CURLINFO_HTTP_CODE)===403,'Cheque endpoint accessible');
 httpAssert(overviewSnapshot()===$snapshot,'Setup overview or linked GET changed financial data');
 print "PASS login, read-only bilingual 18-task overview, core links, explicit gaps, legacy bookmarks; simple setup, wizard links without approval state, retired endpoints and cheque restrictions\n";
}finally{curl_close($curl);unlink($cookie);}
