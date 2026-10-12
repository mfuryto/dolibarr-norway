<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require '../../../main.inc.php';
if (!$user->admin || !isModEnabled('norway') || !isModEnabled('accounting') || !$user->hasRight('accounting','chartofaccount')) { accessforbidden(); }
require_once __DIR__.'/../class/NorwayProductAccounts.php';
require_once __DIR__.'/../class/NorwayStandardAccounts.php';
$langs->load('norway@norway');header('Cache-Control: no-store');
llxHeader('',$langs->trans('NorwayProductAccountsTitle'));
print '<div class="norway-page">'.load_fiche_titre($langs->trans('NorwayProductAccountsTitle'),'','accountancy');
print '<p>'.$langs->trans('NorwayProductAccountsScope').'</p><div class="warning">'.$langs->trans('NorwayProductAccountsLimits').'</div>';
$scope=GETPOST('scope','aZ09');if ($scope==='') { $scope='domestic'; }
print '<form method="get"><label>'.$langs->trans('NorwayProductAccountsContext').' <select name="scope">';
foreach (array('domestic','export','intra') as $option) { print '<option value="'.$option.'"'.($scope===$option ? ' selected' : '').'>'.$langs->trans('NorwayProductAccountsScope'.$option).'</option>'; }
print '</select></label><button class="button" type="submit">'.$langs->trans('NorwayProductAccountsShow').'</button></form>';
try {
    $defaults=array();foreach (NorwayStandardAccounts::catalog() as $key=>$rule) { $defaults[$key]=getDolGlobalString($key); }
    $report=NorwayProductAccounts::report($db,(int)$conf->entity,getDolGlobalInt('CHARTOFACCOUNTS'),$defaults,$scope);
    if ($report['truncated']) { print '<div class="warning">'.$langs->trans('NorwayProductAccountsTruncated').'</div>'; }
    if (!$report['rows']) { print '<p>'.$langs->trans('NorwayProductAccountsEmpty').'</p>'; }
    else {
        print '<div class="div-table-responsive"><table class="noborder centpercent"><tr>';
        foreach (array('Item','Direction','Rate','Explicit','Fallback','Account','Status') as $column) { print '<th>'.$langs->trans('NorwayProductAccounts'.$column).'</th>'; }print '</tr>';
        foreach ($report['rows'] as $row) {
            print '<tr><td><a href="'.DOL_URL_ROOT.'/product/card.php?id='.$row['id'].'">'.dol_escape_htmltag($row['ref'].' — '.$row['label']).'</a><br>'.$langs->trans('NorwayProductAccounts'.$row['type']).'</td><td>'.$langs->trans('NorwayVatSetup'.$row['direction']).'</td><td>'.($row['rate']===null ? '—' : dol_escape_htmltag($row['rate'].' %')).'</td><td>'.dol_escape_htmltag($row['explicit']).'</td><td>'.dol_escape_htmltag($row['fallback']).'</td><td>'.dol_escape_htmltag($row['number'].' — '.$row['account_label']).'<br>'.$langs->trans('NorwayProductAccountsSource'.$row['source']).'</td><td>'.$langs->trans('NorwayAccountingStatus'.$row['status']).'</td></tr>';
        }print '</table></div>';
    }
} catch (Throwable $error) { print '<div class="error">'.$langs->trans('NorwayProductAccountsError').'</div>'; }
print '<p><a href="'.DOL_URL_ROOT.'/product/list.php?type=0">'.$langs->trans('NorwayStandardProducts').'</a> · <a href="'.DOL_URL_ROOT.'/product/list.php?type=1">'.$langs->trans('NorwayStandardServices').'</a></p>';
print '<p><a href="'.dol_buildpath('/norway/admin/standardaccounts.php',1).'">'.$langs->trans('NorwayStandardTitle').'</a></p><p><a href="'.dol_buildpath('/norway/admin/wizard.php',1).'?step=6">'.$langs->trans('NorwayWizard').'</a></p></div>';
llxFooter();$db->close();
