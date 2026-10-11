<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require '../../../main.inc.php';
if (!$user->admin || !isModEnabled('norway')) { accessforbidden(); }
require_once __DIR__.'/../class/NorwayAccountingSetup.php';
require_once __DIR__.'/../class/NorwayBankSetup.php';
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
    $service = new NorwayAccountingSetup($db, $conf->entity);
    $checks = $service->report(getDolGlobalInt('CHARTOFACCOUNTS'), $settings);
    print '<div class="div-table-responsive"><table class="noborder centpercent"><tr class="liste_titre">';
    foreach (array('Item', 'Value', 'Status') as $key) { print '<th>'.$langs->trans('NorwayAccounting'.$key).'</th>'; }
    print '</tr>';
    foreach ($checks as $check) {
        $label = strpos($check['key'], 'ACCOUNTING_') === 0 ? $check['key'] : 'NorwayAccounting'.$check['key'];
        print '<tr><td>'.$langs->trans($label).'</td><td>'.dol_escape_htmltag((string) $check['value']).'</td><td>'
            .$langs->trans('NorwayAccountingStatus'.$check['status']).'</td></tr>';
    }
    print '</table></div>';
    $suggestions = $service->suggestions(getDolGlobalInt('CHARTOFACCOUNTS'), $settings);
    if ($suggestions) {
        print '<h2>'.$langs->trans('NorwayAccountingSuggestions').'</h2>';
        print '<p>'.$langs->trans('NorwayAccountingSuggestionsScope').'</p>';
        print '<div class="div-table-responsive"><table class="noborder centpercent"><tr class="liste_titre">';
        foreach (array('Item', 'Value', 'Suggested', 'Status') as $key) {
            print '<th>'.$langs->trans('NorwayAccounting'.$key).'</th>';
        }
        print '</tr>';
        foreach ($suggestions as $suggestion) {
            print '<tr><td>'.$langs->trans($suggestion['key']).'</td><td>'.dol_escape_htmltag($suggestion['current'])
                .'</td><td>'.dol_escape_htmltag($suggestion['number'].' — '.$suggestion['label']).'</td><td>'
                .$langs->trans('NorwayAccountingStatus'.$suggestion['status']).'</td></tr>';
        }
        print '</table></div>';
    }

    $banks = (new NorwayBankSetup($db, $conf->entity))->report(getDolGlobalInt('CHARTOFACCOUNTS'));
    print '<h2>'.$langs->trans('NorwayBankSetup').'</h2>';
    print '<p>'.$langs->trans('NorwayBankSetupScope').'</p>';
    if (!$banks) { print '<div class="warning">'.$langs->trans('NorwayBankSetupEmpty').'</div>'; }
    else {
        print '<div class="div-table-responsive"><table class="noborder centpercent"><tr class="liste_titre">';
        foreach (array('Account', 'Ledger', 'Journal', 'Currency', 'Shared') as $key) {
            print '<th>'.$langs->trans('NorwayBankSetup'.$key).'</th>';
        }
        print '</tr>';
        foreach ($banks as $bank) {
            print '<tr><td><a href="'.DOL_URL_ROOT.'/compta/bank/card.php?id='.$bank['id'].'">'
                .dol_escape_htmltag($bank['ref'].' — '.$bank['label']).'</a>'
                .($bank['cash'] ? ' ('.$langs->trans('NorwayBankSetupCash').')' : '').'</td>';
            foreach (array('number' => 'account_status', 'journal' => 'journal_status', 'currency' => 'currency_status') as $field => $status) {
                print '<td>'.dol_escape_htmltag((string) $bank[$field]).'<br>'
                    .$langs->trans('NorwayAccountingStatus'.$bank[$status]).'</td>';
            }
            print '<td>'.$langs->trans('NorwayBankSetupShared'.$bank['shared_status']).'</td></tr>';
        }
        print '</table></div>';
    }
    print '<p><a href="'.DOL_URL_ROOT.'/compta/bank/list.php">'.$langs->trans('NorwayBankSetupOpen').'</a></p>';
} catch (RuntimeException $error) {
    print '<div class="error">'.$langs->trans('NorwayAccountingReadError').'</div>';
}
foreach (array('/custom/norway/admin/defaults.php' => 'NorwayDefaults',
    '/accountancy/admin/accountmodel.php' => 'NorwayAccountingChartSettings',
    '/accountancy/admin/defaultaccounts.php' => 'NorwayAccountingDefaultSettings',
    '/accountancy/admin/journals_list.php' => 'NorwayAccountingJournalSettings',
    '/custom/norway/admin/setup.php' => 'NorwaySetup') as $path => $label) {
    print '<p><a href="'.DOL_URL_ROOT.$path.'">'.$langs->trans($label).'</a></p>';
}
print '<p><a href="'.dol_buildpath('/norway/admin/chart.php', 1).'">'.$langs->trans('NorwayChartPreview').'</a></p>';
print '<p><a href="'.dol_buildpath('/norway/admin/profile.php', 1).'">'.$langs->trans('NorwayProfile').'</a></p>';
print '</div>';
llxFooter();
$db->close();
