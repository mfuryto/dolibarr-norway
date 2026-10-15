<?php
// SPDX-License-Identifier: GPL-3.0-or-later
if(PHP_SAPI!=='cli'||getenv('NORWAY_SYNTHETIC_TEST')!=='1'||getenv('DOLI_DB_NAME')!=='norway_test'){exit(1);}
$cookie=tempnam(sys_get_temp_dir(),'norway-http-');$curl=curl_init();curl_setopt_array($curl,array(CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_TIMEOUT=>25));
function request($path,$post=null){global $curl;curl_setopt($curl,CURLOPT_URL,'http://localhost'.$path);curl_setopt($curl,CURLOPT_POST,$post!==null);if($post!==null){curl_setopt($curl,CURLOPT_POSTFIELDS,http_build_query($post));}$body=curl_exec($curl);if($body===false||curl_getinfo($curl,CURLINFO_HTTP_CODE)>=500){throw new RuntimeException('HTTP failure');}return $body;}
function httpAssert($ok,$m){if(!$ok){throw new RuntimeException($m);}}
try{
 $body=request('/custom/norway/admin/setup.php');httpAssert(strpos($body,'norway-page')===false,'Anonymous setup exposed');httpAssert(preg_match('/name="token"[^>]*value="([^"]+)"/',$body,$m)===1,'Login token missing');
 request('/index.php',array('token'=>html_entity_decode($m[1],ENT_QUOTES,'UTF-8'),'actionlogin'=>'login','loginfunction'=>'loginfunction','username'=>'testadmin','password'=>getenv('DOLI_ADMIN_PASSWORD')));
 foreach(array('nb_NO','en_US') as $lang){
  foreach(array('setup','accounting','wizard','numbering','pricing') as $page){$body=request('/custom/norway/admin/'.$page.'.php?lang='.$lang);httpAssert(strpos($body,'norway-page')!==false,'Setup page unavailable');}
  for($step=1;$step<=11;$step++){$body=request('/custom/norway/admin/wizard.php?step='.$step.'&lang='.$lang);httpAssert(strpos($body,'class="norway-page norway-wizard"')!==false&&strpos($body,'name="workstate"')===false,'Wizard state/control remains');foreach(array('invoiceperiod','audit','salestaxbasis','taxmappings','registration','productaccounts') as $removed){httpAssert(strpos($body,'/norway/admin/'.$removed.'.php')===false,'Retired wizard link remains');}}
  $body=request('/accountancy/index.php?mainmenu=accountancy&leftmenu=accountancy_admin&lang='.$lang);httpAssert(strpos($body,'/norway/admin/accounting.php')!==false,'Norwegian settings missing');
 }
 foreach(array('audit','periods','invoice','invoiceperiod','salestaxbasis','purchasededuction','productaccounts','documents','registration','taxmappings','vat','chart') as $retired){request('/custom/norway/admin/'.$retired.'.php');httpAssert(curl_getinfo($curl,CURLINFO_HTTP_CODE)===404,'Retired endpoint still callable');}
 $body=request('/index.php?lang=nb_NO');httpAssert(strpos($body,'bg-infobox-bank_account')!==false,'Bank dashboard missing');httpAssert(stripos(html_entity_decode($body,ENT_QUOTES,'UTF-8'),'sjekkinnskudd')===false&&strpos($body,'/compta/paiement/cheque/')===false,'Cheque dashboard remains');
 request('/compta/paiement/cheque/list.php');httpAssert(curl_getinfo($curl,CURLINFO_HTTP_CODE)===403,'Cheque endpoint accessible');
 print "PASS login, bilingual simple setup, wizard links without approval state, retired endpoints and cheque restrictions\n";
}finally{curl_close($curl);unlink($cookie);}
