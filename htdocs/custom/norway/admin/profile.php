<?php
// SPDX-License-Identifier: GPL-3.0-or-later
define('CSRFCHECK_WITH_TOKEN', 1);
require '../../../main.inc.php';
if (GETPOST('errorcode', 'aZ09') === 'InvalidToken') { httponly_accessforbidden('Invalid security token'); }
if (!$user->admin || !isModEnabled('norway')) { accessforbidden(); }
require_once __DIR__.'/../class/NorwayChartProfile.php';
$langs->load('norway@norway');
header('Cache-Control: no-store');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && GETPOST('action', 'aZ09') === 'installprofile') {
    $pending = $_SESSION['norway_profile'] ?? null;
    unset($_SESSION['norway_profile']);
    try {
        if (!$pending || $pending['expires'] < time() || $pending['entity'] !== (int) $conf->entity
            || $pending['user'] !== (int) $user->id || !hash_equals($pending['nonce'], GETPOST('preview_nonce', 'aZ09'))) {
            throw new InvalidArgumentException('Profile');
        }
        $created = NorwayChartProfile::install($db, $conf->entity, $pending['fingerprint'], $user);
        setEventMessages($langs->trans('NorwayProfileInstalled', $created), null, 'mesgs');
    } catch (Throwable $exception) { setEventMessages($langs->trans('NorwayProfileBlocked'), null, 'errors'); }
    header('Location: '.dol_buildpath('/norway/admin/profile.php', 1)); exit;
}
llxHeader('', $langs->trans('NorwayProfile'));
print '<div class="norway-page">'.load_fiche_titre($langs->trans('NorwayProfile'), '', 'accountancy');
print '<div class="warning">'.$langs->trans('NorwayProfileScope').'</div>';
try {
    $preview = NorwayChartProfile::preview($db, $conf->entity);
    print '<p>'.dol_escape_htmltag(NorwayChartProfile::VERSION).'</p>';
    print '<div class="div-table-responsive"><table class="noborder centpercent">';
    foreach ($preview['report']['rows'] as $row) {
        print '<tr><td>'.dol_escape_htmltag($row['number']).'</td><td>'.dol_escape_htmltag($row['label']).'</td><td>'.$langs->trans('NorwayChartStatus'.$row['status']).'</td></tr>';
    }
    print '</table></div>';
    $nonce = bin2hex(random_bytes(16));
    $_SESSION['norway_profile'] = array('entity' => (int) $conf->entity, 'user' => (int) $user->id,
        'nonce' => $nonce, 'expires' => time()+600, 'fingerprint' => NorwayChartProfile::fingerprint($conf->entity, $preview));
    print '<p>'.$langs->trans('NorwayChartConfirmScope').'</p>';
    print '<form method="post"><input type="hidden" name="token" value="'.newToken().'">';
    print '<input type="hidden" name="action" value="installprofile"><input type="hidden" name="preview_nonce" value="'.$nonce.'">';
    print '<button class="button" type="submit">'.$langs->trans('NorwayProfileConfirm').'</button></form>';
} catch (Throwable $exception) {
    unset($_SESSION['norway_profile']);
    print '<div class="warning">'.$langs->trans('NorwayProfileBlocked').'</div>';
}
print '<p><a href="'.dol_buildpath('/norway/admin/accounting.php', 1).'">'.$langs->trans('NorwayAccountingSetup').'</a></p></div>';
llxFooter(); $db->close();
