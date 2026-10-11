<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require '../../../main.inc.php';
if (!$user->admin || !isModEnabled('norway')) { accessforbidden(); }
require_once __DIR__.'/../class/NorwayAccountingSetup.php';
$langs->loadLangs(array('norway@norway', 'accountancy'));
header('Cache-Control: no-store');
llxHeader('', $langs->trans('NorwayAccountingSetup'));
print '<div class="norway-page">';
print load_fiche_titre($langs->trans('NorwayAccountingSetup'), '', 'accountancy');
print '<div class="warning">'.$langs->trans('NorwayAccountingScope').'</div>';
print '<p>'.$langs->trans('NorwayAccountingDefaultsScope').'</p>';
try {
    $settings = array();
    foreach (NorwayAccountingSetup::defaults() as $key) { $settings[$key] = getDolGlobalString($key); }
    $checks = (new NorwayAccountingSetup($db, $conf->entity))->report(getDolGlobalInt('CHARTOFACCOUNTS'), $settings);
    print '<div class="div-table-responsive"><table class="noborder centpercent"><tr class="liste_titre">';
    foreach (array('Item', 'Value', 'Status') as $key) { print '<th>'.$langs->trans('NorwayAccounting'.$key).'</th>'; }
    print '</tr>';
    foreach ($checks as $check) {
        $label = strpos($check['key'], 'ACCOUNTING_') === 0 ? $check['key'] : 'NorwayAccounting'.$check['key'];
        print '<tr><td>'.$langs->trans($label).'</td><td>'.dol_escape_htmltag((string) $check['value']).'</td><td>'
            .$langs->trans('NorwayAccountingStatus'.$check['status']).'</td></tr>';
    }
    print '</table></div>';
} catch (RuntimeException $error) {
    print '<div class="error">'.$langs->trans('NorwayAccountingReadError').'</div>';
}
foreach (array('/accountancy/admin/accountmodel.php' => 'NorwayAccountingChartSettings',
    '/accountancy/admin/defaultaccounts.php' => 'NorwayAccountingDefaultSettings',
    '/accountancy/admin/journals_list.php' => 'NorwayAccountingJournalSettings',
    '/custom/norway/admin/setup.php' => 'NorwaySetup') as $path => $label) {
    print '<p><a href="'.DOL_URL_ROOT.$path.'">'.$langs->trans($label).'</a></p>';
}
print '</div>';
llxFooter();
$db->close();
