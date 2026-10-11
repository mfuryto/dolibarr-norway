<?php
// SPDX-License-Identifier: GPL-3.0-or-later
if (PHP_SAPI!=='cli' || getenv('NORWAY_SYNTHETIC_TEST')!=='1' || getenv('DOLI_DB_NAME')!=='norway_test') { exit(1); }
require '/var/www/html/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/norway/class/NorwayCompanySetup.php';
function companyAssert($ok,$message) { if (!$ok) { throw new RuntimeException($message); } }
function companyRows($sql) { global $db; $r=$db->query($sql); companyAssert((bool)$r,'Synthetic company query failed'); $rows=array(); if ($r===true) { return $rows; } while($row=$db->fetch_object($r)) { $rows[]=(array)$row; } return $rows; }
$prefix=$db->prefix();
$query='SELECT code,fk_pays,libelle,active,module FROM '.$prefix.'c_forme_juridique WHERE code IN ('.implode(',',array_keys(NorwayCompanySetup::forms())).') ORDER BY code';
$companyBefore=companyRows("SELECT name,value,entity FROM ".$prefix."const WHERE name IN ('MAIN_INFO_SIREN','MAIN_INFO_TVAINTRA','MAIN_INFO_SOCIETE_FORME_JURIDIQUE','FACTURE_TVAOPTION') ORDER BY entity,name");
NorwayCompanySetup::install($db); $before=companyRows($query);
companyAssert(count($before)===14,'Norwegian forms missing');
foreach ($before as $row) { companyAssert($row['libelle']===NorwayCompanySetup::forms()[(int)$row['code']],'Form meaning changed'); }
NorwayCompanySetup::install($db);
companyAssert(companyRows($query)===$before,'Repeated dictionary setup changed rows');
$db->begin();
try {
    companyRows('UPDATE '.$prefix.'c_forme_juridique SET active=0 WHERE code=173001');
    NorwayCompanySetup::install($db);
    companyAssert((int)companyRows($query)[0]['active']===0,'Inactive form reactivated');
} finally { $db->rollback(); }
foreach (array("libelle='Synthetic conflict'","fk_pays=1") as $change) {
    $db->begin();
    try {
        companyRows('UPDATE '.$prefix.'c_forme_juridique SET '.$change.' WHERE code=173001');
        $conflicted=companyRows($query);
        try { NorwayCompanySetup::install($db); throw new LogicException('Dictionary conflict overwritten'); }
        catch (RuntimeException $expected) {}
        companyAssert(companyRows($query)===$conflicted,'Conflict changed dictionary');
    } finally { $db->rollback(); }
}
companyAssert(companyRows($query)===$before,'Fixture rollback failed');
companyAssert(companyRows("SELECT name,value,entity FROM ".$prefix."const WHERE name IN ('MAIN_INFO_SIREN','MAIN_INFO_TVAINTRA','MAIN_INFO_SOCIETE_FORME_JURIDIQUE','FACTURE_TVAOPTION') ORDER BY entity,name")===$companyBefore,'Company identity/settings changed');
print "PASS Norwegian legal forms, repeatability, inactive choices, conflicts and company settings preservation\n";

require_once DOL_DOCUMENT_ROOT.'/custom/norway/class/actions_norway.class.php';
$originalPost=$_POST; $originalGet=$_GET; $originalServer=$_SERVER; $originalUser=$user;
try {
    $user=(object)array('admin'=>1); $_GET=array();
    $_SERVER['SCRIPT_FILENAME']='/var/www/html/admin/company.php'; $_SERVER['REQUEST_METHOD']='POST';
    $country=companyRows("SELECT rowid FROM ".$prefix."c_country WHERE code='NO'")[0]['rowid'];
    $base=array('country_id'=>$country,'siren'=>'974 760 673','optiontva'=>'1','tva'=>'');
    $object=new stdClass();
    foreach (array(array(),array('siren'=>'123456789'),array('optiontva'=>'0'),array('tva'=>'Existing'),array('country_id'=>1),array('cancel'=>'cancel')) as $change) {
        $_POST=array_replace($base,$change); $beforePost=$_POST; $action='update'; $hook=new ActionsNorway($db);
        $result=$hook->doActions(array(),$object,$action,null);
        if (!$change) {
            companyAssert($result===0 && $_POST['tva']==='974760673MVA' && $action==='update','VAT number not generated for core save');
        } else {
            companyAssert($_POST===$beforePost,'Invalid, foreign, disabled or existing VAT data modified');
            if (isset($change['siren'])) {
                companyAssert($result===-1 && $action==='','Invalid request did not stop core save');
            }
        }
    }
    $_POST=$base; $_SERVER['REQUEST_METHOD']='GET'; $action='update';
    companyAssert((new ActionsNorway($db))->doActions(array(),$object,$action,null)===0 && $_POST['tva']==='','GET changed VAT identifier');
    $_SERVER['REQUEST_METHOD']='POST'; $user->admin=0;
    companyAssert((new ActionsNorway($db))->doActions(array(),$object,$action,null)===0 && $_POST['tva']==='','Non-admin request changed VAT identifier');
} finally { $_POST=$originalPost; $_GET=$originalGet; $_SERVER=$originalServer; $user=$originalUser; }
print "PASS VAT identifier core-save preparation requires Norway, valid organisation number, VAT used and empty identifier\n";
