<?php
// SPDX-License-Identifier: GPL-3.0-or-later
if (PHP_SAPI!=='cli' || getenv('NORWAY_SYNTHETIC_TEST')!=='1' || getenv('DOLI_DB_NAME')!=='norway_test') { exit(1); }
require '/var/www/html/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/norway/class/NorwayTaxMappings.php';
require_once DOL_DOCUMENT_ROOT.'/custom/norway/class/NorwayInvoiceMapping.php';
function tmAssert($ok,$message) { if (!$ok) { throw new RuntimeException($message); } }
function tmBlocked($call,$message) {
    $blocked=false; try { $call(); } catch (InvalidArgumentException | RuntimeException $error) { $blocked=true; }
    tmAssert($blocked,$message);
}
$oldEntity=$conf->entity; $oldSoc=$mysoc; $entity=987665; $conf->entity=$entity;
$mysoc=(object)array('idprof1'=>'123456785','country_code'=>'NO'); $p=$db->prefix();
$admin=new User($db); tmAssert($admin->fetch('','testadmin')>0,'Synthetic admin missing'); $admin->admin=1;
$db->begin();
try {
 foreach (array('MAIN_INFO_SIREN'=>'123456785','MAIN_INFO_SOCIETE_COUNTRY'=>'173:NO:Norway') as $key=>$value) {
  tmAssert((bool)$db->query("INSERT INTO ".$p."const (name,value,type,entity) VALUES ('".$key."','".$value."','chaine',".$entity.")"),'Synthetic identity fixture failed');
 }
 $input=NorwayTaxMappings::input('LOCAL25','3','2026-01-01','2026-06-30','sale',0,false);
 $empty=NorwayTaxMappings::read($db,$entity); tmAssert(!$empty['active'],'Unexpected initial rules');
 $a=NorwayTaxMappings::preview($db,$entity,$input);
 $reader=clone $admin; $reader->admin=0;
 tmBlocked(fn()=>NorwayTaxMappings::save($db,$entity,$a['input'],$a['fingerprint'],$reader),'Nonadmin write accepted');
 tmBlocked(fn()=>NorwayTaxMappings::read($db,$entity+1),'Foreign entity read accepted');
 tmBlocked(fn()=>NorwayTaxMappings::save($db,$entity+1,$a['input'],$a['fingerprint'],$admin),'Foreign entity write accepted');
 NorwayTaxMappings::save($db,$entity,$a['input'],$a['fingerprint'],$admin);
 $first=NorwayTaxMappings::read($db,$entity);
 tmAssert(count($first['record']['events'])===1 && $first['active'][1]['target']==='3','First mapping not stored');
 tmBlocked(fn()=>NorwayTaxMappings::save($db,$entity,$a['input'],$a['fingerprint'],$admin),'Replayed preview accepted');
 $overlap=$input; $overlap['start']='2026-06-30'; $overlap['end']='2026-12-31';
 tmBlocked(fn()=>NorwayTaxMappings::preview($db,$entity,$overlap),'Inclusive boundary overlap accepted');
 $next=$overlap; $next['start']='2026-07-01';
 $b=NorwayTaxMappings::preview($db,$entity,$next); NorwayTaxMappings::save($db,$entity,$b['input'],$b['fingerprint'],$admin);
 $buy=$input; $buy['direction']='purchase';
 $c=NorwayTaxMappings::preview($db,$entity,$buy); NorwayTaxMappings::save($db,$entity,$c['input'],$c['fingerprint'],$admin);
 $correction=$input; $correction['id']=1; $correction['target']='31';
 $d=NorwayTaxMappings::preview($db,$entity,$correction); NorwayTaxMappings::save($db,$entity,$d['input'],$d['fingerprint'],$admin);
 $corrected=NorwayTaxMappings::read($db,$entity);
 tmAssert(count($corrected['record']['events'])===4 && $corrected['record']['events'][0]===$first['record']['events'][0]
    && $corrected['active'][1]['target']==='31' && $corrected['active'][3]['direction']==='purchase','Correction erased history or direction');
 $correction['end']='2026-07-01'; tmBlocked(fn()=>NorwayTaxMappings::preview($db,$entity,$correction),'Correction overlap accepted');
 $retire=$d['input']; $retire['retire']=true;
 $e=NorwayTaxMappings::preview($db,$entity,$retire); NorwayTaxMappings::save($db,$entity,$e['input'],$e['fingerprint'],$admin);
 $retired=NorwayTaxMappings::read($db,$entity);
 tmAssert($retired['active'][1]['retire'] && count($retired['record']['events'])===5,'Retirement lost history');
 tmBlocked(fn()=>NorwayTaxMappings::preview($db,$entity,$retire),'Retired rule reopened');
 $invoice=(object)array('entity'=>$entity,'type'=>2,'lines'=>array((object)array('vat_src_code'=>'LOCAL25','info_bits'=>0,'total_ht'=>-100,'total_tva'=>-25)));
 $before=serialize(array($retired['record'],$invoice));
 foreach (array(1=>'3',3=>'3',4=>'31') as $revision=>$target) {
  $comparison=NorwayInvoiceMapping::report($retired['record'],$revision,$invoice,'2026-01-01');
  tmAssert($comparison['rows'][0]['status']==='Candidate' && $comparison['rows'][0]['rule']['target']===$target,'Selected history revision not respected');
 }
 tmAssert(NorwayInvoiceMapping::report($retired['record'],5,$invoice,'2026-01-01')['rows'][0]['status']==='NoRule','Retired sale or purchase rule applied');
 tmAssert(NorwayInvoiceMapping::report($retired['record'],1,$invoice,'2026-06-30')['rows'][0]['status']==='Candidate','Inclusive end boundary lost');
 tmAssert(NorwayInvoiceMapping::report($retired['record'],1,$invoice,'2026-07-01')['rows'][0]['status']==='NoRule','Rule leaked outside interval');
 tmAssert(NorwayInvoiceMapping::report($retired['record'],1,$invoice,'')['rows'][0]['status']==='UnknownDate','Unknown date mapped');
 tmAssert(serialize(array($retired['record'],$invoice))===$before,'History comparison mutated input');
 $invoice->lines[0]->info_bits=8;
 tmAssert(NorwayInvoiceMapping::report($retired['record'],1,$invoice,'2026-01-01')['rows'][0]['status']==='Markers','Flagged line mapped');
 $invoice->lines[0]->info_bits=0; $invoice->lines[0]->vat_src_code='LOCAL25 ';
 tmAssert(NorwayInvoiceMapping::report($retired['record'],1,$invoice,'2026-01-01')['rows'][0]['status']==='NoRule','Local code normalised');
 unset($invoice->lines[0]->vat_src_code);
 tmAssert(NorwayInvoiceMapping::report($retired['record'],1,$invoice,'2026-01-01')['rows'][0]['status']==='Unavailable','Missing line code mapped');
 foreach (array(0,6,-1,'1') as $invalidRevision) {
  tmBlocked(fn()=>NorwayInvoiceMapping::report($retired['record'],$invalidRevision,$invoice,'2026-01-01'),'Invalid revision accepted');
 }
 $invoice->entity=$entity+1;
 tmBlocked(fn()=>NorwayInvoiceMapping::report($retired['record'],1,$invoice,'2026-01-01'),'Foreign invoice accepted');
 $invoice->entity=$entity; $corrupt=$retired['record']; $corrupt['events'][4]['target']='INVALID';
 tmBlocked(fn()=>NorwayInvoiceMapping::report($corrupt,1,$invoice,'2026-01-01'),'Invalid later history ignored');
 $invoice->lines=array();
 tmAssert(!NorwayInvoiceMapping::report($retired['record'],1,$invoice,'2026-01-01')['rows'],'Empty invoice invented rows');
 print "PASS explicit mapping revision comparison, correction/retirement boundaries, sale-only rules, source markers, identity scope and read-only preservation\n";
 $f=NorwayTaxMappings::preview($db,$entity,$input); tmAssert($f['input']['id']===4,'ID reused after retirement');
 // A failed history write must preserve history even within an enclosing transaction.
 $failing=new class($db) {
    private $inner;
    public function __construct($inner) { $this->inner=$inner; }
    public function __get($name) { return $this->inner->$name; }
    public function __call($name,$args) {
        if ($name==='query' && preg_match('/^(INSERT INTO |UPDATE )/i',$args[0]) && strpos($args[0],NorwayTaxMappings::SETTING)!==false) { return false; }
        return $this->inner->$name(...$args);
    }
 };
 tmBlocked(fn()=>NorwayTaxMappings::save($failing,$entity,$f['input'],$f['fingerprint'],$admin),'Injected write failure ignored');
 tmAssert(NorwayTaxMappings::read($db,$entity)['record']===$retired['record'],'Failed write lost existing history');
 tmAssert((bool)$db->query("UPDATE ".$p."const SET value='974760673' WHERE entity=".$entity." AND name='MAIN_INFO_SIREN'"),'Identity change failed');
 tmBlocked(fn()=>NorwayTaxMappings::read($db,$entity),'Changed stored identity accepted');
 tmBlocked(fn()=>NorwayTaxMappings::save($db,$entity,$f['input'],$f['fingerprint'],$admin),'Changed identity write accepted');
 tmAssert((bool)$db->query("UPDATE ".$p."const SET value='123456785' WHERE entity=".$entity." AND name='MAIN_INFO_SIREN'"),'Identity restore failed');
 $broken=$retired['record']; $broken['events'][0]['target']='INVALID';
 tmBlocked(fn()=>NorwayTaxMappings::validate($broken,$entity,'123456785'),'Invalid historical code ignored');
 $full=$first['record']; $event=$full['events'][0]; $full['events']=array();
 for ($i=1;$i<=NorwayTaxMappings::LIMIT;$i++) { $event['revision']=$i; $full['events'][]=$event; }
 tmAssert(count(NorwayTaxMappings::validate($full,$entity,'123456785')['active'])===1,'Valid full history rejected');
 $encoded=json_encode($full,JSON_THROW_ON_ERROR);
 tmAssert((bool)$db->query("UPDATE ".$p."const SET value='".$db->escape($encoded)."' WHERE entity=".$entity." AND name='".NorwayTaxMappings::SETTING."'"),'Capacity fixture failed');
 tmBlocked(fn()=>NorwayTaxMappings::preview($db,$entity,$input),'Capacity overwrote history');
 tmAssert((bool)$db->query("UPDATE ".$p."const SET value='invalid' WHERE entity=".$entity." AND name='".NorwayTaxMappings::SETTING."'"),'Corrupt fixture failed');
 tmBlocked(fn()=>NorwayTaxMappings::read($db,$entity),'Corrupt history ignored');
 tmBlocked(fn()=>NorwayTaxMappings::save($db,$entity,$f['input'],$f['fingerprint'],$admin),'Corrupt history overwritten');
 $r=$db->query('SELECT COUNT(*) AS n FROM '.$p.'facture WHERE entity='.$entity);
 tmAssert($r && (int)$db->fetch_object($r)->n===0,'Mapping created accounting documents');
} finally { $db->rollback(); $conf->entity=$oldEntity; $mysoc=$oldSoc; unset($conf->global->{NorwayTaxMappings::SETTING}); }
print "PASS versioned mapping setup, inclusive overlap, directions, corrections, retirement, identity/entity/admin scope, stale preview, failure rollback and capacity\n";
