<?php
// SPDX-License-Identifier: GPL-3.0-or-later
define('CSRFCHECK_WITH_TOKEN', 1);
require '../../../main.inc.php';
if (GETPOST('errorcode', 'aZ09') === 'InvalidToken') { httponly_accessforbidden('Invalid security token'); }
if (!$user->admin || !isModEnabled('norway')) { accessforbidden(); }
require_once __DIR__.'/../class/NorwayChartPreview.php';
$langs->load('norway@norway');
header('Cache-Control: no-store');
llxHeader('', $langs->trans('NorwayChartPreview'));
print '<div class="norway-page">';
print load_fiche_titre($langs->trans('NorwayChartPreview'), '', 'accountancy');
print '<div class="warning">'.$langs->trans('NorwayChartScope').'</div>';
print '<p>'.$langs->trans('NorwayChartFormat').'</p>';
print '<form method="post" action="'.dol_buildpath('/norway/admin/chart.php', 1).'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="previewchart">';
print '<label style="width:100%;min-width:0;max-width:100%">'.$langs->trans('NorwayChartCSV').' <textarea style="width:100%;max-width:100%;min-width:0;box-sizing:border-box" name="chart_csv" rows="8" cols="80" maxlength="262144" required></textarea></label> ';
print '<button class="button" type="submit">'.$langs->trans('NorwayChartRun').'</button></form>';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && GETPOST('action', 'aZ09') === 'previewchart') {
    try {
        $report = NorwayChartPreview::report($db, $conf->entity, getDolGlobalInt('CHARTOFACCOUNTS'), $_POST['chart_csv'] ?? null);
        print '<p>'.dol_escape_htmltag($report['format'].' / '.$report['chart']).'</p>';
        print '<div class="div-table-responsive"><table class="noborder centpercent"><tr class="liste_titre">';
        foreach (array('Number', 'Label', 'Existing', 'Usage', 'Status') as $key) { print '<th>'.$langs->trans('NorwayChart'.$key).'</th>'; }
        print '</tr>';
        foreach ($report['rows'] as $row) {
            print '<tr>';
            foreach (array('number', 'label', 'existing_label', 'usage') as $key) { print '<td>'.dol_escape_htmltag((string) $row[$key]).'</td>'; }
            print '<td>'.$langs->trans('NorwayChartStatus'.$row['status']).'</td></tr>';
        }
        print '</table></div>';
    } catch (InvalidArgumentException $error) {
        print '<div class="error">'.$langs->trans($error->getMessage() === 'Chart' ? 'NorwayChartInvalidChart' : 'NorwayChartInvalidCSV').'</div>';
    } catch (RuntimeException $error) {
        print '<div class="error">'.$langs->trans('NorwayChartReadError').'</div>';
    }
}
print '<p><a href="'.dol_buildpath('/norway/admin/accounting.php', 1).'">'.$langs->trans('NorwayAccountingSetup').'</a></p>';
print '</div>';
llxFooter();
$db->close();
