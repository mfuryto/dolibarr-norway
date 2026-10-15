<?php
// SPDX-License-Identifier: GPL-3.0-or-later
if (PHP_SAPI !== 'cli' || getenv('NORWAY_SYNTHETIC_TEST') !== '1'
    || getenv('DOLI_DB_NAME') !== 'norway_test') { exit(1); }
require '/var/www/html/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/norway/core/modules/modNorway.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
function nvAssert($ok, $message) { if (!$ok) { throw new RuntimeException($message); } }
function nvSql($sql) { global $db; $r=$db->query($sql); nvAssert((bool)$r,$db->lasterror()); return $r; }
$admin=new User($db); nvAssert($admin->fetch('', 'testadmin')>0,'Admin missing'); $admin->loadRights('',1); $user=$admin;
foreach (array('modProduct','modService') as $dependency) {
    $result=activateModule($dependency); nvAssert(empty($result['errors']),'Synthetic catalogue activation failed');
}
$conf->setValues($db);
$module=new modNorway($db);
nvAssert(dolibarr_set_const($db,'MAIN_MODULE_NORWAY_TABS_0','invoice:+oldtab:Old:norway@norway:1:/old.php','chaine',0,'',$conf->entity)>0,'Legacy tab setup failed');
foreach (array(1,2) as $attempt) {
    nvAssert($module->init()>0,'Repeated menu activation: '.$module->error);
    $count=$db->fetch_object(nvSql("SELECT COUNT(*) n FROM ".MAIN_DB_PREFIX."menu WHERE module='norway' AND entity=".(int)$conf->entity));
    nvAssert((int)$count->n===12,'Duplicate or missing module menus');
}
$conf->setValues($db);
foreach ($module->tabs as $i=>$tab) { nvAssert(getDolGlobalString('MAIN_MODULE_NORWAY_TABS_'.$i)===$tab,'Legacy tab not refreshed'); }
$langs->load('norway@norway');
foreach ($module->menu as $menu) { nvAssert($langs->trans($menu['titre'])!==$menu['titre'],'Missing menu translation'); }
$reader=new User($db); $reader->login='synthetic-menu-'.bin2hex(random_bytes(5));
$reader->lastname='SYNTHETIC MENU TEST ONLY'; $reader->pass=bin2hex(random_bytes(16));
$reader->admin=0; $reader->statut=1; $reader->status=1; $reader->entity=$conf->entity;
$cookie=tempnam(sys_get_temp_dir(),'norway-menu-'); $curl=curl_init();
curl_setopt_array($curl,array(CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_TIMEOUT=>20));
function nvPage($path,$post=null) {
    global $curl; curl_setopt($curl,CURLOPT_URL,'http://localhost'.$path); curl_setopt($curl,CURLOPT_POST,$post!==null);
    if ($post!==null) { curl_setopt($curl,CURLOPT_POSTFIELDS,http_build_query($post)); }
    $body=curl_exec($curl); nvAssert($body!==false && curl_getinfo($curl,CURLINFO_HTTP_CODE)<500,'HTTP request failed'); return $body;
}
function nvLogin($login,$password) {
    nvPage('/user/logout.php'); $body=nvPage('/index.php');
    nvAssert(preg_match('/name="token"[^>]*value="([^"]+)"/',$body,$m)===1,'Login token missing');
    $body=nvPage('/index.php',array('token'=>html_entity_decode($m[1],ENT_QUOTES,'UTF-8'),'username'=>$login,'password'=>$password,'actionlogin'=>'login','loginfunction'=>'loginfunction'));
    nvAssert(strpos($body,'name="username"')===false,'Synthetic login failed');
}
function nvDenied($path) { global $curl; $body=nvPage($path); nvAssert(strpos($body,'class="error"')!==false && strpos($body,'norway-page')===false && strpos($body,'name="username"')===false,'Access unexpectedly allowed: '.$path.' HTTP '.curl_getinfo($curl,CURLINFO_HTTP_CODE)); }
try {
    $password=$reader->pass; nvAssert($reader->create($admin,1)>0,'Synthetic reader creation failed');
    $passwordResult=$reader->setPassword($admin,$password,0,1);
    nvAssert(!is_int($passwordResult) || $passwordResult>=0,'Synthetic password setup failed');
    // Remove inherited default rights, then prove direct access is denied.
    nvSql('DELETE FROM '.MAIN_DB_PREFIX.'user_rights WHERE fk_user='.(int)$reader->id);
    nvLogin($reader->login,$password);
    nvDenied('/custom/norway/admin/audit.php'); nvDenied('/custom/norway/admin/periods.php');
    $right=$db->fetch_object(nvSql("SELECT id FROM ".MAIN_DB_PREFIX."rights_def WHERE module='accounting' AND perms='mouvements' AND subperms='lire' AND entity=".(int)$conf->entity));
    nvAssert($right && $reader->addrights((int)$right->id)>0,'Ledger read grant failed');
    nvLogin($reader->login,$password);
    foreach (array('nb_NO','en_US') as $language) {
        foreach (array('audit','periods') as $page) {
            $body=nvPage('/custom/norway/admin/'.$page.'.php?mainmenu=accountancy&lang='.$language);
            nvAssert(curl_getinfo($curl,CURLINFO_HTTP_CODE)===200 && strpos($body,'norway-page')!==false,'Reader page unavailable');
            nvAssert(strpos($body,'admin/wizard.php?step=9')===false && strpos($body,'fiscalyear_card.php?id=')===false,'Reader offered administrator action');
            nvAssert(strpos($body,'admin/audit.php?mainmenu=accountancy')!==false,'Ledger menu missing');
            nvAssert(strpos($body,'admin/periods.php?mainmenu=accountancy')!==false,'Period menu missing');
            nvAssert(strpos($body,'admin/registration.php?mainmenu=accountancy')===false,'Administrator menu exposed');
        }
    }
    foreach (array('accounting','registration','taxmappings','numbering','bankdefaults','evidence','invoice','vat','productaccounts','pricing') as $page) { nvDenied('/custom/norway/admin/'.$page.'.php'); }
    $reader->loadRights('',1);
    nvAssert(NorwayNavigation::canReadLedger($reader),'Reader helper mismatch');
    $reader->socid=999999; nvAssert(!NorwayNavigation::canReadLedger($reader),'External user passed ledger guard'); $reader->socid=0;
    nvLogin('testadmin',getenv('DOLI_ADMIN_PASSWORD'));
    foreach (array(7,8,9,11) as $step) {
        $body=nvPage('/custom/norway/admin/wizard.php?step='.$step);
        foreach (array('invoiceperiod','invoice','audit','periods','pricing') as $daily) {
            nvAssert(strpos($body,'/norway/admin/'.$daily.'.php')===false,'Daily control retained in wizard');
        }
    }
    $body=nvPage('/compta/facture/index.php?mainmenu=billing&leftmenu=customers_bills');
    nvAssert(strpos($body,'/norway/admin/invoiceperiod.php?mainmenu=billing')!==false,'Billing menu missing');
    $body=nvPage('/accountancy/index.php?mainmenu=accountancy&leftmenu=accountancy_admin');
    nvAssert(strpos($body,'/norway/admin/registration.php?mainmenu=accountancy')!==false,'Accounting setup menu missing');
    nvAssert(strpos($body,'/norway/admin/vat.php?mainmenu=accountancy')!==false,'VAT setup menu missing');
    $body=nvPage('/product/index.php?mainmenu=products');
    nvAssert(strpos($body,'/norway/admin/productaccounts.php?mainmenu=products')!==false,'Product codes menu missing');
    $body=nvPage('/comm/propal/index.php?mainmenu=commercial&leftmenu=propals');
    nvAssert(strpos($body,'/norway/admin/pricing.php?mainmenu=commercial')!==false,'Proposal pricing menu missing');
    $body=nvPage('/custom/norway/admin/vat.php');
    foreach (array('vatrates','vataccounts','registration','taxmappings') as $page) { nvAssert(strpos($body,'/norway/admin/'.$page.'.php')!==false,'VAT setup action missing'); }
    $body=nvPage('/compta/bank/list.php?mainmenu=bank');
    nvAssert(strpos($body,'/norway/admin/bankdefaults.php?mainmenu=bank')!==false,'Bank setup menu missing');
    $invoice=$db->fetch_object(nvSql('SELECT rowid FROM '.MAIN_DB_PREFIX.'facture WHERE entity='.(int)$conf->entity.' ORDER BY rowid DESC LIMIT 1'));
    nvAssert((bool)$invoice,'Synthetic invoice required');
    $body=nvPage('/compta/facture/card.php?facid='.(int)$invoice->rowid);
    nvAssert(strpos($body,'/norway/admin/invoice.php?id='.(int)$invoice->rowid)!==false,'Native invoice control tab missing');
    nvAssert(strpos($body,'/norway/admin/evidence.php?id='.(int)$invoice->rowid)!==false,'Native invoice evidence tab missing');
    $body=nvPage('/custom/norway/admin/evidence.php?id='.(int)$invoice->rowid);
    nvAssert(strpos($body,'/compta/facture/card.php?facid='.(int)$invoice->rowid)!==false,'Evidence return link missing');
    $productIds=array();
    foreach (array(0,1) as $type) {
        $ref='SYN-MENU-'.bin2hex(random_bytes(6));
        nvSql("INSERT INTO ".MAIN_DB_PREFIX."product (ref,label,entity,fk_product_type,tosell,tobuy,tva_tx) VALUES ('".$ref."','SYNTHETIC MENU ITEM ONLY',".(int)$conf->entity.",".$type.",1,1,25)");
        $productId=(int)$db->last_insert_id(MAIN_DB_PREFIX.'product'); $productIds[]=$productId;
        foreach (array('nb_NO','en_US') as $language) {
            $body=nvPage('/product/card.php?id='.$productId.'&lang='.$language);
            nvAssert(strpos($body,'/norway/admin/productaccounts.php?id='.$productId)!==false,'Native product/service tab missing');
            $body=nvPage('/custom/norway/admin/productaccounts.php?id='.$productId.'&scope=export&lang='.$language);
            nvAssert(strpos($body,'name="id" value="'.$productId.'"')!==false && strpos($body,$ref)!==false,'Selected product scope lost');
            nvAssert(strpos($body,'/product/card.php?id='.$productId)!==false,'Product return link missing');
        }
    }
    $body=nvPage('/custom/norway/admin/productaccounts.php?id=0');
    foreach ($productIds as $productId) { nvAssert(strpos($body,'/product/card.php?id='.$productId)===false,'Invalid id broadened product report'); }
    $body=nvPage('/custom/norway/admin/invoice.php?id='.(int)$invoice->rowid);
    nvAssert(strpos($body,'/compta/facture/card.php?facid='.(int)$invoice->rowid)!==false,'Invoice return link missing');
    nvAssert($module->remove()>0,'Module removal failed');
    $count=$db->fetch_object(nvSql("SELECT COUNT(*) n FROM ".MAIN_DB_PREFIX."menu WHERE module='norway' AND entity=".(int)$conf->entity));
    nvAssert((int)$count->n===0,'Disabled module left menus behind');
    nvDenied('/custom/norway/admin/audit.php');
    nvAssert($module->init()>0,'Module reactivation failed');
    print "PASS native menus, repeated activation, bilingual ledger reader HTTP, denied missing rights/admin actions, external guard, VAT/catalogue/proposal menus, product/service scope, evidence tab, legacy tab refresh and deactivation\n";
} finally {
    foreach ($productIds ?? array() as $productId) { nvSql('DELETE FROM '.MAIN_DB_PREFIX.'product WHERE rowid='.(int)$productId); }
    $user=$admin; $module->init(); if ($reader->id>0) { $reader->delete($admin); }
    curl_close($curl); unlink($cookie);
}
