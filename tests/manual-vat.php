<?php
// SPDX-License-Identifier: GPL-3.0-or-later
if(PHP_SAPI!=='cli'||getenv('NORWAY_SYNTHETIC_TEST')!=='1'||getenv('DOLI_DB_NAME')!=='norway_test'){exit(1);}
require '/var/www/html/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/accountancy/class/bookkeeping.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/norway/core/modules/modNorway.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/norway/class/NorwayManualVat.php';
function mvAssert($ok,$message){if(!$ok){throw new RuntimeException($message);}}
function mvSql($sql){global $db;$r=$db->query($sql);mvAssert((bool)$r,'Synthetic manual VAT SQL: '.$db->lasterror());return $r;}
function mvBlocked($f,$message){$blocked=false;try{$f();}catch(RuntimeException $e){$blocked=true;}mvAssert($blocked,$message);}
mvAssert($user->fetch('','testadmin')>0,'Admin missing');$module=new modNorway($db);mvAssert($module->init()>0,$module->error);$conf->setValues($db);$user->loadRights('',1);
$p=$db->prefix();$entity=(int)$conf->entity;$run=bin2hex(random_bytes(4));$ids=array();$pieces=array();$oldGlobals=clone $conf->global;
$codes="'1','11','13','3','31','33'";$oldRates=array();$r=mvSql('SELECT * FROM '.$p.'c_tva WHERE entity='.$entity.' AND code IN ('.$codes.')');while($row=$db->fetch_object($r)){$oldRates[]=(array)$row;}
$cookie=tempnam(sys_get_temp_dir(),'norway-manual-vat-');$curl=curl_init();curl_setopt_array($curl,array(CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_TIMEOUT=>30));
function mvHttp($path,$post=null){global $curl;curl_setopt($curl,CURLOPT_URL,'http://localhost'.$path);curl_setopt($curl,CURLOPT_POST,$post!==null);if($post!==null){curl_setopt($curl,CURLOPT_POSTFIELDS,http_build_query($post));}$b=curl_exec($curl);mvAssert($b!==false,'HTTP failed');return $b;}
function mvToken($body){mvAssert(preg_match('/name="token"[^>]*value="([^"]+)"/',$body,$m)===1,'CSRF token missing');return html_entity_decode($m[1]);}
function mvLine($piece,$id){global $db,$entity,$user;foreach(NorwayManualVat::lines($db,$entity,$piece,$user) as $row){if((int)$row->rowid===$id){return $row;}}throw new RuntimeException('Manual line missing');}
function mvSave($piece,$id,$code,$base,$token=null,$actor=null){global $db,$entity,$user;NorwayManualVat::save($db,$entity,$piece,$id,$code,$base,$token??NorwayManualVat::editToken(mvLine($piece,$id)),$actor??$user);}
try {
 $conf->global->ACCOUNTANCY_FISCAL_PERIOD_MODE='blockedonclosed';
 mvSql('DELETE FROM '.$p.'c_tva WHERE entity='.$entity.' AND code IN ('.$codes.')');
 foreach(NorwayVatRates::codes() as $spec){$account=($spec['type']===1?'270':'271').array(25=>'0',15=>'1',12=>'3')[$spec['rate']];
  mvSql("INSERT INTO ".$p."c_tva (entity,fk_pays,code,type_vat,taux,recuperableonly,localtax1,localtax2,active,accountancy_code_sell,accountancy_code_buy) VALUES (".$entity.",173,'".$spec['code']."',".$spec['type'].",".$spec['rate'].",0,0,0,1,'".($spec['type']===1?$account:'')."','".($spec['type']===2?$account:'')."')");
 }
 // Native manual entry creation and its real balancing line; both signs, all six codes.
 foreach(NorwayManualVat::choices($db,$entity) as $code=>$spec){foreach(array(1,-1) as $sign){
  $piece=(new BookKeeping($db))->getNextNumMvt();$tax=$spec['rate']*$sign*($spec['type']===2 && $sign<0?0.5:1);
  foreach(array($spec['account']=>($spec['type']===2?$tax:-$tax),'1920'=>($spec['type']===2?-$tax:$tax)) as $account=>$amount){
   $o=new BookKeeping($db);$o->entity=$entity;$o->doc_date=dol_mktime(12,0,0,2,10,2026);$o->doc_type='';$o->doc_ref='SYNTHETIC MANUAL VAT '.$run;$o->fk_doc=0;$o->fk_docdet=0;
   $o->numero_compte=$account;$o->label_compte='Synthetic manual';$o->label_operation='Synthetic manual';$o->debit=max($amount,0);$o->credit=max(-$amount,0);$o->montant=abs($amount);$o->sens=$amount>0?'D':'C';$o->code_journal='SYNMV';$o->journal_label='Synthetic';$o->piece_num=$piece;
   mvAssert($o->createStd($user)>0,'Native entry: '.$o->error.' '.implode(';',$o->errors));$ids[]=(int)$o->id;$piece=(int)$o->piece_num;
   if((string)$account===$spec['account']){$id=(int)$o->id;}
  }
  $pieces[]=$piece;
  // Test-local open-period mode; the HTTP session uses the existing synthetic active year.
  $conf->global->ACCOUNTANCY_FISCAL_PERIOD_MODE='blockedonclosed';
  $line=mvLine($piece,$id);$hash=NorwayManualVat::sourceHash($line);$token=NorwayManualVat::editToken($line);
  $base=$spec['type']===1?(string)(100*$sign):'';
  mvSave($piece,$id,(string)$code,$base);
  $line=mvLine($piece,$id);mvAssert(NorwayManualVat::current($line) && $line->tax_code===(string)$code && NorwayManualVat::sourceHash($line)===$hash,'Tax fields changed native ledger or failed');
  mvBlocked(fn()=>mvSave($piece,$id,(string)$code,$base,$token),'Stale metadata token accepted');
  mvBlocked(fn()=>mvSave($piece,$id,'81',$base),'Unsupported import code accepted');
  mvBlocked(fn()=>mvSave($piece,$id,$spec['type']===1?'1':'3',$base),'Wrong direction/account accepted');
  mvBlocked(fn()=>mvSave($piece,$id,(string)$code,'999'),'Wrong base accepted');
  $outsider=new class {public $id=987654;public $socid=1;public function hasRight(...$args){return true;}};
  mvBlocked(fn()=>mvSave($piece,$id,(string)$code,$base,null,$outsider),'External user accepted');
  $reader=new class {public $id=987654;public $socid=0;public function hasRight(...$args){return end($args)==='lire';}};
  mvBlocked(fn()=>mvSave($piece,$id,(string)$code,$base,null,$reader),'Read-only user wrote metadata');
  $writer=new class {public $id=987654;public $socid=0;public function hasRight(...$args){return end($args)==='creer';}};
  mvBlocked(fn()=>mvSave($piece,$id,(string)$code,$base,null,$writer),'Write-only user without read access accepted');
  mvBlocked(fn()=>NorwayManualVat::save($db,$entity+1,$piece,$id,(string)$code,$base,NorwayManualVat::editToken($line),$user),'Other entity accepted');
  mvSql('UPDATE '.$p.'accounting_bookkeeping SET date_validated=UTC_TIMESTAMP() WHERE rowid='.$id);
  mvBlocked(fn()=>mvSave($piece,$id,(string)$code,$base),'Locked entry accepted');
  mvSql('UPDATE '.$p.'accounting_bookkeeping SET date_validated=NULL,date_export=UTC_TIMESTAMP() WHERE rowid='.$id);
  mvBlocked(fn()=>mvSave($piece,$id,(string)$code,$base),'Exported entry accepted');
  mvSql('UPDATE '.$p.'accounting_bookkeeping SET date_export=NULL WHERE rowid='.$id);
  $conf->global->ACCOUNTANCY_FISCAL_PERIOD_MODE='openonly';$conf->cache['active_fiscal_period_cached']=array();
  mvBlocked(fn()=>mvSave($piece,$id,(string)$code,$base),'Closed period accepted');
  $conf->global->ACCOUNTANCY_FISCAL_PERIOD_MODE='blockedonclosed';unset($conf->cache['active_fiscal_period_cached']);
  mvSql("UPDATE ".$p."accounting_bookkeeping SET doc_ref='SYNTHETIC CHANGED' WHERE rowid=".$id);
  mvAssert(!NorwayManualVat::current(mvLine($piece,$id)),'Changed source still current');
  mvSave($piece,$id,(string)$code,$base);mvAssert(NorwayManualVat::current(mvLine($piece,$id)),'Changed source could not be updated');
  mvSave($piece,$id,'','');mvAssert(mvLine($piece,$id)->tax_code===null,'Removal failed');
 }}
 echo "PASS all six manual VAT codes and signs, unchanged ledger, source/concurrent-edit guards, removal, entity/rights, lock/export/period guards\n";
 mvAssert(count($pieces)===12,'Six configured codes not exercised');
 $piece=$pieces[0];$line=NorwayManualVat::lines($db,$entity,$piece,$user)[0];$id=(int)$line->rowid;
 $body=mvHttp('/index.php');mvHttp('/index.php',array('token'=>mvToken($body),'actionlogin'=>'login','loginfunction'=>'loginfunction','username'=>'testadmin','password'=>getenv('DOLI_ADMIN_PASSWORD')));
 $path='/accountancy/bookkeeping/card.php?piece_num='.$piece;
 foreach(array('nb_NO'=>'MVA på manuelt bilag','en_US'=>'VAT on manual voucher') as $lang=>$title){$body=mvHttp($path.'&lang='.$lang);mvAssert(strpos(html_entity_decode($body,ENT_QUOTES,'UTF-8'),$title)!==false,'Native voucher section not rendered/translated');}
 $post=array('action'=>'norway_save_manual_vat','token'=>mvToken($body),'piece_num'=>$piece,'norway_vat_line'=>$id,'norway_vat_code'=>'3','norway_vat_base'=>'100,00','norway_vat_token'=>NorwayManualVat::editToken(mvLine($piece,$id)));
 $body=mvHttp($path,$post);mvAssert(mvLine($piece,$id)->tax_code==='3','Native voucher HTTP save failed');
 $post['norway_vat_code']='';$post['token']='invalid';mvHttp($path,$post);mvAssert(mvLine($piece,$id)->tax_code==='3','Bad CSRF token changed metadata');
 mvSql('UPDATE '.$p.'accounting_bookkeeping SET date_validated=UTC_TIMESTAMP() WHERE piece_num='.$piece.' AND entity='.$entity);
 $body=mvHttp($path);mvAssert(strpos($body,'norway-manual-vat')!==false && strpos($body,'name="norway_vat_code"')===false,'Locked metadata missing or editable');
 echo "PASS native voucher HTTP bilingual section, save, comma decimal, invalid CSRF and read-only locked information\n";
} finally {
 if($ids){mvSql('DELETE FROM '.$p.'norway_manual_vat WHERE entity='.$entity.' AND fk_bookkeeping IN ('.implode(',',$ids).')');mvSql('DELETE FROM '.$p.'accounting_bookkeeping WHERE entity='.$entity.' AND rowid IN ('.implode(',',$ids).')');}
 mvSql('DELETE FROM '.$p.'c_tva WHERE entity='.$entity.' AND code IN ('.$codes.')');foreach($oldRates as $row){$values=array_map(fn($v)=>$v===null?'NULL':"'".$db->escape((string)$v)."'",array_values($row));mvSql('INSERT INTO '.$p.'c_tva ('.implode(',',array_keys($row)).') VALUES ('.implode(',',$values).')');}
 $conf->global=$oldGlobals;curl_close($curl);unlink($cookie);
}
