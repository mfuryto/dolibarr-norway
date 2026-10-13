<?php
// SPDX-License-Identifier: GPL-3.0-or-later
if(PHP_SAPI!=='cli' || getenv('NORWAY_SYNTHETIC_TEST')!=='1' || getenv('DOLI_DB_NAME')!=='norway_test'){exit(1);}
require '/var/www/html/master.inc.php';require_once DOL_DOCUMENT_ROOT.'/accountancy/class/bookkeeping.class.php';require_once DOL_DOCUMENT_ROOT.'/custom/norway/class/NorwayPostingEvidence.php';
function peAssert($ok,$message){if(!$ok){throw new RuntimeException($message);}}
function peSql($sql){global $db;$r=$db->query($sql);peAssert($r!==false,'Synthetic observation query: '.$db->lasterror());return $r;}
peAssert($user->fetch('','testadmin')>0,'Admin missing');$user->loadRights();$p=$db->prefix();$entity=(int)$conf->entity;
$r=peSql("SELECT DISTINCT f.rowid,f.ref FROM ".$p."facture f JOIN ".$p."accounting_bookkeeping b ON b.fk_doc=f.rowid AND b.entity=f.entity AND b.doc_type='customer_invoice' WHERE f.entity=".$entity." AND f.type=0 AND f.fk_statut IN (1,2) AND f.multicurrency_code='NOK' AND f.total_tva<>0 ORDER BY f.rowid LIMIT 1");$invoice=$db->fetch_object($r);peAssert((bool)$invoice,'Core invoice missing');
function peObject($connection,$invoice,$entity){$o=new BookKeeping($connection);$o->entity=$entity;$o->doc_date=strtotime('2026-01-01');$o->doc_type='customer_invoice';$o->doc_ref=$invoice->ref;$o->fk_doc=(int)$invoice->rowid;$o->fk_docdet=0;$o->numero_compte='1500';$o->label_compte='Synthetic observation';$o->label_operation='Synthetic observation test';$o->debit=1;$o->credit=0;$o->montant=1;$o->sens='D';$o->code_journal='SYN-OBS';$o->journal_label='Synthetic';$o->piece_num=987641;$o->ref='SYN-OBS';return $o;}
$objects=array();
try{
 // Native create joins the outer transaction. Both entry and observation must roll back together.
 $db->begin();$rolled=peObject($db,$invoice,$entity);peAssert($rolled->createStd($user)>0,'Native create failed: '.$rolled->errorsToString());$rolledId=(int)$rolled->id;
 $r=peSql("SELECT COUNT(*) AS n FROM ".$p."norway_tax_evidence WHERE entity=".$entity." AND document_type='".NorwayPostingEvidence::TYPE."' AND fk_document=".$rolledId);peAssert((int)$db->fetch_object($r)->n===1,'Native trigger did not preserve source');$db->rollback();
 $r=peSql('SELECT rowid FROM '.$p.'accounting_bookkeeping WHERE rowid='.$rolledId);peAssert(!$db->fetch_object($r),'Native rollback retained entry');
 $r=peSql("SELECT rowid FROM ".$p."norway_tax_evidence WHERE document_type='".NorwayPostingEvidence::TYPE."' AND entity=".$entity.' AND fk_document='.$rolledId);peAssert(!$db->fetch_object($r),'Native rollback retained observation');
 $o=peObject($db,$invoice,$entity);peAssert($o->createStd($user)>0,'Committed native event failed');$objects[]=(int)$o->id;
 $report=NorwayPostingEvidence::report($db,$entity,(int)$invoice->rowid,$user);$found=array_values(array_filter($report,fn($r)=>$r['id']===(int)$o->id));peAssert(count($found)===1 && $found[0]['status']==='Same','Saved event did not match current sources');$saved=$found[0]['data'];
 $db->begin();peAssert(NorwayPostingEvidence::capture($db,$entity,$o,$user)===0,'Replay duplicated event');$db->commit();
 try{NorwayPostingEvidence::capture($db,$entity,$o,$user);throw new LogicException('Out-of-transaction capture accepted');}catch(RuntimeException $expected){}
 $r=peSql('SELECT rowid,total_tva FROM '.$p.'facturedet WHERE fk_facture='.(int)$invoice->rowid.' ORDER BY rowid LIMIT 1');$line=$db->fetch_object($r);
 try{
  peSql('UPDATE '.$p.'facturedet SET total_tva=total_tva+1 WHERE rowid='.(int)$line->rowid);
  $changed=NorwayPostingEvidence::report($db,$entity,(int)$invoice->rowid,$user);$entry=array_values(array_filter($changed,fn($r)=>$r['id']===(int)$o->id))[0];peAssert($entry['status']==='Changed' && $entry['data']===$saved,'Source mutation overwrote observed data');
 }finally{peSql("UPDATE ".$p."facturedet SET total_tva='".$db->escape($line->total_tva)."' WHERE rowid=".(int)$line->rowid);}
 peSql('DELETE FROM '.$p.'accounting_bookkeeping WHERE rowid='.(int)$o->id);$removed=NorwayPostingEvidence::report($db,$entity,(int)$invoice->rowid,$user);$entry=array_values(array_filter($removed,fn($r)=>$r['id']===(int)$o->id))[0];peAssert($entry['status']==='Removed' && $entry['data']===$saved,'Deleted posting lost its observation');
 $reader=clone $user;$reader->admin=0;try{NorwayPostingEvidence::report($db,$entity,(int)$invoice->rowid,$reader);throw new LogicException('Nonadmin accepted');}catch(InvalidArgumentException $expected){}
 try{NorwayPostingEvidence::report($db,$entity+1,(int)$invoice->rowid,$user);throw new LogicException('Foreign entity accepted');}catch(InvalidArgumentException $expected){}
 peSql("CREATE TRIGGER ".$p."syn_posting_failure BEFORE INSERT ON ".$p."norway_tax_evidence FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic observation failure'");
 try {
  $failed=peObject($db,$invoice,$entity);$result=$failed->createStd($user);if((int)$failed->id>0){$objects[]=(int)$failed->id;}
  peAssert($result<0,'Storage failure did not stop native posting');
  if((int)$failed->id>0){$r=peSql('SELECT rowid FROM '.$p.'accounting_bookkeeping WHERE rowid='.(int)$failed->id);peAssert(!$db->fetch_object($r),'Failed native posting survived rollback');}
 }finally{peSql('DROP TRIGGER IF EXISTS '.$p.'syn_posting_failure');}
 // Corrupted archived evidence is rejected even after its posting was removed.
 peSql("UPDATE ".$p."norway_tax_evidence SET payload_sha256=REPEAT('0',64) WHERE entity=".$entity." AND document_type='".NorwayPostingEvidence::TYPE."' AND fk_document=".(int)$o->id);
 try{NorwayPostingEvidence::report($db,$entity,(int)$invoice->rowid,$user);throw new LogicException('Corruption accepted');}catch(RuntimeException $expected){}
}finally{
 $db->rollback();foreach($objects as $id){peSql('DELETE FROM '.$p.'accounting_bookkeeping WHERE rowid='.$id);peSql("DELETE FROM ".$p."norway_tax_evidence WHERE entity=".$entity." AND document_type='".NorwayPostingEvidence::TYPE."' AND fk_document=".$id);}
}
print "PASS native posting/source observation atomicity, replay, retained sources, removed entries, corruption, storage failure, access and entity guards\n";
