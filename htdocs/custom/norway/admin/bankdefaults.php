<?php
// SPDX-License-Identifier: GPL-3.0-or-later
define('CSRFCHECK_WITH_TOKEN', 1);
require '../../../main.inc.php';
if (GETPOST('errorcode', 'aZ09') === 'InvalidToken') { httponly_accessforbidden('Invalid security token'); }
if (!$user->admin || !isModEnabled('norway')) { accessforbidden(); }
require_once __DIR__.'/../class/NorwayBankDefaults.php';
$langs->loadLangs(array('norway@norway', 'accountancy'));
header('Cache-Control: no-store');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && GETPOST('action', 'aZ09') === 'savebankdefaults') {
    $pending = $_SESSION['norway_bankdefaults'] ?? null;
    unset($_SESSION['norway_bankdefaults']);
    try {
        if (!$pending || $pending['expires'] < time() || $pending['entity'] !== (int) $conf->entity
            || $pending['user'] !== (int) $user->id || !hash_equals($pending['nonce'], GETPOST('preview_nonce', 'aZ09'))) {
            throw new InvalidArgumentException('Confirmation');
        }
        $saved = NorwayBankDefaults::apply($db, $conf->entity, $pending['fingerprint'], $user);
        setEventMessages($langs->trans('NorwayBankDefaultsSaved', $saved), null, 'mesgs');
    } catch (Throwable $error) { setEventMessages($langs->trans('NorwayBankDefaultsFailed'), null, 'errors'); }
    header('Location: '.dol_buildpath('/norway/admin/bankdefaults.php', 1)); exit;
}
llxHeader('', $langs->trans('NorwayBankDefaults'));
print '<div class="norway-page">'.load_fiche_titre($langs->trans('NorwayBankDefaults'), '', 'accountancy');
print '<div class="warning">'.$langs->trans('NorwayBankDefaultsScope').'</div>';
try {
    $preview = NorwayBankDefaults::preview($db, $conf->entity);
    $blocked = false; $fills = 0;
    print '<p>'.$langs->trans('NorwayBankDefaultsReason'.$preview['reason']).'</p>';
    if ($preview['bank']) { print '<p>'.dol_escape_htmltag($preview['bank']['ref'].' — '.$preview['bank']['label']).'</p>'; }
    print '<div class="div-table-responsive"><table class="noborder centpercent"><tr class="liste_titre">';
    foreach (array('Item', 'Value', 'Suggested', 'Status') as $key) { print '<th>'.$langs->trans('NorwayAccounting'.$key).'</th>'; }
    print '</tr>';
    foreach ($preview['rows'] as $row) {
        $blocked = $blocked || $row['action'] === 'Blocked'; $fills += $row['action'] === 'Fill' ? 1 : 0;
        print '<tr><td>'.$langs->trans($row['key']==='account_number' ? 'NorwayBankSetupLedger' : 'NorwayBankSetupJournal').'</td><td>'.dol_escape_htmltag($row['current']);

        print '</td><td>'.dol_escape_htmltag($row['label']).'</td><td>'.$langs->trans('NorwayBankDefaults'.$row['action']).'</td></tr>';
    }
    print '</table></div>';
    if (!$blocked && $fills > 0) {
        $nonce = bin2hex(random_bytes(16));
        $_SESSION['norway_bankdefaults'] = array('entity' => (int) $conf->entity, 'user' => (int) $user->id,
            'nonce' => $nonce, 'expires' => time()+600, 'fingerprint' => NorwayBankDefaults::fingerprint($preview));
        print '<p>'.$langs->trans('NorwayBankDefaultsConfirmScope').'</p>';
        print '<form method="post"><input type="hidden" name="token" value="'.newToken().'">';
        print '<input type="hidden" name="action" value="savebankdefaults"><input type="hidden" name="preview_nonce" value="'.$nonce.'">';
        print '<button class="button" type="submit">'.$langs->trans('NorwayBankDefaultsConfirm').'</button></form>';
    } else {
        unset($_SESSION['norway_bankdefaults']);
        print '<p>'.$langs->trans($blocked ? 'NorwayBankDefaultsBlocked' : 'NorwayBankDefaultsNothing').'</p>';
    }
} catch (Throwable $error) {
    unset($_SESSION['norway_bankdefaults']);
    print '<div class="error">'.$langs->trans('NorwayBankDefaultsFailed').'</div>';
}
foreach (array('/compta/bank/list.php' => 'NorwayBankSetupOpen',
    '/custom/norway/admin/wizard.php?step=5' => 'NorwayWizard') as $path => $label) {
    print '<p><a href="'.DOL_URL_ROOT.$path.'">'.$langs->trans($label).'</a></p>';
}
print '</div>'; llxFooter(); $db->close();
