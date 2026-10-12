<?php
// SPDX-License-Identifier: GPL-3.0-or-later
if (PHP_SAPI!=='cli' || getenv('NORWAY_SYNTHETIC_TEST')!=='1' || getenv('DOLI_DB_NAME')!=='norway_test') { exit(1); }
require '/var/www/html/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/norway/class/NorwayVatAccounts.php';
function vaAssert($ok,$message) { if (!$ok) { throw new RuntimeException($message); } }
function vaSql($sql) { global $db;$r=$db->query($sql);vaAssert($r!==false,'Synthetic VAT query: '.$db->lasterror());return $r; }
vaAssert($user->fetch('','testadmin')>0 && $user->admin,'Admin missing');
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
if (!isModEnabled('accounting')) { $activated=activateModule('modAccounting');vaAssert(empty($activated['errors']),'Accounting activation failed'); }
$user->loadRights('accounting',1);
$p=$db->prefix();$original=$conf->entity;$entity=987648;$conf->entity=$entity;
$old=$db->fetch_object(vaSql("SELECT rowid FROM ".$p."accounting_system WHERE pcg_version='".NorwayChartProfile::VERSION."'"));
$before=array();foreach (array('CHARTOFACCOUNTS','ACCOUNTING_VAT_SOLD_ACCOUNT','ACCOUNTING_VAT_BUY_ACCOUNT') as $key) { $before[$key]=array(property_exists($conf->global,$key),$conf->global->$key ?? null);unset($conf->global->$key); }
try {
 $chart=NorwayChartProfile::preview($db,$entity);vaAssert(NorwayChartProfile::install($db,$entity,NorwayChartProfile::fingerprint($entity,$chart),$user)===745,'Chart failed');
 foreach (array(array(25,'',0,1,173),array(15,'',0,1,173),array(12,'',0,1,173),array(0,'',0,1,173),array(8,'',0,1,173),array(14,'',0,1,173),array(25,'IMPORT',0,1,173),array(25,'',1,1,173),array(25,'INACTIVE',0,0,173),array(25,'',0,1,1)) as $r) {
  vaSql("INSERT INTO ".$p."c_tva (entity,fk_pays,active,type_vat,code,taux,recuperableonly) VALUES (".$entity.",".$r[4].",".$r[3].",0,'".$r[1]."',".$r[0].",".$r[2].")");
 }
 // Existing nonstandard mappings must survive even when the account is invalid.
 vaSql("UPDATE ".$p."c_tva SET accountancy_code_sell='CUSTOM' WHERE entity=".$entity." AND taux=15");
 $preview=NorwayVatAccounts::preview($db,$entity);
 vaAssert(count(array_filter($preview['rows'],static fn($r)=>$r['action']==='Fill'))===5,'Unexpected fill scope');
 vaAssert(count(array_filter($preview['rows'],static fn($r)=>$r['action']==='Separate'))===10,'Special/historical rows not excluded');
 $failing=new class($db) {
  private $db;private $writes=0;public function __construct($db){$this->db=$db;}
  public function __call($name,$args){return $this->db->$name(...$args);}
  public function query($sql,...$args){if (strpos($sql,'UPDATE '.$this->db->prefix().'c_tva SET ')===0 && ++$this->writes===2){return false;}return $this->db->query($sql,...$args);}
 };
 try { NorwayVatAccounts::apply($failing,$entity,NorwayDefaultAccounts::fingerprint($preview),$user);throw new LogicException('Partial write accepted'); }catch(RuntimeException $expected){}
 vaAssert(NorwayVatAccounts::preview($db,$entity)===$preview,'Rollback failed');
 $reader=clone $user;$reader->admin=0;
 try { NorwayVatAccounts::apply($db,$entity,NorwayDefaultAccounts::fingerprint($preview),$reader);throw new LogicException('Nonadmin accepted'); }catch(InvalidArgumentException $expected){}
 $db->begin();try { NorwayVatAccounts::apply($db,$entity,NorwayDefaultAccounts::fingerprint($preview),$user);throw new LogicException('Nested transaction accepted'); }catch(RuntimeException $expected){}finally{$db->rollback();}
 vaAssert(NorwayVatAccounts::apply($db,$entity,NorwayDefaultAccounts::fingerprint($preview),$user)===5,'Expected five empty account fields');
 $after=NorwayVatAccounts::preview($db,$entity);
 foreach ($after['rows'] as $row) {
  if ($row['number']!=='') { vaAssert($row['current']==='CUSTOM' || $row['current']===$row['number'],'Incorrect rate/direction mapping'); }
 }
 vaAssert(array_column($preview['rates'],'taux')===array_column($after['rates'],'taux'),'Rates changed');
 vaAssert(NorwayVatAccounts::apply($db,$entity,NorwayDefaultAccounts::fingerprint($after),$user)===0,'Idempotence failed');
 try { NorwayVatAccounts::apply($db,$entity,NorwayDefaultAccounts::fingerprint($preview),$user);throw new LogicException('Stale preview accepted'); }catch(InvalidArgumentException $expected){}
 vaSql("UPDATE ".$p."c_tva SET accountancy_code_buy=NULL WHERE entity=".$entity." AND taux=12");
 vaSql("INSERT INTO ".$p."societe (nom,entity,client,status) VALUES ('Synthetic VAT history',".$entity.",1,1)");
 $party=(int)$db->last_insert_id($p.'societe');
 vaSql("INSERT INTO ".$p."facture (ref,entity,type,fk_soc,datec,datef,fk_statut) VALUES ('SYN-VAT-HISTORY',".$entity.",0,".$party.",NOW(),NOW(),1)");
 $blocked=NorwayVatAccounts::preview($db,$entity);vaAssert(count(array_filter($blocked['rows'],static fn($r)=>$r['action']==='Blocked'))===1,'History ignored');
 try { NorwayVatAccounts::apply($db,$entity,NorwayDefaultAccounts::fingerprint($blocked),$user);throw new LogicException('History accepted'); }catch(InvalidArgumentException $expected){}
 vaSql('DELETE FROM '.$p.'facture WHERE entity='.$entity);
 vaSql("UPDATE ".$p."accounting_account SET active=0 WHERE entity=".$entity." AND account_number='2713'");
 $blocked=NorwayVatAccounts::preview($db,$entity);vaAssert(count(array_filter($blocked['rows'],static fn($r)=>$r['action']==='Blocked'))===1,'Inactive account accepted');
 vaSql("UPDATE ".$p."accounting_account SET active=1,label='Changed name' WHERE entity=".$entity." AND account_number='2713'");
 $blocked=NorwayVatAccounts::preview($db,$entity);vaAssert(count(array_filter($blocked['rows'],static fn($r)=>$r['action']==='Blocked'))===1,'Changed account accepted');
 $chartBefore=$conf->global->CHARTOFACCOUNTS;$conf->global->CHARTOFACCOUNTS=0;
 $blocked=NorwayVatAccounts::preview($db,$entity);vaAssert(count(array_filter($blocked['rows'],static fn($r)=>$r['action']==='Blocked'))===1,'Runtime chart mismatch accepted');
 $conf->global->CHARTOFACCOUNTS=$chartBefore;
 try { NorwayVatAccounts::preview($db,$entity+1);throw new LogicException('Foreign entity accepted'); }catch(InvalidArgumentException $expected){}
} finally {
 $db->rollback();foreach(array('c_tva','facture','societe','accounting_account','const') as $table){vaSql('DELETE FROM '.$p.$table.' WHERE entity='.$entity);}
 if(!$old){vaSql("DELETE FROM ".$p."accounting_system WHERE pcg_version='".NorwayChartProfile::VERSION."'");}
 foreach($before as $key=>$value){if($value[0]){$conf->global->$key=$value[1];}else{unset($conf->global->$key);}}$conf->entity=$original;
}
print "PASS VAT rate sales/purchase mappings, special/zero/historical exclusions, preservation, rollback, stale confirmation, permissions, entity and history guards\n";
