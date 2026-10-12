<?php
// SPDX-License-Identifier: GPL-3.0-or-later
if (PHP_SAPI!=='cli' || getenv('NORWAY_SYNTHETIC_TEST')!=='1' || getenv('DOLI_DB_NAME')!=='norway_test') { exit(1); }
require '/var/www/html/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/norway/class/NorwayInvoiceEvidence.php';
function ieAssert($ok,$message) { if (!$ok) { throw new RuntimeException($message); } }
function ieBlocked($call,$message) { $blocked=false; try { $call(); } catch (Throwable $error) { $blocked=true; } ieAssert($blocked,$message); }
function ieSql($sql) { global $db; $r=$db->query($sql); ieAssert((bool)$r,'Synthetic evidence fixture SQL failed: '.$db->lasterror()); return $r; }
function ieClone($table,$id,$changes) {
 global $db,$p;
 $r=ieSql('SHOW COLUMNS FROM '.$p.$table); $columns=array(); $values=array();
 while ($c=$db->fetch_object($r)) { if ($c->Field!=='rowid') { $columns[]=$c->Field; $values[]=array_key_exists($c->Field,$changes) ? $changes[$c->Field] : $c->Field; } }
 ieSql('INSERT INTO '.$p.$table.' ('.implode(',',$columns).') SELECT '.implode(',',$values).' FROM '.$p.$table.' WHERE rowid='.$id);
 return (int)$db->last_insert_id($p.$table);
}
$p=$db->prefix(); $oldEntity=$conf->entity; $oldSoc=$mysoc; $entity=987664; $id=0;
$admin=new User($db); ieAssert($admin->fetch('','testadmin')>0,'Synthetic administrator missing'); $admin->loadRights('',1);
$r=ieSql("SELECT fk_doc FROM ".$p."accounting_bookkeeping WHERE entity=".(int)$oldEntity." AND doc_type='customer_invoice' ORDER BY rowid LIMIT 1");
$source=$db->fetch_object($r); ieAssert((bool)$source,'Synthetic posting missing');
$conf->entity=$entity; $mysoc=(object)array('idprof1'=>'123456785','country_code'=>'NO');
try {
 foreach (array('MAIN_INFO_SIREN'=>'123456785','MAIN_INFO_SOCIETE_COUNTRY'=>'173:NO:Norway') as $name=>$value) { ieSql("INSERT INTO ".$p."const (name,value,type,entity) VALUES ('".$name."','".$value."','chaine',".$entity.")"); }
 $input=NorwayTaxMappings::input('SYN-EVID','3','2026-01-01','2026-12-31','sale',0,false);
 $m=NorwayTaxMappings::preview($db,$entity,$input); NorwayTaxMappings::save($db,$entity,$m['input'],$m['fingerprint'],$admin);
 $id=ieClone('facture',(int)$source->fk_doc,array('entity'=>$entity));
 $r=ieSql('SELECT rowid FROM '.$p.'facturedet WHERE fk_facture='.(int)$source->fk_doc); $lineIds=array();
 while ($line=$db->fetch_object($r)) { $lineIds[(int)$line->rowid]=ieClone('facturedet',(int)$line->rowid,array('fk_facture'=>$id,"vat_src_code"=>"'SYN-EVID'")); }
 $r=ieSql("SELECT rowid,fk_docdet FROM ".$p."accounting_bookkeeping WHERE entity=".(int)$oldEntity." AND fk_doc=".(int)$source->fk_doc." AND doc_type='customer_invoice'"); $ledgerIds=array();
 while ($entry=$db->fetch_object($r)) { $ledgerIds[]=ieClone('accounting_bookkeeping',(int)$entry->rowid,array('entity'=>$entity,'fk_doc'=>$id,'fk_docdet'=>$lineIds[(int)$entry->fk_docdet] ?? 0)); }
 $preview=NorwayInvoiceEvidence::preview($db,$entity,$id,1,$admin);
 ieAssert($preview['basis']['mapping']['comparison']['rows'][0]['status']==='Candidate','Selected revision not copied');
 $reader=clone $admin; $reader->admin=0;
 ieBlocked(fn()=>NorwayInvoiceEvidence::preview($db,$entity,$id,1,$reader),'Nonadmin read accepted');
 ieBlocked(fn()=>NorwayInvoiceEvidence::history($db,$entity+1,$id,$admin),'Foreign entity read accepted');
 ieBlocked(fn()=>NorwayInvoiceEvidence::preview($db,$entity,$id+999999,1,$admin),'Unknown invoice accepted');
 ieBlocked(fn()=>NorwayInvoiceEvidence::preview($db,$entity,$id,0,$admin),'Implicit revision accepted');
 $db->begin(); ieBlocked(fn()=>NorwayInvoiceEvidence::save($db,$entity,$id,1,$preview['fingerprint'],$admin),'Nested transaction accepted'); $db->rollback();
 $lineId=reset($lineIds); $ledgerId=reset($ledgerIds);
 foreach (array(array('facture','rowid='.$id,'total_ht','total_ht+1','total_ht-1'),array('facturedet','rowid='.$lineId,'info_bits','8','0'),array('accounting_bookkeeping','rowid='.$ledgerId,'debit','debit+1','debit-1')) as [$table,$where,$field,$change,$restore]) {
  ieSql('UPDATE '.$p.$table.' SET '.$field.'='.$change.' WHERE '.$where);
  ieBlocked(fn()=>NorwayInvoiceEvidence::save($db,$entity,$id,1,$preview['fingerprint'],$admin),'Changed source accepted: '.$table);
  ieSql('UPDATE '.$p.$table.' SET '.$field.'='.$restore.' WHERE '.$where);
 }
 ieAssert(NorwayInvoiceEvidence::save($db,$entity,$id,1,$preview['fingerprint'],$admin)===1,'First evidence not saved');
 $first=NorwayInvoiceEvidence::history($db,$entity,$id,$admin);
 ieBlocked(fn()=>NorwayInvoiceEvidence::save($db,$entity,$id,1,$preview['fingerprint'],$admin),'Replayed evidence accepted');
 $equal=NorwayInvoiceEvidence::compare($db,$entity,$id,1,$admin);
 ieAssert($equal===array('changes'=>array(),'mapping_changed'=>false,'organisation_changed'=>false),'Identical source reported changed');
 ieBlocked(fn()=>NorwayInvoiceEvidence::compare($db,$entity,$id,0,$admin),'Implicit copy revision accepted');
 ieBlocked(fn()=>NorwayInvoiceEvidence::compare($db,$entity,$id,2,$admin),'Unknown copy accepted');
 ieBlocked(fn()=>NorwayInvoiceEvidence::compare($db,$entity,$id,1,$reader),'Nonadmin comparison accepted');
 ieSql('UPDATE '.$p.'facture SET total_ht=total_ht+1 WHERE rowid='.$id);
 $difference=NorwayInvoiceEvidence::compare($db,$entity,$id,1,$admin);
 ieAssert(count($difference['changes'])===1 && $difference['changes'][0]['field']==='total_ht' && $difference['changes'][0]['group']==='invoice','Header change not identified');
 ieSql('UPDATE '.$p.'facture SET total_ht=total_ht-1 WHERE rowid='.$id);
 ieSql("UPDATE ".$p."facturedet SET description='<script>SYN-COMPARE</script>' WHERE rowid=".$lineId);
 $difference=NorwayInvoiceEvidence::compare($db,$entity,$id,1,$admin);
 ieAssert(count($difference['changes'])===1 && $difference['changes'][0]['field']==='description','Line change not identified');
 ieSql("UPDATE ".$p."facturedet SET description='".$db->escape($first[0]['data']['lines'][0]['description'])."' WHERE rowid=".$lineId);
 ieSql('UPDATE '.$p.'accounting_bookkeeping SET debit=debit+1 WHERE rowid='.$ledgerId);
 $difference=NorwayInvoiceEvidence::compare($db,$entity,$id,1,$admin);
 ieAssert(count($difference['changes'])===1 && $difference['changes'][0]['field']==='debit' && $difference['changes'][0]['group']==='ledger','Ledger change not identified');
 ieSql('UPDATE '.$p.'accounting_bookkeeping SET debit=debit-1 WHERE rowid='.$ledgerId);
 $added=ieClone('accounting_bookkeeping',$ledgerId,array());
 $difference=NorwayInvoiceEvidence::compare($db,$entity,$id,1,$admin);
 ieAssert(count($difference['changes'])===1 && $difference['changes'][0]['status']==='Added','New ledger row missed');
 ieSql('DELETE FROM '.$p.'accounting_bookkeeping WHERE rowid='.$added);
 $before=$first[0]['data']; $after=$preview['basis'];
 $after['lines']=array_reverse($after['lines']); $after['ledger']=array_reverse($after['ledger']);
 ieAssert(!NorwayInvoiceEvidence::differences($before,$after)['changes'],'Row order treated as data change');
 array_pop($after['ledger']);
 ieAssert(NorwayInvoiceEvidence::differences($before,$after)['changes'][0]['status']==='Removed','Removed row missed');
 $after=$preview['basis']; $after['document_id']++;
 ieBlocked(fn()=>NorwayInvoiceEvidence::differences($before,$after),'Foreign document comparison accepted');
 ieAssert(NorwayInvoiceEvidence::history($db,$entity,$id,$admin)===$first,'Comparison modified saved history');

 $next=NorwayInvoiceEvidence::preview($db,$entity,$id,1,$admin);
 $input['id']=1; $input['target']='31'; $m=NorwayTaxMappings::preview($db,$entity,$input); NorwayTaxMappings::save($db,$entity,$m['input'],$m['fingerprint'],$admin);
 ieBlocked(fn()=>NorwayInvoiceEvidence::save($db,$entity,$id,1,$next['fingerprint'],$admin),'Changed mapping ignored');
 ieAssert(!NorwayInvoiceEvidence::compare($db,$entity,$id,1,$admin)['mapping_changed'],'Later mapping rewrote selected revision');
 ieSql('UPDATE '.$p.'facture SET type=2 WHERE rowid='.$id);
 $next=NorwayInvoiceEvidence::preview($db,$entity,$id,2,$admin);
 $failing=new class($db) {
  private $inner; private $written=false;
  public function __construct($inner) { $this->inner=$inner; }
  public function __get($name) { return $this->inner->$name; }
  public function __call($name,$args) {
   if ($name==='query') {
    if (strpos($args[0],'INSERT INTO '.$this->inner->prefix().'norway_tax_evidence')===0) { $this->written=true; }
    elseif ($this->written && strpos($args[0],'SELECT rowid,revision,captured_at')===0) { return false; }
   }
   return $this->inner->$name(...$args);
  }
 };
 ieBlocked(fn()=>NorwayInvoiceEvidence::save($failing,$entity,$id,2,$next['fingerprint'],$admin),'Failed verification ignored');
 ieAssert(NorwayInvoiceEvidence::history($db,$entity,$id,$admin)===$first,'Failed save changed history');
 ieAssert(NorwayInvoiceEvidence::save($db,$entity,$id,2,$next['fingerprint'],$admin)===2,'Second evidence not appended');
 $history=NorwayInvoiceEvidence::history($db,$entity,$id,$admin);
 ieAssert($history[0]===$first[0] && $history[1]['data']['mapping']['comparison']['rows'][0]['rule']['target']==='31','Earlier copy rewritten or new mapping lost');
 foreach (array("multicurrency_code='EUR'","multicurrency_code='NOK',fk_statut=0","fk_statut=1,type=1") as $change) {
  ieSql('UPDATE '.$p.'facture SET '.$change.' WHERE rowid='.$id);
  ieBlocked(fn()=>NorwayInvoiceEvidence::preview($db,$entity,$id,2,$admin),'Unsupported invoice accepted');
 }
 ieSql('UPDATE '.$p.'facture SET fk_statut=1,type=0 WHERE rowid='.$id);
 ieSql("UPDATE ".$p."facturedet SET vat_src_code=NULL,info_bits=8 WHERE rowid=".$lineId);
 $missing=NorwayInvoiceEvidence::preview($db,$entity,$id,2,$admin);
 ieAssert($missing['basis']['mapping']['comparison']['rows'][0]['status']==='Unavailable','Missing evidence silently classified');
 ieAssert(NorwayInvoiceEvidence::history($db,$entity,$id,$admin)===$history,'Current changes rewrote stored copies');
 ieSql("UPDATE ".$p."norway_tax_evidence SET payload='{}' WHERE entity=".$entity." AND fk_document=".$id." AND revision=2");
 ieBlocked(fn()=>NorwayInvoiceEvidence::history($db,$entity,$id,$admin),'Corrupt payload accepted');
 ieSql("UPDATE ".$p."norway_tax_evidence SET payload='".$db->escape(json_encode($history[1]['data'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR))."' WHERE entity=".$entity." AND fk_document=".$id." AND revision=2");
 for ($i=0;$i<201;$i++) { ieClone('accounting_bookkeeping',$ledgerId,array()); }
 ieBlocked(fn()=>NorwayInvoiceEvidence::preview($db,$entity,$id,2,$admin),'Truncated ledger accepted');
 $large=str_repeat('x',900000);
 for ($i=3;$i<=8;$i++) { ieSql("INSERT INTO ".$p."norway_tax_evidence (entity,document_type,fk_document,revision,captured_at,fk_user,mapping_revision,payload,payload_sha256) VALUES (".$entity.",'customer_invoice',".$id.','.$i.",UTC_TIMESTAMP(),1,1,'".$large."','".hash('sha256',$large)."')"); }
 $capacity=false;
 try { NorwayInvoiceEvidence::history($db,$entity,$id,$admin); } catch (RuntimeException $error) { $capacity=$error->getMessage()==='Evidence history capacity'; }
 ieAssert($capacity,'Aggregate payload read was not bounded before decoding');
 print "PASS confirmed evidence: source and mapping changes, replay, append-only copies, old revision preservation, rollback after write, permissions/entity, NOK/status/type scope, missing evidence, corruption and truncation\n";
} finally {
 ieSql('DELETE FROM '.$p.'norway_tax_evidence WHERE entity='.$entity);
 ieSql('DELETE FROM '.$p.'accounting_bookkeeping WHERE entity='.$entity);
 if ($id) { ieSql('DELETE FROM '.$p.'facturedet WHERE fk_facture='.$id); ieSql('DELETE FROM '.$p.'facture WHERE rowid='.$id.' AND entity='.$entity); }
 ieSql('DELETE FROM '.$p.'const WHERE entity='.$entity);
 $conf->entity=$oldEntity; $mysoc=$oldSoc;
}
