<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require '../../../main.inc.php';
if (!$user->admin || !isModEnabled('norway')) { accessforbidden(); }
require_once __DIR__.'/../class/NorwayVatSetup.php';
$langs->load('norway@norway'); header('Cache-Control: no-store');
llxHeader('',$langs->trans('NorwayVatSetup'));
print '<div class="norway-page">'.load_fiche_titre($langs->trans('NorwayVatSetup'),'','accountancy');
print '<div class="warning">'.$langs->trans('NorwayVatSetupScope').'</div>';
print '<p>'.$langs->trans('NorwayVatSetupTreatment').'</p>';
print '<p><a href="'.dol_buildpath('/norway/admin/vatrates.php',1).'">'.$langs->trans('NorwayVatRatesTitle').'</a></p>';
try {
    $rows=NorwayVatSetup::report($db,$conf->entity,getDolGlobalInt('CHARTOFACCOUNTS'),array('sell'=>getDolGlobalString('ACCOUNTING_VAT_SOLD_ACCOUNT'),'buy'=>getDolGlobalString('ACCOUNTING_VAT_BUY_ACCOUNT')));
    if (!$rows) { print '<div class="warning">'.$langs->trans('NorwayVatSetupEmpty').'</div>'; }
    print '<div class="div-table-responsive"><table class="noborder centpercent"><tr class="liste_titre">';
    foreach (array('Rate','Direction','Explicit','Fallback','Account','Status') as $key) { print '<th>'.$langs->trans('NorwayVatSetup'.$key).'</th>'; } print '</tr>';
    foreach ($rows as $row) {
        print '<tr><td>'.dol_escape_htmltag($row['rate'].' % / '.$row['code'].' (#'.$row['id'].')').'</td><td>'.$langs->trans('NorwayVatSetup'.$row['direction']).'</td><td>'.dol_escape_htmltag($row['explicit']).'</td><td>'.dol_escape_htmltag($row['fallback']).'</td><td>'.dol_escape_htmltag($row['number'].' — '.$row['label']).'<br>'.$langs->trans('NorwayVatSetupSource'.$row['source']).'</td><td>'.$langs->trans('NorwayAccountingStatus'.$row['status']).'</td></tr>';
    }
    print '</table></div>';
} catch (RuntimeException $error) { print '<div class="error">'.$langs->trans('NorwayVatSetupReadError').'</div>'; }
foreach (array('/admin/dict.php?id=10'=>'NorwayVatSetupDictionary','/accountancy/admin/defaultaccounts.php'=>'NorwayAccountingDefaultSettings','/custom/norway/admin/wizard.php?step=3'=>'NorwayWizard') as $path=>$label) { print '<p><a href="'.DOL_URL_ROOT.$path.'">'.$langs->trans($label).'</a></p>'; }
print '<p><a href="'.dol_buildpath('/norway/admin/vataccounts.php',1).'">'.$langs->trans('NorwayVatAccountsTitle').'</a></p>';
print '</div>'; llxFooter(); $db->close();
