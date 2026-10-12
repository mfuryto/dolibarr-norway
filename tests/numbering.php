<?php
// SPDX-License-Identifier: GPL-3.0-or-later
if (PHP_SAPI!=='cli' || getenv('NORWAY_SYNTHETIC_TEST')!=='1' || getenv('DOLI_DB_NAME')!=='norway_test') { exit(1); }
require '/var/www/html/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/norway/class/NorwayNumbering.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/comm/propal/class/propal.class.php';
function nuAssert($ok,$message) { if (!$ok) { throw new RuntimeException($message); } }
function nuBlocked($call,$message) { $blocked=false; try { $call(); } catch (Throwable $error) { $blocked=true; } nuAssert($blocked,$message); }
function nuSql($sql) { global $db; $r=$db->query($sql); nuAssert((bool)$r,'Synthetic numbering fixture SQL: '.$db->lasterror()); return $r; }
$p=$db->prefix(); $entity=(int)$conf->entity; $oldSoc=clone $mysoc; $oldGlobal=clone $conf->global;
nuAssert($user->fetch('','testadmin')>0,'Synthetic admin missing'); $user->loadRights('',1);
$names="'FACTURE_ADDON','PROPALE_ADDON','SOCIETE_CODECLIENT_ADDON','NORWAY_NUMBERING_V1'";
$r=nuSql('SELECT * FROM '.$p.'const WHERE entity='.$entity.' AND name IN ('.$names.')'); $original=array(); while ($row=$db->fetch_object($r)) { $original[]=(array)$row; }
$r=nuSql('SELECT rowid,fk_soc AS socid,ref,fk_statut FROM '.$p.'facture WHERE entity='.$entity.' AND fk_statut<>0 AND type=0 ORDER BY rowid LIMIT 1'); $source=$db->fetch_object($r); nuAssert((bool)$source,'Synthetic source missing');
$createdInvoices=array(); $createdProposals=array(); $createdPartners=array();
try {
 $input=NorwayNumbering::input('F',1201,'K',501,'T',301);
 $before=NorwayNumbering::preview($db,$entity,$input,$user);
 nuAssert($before['next']===array('invoice'=>'F001201','credit'=>'K000501','proposal'=>'T000301','customer'=>'00001','supplier'=>'00001'),'Migration start offsets incorrect');
 nuAssert(!NorwayNumbering::preview($db,$entity,NorwayNumbering::input('F',1,'K',1),$user)['record'],'Preview wrote a profile');
 $reader=clone $user; $reader->admin=0;
 nuBlocked(fn()=>NorwayNumbering::preview($db,$entity,$input,$reader),'Nonadmin accepted');
 nuBlocked(fn()=>NorwayNumbering::preview($db,$entity+1,$input,$user),'Foreign entity accepted');
 foreach (array(array('F',0,'K',1),array('F',1000000,'K',1),array('F- ',1,'K',1),array('f',1,'K',1),array('F',1,'F',1),array('F',1,'K',1,'F',1)) as $invalid) { nuBlocked(fn()=>NorwayNumbering::input(...$invalid),'Invalid series accepted'); }
 $db->begin(); nuBlocked(fn()=>NorwayNumbering::save($db,$entity,$input,$before['fingerprint'],$user),'Nested write accepted'); $db->rollback();
 nuSql('UPDATE '.$p.'facture SET fk_statut=0 WHERE rowid='.(int)$source->rowid);
 try { nuBlocked(fn()=>NorwayNumbering::save($db,$entity,$input,$before['fingerprint'],$user),'Stale document history accepted'); }
 finally { nuSql('UPDATE '.$p.'facture SET fk_statut='.(int)$source->fk_statut.' WHERE rowid='.(int)$source->rowid); }
 $failing=new class($db) {
  private $inner;
  public function __construct($inner) { $this->inner=$inner; }
  public function __get($name) { return $this->inner->$name; }
  public function __call($name,$args) {
   if ($name==='query' && preg_match('/^(INSERT INTO |UPDATE )/',$args[0]) && strpos($args[0],"'FACTURE_ADDON'")!==false) { return false; }
   if ($name==='query' && strpos($args[0],'UPDATE '.$this->inner->prefix().'const SET')===0 && strpos($args[0],NorwayNumbering::MODULE)!==false) { return false; }
   return $this->inner->$name(...$args);
  }
 };
 nuBlocked(fn()=>NorwayNumbering::save($failing,$entity,$input,$before['fingerprint'],$user),'Injected partial write succeeded');
 nuAssert(NorwayNumbering::preview($db,$entity,$input,$user)===$before,'Partial write was not rolled back');
 $first=NorwayNumbering::input('F',1,'K',1,'T',1); $a=NorwayNumbering::preview($db,$entity,$first,$user);
 NorwayNumbering::save($db,$entity,$first,$a['fingerprint'],$user);
 nuBlocked(fn()=>NorwayNumbering::save($db,$entity,$first,$a['fingerprint'],$user),'Replayed configuration accepted');
 $a=NorwayNumbering::preview($db,$entity,$input,$user); NorwayNumbering::save($db,$entity,$input,$a['fingerprint'],$user);
 $record=NorwayNumbering::record($db,$entity); nuAssert($record['input']===$input,'Start changes before first issue failed');
 // Real core issuance: no forced references, and no month/year reset.
 $mysoc->idprof1='123456785'; $mysoc->country_code='NO'; $mysoc->name='SYNTHETIC NUMBERING ONLY'; $mysoc->address='Synthetic'; $mysoc->zip='0000'; $mysoc->town='Synthetic'; $mysoc->tva_assuj=1;
 foreach (array(array(0,2026,'F001201'),array(0,2027,'F001202'),array(2,2027,'K000501'),array(2,2027,'K000502')) as [$type,$year,$expected]) {
  $invoice=new Facture($db); $invoice->socid=(int)$source->socid; $invoice->entity=$entity; $invoice->type=$type;
  $invoice->date=dol_mktime(12,0,0,1,1,$year); $invoice->multicurrency_code='NOK'; $invoice->multicurrency_tx=1;
  if ($type===2) { $invoice->fk_facture_source=$createdInvoices[0]; }
  nuAssert($invoice->create($user)>0,'Real invoice create failed: '.$invoice->error); $createdInvoices[]=(int)$invoice->id;
  nuAssert($invoice->addline('Synthetic numbering only',10,1,25)>0,'Real line failed');
  nuAssert($invoice->validate($user)>0,'Real invoice validation failed: '.$invoice->error);
  nuAssert($invoice->fetch($invoice->id)>0 && $invoice->ref===$expected,'Real invoice numbering wrong: '.$invoice->ref);
 }
 foreach (array(2026=>'T000301',2027=>'T000302') as $year=>$expected) {
  $proposal=new Propal($db); $proposal->socid=(int)$source->socid; $proposal->entity=$entity; $proposal->date=dol_mktime(12,0,0,1,1,$year); $proposal->duree_validite=30; $proposal->multicurrency_code='NOK'; $proposal->multicurrency_tx=1;
  nuAssert($proposal->create($user)>0,'Real proposal create failed: '.$proposal->error); $createdProposals[]=(int)$proposal->id;
  nuAssert($proposal->addline('Synthetic numbering only',100,1,25)>0,'Proposal line failed');
  nuAssert($proposal->valid($user)>0,'Proposal issuance failed: '.$proposal->error);
  nuAssert($proposal->fetch($proposal->id)>0 && $proposal->ref===$expected,'Real proposal numbering wrong: '.$proposal->ref);
 }
 $state=NorwayNumbering::preview($db,$entity,$input,$user);
 nuAssert($state['locked'] && $state['next']===array('invoice'=>'F001203','credit'=>'K000503','proposal'=>'T000303','customer'=>'00001','supplier'=>'00001'),'Sequences not independent and increasing');
 nuBlocked(fn()=>NorwayNumbering::preview($db,$entity,$first,$user),'Start reset after issuance accepted');
 $changed=$input; $changed['invoice_prefix']='Z'; nuBlocked(fn()=>NorwayNumbering::preview($db,$entity,$changed,$user),'Prefix reset after issuance accepted');
 foreach (array(array(1,0,'00001',''),array(0,1,'','00001'),array(1,1,'00002','00002'),array(1,0,'00003',''),array(0,1,'','00003')) as [$client,$supplier,$customerCode,$supplierCode]) {
  $partner=new Societe($db); $partner->name='SYNTHETIC NUMBERING PARTNER ONLY'; $partner->client=$client; $partner->fournisseur=$supplier;
  $partner->code_client=$client ? 'auto' : ''; $partner->code_fournisseur=$supplier ? 'auto' : ''; $partner->country_id=173;
  nuAssert($partner->create($user)>0,'Real partner create failed: '.$partner->error); $createdPartners[]=(int)$partner->id;
  nuAssert($partner->fetch($partner->id)>0 && (string)$partner->code_client===$customerCode && (string)$partner->code_fournisseur===$supplierCode,'Automatic five-digit partner numbers wrong');
 }
 $partnerState=NorwayNumbering::preview($db,$entity,$input,$user);
 nuAssert($partnerState['partners_locked'] && $partnerState['next']['customer']==='00004' && $partnerState['next']['supplier']==='00004','Partner series not independently increasing or locked');
 $changed=$input; $changed['customer_start']=2; nuBlocked(fn()=>NorwayNumbering::preview($db,$entity,$changed,$user),'Customer start reset after creation accepted');
 require_once DOL_DOCUMENT_ROOT.'/custom/norway/core/modules/societe/mod_codeclient_norway.php';
 $partnerModel=new mod_codeclient_norway($db); $legacy=new Societe($db); nuAssert($legacy->fetch((int)$source->socid)>0,'Legacy partner fetch failed'); $legacyCode=$legacy->code_client;
 nuAssert($partnerModel->verif($db,$legacyCode,$legacy,0)===0 && $legacyCode===$legacy->code_client,'Unchanged legacy customer code rejected or rewritten');
 $newPartner=new Societe($db); $duplicate='00001'; nuAssert($partnerModel->verif($db,$duplicate,$newPartner,0)===-3,'Duplicate customer code accepted');
 foreach (array('1234','99999') as $bad) {
  $partnerId=$createdPartners[0]; nuSql("UPDATE ".$p."societe SET code_client='".$bad."' WHERE rowid=".$partnerId);
  try { nuBlocked(fn()=>NorwayNumbering::next($db,$entity,$input,'customer'),'Wrong width or exhausted customer numbering accepted'); }
  finally { nuSql("UPDATE ".$p."societe SET code_client='00001' WHERE rowid=".$partnerId); }
 }
 // Other invoice types continue through the original core model.
 require_once DOL_DOCUMENT_ROOT.'/custom/norway/core/modules/facture/mod_facture_norway.php';
 $wrapper=new mod_facture_norway($db); $fallback=$record['fallback']; require_once DOL_DOCUMENT_ROOT.'/core/modules/facture/'.$fallback.'.php'; $oldModel=new $fallback($db);
 $soc=new Societe($db); nuAssert($soc->fetch((int)$source->socid)>0,'Customer fetch failed');
 foreach (array(1,3,5) as $type) { $doc=new Facture($db); $doc->entity=$entity; $doc->type=$type; $doc->date=dol_now(); nuAssert($wrapper->getNextValue($soc,$doc)===$oldModel->getNextValue($soc,$doc),'Other invoice type changed'); }
 $current=$db->fetch_object(nuSql('SELECT ref FROM '.$p.'facture WHERE rowid='.(int)$source->rowid)); nuAssert($current->ref===$source->ref,'Historical reference changed');
 $id=$createdInvoices[0];
 nuSql('UPDATE '.$p.'facture SET entity='.($entity+1)." ,ref='F999998' WHERE rowid=".$id);
 try { nuAssert(NorwayNumbering::next($db,$entity,$input,'invoice')==='F001203','Foreign entity contaminated series'); }
 finally { nuSql('UPDATE '.$p.'facture SET entity='.$entity.",ref='F001201' WHERE rowid=".$id); }
 foreach (array('F1234','F999999') as $bad) {
  nuSql("UPDATE ".$p."facture SET ref='".$bad."' WHERE rowid=".$id);
  try { nuBlocked(fn()=>NorwayNumbering::next($db,$entity,$input,'invoice'),'Digit mismatch or exhausted sequence accepted'); }
  finally { nuSql("UPDATE ".$p."facture SET ref='F001201' WHERE rowid=".$id); }
 }
 nuAssert(NorwayNumbering::record($db,$entity)===$record,'Issuance changed the saved profile');
 print "PASS real automatic F/K/T issuance and five-digit customer/supplier creation, legacy codes, migration start, independent ascending sequences, year continuity, freeze after issue, history preservation, fallback types, entity isolation, capacity, stale preview, replay and partial-write rollback\n";
} finally {
 foreach (array_reverse($createdInvoices) as $id) { nuSql('DELETE FROM '.$p.'facturedet WHERE fk_facture='.$id); nuSql('DELETE FROM '.$p.'facture_extrafields WHERE fk_object='.$id); nuSql('DELETE FROM '.$p.'facture WHERE rowid='.$id); }
 foreach ($createdProposals as $id) { nuSql('DELETE FROM '.$p.'propaldet WHERE fk_propal='.$id); nuSql('DELETE FROM '.$p.'propal_extrafields WHERE fk_object='.$id); nuSql('DELETE FROM '.$p.'propal WHERE rowid='.$id); }
 foreach ($createdPartners as $id) { nuSql('DELETE FROM '.$p.'societe_extrafields WHERE fk_object='.$id); nuSql('DELETE FROM '.$p.'societe WHERE rowid='.$id); }
 nuSql('DELETE FROM '.$p.'const WHERE entity='.$entity.' AND name IN ('.$names.')');
 foreach ($original as $row) { $values=array_map(static fn($v)=>$v===null ? 'NULL' : "'".$db->escape((string)$v)."'",array_values($row)); nuSql('INSERT INTO '.$p.'const ('.implode(',',array_keys($row)).') VALUES ('.implode(',',$values).')'); }
 $mysoc=$oldSoc; $conf->global=$oldGlobal;
}
