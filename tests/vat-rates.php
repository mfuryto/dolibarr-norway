<?php
// SPDX-License-Identifier: GPL-3.0-or-later
if (PHP_SAPI!=='cli' || getenv('NORWAY_SYNTHETIC_TEST')!=='1' || getenv('DOLI_DB_NAME')!=='norway_test') { exit(1); }
require '/var/www/html/master.inc.php';require_once DOL_DOCUMENT_ROOT.'/custom/norway/class/NorwayVatRates.php';
function vrAssert($ok,$message) { if (!$ok) { throw new RuntimeException($message); } }
function vrSql($sql) { global $db;$r=$db->query($sql);vrAssert($r!==false,'Synthetic rate query: '.$db->lasterror());return $r; }
vrAssert($user->fetch('','testadmin')>0 && $user->admin,'Admin missing');$user->loadRights('accounting',1);
$p=$db->prefix();$original=$conf->entity;$entity=987647;$conf->entity=$entity;
$old=$db->fetch_object(vrSql("SELECT rowid FROM ".$p."accounting_system WHERE pcg_version='".NorwayChartProfile::VERSION."'"));
$before=array();foreach(array('CHARTOFACCOUNTS','ACCOUNTING_VAT_SOLD_ACCOUNT','ACCOUNTING_VAT_BUY_ACCOUNT') as $key){$before[$key]=array(property_exists($conf->global,$key),$conf->global->$key ?? null);unset($conf->global->$key);}
try {
 $chart=NorwayChartProfile::preview($db,$entity);vrAssert(NorwayChartProfile::install($db,$entity,NorwayChartProfile::fingerprint($entity,$chart),$user)===745,'Chart failed');
 foreach(array(0,8,14,25) as $rate){vrSql("INSERT INTO ".$p."c_tva (entity,fk_pays,taux,type_vat,code,active) VALUES (".$entity.",173,".$rate.",0,'',1)");}
 vrSql("UPDATE ".$p."c_tva SET accountancy_code_sell='CUSTOM',use_default=1 WHERE entity=".$entity." AND taux=25");
 vrSql("INSERT INTO ".$p."c_tva (entity,fk_pays,taux,type_vat,code,active) VALUES (".$entity.",1,8,0,'',1)");
 $preview=NorwayVatRates::preview($db,$entity);vrAssert(!$preview['blocked'] && count($preview['changes'])===5,'Initial scope failed');
 $reader=clone $user;$reader->admin=0;
 try{NorwayVatRates::apply($db,$entity,NorwayDefaultAccounts::fingerprint($preview),$reader);throw new LogicException('Nonadmin accepted');}catch(InvalidArgumentException $expected){}
 $failing=new class($db){private $db;private $writes=0;public function __construct($db){$this->db=$db;}public function __call($name,$args){return $this->db->$name(...$args);}public function query($sql,...$args){if((strpos($sql,'INSERT INTO '.$this->db->prefix().'c_tva ')===0 || strpos($sql,'UPDATE '.$this->db->prefix().'c_tva SET ')===0) && ++$this->writes===3){return false;}return $this->db->query($sql,...$args);}};
 try{NorwayVatRates::apply($failing,$entity,NorwayDefaultAccounts::fingerprint($preview),$user);throw new LogicException('Partial write accepted');}catch(RuntimeException $expected){}
 vrAssert(NorwayVatRates::preview($db,$entity)===$preview,'Atomic rollback failed');
 $db->begin();try{NorwayVatRates::apply($db,$entity,NorwayDefaultAccounts::fingerprint($preview),$user);throw new LogicException('Nested transaction accepted');}catch(RuntimeException $expected){}finally{$db->rollback();}
 vrAssert(NorwayVatRates::apply($db,$entity,NorwayDefaultAccounts::fingerprint($preview),$user)===4,'Expected two creates and two deactivations');
 $after=NorwayVatRates::preview($db,$entity);vrAssert(!$after['blocked'] && count($after['rates'])===6,'Rows deleted or duplicated');
 foreach($after['rates'] as $r){vrAssert((int)$r['active']===(in_array((int)$r['taux'],array(8,14),true)?0:1),'Incorrect active rate');if((int)$r['taux']===25){vrAssert($r['accountancy_code_sell']==='CUSTOM' && (int)$r['use_default']===1,'Account/default overwritten');}}
 $foreign=$db->fetch_object(vrSql('SELECT active FROM '.$p.'c_tva WHERE entity='.$entity.' AND fk_pays=1'));vrAssert((int)$foreign->active===1,'Foreign rate changed');
 vrAssert(NorwayVatRates::apply($db,$entity,NorwayDefaultAccounts::fingerprint($after),$user)===0,'Repeat failed');
 try{NorwayVatRates::apply($db,$entity,NorwayDefaultAccounts::fingerprint($preview),$user);throw new LogicException('Stale preview accepted');}catch(InvalidArgumentException $expected){}
 vrSql('UPDATE '.$p.'c_tva SET active=0 WHERE entity='.$entity.' AND taux=15');vrAssert(NorwayVatRates::preview($db,$entity)['blocked'],'Inactive current rate overwritten');
 vrSql('UPDATE '.$p.'c_tva SET active=1 WHERE entity='.$entity.' AND fk_pays=173 AND taux IN (8,15)');
 vrSql("UPDATE ".$p."c_tva SET code='SPECIAL' WHERE entity=".$entity." AND fk_pays=173 AND taux=8");vrAssert(NorwayVatRates::preview($db,$entity)['blocked'],'Special historical rate accepted');
 vrSql("UPDATE ".$p."c_tva SET code='',use_default=1 WHERE entity=".$entity." AND fk_pays=173 AND taux=8");vrAssert(NorwayVatRates::preview($db,$entity)['blocked'],'Historical default overwritten');
 vrSql('UPDATE '.$p.'c_tva SET use_default=0 WHERE entity='.$entity.' AND fk_pays=173 AND taux=8');
 vrSql("INSERT INTO ".$p."product (ref,label,entity,tva_tx) VALUES ('SYN-OLD-RATE','Synthetic',".$entity.",8)");vrAssert(NorwayVatRates::preview($db,$entity)['blocked'],'Product use ignored');vrSql('DELETE FROM '.$p.'product WHERE entity='.$entity);
 vrSql("INSERT INTO ".$p."societe (nom,entity,client,status) VALUES ('Synthetic rate history',".$entity.",1,1)");$party=(int)$db->last_insert_id($p.'societe');
 vrSql("INSERT INTO ".$p."facture (ref,entity,type,fk_soc,datec,datef,fk_statut) VALUES ('SYN-RATE-DRAFT',".$entity.",0,".$party.",NOW(),NOW(),0)");
 $blocked=NorwayVatRates::preview($db,$entity);vrAssert($blocked['blocked'],'Draft document use ignored');
 try{NorwayVatRates::apply($db,$entity,NorwayDefaultAccounts::fingerprint($blocked),$user);throw new LogicException('Used setup accepted');}catch(InvalidArgumentException $expected){}
 try{NorwayVatRates::preview($db,$entity+1);throw new LogicException('Foreign entity accepted');}catch(InvalidArgumentException $expected){}
} finally {
 $db->rollback();foreach(array('c_tva','facture','product','societe','accounting_account','const') as $table){vrSql('DELETE FROM '.$p.$table.' WHERE entity='.$entity);}
 if(!$old){vrSql("DELETE FROM ".$p."accounting_system WHERE pcg_version='".NorwayChartProfile::VERSION."'");}
 foreach($before as $key=>$value){if($value[0]){$conf->global->$key=$value[1];}else{unset($conf->global->$key);}}$conf->entity=$original;
}
print "PASS current VAT setup, retained old/zero/foreign rows, custom accounts/defaults, special/inactive/use guards, rollback, replay, stale confirmation and permissions\n";
