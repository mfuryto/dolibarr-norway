<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require '../../../main.inc.php';
if (!$user->admin || !empty($user->socid) || !isModEnabled('norway')) { accessforbidden(); }
require_once __DIR__.'/../class/NorwayLegacyArchive.php';
$langs->load('norway@norway');llxHeader('',$langs->trans('NorwaySetup'));
print '<div class="norway-page">'.load_fiche_titre($langs->trans('NorwaySetup'),'','accountancy');
print '<p>'.$langs->trans('NorwaySimpleScope').'</p><p><a class="butAction" href="'.dol_buildpath('/norway/admin/wizard.php',1).'">'.$langs->trans('NorwayWizard').'</a></p>';
foreach (array('/admin/company.php'=>'NorwayCompanySettings','/custom/norway/admin/accounting.php'=>'NorwayAccountingSettings','/custom/norway/admin/numbering.php'=>'NorwayNumberingTitle') as $path=>$label) {
    print '<p><a href="'.DOL_URL_ROOT.$path.'">'.$langs->trans($label).'</a></p>';
}
try { if (NorwayLegacyArchive::hasData($db,(int)$conf->entity,$user)) { print '<p><a href="'.dol_buildpath('/norway/admin/evidence.php',1).'">'.$langs->trans('NorwayArchiveTitle').'</a></p>'; } }
catch (InvalidArgumentException $denied) { }
catch (Throwable $error) { print '<p class="warning">'.$langs->trans('NorwayArchiveUnavailable').'</p>'; }
print '<p>'.$langs->trans('NorwaySimpleLimits').'</p></div>';llxFooter();$db->close();
