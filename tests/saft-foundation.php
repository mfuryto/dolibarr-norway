<?php
// SPDX-License-Identifier: GPL-3.0-or-later
if(PHP_SAPI!=='cli'||getenv('NORWAY_SYNTHETIC_TEST')!=='1'||getenv('DOLI_DB_NAME')!=='norway_test'||getenv('DOLI_COMPANY_NAME')!=='SYNTHETIC NORWAY TEST ONLY'){exit(1);}
require '/var/www/html/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/accountancy/class/bookkeeping.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/norway/class/NorwaySaftReference.php';
require_once DOL_DOCUMENT_ROOT.'/custom/norway/class/NorwaySaftLedger.php';
function sfAssert($value,$label){if(!$value){throw new RuntimeException($label);}}
function sfSql($sql){global $db;$r=$db->query($sql);sfAssert((bool)$r,$db->lasterror());return $r;}
function sfRows($sql){global $db;$out=array();$r=sfSql($sql);while($o=$db->fetch_object($r)){$out[]=$o;}return $out;}
function sfFailure($callback,$expected){$message='';try{$callback();}catch(RuntimeException $error){$message=$error->getMessage();}sfAssert($message===$expected,'Expected '.$expected.', got '.$message);}
sfAssert($user->fetch('','testadmin')>0,'Admin');$user->loadRights('',1);$p=$db->prefix();$entity=(int)$conf->entity;$pieces=array();$run=bin2hex(random_bytes(4));
$start='2049-01-10';$end='2049-01-31';sfAssert(!sfRows('SELECT rowid FROM '.$p."accounting_bookkeeping WHERE entity=$entity AND doc_date BETWEEN '2049-01-01' AND '2049-12-31'"),'Fresh synthetic year');
function sfVoucher($date,$amounts){
 global $db,$user,$p,$entity,$pieces,$run;
 $native=new BookKeeping($db);$piece=$native->getNextNumMvt();$pieces[]=$piece;
 foreach($amounts as $account=>$value){$b=new BookKeeping($db);$b->piece_num=$piece;$b->doc_date=strtotime($date.' 12:00:00 UTC');$b->doc_type='manual';$b->doc_ref='SYNTHETIC SAF-T '.$run;$b->fk_doc=0;$b->fk_docdet=0;$b->numero_compte=(string)$account;$b->label_compte='SYNTHETIC ACCOUNT '.$account;$b->label_operation='SYNTHETIC SAF-T ONLY';$b->debit=max($value,0)/100;$b->credit=max(-$value,0)/100;$b->montant=abs($value)/100;$b->sens=$value<0?'C':'D';$b->code_journal='DIV';$b->journal_label='Synthetic SAF-T';sfAssert($b->createStd($user)>0,'Native voucher '.$b->error);}
 return $piece;
}
try {
 $groups=NorwaySaftReference::groups(2026);sfAssert(count($groups)===532,'Official grouping count');
 sfAssert(isset($groups['kortsiktigGjeld/2740']) && !isset($groups['kortsiktigGjeld/2700']),'VAT grouping differs from local account');
 $mapping=array('2700'=>array('category'=>'kortsiktigGjeld','code'=>'2740'));
 sfAssert(NorwaySaftReference::mapping(2026,$mapping,array('2700'))[2700]['code']==='2740','Explicit tax account grouping');
 sfFailure(fn()=>NorwaySaftReference::mapping(2026,array(),array('2700')),'NorwaySaftAccountMapping:2700');
 sfFailure(fn()=>NorwaySaftReference::mapping(2026,array('2700'=>array('category'=>'kortsiktigGjeld','code'=>'2700')),array('2700')),'NorwaySaftAccountMapping:2700');
 sfFailure(fn()=>NorwaySaftReference::groups(2027),'NorwaySaftGroupingYear');
 sfAssert(isset(NorwaySaftReference::taxCodes()['86']),'Official tax code');
 sfFailure(fn()=>NorwaySaftReference::validate('<!DOCTYPE x [<!ENTITY a SYSTEM "file:///etc/passwd">]><x>&a;</x>'),'NorwaySaftXml');
 sfFailure(fn()=>NorwaySaftReference::validate('<AuditFile/>'),'NorwaySaftXml');
 $xml='<?xml version="1.0" encoding="UTF-8"?><AuditFile xmlns="urn:StandardAuditFile-Taxation-Financial:NO"><Header><AuditFileVersion>1.40</AuditFileVersion><AuditFileCountry>NO</AuditFileCountry><AuditFileDateCreated>2026-10-18</AuditFileDateCreated><SoftwareCompanyName>Synthetic Test Only</SoftwareCompanyName><SoftwareID>Synthetic SAF-T fixture</SoftwareID><SoftwareVersion>0</SoftwareVersion><Company><RegistrationNumber>123456785</RegistrationNumber><Name>SYNTHETIC ONLY</Name><Contact><ContactPerson><FirstName>Synthetic</FirstName><LastName>Contact</LastName></ContactPerson></Contact></Company><DefaultCurrencyCode>NOK</DefaultCurrencyCode><SelectionCriteria><SelectionStartDate>2026-01-01</SelectionStartDate><SelectionEndDate>2026-01-31</SelectionEndDate></SelectionCriteria><TaxAccountingBasis>A</TaxAccountingBasis></Header><MasterFiles/><GeneralLedgerEntries><NumberOfEntries>0</NumberOfEntries><TotalDebit>0.00</TotalDebit><TotalCredit>0.00</TotalCredit></GeneralLedgerEntries></AuditFile>';
 sfAssert(NorwaySaftReference::validate($xml) instanceof DOMDocument,'Valid official-schema synthetic document');
 sfFailure(fn()=>NorwaySaftReference::validate(str_replace('<TaxAccountingBasis>A','<TaxAccountingBasis>B',$xml)),'NorwaySaftXml');
 sfFailure(fn()=>NorwaySaftReference::validate(str_replace('<AuditFileVersion>1.40','<AuditFileVersion>1.30',$xml)),'NorwaySaftXml');

 $opening=sfVoucher('2049-01-01',array('1920'=>50000,'2050'=>-50000));
 $fee=sfVoucher('2049-01-15',array('7770'=>1015,'1920'=>-1015));
 $later=sfVoucher('2049-02-01',array('7770'=>200,'1920'=>-200));
 $before=sfRows('SELECT * FROM '.$p.'accounting_bookkeeping WHERE entity='.$entity.' AND piece_num IN ('.implode(',',$pieces).') ORDER BY rowid');
 $snapshot=NorwaySaftLedger::read($db,$entity,$start,$end,$user);
 sfAssert(count($snapshot['vouchers'])===1 && count($snapshot['lines'])===2 && $snapshot['total_debit']===1015000000 && $snapshot['total_credit']===1015000000,'Complete period voucher');
 // Other earlier integration fixtures may exist, so compare this fixture's movement directly.
 sfAssert($snapshot['accounts'][7770]['movement']===1015000000,'Period movement');
 sfAssert($snapshot['accounts'][1920]['closing']===$snapshot['accounts'][1920]['opening']-1015000000,'Opening + movement = closing');
 sfAssert(json_encode($before)===json_encode(sfRows('SELECT * FROM '.$p.'accounting_bookkeeping WHERE entity='.$entity.' AND piece_num IN ('.implode(',',$pieces).') ORDER BY rowid')),'Snapshot wrote source or export flags');
 sfSql("UPDATE ".$p."accounting_bookkeeping SET date_export='2049-02-20' WHERE entity=$entity AND piece_num=$fee");
 $again=NorwaySaftLedger::read($db,$entity,$start,$end,$user);sfAssert(count($again['lines'])===2 && $again['total_debit']===$snapshot['total_debit'],'Previously exported entries remain included');
 $id=(int)$again['vouchers'][$fee]['lines'][0];sfSql("UPDATE ".$p."accounting_bookkeeping SET doc_date='2049-02-01' WHERE rowid=$id");
 sfFailure(fn()=>NorwaySaftLedger::read($db,$entity,$start,$end,$user),'NorwaySaftVoucher');
 sfSql("UPDATE ".$p."accounting_bookkeeping SET doc_date='2049-01-15' WHERE rowid=$id");
 sfSql("UPDATE ".$p."accounting_bookkeeping SET debit=debit+1 WHERE rowid=$id");
 sfFailure(fn()=>NorwaySaftLedger::read($db,$entity,$start,$end,$user),'NorwaySaftVoucher');
 sfSql("UPDATE ".$p."accounting_bookkeeping SET debit=debit-1 WHERE rowid=$id");
 sfSql("UPDATE ".$p."accounting_bookkeeping SET doc_type='closure' WHERE entity=$entity AND piece_num=$opening");
 sfFailure(fn()=>NorwaySaftLedger::read($db,$entity,$start,$end,$user),'NorwaySaftOpeningBalance');
 sfSql("UPDATE ".$p."accounting_bookkeeping SET doc_type='manual' WHERE entity=$entity AND piece_num=$opening");
 sfFailure(fn()=>NorwaySaftLedger::read($db,$entity+1,$start,$end,$user),'NorwaySaftAccess');
 sfFailure(fn()=>NorwaySaftLedger::read($db,$entity,'2049-02-30',$end,$user),'NorwaySaftPeriod');
 $external=clone $user;$external->socid=999999;
 sfFailure(fn()=>NorwaySaftLedger::read($db,$entity,$start,$end,$external),'NorwaySaftAccess');
 echo "PASS pinned SAF-T 1.40 references, explicit mandatory grouping, native full-period read, source preservation, re-export inclusion, balances, boundary completeness, entity and access\n";
}finally{
 if($pieces){sfSql('DELETE FROM '.$p.'accounting_bookkeeping WHERE entity='.$entity.' AND piece_num IN ('.implode(',',$pieces).')');}
}
