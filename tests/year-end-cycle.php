<?php
// SPDX-License-Identifier: GPL-3.0-or-later
if(PHP_SAPI!=='cli'||getenv('NORWAY_SYNTHETIC_TEST')!=='1'||getenv('DOLI_DB_NAME')!=='norway_test'){exit(1);}
require '/var/www/html/master.inc.php';
foreach(array('/core/lib/admin.lib.php','/core/lib/date.lib.php','/core/class/fiscalyear.class.php','/accountancy/class/bookkeeping.class.php','/accountancy/class/accountingaccount.class.php','/accountancy/class/accountingjournal.class.php','/custom/norway/class/NorwayChartProfile.php','/custom/norway/class/NorwayChartGroups.php','/custom/norway/class/NorwayYearEnd.php','/custom/norway/class/NorwayAnnualReport.php','/custom/norway/class/NorwayAnnualUi.php') as $file){require_once DOL_DOCUMENT_ROOT.$file;}
function ycAssert($ok,$message){if(!$ok){throw new RuntimeException($message);}}
function ycRows($sql){global $db;$r=$db->query($sql);ycAssert($r!==false,$db->lasterror());$rows=array();if($r===true){return $rows;}while($o=$db->fetch_object($r)){$rows[]=$o;}return $rows;}
function ycPost($lines,$month,$day,$year,$label,$expect=true){global $db,$user,$conf;$piece=(new BookKeeping($db))->getNextNumMvt();$db->begin();foreach($lines as [$number,$amount,$aux]){$b=new BookKeeping($db);$b->entity=$conf->entity;$b->piece_num=$piece;$b->doc_date=dol_mktime(12,0,0,$month,$day,$year);$b->doc_type='manual';$b->doc_ref=$label;$b->fk_doc=0;$b->fk_docdet=0;$b->numero_compte=$number;$b->subledger_account=$aux;$b->subledger_label=$aux;$b->label_compte='Synthetic '.$number;$b->label_operation=$label;$b->debit=max($amount,0);$b->credit=max(-$amount,0);$b->montant=abs($amount);$b->sens=$amount<0?'C':'D';$b->code_journal='YCDIV';$b->journal_label='Synthetic closing';$r=$b->createStd($user);if(!$expect){$db->rollback();return $r;}ycAssert($r>0,'Native voucher '.$label.': '.$b->error);} $db->commit();return $piece;}
function ycVoucher($pairs,$month,$day,$year,$label){$lines=array();foreach($pairs as $number=>$amount){$lines[]=array((string)$number,$amount,'');}return ycPost($lines,$month,$day,$year,$label);}
function ycResult($year){global $db;$b=new BookKeeping($db);$r=$b->accountingResult(dol_mktime(0,0,0,1,1,$year),dol_mktime(23,59,59,12,31,$year));$db->rollback();return round((float)$r,2);}
function ycBalances($where){global $p,$conf;$out=array();foreach(ycRows("SELECT numero_compte,SUM(debit-credit) amount FROM {$p}accounting_bookkeeping WHERE entity=".(int)$conf->entity.' AND '.$where.' GROUP BY numero_compte HAVING ABS(SUM(debit-credit))>0.00001') as $row){$out[$row->numero_compte]=round((float)$row->amount,2);}ksort($out);return $out;}
ycAssert($user->fetch('','testadmin')>0,'Admin');$user->loadRights('',1);$originalEntity=$conf->entity;$originalGlobal=clone $conf->global;$conf->entity=987647;$entity=$conf->entity;$p=$db->prefix();
try{
 $preview=NorwayChartProfile::preview($db,$entity);ycAssert(NorwayChartProfile::install($db,$entity,NorwayChartProfile::fingerprint($entity,$preview),$user)===745,'Chart');$conf->global->CHARTOFACCOUNTS=(string)NorwayChartProfile::preview($db,$entity)['target'];
 foreach(array('ACCOUNTANCY_FISCAL_PERIOD_MODE'=>'blockedonclosed','ACCOUNTING_RESULT_PROFIT'=>'2050','ACCOUNTING_RESULT_LOSS'=>'2050') as $name=>$value){dolibarr_set_const($db,$name,$value,'chaine',0,'',$entity);$conf->global->$name=$value;}$conf->cache=array();
 ycRows("INSERT INTO {$p}accounting_journal (entity,code,label,nature,active) VALUES ($entity,'YCAN','Synthetic opening',9,1)");$journal=(int)$db->last_insert_id($p.'accounting_journal');dolibarr_set_const($db,'ACCOUNTING_CLOSURE_DEFAULT_JOURNAL',(string)$journal,'chaine',0,'',$entity);
 foreach(array(2025,2026,2027) as $year){$y=new FiscalYear($db);$y->label='SYNTHETIC YEAR '.$year;$y->date_start=dol_mktime(0,0,0,1,1,$year);$y->date_end=dol_mktime(23,59,59,12,31,$year);$y->status=0;ycAssert($y->create($user)>0,'Year');$years[$year]=(int)$y->id;}
 // Core can store a manual account outside the selected chart. Closure must not omit it.
 foreach(array('unknown','unbalanced') as $bad){
  ycVoucher($bad==='unknown'?array('7997'=>1,'1920'=>-1):array('1920'=>1),1,1,2025,'SYNTHETIC INVALID '.$bad);
  $before=ycRows("SELECT * FROM {$p}accounting_bookkeeping WHERE entity=$entity ORDER BY rowid");
  try{NorwayYearEnd::guard($db,$entity,$years[2025],$years[2026],true,true);throw new LogicException('Invalid source accepted');}catch(RuntimeException $expectedError){}finally{ycRows("SELECT RELEASE_LOCK('norway-year-end-$entity')");}
  ycAssert($before==ycRows("SELECT * FROM {$p}accounting_bookkeeping WHERE entity=$entity ORDER BY rowid"),'Guard rewrote invalid source');
  ycRows("DELETE FROM {$p}accounting_bookkeeping WHERE entity=$entity AND doc_ref='SYNTHETIC INVALID $bad'");
 }
 ycVoucher(array('1920'=>200000,'2050'=>-200000),1,1,2025,'SYNTHETIC OPENING');
 ycVoucher(array('1920'=>100000,'3000'=>-100000),6,1,2025,'SYNTHETIC NET SALES');
 ycVoucher(array('6800'=>40000,'1920'=>-40000),6,2,2025,'SYNTHETIC COST');
 ycVoucher(array('1280'=>50000,'1920'=>-50000),6,3,2025,'SYNTHETIC OFFICE EQUIPMENT');
 ycVoucher(array('6300'=>12000,'1920'=>-12000),10,1,2025,'SYNTHETIC RENT OCT-SEP');
 ycVoucher(array('4300'=>5000,'1920'=>-5000),12,1,2025,'SYNTHETIC GOODS');
 ycVoucher(array('7799'=>750,'1920'=>-750),12,2,2025,'SYNTHETIC NONDEDUCTIBLE PENALTY');
 ycVoucher(array('2070'=>17000,'2060'=>3000,'1920'=>-20000),12,3,2025,'SYNTHETIC PERSONAL WITHDRAWALS');
 ycPost(array(array('1500',2500,'SYN-C'),array('3000',-2000,''),array('2700',-500,'')),12,10,2025,'SYNTHETIC OPEN RECEIVABLE');
 ycPost(array(array('6800',1000,''),array('2710',250,''),array('2400',-1250,'SYN-S')),12,11,2025,'SYNTHETIC OPEN PAYABLE');
 // Ordinary year-end journals, based on supporting schedules; never per-invoice reclassification.
 ycVoucher(array('1700'=>9000,'6300'=>-9000),12,31,2025,'SYNTHETIC PREPAID RENT');
 ycVoucher(array('1460'=>5000,'4390'=>-5000),12,31,2025,'SYNTHETIC COUNTED STOCK');
 ycVoucher(array('6030'=>15000,'1280'=>-15000),12,31,2025,'SYNTHETIC TAX BASIS DEPRECIATION');
 ycVoucher(array('2050'=>20000,'2060'=>-3000,'2070'=>-17000),12,31,2025,'SYNTHETIC OWNER CLEARING');
 ycAssert(ycResult(2025)===42250.0,'Independent profit 42250 actual='.ycResult(2025).' '.json_encode(ycBalances("YEAR(doc_date)=2025")));
 $preDisposal=ycBalances("YEAR(doc_date)=2025");ycAssert($preDisposal['1920']===172250.0 && !isset($preDisposal['2070']) && !isset($preDisposal['2060']),'Bank and owner accounts');
 $annualBefore=NorwayAnnualReport::build($db,$entity,2025,$user);
 ycAssert(!$annualBefore['issues'],'Annual basis issues: '.json_encode($annualBefore['issues']));
 ycAssert($annualBefore['profit']===4225000 && $annualBefore['tax_basis_before_other_adjustments']===4300000 && $annualBefore['advance_tax']===1700000 && $annualBefore['owner_cash']===2000000,'Independent report result/tax and actual owner payments');
 ycAssert($annualBefore['fields']['salgsinntekt/3000']['value']===10200000 && $annualBefore['fields']['annenDriftskostnad/6000']['value']===1500000 && $annualBefore['fields']['annenDriftskostnad/6300']['value']===300000,'Exact ENK income, depreciation and periodized rent fields');
 ycAssert($annualBefore['assets']['1280']['increases']===5000000 && $annualBefore['assets']['1280']['depreciation']===1500000 && $annualBefore['assets']['1280']['closing']===3500000,'Asset schedule additions/depreciation and closing');
 ycAssert($annualBefore['fields']['annenDriftskostnad/7700']['value']===75000 && $annualBefore['fields']['annenDriftskostnad/7700']['adjustment']===-75000 && $annualBefore['fields']['annenDriftskostnad/7700']['basis']===0,'Non-deductible cost displayed with explicit correction and zero deduction basis');
 // Tax reconciliation: no second depreciation deduction or advance-tax deduction.
 $taxable=ycResult(2025)+750;ycAssert($taxable===43000.0 && $preDisposal['1280']===35000.0,'Independent tax result 43000 and closing asset 35000');
 ycVoucher(array('8800'=>42250,'2050'=>-42250),12,31,2025,'SYNTHETIC RESULT DISPOSAL');ycAssert(ycResult(2025)===0.0,'Disposal zeros result exactly once');
 ycAssert($annualBefore['fields']['egenkapital/2050']['basis']===22225000,'Annual equity before disposal matches native closing');
 $annualAfter=NorwayAnnualReport::build($db,$entity,2025,$user);ycAssert($annualAfter['profit']===$annualBefore['profit'] && $annualAfter['advance_tax']===1700000 && $annualAfter['closing_equity']===22225000 && $annualAfter['unallocated_result']===0,'Result disposal must not erase profit or actual tax payments in report');
 $langs->load('norway@norway');$csv=NorwayAnnualUi::csv($annualAfter,$langs);ycAssert(strpos($csv,'43000.00')!==false && strpos($csv,'Kontospesifikasjon')!==false,'Complete annual CSV with independent tax basis and account detail');
 $history=ycRows("SELECT * FROM {$p}accounting_bookkeeping WHERE entity=$entity ORDER BY rowid");$b=new BookKeeping($db);ycAssert($b->validateMovementForFiscalPeriod(dol_mktime(0,0,0,1,1,2025),dol_mktime(23,59,59,12,31,2025))>=0,'Native validation');
 foreach(array(array($years[2025],$years[2025],true),array($years[2025],$years[2027],true),array($years[2025],$years[2026],false),array(999999999,$years[2026],true)) as [$from,$to,$aux]){try{NorwayYearEnd::guard($db,$entity,$from,$to,true,$aux);throw new LogicException('Unsafe close accepted');}catch(RuntimeException $expectedError){}finally{ycRows("SELECT RELEASE_LOCK('norway-year-end-$entity')");}}
 NorwayYearEnd::guard($db,$entity,$years[2025],$years[2026],true,true);
 ycAssert($b->closeFiscalPeriod($years[2025],$years[2026],true,true)>0,'Native close '.implode(' ',$b->errors));$conf->cache=array();
 ycRows("SELECT RELEASE_LOCK('norway-year-end-$entity')");
 $closedRows=ycRows("SELECT * FROM {$p}accounting_bookkeeping WHERE entity=$entity ORDER BY rowid");try{NorwayYearEnd::guard($db,$entity,$years[2025],$years[2026],true,true);throw new LogicException('Repeated close accepted');}catch(RuntimeException $expectedError){}finally{ycRows("SELECT RELEASE_LOCK('norway-year-end-$entity')");}ycAssert($closedRows==ycRows("SELECT * FROM {$p}accounting_bookkeeping WHERE entity=$entity ORDER BY rowid"),'Repeated close changed ledger');
 $expected=array('1280'=>35000.0,'1460'=>5000.0,'1500'=>2500.0,'1700'=>9000.0,'1920'=>172250.0,'2050'=>-222250.0,'2400'=>-1250.0,'2700'=>-500.0,'2710'=>250.0);ksort($expected);$actual=ycBalances("doc_type='closure' AND fk_doc=".$years[2025]);ycAssert($actual===$expected && array_sum($actual)===0.0,'Independent balanced opening: '.json_encode($actual));
 // Reopening a status alone must not let core generate a second opening balance.
 ycRows("UPDATE {$p}accounting_fiscalyear SET statut=0 WHERE entity=$entity AND rowid=".$years[2025]);
 try{NorwayYearEnd::guard($db,$entity,$years[2025],$years[2026],true,true);throw new LogicException('Reopened year duplicated carry');}catch(RuntimeException $expectedError){}finally{ycRows("SELECT RELEASE_LOCK('norway-year-end-$entity')");}
 ycRows("UPDATE {$p}accounting_fiscalyear SET statut=1 WHERE entity=$entity AND rowid=".$years[2025]);
 $aux=ycRows("SELECT numero_compte,subledger_account FROM {$p}accounting_bookkeeping WHERE entity=$entity AND doc_type='closure' AND numero_compte IN ('1500','2400') ORDER BY numero_compte");ycAssert(count($aux)===2 && $aux[0]->subledger_account==='SYN-C' && $aux[1]->subledger_account==='SYN-S','Both open subledgers carried forward');
 ycAssert(ycResult(2026)===0.0,'No carried revenue, expense, or repeated profit');
 ycAssert(ycPost(array(array('6800',1,''),array('1920',-1,'')),12,31,2025,'SYNTHETIC BLOCKED',false)<0,'Closed year blocks new posting');
 // A later documented correction is posted in the open year, leaving validated history intact.
 ycVoucher(array('6800'=>100,'1920'=>-100),1,2,2026,'SYNTHETIC CORRECTION OF PRIOR PERIOD');ycAssert(ycResult(2026)===-100.0,'Open-year correction');
 ycVoucher(array('6300'=>9000,'1700'=>-9000),1,2,2026,'SYNTHETIC NEXT YEAR RENT RELEASE');
 ycVoucher(array('6030'=>10500,'1280'=>-10500),12,31,2026,'SYNTHETIC SECOND YEAR DEPRECIATION');
 ycAssert(ycResult(2026)===-19600.0,'Second-year rent release, depreciation and correction');
 ycAssert($b->closeFiscalPeriod($years[2026],$years[2027],true,true)>0,'Native loss close');$conf->cache=array();$lossOpening=ycBalances("doc_type='closure' AND fk_doc=".$years[2026]);$expected['1920']-=100;$expected['2050']+=19600;$expected['1280']-=10500;unset($expected['1700']);ycAssert($lossOpening===$expected && array_sum($lossOpening)===0.0,'Loss reduces ENK equity once');
 $annualLoss=NorwayAnnualReport::build($db,$entity,2026,$user);ycAssert(!$annualLoss['issues'] && $annualLoss['profit']===-1960000 && $annualLoss['fields']['egenkapital/2050']['basis']===20265000,'Annual report agrees with native loss disposed only in next opening');
 foreach($history as $old){$now=ycRows("SELECT * FROM {$p}accounting_bookkeeping WHERE rowid=".(int)$old->rowid)[0];unset($old->date_validated,$now->date_validated,$old->fk_user_modif,$now->fk_user_modif,$old->tms,$now->tms);ycAssert($old==$now,'Historical source changed');}
 print "PASS ENK full year: prepaids, stock, asset/depreciation, owner tax/withdrawals, profit42250/tax43000, native disposal and balanced opening/equity222250, open subledgers, closed-year block, documented correction, prepaid release, second-year depreciation and loss19600 carried without double result\n";
}finally{$db->rollback();ycRows("SELECT RELEASE_LOCK('norway-year-end-$entity')");foreach(array('accounting_bookkeeping','accounting_bookkeeping_tmp','accounting_account','accounting_journal','accounting_fiscalyear','const') as $table){ycRows("DELETE FROM {$p}{$table} WHERE entity=$entity");}$conf->entity=$originalEntity;$conf->global=$originalGlobal;$conf->cache=array();}
