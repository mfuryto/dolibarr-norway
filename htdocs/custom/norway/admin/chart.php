<?php
// SPDX-License-Identifier: GPL-3.0-or-later
define('CSRFCHECK_WITH_TOKEN', 1);
require '../../../main.inc.php';
if (GETPOST('errorcode', 'aZ09') === 'InvalidToken') { httponly_accessforbidden('Invalid security token'); }
if (!$user->admin || !isModEnabled('norway')) { accessforbidden(); }
require_once __DIR__.'/../class/NorwayChartImport.php';
$langs->load('norway@norway');
header('Cache-Control: no-store');
if (isset($_SESSION['norway_chart_preview']) && $_SESSION['norway_chart_preview']['expires'] < time()) {
    unset($_SESSION['norway_chart_preview']);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && GETPOST('action', 'aZ09') === 'importchart') {
    $pending = $_SESSION['norway_chart_preview'] ?? null;
    unset($_SESSION['norway_chart_preview']);
    try {
        if (!is_array($pending) || $pending['expires'] < time() || (int) $pending['entity'] !== (int) $conf->entity
            || (int) $pending['user'] !== (int) $user->id
            || (int) $pending['chart'] !== getDolGlobalInt('CHARTOFACCOUNTS')
            || !hash_equals($pending['nonce'], GETPOST('preview_nonce', 'aZ09'))) {
            throw new InvalidArgumentException('Confirmation');
        }
        $created = NorwayChartImport::apply($db, $conf->entity, $pending['chart'], $pending['csv'], $pending['fingerprint'], $user);
        setEventMessages($langs->trans('NorwayChartImported', $created), null, 'mesgs');
    } catch (InvalidArgumentException $error) {
        setEventMessages($langs->trans('NorwayChartChanged'), null, 'errors');
    } catch (Throwable $error) {
        setEventMessages($langs->trans('NorwayChartImportFailed'), null, 'errors');
    }
    header('Location: '.dol_buildpath('/norway/admin/chart.php', 1));
    exit;
}
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
    unset($_SESSION['norway_chart_preview']);
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
        if (NorwayChartImport::eligible($report)) {
            $nonce = bin2hex(random_bytes(16));
            $_SESSION['norway_chart_preview'] = array('entity' => (int) $conf->entity, 'user' => (int) $user->id,
                'chart' => getDolGlobalInt('CHARTOFACCOUNTS'), 'csv' => $_POST['chart_csv'],
                'fingerprint' => NorwayChartImport::fingerprint($conf->entity, getDolGlobalInt('CHARTOFACCOUNTS'), $report),
                'nonce' => $nonce, 'expires' => time() + 600);
            print '<p>'.$langs->trans('NorwayChartConfirmScope').'</p>';
            print '<form method="post" action="'.dol_buildpath('/norway/admin/chart.php', 1).'">';
            print '<input type="hidden" name="token" value="'.newToken().'">';
            print '<input type="hidden" name="action" value="importchart">';
            print '<input type="hidden" name="preview_nonce" value="'.$nonce.'">';
            print '<button class="button" type="submit">'.$langs->trans('NorwayChartConfirm').'</button></form>';
        } else {
            print '<div class="warning">'.$langs->trans('NorwayChartBlocked').'</div>';
        }
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
