<?php
// SPDX-License-Identifier: GPL-3.0-or-later
if (PHP_SAPI !== 'cli') {
    exit(1);
}
require '/var/www/html/master.inc.php';
if (DOL_VERSION !== '24.0.1' || !isModEnabled('norway') || !isModEnabled('accounting')
    || !isModEnabled('propal') || !isModEnabled('facture') || isModEnabled('multicurrency')
    || $conf->currency !== 'NOK' || getDolGlobalInt('BANK_DISABLE_CHECK_DEPOSIT') !== 1
    || getDolGlobalInt('MAIN_MULTILANGS') !== 1
    || !file_exists('/var/www/documents/install.lock')
    || $conf->file->instance_unique_id !== getenv('DOLI_INSTANCE_UNIQUE_ID')) {
    fwrite(STDERR, "Installation verification failed; retain backup and inspect configuration.\n");
    exit(1);
}
print "PASS core version, enabled modules, instance key and installer lock\n";
$db->close();
