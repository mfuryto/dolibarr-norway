<?php
// SPDX-License-Identifier: GPL-3.0-or-later
if(PHP_SAPI!=='cli'||getenv('NORWAY_SYNTHETIC_TEST')!=='1'||getenv('DOLI_DB_NAME')!=='norway_test'){exit(1);}
require '/var/www/html/master.inc.php';
foreach(array('/core/lib/admin.lib.php','/core/lib/date.lib.php','/accountancy/class/bookkeeping.class.php','/custom/norway/class/NorwayAnnualUi.php') as $file){require_once DOL_DOCUMENT_ROOT.$file;}
function ddAssert($ok,$message){if(!$ok){throw new RuntimeException($message);}}
function ddSql($sql){global $db;$r=$db->query($sql);ddAssert($r!==false,$db->lasterror());return $r;}
function ddPost($pairs,$label){global $db,$conf,$user;$b=new BookKeeping($db);$piece=$b->getNextNumMvt();foreach($pairs as $number=>$amount){$b=new BookKeeping($db);$b->entity=$conf->entity;$b->piece_num=$piece;$b->doc_date=dol_mktime(12,0,0,12,31,2026);$b->doc_type='manual';$b->doc_ref=$label;$b->fk_doc=0;$b->fk_docdet=0;$b->numero_compte=(string)$number;$b->label_compte='Synthetic';$b->label_operation=$label;$b->debit=max($amount,0);$b->credit=max(-$amount,0);$b->montant=abs($amount);$b->sens=$amount<0?'C':'D';$b->code_journal='SYNDED';$b->journal_label='Synthetic deductions';ddAssert($b->createStd($user)>0,'Native deduction journal: '.$b->error);}return $piece;}
ddAssert($user->fetch('','testadmin')>0,'Admin');$user->loadRights('',1);$oldEntity=$conf->entity;$oldGlobal=clone $conf->global;$conf->entity=987648;$entity=$conf->entity;$p=$db->prefix();
try{
 $preview=NorwayChartProfile::preview($db,$entity);ddAssert(NorwayChartProfile::install($db,$entity,NorwayChartProfile::fingerprint($entity,$preview),$user)===745,'Chart');$conf->global->CHARTOFACCOUNTS=(string)NorwayChartProfile::preview($db,$entity)['target'];$conf->global->ACCOUNTANCY_FISCAL_PERIOD_MODE='blockedonclosed';$conf->cache=array();
 ddAssert(count(NorwayAnnualReport::fields(2025))===131 && count(NorwayAnnualReport::fields(2026))===131,'Pinned eligible-year fields');
 $empty=NorwayAnnualReport::build($db,$entity,2026,$user);ddAssert(!$empty['issues'] && $empty['profit']===0 && !$empty['fields'],'Explicit zero-activity basis');
 ddPost(array('1920'=>100000,'3000'=>-100000),'SYNTHETIC SALES');
 ddPost(array('6907'=>12000,'1920'=>-12000),'SYNTHETIC INTERNET');
 $benefit=ddPost(array('2068'=>4392,'6907'=>-4392),'SYNTHETIC PRIVATE COMMUNICATION');
 ddPost(array('6340'=>3000,'2062'=>-3000),'SYNTHETIC DOCUMENTED OWN-HOME COST PAID PRIVATELY');
 ddPost(array('6551'=>5000,'1920'=>-5000),'SYNTHETIC DIRECT EXPENSE EQUIPMENT');
 ddPost(array('2078'=>1000,'1920'=>-1000),'SYNTHETIC PERSONAL NAV PREMIUM');
 ddPost(array('2050'=>5392,'2068'=>-4392,'2078'=>-1000),'SYNTHETIC PRIVATE CLEARING');
 ddPost(array('2062'=>3000,'2050'=>-3000),'SYNTHETIC PRIVATE CONTRIBUTION CLEARING');
 $r=NorwayAnnualReport::build($db,$entity,2026,$user);ddAssert(!$r['issues'],'Deduction report: '.json_encode($r['issues']));
 ddAssert($r['profit']===8439200 && $r['tax_basis_before_other_adjustments']===8439200,'Private use already in profit; personal premium does not reduce it');
 ddAssert($r['fields']['annenDriftskostnad/6995']['value']===760800 && $r['fields']['annenDriftskostnad/6995']['basis']===1200000 && $r['fields']['annenDriftskostnad/6998']['basis']===439200,'Gross communication and exact private-use field, no second addition');
 ddAssert($r['phone_projection']===array(array('piece'=>(int)$benefit,'amount'=>439200)),'Phone presentation trace');
 ddAssert($r['fields']['annenDriftskostnad/6500']['basis']===500000,'Direct equipment mapped to actual ENK field instead of SAF-T IT category');
 ddAssert(count($r['personal_premiums'])===1 && $r['personal_premiums'][0]['amount']===100000,'Personal NAV evidence retained after owner clearing');
 $topics=NorwayDeductions::topics($r);ddAssert($topics['Phone']['amount']===760800 && $topics['Equipment']['amount']===500000 && $topics['Home']['amount']===300000 && $topics['Startup']['amount']===null,'Deduction topics reflect booked evidence, not extra expense');
 $csv=NorwayAnnualUi::csv($r,$langs);ddAssert(strpos($csv,'6998;')!==false && strpos($csv,'4392.00')!==false && strpos($csv,'Fradragsoversikt')!==false,'Fields, known return and deductions in CSV');
 $bad=$r;$bad['accounts']['6907']['label']='=HYPERLINK("https://example.invalid")';$csv=NorwayAnnualUi::csv($bad,$langs);ddAssert(strpos($csv,"'=HYPERLINK")!==false,'Spreadsheet formula escaping');
 ddSql("UPDATE {$p}accounting_account SET pcg_type='1' WHERE entity=$entity AND account_number='6907'");$wrongGroup=NorwayAnnualReport::build($db,$entity,2026,$user);ddAssert(in_array('NorwayAnnualAccountGroup',array_column($wrongGroup['issues'],'key'),true),'Wrong account group must not produce an apparently ready result');ddSql("UPDATE {$p}accounting_account SET pcg_type='6' WHERE entity=$entity AND account_number='6907'");
 ddSql("UPDATE {$p}accounting_account SET label='SYNTHETIC CUSTOM MEANING' WHERE entity=$entity AND account_number='6907'");$changed=NorwayAnnualReport::build($db,$entity,2026,$user);ddAssert($changed['issues'] && NorwayDeductions::topics($changed)['Phone']['amount']===null,'Renamed account not silently classified or shown as zero');
 try{NorwayAnnualReport::build($db,$entity,2027,$user);throw new LogicException('Unqualified year accepted');}catch(RuntimeException $expected){}
 $denied=clone $user;$denied->socid=987654;try{NorwayAnnualReport::build($db,$entity,2026,$denied);throw new LogicException('External user read annual report');}catch(RuntimeException $expected){}
 echo "PASS ENK deduction basis: zero activity, phone12000/private4392/net7608 in exact 6995/6998 fields, equipment5000, own-home evidence3000, personal NAV1000 kept outside business profit84392, cleared-owner trace, renamed account, CSV injection and access/year guards\n";
}finally{$db->rollback();foreach(array('accounting_bookkeeping','accounting_account','const') as $table){ddSql("DELETE FROM {$p}{$table} WHERE entity=$entity");}$conf->entity=$oldEntity;$conf->global=$oldGlobal;$conf->cache=array();}
