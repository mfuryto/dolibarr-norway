<?php
// SPDX-License-Identifier: GPL-3.0-or-later
if(PHP_SAPI!=='cli'||getenv('NORWAY_SYNTHETIC_TEST')!=='1'||getenv('DOLI_DB_NAME')!=='norway_test'){exit(1);}
require '/var/www/html/master.inc.php';require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';require_once DOL_DOCUMENT_ROOT.'/custom/norway/core/modules/modNorway.class.php';
function navAssert($ok,$m){if(!$ok){throw new RuntimeException($m);}}
$user->fetch('','testadmin');$user->loadRights('',1);$entity=(int)$conf->entity;$p=$db->prefix();$module=new modNorway($db);
for($i=0;$i<4;$i++){navAssert(dolibarr_set_const($db,'MAIN_MODULE_NORWAY_TABS_'.$i,'invoice:+old:Old:norway@norway:1:/old.php','chaine',0,'',$entity)>0,'Old tab seed');}
foreach(array(1,2) as $attempt){navAssert($module->init()>0,'Menu upgrade failed');$conf->setValues($db);$r=$db->query("SELECT COUNT(*) n FROM ".$p."menu WHERE entity=".$entity." AND module='norway'");navAssert($r&&(int)$db->fetch_object($r)->n===3,'Retired or duplicate menus remain');
 $r=$db->query("SELECT COUNT(*) n FROM ".$p."const WHERE entity=".$entity." AND name LIKE 'MAIN_MODULE_NORWAY_TABS_%'");navAssert($r&&(int)$db->fetch_object($r)->n===0,'Retired tabs remain');
 foreach(array('invoicesuppliercard','supplierinvoicelist','fileslib','actionlinkedfiles') as $context){navAssert(in_array($context,$conf->modules_parts['hooks']['norway'] ?? array(),true),'Paperless native context missing');}
}
require_once DOL_DOCUMENT_ROOT.'/core/menus/standard/eldy.lib.php';require_once DOL_DOCUMENT_ROOT.'/core/class/menu.class.php';
$tree=new Menubase($db,'eldy');$tab=array();$menu=new Menu();$tree->menuLoad('accountancy','accountancy_admin',0,'eldy',$tab);get_left_menu_accountancy('accountancy',$menu,0,'accountancy_admin',0);$entries=$tree->menuLeftCharger($menu,'accountancy','accountancy_admin',0,'eldy',$tab)->liste;$found=array_values(array_filter($entries,fn($e)=>$e['leftmenu']==='norway_accounting'));navAssert(count($found)===1&&$found[0]['level']===1,'Norwegian defaults outside native setup');
$tab=array();$menu=new Menu();$tree->menuLoad('accountancy','accountancy_accountancy_report',0,'eldy',$tab);get_left_menu_accountancy('accountancy',$menu,0,'accountancy_accountancy_report',0);$entries=$tree->menuLeftCharger($menu,'accountancy','accountancy_accountancy_report',0,'eldy',$tab)->liste;
$found=array_values(array_filter($entries,fn($e)=>$e['leftmenu']==='norway_vat_report'));navAssert(count($found)===1&&$found[0]['level']===2,'VAT extract outside native accounting reports');
$tab=array();$menu=new Menu();$tree->menuLoad('accountancy','accountancy_accountancy',0,'eldy',$tab);get_left_menu_accountancy('accountancy',$menu,0,'accountancy_accountancy',0);$entries=$tree->menuLeftCharger($menu,'accountancy','accountancy_accountancy',0,'eldy',$tab)->liste;
$found=array_values(array_filter($entries,fn($e)=>$e['leftmenu']==='norway_balance'));navAssert(count($found)===1&&$found[0]['level']===1,'Balance overview outside native accounting');
navAssert($module->remove()>0&&$module->init()>0,'Disable/reactivate failed');print "PASS native settings and reports hierarchy, removed tabs, native paperless hooks without extra menus, repeated upgrade and native hierarchy\n";
