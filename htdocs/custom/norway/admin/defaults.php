<?php
// SPDX-License-Identifier: GPL-3.0-or-later
define('CSRFCHECK_WITH_TOKEN', 1);
require '../../../main.inc.php';
if (GETPOST('errorcode', 'aZ09') === 'InvalidToken') { httponly_accessforbidden('Invalid security token'); }
if (!$user->admin || !isModEnabled('norway')) { accessforbidden(); }
require_once __DIR__.'/../class/NorwayDefaultAccounts.php';
$langs->loadLangs(array('norway@norway', 'accountancy'));
header('Cache-Control: no-store');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && GETPOST('action', 'aZ09') === 'savedefaults') {
    $pending = $_SESSION['norway_defaults'] ?? null;
    unset($_SESSION['norway_defaults']);
    try {
        if (!$pending || $pending['expires'] < time() || $pending['entity'] !== (int) $conf->entity
            || $pending['user'] !== (int) $user->id || !hash_equals($pending['nonce'], GETPOST('preview_nonce', 'aZ09'))) {
            throw new InvalidArgumentException('Confirmation');
        }
        $saved = NorwayDefaultAccounts::apply($db, $conf->entity, $pending['fingerprint'], $user);
        setEventMessages($langs->trans('NorwayDefaultsSaved', $saved), null, 'mesgs');
    } catch (Throwable $error) { setEventMessages($langs->trans('NorwayDefaultsFailed'), null, 'errors'); }
    header('Location: '.dol_buildpath('/norway/admin/defaults.php', 1)); exit;
}
llxHeader('', $langs->trans('NorwayDefaults'));
print '<div class="norway-page">'.load_fiche_titre($langs->trans('NorwayDefaults'), '', 'accountancy');
print '<div class="warning">'.$langs->trans('NorwayDefaultsScope').'</div>';
try {
    $preview = NorwayDefaultAccounts::preview($db, $conf->entity);
    $blocked = false; $fills = 0;
    print '<div class="div-table-responsive"><table class="noborder centpercent"><tr class="liste_titre">';
    foreach (array('Item', 'Value', 'Suggested', 'Status') as $key) { print '<th>'.$langs->trans('NorwayAccounting'.$key).'</th>'; }
    print '</tr>';
    foreach ($preview['rows'] as $row) {
        $blocked = $blocked || $row['action'] === 'Blocked'; $fills += $row['action'] === 'Fill' ? 1 : 0;
        print '<tr><td>'.$langs->trans($row['key']).'</td><td>'.dol_escape_htmltag($row['current']);
        if ($row['runtime'] !== $row['current']) { print '<br>'.$langs->trans('NorwayDefaultsEffective').': '.dol_escape_htmltag($row['runtime']); }
        print '</td><td>'.dol_escape_htmltag($row['number'].' — '.$row['label']).'</td><td>'.$langs->trans('NorwayDefaults'.$row['action']).'</td></tr>';
    }
    print '</table></div>';
    if (!$blocked && $fills > 0) {
        $nonce = bin2hex(random_bytes(16));
        $_SESSION['norway_defaults'] = array('entity' => (int) $conf->entity, 'user' => (int) $user->id,
            'nonce' => $nonce, 'expires' => time()+600, 'fingerprint' => NorwayDefaultAccounts::fingerprint($preview));
        print '<p>'.$langs->trans('NorwayDefaultsConfirmScope').'</p>';
        print '<form method="post"><input type="hidden" name="token" value="'.newToken().'">';
        print '<input type="hidden" name="action" value="savedefaults"><input type="hidden" name="preview_nonce" value="'.$nonce.'">';
        print '<button class="button" type="submit">'.$langs->trans('NorwayDefaultsConfirm').'</button></form>';
    } else {
        unset($_SESSION['norway_defaults']);
        print '<p>'.$langs->trans($blocked ? 'NorwayDefaultsBlocked' : 'NorwayDefaultsNothing').'</p>';
    }
} catch (Throwable $error) {
    unset($_SESSION['norway_defaults']);
    print '<div class="error">'.$langs->trans('NorwayDefaultsFailed').'</div>';
}
foreach (array('/accountancy/admin/defaultaccounts.php' => 'NorwayAccountingDefaultSettings',
    '/custom/norway/admin/wizard.php?step=4' => 'NorwayWizard') as $path => $label) {
    print '<p><a href="'.DOL_URL_ROOT.$path.'">'.$langs->trans($label).'</a></p>';
}
print '</div>'; llxFooter(); $db->close();
