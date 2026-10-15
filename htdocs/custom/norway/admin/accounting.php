<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require '../../../main.inc.php';
if (!$user->admin || !empty($user->socid) || !isModEnabled('norway')) { accessforbidden(); }
$langs->load('norway@norway');llxHeader('',$langs->trans('NorwayAccountingSettings'));
print '<div class="norway-page">'.load_fiche_titre($langs->trans('NorwayAccountingSettings'),'','accountancy');
print '<p>'.$langs->trans('NorwaySimpleAccounting').'</p>';
foreach (array('/custom/norway/admin/profile.php'=>'NorwayProfile','/custom/norway/admin/defaults.php'=>'NorwayDefaults','/custom/norway/admin/standardaccounts.php'=>'NorwayStandardTitle','/custom/norway/admin/vatrates.php'=>'NorwayVatRatesTitle','/custom/norway/admin/vataccounts.php'=>'NorwayVatAccountsTitle','/custom/norway/admin/journals.php'=>'NorwayJournals','/custom/norway/admin/bankdefaults.php'=>'NorwayBankDefaults','/accountancy/admin/productaccount.php'=>'NorwayProductAccountsTitle','/accountancy/admin/fiscalyear.php'=>'NorwayPeriods','/accountancy/admin/defaultaccounts.php'=>'NorwayAccountingDefaultSettings') as $path=>$label) {
    print '<p><a href="'.DOL_URL_ROOT.$path.'">'.$langs->trans($label).'</a></p>';
}
print '</div>';llxFooter();$db->close();
