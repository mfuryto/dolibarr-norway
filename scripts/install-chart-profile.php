<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// Explicit operator action after backup; not called during module activation.
if (PHP_SAPI !== 'cli') { exit(1); }
require '/var/www/html/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/norway/class/NorwayChartProfile.php';
try {
    if (DOL_VERSION !== '24.0.1' || !isModEnabled('norway') || $conf->currency !== 'NOK'
        || $user->fetch('', getenv('DOLI_ADMIN_LOGIN')) <= 0 || !$user->admin) {
        throw new RuntimeException('Profile');
    }
    $preview = NorwayChartProfile::preview($db, $conf->entity);
    $created = NorwayChartProfile::install($db, $conf->entity, NorwayChartProfile::fingerprint($conf->entity, $preview), $user);
    print 'Norsk kontoplan valgt: '.NorwayChartProfile::VERSION.'; nye kontoer: '.$created."\n";
    print "Kontoklasser er utfylt; egne grupper er bevart. Standardkontoer, journaler, MVA-behandling og SAF-T-mapping er ikke automatisk konfigurert.\n";
} catch (Throwable $error) {
    file_put_contents('php://stderr', "Kontoplaninstallering stoppet: eksisterende plan/historikk, konflikt eller teknisk feil. Ingen eksisterende plan erstattes. Bruk modulens kontoplanside for kontroll.\n");
    exit(1);
}
$db->close();
