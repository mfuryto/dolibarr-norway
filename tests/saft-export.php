<?php
// SPDX-License-Identifier: GPL-3.0-or-later
if(PHP_SAPI!=='cli'||getenv('NORWAY_SYNTHETIC_TEST')!=='1'||getenv('DOLI_DB_NAME')!=='norway_test'||getenv('DOLI_COMPANY_NAME')!=='SYNTHETIC NORWAY TEST ONLY'){exit(1);}
require '/var/www/html/master.inc.php';
foreach(array('/core/lib/admin.lib.php','/core/lib/date.lib.php','/accountancy/class/bookkeeping.class.php','/accountancy/class/accountingjournal.class.php','/core/class/fiscalyear.class.php','/custom/norway/class/NorwaySaftExport.php','/custom/norway/class/NorwayChartProfile.php') as $f){require_once DOL_DOCUMENT_ROOT.$f;}
function sxAssert($ok,$label){if(!$ok){throw new RuntimeException($label);}}
function sxSql($sql){global $db;$r=$db->query($sql);sxAssert((bool)$r,$db->lasterror());return $r;}
function sxRows($sql){global $db;$out=array();$r=sxSql($sql);while($o=$db->fetch_object($r)){$out[]=$o;}return $out;}
function sxFailure($f,$prefix){$m='';try{$f();}catch(Throwable $e){$m=$e->getMessage();}sxAssert(strpos($m,$prefix)===0,'Expected '.$prefix.', got '.$m);}
sxAssert($user->fetch('','testadmin')>0,'Admin');$user->loadRights('',1);$oldEntity=$conf->entity;$oldGlobal=clone $conf->global;$oldSoc=clone $mysoc;$oldUser=clone $user;
$conf->entity=987646;$conf->global->ACCOUNTANCY_FISCAL_PERIOD_MODE='blockedonclosed';$conf->global->SOCIETE_FISCAL_MONTH_START=1;$conf->cache=array();$entity=(int)$conf->entity;$p=$db->prefix();$pieces=array();$run=bin2hex(random_bytes(4));
function sxVoucher($date,$amounts,$type='manual',$fk=0,$subs=array()){
 global $db,$user,$pieces,$run;
 $piece=(new BookKeeping($db))->getNextNumMvt();$pieces[]=$piece;
 foreach($amounts as $account=>$cents){$b=new BookKeeping($db);$b->piece_num=$piece;$b->doc_date=strtotime($date.' 12:00:00 UTC');$b->doc_type=$type;$b->fk_doc=$fk;$b->fk_docdet=0;$b->doc_ref='SYNTHETIC SAF-T '.$run;$b->numero_compte=(string)$account;$b->label_compte='SYNTHETIC '.$account;$b->label_operation='SYNTHETIC ONLY';$b->debit=max($cents,0)/100;$b->credit=max(-$cents,0)/100;$b->montant=abs($cents)/100;$b->sens=$cents<0?'C':'D';$b->code_journal='DIV';$b->journal_label='Synthetic';$b->subledger_account=$subs[$account]??'';sxAssert($b->createStd($user)>0,'Voucher '.$b->error);}
 return $piece;
}
try{
 $defaults=NorwaySaftMapping::defaults(2026);sxAssert(count($defaults)===357,'Reviewed ordinary account default count');
 $source=fopen('php://temp','w+');fwrite($source,NorwayChartProfile::csv());rewind($source);fgetcsv($source,0,';','"','');$labels=array();while(($r=fgetcsv($source,0,';','"',''))!==false){$labels[$r[0]]=$r[1];}fclose($source);
 foreach($defaults as $number=>$choice){sxAssert(($labels[$number]??null)===$choice['label'],'Default changed shipped meaning '.$number);}
 $preview=NorwayChartProfile::preview($db,$entity);NorwayChartProfile::install($db,$entity,NorwayChartProfile::fingerprint($entity,$preview),$user);$conf->global->CHARTOFACCOUNTS=NorwayChartProfile::preview($db,$entity)['target'];
 $mysoc->name='SYNTHETIC SAF-T COMPANY';$mysoc->idprof1='123456785';$mysoc->country_code='NO';$mysoc->address='Synthetic road';$mysoc->zip='0000';$mysoc->town='Synthetic';$mysoc->tva_intra='123456785MVA';$user->firstname='Synthetic';$user->lastname='Contact';
 sxVoucher('2025-01-01',array('1920'=>100000,'2050'=>-100000));
 sxFailure(fn()=>NorwaySaftExport::build($db,$entity,'2026-01-01','2026-12-31',$user),'NorwaySaftOpeningBalance');
 $years=array();foreach(array(2025,2026) as $y){sxSql('INSERT INTO '.$p."accounting_fiscalyear (entity,label,date_start,date_end,statut,datec) VALUES ($entity,'SYNTHETIC SAF-T $y','$y-01-01','$y-12-31',0,'2026-01-01')");$years[$y]=(int)$db->last_insert_id($p.'accounting_fiscalyear');}
 sxSql('INSERT INTO '.$p."accounting_journal (entity,code,label,nature,active) VALUES ($entity,'ANSX','Synthetic opening',9,1)");$journal=(int)$db->last_insert_id($p.'accounting_journal');
 dolibarr_set_const($db,'ACCOUNTING_CLOSURE_DEFAULT_JOURNAL',(string)$journal,'chaine',0,'',$entity);$conf->global->ACCOUNTING_CLOSURE_DEFAULT_JOURNAL=(string)$journal;
 $native=new BookKeeping($db);sxAssert($native->closeFiscalPeriod($years[2025],$years[2026],false,true)>0,'Native annual carry-forward '.implode(' ',$native->errors));
 $piece=sxVoucher('2026-02-01',array('7770'=>1015,'1920'=>-1015));
 $before=sxRows('SELECT * FROM '.$p.'accounting_bookkeeping WHERE entity='.$entity.' ORDER BY rowid');
 $e=NorwaySaftExport::build($db,$entity,'2026-01-01','2026-12-31',$user);sxAssert($e['entries']===1 && $e['lines']===2 && $e['debit']==='10.15' && $e['credit']==='10.15','Native carry-forward was counted twice');
 $conf->global->SOCIETE_FISCAL_MONTH_START=7;sxFailure(fn()=>NorwaySaftExport::build($db,$entity,'2026-01-01','2026-12-31',$user),'NorwaySaftPeriod');$conf->global->SOCIETE_FISCAL_MONTH_START=1;
 $d=NorwaySaftReference::validate($e['xml']);$x=new DOMXPath($d);$x->registerNamespace('s',NorwaySaftExport::NS);
 sxAssert($x->evaluate('string(//s:Account[s:AccountID="1920"]/s:OpeningDebitBalance)')==='1000.00','Opening balance');
 sxAssert($x->evaluate('string(//s:Account[s:AccountID="1920"]/s:ClosingDebitBalance)')==='989.85','Closing balance');
 sxAssert(serialize($before)===serialize(sxRows('SELECT * FROM '.$p.'accounting_bookkeeping WHERE entity='.$entity.' ORDER BY rowid')),'Export changed source');
 sxSql('UPDATE '.$p."accounting_bookkeeping SET date_export='2026-03-01' WHERE entity=$entity");$again=NorwaySaftExport::build($db,$entity,'2026-01-01','2026-12-31',$user);sxAssert($again['xml']===$e['xml'],'Repeated export lost previously exported rows');
 sxVoucher('2026-03-01',array('9998'=>100,'1920'=>-100));sxVoucher('2026-03-02',array('9998'=>-100,'1920'=>100));
 $empty=NorwaySaftExport::build($db,$entity,'2026-06-01','2026-06-30',$user);sxAssert($empty['entries']===0 && strpos($empty['xml'],'<AccountID>9998</AccountID>')===false,'Unused zero-balance historical account forced mapping');
 $id=(int)sxRows('SELECT rowid FROM '.$p.'accounting_bookkeeping WHERE entity='.$entity.' AND piece_num='.$piece.' ORDER BY rowid')[0]->rowid;
 sxSql('UPDATE '.$p.'accounting_bookkeeping SET debit=debit+0.001 WHERE rowid='.$id);
 sxFailure(fn()=>NorwaySaftExport::build($db,$entity,'2026-02-02','2026-02-28',$user),'NorwaySaftPrecision');
 sxSql('UPDATE '.$p.'accounting_bookkeeping SET debit=debit-0.001 WHERE rowid='.$id);
 $bank=sxRows('SELECT rowid FROM '.$p.'accounting_account WHERE entity='.$entity." AND account_number='1920'")[0];
 sxSql('UPDATE '.$p."accounting_account SET label='Synthetic renamed bank' WHERE rowid=".(int)$bank->rowid);
 sxFailure(fn()=>NorwaySaftExport::build($db,$entity,'2026-01-01','2026-02-28',$user),'NorwaySaftAccountMapping:1920');
 NorwaySaftMapping::save($db,$entity,2026,$bank->rowid,'balanseverdiForOmloepsmiddel/1920',$user);
 sxAssert(NorwaySaftExport::build($db,$entity,'2026-02-01','2026-02-28',$user)['entries']===1,'Explicit renamed-account mapping');
 sxFailure(fn()=>NorwaySaftExport::build($db,$entity,'2025-01-01','2025-12-31',$user),'NorwaySaftAccountMapping:1920');
 $mapBefore=serialize(sxRows('SELECT * FROM '.$p.'const WHERE entity='.$entity.' ORDER BY name'));
 sxFailure(fn()=>NorwaySaftMapping::save($db,$entity,2026,$bank->rowid,'kortsiktigGjeld/2700',$user),'NorwaySaftAccountMapping');
 sxAssert($mapBefore===serialize(sxRows('SELECT * FROM '.$p.'const WHERE entity='.$entity.' ORDER BY name')),'Invalid mapping changed settings');
 sxFailure(fn()=>NorwaySaftExport::build($db,$entity,'2027-01-01','2027-12-31',$user),'NorwaySaftGroupingYear');
 $restricted=new class {public $socid=0;public function hasRight($module,$group,$right=''){return $right==='lire';}};
 sxFailure(fn()=>NorwaySaftExport::build($db,$entity,'2026-01-01','2026-12-31',$restricted),'NorwaySaftAccess');
 sxFailure(fn()=>NorwaySaftMapping::save($db,$entity,2026,$bank->rowid,'balanseverdiForOmloepsmiddel/1920',$restricted),'NorwaySaftAccess');
 sxFailure(fn()=>NorwaySaftExport::build($db,$entity+1,'2026-01-01','2026-12-31',$user),'NorwaySaftAccess');
 $external=clone $user;$external->socid=1;sxFailure(fn()=>NorwaySaftExport::build($db,$entity,'2026-01-01','2026-12-31',$external),'NorwaySaftAccess');
 echo "PASS SAF-T official XSD, native opening replacement, independent 1000/989.85 balances, whole-period/repeated export, unchanged source, explicit renamed-account mapping, unsupported year and access\n";
}finally{
 $db->rollback();foreach(array('accounting_bookkeeping','accounting_account','accounting_journal','accounting_fiscalyear','const') as $table){sxSql('DELETE FROM '.$p.$table.' WHERE entity='.$entity);}
 $conf->entity=$oldEntity;$conf->global=$oldGlobal;$mysoc=$oldSoc;$user=$oldUser;
}
