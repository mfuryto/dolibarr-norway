<?php
// SPDX-License-Identifier: GPL-3.0-or-later
if (PHP_SAPI !== 'cli' || getenv('NORWAY_SYNTHETIC_TEST') !== '1'
    || getenv('DOLI_DB_NAME') !== 'norway_test'
    || getenv('DOLI_COMPANY_NAME') !== 'SYNTHETIC NORWAY TEST ONLY') { exit(1); }
require '/var/www/html/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/norway/class/NorwayPolicy.php';
foreach (array('facture', 'facture_fourn', 'propal', 'commande', 'commande_fournisseur',
    'accounting_bookkeeping', 'bank', 'paiement', 'paiementfourn') as $table) {
    $res = $db->query('SELECT COUNT(*) AS n FROM '.MAIN_DB_PREFIX.$table);
    if (!$res || (int) $db->fetch_object($res)->n !== 0) { throw new RuntimeException('Empty synthetic database required'); }
}
foreach (array('MAIN_INFO_SOCIETE_DEVISE' => 'NOK', 'MAIN_MONNAIE' => 'EUR') as $key => $value) {
    if (dolibarr_set_const($db, $key, $value, 'chaine', 0, '', $conf->entity) <= 0) { throw new RuntimeException('Synthetic setting failed'); }
}
$conf->setValues($db);
if ($conf->currency !== 'EUR') { throw new RuntimeException('Legacy core currency not reproduced'); }
NorwayPolicy::apply($db, $conf);
$conf->setValues($db);
if ($conf->currency !== 'NOK') { throw new RuntimeException('Empty legacy instance not corrected'); }
print "PASS empty legacy installation corrected using core MAIN_MONNAIE setting\n";
$db->close();
