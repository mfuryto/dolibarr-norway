<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require '../../../main.inc.php';
if (!$user->admin || !isModEnabled('norway')) { accessforbidden(); }
require_once __DIR__.'/../class/NorwayPeriodSetup.php';
$langs->load('norway@norway'); header('Cache-Control: no-store');
$date=GETPOST('date','alphanohtml'); if ($date==='') { $date=dol_print_date(dol_now(),'%Y-%m-%d'); }
llxHeader('',$langs->trans('NorwayPeriods'));
print '<div class="norway-page">'.load_fiche_titre($langs->trans('NorwayPeriods'),'','accountancy');
print '<div class="warning">'.$langs->trans('NorwayPeriodsScope').'</div>';
print '<form method="get"><label>'.$langs->trans('NorwayPeriodsDate').' <input type="date" name="date" value="'.dol_escape_htmltag($date).'" required></label> <button class="button" type="submit">'.$langs->trans('NorwayPeriodsShow').'</button></form>';
try {
    $report=NorwayPeriodSetup::report($db,$conf->entity,$date,getDolGlobalInt('ACCOUNTANCY_FISCALYEAR_DEFAULT'),getDolGlobalString('ACCOUNTANCY_FISCAL_PERIOD_MODE'));
    print '<p>'.$langs->trans('NorwayPeriodsCoverage').': '.dol_escape_htmltag($date).' — '.$langs->trans('NorwayPeriods'.$report['coverage']).'</p>';
    print '<p>'.$langs->trans('NorwayPeriodsDefault').': '.(int)$report['default_id'].' — '.$langs->trans('NorwayPeriods'.$report['default']).'</p>';
    print '<p>'.$langs->trans('NorwayPeriodsMode').': '.dol_escape_htmltag($report['mode']).'<br>'.$langs->trans('NorwayPeriodsModeHelp').'</p>';
    if (!$report['rows']) { print '<div class="warning">'.$langs->trans('NorwayPeriodsMissing').'</div>'; }
    print '<div class="div-table-responsive"><table class="noborder centpercent"><tr class="liste_titre">';
    foreach (array('Label','Start','End','Status') as $key) { print '<th>'.$langs->trans('NorwayPeriods'.$key).'</th>'; } print '</tr>';
    foreach ($report['rows'] as $row) {
        print '<tr><td><a href="'.DOL_URL_ROOT.'/accountancy/admin/fiscalyear_card.php?id='.$row['id'].'">'.dol_escape_htmltag($row['label']).'</a></td><td>'.dol_escape_htmltag($row['start']).'</td><td>'.dol_escape_htmltag($row['end']).'</td><td>'.$langs->trans('NorwayPeriods'.$row['status']).'</td></tr>';
    }
    print '</table></div>';
} catch (InvalidArgumentException $error) { print '<div class="error">'.$langs->trans('NorwayPeriodsInvalidDate').'</div>'; }
catch (RuntimeException $error) { print '<div class="error">'.$langs->trans('NorwayPeriodsReadError').'</div>'; }
foreach (array('/accountancy/admin/fiscalyear.php'=>'NorwayWizardPeriods','/custom/norway/admin/wizard.php?step=9'=>'NorwayWizard') as $path=>$label) { print '<p><a href="'.DOL_URL_ROOT.$path.'">'.$langs->trans($label).'</a></p>'; }
print '</div>'; llxFooter(); $db->close();
