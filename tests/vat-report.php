<?php
// SPDX-License-Identifier: GPL-3.0-or-later
if(PHP_SAPI!=='cli'||getenv('NORWAY_SYNTHETIC_TEST')!=='1'||getenv('DOLI_DB_NAME')!=='norway_test'||getenv('DOLI_COMPANY_NAME')!=='SYNTHETIC NORWAY TEST ONLY'){exit(1);}
require '/var/www/html/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';require_once DOL_DOCUMENT_ROOT.'/accountancy/class/accountingaccount.class.php';require_once DOL_DOCUMENT_ROOT.'/accountancy/class/bookkeeping.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/norway/core/modules/modNorway.class.php';require_once DOL_DOCUMENT_ROOT.'/custom/norway/class/NorwayVatReport.php';
function nrAssert($ok,$m){if(!$ok){throw new RuntimeException($m);}}
function nrSql($sql){global $db;$r=$db->query($sql);nrAssert((bool)$r,'Synthetic VAT report SQL: '.$db->lasterror());return $r;}
function nrBlocked($f,$m){$blocked=false;try{$f();}catch(RuntimeException $e){$blocked=true;}nrAssert($blocked,$m);}
function nrReason($report,$reason){return count(array_filter($report['issues'],fn($i)=>$i['reason']===$reason))>0;}
nrAssert($user->fetch('','testadmin')>0,'Admin missing');$mod=new modNorway($db);nrAssert($mod->init()>0,$mod->error);$conf->setValues($db);$user->loadRights('',1);
$p=$db->prefix();$entity=(int)$conf->entity;$run=bin2hex(random_bytes(4));$start='2038-02-01';$end='2038-02-28';
$oldGlobals=clone $conf->global;$oldCompany=clone $mysoc;$oldRates=array();$oldSettings=array();$invoices=array('customer_invoice'=>array(),'supplier_invoice'=>array());$manualIds=array();$accountIds=array();$newAccounts=array();$journalIds=array();$partnerId=0;
$settings=array('ACCOUNTANCY_FISCAL_PERIOD_MODE'=>'blockedonclosed','ACCOUNTING_ACCOUNT_CUSTOMER'=>'1500','ACCOUNTING_ACCOUNT_SUPPLIER'=>'2400','ACCOUNTING_PRODUCT_SOLD_ACCOUNT'=>'3000','ACCOUNTING_SERVICE_SOLD_ACCOUNT'=>'3000','ACCOUNTING_PRODUCT_BUY_ACCOUNT'=>'4000','ACCOUNTING_SERVICE_BUY_ACCOUNT'=>'4000','ACCOUNTING_VAT_SOLD_ACCOUNT'=>'2700','ACCOUNTING_VAT_BUY_ACCOUNT'=>'2710');
$codes="'1','11','13','3','31','33'";$r=nrSql('SELECT * FROM '.$p.'c_tva WHERE entity='.$entity.' AND code IN ('.$codes.')');while($row=$db->fetch_object($r)){$oldRates[]=(array)$row;}
foreach($settings as $name=>$v){$r=nrSql('SELECT * FROM '.$p."const WHERE entity=".$entity." AND name='".$name."'");while($row=$db->fetch_object($r)){$oldSettings[]=(array)$row;}}
$cookie=tempnam(sys_get_temp_dir(),'norway-vat-report-');$curl=curl_init();curl_setopt_array($curl,array(CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_TIMEOUT=>30));
function nrHttp($path,$post=null){global $curl;curl_setopt($curl,CURLOPT_URL,'http://localhost'.$path);curl_setopt($curl,CURLOPT_POST,$post!==null);if($post!==null){curl_setopt($curl,CURLOPT_POSTFIELDS,http_build_query($post));}$b=curl_exec($curl);nrAssert($b!==false,'HTTP failed');return $b;}
function nrToken($body){nrAssert(preg_match('/name="token"[^>]*value="([^"]+)"/',$body,$m)===1,'Token missing');return html_entity_decode($m[1]);}
function nrReport(){global $db,$entity,$start,$end,$user;return NorwayVatReport::build($db,$entity,$start,$end,$user);}
function nrRestoreRows($table,$rows){global $db;foreach($rows as $row){$values=array_map(fn($v)=>$v===null?'NULL':"'".$db->escape((string)$v)."'",array_values($row));nrSql('INSERT INTO '.$table.' ('.implode(',',array_keys($row)).') VALUES ('.implode(',',$values).')');}}
try {
 foreach($settings as $name=>$v){nrAssert(dolibarr_set_const($db,$name,$v,'chaine',0,'',$entity)>0,'Test setting');$conf->global->$name=$v;}
 $mysoc->country_code='NO';$mysoc->country_id=173;$mysoc->tva_assuj=1;$mysoc->idprof1='123456785';$mysoc->address='Synthetic address';$mysoc->zip='0000';$mysoc->town='Synthetic';
 $chart=$db->fetch_object(nrSql('SELECT pcg_version FROM '.$p.'accounting_system WHERE rowid='.(int)getDolGlobalString('CHARTOFACCOUNTS')));nrAssert((bool)$chart,'Chart missing');
 foreach(array('1500','2400','3000','4000','1920','2700','2701','2703','2710','2711','2713') as $number){$row=$db->fetch_object(nrSql('SELECT rowid FROM '.$p.'accounting_account WHERE entity='.$entity." AND fk_pcg_version='".$db->escape($chart->pcg_version)."' AND account_number='".$number."'"));if($row){$accountIds[$number]=(int)$row->rowid;continue;}
  $a=new AccountingAccount($db);$a->fk_pcg_version=$chart->pcg_version;$a->account_number=$number;$a->label='Synthetic '.$number;$a->active=1;nrAssert($a->create($user)>0,'Account create');$accountIds[$number]=(int)$a->id;$newAccounts[]=(int)$a->id;
 }
 nrSql('DELETE FROM '.$p.'c_tva WHERE entity='.$entity.' AND code IN ('.$codes.')');
 foreach(NorwayVatRates::codes() as $s){$account=($s['type']===1?'270':'271').array(25=>'0',15=>'1',12=>'3')[$s['rate']];nrSql("INSERT INTO ".$p."c_tva (entity,fk_pays,code,type_vat,taux,recuperableonly,localtax1,localtax2,active,accountancy_code_sell,accountancy_code_buy) VALUES (".$entity.",173,'".$s['code']."',".$s['type'].",".$s['rate'].",0,0,0,1,'".($s['type']===1?$account:'')."','".($s['type']===2?$account:'')."')");}
 foreach(array(2,3) as $nature){nrSql("INSERT INTO ".$p."accounting_journal (entity,code,label,nature,active) VALUES (".$entity.",'SYNVR".$nature.'-'.$run."','Synthetic report',".$nature.",1)");$journalIds[$nature]=(int)$db->last_insert_id($p.'accounting_journal');}
 $party=new Societe($db);$party->name='SYNTHETIC VAT REPORT ONLY '.$run;$party->client=1;$party->fournisseur=1;$party->code_client='SYNVRC-'.$run;$party->code_fournisseur='SYNVRL-'.$run;$party->code_compta='SYNVRC';$party->code_compta_fournisseur='SYNVRL';$party->country_id=173;$party->tva_assuj=1;$party->idprof1='123456785';nrAssert($party->create($user)>0,'Party create');$partnerId=(int)$party->id;
 foreach(array('customer_invoice','supplier_invoice') as $source){$sale=$source==='customer_invoice';foreach(array(0,1) as $productType){foreach(array(0,2) as $type){
  $o=$sale?new Facture($db):new FactureFournisseur($db);$o->socid=$partnerId;$o->date=dol_mktime(12,0,0,2,10,2038);$o->type=$type;$o->multicurrency_code='NOK';$o->multicurrency_tx=1;
  if(!$sale){$o->ref_supplier='SYNVR-'.$run.'-'.$productType.'-'.$type;}
  if($type===2){$o->fk_facture_source=$invoices[$source][count($invoices[$source])-1]->id;}
  nrAssert($o->create($user)>0,'Document create: '.$o->error);$invoices[$source][]=$o;
  foreach(array(25,15,12) as $rate){$code=($sale?array(25=>'3',15=>'31',12=>'33'):array(25=>'1',15=>'11',12=>'13'))[$rate];$net=$type===2?($sale?20.10:-20.10):100.10;
   $result=$sale?$o->addline('Synthetic goods/services',$net,1,$rate.' ('.$code.')',0,0,0,0,'','',$accountIds['3000'],0,0,'HT',0,$productType):$o->addline('Synthetic goods/services',$net,$rate.' ('.$code.')',0,0,1,0,0,0,0,$accountIds['4000'],0,'HT',$productType);
   nrAssert($result>0,'Line create: '.$o->error);
  }
  nrAssert($o->validate($user,$sale?'':'SYNVR-'.$run.'-'.$productType.'-'.$type)>0,'Validate: '.$o->error);nrAssert($o->fetch($o->id)>0,'Reload');
 }}}
 $private=nrHttp('/custom/norway/vat.php?date_start='.$start.'&date_end='.$end.'&format=csv');nrAssert(strpos($private,'name="username"')!==false && strpos($private,'period_start;')===false,'Anonymous CSV exposed');
 $body=nrHttp('/index.php');nrHttp('/index.php',array('token'=>nrToken($body),'actionlogin'=>'login','loginfunction'=>'loginfunction','username'=>'testadmin','password'=>getenv('DOLI_ADMIN_PASSWORD')));
 foreach(array(2=>'sellsjournal',3=>'purchasesjournal') as $nature=>$journal){$params=array('id_journal'=>$journalIds[$nature],'date_startday'=>1,'date_startmonth'=>2,'date_startyear'=>2038,'date_endday'=>28,'date_endmonth'=>2,'date_endyear'=>2038,'in_bookkeeping'=>'notyet');$path='/accountancy/journal/'.$journal.'.php?'.http_build_query($params);$body=nrHttp($path);nrHttp($path,$params+array('token'=>nrToken($body),'action'=>'writebookkeeping'));}
 $r=nrReport();nrAssert(!$r['issues'],'Native coded journal report unresolved: '.json_encode($r['issues']));nrAssert(count($r['entries'])===24 && count($r['groups'])===6,'Lost/duplicated grouped invoice codes');
 foreach(array('3'=>4000,'31'=>2400,'33'=>1920,'1'=>4000,'11'=>2400,'13'=>1920) as $code=>$tax){nrAssert($r['groups'][$code]['tax']===$tax,'Mixed/credit VAT amount');if(in_array((string)$code,array('3','31','33'),true)){nrAssert($r['groups'][$code]['base']===16000,'Output base amount');}else{nrAssert($r['groups'][$code]['base']===null,'Input base invented');}}
 nrAssert($r['net']===0,'Balanced sale/purchase net');
 // Native separate manual correction reduces the real deductible VAT without rewriting its invoice.
 $piece=(new BookKeeping($db))->getNextNumMvt();foreach(array('2710'=>-12.5,'4000'=>12.5) as $account=>$amount){$o=new BookKeeping($db);$o->entity=$entity;$o->doc_date=dol_mktime(12,0,0,2,28,2038);$o->doc_type='manual';$o->doc_ref='SYNTHETIC VAT CORRECTION '.$run;$o->fk_doc=0;$o->fk_docdet=0;$o->numero_compte=$account;$o->label_compte='Synthetic';$o->label_operation='Synthetic';$o->debit=max($amount,0);$o->credit=max(-$amount,0);$o->montant=abs($amount);$o->sens=$amount>0?'D':'C';$o->code_journal='SYNVR';$o->journal_label='Synthetic';$o->piece_num=$piece;nrAssert($o->createStd($user)>0,'Correction create');$manualIds[]=(int)$o->id;}
 $r=nrReport();nrAssert(nrReason($r,'MissingCode') && $r['net']===null,'Uncoded manual VAT silently excluded');nrBlocked(fn()=>NorwayVatReport::csv($r),'Incomplete CSV allowed');
 $line=NorwayManualVat::lines($db,$entity,$piece,$user)[0];NorwayManualVat::save($db,$entity,$piece,(int)$line->rowid,'1','',NorwayManualVat::editToken($line),$user);
 $r=nrReport();nrAssert(!$r['issues'] && $r['net']===1250 && $r['groups']['1']['tax']===2750,'Manual correction not reflected in report');
 // Native manual output VAT and explicit output base also join the same extract.
 $outputPiece=(new BookKeeping($db))->getNextNumMvt();foreach(array('2701'=>-15,'3000'=>-100,'1920'=>115) as $account=>$amount){$o=new BookKeeping($db);$o->entity=$entity;$o->doc_date=dol_mktime(12,0,0,2,1,2038);$o->doc_type='';$o->doc_ref='SYNTHETIC MANUAL OUTPUT '.$run;$o->fk_doc=0;$o->fk_docdet=0;$o->numero_compte=$account;$o->label_compte='Synthetic';$o->label_operation='Synthetic';$o->debit=max($amount,0);$o->credit=max(-$amount,0);$o->montant=abs($amount);$o->sens=$amount>0?'D':'C';$o->code_journal='SYNVR';$o->journal_label='Synthetic';$o->piece_num=$outputPiece;nrAssert($o->createStd($user)>0,'Manual output create');$manualIds[]=(int)$o->id;}
 $outputLine=NorwayManualVat::lines($db,$entity,$outputPiece,$user)[0];NorwayManualVat::save($db,$entity,$outputPiece,(int)$outputLine->rowid,'31','100',NorwayManualVat::editToken($outputLine),$user);
 $r=nrReport();nrAssert(!$r['issues'] && $r['net']===2750 && $r['groups']['31']['base']===26000 && $r['groups']['31']['tax']===3900,'Manual output base or VAT lost');
 $csv=NorwayVatReport::csv($r);nrAssert(substr_count($csv,"\n")===27 && strpos($csv,'ledger;'.(int)$line->rowid.';'.$piece.';1;input;;;-12.50')!==false,'CSV detail/sign/input fields');
 echo "PASS real native goods/services sales, purchases, mixed 25/15/12, credits, grouped ledger agreement and separate manual deduction correction\n";
 // No persistent snapshots: variants are transactional and report must neither write nor adopt stale source metadata.
 function nrSnapshot(){
  global $db,$p,$invoices,$manualIds;
  $data=array();foreach($invoices as $type=>$objects){$ids=array_map(fn($o)=>(int)$o->id,$objects);$header=$type==='customer_invoice'?'facture':'facture_fourn';$detail=$type==='customer_invoice'?'facturedet':'facture_fourn_det';$fk=$type==='customer_invoice'?'fk_facture':'fk_facture_fourn';
   foreach(array($header=>'rowid IN ('.implode(',',$ids).')',$detail=>$fk.' IN ('.implode(',',$ids).')','accounting_bookkeeping'=>"doc_type='".$type."' AND fk_doc IN (".implode(',',$ids).')') as $table=>$where){$q=nrSql('SELECT * FROM '.$p.$table.' WHERE '.$where.' ORDER BY rowid');while($row=$db->fetch_object($q)){$data[]=(array)$row;}}
  }
  foreach(array('accounting_bookkeeping','norway_manual_vat') as $table){$q=nrSql('SELECT * FROM '.$p.$table.' WHERE '.($table==='accounting_bookkeeping'?'rowid':'fk_bookkeeping').' IN ('.implode(',',$manualIds).')');while($row=$db->fetch_object($q)){$data[]=(array)$row;}}
  $data[]=$db->fetch_object(nrSql('SELECT COUNT(*) AS n FROM '.$p.'norway_tax_evidence'));return hash('sha256',serialize($data));
 }
 $snapshot=nrSnapshot();
 nrAssert(NorwayVatReport::cents(NorwayVatReport::amount('0.00499999'))===0 && NorwayVatReport::cents(NorwayVatReport::amount('0.005'))===1 && NorwayVatReport::cents(NorwayVatReport::amount('-0.005'))===-1,'Exact half-ore arithmetic');
 nrBlocked(fn()=>NorwayVatReport::amount('99999999999'),'Amount overflow accepted');
 $variants=array(
  'Changed'=>"UPDATE ".$p."accounting_bookkeeping SET doc_ref='SYNTHETIC EDITED' WHERE rowid=".(int)$line->rowid,
  'Code'=>"UPDATE ".$p."facturedet SET vat_src_code='' WHERE fk_facture=".(int)$invoices['customer_invoice'][0]->id,
  'Mismatch'=>"UPDATE ".$p."accounting_bookkeeping SET credit=credit+1 WHERE doc_type='customer_invoice' AND fk_doc=".(int)$invoices['customer_invoice'][0]->id." AND numero_compte='2700'",
  'Date'=>"UPDATE ".$p."accounting_bookkeeping SET doc_date='2038-03-01' WHERE doc_type='supplier_invoice' AND fk_doc=".(int)$invoices['supplier_invoice'][0]->id." AND numero_compte='2710'",
  'NotPosted'=>'DELETE FROM '.$p."accounting_bookkeeping WHERE doc_type='customer_invoice' AND fk_doc=".(int)$invoices['customer_invoice'][0]->id,
  'Unsupported'=>"UPDATE ".$p."societe SET fk_pays=1 WHERE rowid=".$partnerId,
 );
 foreach($variants as $reason=>$sql){$db->begin();try{nrSql($sql);$bad=nrReport();nrAssert(nrReason($bad,$reason) && $bad['net']===null,'Unresolved variant accepted: '.$reason);nrBlocked(fn()=>NorwayVatReport::csv($bad),'Unresolved CSV accepted: '.$reason);}finally{$db->rollback();}}
 // Altered source net/gross cannot pass merely by preserving its old tax amount.
 $db->begin();try{$id=(int)$invoices['customer_invoice'][0]->id;nrSql('UPDATE '.$p.'facture SET total_ht=total_ht+100,total_ttc=total_ttc+100 WHERE rowid='.$id);nrSql('UPDATE '.$p.'facturedet SET total_ht=total_ht+100 WHERE fk_facture='.$id.' ORDER BY rowid LIMIT 1');nrAssert(nrReason(nrReport(),'Mismatch'),'Source base drift accepted');}finally{$db->rollback();}
 $db->begin();try{$id=(int)$invoices['customer_invoice'][0]->id;nrSql("UPDATE ".$p."facturedet SET total_ht=total_ht+CASE WHEN vat_src_code='3' THEN 10 WHEN vat_src_code='31' THEN -10 ELSE 0 END WHERE fk_facture=".$id);nrAssert(nrReason(nrReport(),'Mismatch'),'Redistributed base with unchanged tax/header accepted');}finally{$db->rollback();}
 // Multiple rate codes can legitimately share a VAT account without multiplying their amounts.
 $db->begin();try{nrSql("UPDATE ".$p."c_tva SET accountancy_code_sell='2700' WHERE entity=".$entity." AND code IN ('3','31','33')");$docids=implode(',',array_map(fn($o)=>(int)$o->id,$invoices['customer_invoice']));nrSql("UPDATE ".$p."accounting_bookkeeping SET numero_compte='2700' WHERE doc_type='customer_invoice' AND fk_doc IN (".$docids.") AND numero_compte IN ('2701','2703')");$shared=nrReport();nrAssert(!$shared['issues'] && $shared['net']===2750 && $shared['groups']['3']['tax']===4000,'Shared VAT account duplicated/lost codes');}finally{$db->rollback();}
 $db->begin();try{nrSql('UPDATE '.$p.'societe SET entity=2 WHERE rowid='.$partnerId);nrAssert(nrReason(nrReport(),'Unsupported'),'Other-entity source party accepted');$r2=NorwayVatReport::build($db,$entity,'2038-03-01','2038-03-31',$user);nrAssert(!$r2['entries'],'Period leaked entries');}finally{$db->rollback();}
 nrBlocked(fn()=>NorwayVatReport::build($db,$entity+1,$start,$end,$user),'Entity leaked');
 nrBlocked(fn()=>NorwayVatReport::build($db,$entity,'2038-02-30',$end,$user),'Invalid date accepted');
 $reader=new class{public $id=987654;public $socid=0;public function hasRight(...$a){return $a[1]==='mouvements';}};nrBlocked(fn()=>NorwayVatReport::build($db,$entity,$start,$end,$reader),'Report permission not required');
 nrAssert(nrSnapshot()===$snapshot,'Report/variants changed original financial data or metadata');
 $path='/custom/norway/vat.php?date_start='.$start.'&date_end='.$end;
 foreach(array('nb_NO'=>'MVA-uttrekk (ordinære koder)','en_US'=>'VAT extract (ordinary codes)') as $lang=>$title){$body=nrHttp($path.'&lang='.$lang);nrAssert(strpos(html_entity_decode($body,ENT_QUOTES,'UTF-8'),$title)!==false && strpos($body,'format=csv')!==false,'Report HTTP/translation missing');}
 $download=nrHttp($path.'&format=csv');nrAssert(curl_getinfo($curl,CURLINFO_HTTP_CODE)===200 && $download===$csv,'HTTP CSV differs');
 nrAssert(nrSnapshot()===$snapshot,'HTTP report/export changed financial data');
 nrSql("UPDATE ".$p."accounting_bookkeeping SET doc_ref='SYNTHETIC EDITED' WHERE rowid=".(int)$line->rowid);
 $bad=nrHttp($path.'&format=csv');nrAssert(curl_getinfo($curl,CURLINFO_HTTP_CODE)===409 && strpos($bad,'period_start;')===false,'HTTP exported incomplete figures');
 echo "PASS missing/stale codes, amount/source/date/period/foreign gaps, incomplete CSV refusal, rights/entity, bilingual HTTP and read-only export\n";
} finally {
 $db->rollback();
 if($manualIds){nrSql('DELETE FROM '.$p.'norway_manual_vat WHERE entity='.$entity.' AND fk_bookkeeping IN ('.implode(',',$manualIds).')');nrSql('DELETE FROM '.$p.'accounting_bookkeeping WHERE entity='.$entity.' AND rowid IN ('.implode(',',$manualIds).')');}
 foreach($invoices as $type=>$objects){foreach(array_reverse($objects) as $o){$id=(int)$o->id;nrSql('DELETE FROM '.$p."accounting_bookkeeping WHERE entity=".$entity." AND doc_type='".$type."' AND fk_doc=".$id);$header=$type==='customer_invoice'?'facture':'facture_fourn';$detail=$type==='customer_invoice'?'facturedet':'facture_fourn_det';$fk=$type==='customer_invoice'?'fk_facture':'fk_facture_fourn';nrSql('DELETE FROM '.$p.$detail.' WHERE '.$fk.'='.$id);nrSql('DELETE FROM '.$p.$header.'_extrafields WHERE fk_object='.$id);nrSql('DELETE FROM '.$p.$header.' WHERE rowid='.$id);}}
 if($partnerId){nrSql('DELETE FROM '.$p.'societe_extrafields WHERE fk_object='.$partnerId);nrSql('DELETE FROM '.$p.'societe WHERE rowid='.$partnerId);}
 if($journalIds){nrSql('DELETE FROM '.$p.'accounting_journal WHERE rowid IN ('.implode(',',$journalIds).')');}
 if($newAccounts){nrSql('DELETE FROM '.$p.'accounting_account WHERE rowid IN ('.implode(',',$newAccounts).')');}
 nrSql('DELETE FROM '.$p.'c_tva WHERE entity='.$entity.' AND code IN ('.$codes.')');nrRestoreRows($p.'c_tva',$oldRates);
 foreach($settings as $name=>$v){nrSql('DELETE FROM '.$p."const WHERE entity=".$entity." AND name='".$name."'");}nrRestoreRows($p.'const',$oldSettings);
 $conf->global=$oldGlobals;$mysoc=$oldCompany;curl_close($curl);unlink($cookie);
}
