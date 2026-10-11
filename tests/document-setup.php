<?php
// SPDX-License-Identifier: GPL-3.0-or-later
if (PHP_SAPI!=='cli' || getenv('NORWAY_SYNTHETIC_TEST')!=='1' || getenv('DOLI_DB_NAME')!=='norway_test') { exit(1); }
require '/var/www/html/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/norway/class/NorwayDocumentSetup.php';
function dsAssert($ok,$message) { if (!$ok) { throw new RuntimeException($message); } }
function dsMap($rows) { return array_column($rows,null,'key'); }
$models=array(array('nom'=>'sponge','type'=>'invoice'),array('nom'=>'azur','type'=>'propal'));
$settings=array('FACTURE_ADDON'=>'mod_facture_terre.php','PROPALE_ADDON'=>'mod_propale_marbre','FACTURE_ADDON_PDF'=>'sponge','PROPALE_ADDON_PDF'=>'azur','MAIN_MULTILANGS'=>'1','MAIN_LANG_DEFAULT'=>'nb_NO');
$before=$settings; $rows=dsMap(NorwayDocumentSetup::evaluate($settings,$models,'NOK'));
dsAssert(count($rows)===11 && count(array_filter($rows,static function($r){return $r['status']!=='Configured';}))===0,'Configured setup rejected');
dsAssert($settings===$before,'Read-only evaluation changed settings');
dsAssert(!isset($rows['FACTURE_ADDON_PDF_4'],$rows['FACTURE_ADDON_PDF_5']),'Unused invoice types shown');
$settings['INVOICE_USE_SITUATION']='1'; $settings['FACTURE_ADDON_PDF_2']='custom-synthetic';
$rows=dsMap(NorwayDocumentSetup::evaluate($settings,$models,'NOK'));
dsAssert(isset($rows['FACTURE_ADDON_PDF_5']) && $rows['FACTURE_ADDON_PDF_2']['status']==='Missing','Effective credit PDF override ignored');
$models[]=array('nom'=>'custom-synthetic','type'=>'invoice');
$rows=dsMap(NorwayDocumentSetup::evaluate($settings,$models,'NOK'));
dsAssert($rows['FACTURE_ADDON_PDF_2']['status']==='Review','Untested model approved');
$settings['FACTURE_ADDON']='../../synthetic'; $settings['PROPALE_ADDON']='mod_propale_saphir';
$rows=dsMap(NorwayDocumentSetup::evaluate($settings,$models,'EUR'));
dsAssert($rows['FACTURE_ADDON']['status']==='Review' && $rows['PROPALE_SAPHIR_MASK']['status']==='Missing' && $rows['Currency']['status']==='Review','Unknown numbering or currency accepted');
$settings['FACTURE_ADDON']='mod_facture_mercure'; $settings['FACTURE_MERCURE_MASK_INVOICE']='SYN-{0000}';
$rows=dsMap(NorwayDocumentSetup::evaluate($settings,$models,'NOK'));
dsAssert($rows['FACTURE_MERCURE_MASK_CREDIT']['status']==='Missing' && $rows['FACTURE_MERCURE_MASK_INVOICE']['status']==='Review','Numbering mask claimed validated');
$settings['MAIN_MULTILANGS']='0'; $settings['MAIN_LANG_DEFAULT']='fr_FR';
$rows=dsMap(NorwayDocumentSetup::evaluate($settings,$models,'NOK'));
dsAssert($rows['MAIN_MULTILANGS']['status']==='Missing' && $rows['MAIN_LANG_DEFAULT']['status']==='Review','Unsupported language configuration hidden');
$oldEntity=$conf->entity; $conf->entity=987655; $db->begin();
try {
    foreach (array(array(987655,'invoice','sponge'),array(987656,'propal','azur')) as $model) {
        dsAssert((bool)$db->query("INSERT INTO ".$db->prefix()."document_model (entity,type,nom) VALUES (".$model[0].",'".$model[1]."','".$model[2]."')"),'Synthetic model fixture failed');
    }
    $rows=dsMap(NorwayDocumentSetup::report($db,987655,$before,'NOK'));
    dsAssert($rows['FACTURE_ADDON_PDF']['status']==='Configured' && $rows['PROPALE_ADDON_PDF']['status']==='Missing','Foreign model registration leaked');
    try { NorwayDocumentSetup::report($db,987656,$before,'NOK'); throw new LogicException('Foreign entity accepted'); } catch (InvalidArgumentException $expected) {}
} finally { $db->rollback(); $conf->entity=$oldEntity; }
$failed=new class { public function prefix(){return 'synthetic_';} public function query($sql){return false;} };
try { NorwayDocumentSetup::report($failed,$conf->entity,$before,'NOK'); throw new LogicException('Query failure hidden'); } catch (RuntimeException $expected) {}
print "PASS read-only document setup, effective PDF overrides, numbering masks, languages, currency, entity scope and query failure\n";
