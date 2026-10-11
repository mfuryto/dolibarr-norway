<?php
// SPDX-License-Identifier: GPL-3.0-or-later
if (PHP_SAPI!=='cli' || getenv('NORWAY_SYNTHETIC_TEST')!=='1' || getenv('DOLI_DB_NAME')!=='norway_test') { exit(1); }
require '/var/www/html/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/norway/class/NorwayVatSetup.php';
function vsAssert($ok,$message) { if (!$ok) { throw new RuntimeException($message); } }
function vsSql($sql) { global $db; $r=$db->query($sql); vsAssert((bool)$r,'Synthetic VAT fixture failed'); return $r; }
$old=$conf->entity; $entity=987659; $conf->entity=$entity; $p=$db->prefix(); $db->begin();
try {
    vsSql("INSERT INTO ".$p."accounting_system (pcg_version,label,active) VALUES ('SYN-VAT','Synthetic VAT chart',1)");
    $chart=(int)$db->last_insert_id($p.'accounting_system');
    foreach (array(array($entity,'2700',1),array($entity,'2710',0),array($entity+1,'2799',1)) as $a) {
        vsSql("INSERT INTO ".$p."accounting_account (account_number,entity,fk_pcg_version,pcg_type,label,active) VALUES ('".$a[1]."',".$a[0].",'SYN-VAT','SYN','Synthetic VAT account',".$a[2].")");
    }
    foreach (array(array($entity,173,1,0,'SYN25',25,'2700',''),array($entity,173,1,1,'SYN15',15,'2799',''),array($entity,173,1,2,'SYN0',0,'',''),array($entity+1,173,1,0,'FOREIGN',25,'',''),array($entity,1,1,0,'COUNTRY',25,'',''),array($entity,173,0,0,'INACTIVE',25,'','')) as $r) {
        vsSql("INSERT INTO ".$p."c_tva (entity,fk_pays,active,type_vat,code,taux,accountancy_code_sell,accountancy_code_buy) VALUES (".$r[0].",".$r[1].",".$r[2].",".$r[3].",'".$r[4]."',".$r[5].",'".$r[6]."','".$r[7]."')");
    }
    $defaults=array('sell'=>'2700','buy'=>'2710');
    $rows=NorwayVatSetup::report($db,$entity,$chart,$defaults); vsAssert(count($rows)===4,'Inactive/foreign rates leaked');
    $map=array(); foreach ($rows as $row) { $map[$row['code'].'-'.$row['direction']]=$row; }
    vsAssert($map['SYN25-sell']['status']==='Available' && $map['SYN25-sell']['source']==='Explicit','Explicit sales mapping ignored');
    vsAssert($map['SYN25-buy']['status']==='Inactive' && $map['SYN25-buy']['source']==='Fallback','Inactive fallback ignored');
    vsAssert($map['SYN15-sell']['status']==='Missing','Foreign ledger account leaked');
    vsAssert($map['SYN0-buy']['rate']==='0','Zero-rate treatment hidden');
    vsAssert(NorwayVatSetup::report($db,$entity,0,$defaults)[0]['status']==='Missing','Missing chart accepted');
    vsSql('UPDATE '.$p.'accounting_system SET active=0 WHERE rowid='.$chart);
    $rows=NorwayVatSetup::report($db,$entity,$chart,$defaults); vsAssert($rows[2]['status']==='Inactive','Inactive chart accepted');
    vsSql('UPDATE '.$p.'accounting_system SET active=1 WHERE rowid='.$chart);
    vsSql("UPDATE ".$p."c_tva SET accountancy_code_sell='0' WHERE entity=".$entity." AND code='SYN25'");
    $rows=NorwayVatSetup::report($db,$entity,$chart,array('sell'=>'','buy'=>''));
    vsAssert($rows[2]['status']==='Unset' && $rows[2]['source']==='Fallback','Core empty-account fallback mismatch');
    try { NorwayVatSetup::report($db,$entity+1,$chart,$defaults); throw new LogicException('Foreign entity accepted'); } catch (InvalidArgumentException $expected) {}
} finally { $db->rollback(); $conf->entity=$old; }
$failed=new class { public function prefix(){return 'synthetic_';} public function query($sql){return false;} };
try { NorwayVatSetup::report($failed,$conf->entity,0,array()); throw new LogicException('Database error hidden'); } catch (RuntimeException $expected) {}
print "PASS VAT rate mappings, explicit/default precedence, chart/entity/country scope, inactive accounts, zero rates and errors\n";
