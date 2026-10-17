<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// Idempotent provisioning of native sales and VAT payment/document features.
if (PHP_SAPI !== 'cli') { exit(1); }
require '/var/www/html/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
if (DOL_VERSION !== '24.0.1') { throw new RuntimeException('Unsupported core version'); }
foreach (array('modSociete' => 'societe', 'modPropale' => 'propal', 'modFacture' => 'facture', 'modTax' => 'tax') as $module => $code) {
    if (isModEnabled($code)) { continue; }
    $result = activateModule($module);
    if (!empty($result['errors'])) { throw new RuntimeException('Sales module activation failed'); }
}
$conf->setValues($db);
if (!is_writable($conf->tax->dir_output)) { throw new RuntimeException('Native VAT document directory is not writable by the current application user'); }
require_once DOL_DOCUMENT_ROOT.'/custom/norway/core/modules/modNorway.class.php';
$norway = new modNorway($db);
if ($norway->init() <= 0) { throw new RuntimeException($norway->error); }
print "Sales modules, Norwegian/English documents, NOK only and cheque restrictions configured\n";
$db->close();
