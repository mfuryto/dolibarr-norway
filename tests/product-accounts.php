<?php
// SPDX-License-Identifier: GPL-3.0-or-later
if (PHP_SAPI!=='cli' || getenv('NORWAY_SYNTHETIC_TEST')!=='1' || getenv('DOLI_DB_NAME')!=='norway_test') { exit(1); }
require '/var/www/html/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/norway/class/NorwayProductAccounts.php';
function paAssert($ok,$message) { if (!$ok) { throw new RuntimeException($message); } }
function paSql($sql) { global $db;$r=$db->query($sql);paAssert((bool)$r,'Synthetic product-account fixture failed: '.$db->lasterror());return $r; }
$old=$conf->entity;$entity=987648;$conf->entity=$entity;$p=$db->prefix();$db->begin();
try {
 paSql("INSERT INTO ".$p."accounting_system (pcg_version,label,active) VALUES ('SYN-PRODUCT-CODES','Synthetic product chart',1)");$chart=(int)$db->last_insert_id($p.'accounting_system');
 foreach (array(array($entity,'3000',1),array($entity,'3020',1),array($entity,'4300',0),array($entity+1,'9999',1)) as $a) {
  paSql("INSERT INTO ".$p."accounting_account (entity,fk_pcg_version,account_number,pcg_type,label,active) VALUES (".$a[0].",'SYN-PRODUCT-CODES','".$a[1]."','SYN','Synthetic account',".$a[2].")");
 }
 foreach (array(array('SYN-A',0,1,1,'','4300',$entity),array('SYN-B',1,1,0,'3000','',$entity),array('SYN-C',1,0,1,'','9999',$entity),array('SYN-D',0,1,0,'-1','',$entity),array('SYN-E',0,1,0,'0','',$entity),array('SYN-F',1,1,0,'','',$entity),array('SYN-INACTIVE',0,0,0,'','',$entity),array('SYN-OTHER',9,1,1,'','',$entity),array('SYN-FOREIGN',0,1,1,'','',$entity+1)) as $a) {
  paSql("INSERT INTO ".$p."product (ref,label,entity,fk_product_type,tosell,tobuy,tva_tx,accountancy_code_sell,accountancy_code_buy,accountancy_code_sell_export,accountancy_code_sell_intra) VALUES ('".$a[0]."','Synthetic item',".$a[6].",".$a[1].",".$a[2].",".$a[3].",25,'".$a[4]."','".$a[5]."','3020','9999')");
 }
 $defaults=array('ACCOUNTING_PRODUCT_SOLD_ACCOUNT'=>'3000','ACCOUNTING_PRODUCT_BUY_ACCOUNT'=>'4300','ACCOUNTING_SERVICE_SOLD_ACCOUNT'=>'3020','ACCOUNTING_SERVICE_BUY_ACCOUNT'=>'4300','ACCOUNTING_SERVICE_SOLD_EXPORT_ACCOUNT'=>'3000');
 $selected=(int)$db->fetch_object(paSql("SELECT rowid FROM ".$p."product WHERE ref='SYN-B' AND entity=".$entity))->rowid;
 $foreign=(int)$db->fetch_object(paSql("SELECT rowid FROM ".$p."product WHERE ref='SYN-FOREIGN' AND entity=".($entity+1)))->rowid;
 foreach (array('domestic','export','intra') as $scope) {
  $single=NorwayProductAccounts::report($db,$entity,$chart,$defaults,$scope,$selected);
  paAssert($single['products']===1 && count($single['rows'])===1 && $single['rows'][0]['ref']==='SYN-B','Selected product scope leaked other products');
 }
 paAssert(NorwayProductAccounts::report($db,$entity,$chart,$defaults,'domestic',$foreign)['products']===0,'Foreign selected product leaked');
 foreach (array(0,-1,'1') as $badId) { try { NorwayProductAccounts::report($db,$entity,$chart,$defaults,'domestic',$badId);throw new LogicException('Invalid product id broadened scope'); } catch (InvalidArgumentException $expected) {} }
 $report=NorwayProductAccounts::report($db,$entity,$chart,$defaults);paAssert($report['products']===6 && count($report['rows'])===7 && !$report['truncated'],'Item/direction/entity filtering failed');
 $map=array();foreach ($report['rows'] as $row) { $map[$row['ref'].'-'.$row['direction']]=$row; }
 paAssert($map['SYN-A-sell']['source']==='Fallback' && $map['SYN-A-sell']['number']==='3000' && $map['SYN-A-sell']['status']==='Available','Product default missing');
 paAssert($map['SYN-A-buy']['source']==='Explicit' && $map['SYN-A-buy']['status']==='Inactive','Inactive explicit account concealed');
 paAssert($map['SYN-B-sell']['number']==='3000' && $map['SYN-B-sell']['fallback']==='3020','Own service code not preserved');
 paAssert($map['SYN-C-buy']['status']==='Missing','Foreign account leaked');
 paAssert($map['SYN-D-sell']['source']==='Explicit' && $map['SYN-D-sell']['status']==='Unset','Nonempty invalid code silently fell back');
 paAssert($map['SYN-E-sell']['source']==='Fallback','PHP-empty product code mismatch');
 paAssert($map['SYN-F-sell']['number']==='3020' && $map['SYN-F-sell']['source']==='Fallback','Service default ignored');
 paSql("UPDATE ".$p."product SET accountancy_code_sell_export='' WHERE entity=".$entity." AND ref='SYN-B'");
 $export=NorwayProductAccounts::report($db,$entity,$chart,$defaults,'export');paAssert($export['rows'][0]['number']==='3020' && $export['rows'][0]['source']==='Explicit','Export field not inspected');
 paAssert($export['rows'][2]['number']==='3000' && $export['rows'][2]['source']==='Fallback','Export default ignored');
 $intra=NorwayProductAccounts::report($db,$entity,$chart,$defaults,'intra');paAssert($intra['rows'][0]['number']==='9999' && $intra['rows'][0]['status']==='Missing','EU field leaked or hidden');
 paAssert(NorwayProductAccounts::report($db,$entity,0,$defaults)['rows'][0]['status']==='Missing','Missing chart accepted');
 paSql('UPDATE '.$p.'accounting_system SET active=0 WHERE rowid='.$chart);paAssert(NorwayProductAccounts::report($db,$entity,$chart,$defaults)['rows'][0]['status']==='Inactive','Inactive chart accepted');
 paSql('UPDATE '.$p.'accounting_system SET active=1 WHERE rowid='.$chart);
 try { NorwayProductAccounts::report($db,$entity+1,$chart,$defaults);throw new LogicException('Entity bypass'); }catch (InvalidArgumentException $expected) {}
 try { NorwayProductAccounts::report($db,$entity,$chart,$defaults,'invalid');throw new LogicException('Scope bypass'); }catch (InvalidArgumentException $expected) {}
 for ($i=0;$i<201;$i++) { paSql("INSERT INTO ".$p."product (ref,label,entity,fk_product_type,tosell,tobuy) VALUES ('SYN-LIMIT-".$i."','Synthetic limit',".$entity.",0,1,0)"); }
 $limited=NorwayProductAccounts::report($db,$entity,$chart,$defaults);paAssert($limited['truncated'] && $limited['products']===200,'Truncation hidden');
} finally { $db->rollback();$conf->entity=$old; }
$failing=new class { public function prefix(){return 'synthetic_';}public function query($sql){return false;} };
try { NorwayProductAccounts::report($failing,(int)$conf->entity,0,array());throw new LogicException('Read failure hidden'); }catch (RuntimeException $expected) {}
print "PASS product account candidates, explicit/default precedence, trade fields, active chart, entity isolation, truncation and query failure\n";
