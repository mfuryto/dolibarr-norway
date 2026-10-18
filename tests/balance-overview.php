<?php
// SPDX-License-Identifier: GPL-3.0-or-later
if(PHP_SAPI!=='cli'||getenv('NORWAY_SYNTHETIC_TEST')!=='1'||getenv('DOLI_DB_NAME')!=='norway_test'){exit(1);}
require '/var/www/html/master.inc.php';
foreach(array('/core/lib/admin.lib.php','/core/lib/date.lib.php','/core/class/fiscalyear.class.php','/accountancy/class/bookkeeping.class.php','/custom/norway/class/NorwayChartProfile.php','/custom/norway/class/NorwayBalance.php') as $file){require_once DOL_DOCUMENT_ROOT.$file;}
function boAssert($ok,$message){if(!$ok){throw new RuntimeException($message);}}
function boRows($sql){global $db;$result=$db->query($sql);boAssert($result!==false,$db->lasterror());$out=array();if($result===true){return $out;}while($row=$db->fetch_object($result)){$out[]=$row;}return $out;}
function boPost($pairs,$date,$ref){global $db,$conf,$user;$piece=(new BookKeeping($db))->getNextNumMvt();foreach($pairs as $number=>$amount){$row=new BookKeeping($db);$row->piece_num=$piece;$row->doc_date=strtotime($date.' 12:00:00');$row->doc_type='';$row->doc_ref=$ref;$row->fk_doc=0;$row->fk_docdet=0;$row->numero_compte=(string)$number;$row->label_compte='Synthetic';$row->label_operation=$ref;$row->debit=max($amount,0);$row->credit=max(-$amount,0);$row->montant=abs($amount);$row->sens=$amount>=0?'D':'C';$row->code_journal='BODIV';$row->journal_label='Synthetic';boAssert($row->createStd($user)>0,'Native posting: '.$row->error);}return $piece;}
boAssert($user->fetch('','testadmin')>0,'Admin');$user->loadRights('',1);$oldEntity=$conf->entity;$oldGlobal=clone $conf->global;$entity=987645;$conf->entity=$entity;$p=$db->prefix();$years=array();$journal=0;
try{
 $preview=NorwayChartProfile::preview($db,$entity);boAssert(NorwayChartProfile::install($db,$entity,NorwayChartProfile::fingerprint($entity,$preview),$user)===745,'Chart');$conf->global->CHARTOFACCOUNTS=(string)NorwayChartProfile::preview($db,$entity)['target'];
 foreach(array('ACCOUNTANCY_FISCAL_PERIOD_MODE'=>'blockedonclosed','ACCOUNTING_RESULT_PROFIT'=>'2050','ACCOUNTING_RESULT_LOSS'=>'2050') as $key=>$value){dolibarr_set_const($db,$key,$value,'chaine',0,'',$entity);$conf->global->$key=$value;}$conf->cache=array();
 boRows("INSERT INTO {$p}accounting_journal (entity,code,label,nature,active) VALUES ($entity,'BOAN','Synthetic balance opening',9,1)");$journal=(int)$db->last_insert_id($p.'accounting_journal');dolibarr_set_const($db,'ACCOUNTING_CLOSURE_DEFAULT_JOURNAL',(string)$journal,'chaine',0,'',$entity);$conf->global->ACCOUNTING_CLOSURE_DEFAULT_JOURNAL=(string)$journal;
 foreach(array(2025,2026) as $year){$f=new FiscalYear($db);$f->label='SYNTHETIC BALANCE '.$year;$f->date_start=dol_mktime(0,0,0,1,1,$year);$f->date_end=dol_mktime(23,59,59,12,31,$year);$f->status=0;boAssert($f->create($user)>0,'Native year');$years[$year]=(int)$f->id;}
 $empty=NorwayBalance::build($db,$entity,'2025-01-01',$user);boAssert($empty['assets']===0 && $empty['difference']===0 && !$empty['groups'],'Empty chart gives explicit zero');
 boPost(array('1920'=>10000,'2050'=>-10000),'2025-02-01','SYNTHETIC OWNER CAPITAL');
 boPost(array('1500'=>2500,'3000'=>-2000,'2700'=>-500),'2025-02-01','SYNTHETIC SALE');
 boPost(array('1460'=>500,'1920'=>-500),'2025-02-01','SYNTHETIC STOCK');
 boPost(array('1280'=>2000,'1920'=>-2000),'2025-02-01','SYNTHETIC ASSET');
 boPost(array('6030'=>600,'1280'=>-600),'2025-02-01','SYNTHETIC DEPRECIATION');
 boPost(array('1700'=>900,'1920'=>-900),'2025-02-01','SYNTHETIC PREPAID');
 boPost(array('4000'=>400,'2710'=>100,'2400'=>-500),'2025-02-01','SYNTHETIC PURCHASE');
 boPost(array('2070'=>700,'1920'=>-700),'2025-02-01','SYNTHETIC PERSONAL TAX');
 boPost(array('1920'=>3000,'2220'=>-3000),'2025-02-01','SYNTHETIC LOAN');
 $before=boRows("SELECT * FROM {$p}accounting_bookkeeping WHERE entity=$entity ORDER BY rowid");
 $r=NorwayBalance::build($db,$entity,'2025-12-31',$user);boAssert($r['assets']===1420000 && $r['debt']===390000 && $r['equity']===930000 && $r['result']===100000 && $r['total_funding']===1420000 && $r['difference']===0,'Independent balance14200 = equity10300 + liabilities3900');
 boAssert($r['groups']['Bank']['amount']===890000 && $r['groups']['Receivables']['amount']===250000 && $r['groups']['FixedAssets']['amount']===140000 && $r['groups']['Inventory']['amount']===50000 && $r['groups']['OtherReceivables']['amount']===90000 && $r['groups']['Suppliers']['amount']===50000 && $r['groups']['Tax']['amount']===40000,'Actual grouped account values');
 boAssert(NorwayBalance::build($db,$entity,'2025-01-31',$user)['assets']===0,'As-of cutoff includes neither future balances nor result');
 $nextOpen=NorwayBalance::build($db,$entity,'2026-01-01',$user);boAssert($nextOpen['assets']===1420000 && $nextOpen['result']===100000 && $nextOpen['baseline']===null,'Open prior year remains included without silently losing profit');
 $url=NorwayBalance::accountUrl($r,'1920');boAssert(strpos($url,'search_accountancy_code_start=1920')!==false && strpos($url,'search_accountancy_code_end=1920')!==false && strpos($url,'search_date_startmonth=2')!==false && strpos($url,'search_date_endyear=2025')!==false,'Native account detail links use actual filters and complete history');
 boAssert($before==boRows("SELECT * FROM {$p}accounting_bookkeeping WHERE entity=$entity ORDER BY rowid"),'Read-only report');
 // Display corruption honestly; no balancing plug or implicit posting is permitted.
 $badPiece=boPost(array('1920'=>1),'2025-03-01','SYNTHETIC UNBALANCED');$bad=NorwayBalance::build($db,$entity,'2025-12-31',$user);boAssert($bad['difference']===100 && $bad['assets']===1420100 && $bad['total_funding']===1420000,'Actual unbalanced ledger difference remains visible');boRows("DELETE FROM {$p}accounting_bookkeeping WHERE entity=$entity AND piece_num=$badPiece");
 boRows("UPDATE {$p}accounting_account SET pcg_type='XXXXXX' WHERE entity=$entity AND account_number='1920'");try{NorwayBalance::build($db,$entity,'2025-12-31',$user);throw new LogicException('Unknown classification accepted');}catch(RuntimeException $expected){}finally{boRows("UPDATE {$p}accounting_account SET pcg_type='1' WHERE entity=$entity AND account_number='1920'");}
 boPost(array('2050'=>700,'2070'=>-700),'2025-12-31','SYNTHETIC OWNER CLEARING');boPost(array('8800'=>1000,'2050'=>-1000),'2025-12-31','SYNTHETIC RESULT DISPOSAL');
 $disposed=NorwayBalance::build($db,$entity,'2025-12-31',$user);boAssert($disposed['result']===0 && $disposed['equity_with_result']===1030000 && $disposed['assets']===1420000,'Disposed profit not counted twice');
 $b=new BookKeeping($db);boAssert($b->closeFiscalPeriod($years[2025],$years[2026],true,true)>0,'Native year close: '.implode(' ',$b->errors));$conf->cache=array();
 $carried=NorwayBalance::build($db,$entity,'2026-01-01',$user);boAssert($carried['baseline']==='2026-01-01' && $carried['assets']===1420000 && $carried['equity_with_result']===1030000 && $carried['result']===0 && $carried['difference']===0,'Native carry-forward replaces prior-year history exactly once');
 boPost(array('7770'=>100,'1920'=>-100),'2026-02-01','SYNTHETIC NEXT YEAR COST');$loss=NorwayBalance::build($db,$entity,'2026-02-01',$user);boAssert($loss['assets']===1410000 && $loss['result']===-10000 && $loss['equity_with_result']===1020000 && $loss['difference']===0,'Loss reduces equity without journal mutation');
 boAssert(NorwayBalance::build($db,$entity,'2025-12-31',$user)['assets']===1420000,'Historical report unchanged after future carry/cost');
 foreach(array('2025-02-30','invalid','2100-01-01') as $date){try{NorwayBalance::build($db,$entity,$date,$user);throw new LogicException('Invalid date');}catch(RuntimeException $expected){}}
 $external=clone $user;$external->socid=1;boAssert(!NorwayBalance::allowed($external),'External denied');try{NorwayBalance::build($db,$entity+1,'2025-12-31',$user);throw new LogicException('Cross-entity report');}catch(RuntimeException $expected){}
 echo "PASS balance overview: native aggregation, independent assets14200/equity10300/debt3900, account groups, exact as-of cutoff, prior open result, read-only/native detail links, honest imbalance, missing classification, disposal/carry without double counting, next-year loss100 and historical stability, date/entity/external guards\n";
}finally{$db->rollback();foreach(array('accounting_bookkeeping','accounting_bookkeeping_tmp','accounting_account','accounting_journal','accounting_fiscalyear','const') as $table){boRows("DELETE FROM {$p}{$table} WHERE entity=$entity");}$conf->entity=$oldEntity;$conf->global=$oldGlobal;$conf->cache=array();}
