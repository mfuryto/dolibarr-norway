<?php
// SPDX-License-Identifier: GPL-3.0-or-later
if (PHP_SAPI!=='cli' || getenv('NORWAY_SYNTHETIC_TEST')!=='1' || getenv('DOLI_DB_NAME')!=='norway_test') { exit(1); }
require '/var/www/html/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/norway/class/NorwayChartGroups.php';
require_once DOL_DOCUMENT_ROOT.'/custom/norway/class/NorwayChartProfile.php';
require_once DOL_DOCUMENT_ROOT.'/accountancy/class/bookkeeping.class.php';
require_once DOL_DOCUMENT_ROOT.'/accountancy/class/accountingaccount.class.php';
require_once DOL_DOCUMENT_ROOT.'/accountancy/class/accountingjournal.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/fiscalyear.class.php';
function groupAssert($ok,$message) { if (!$ok) { throw new RuntimeException($message); } }
function groupSql($sql) { global $db; $r=$db->query($sql);groupAssert($r!==false,'Synthetic grouping query: '.$db->lasterror());return $r; }
function groupRows($sql) { global $db; $r=groupSql($sql);$rows=array();while($row=$db->fetch_object($r)){$rows[]=(array)$row;}return $rows; }
groupAssert($user->fetch('','testadmin')>0 && $user->admin,'Synthetic administrator missing');
$beforeEntity=$conf->entity;$conf->entity=987631;$entity=$conf->entity;$p=$db->prefix();$beforeGlobal=clone $conf->global;
$oldChart=groupRows("SELECT rowid FROM ".$p."accounting_system WHERE pcg_version='".NorwayChartProfile::VERSION."'");
try {
    foreach(array_keys(NorwayChartGroups::SETTINGS) as $name){unset($conf->global->$name);}
    groupAssert(NorwayChartGroups::complete($db,$entity)===0,'Repair selected an unrequested chart');
    $preview=NorwayChartProfile::preview($db,$entity);
    groupAssert(NorwayChartProfile::install($db,$entity,NorwayChartProfile::fingerprint($entity,$preview),$user)===745,'Fresh chart creation');
    $chart=NorwayChartProfile::preview($db,$entity)['target'];$conf->global->CHARTOFACCOUNTS=(string)$chart;
    groupAssert(NorwayChartGroups::ready($db,$entity),'Fresh native groups incomplete');
    foreach(NorwayChartGroups::SETTINGS as $name=>$value){groupAssert(getDolGlobalString($name)===$value,'Wrong closing groups');}
    $actual=groupRows("SELECT account_number,pcg_type FROM ".$p."accounting_account WHERE entity=".$entity);
    groupAssert(count($actual)===745,'Wrong account count');
    foreach($actual as $row){groupAssert($row['pcg_type']===substr($row['account_number'],0,1),'Incorrect account class '.$row['account_number']);}
    // Simulate an installed 0.76 chart with custom names/groups, an inactive account and history.
    groupSql("UPDATE ".$p."accounting_account SET pcg_type='XXXXXX' WHERE entity=".$entity);
    groupSql("UPDATE ".$p."accounting_account SET label='Synthetic custom bank name',pcg_type='BANKCUSTOM' WHERE entity=".$entity." AND account_number='1920'");
    groupSql("UPDATE ".$p."accounting_account SET active=0,pcg_type='' WHERE entity=".$entity." AND account_number='1950'");
    groupSql("INSERT INTO ".$p."accounting_account (entity,fk_pcg_version,account_number,label,pcg_type,active) VALUES (".$entity.",'".NorwayChartProfile::VERSION."','1998','Synthetic added account','LOCAL',1)");
    groupSql("INSERT INTO ".$p."accounting_account (entity,fk_pcg_version,account_number,label,pcg_type,active) VALUES (".($entity+1).",'".NorwayChartProfile::VERSION."','1920','Synthetic other entity','XXXXXX',1)");
    $failing=new class($db){private $db;private $writes=0;public function __construct($db){$this->db=$db;}public function __call($n,$a){return $this->db->$n(...$a);}public function query($sql,...$a){if(strpos($sql,'UPDATE '.$this->db->prefix().'accounting_account SET pcg_type=')===0 && ++$this->writes===2){return false;}return $this->db->query($sql,...$a);}};
    $before=groupRows("SELECT * FROM ".$p."accounting_account WHERE entity=".$entity." ORDER BY rowid");
    try{NorwayChartGroups::complete($failing,$entity);throw new LogicException('Partial repair succeeded');}catch(RuntimeException $expected){}
    groupAssert($before===groupRows("SELECT * FROM ".$p."accounting_account WHERE entity=".$entity." ORDER BY rowid"),'Partial repair did not roll back');
    groupAssert(NorwayChartGroups::complete($db,$entity)===744,'Old chart was not repaired');
    groupAssert(NorwayChartGroups::complete($db,$entity)===0,'Repair is not repeatable');
    $custom=groupRows("SELECT label,pcg_type FROM ".$p."accounting_account WHERE entity=".$entity." AND account_number='1920'")[0];
    groupAssert($custom===array('label'=>'Synthetic custom bank name','pcg_type'=>'BANKCUSTOM'),'Custom choices overwritten');
    groupAssert(groupRows("SELECT active,pcg_type FROM ".$p."accounting_account WHERE entity=".$entity." AND account_number='1950'")[0]===array('active'=>'0','pcg_type'=>'1'),'Inactive account changed or unclassified');
    groupAssert(groupRows("SELECT pcg_type FROM ".$p."accounting_account WHERE entity=".($entity+1))[0]['pcg_type']==='XXXXXX','Other entity modified');
    groupAssert(!NorwayChartGroups::ready($db,$entity),'Unmapped custom groups reported ready');
    // Native settings accommodate custom groups; the module must preserve them.
    dolibarr_set_const($db,'ACCOUNTING_CLOSURE_ACCOUNTING_GROUPS_USED_FOR_BALANCE_SHEET_ACCOUNT','1,2,BANKCUSTOM,LOCAL','chaine',0,'',$entity);
    groupAssert(NorwayChartGroups::complete($db,$entity)===0 && NorwayChartGroups::ready($db,$entity),'Custom closing groups not supported');
    // Unique synthetic periods and journal, then native posting/result/carry-forward.
    groupSql("INSERT INTO ".$p."accounting_journal (entity,code,label,nature,active) VALUES (".$entity.",'SYNAN','Synthetic opening',9,1)");
    $journal=(int)$db->last_insert_id($p.'accounting_journal');
    dolibarr_set_const($db,'ACCOUNTING_CLOSURE_DEFAULT_JOURNAL',(string)$journal,'chaine',0,'',$entity);
    dolibarr_set_const($db,'ACCOUNTING_RESULT_PROFIT','2099','chaine',0,'',$entity);
    dolibarr_set_const($db,'ACCOUNTING_RESULT_LOSS','2099','chaine',0,'',$entity);
    foreach(array('2025','2026') as $year){groupSql("INSERT INTO ".$p."accounting_fiscalyear (entity,label,date_start,date_end,statut,datec) VALUES (".$entity.",'Synthetic ".$year."','".$year."-01-01','".$year."-12-31',0,'2026-01-01')");$years[$year]=(int)$db->last_insert_id($p.'accounting_fiscalyear');}
    $postingYear=2025;
    $post=static function($number,$debit,$credit,$piece)use($db,$user,$entity,&$postingYear){$b=new BookKeeping($db);$b->entity=$entity;$b->doc_date=dol_mktime(12,0,0,6,1,$postingYear);$b->doc_type='SYN';$b->doc_ref='SYN-GROUPS-'.$piece;$b->fk_doc=$piece;$b->fk_docdet=$piece;$b->numero_compte=$number;$b->label_compte='Synthetic';$b->label_operation='Synthetic grouping fixture';$b->debit=$debit;$b->credit=$credit;$b->montant=$debit+$credit;$b->sens=$debit>0?'D':'C';$b->code_journal='SYNAN';$b->journal_label='Synthetic';$b->fk_user_author=$user->id;$b->piece_num=$piece;groupAssert($b->create($user)>=0 && $b->id>0,'Native posting failed: '.implode(' ',$b->errors));};
    // Opening: bank 1000/equity 1000; sales 500; expenses 200; receivable 125; payable 125.
    foreach(array(array('1920',1000,0,1),array('2050',0,1000,1),array('1920',500,0,2),array('3000',0,500,2),array('4000',200,0,3),array('1920',0,200,3),array('1500',125,0,4),array('2400',0,125,4)) as $entry){$post(...$entry);}
    $ledgerBefore=groupRows("SELECT * FROM ".$p."accounting_bookkeeping WHERE entity=".$entity." ORDER BY rowid");
    // Repair a missing class even after posting, without rewriting any ledger row.
    groupSql("UPDATE ".$p."accounting_account SET pcg_type='XXXXXX' WHERE entity=".$entity." AND account_number='4000'");
    groupAssert(NorwayChartGroups::complete($db,$entity)===1,'Used chart repair blocked');
    groupAssert($ledgerBefore===groupRows("SELECT * FROM ".$p."accounting_bookkeeping WHERE entity=".$entity." ORDER BY rowid"),'Repair changed historical postings');
    $bk=new BookKeeping($db);$profit=$bk->accountingResult(dol_mktime(0,0,0,1,1,2025),dol_mktime(23,59,59,12,31,2025));
    // Core 24.0.1 accountingResult opens a read transaction without closing it.
    $db->rollback();
    groupAssert((float)$profit===300.0,'Native result should be 300');
    groupAssert($bk->closeFiscalPeriod($years['2025'],$years['2026'],false,true)>0,'Native closing failed: '.implode(' ',$bk->errors));
    $opening=groupRows("SELECT numero_compte,SUM(debit-credit) AS balance FROM ".$p."accounting_bookkeeping WHERE entity=".$entity." AND doc_type='closure' GROUP BY numero_compte ORDER BY numero_compte");
    $expected=array('1500'=>125.0,'1920'=>1300.0,'2050'=>-1000.0,'2099'=>-300.0,'2400'=>-125.0);$sum=0;
    groupAssert(count($opening)===count($expected),'Result accounts were carried forward or balance accounts missing');
    foreach($opening as $row){groupAssert(isset($expected[$row['numero_compte']]) && (float)$row['balance']===$expected[$row['numero_compte']],'Wrong native opening balance '.$row['numero_compte']);$sum+=(float)$row['balance'];}
    groupAssert($sum===0.0,'Opening balance is not balanced');
    groupAssert($ledgerBefore===groupRows("SELECT * FROM ".$p."accounting_bookkeeping WHERE entity=".$entity." AND doc_type<>'closure' ORDER BY rowid"),'Closing rewrote historical postings');
    // A loss uses the other native result setting; no revenue/expense balance is carried.
    foreach(array('2027','2028') as $year){groupSql("INSERT INTO ".$p."accounting_fiscalyear (entity,label,date_start,date_end,statut,datec) VALUES (".$entity.",'Synthetic ".$year."','".$year."-01-01','".$year."-12-31',0,'2026-01-01')");$years[$year]=(int)$db->last_insert_id($p.'accounting_fiscalyear');}
    $postingYear=2027;
    foreach(array(array('1920',100,0,11),array('2050',0,100,11),array('6800',200,0,12),array('1920',0,200,12)) as $entry){$post(...$entry);}
    $loss=$bk->accountingResult(dol_mktime(0,0,0,1,1,2027),dol_mktime(23,59,59,12,31,2027));$db->rollback();
    groupAssert((float)$loss===-200.0,'Native loss should be 200');
    groupAssert($bk->closeFiscalPeriod($years['2027'],$years['2028'],false,true)>0,'Native loss carry-forward failed');
    $opening=groupRows("SELECT numero_compte,SUM(debit-credit) AS balance FROM ".$p."accounting_bookkeeping WHERE entity=".$entity." AND doc_type='closure' AND fk_doc=".$years['2027']." GROUP BY numero_compte ORDER BY numero_compte");
    $expected=array('1920'=>-100.0,'2050'=>-100.0,'2099'=>200.0);$sum=0;
    groupAssert(count($opening)===3,'Loss carry-forward account count');
    foreach($opening as $row){groupAssert(isset($expected[$row['numero_compte']]) && (float)$row['balance']===$expected[$row['numero_compte']],'Wrong loss opening balance');$sum+=(float)$row['balance'];}
    groupAssert($sum===0.0,'Loss opening does not balance');

}finally{
    $db->rollback();
    foreach(array('accounting_bookkeeping','accounting_account','accounting_fiscalyear','accounting_journal','const') as $table){groupSql("DELETE FROM ".$p.$table." WHERE entity IN (".$entity.",".($entity+1).")");}
    if(!$oldChart){groupSql("DELETE FROM ".$p."accounting_system WHERE pcg_version='".NorwayChartProfile::VERSION."'");}
    $conf->entity=$beforeEntity;$conf->global=$beforeGlobal;
}
print "PASS 745 native account classes; fresh/used chart repair, custom choices, isolation, rollback and native profit 300 / loss 200 and balanced opening amounts\n";
