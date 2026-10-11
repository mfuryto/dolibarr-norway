<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// Runs only during initial deployment via the official container's init hook.
if (PHP_SAPI !== 'cli') {
    exit(1);
}
require '/var/www/html/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
if (DOL_VERSION !== '24.0.1') {
    throw new RuntimeException('Unsupported Dolibarr version for Norway module.');
}
foreach (array('MAIN_INFO_SOCIETE_DEVISE' => 'NOK', 'MAIN_LANG_DEFAULT' => 'nb_NO') as $key => $value) {
    if (dolibarr_set_const($db, $key, $value, 'chaine', 0, '', $conf->entity) <= 0) {
        throw new RuntimeException('Could not initialise '.$key);
    }
}
$result = activateModule('modNorway');
if (!empty($result['errors'])) {
    throw new RuntimeException(implode('; ', $result['errors']));
}
print "Norwegian module foundation enabled; base currency NOK, language nb_NO.
";
$db->close();
