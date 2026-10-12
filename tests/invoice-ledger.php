<?php
// SPDX-License-Identifier: GPL-3.0-or-later
if (PHP_SAPI!=='cli' || getenv('NORWAY_SYNTHETIC_TEST')!=='1' || getenv('DOLI_DB_NAME')!=='norway_test') { exit(1); }
require '/var/www/html/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/norway/class/NorwayInvoiceLedger.php';
function ilAssert($ok,$message) { if (!$ok) { throw new RuntimeException($message); } }
$p=$db->prefix(); $entity=(int)$conf->entity;
$r=$db->query("SELECT b.rowid,b.fk_doc,f.ref FROM ".$p."accounting_bookkeeping b JOIN ".$p."facture f ON f.rowid=b.fk_doc AND f.entity=b.entity WHERE b.entity=".$entity." AND b.doc_type='customer_invoice' ORDER BY b.rowid LIMIT 1");
$fixture=$r ? $db->fetch_object($r) : null; ilAssert((bool)$fixture,'Posted synthetic invoice missing');
$id=(int)$fixture->fk_doc; $rowId=(int)$fixture->rowid;
$report=NorwayInvoiceLedger::report($db,$entity,$id);
ilAssert(count($report['rows'])>=3 && !$report['truncated'],'Real posting trace incomplete');
foreach ($report['rows'] as $row) { ilAssert(in_array($row->trace,array('Linked','Aggregated'),true),'Real source trace failed'); }
$r=$db->query('SELECT rowid FROM '.$p.'facturedet WHERE fk_facture='.$id.' ORDER BY rowid LIMIT 1');
$line=$r ? $db->fetch_object($r) : null; ilAssert((bool)$line,'Synthetic line missing');
$db->begin();
try {
 $state=function($changes) use ($db,$p,$rowId,$entity,$id) {
  ilAssert((bool)$db->query('UPDATE '.$p.'accounting_bookkeeping SET '.$changes.' WHERE rowid='.$rowId),'Synthetic mutation failed');
  foreach (NorwayInvoiceLedger::report($db,$entity,$id)['rows'] as $row) { if ((int)$row->rowid===$rowId) { return $row->trace; } }
  return 'Excluded';
 };
 ilAssert($state('fk_docdet='.(int)$line->rowid)==='Linked','Existing line not linked');
 ilAssert($state('fk_docdet=0')==='Aggregated','Aggregated posting misclassified');
 ilAssert($state('fk_docdet=999999999')==='MissingLine','Orphan line accepted');
 ilAssert($state("doc_ref='SYN-DIFFERENT'")==='Reference','Changed reference accepted');
 ilAssert($state("doc_ref='".$db->escape($fixture->ref)."',code_journal='' ")==='Key','Missing journal ignored');
 ilAssert($state("code_journal='SYN-TRACE',piece_num=0")==='Key','Missing voucher ignored');
 ilAssert($state("piece_num=1,numero_compte=''")==='MissingAccount','Missing account ignored');
 ilAssert($state("numero_compte='1500',entity=".($entity+1))==='Excluded','Foreign posting leaked');
 ilAssert($state("entity=".$entity.",doc_type='supplier_invoice'")==='Excluded','Other source type leaked');
 $state("doc_type='customer_invoice',fk_docdet=0");
 $r=$db->query('SHOW COLUMNS FROM '.$p.'accounting_bookkeeping'); $columns=array();
 while ($column=$db->fetch_object($r)) { if ($column->Field!=='rowid') { $columns[]=$column->Field; } }
 $names=implode(',',$columns);
 for ($i=0;$i<201;$i++) { ilAssert((bool)$db->query('INSERT INTO '.$p.'accounting_bookkeeping ('.$names.') SELECT '.$names.' FROM '.$p.'accounting_bookkeeping WHERE rowid='.$rowId),'Truncation fixture failed'); }
 $r=NorwayInvoiceLedger::report($db,$entity,$id);
 ilAssert($r['truncated'] && count($r['rows'])===200,'Truncation silently treated as complete');
 ilAssert(!NorwayInvoiceLedger::report($db,$entity,999999999)['rows'],'Missing invoice invented rows');
 $blocked=false; try { NorwayInvoiceLedger::report($db,$entity+1,$id); } catch (InvalidArgumentException $error) { $blocked=true; }
 ilAssert($blocked,'Foreign active entity accepted');
} finally { $db->rollback(); }
ilAssert(serialize(NorwayInvoiceLedger::report($db,$entity,$id))===serialize($report),'Read-only report or fixture cleanup changed postings');
$failing=new class { public function prefix() { return 'llx_'; } public function query($sql) { return false; } };
$blocked=false; try { NorwayInvoiceLedger::report($failing,$entity,$id); } catch (RuntimeException $error) { $blocked=true; }
ilAssert($blocked,'Failed query treated as empty ledger');
print "PASS actual invoice postings, line/aggregate references, missing sources/keys/accounts, entity/type scope, truncation, query failure and read-only preservation\n";
