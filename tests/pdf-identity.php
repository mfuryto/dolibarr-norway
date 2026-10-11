<?php
// SPDX-License-Identifier: GPL-3.0-or-later
if (PHP_SAPI !== 'cli' || getenv('NORWAY_SYNTHETIC_TEST') !== '1') { exit(1); }
require '/var/www/html/custom/norway/class/actions_norway.class.php';
function expectIdentity($condition, $message) { if (!$condition) { throw new RuntimeException($message); } }
class IdentityTestLanguage
{
    public function load($file) {}
    public function transnoentities($key) { return 'Org.nr.'; }
    public function convToOutputCharset($value) { return $value; }
}
$conf = (object) array('entity'=>1);
$hook = new ActionsNorway(null);
$source = (object) array('country_code'=>'NO', 'idprof1'=>'123 456 785', 'tva_assuj'=>1);
$object = (object) array('element'=>'facture', 'entity'=>1);
$action = '';
$params = array('sourcecompany'=>$source, 'mode'=>'source', 'outputlangs'=>new IdentityTestLanguage());
$before = serialize($source);
expectIdentity($hook->pdf_build_address($params, $object, $action, null) === 0, 'Core address replaced');
expectIdentity($hook->resprints === 'Org.nr.: 123 456 785 MVA', 'Registered identity missing');
expectIdentity(serialize($source) === $before, 'Company fields changed during rendering');
$source->tva_assuj = 0;
$hook->pdf_build_address($params, $object, $action, null);
expectIdentity($hook->resprints === 'Org.nr.: 123 456 785', 'MVA shown when configured unregistered');
foreach (array('mode'=>'target', 'element'=>'supplier_invoice', 'entity'=>2, 'country_code'=>'SE', 'idprof1'=>'123456786') as $key=>$value) {
    $testParams = $params; $testObject = clone $object; $testSource = clone $source;
    if ($key === 'mode') { $testParams['mode'] = $value; }
    elseif (in_array($key, array('element','entity'), true)) { $testObject->$key = $value; }
    else { $testSource->$key = $value; }
    $testParams['sourcecompany'] = $testSource;
    $hook->pdf_build_address($testParams, $testObject, $action, null);
    expectIdentity($hook->resprints === '', 'Unrelated or invalid identity affected: '.$key);
}
print "PASS PDF identity registration flag, checksum, entity, recipient and source immutability\n";
