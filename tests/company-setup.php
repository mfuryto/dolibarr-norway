<?php
// SPDX-License-Identifier: GPL-3.0-or-later
if (PHP_SAPI!=='cli' || getenv('NORWAY_SYNTHETIC_TEST')!=='1' || getenv('DOLI_DB_NAME')!=='norway_test') { exit(1); }
require '/var/www/html/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/norway/class/NorwayCompanySetup.php';
function companyAssert($ok,$message) { if (!$ok) { throw new RuntimeException($message); } }
function companyRows($sql) { global $db; $r=$db->query($sql); companyAssert((bool)$r,'Synthetic company query failed'); $rows=array(); if ($r===true) { return $rows; } while($row=$db->fetch_object($r)) { $rows[]=(array)$row; } return $rows; }
$prefix=$db->prefix();
$query='SELECT code,fk_pays,libelle,active,module FROM '.$prefix.'c_forme_juridique WHERE code IN ('.implode(',',array_keys(NorwayCompanySetup::forms())).') ORDER BY code';
$companyBefore=companyRows("SELECT name,value,entity FROM ".$prefix."const WHERE name IN ('MAIN_INFO_SIREN','MAIN_INFO_TVAINTRA','MAIN_INFO_SOCIETE_FORME_JURIDIQUE','MAIN_INFO_TVA_ASSUJ') ORDER BY entity,name");
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
companyAssert(companyRows("SELECT name,value,entity FROM ".$prefix."const WHERE name IN ('MAIN_INFO_SIREN','MAIN_INFO_TVAINTRA','MAIN_INFO_SOCIETE_FORME_JURIDIQUE','MAIN_INFO_TVA_ASSUJ') ORDER BY entity,name")===$companyBefore,'Company identity/settings changed');
print "PASS Norwegian legal forms, repeatability, inactive choices, conflicts and company settings preservation\n";
