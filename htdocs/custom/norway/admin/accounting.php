<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require '../../../main.inc.php';
if (!$user->admin || !empty($user->socid) || !isModEnabled('norway')) { accessforbidden(); }
$langs->load('norway@norway');llxHeader('',$langs->trans('NorwayAccountingSettings'));
print '<div class="norway-page">'.load_fiche_titre($langs->trans('NorwayAccountingSettings'),'','accountancy');
print '<p>'.$langs->trans('NorwaySimpleAccounting').'</p>';
foreach (array('/custom/norway/admin/profile.php'=>'NorwayProfile','/accountancy/admin/account.php'=>'NorwaySetupLinkChart','/accountancy/admin/defaultaccounts.php'=>'NorwayAccountingDefaultSettings','/accountancy/admin/journals_list.php'=>'NorwayJournals','/compta/bank/list.php'=>'NorwayWizardTaskBank','/custom/norway/admin/vatrates.php'=>'NorwayVatRatesTitle','/admin/dict.php?id=10'=>'NorwaySetupLinkVatAccounts','/accountancy/admin/productaccount.php'=>'NorwayProductAccountsTitle','/accountancy/admin/fiscalyear.php'=>'NorwayPeriods','/accountancy/admin/closure.php'=>'NorwaySetupLinkYearEnd') as $path=>$label) {
    print '<p><a href="'.DOL_URL_ROOT.$path.'">'.$langs->trans($label).'</a></p>';
}
print '</div>';llxFooter();$db->close();
