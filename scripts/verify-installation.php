<?php
// SPDX-License-Identifier: GPL-3.0-or-later
if (PHP_SAPI !== 'cli') {
    exit(1);
}
require '/var/www/html/master.inc.php';
if (DOL_VERSION !== '24.0.1' || !isModEnabled('norway') || !isModEnabled('accounting')
    || !file_exists('/var/www/documents/install.lock')
    || $conf->file->instance_unique_id !== getenv('DOLI_INSTANCE_UNIQUE_ID')) {
    fwrite(STDERR, "Installation verification failed; retain backup and inspect configuration.\n");
    exit(1);
}
print "PASS core version, enabled modules, instance key and installer lock\n";
$db->close();
