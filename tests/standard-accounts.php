<?php
// SPDX-License-Identifier: GPL-3.0-or-later
if (PHP_SAPI!=='cli' || getenv('NORWAY_SYNTHETIC_TEST')!=='1' || getenv('DOLI_DB_NAME')!=='norway_test') { exit(1); }
require '/var/www/html/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/norway/class/NorwayStandardAccounts.php';
function saAssert($ok,$message) { if (!$ok) { throw new RuntimeException($message); } }
function saSql($sql) { global $db; $r=$db->query($sql);saAssert($r!==false,'Synthetic standard query: '.$db->lasterror());return $r; }
saAssert($user->fetch('', 'testadmin')>0 && $user->admin,'Admin missing');
$catalog=NorwayStandardAccounts::catalog();saAssert(count($catalog)===46,'Default/closing inventory incomplete');
$labels=array_column(NorwayChartPreview::parse(NorwayChartProfile::csv()),'label','number');
foreach ($catalog as $key=>$rule) { foreach ($rule['options'] as $number) { saAssert(isset($labels[$number]),'Unknown Norwegian account'); } }
saAssert($catalog['ACCOUNTING_SERVICE_SOLD_EXPORT_ACCOUNT']['options']===array('3125') && !in_array('3120',$catalog['ACCOUNTING_SERVICE_SOLD_EXPORT_ACCOUNT']['options'],true),'Domestic zero-rated account offered as export');
saAssert(in_array('6420',$catalog['ACCOUNTING_SERVICE_BUY_ACCOUNT']['options'],true) && in_array('6705',$catalog['ACCOUNTING_SERVICE_BUY_EXPORT_ACCOUNT']['options'],true),'General service types missing');
saAssert($catalog['ACCOUNTING_RESULT_PROFIT']['options']===array('2050') && $catalog['ACCOUNTING_RESULT_LOSS']['options']===array('2050','2080'),'Closing destinations changed');
foreach (array(array(),array('UNKNOWN'=>'1500'),array('ACCOUNTING_RESULT_PROFIT'=>'8800'),array('ACCOUNTING_RESULT_LOSS'=>'8990'),array('ACCOUNTING_PRODUCT_SOLD_ACCOUNT'=>'1500'),array('ACCOUNTING_VAT_BUY_REVERSE_CHARGES_DEBIT'=>'2710')) as $bad) {
 try { NorwayStandardAccounts::validate($bad);throw new LogicException('Invalid choice accepted'); } catch (InvalidArgumentException $expected) {}
}
$p=$db->prefix();$originalEntity=$conf->entity;$conf->entity=987649;$entity=$conf->entity;
$old=$db->fetch_object(saSql("SELECT rowid FROM ".$p."accounting_system WHERE pcg_version='".NorwayChartProfile::VERSION."'"));
$before=array();foreach (array_merge(array('CHARTOFACCOUNTS'),array_keys($catalog)) as $key) { $before[$key]=array(property_exists($conf->global,$key),$conf->global->$key ?? null);unset($conf->global->$key); }
$choices=array('ACCOUNTING_PRODUCT_SOLD_ACCOUNT'=>'3000','ACCOUNTING_SERVICE_SOLD_ACCOUNT'=>'3020','ACCOUNTING_SERVICE_BUY_ACCOUNT'=>'6705','ACCOUNTING_PRODUCT_SOLD_EXPORT_ACCOUNT'=>'3105','ACCOUNTING_SERVICE_SOLD_EXPORT_ACCOUNT'=>'3125','ACCOUNTING_PRODUCT_BUY_EXPORT_ACCOUNT'=>'4340','ACCOUNTING_SERVICE_BUY_EXPORT_ACCOUNT'=>'6400','ACCOUNTING_VAT_SOLD_ACCOUNT'=>'2700','ACCOUNTING_VAT_BUY_ACCOUNT'=>'2710','ACCOUNTING_VAT_PAY_ACCOUNT'=>'2740','ACCOUNTING_RESULT_PROFIT'=>'2050','ACCOUNTING_RESULT_LOSS'=>'2050');
try {
 $preview=NorwayChartProfile::preview($db,$entity);saAssert(NorwayChartProfile::install($db,$entity,NorwayChartProfile::fingerprint($entity,$preview),$user)===745,'Chart import failed');
 $preview=NorwayDefaultAccounts::preview($db,$entity,$choices);saAssert(count(array_filter($preview['rows'],static fn($r)=>$r['action']==='Fill'))===12,'Expected empty standard choices');
 $failing=new class($db) {
  private $db;private $inserts=0;
  public function __construct($db) { $this->db=$db; }
  public function __call($name,$args) { return $this->db->$name(...$args); }
  public function __get($name) { return $this->db->$name; }
  public function query($sql,...$args) { if (strpos($sql,'INSERT INTO '.$this->db->prefix().'const(')===0 && ++$this->inserts===2) { return false; }return $this->db->query($sql,...$args); }
 };
 try { NorwayDefaultAccounts::apply($failing,$entity,NorwayDefaultAccounts::fingerprint($preview),$user,$choices);throw new LogicException('Partial write accepted'); } catch (RuntimeException $expected) {}
 saAssert(NorwayDefaultAccounts::preview($db,$entity,$choices)===$preview,'Partial write did not roll back');
 $db->begin();try { NorwayDefaultAccounts::apply($db,$entity,NorwayDefaultAccounts::fingerprint($preview),$user,$choices);throw new LogicException('Nested transaction accepted'); } catch (RuntimeException $expected) {}finally { $db->rollback(); }
 $reader=clone $user;$reader->admin=0;
 try { NorwayDefaultAccounts::apply($db,$entity,NorwayDefaultAccounts::fingerprint($preview),$reader,$choices);throw new LogicException('Nonadmin accepted'); }catch (InvalidArgumentException $expected) {}
 saAssert(NorwayDefaultAccounts::apply($db,$entity,NorwayDefaultAccounts::fingerprint($preview),$user,$choices)===12,'Selected defaults not saved');
 require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
 require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
 require_once DOL_DOCUMENT_ROOT.'/accountancy/class/accountingaccount.class.php';
 require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
 $no=new Societe($db);$no->country_code='NO';$foreign=new Societe($db);$foreign->country_code='US';
 $product=new Product($db);$invoice=new Facture($db);$invoice->type=0;
 $line=(object)array('product_type'=>1,'tva_tx'=>0,'desc'=>'Synthetic binding');
 $binding=(new AccountingAccount($db))->getAccountingCodeToBind($foreign,$no,$product,$invoice,$line,array('dom'=>0,'export'=>0,'intra'=>0,'thirdparty'=>0),'customer');
 saAssert(is_array($binding) && $binding['code_l']==='3125' && (int)$binding['suggestedid']>0,'Native service export default did not resolve');
 $binding=(new AccountingAccount($db))->getAccountingCodeToBind($no,$foreign,$product,$invoice,$line,array('dom'=>0,'export'=>0,'intra'=>0,'thirdparty'=>0),'supplier');
 saAssert(is_array($binding) && $binding['code_l']==='6400' && (int)$binding['suggestedid']>0,'Native service import default did not resolve');
 $line->product_type=0;
 $binding=(new AccountingAccount($db))->getAccountingCodeToBind($foreign,$no,$product,$invoice,$line,array('dom'=>0,'export'=>0,'intra'=>0,'thirdparty'=>0),'customer');
 saAssert(is_array($binding) && $binding['code_l']==='3105' && (int)$binding['suggestedid']>0,'Native goods export default did not resolve');
 $binding=(new AccountingAccount($db))->getAccountingCodeToBind($no,$foreign,$product,$invoice,$line,array('dom'=>0,'export'=>0,'intra'=>0,'thirdparty'=>0),'supplier');
 saAssert(is_array($binding) && $binding['code_l']==='4340' && (int)$binding['suggestedid']>0,'Native goods import default did not resolve');
 $kept=NorwayDefaultAccounts::preview($db,$entity,array('ACCOUNTING_RESULT_LOSS'=>'2080'));
 saAssert($kept['rows'][0]['action']==='Keep','Existing closing account overwritten');
 saAssert(NorwayDefaultAccounts::apply($db,$entity,NorwayDefaultAccounts::fingerprint($kept),$user,array('ACCOUNTING_RESULT_LOSS'=>'2080'))===0 && getDolGlobalString('ACCOUNTING_RESULT_LOSS')==='2050','Preservation failed');
 try { NorwayDefaultAccounts::apply($db,$entity,NorwayDefaultAccounts::fingerprint($preview),$user,$choices);throw new LogicException('Stale confirmation accepted'); }catch (InvalidArgumentException $expected) {}
 $new=array('ACCOUNTING_PRODUCT_BUY_ACCOUNT'=>'4300');
 saSql("INSERT INTO ".$p."societe (nom,entity,client,status) VALUES ('Synthetic standard history',".$entity.",1,1)");
 $party=(int)$db->last_insert_id($p.'societe');
 saSql("INSERT INTO ".$p."facture (ref,entity,type,fk_soc,datec,datef,fk_statut) VALUES ('SYN-STANDARD-HISTORY',".$entity.",0,".$party.",NOW(),NOW(),1)");
 $blocked=NorwayDefaultAccounts::preview($db,$entity,$new);saAssert($blocked['rows'][0]['action']==='Blocked','Issued history ignored');
 try { NorwayDefaultAccounts::apply($db,$entity,NorwayDefaultAccounts::fingerprint($blocked),$user,$new);throw new LogicException('History accepted'); }catch (InvalidArgumentException $expected) {}
} finally {
 $db->rollback();foreach (array('facture','societe','accounting_account','const') as $table) { saSql('DELETE FROM '.$p.$table.' WHERE entity='.$entity); }
 if (!$old) { saSql("DELETE FROM ".$p."accounting_system WHERE pcg_version='".NorwayChartProfile::VERSION."'"); }
 foreach ($before as $key=>$value) { if ($value[0]) { $conf->global->$key=$value[1]; }else { unset($conf->global->$key); } }$conf->entity=$originalEntity;
}
print "PASS complete standard/closing inventory, purpose-bound choices, explicit fills, existing choices, history, stale confirmation, permissions and atomic rollback\n";
