<?php
// SPDX-License-Identifier: GPL-3.0-or-later
if (PHP_SAPI !== 'cli' || getenv('DOLI_DB_NAME') !== 'accounting'
    || getenv('DOLI_COMPANY_NAME') !== 'SYNTHETIC INSTALLATION ONLY'
    || getenv('NORWAY_EXPECT_EMPTY') !== '1') {
    exit(1);
}
require '/var/www/html/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
if (getenv('NORWAY_CREATE_SENTINEL') === '1') {
    if (dolibarr_set_const($db, 'NORWAY_MIGRATION_SENTINEL', 'synthetic-only', 'chaine', 0, '', $conf->entity) <= 0
        || file_put_contents('/var/www/documents/norway-backup-marker', "synthetic backup marker\n") === false) {
        exit(1);
    }
} elseif (getDolGlobalString('NORWAY_MIGRATION_SENTINEL') !== 'synthetic-only'
    || file_get_contents('/var/www/documents/norway-backup-marker') !== "synthetic backup marker\n") {
    fwrite(STDERR, "Synthetic database/document content was not preserved.\n");
    exit(1);
}
print "PASS synthetic persistent content\n";
$db->close();
