<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require '../../../main.inc.php';
require_once __DIR__.'/../class/NorwayNavigation.php';
if (!NorwayNavigation::canReadLedger($user)) { accessforbidden(); }
require_once __DIR__.'/../class/NorwayLedgerAudit.php';
$langs->load('norway@norway');
header('Cache-Control: no-store');
$start = GETPOSTISSET('date_start') ? GETPOST('date_start', 'alphanohtml') : date('Y-m-01');
$end = GETPOSTISSET('date_end') ? GETPOST('date_end', 'alphanohtml') : date('Y-m-t');
llxHeader('', $langs->trans('NorwayAudit'));
print '<div class="norway-page">';
print load_fiche_titre($langs->trans('NorwayAudit'), '', 'accountancy');
print '<div class="warning">'.$langs->trans('NorwayAuditScope').'</div>';
print '<form method="get" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
print '<label>'.$langs->trans('NorwayAuditFrom').' <input type="date" name="date_start" value="'.dol_escape_htmltag($start).'"></label> ';
print '<label>'.$langs->trans('NorwayAuditUntil').' <input type="date" name="date_end" value="'.dol_escape_htmltag($end).'"></label> ';
print '<input class="button" type="submit" value="'.$langs->trans('NorwayAuditRun').'"></form>';
try {
    $report = (new NorwayLedgerAudit($db, $conf->entity))->vouchers($start, $end);
    if ($report['truncated']) { print '<div class="warning">'.$langs->trans('NorwayAuditTruncated').'</div>'; }
    if (!$report['rows']) { print '<p>'.$langs->trans('NorwayAuditEmpty').'</p>'; }
    else {
        print '<div class="div-table-responsive"><table class="noborder centpercent"><tr class="liste_titre">';
        foreach (array('Date', 'Journal', 'Voucher', 'Debit', 'Credit', 'Difference', 'Missing', 'Other', 'Aggregated', 'Status') as $key) {
            print '<th>'.$langs->trans('NorwayAudit'.$key).'</th>';
        }
        print '</tr>';
        foreach ($report['rows'] as $row) {
            print '<tr>';
            foreach (array($row->doc_date, $row->code_journal, $row->piece_num, $row->total_debit,
                $row->total_credit, $row->difference, $row->missing_sources, $row->other_sources,
                $row->aggregated_lines) as $value) {
                print '<td>'.dol_escape_htmltag((string) $value).'</td>';
            }
            print '<td>'.$langs->trans('NorwayAuditStatus'.$row->status).'</td></tr>';
        }
        print '</table></div>';
    }
} catch (InvalidArgumentException $error) {
    print '<div class="error">'.$langs->trans('NorwayAuditInvalidPeriod').'</div>';
} catch (RuntimeException $error) {
    print '<div class="error">'.$langs->trans('NorwayAuditReadError').'</div>';
}
print '<p>'.$langs->trans('NorwayAuditTraceScope').'</p>';
print '</div>';
llxFooter();
$db->close();
