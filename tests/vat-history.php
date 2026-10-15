<?php
// SPDX-License-Identifier: GPL-3.0-or-later
if(PHP_SAPI!=='cli'||getenv('NORWAY_SYNTHETIC_TEST')!=='1'||getenv('DOLI_DB_NAME')!=='norway_test'){exit(1);}
require '/var/www/html/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/norway/class/NorwayVatHistory.php';
require_once DOL_DOCUMENT_ROOT.'/custom/norway/class/NorwayPostingRegistrationContext.php';
function vhAssert($ok,$message){if(!$ok){throw new RuntimeException($message);}}
function vhSql($sql){global $db;$r=$db->query($sql);vhAssert((bool)$r,$db->lasterror());return $r;}
function vhSave($input){global $db,$entity,$user;$p=NorwayVatHistory::preview($db,$entity,'123456785',$input);NorwayVatHistory::save($db,$entity,'123456785',$input,NorwayVatHistory::fingerprint($p),$user);return NorwayVatHistory::read($db,$entity,'123456785');}
$user->fetch('','testadmin');$user->loadRights('',1);$oldEntity=$conf->entity;$entity=987702;$conf->entity=$entity;$p=$db->prefix();$db->begin();
try{
 vhAssert((int)$db->fetch_object(vhSql('SELECT COUNT(*) n FROM '.$p.'const WHERE entity='.$entity))->n===0,'History fixture entity must be empty');
 $legacy=json_encode(array('version'=>1,'entity'=>$entity,'org'=>'123456785','start'=>'2026-01-01','confirmed_by'=>(int)$user->id,'confirmed_at'=>'2026-01-01T00:00:00+00:00'));
 foreach(array('MAIN_INFO_SIREN'=>'123456785','MAIN_INFO_SOCIETE_COUNTRY'=>'173:NO:Norway',NorwayVatRegistration::SETTING=>$legacy) as $name=>$value){vhSql("INSERT INTO ".$p."const (name,value,type,entity) VALUES ('".$name."','".$db->escape($value)."','chaine',".$entity.")");}
 $input=NorwayVatHistory::input(1,'2026-01-01','2026-03-31','Synthetic documented ending',false);
 $preview=NorwayVatHistory::preview($db,$entity,'123456785',$input);$reader=clone $user;$reader->admin=0;
 try{NorwayVatHistory::save($db,$entity,'123456785',$input,NorwayVatHistory::fingerprint($preview),$reader);throw new LogicException('Reader wrote history');}catch(InvalidArgumentException $expected){}
 $state=vhSave($input);vhAssert($state['revision']===2,'Original date not preserved as seed');
 vhAssert(NorwayVatHistory::compare($state,'2026-03-31')['status']==='HistoryCovered'&&NorwayVatHistory::compare($state,'2026-04-01')['status']==='HistoryGap','Inclusive end boundary wrong');
 try{NorwayVatHistory::save($db,$entity,'123456785',$input,NorwayVatHistory::fingerprint($preview),$user);throw new LogicException('History replay accepted');}catch(RuntimeException $expected){}
 $state=vhSave(NorwayVatHistory::input(0,'2026-05-01','','Synthetic new registration',false));vhAssert($state['revision']===3&&count($state['active'])===2,'New period missing');
 foreach(array(array('2026-03-31','2026-04-30'),array('2026-05-01','2026-06-01')) as $dates){try{NorwayVatHistory::preview($db,$entity,'123456785',NorwayVatHistory::input(0,$dates[0],$dates[1],'Synthetic overlap rejection',false));throw new LogicException('Overlapping period accepted');}catch(InvalidArgumentException $expected){}}
 NorwayVatHistory::preview($db,$entity,'123456785',NorwayVatHistory::input(0,'2026-04-01','2026-04-30','Synthetic contiguous boundaries',false));
 $stored=$db->fetch_object(vhSql("SELECT value FROM ".$p."const WHERE entity=".$entity." AND name='".NorwayVatHistory::SETTING."'"))->value;
 $basis=array('source_complete'=>true,'posting'=>array('entity'=>$entity),'invoice'=>array('entity'=>$entity,'datef'=>'2026-01-01','type'=>2),'settings'=>array(array('entity'=>$entity,'name'=>'MAIN_INFO_SIREN','value'=>'123456785'),array('entity'=>$entity,'name'=>'MAIN_INFO_SOCIETE_COUNTRY','value'=>'173:NO:Norway'),array('entity'=>$entity,'name'=>NorwayVatRegistration::SETTING,'value'=>$legacy),array('entity'=>$entity,'name'=>NorwayVatHistory::SETTING,'value'=>$stored)));
 $captured=NorwayPostingRegistrationContext::history($basis,$entity);$legacyContext=NorwayPostingRegistrationContext::evaluate($basis,$entity);
 vhAssert($captured['version']===2&&$captured['status']==='HistoryCovered'&&$captured['period']===1&&$captured['credit'],'Captured history/credit context wrong');
 $state=vhSave(NorwayVatHistory::input(1,'2026-01-05','2026-03-31','Synthetic corrected first date',false));
 vhAssert($state['revision']===4&&$state['record']['events'][0]['start']==='2026-01-01','Correction erased original');
 vhAssert(NorwayVatHistory::compare($state,'2026-01-01')['status']==='HistoryGap','Corrected start ignored');
 vhAssert(NorwayPostingRegistrationContext::history($basis,$entity)===$captured&&NorwayPostingRegistrationContext::evaluate($basis,$entity)===$legacyContext,'Current history changed preserved semantics');
 $state=vhSave(NorwayVatHistory::input(2,'2026-05-01','','Synthetic erroneous period excluded',true));
 try{NorwayVatHistory::preview($db,$entity,'123456785',NorwayVatHistory::input(2,'2026-05-01','','Synthetic prohibited reopening',false));throw new LogicException('Voided period reopened');}catch(RuntimeException $expected){}
 $state=vhSave(NorwayVatHistory::input(0,'2026-05-05','','Synthetic replacement period',false));vhAssert($state['active'][3]['start']==='2026-05-05','New identity after void missing');
 vhAssert(NorwayVatHistory::inspect('{invalid',$legacy,$entity,'123456785')['status']==='HistoryInvalid','Corrupt history trusted');
 vhAssert(NorwayVatHistory::inspect($stored,'changed origin',$entity,'123456785')['status']==='HistoryInvalid','Changed original accepted');
 vhAssert(NorwayVatHistory::inspect($stored,$legacy,$entity,'974760673')['status']==='Identity','Foreign organisation accepted');
 $row=$db->fetch_object(vhSql("SELECT value FROM ".$p."const WHERE entity=".$entity." AND name='".NorwayVatRegistration::SETTING."'"));vhAssert($row->value===$legacy,'Original constant overwritten');
 $stale=NorwayVatHistory::preview($db,$entity,'123456785',NorwayVatHistory::input(1,'2026-01-06','2026-03-31','Synthetic stale proposal',false));
 vhSave(NorwayVatHistory::input(1,'2026-01-07','2026-03-31','Synthetic intervening correction',false));
 try{NorwayVatHistory::save($db,$entity,'123456785',$stale['input'],NorwayVatHistory::fingerprint($stale),$user);throw new LogicException('Stale revision accepted');}catch(RuntimeException $expected){}
 $stable=NorwayVatHistory::read($db,$entity,'123456785');
 $failInput=NorwayVatHistory::input(1,'2026-01-08','2026-03-31','Synthetic failure after write',false);
 $failPreview=NorwayVatHistory::preview($db,$entity,'123456785',$failInput);
 $failing=new class($db){private $inner;private $written=false;public function __construct($db){$this->inner=$db;}public function __get($name){return $this->inner->$name;}public function __call($name,$args){
  if($name==='query'&&preg_match('/^(INSERT INTO |UPDATE |DELETE FROM )/i',$args[0])&&strpos($args[0],NorwayVatHistory::SETTING)!==false){$this->written=true;}
  if($name==='query'&&$this->written&&strpos($args[0],'SELECT value FROM ')===0&&strpos($args[0],NorwayVatHistory::SETTING)!==false){return false;}
  return $this->inner->$name(...$args);
 }};
 try{NorwayVatHistory::save($failing,$entity,'123456785',$failInput,NorwayVatHistory::fingerprint($failPreview),$user);throw new LogicException('Injected post-write failure ignored');}catch(RuntimeException $expected){}
 vhAssert(NorwayVatHistory::read($db,$entity,'123456785')['record']===$stable['record'],'Failed write changed history inside outer transaction');
 $capacity=$stable['record'];while(count($capacity['events'])<100){$event=end($capacity['events']);$event['revision']=count($capacity['events'])+1;$event['reason']='Synthetic capacity review '.$event['revision'];$capacity['events'][]=$event;}
 NorwayVatHistory::validate($capacity,$entity,'123456785');$event=end($capacity['events']);$event['revision']=101;$capacity['events'][]=$event;
 try{NorwayVatHistory::validate($capacity,$entity,'123456785');throw new LogicException('Capacity trimmed or exceeded');}catch(RuntimeException $expected){}
 $b=$basis;$b['settings'][3]['value']='invalid';vhAssert(NorwayPostingRegistrationContext::history($b,$entity)['status']==='HistoryInvalid','Invalid history fell back to first date');
 $b=$basis;$b['settings'][3]['entity']=0;vhAssert(!NorwayPostingRegistrationContext::usesHistory($b,$entity),'Global history selected');
 $bad=$state['record'];$bad['events'][0]['start']='2025-01-01';try{NorwayVatHistory::validate($bad,$entity,'123456785');throw new LogicException('Origin seed changed');}catch(RuntimeException $expected){}
}finally{$db->rollback();$conf->entity=$oldEntity;unset($conf->global->{NorwayVatHistory::SETTING});}
print "PASS registration history import, append-only corrections, periods/gaps, inclusive overlap, void/reopen, stale/replay/admin/identity guards and preserved v1/v2 contexts\n";
