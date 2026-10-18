<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// Qualify native ENK bank flows; no tax engine or parallel payment records.
if (PHP_SAPI !== 'cli' || getenv('NORWAY_SYNTHETIC_TEST') !== '1' || getenv('DOLI_DB_NAME') !== 'norway_test') { exit(1); }
require '/var/www/html/master.inc.php';
foreach (array('/core/lib/admin.lib.php','/core/lib/date.lib.php','/accountancy/class/bookkeeping.class.php','/accountancy/class/accountingaccount.class.php','/compta/bank/class/account.class.php','/compta/bank/class/paymentvarious.class.php') as $file) { require_once DOL_DOCUMENT_ROOT.$file; }
function atAssert($ok, $message) { if (!$ok) { throw new RuntimeException($message); } }
function atRows($sql) { global $db; $res=$db->query($sql); atAssert($res!==false, $db->lasterror()); $out=array(); if($res===true){return $out;} while($row=$db->fetch_object($res)) { $out[]=$row; } return $out; }
function atHttp($path, $post=null) { global $curl; curl_setopt($curl,CURLOPT_URL,'http://localhost'.$path); curl_setopt($curl,CURLOPT_POST,$post!==null); if($post!==null){curl_setopt($curl,CURLOPT_POSTFIELDS,http_build_query($post));} $body=curl_exec($curl); atAssert($body!==false && curl_getinfo($curl,CURLINFO_HTTP_CODE)<400,'Native HTTP '.$path); return $body; }
function atToken($body) { atAssert(preg_match('/name="token"[^>]*value="([^"]+)"/',$body,$m)===1,'Native token'); return html_entity_decode($m[1]); }
atAssert($user->fetch('','testadmin')>0,'Admin'); $r=activateModule('modBanque'); atAssert(empty($r['errors']),'Bank module'); $conf->setValues($db); $user->loadRights('',1);
$p=$db->prefix(); $entity=(int)$conf->entity; $run=bin2hex(random_bytes(4)); $banks=array(); $payments=array(); $journal=0; $newAccounts=array(); $oldTransfer=atRows("SELECT * FROM {$p}const WHERE entity=$entity AND name='ACCOUNTING_ACCOUNT_TRANSFER_CASH'"); $oldMode=atRows("SELECT * FROM {$p}const WHERE entity=$entity AND name='ACCOUNTANCY_FISCAL_PERIOD_MODE'");
$cookie=tempnam(sys_get_temp_dir(),'norway-tax-'); $curl=curl_init(); curl_setopt_array($curl,array(CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_TIMEOUT=>30));
try {
 dolibarr_set_const($db,'ACCOUNTANCY_FISCAL_PERIOD_MODE','blockedonclosed','chaine',0,'',$entity); $conf->global->ACCOUNTANCY_FISCAL_PERIOD_MODE='blockedonclosed'; $conf->cache=array(); dolibarr_set_const($db,'ACCOUNTING_ACCOUNT_TRANSFER_CASH','1999','chaine',0,'',$entity);
 atRows("INSERT INTO {$p}accounting_journal (entity,code,label,nature,active) VALUES ($entity,'AT$run','Synthetic advance tax',4,1)"); $journal=(int)$db->last_insert_id($p.'accounting_journal');
 foreach(array('1920','1921') as $number) { $b=new Account($db); $b->ref='A'.substr($run,0,5).substr($number,0,2); $b->label='SYNTHETIC TAX ONLY '.$number; $b->country_id=173; $b->currency_code='NOK'; $b->courant=1; $b->rappro=1; $b->balance=0; $b->date_solde=dol_mktime(12,0,0,1,1,2052); $b->account_number=$number; $b->fk_accountancy_journal=$journal; atAssert($b->create($user)>0,'Bank '.$b->error); $banks[$number]=(int)$b->id; }
 $chart=atRows("SELECT pcg_version FROM {$p}accounting_system WHERE rowid=".(int)getDolGlobalInt('CHARTOFACCOUNTS'))[0]->pcg_version;
 foreach(array('1920','1921','2070','2072','1999') as $number){ if(atRows("SELECT rowid FROM {$p}accounting_account WHERE entity=$entity AND fk_pcg_version='".$db->escape($chart)."' AND account_number='$number'")){continue;} $a=new AccountingAccount($db);$a->fk_pcg_version=$chart;$a->account_number=$number;$a->label='SYNTHETIC '.$number;$a->active=1;atAssert($a->create($user)>0,'Account');$newAccounts[]=(int)$a->id; }
 $method=(int)atRows("SELECT id FROM {$p}c_paiement WHERE entity=$entity AND code='VIR' AND active=1")[0]->id;
 // Authority demands change from 10000 to 8000; a refund reverses the owner movement.
 foreach(array(array(10000,0,'2070','1920'),array(8000,0,'2070','1920'),array(1000,1,'2070','1920'),array(2000,0,'2072','1921')) as [$amount,$direction,$account,$bank]) { $pay=new PaymentVarious($db); $pay->datep=dol_mktime(12,0,0,3,15,2052); $pay->datev=$pay->datep; $pay->sens=$direction; $pay->amount=$amount; $pay->type_payment=$method; $pay->label='SYNTHETIC PERSONAL TAX ONLY'; $pay->accountancy_code=$account; $pay->fk_account=$banks[$bank]; atAssert($pay->create($user)>0,'Payment '.$pay->error); $payments[]=(int)$pay->id; }
 $body=atHttp('/index.php'); atHttp('/index.php',array('token'=>atToken($body),'actionlogin'=>'login','loginfunction'=>'loginfunction','username'=>'testadmin','password'=>getenv('DOLI_ADMIN_PASSWORD')));
 // The actual native transfer form links both bank movements. No 2070 allocation.
 $path='/compta/bank/transfer.php'; $body=atHttp($path); $post=array('token'=>atToken($body),'action'=>'add','1_account_from'=>$banks['1920'],'1_account_to'=>$banks['1921'],'1_amount'=>5000,'1_type'=>$method,'1_day'=>15,'1_month'=>3,'1_year'=>2052,'1_label'=>'SYNTHETIC TAX RESERVE ONLY');
 for($i=2;$i<20;$i++){ $post[$i.'_account_from']=-1; $post[$i.'_account_to']=-1; }
 $transferBody=atHttp($path,$post);
 $ids=implode(',',$banks); $movements=atRows("SELECT rowid,fk_account,amount FROM {$p}bank WHERE fk_account IN ($ids) AND amount<>0"); atAssert(count($movements)===6,'Four payments plus two native transfer movements: '.count($movements).' '.substr(strip_tags($transferBody),-4500));
 foreach($movements as $line) { $b=new AccountLine($db); atAssert($b->fetch($line->rowid)>0,'Bank line'); $b->num_releve='SYNTHETIC-2052-03'; atAssert($b->update_conciliation($user,0,1)>0,'Native reconciliation'); }
 $statement=array(); foreach(atRows("SELECT fk_account,SUM(amount) balance,MIN(rappro) reconciled FROM {$p}bank WHERE fk_account IN ($ids) AND amount<>0 GROUP BY fk_account") as $row){$statement[$row->fk_account]=(float)$row->balance;atAssert((int)$row->reconciled===1,'Statement reconciled');}
 atAssert($statement[$banks['1920']]===-22000.0 && $statement[$banks['1921']]===3000.0,'Independent bank statements -22000 / 3000');
 $params=array('id_journal'=>$journal,'date_startday'=>1,'date_startmonth'=>1,'date_startyear'=>2052,'date_endday'=>31,'date_endmonth'=>12,'date_endyear'=>2052,'in_bookkeeping'=>'notyet','only_rappro'=>2); $path='/accountancy/journal/bankjournal.php?'.http_build_query($params);
 for($i=0;$i<2;$i++){ $body=atHttp($path); $journalBody=atHttp($path,$params+array('token'=>atToken($body),'action'=>'writebookkeeping'));  }
 $balances=array(); foreach(atRows("SELECT numero_compte,SUM(debit-credit) balance FROM {$p}accounting_bookkeeping WHERE entity=$entity AND code_journal='AT$run' GROUP BY numero_compte") as $row){$balances[$row->numero_compte]=round((float)$row->balance,2);}
 $expected=array('1999'=>0.0,'1920'=>-22000.0,'1921'=>3000.0,'2070'=>17000.0,'2072'=>2000.0); ksort($balances);ksort($expected);atAssert($balances===$expected,'Native main ledger / repeat import: '.json_encode($balances).' '.(preg_match_all('/jnotify\((.*?)\);/s',$journalBody,$jm)?json_encode($jm[1]):substr(strip_tags($journalBody),0,2200)));
 atAssert(count(atRows("SELECT rowid FROM {$p}accounting_bookkeeping WHERE entity=$entity AND code_journal='AT$run' AND (numero_compte LIKE '3%' OR numero_compte LIKE '4%' OR numero_compte LIKE '6%' OR numero_compte LIKE '27%')"))===0,'No expense, income or VAT from personal tax');
 // Private payment has no business source: the four actual business payments are exhaustive.
 atAssert(count(atRows("SELECT rowid FROM {$p}payment_various WHERE rowid IN (".implode(',',$payments).')'))===4,'No fictitious fifth private payment');
 print "PASS ENK native bank: changed term, refund, additional advance, linked reserve transfer, reconciliation and repeat journal; 2070=17000, 2072=2000, bank=-19000, no expense/VAT\n";
} finally {
 $db->rollback(); atRows("DELETE FROM {$p}accounting_bookkeeping WHERE entity=$entity AND code_journal='AT$run'");
 if($banks){$ids=implode(',',$banks);atRows("DELETE FROM {$p}bank_url WHERE fk_bank IN (SELECT rowid FROM {$p}bank WHERE fk_account IN ($ids))");atRows("DELETE FROM {$p}bank WHERE fk_account IN ($ids)");atRows("DELETE FROM {$p}bank_account WHERE rowid IN ($ids)");}
 if($payments){$ids=implode(',',$payments);atRows("DELETE FROM {$p}payment_various_extrafields WHERE fk_object IN ($ids)");atRows("DELETE FROM {$p}payment_various WHERE rowid IN ($ids)");}
 if($newAccounts){atRows("DELETE FROM {$p}accounting_account WHERE rowid IN (".implode(',',$newAccounts).')');}
 if($journal){atRows("DELETE FROM {$p}accounting_journal WHERE rowid=$journal");} atRows("DELETE FROM {$p}const WHERE entity=$entity AND name='ACCOUNTANCY_FISCAL_PERIOD_MODE'"); atRows("DELETE FROM {$p}const WHERE entity=$entity AND name='ACCOUNTING_ACCOUNT_TRANSFER_CASH'"); foreach(array_merge($oldMode,$oldTransfer) as $row){ $values=array_map(fn($v)=>$v===null?'NULL':"'".$db->escape((string)$v)."'",array_values((array)$row));atRows("INSERT INTO {$p}const (".implode(',',array_keys((array)$row)).') VALUES ('.implode(',',$values).')'); } curl_close($curl);unlink($cookie);
}
