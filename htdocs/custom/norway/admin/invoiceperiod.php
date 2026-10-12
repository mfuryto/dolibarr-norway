<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require '../../../main.inc.php';
if (!$user->admin || !isModEnabled('norway') || !$user->hasRight('facture', 'lire')) { accessforbidden(); }
require_once __DIR__.'/../class/NorwayInvoicePeriod.php';
$langs->load('norway@norway'); header('Cache-Control: no-store');
$run = GETPOSTISSET('start') || GETPOSTISSET('end');
$start = $run ? GETPOST('start', 'alphanohtml') : dol_print_date(dol_now(), '%Y-%m-01');
$end = $run ? GETPOST('end', 'alphanohtml') : dol_print_date(dol_now(), '%Y-%m-%d');
llxHeader('', $langs->trans('NorwayBatch'));
print '<div class="norway-page">'.load_fiche_titre($langs->trans('NorwayBatch'), '', 'bill');
print '<p>'.$langs->trans('NorwayBatchScope').'</p>';
print '<form method="get"><label>'.$langs->trans('NorwayBatchStart').' <input type="date" name="start" value="'.dol_escape_htmltag($start).'" required></label><label>'.$langs->trans('NorwayBatchEnd').' <input type="date" name="end" value="'.dol_escape_htmltag($end).'" required></label><button class="button" type="submit">'.$langs->trans('NorwayBatchRun').'</button></form>';
if ($run) {
    try {
        $report = NorwayInvoicePeriod::report($db, $conf->entity, $start, $end);
        if ($report['truncated']) { print '<div class="warning">'.$langs->trans('NorwayBatchTruncated').'</div>'; }
        if (!$report['rows']) { print '<p>'.$langs->trans('NorwayBatchEmpty').'</p>'; }
        else {
            print '<p>'.$langs->trans('NorwayBatchCount').': '.count($report['rows']).'</p>';
            print '<div class="div-table-responsive"><table class="noborder centpercent"><tr>';
            foreach (array('Invoice','Date','State','Net','VAT','Result') as $column) { print '<th>'.$langs->trans('NorwayBatch'.$column).'</th>'; } print '</tr>';
            foreach ($report['rows'] as $row) {
                $type = in_array($row['type'], range(0,5), true) ? (string) $row['type'] : 'Unknown';
                $status = in_array($row['status'], range(0,3), true) ? (string) $row['status'] : 'Unknown';
                print '<tr><td><a href="'.dol_buildpath('/norway/admin/invoice.php',1).'?id='.$row['id'].'">'.dol_escape_htmltag($row['ref']).'</a><br>'.$langs->trans('NorwayBatchType'.$type).'</td><td>'.dol_escape_htmltag($row['date']).'</td><td>'.$langs->trans('NorwayBatchStatus'.$status).'</td>';
                foreach (array('net','vat') as $amount) { print '<td>'.($row[$amount] === null ? '—' : price($row[$amount])).'</td>'; }
                print '<td>'.$langs->trans('NorwayBatch'.$row['amount_status']);
                if ($row['zero']) { print '<br>'.$langs->trans('NorwayBatchZero').': '.$row['zero']; }
                if ($row['currency'] !== 'NOK' || $conf->currency !== 'NOK') { print '<br>'.$langs->trans('NorwayBatchCurrency'); }
                print '</td></tr>';
            }
            print '</table></div>';
        }
    } catch (InvalidArgumentException $error) { print '<div class="error">'.$langs->trans('NorwayBatchDates').'</div>'; }
    catch (RuntimeException $error) { print '<div class="error">'.$langs->trans('NorwayBatchReadError').'</div>'; }
}
print '<p><a href="'.dol_buildpath('/norway/admin/wizard.php',1).'?step=7">'.$langs->trans('NorwayWizard').'</a></p></div>';
llxFooter(); $db->close();
