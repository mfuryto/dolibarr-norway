<?php
// SPDX-License-Identifier: GPL-3.0-or-later
if (PHP_SAPI!=='cli' || getenv('NORWAY_SYNTHETIC_TEST')!=='1' || getenv('DOLI_DB_NAME')!=='norway_test') { exit(1); }
require '/var/www/html/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/norway/class/NorwayInvoiceReconciliation.php';
function irAssert($ok,$message) { if (!$ok) { throw new RuntimeException($message); } }
foreach (array('1e2','NaN','100000000.00000001',1.5,'1.000000001') as $bad) { try { NorwayInvoiceReconciliation::amount($bad);throw new LogicException('Invalid amount accepted'); }catch (InvalidArgumentException $expected) {} }
irAssert(NorwayInvoiceReconciliation::display(NorwayInvoiceReconciliation::amount('-1234.56789012'))==='-1234.56789012','Exact decimals lost');
$h=(object)array('type'=>0,'fk_statut'=>1,'datef'=>'2026-01-01','multicurrency_code'=>'NOK','total_ht'=>'1000','total_tva'=>'250','total_ttc'=>'1250');
$l=array('truncated'=>false,'rows'=>array((object)array('debit'=>'1250','credit'=>'0','trace'=>'Aggregated','doc_date'=>'2026-01-01','code_journal'=>'SYN','piece_num'=>1),(object)array('debit'=>'0','credit'=>'1250','trace'=>'Aggregated','doc_date'=>'2026-01-01','code_journal'=>'SYN','piece_num'=>1)));
irAssert(NorwayInvoiceReconciliation::evaluate($h,$l)['status']==='Match','Balanced gross failed');
$l['rows'][1]->credit='1249.99999999';irAssert(NorwayInvoiceReconciliation::evaluate($h,$l)['status']==='Different','Tiny ledger imbalance hidden');
$l['rows'][0]->debit='1249.99';$l['rows'][1]->credit='1249.99';irAssert(NorwayInvoiceReconciliation::evaluate($h,$l)['status']==='Match','One ore tolerance failed');
$l['rows'][0]->debit='1249.98';$l['rows'][1]->credit='1249.98';irAssert(NorwayInvoiceReconciliation::evaluate($h,$l)['status']==='Different','Wrong gross accepted');
$l['rows'][1]->piece_num=2;irAssert(NorwayInvoiceReconciliation::evaluate($h,$l)['status']==='Review','Split voucher accepted');
$l['rows'][1]->piece_num=1;$l['rows'][1]->trace='MissingLine';irAssert(NorwayInvoiceReconciliation::evaluate($h,$l)['status']==='Review','Missing source accepted');
$l['truncated']=true;try { NorwayInvoiceReconciliation::evaluate($h,$l);throw new LogicException('Truncation accepted'); }catch (RuntimeException $expected) {}
irAssert(NorwayInvoiceReconciliation::evaluate($h,array('truncated'=>false,'rows'=>array()))['status']==='Empty','Missing postings approved');
$h->type=2;$h->total_ht='-1000';$h->total_tva='-250';$h->total_ttc='-1250';$l['truncated']=false;$l['rows'][1]->trace='Linked';$l['rows'][0]->debit='1250';$l['rows'][1]->credit='1250';irAssert(NorwayInvoiceReconciliation::evaluate($h,$l)['status']==='Match','Credit note signs failed');
$p=$db->prefix();$entity=(int)$conf->entity;
$r=$db->query("SELECT DISTINCT f.rowid,f.type FROM ".$p."facture f JOIN ".$p."accounting_bookkeeping b ON b.fk_doc=f.rowid AND b.entity=f.entity AND b.doc_type='customer_invoice' WHERE f.entity=".$entity." AND f.type IN (0,2) AND f.fk_statut IN (1,2) AND f.multicurrency_code='NOK'");
$count=0;$credit=false;while ($f=$db->fetch_object($r)) { $report=NorwayInvoiceReconciliation::report($db,$entity,(int)$f->rowid);irAssert($report['status']==='Match','Actual core posting gross mismatch');$count++;$credit=$credit || (int)$f->type===2; }
irAssert($count>=3 && $credit,'Actual ordinary/credit posting fixtures missing');
try { NorwayInvoiceReconciliation::report($db,$entity+1,1);throw new LogicException('Entity leak'); }catch (InvalidArgumentException $expected) {}
print "PASS exact gross reconciliation, ordinary/credit core postings, one-ore tolerance, imbalance, source/scope protections and truncation\n";
