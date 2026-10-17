<?php
// SPDX-License-Identifier: GPL-3.0-or-later
if(PHP_SAPI!=='cli'||getenv('NORWAY_SYNTHETIC_TEST')!=='1'||getenv('DOLI_DB_NAME')!=='norway_test'||getenv('DOLI_COMPANY_NAME')!=='SYNTHETIC NORWAY TEST ONLY'){exit(1);}
require '/var/www/html/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';require_once DOL_DOCUMENT_ROOT.'/accountancy/class/accountingaccount.class.php';require_once DOL_DOCUMENT_ROOT.'/accountancy/class/bookkeeping.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/norway/core/modules/modNorway.class.php';require_once DOL_DOCUMENT_ROOT.'/custom/norway/class/NorwayVatReport.php';
function nrAssert($ok,$m){if(!$ok){throw new RuntimeException($m);}}
function nrSql($sql){global $db;$r=$db->query($sql);nrAssert((bool)$r,'Synthetic VAT report SQL: '.$db->lasterror());return $r;}
function nrBlocked($f,$m){$blocked=false;try{$f();}catch(RuntimeException $e){$blocked=true;}nrAssert($blocked,$m);}
function nrReason($report,$reason){return count(array_filter($report['issues'],fn($i)=>$i['reason']===$reason))>0;}
nrAssert($user->fetch('','testadmin')>0,'Admin missing');$mod=new modNorway($db);nrAssert($mod->init()>0,$mod->error);$conf->setValues($db);$user->loadRights('',1);
$p=$db->prefix();$entity=(int)$conf->entity;$run=bin2hex(random_bytes(4));$start='2038-02-01';$end='2038-02-28';
$oldGlobals=clone $conf->global;$oldCompany=clone $mysoc;$oldRates=array();$oldSettings=array();$invoices=array('customer_invoice'=>array(),'supplier_invoice'=>array());$manualIds=array();$accountIds=array();$newAccounts=array();$journalIds=array();$partnerId=0;$foreignParties=array();$exportInvoices=array();$pdfDirectories=array();$reverseInvoices=array();$reverseProduct=0;
$settings=array('ACCOUNTING_VAT_BUY_REVERSE_CHARGES_CREDIT'=>'2704','ACCOUNTING_VAT_BUY_REVERSE_CHARGES_DEBIT'=>'2714','ACCOUNTING_FORCE_ENABLE_VAT_REVERSE_CHARGE'=>'1','ACCOUNTING_REVERSE_CHARGE_ALSO_NON_EEC'=>'1','ACCOUNTANCY_FISCAL_PERIOD_MODE'=>'blockedonclosed','ACCOUNTING_ACCOUNT_CUSTOMER'=>'1500','ACCOUNTING_ACCOUNT_SUPPLIER'=>'2400','ACCOUNTING_PRODUCT_SOLD_ACCOUNT'=>'3000','ACCOUNTING_SERVICE_SOLD_ACCOUNT'=>'3000','ACCOUNTING_PRODUCT_BUY_ACCOUNT'=>'4000','ACCOUNTING_SERVICE_BUY_ACCOUNT'=>'4000','ACCOUNTING_VAT_SOLD_ACCOUNT'=>'2700','ACCOUNTING_VAT_BUY_ACCOUNT'=>'2710');
$codes="'86','52','NO0','1','11','13','3','31','33','5','6','7'";$r=nrSql('SELECT * FROM '.$p.'c_tva WHERE entity='.$entity.' AND code IN ('.$codes.')');while($row=$db->fetch_object($r)){$oldRates[]=(array)$row;}
foreach($settings as $name=>$v){$r=nrSql('SELECT * FROM '.$p."const WHERE entity=".$entity." AND name='".$name."'");while($row=$db->fetch_object($r)){$oldSettings[]=(array)$row;}}
$cookie=tempnam(sys_get_temp_dir(),'norway-vat-report-');$curl=curl_init();curl_setopt_array($curl,array(CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_TIMEOUT=>30));
function nrHttp($path,$post=null){global $curl;curl_setopt($curl,CURLOPT_URL,'http://localhost'.$path);curl_setopt($curl,CURLOPT_POST,$post!==null);if($post!==null){curl_setopt($curl,CURLOPT_POSTFIELDS,http_build_query($post));}$b=curl_exec($curl);nrAssert($b!==false,'HTTP failed');return $b;}
function nrToken($body){nrAssert(preg_match('/name="token"[^>]*value="([^"]+)"/',$body,$m)===1,'Token missing');return html_entity_decode($m[1]);}
// Inspect real PDF content streams, including UTF-16 text, without external binaries.
function nrPdfContains($path,$expected){
 nrAssert(is_file($path),'Export PDF missing');$pdf=file_get_contents($path);nrAssert(strpos($pdf,'%PDF-')===0,'Invalid export PDF');$parts=array();
 preg_match_all('/\/Length\s+([0-9]+)\s*>>\s*stream(?:\r\n|\n|\r)/',$pdf,$streams,PREG_OFFSET_CAPTURE);
 foreach($streams[0] as $i=>$header){$stream=substr($pdf,$header[1]+strlen($header[0]),(int)$streams[1][$i][0]);$decoded=@gzuncompress($stream);$parts[]=$decoded!==false?$decoded:$stream;}
 foreach($expected as $text){$unicode=mb_convert_encoding($text,'UTF-16BE','UTF-8');$found=false;foreach($parts as $part){if(strpos($part,$text)!==false || strpos($part,$unicode)!==false || stripos($part,bin2hex($unicode))!==false){$found=true;break;}}nrAssert($found,'Export PDF text missing: '.$text);}
}
function nrReport(){global $db,$entity,$start,$end,$user;return NorwayVatReport::build($db,$entity,$start,$end,$user);}
function nrRestoreRows($table,$rows){global $db;foreach($rows as $row){$values=array_map(fn($v)=>$v===null?'NULL':"'".$db->escape((string)$v)."'",array_values($row));nrSql('INSERT INTO '.$table.' ('.implode(',',array_keys($row)).') VALUES ('.implode(',',$values).')');}}
try {
 foreach($settings as $name=>$v){nrAssert(dolibarr_set_const($db,$name,$v,'chaine',0,'',$entity)>0,'Test setting');$conf->global->$name=$v;}
 $private=nrHttp('/custom/norway/vat.php?date_start='.$start.'&date_end='.$end.'&format=csv');nrAssert(strpos($private,'name="username"')!==false && strpos($private,'period_start;')===false,'Anonymous CSV exposed');
 $body=nrHttp('/index.php');nrHttp('/index.php',array('token'=>nrToken($body),'actionlogin'=>'login','loginfunction'=>'loginfunction','username'=>'testadmin','password'=>getenv('DOLI_ADMIN_PASSWORD')));
 $mysoc->country_code='NO';$mysoc->country_id=173;$mysoc->tva_assuj=1;$mysoc->idprof1='123456785';$mysoc->address='Synthetic address';$mysoc->zip='0000';$mysoc->town='Synthetic';
 $chart=$db->fetch_object(nrSql('SELECT pcg_version FROM '.$p.'accounting_system WHERE rowid='.(int)getDolGlobalString('CHARTOFACCOUNTS')));nrAssert((bool)$chart,'Chart missing');
 foreach(array('1500','2400','3000','3105','3125','4000','1920','2700','2701','2703','2704','2710','2711','2713','2714','6790') as $number){$row=$db->fetch_object(nrSql('SELECT rowid FROM '.$p.'accounting_account WHERE entity='.$entity." AND fk_pcg_version='".$db->escape($chart->pcg_version)."' AND account_number='".$number."'"));if($row){$accountIds[$number]=(int)$row->rowid;continue;}
  $a=new AccountingAccount($db);$a->fk_pcg_version=$chart->pcg_version;$a->account_number=$number;$a->label='Synthetic '.$number;$a->active=1;nrAssert($a->create($user)>0,'Account create');$accountIds[$number]=(int)$a->id;$newAccounts[]=(int)$a->id;
 }
 nrSql('DELETE FROM '.$p.'c_tva WHERE entity='.$entity.' AND code IN ('.$codes.')');
 foreach(NorwayVatRates::codes() as $s){$account=($s['type']===1?'270':'271').array(25=>'0',15=>'1',12=>'3')[$s['rate']];nrSql("INSERT INTO ".$p."c_tva (entity,fk_pays,code,type_vat,taux,recuperableonly,localtax1,localtax2,active,accountancy_code_sell,accountancy_code_buy) VALUES (".$entity.",173,'".$s['code']."',".$s['type'].",".$s['rate'].",0,0,0,1,'".($s['type']===1?$account:'')."','".($s['type']===2?$account:'')."')");}
 foreach(NorwayVatRates::zeroCodes() as $s){nrSql("INSERT INTO ".$p."c_tva (entity,fk_pays,code,type_vat,taux,recuperableonly,localtax1,localtax2,active) VALUES (".$entity.",173,'".$s['code']."',".$s['type'].",0,0,0,0,1)");}
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
  if($sale){foreach(array('5','6','7') as $code){nrAssert($o->addline('Synthetic domestic treatment '.$code,$type===2?20.10:100.10,1,'0 ('.$code.')',0,0,0,0,'','',$accountIds['3000'],0,0,'HT',0,$productType)>0,'Native zero line');}}
  if(!$sale){nrAssert($o->addline('Synthetic purchase without VAT',$type===2?-20.10:100.10,'0 (NO0)',0,0,1,0,0,0,0,$accountIds['4000'],0,'HT',$productType)>0,'Native purchase zero');}
  nrAssert($o->validate($user,$sale?'':'SYNVR-'.$run.'-'.$productType.'-'.$type)>0,'Validate: '.$o->error);nrAssert($o->fetch($o->id)>0,'Reload');
 }}}
 // Pure zero-tax invoices have no VAT ledger line: all three classifications must still reconcile.
 foreach(array(0,2) as $type){$o=new Facture($db);$o->socid=$partnerId;$o->date=dol_mktime(12,0,0,2,10,2038);$o->type=$type;$o->multicurrency_code='NOK';$o->multicurrency_tx=1;if($type===2){$o->fk_facture_source=end($invoices['customer_invoice'])->id;}
  nrAssert($o->create($user)>0,'Pure zero invoice');$invoices['customer_invoice'][]=$o;
  foreach(array('5','6','7') as $code){nrAssert($o->addline('Synthetic zero '.$code,$type===2?20:100,1,'0 ('.$code.')',0,0,0,0,'','',$accountIds['3000'])>0,'Pure zero line');}
  nrAssert($o->validate($user)>0,'Pure zero validation: '.$o->error);nrAssert($o->fetch($o->id)>0,'Pure zero fetch');
 }
 foreach(array(0,2) as $type){$o=new FactureFournisseur($db);$o->socid=$partnerId;$o->date=dol_mktime(12,0,0,2,10,2038);$o->type=$type;$o->multicurrency_code='NOK';$o->multicurrency_tx=1;$o->ref_supplier='SYNVR-ZERO-'.$run.'-'.$type;
  if($type===2){$o->fk_facture_source=end($invoices['supplier_invoice'])->id;}
  nrAssert($o->create($user)>0,'Pure purchase zero create');$invoices['supplier_invoice'][]=$o;
  nrAssert($o->addline('Synthetic purchase without VAT',$type===2?-20:100,'0 (NO0)',0,0,1,0,0,0,0,$accountIds['4000'])>0,'Pure purchase zero line');
  nrAssert($o->validate($user,$o->ref_supplier)>0,'Pure purchase zero validation: '.$o->error);nrAssert($o->fetch($o->id)>0,'Pure purchase zero fetch');
 }
 // Explicit export classification, not a country-based zero-rate default.
 foreach(array('SE','US') as $countryCode){$country=$db->fetch_object(nrSql("SELECT rowid FROM ".$p."c_country WHERE code='".$countryCode."'"));nrAssert((bool)$country,'Foreign country missing');
  $foreign=new Societe($db);$foreign->name='SYNTHETIC EXPORT ONLY '.$countryCode.' '.$run;$foreign->client=1;$foreign->fournisseur=1;$foreign->code_fournisseur='SYNRC-'.$countryCode.'-'.$run;$foreign->code_client='SYNEXP-'.$countryCode.'-'.$run;$foreign->country_id=(int)$country->rowid;$foreign->tva_assuj=1;$foreign->address='Synthetic export address';$foreign->zip='00000';$foreign->town='Synthetic export town';nrAssert($foreign->create($user)>0,'Export party');$foreignParties[]=(int)$foreign->id;
  foreach(array(0,1) as $productType){foreach(array(0,2) as $type){$o=new Facture($db);$o->socid=$foreign->id;$o->date=dol_mktime(12,0,0,2,10,2038);$o->type=$type;$o->multicurrency_code='NOK';$o->multicurrency_tx=1;if($type===2){$o->fk_facture_source=end($exportInvoices)->id;}
   $o->note_public=$productType===0?'Export of goods, VAT exempt under Norwegian VAT Act section 6-21. Synthetic export evidence.':'Export of remotely deliverable services, VAT exempt under Norwegian VAT Act section 6-22(2). Synthetic recipient evidence.';
   nrAssert($o->create($user)>0,'Export create');$invoices['customer_invoice'][]=$o;$exportInvoices[]=$o;
   nrAssert($o->addline('Synthetic export goods/services',$type===2?20.10:100.10,1,'0 (52)',0,0,0,0,'','',$accountIds[$productType===0?'3105':'3125'],0,0,'HT',0,$productType)>0,'Export line');
   nrAssert($o->validate($user)>0,'Export validation: '.$o->error);nrAssert($o->fetch($o->id)>0 && $o->lines[0]->vat_src_code==='52','Export code lost');
  }}
 }
 $product=new Product($db);$product->ref='SYNRC-'.$run;$product->label='Synthetic remotely deliverable service';$product->type=1;$product->status=1;$product->status_buy=1;$product->tva_tx=25;$product->default_vat_code='3';$product->price=100;$product->price_base_type='HT';$product->accountancy_code_buy='6790';nrAssert($product->create($user)>0,'Reverse-charge service create: '.$product->error);$reverseProduct=(int)$product->id;
 foreach($foreignParties as $supplier){$original=0;foreach(array(0,2) as $type){$o=new FactureFournisseur($db);$o->socid=$supplier;$o->ref_supplier='SYNRC-'.$run.'-'.$supplier.'-'.$type;$o->date=dol_mktime(12,0,0,2,10,2038);$o->type=$type;$o->multicurrency_code='NOK';$o->multicurrency_tx=1;$o->vat_reverse_charge=1;if($type===2){$o->fk_facture_source=$original;}
  nrAssert($o->create($user)>0,'Foreign purchase create');$invoices['supplier_invoice'][]=$o;$reverseInvoices[]=$o;if(!$original){$original=(int)$o->id;}
  foreach($type===2?array(-20.10,-10.02):array(100.10,50.02) as $net){nrAssert($o->addline('Synthetic reverse-charge service',$net,'0 (86)',0,0,1,$reverseProduct,0,0,0,$accountIds['6790'],0,'HT',1)>0,'Reverse-charge line');}
  if(count($reverseInvoices)===1){$states=array();$q=nrSql("SELECT rowid,active FROM ".$p."c_tva WHERE entity=".$entity." AND fk_pays=173 AND taux=0 AND code<>'86'");while($state=$db->fetch_object($q)){$states[(int)$state->rowid]=(int)$state->active;}
   try{foreach($states as $id=>$active){nrSql('UPDATE '.$p.'c_tva SET active=0 WHERE rowid='.$id);}$only=nrHttp('/fourn/facture/card.php?facid='.(int)$o->id);nrAssert(preg_match('/<option[^>]*value="0(?:\.0+)? \(86\)"[^>]*selected/',$only)!==1 && preg_match('/<option value="0" selected>0%<\/option>/',$only)===1,'Code 86 became an implicit browser default');}
   finally{foreach($states as $id=>$active){nrSql('UPDATE '.$p.'c_tva SET active='.$active.' WHERE rowid='.$id);}}
  }
  nrAssert($o->fetch($o->id)>0,'Reverse-charge draft fetch');$card=nrHttp('/fourn/facture/card.php?facid='.(int)$o->id);nrAssert(strpos($card,'(86)')!==false && preg_match('/<option[^>]*value="0(?:\.0+)? \(86\)"[^>]*selected/',$card)!==1,'Reverse charge silently preselected');$body=nrHttp('/fourn/facture/card.php?facid='.(int)$o->id.'&action=editline&lineid='.(int)$o->lines[0]->id.'&token='.urlencode(nrToken($card)));nrAssert(preg_match('/<option[^>]*value="0(?:\.0+)? \(86\)"[^>]*selected/',$body)===1,'Native foreign purchase edit lost code 86');
  nrAssert($o->validate($user,$o->ref_supplier)>0,'Reverse-charge validation: '.$o->error);nrAssert($o->fetch($o->id)>0,'Reverse-charge fetch');
 }}
 foreach($exportInvoices as $o){foreach(array('nb_NO','en_US') as $language){$outputlangs=new Translate('',$conf);$outputlangs->setDefaultLang($language);$outputlangs->loadLangs(array('main','bills'));
  nrAssert($o->generateDocument('sponge',$outputlangs)>0,'Export PDF generation: '.$o->error);$directory=$conf->facture->dir_output.'/'.dol_sanitizeFileName($o->ref);$pdfDirectories[$directory]=true;
  nrPdfContains($directory.'/'.dol_sanitizeFileName($o->ref).'.pdf',array('Synthetic export address','Synthetic export town',$outputlangs->transnoentitiesnoconv('CurrencyNOK'),$outputlangs->transnoentities((int)$o->type===2?'InvoiceAvoir':'PdfInvoiceTitle'),(int)$o->lines[0]->product_type===0?'6-21':'6-22'));
 }}
 echo "PASS native export invoice/credit PDFs in Norwegian/English, NOK, recipient address and explicit goods/services exemption note\n";
 foreach(array(2=>'sellsjournal',3=>'purchasesjournal') as $nature=>$journal){$params=array('id_journal'=>$journalIds[$nature],'date_startday'=>1,'date_startmonth'=>2,'date_startyear'=>2038,'date_endday'=>28,'date_endmonth'=>2,'date_endyear'=>2038,'in_bookkeeping'=>'notyet');$path='/accountancy/journal/'.$journal.'.php?'.http_build_query($params);$body=nrHttp($path);nrHttp($path,$params+array('token'=>nrToken($body),'action'=>'writebookkeeping'));}
 foreach($exportInvoices as $o){$booked=array();$rows=nrSql('SELECT numero_compte,debit,credit FROM '.$p."accounting_bookkeeping WHERE entity=".$entity." AND doc_type='customer_invoice' AND fk_doc=".(int)$o->id);while($line=$db->fetch_object($rows)){$booked[$line->numero_compte]=($booked[$line->numero_compte]??0)+NorwayVatReport::cents(NorwayVatReport::amount($line->credit)-NorwayVatReport::amount($line->debit));}
  $income=(int)$o->lines[0]->product_type===0?'3105':'3125';$net=(int)$o->type===2?-2010:10010;nrAssert(count($booked)===2 && ($booked[$income]??null)===$net && ($booked['1500']??null)===-$net,'Export journal income/reskontra/VAT accounts');
 }
 foreach($reverseInvoices as $o){$booked=array();$rows=nrSql('SELECT numero_compte,debit,credit FROM '.$p."accounting_bookkeeping WHERE entity=".$entity." AND doc_type='supplier_invoice' AND fk_doc=".(int)$o->id);while($line=$db->fetch_object($rows)){$booked[$line->numero_compte]=($booked[$line->numero_compte]??0)+NorwayVatReport::cents(NorwayVatReport::amount($line->debit)-NorwayVatReport::amount($line->credit));}
  $base=(int)$o->type===2?-3012:15012;$tax=(int)$o->type===2?-754:3754;nrAssert(count($booked)===4 && ($booked['6790']??null)===$base && ($booked['2400']??null)===-$base && ($booked['2714']??null)===$tax && ($booked['2704']??null)===-$tax,'Native reverse charge accounts: '.json_encode($booked));
 }
 $r=nrReport();nrAssert(!$r['issues'],'Native coded journal report unresolved: '.json_encode($r['issues']));nrAssert(count($r['entries'])===64 && count($r['groups'])===13,'Lost/duplicated grouped invoice codes');
 foreach(array('3'=>4000,'31'=>2400,'33'=>1920,'1'=>4000,'11'=>2400,'13'=>1920) as $code=>$tax){nrAssert($r['groups'][$code]['tax']===$tax,'Mixed/credit VAT amount');if(in_array((string)$code,array('3','31','33'),true)){nrAssert($r['groups'][$code]['base']===16000,'Output base amount');}else{nrAssert($r['groups'][$code]['base']===null,'Input base invented');}}
 foreach(array('5','6','7') as $code){nrAssert($r['groups'][$code]['base']===24000 && $r['groups'][$code]['tax']===0 && $r['groups'][$code]['reported']===($code!=='7'),'Zero classification lost');}
 nrAssert($r['groups']['0']['base']===24000 && $r['groups']['0']['tax']===0 && !$r['groups']['0']['reported'],'Purchase zero classification lost');
 nrAssert($r['groups']['52']['base']===32000 && $r['groups']['52']['tax']===0 && $r['groups']['52']['reported'],'Export base/credit lost');
 nrAssert($r['groups']['86/1']['base']===24000 && $r['groups']['86/1']['tax']===6000 && $r['groups']['86/2']['base']===null && $r['groups']['86/2']['tax']===6000,'Reverse-charge output/deduction lost or doubled');
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
 // Whole manual purchases without deduction: cost includes VAT, no supplier-invoice object is posted twice.
 $manual87=array();foreach($foreignParties as $supplier){foreach(array(1,-1) as $sign){$base=$sign===1?100:-20;$tax=$base*0.25;$manualPiece=(new BookKeeping($db))->getNextNumMvt();
  foreach(array('6790'=>$base+$tax,'2400'=>-$base,'2704'=>-$tax) as $account=>$amount){$o=new BookKeeping($db);$o->entity=$entity;$o->doc_date=dol_mktime(12,0,0,2,10,2038);$o->doc_type='manual';$o->doc_ref='SYNTHETIC FOREIGN PURCHASE '.$run.' '.$supplier.' '.$sign;$o->fk_doc=0;$o->fk_docdet=0;$o->numero_compte=$account;$o->label_compte='Synthetic purchase';$o->label_operation='Synthetic foreign service, no deduction';$o->debit=max($amount,0);$o->credit=max(-$amount,0);$o->montant=abs($amount);$o->sens=$amount>0?'D':'C';$o->piece_num=$manualPiece;$o->code_journal='SYNVR';$o->journal_label='Synthetic manual purchases';nrAssert($o->createStd($user)>0,'Manual purchase create');$manualIds[]=(int)$o->id;if((string)$account==='2704'){$manual87[]=(int)$o->id;}}
  $r=nrReport();nrAssert(nrReason($r,'MissingCode') && $r['net']===null,'Unclassified manual reverse charge hidden');
  foreach(NorwayManualVat::lines($db,$entity,$manualPiece,$user) as $row){if($row->numero_compte==='2704'){NorwayManualVat::save($db,$entity,$manualPiece,(int)$row->rowid,'87',(string)$base,NorwayManualVat::editToken($row),$user);}}
 }}
 $r=nrReport();nrAssert(!$r['issues'] && $r['groups']['87']['base']===16000 && $r['groups']['87']['tax']===4000 && $r['groups']['87']['type']===1 && $r['net']===6750,'Manual code 87 base, liability, credit or no deduction lost');
 $choices87=NorwayManualVat::choices($db,$entity);nrAssert(isset($choices87['87']) && $choices87['87']['account']==='2704','Manual 87 choice missing');
 echo "PASS native complete manual service purchases/credits without deduction, 6790/2400/2704, code 87 base/liability and unchanged original invoices\n";
 $csv=NorwayVatReport::csv($r);nrAssert(substr_count($csv,"\n")===71 && strpos($csv,'ledger;'.(int)$line->rowid.';'.$piece.';1;input;;;-12.50')!==false,'CSV detail/sign/input fields');
 $csvRows=array_map(fn($s)=>str_getcsv($s,';','"',''),explode("\n",trim($csv)));$outside=array_filter(array_slice($csvRows,1),fn($row)=>$row[5]==='7');nrAssert(count($outside)===6,'Code 7 CSV lost');
 foreach($outside as $row){nrAssert($row[7]==='' && $row[8]==='' && $row[9]==='0.00' && $row[10]==='excluded' && $row[11]!=='','Code 7 became VAT return basis');}
 $purchaseZero=array_filter(array_slice($csvRows,1),fn($row)=>$row[5]==='0');nrAssert(count($purchaseZero)===6,'Purchase zero CSV lost');$zeroBase=0;
 foreach($purchaseZero as $row){nrAssert($row[6]==='input' && $row[7]==='' && $row[8]==='' && $row[9]==='0.00' && $row[10]==='excluded' && $row[11]!=='','Purchase zero became VAT return deduction/base');$zeroBase+=(int)round((float)$row[11]*100);}
 nrAssert($zeroBase===24000,'Purchase zero invoice/credit signs');
 $exportRows=array_filter(array_slice($csvRows,1),fn($row)=>$row[5]==='52');nrAssert(count($exportRows)===8,'Export CSV lost');
 foreach($exportRows as $row){nrAssert($row[6]==='output' && $row[7]==='0' && $row[8]!=='' && $row[9]==='0.00' && $row[10]==='included' && $row[11]==='','Export became excluded/non-zero VAT');}
 $reverseRows=array_filter(array_slice($csvRows,1),fn($row)=>$row[5]==='86');nrAssert(count($reverseRows)===8,'Reverse-charge CSV rows');
 foreach($reverseRows as $row){nrAssert($row[10]==='included' && $row[11]==='' && ($row[6]==='output'?($row[7]==='25' && $row[8]!==''):($row[7]==='' && $row[8]==='')),'Reverse-charge CSV direction/base');}
 $manualRows87=array_filter(array_slice($csvRows,1),fn($row)=>$row[5]==='87');nrAssert(count($manualRows87)===4,'Manual 87 CSV rows');
 foreach($manualRows87 as $row){nrAssert($row[2]==='ledger' && $row[6]==='output' && $row[7]==='25' && $row[8]!=='' && $row[10]==='included' && $row[11]==='','Code 87 became an input deduction');}
 echo "PASS native reverse-charge service invoices/credits, SE/US, 2704/2714/6790/2400, per-line rounding, code 86 output plus deduction and unchanged net VAT\n";
 echo "PASS explicit export 52, SE/US goods/services invoices and credits, native journal accounts, included CSV base and unchanged net VAT\n";
 echo "PASS purchase code 0, native pure/mixed goods/services invoices and credits, zero VAT, excluded CSV base and no deduction\n";
 echo "PASS domestic native zero codes 5/6/7, pure/mixed invoices and credits, no VAT ledger line required, code 7 separated in CSV\n";
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
 foreach(array(
  array('Unsupported',"UPDATE ".$p."facture_fourn_det SET vat_src_code='87' WHERE fk_facture_fourn=".(int)$reverseInvoices[0]->id),
  array('Changed',"UPDATE ".$p."accounting_bookkeeping SET doc_ref='SYNTHETIC CHANGED' WHERE rowid=".$manual87[0]),
  array('MissingCode',"UPDATE ".$p."accounting_account SET active=0 WHERE entity=".$entity." AND rowid=".$accountIds['2704']),
  array('Unsupported',"UPDATE ".$p."facture_fourn SET vat_reverse_charge=0 WHERE rowid=".(int)$reverseInvoices[0]->id),
  array('Unsupported',"UPDATE ".$p."facture_fourn_det SET product_type=0 WHERE fk_facture_fourn=".(int)$reverseInvoices[0]->id),
  array('Unsupported',"UPDATE ".$p."facture_fourn_det SET fk_product=NULL WHERE fk_facture_fourn=".(int)$reverseInvoices[0]->id),
  array('Code',"UPDATE ".$p."c_tva SET active=0 WHERE entity=".$entity." AND code='86'"),
  array('Code',"UPDATE ".$p."facture_fourn_det SET vat_src_code='' WHERE fk_facture_fourn=".(int)$reverseInvoices[0]->id),
  array('Mismatch',"UPDATE ".$p."facture_fourn_det SET tva=0.00001 WHERE fk_facture_fourn=".(int)$reverseInvoices[0]->id),
  array('Mismatch',"UPDATE ".$p."accounting_bookkeeping SET debit=debit-1 WHERE entity=".$entity." AND doc_type='supplier_invoice' AND fk_doc=".(int)$reverseInvoices[0]->id." AND numero_compte='2714'"),
  array('Unsupported',"UPDATE ".$p."societe SET fk_pays=173 WHERE rowid=".$foreignParties[0]),
  array('Unsupported',"UPDATE ".$p."societe SET fk_pays=NULL WHERE rowid=".$foreignParties[0]),
  array('Code',"UPDATE ".$p."c_tva SET active=0 WHERE entity=".$entity." AND code='52'"),
  array('Code',"UPDATE ".$p."c_tva SET type_vat=2 WHERE entity=".$entity." AND code='52'"),
  array('Mismatch',"UPDATE ".$p."facturedet SET total_tva=0.00001 WHERE vat_src_code='52' AND fk_facture=".(int)$exportInvoices[0]->id),
  array('Code',"UPDATE ".$p."facturedet SET vat_src_code='' WHERE fk_facture=".(int)$exportInvoices[0]->id),
  array('Unsupported',"UPDATE ".$p."facturedet SET vat_src_code='5' WHERE fk_facture=".(int)$exportInvoices[0]->id),
  array('Code',"UPDATE ".$p."c_tva SET active=0 WHERE entity=".$entity." AND code='NO0'"),
  array('Code',"UPDATE ".$p."c_tva SET type_vat=1 WHERE entity=".$entity." AND code='NO0'"),
  array('Code',"UPDATE ".$p."facture_fourn_det SET vat_src_code='' WHERE vat_src_code='NO0' AND fk_facture_fourn=".(int)$invoices['supplier_invoice'][0]->id),
  array('Mismatch',"UPDATE ".$p."facture_fourn_det SET tva=0.00001 WHERE vat_src_code='NO0' AND fk_facture_fourn=".(int)$invoices['supplier_invoice'][0]->id),
  array('Code',"UPDATE ".$p."c_tva SET type_vat=2 WHERE entity=".$entity." AND code='5'"),
  array('Code',"UPDATE ".$p."c_tva SET active=0 WHERE entity=".$entity." AND code='6'"),
  array('Code',"INSERT INTO ".$p."c_tva (entity,fk_pays,code,type_vat,taux,active) VALUES (".$entity.",173,'7',1,25,1)"),
  array('Mismatch',"UPDATE ".$p."facturedet SET total_tva=0.00001 WHERE vat_src_code='5' AND fk_facture=".(int)$invoices['customer_invoice'][0]->id),
  array('Code',"UPDATE ".$p."facturedet SET tva_tx=25 WHERE vat_src_code='6' AND fk_facture=".(int)$invoices['customer_invoice'][0]->id),
 ) as $case){$db->begin();try{nrSql($case[1]);$bad=nrReport();nrAssert(nrReason($bad,$case[0]) && $bad['net']===null,'Invalid zero treatment accepted');nrBlocked(fn()=>NorwayVatReport::csv($bad),'Invalid zero CSV accepted');}finally{$db->rollback();}}
 $registered=$mysoc->tva_assuj;try{$mysoc->tva_assuj=0;$bad=nrReport();nrAssert(nrReason($bad,'Unsupported') && $bad['net']===null,'Unregistered company accepted ordinary reverse-charge return');}finally{$mysoc->tva_assuj=$registered;}
 // Altered source net/gross cannot pass merely by preserving its old tax amount.
 $db->begin();try{$id=(int)$invoices['customer_invoice'][0]->id;nrSql('UPDATE '.$p.'facture SET total_ht=total_ht+100,total_ttc=total_ttc+100 WHERE rowid='.$id);nrSql('UPDATE '.$p.'facturedet SET total_ht=total_ht+100 WHERE fk_facture='.$id.' ORDER BY rowid LIMIT 1');nrAssert(nrReason(nrReport(),'Mismatch'),'Source base drift accepted');}finally{$db->rollback();}
 $db->begin();try{$id=(int)$invoices['customer_invoice'][0]->id;nrSql("UPDATE ".$p."facturedet SET total_ht=total_ht+CASE WHEN vat_src_code='3' THEN 10 WHEN vat_src_code='31' THEN -10 ELSE 0 END WHERE fk_facture=".$id);nrAssert(nrReason(nrReport(),'Mismatch'),'Redistributed base with unchanged tax/header accepted');}finally{$db->rollback();}
 // Multiple rate codes can legitimately share a VAT account without multiplying their amounts.
 $db->begin();try{nrSql("UPDATE ".$p."c_tva SET accountancy_code_sell='2700' WHERE entity=".$entity." AND code IN ('3','31','33')");$docids=implode(',',array_map(fn($o)=>(int)$o->id,$invoices['customer_invoice']));nrSql("UPDATE ".$p."accounting_bookkeeping SET numero_compte='2700' WHERE doc_type='customer_invoice' AND fk_doc IN (".$docids.") AND numero_compte IN ('2701','2703')");$shared=nrReport();nrAssert(!$shared['issues'] && $shared['net']===6750 && $shared['groups']['3']['tax']===4000,'Shared VAT account duplicated/lost codes');}finally{$db->rollback();}
 $db->begin();try{nrSql('UPDATE '.$p.'societe SET entity=2 WHERE rowid='.$partnerId);nrAssert(nrReason(nrReport(),'Unsupported'),'Other-entity source party accepted');$r2=NorwayVatReport::build($db,$entity,'2038-03-01','2038-03-31',$user);nrAssert(!$r2['entries'],'Period leaked entries');}finally{$db->rollback();}
 nrBlocked(fn()=>NorwayVatReport::build($db,$entity+1,$start,$end,$user),'Entity leaked');
 nrBlocked(fn()=>NorwayVatReport::build($db,$entity,'2038-02-30',$end,$user),'Invalid date accepted');
 $reader=new class{public $id=987654;public $socid=0;public function hasRight(...$a){return $a[1]==='mouvements';}};nrBlocked(fn()=>NorwayVatReport::build($db,$entity,$start,$end,$reader),'Report permission not required');
 nrAssert(nrSnapshot()===$snapshot,'Report/variants changed original financial data or metadata');
 $path='/custom/norway/vat.php?date_start='.$start.'&date_end='.$end;
 foreach(array('nb_NO'=>'MVA-uttrekk (støttede koder)','en_US'=>'VAT extract (supported codes)') as $lang=>$title){$body=nrHttp($path.'&lang='.$lang);nrAssert(strpos(html_entity_decode($body,ENT_QUOTES,'UTF-8'),$title)!==false && strpos($body,'format=csv')!==false,'Report HTTP/translation missing');}
 foreach($manual87 as $id){$row=$db->fetch_object(nrSql('SELECT piece_num FROM '.$p.'accounting_bookkeeping WHERE rowid='.$id));$body=nrHttp('/accountancy/bookkeeping/card.php?piece_num='.(int)$row->piece_num.'&lang=nb_NO');nrAssert(strpos($body,'value="87" selected')!==false && strpos($body,'value="87" selected>87')!==false,'Native manual 87 choice/base missing');}
 $download=nrHttp($path.'&format=csv');nrAssert(curl_getinfo($curl,CURLINFO_HTTP_CODE)===200 && $download===$csv,'HTTP CSV differs');
 nrAssert(nrSnapshot()===$snapshot,'HTTP report/export changed financial data');
 nrSql("UPDATE ".$p."accounting_bookkeeping SET doc_ref='SYNTHETIC EDITED' WHERE rowid=".(int)$line->rowid);
 $bad=nrHttp($path.'&format=csv');nrAssert(curl_getinfo($curl,CURLINFO_HTTP_CODE)===409 && strpos($bad,'period_start;')===false,'HTTP exported incomplete figures');
 echo "PASS missing/stale codes, amount/source/date/period/foreign gaps, incomplete CSV refusal, rights/entity, bilingual HTTP and read-only export\n";
} finally {
 $db->rollback();
 if($manualIds){nrSql('DELETE FROM '.$p.'norway_manual_vat WHERE entity='.$entity.' AND fk_bookkeeping IN ('.implode(',',$manualIds).')');nrSql('DELETE FROM '.$p.'accounting_bookkeeping WHERE entity='.$entity.' AND rowid IN ('.implode(',',$manualIds).')');}
 foreach($invoices as $type=>$objects){foreach(array_reverse($objects) as $o){$id=(int)$o->id;nrSql('DELETE FROM '.$p."accounting_bookkeeping WHERE entity=".$entity." AND doc_type='".$type."' AND fk_doc=".$id);$header=$type==='customer_invoice'?'facture':'facture_fourn';$detail=$type==='customer_invoice'?'facturedet':'facture_fourn_det';$fk=$type==='customer_invoice'?'fk_facture':'fk_facture_fourn';nrSql('DELETE FROM '.$p.$detail.' WHERE '.$fk.'='.$id);nrSql('DELETE FROM '.$p.$header.'_extrafields WHERE fk_object='.$id);nrSql('DELETE FROM '.$p.$header.' WHERE rowid='.$id);}}
 foreach(array_merge($foreignParties,$partnerId?array($partnerId):array()) as $partnerId){nrSql('DELETE FROM '.$p.'societe_extrafields WHERE fk_object='.$partnerId);nrSql('DELETE FROM '.$p.'societe WHERE rowid='.$partnerId);}
 foreach(array_keys($pdfDirectories) as $directory){dol_delete_dir_recursive($directory);}
 if($journalIds){nrSql('DELETE FROM '.$p.'accounting_journal WHERE rowid IN ('.implode(',',$journalIds).')');}
 if($reverseProduct){foreach(array('product_lang'=>'fk_product','product_price'=>'fk_product','product_extrafields'=>'fk_object','product'=>'rowid') as $table=>$fk){nrSql('DELETE FROM '.$p.$table.' WHERE '.$fk.'='.$reverseProduct);}}
 if($newAccounts){nrSql('DELETE FROM '.$p.'accounting_account WHERE rowid IN ('.implode(',',$newAccounts).')');}
 nrSql('DELETE FROM '.$p.'c_tva WHERE entity='.$entity.' AND code IN ('.$codes.')');nrRestoreRows($p.'c_tva',$oldRates);
 foreach($settings as $name=>$v){nrSql('DELETE FROM '.$p."const WHERE entity=".$entity." AND name='".$name."'");}nrRestoreRows($p.'const',$oldSettings);
 $conf->global=$oldGlobals;$mysoc=$oldCompany;curl_close($curl);unlink($cookie);
}
