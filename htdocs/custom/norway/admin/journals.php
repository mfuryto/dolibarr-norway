<?php
// SPDX-License-Identifier: GPL-3.0-or-later
define('CSRFCHECK_WITH_TOKEN', 1);
require '../../../main.inc.php';
if (GETPOST('errorcode', 'aZ09') === 'InvalidToken') { httponly_accessforbidden('Invalid security token'); }
if (!$user->admin || !isModEnabled('norway')) { accessforbidden(); }
require_once __DIR__.'/../class/NorwayJournalSetup.php';
$langs->loadLangs(array('norway@norway', 'accountancy'));
header('Cache-Control: no-store');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && GETPOST('action', 'aZ09') === 'createjournals') {
    $pending = $_SESSION['norway_journals'] ?? null;
    unset($_SESSION['norway_journals']);
    try {
        if (!$pending || $pending['expires'] < time() || $pending['entity'] !== (int) $conf->entity
            || $pending['user'] !== (int) $user->id || !hash_equals($pending['nonce'], GETPOST('preview_nonce', 'aZ09'))) {
            throw new InvalidArgumentException('Confirmation');
        }
        $saved = NorwayJournalSetup::apply($db, $conf->entity, $pending['fingerprint'], $user);
        setEventMessages($langs->trans('NorwayJournalsSaved', $saved), null, 'mesgs');
    } catch (Throwable $error) { setEventMessages($langs->trans('NorwayJournalsFailed'), null, 'errors'); }
    header('Location: '.dol_buildpath('/norway/admin/journals.php', 1)); exit;
}
llxHeader('', $langs->trans('NorwayJournals'));
print '<div class="norway-page">'.load_fiche_titre($langs->trans('NorwayJournals'), '', 'accountancy');
print '<div class="warning">'.$langs->trans('NorwayJournalsScope').'</div>';
try {
    $preview = NorwayJournalSetup::preview($db, $conf->entity);
    $blocked = false; $fills = 0;
    print '<div class="div-table-responsive"><table class="noborder centpercent"><tr class="liste_titre">';
    foreach (array('Item', 'Value', 'Suggested', 'Status') as $key) { print '<th>'.$langs->trans('NorwayAccounting'.$key).'</th>'; }
    print '</tr>';
    foreach ($preview['rows'] as $row) {
        $blocked = $blocked || $row['action'] === 'Blocked'; $fills += $row['action'] === 'Create' ? 1 : 0;
        print '<tr><td>'.$langs->trans('AccountingJournalType'.$row['nature']).'</td><td>'
            .dol_escape_htmltag(implode('; ', $row['existing'])).'</td><td>'
            .dol_escape_htmltag($row['code'].' — '.$row['label']).'</td><td>'
            .$langs->trans('NorwayJournals'.$row['action']).'</td></tr>';
    }
    print '</table></div>';
    if (!$blocked && $fills > 0) {
        $nonce = bin2hex(random_bytes(16));
        $_SESSION['norway_journals'] = array('entity' => (int) $conf->entity, 'user' => (int) $user->id,
            'nonce' => $nonce, 'expires' => time()+600, 'fingerprint' => NorwayJournalSetup::fingerprint($preview));
        print '<p>'.$langs->trans('NorwayJournalsConfirmScope').'</p>';
        print '<form method="post"><input type="hidden" name="token" value="'.newToken().'">';
        print '<input type="hidden" name="action" value="createjournals"><input type="hidden" name="preview_nonce" value="'.$nonce.'">';
        print '<button class="button" type="submit">'.$langs->trans('NorwayJournalsConfirm').'</button></form>';
    } else {
        unset($_SESSION['norway_journals']);
        print '<p>'.$langs->trans($blocked ? 'NorwayJournalsBlocked' : 'NorwayJournalsNothing').'</p>';
    }
} catch (Throwable $error) {
    unset($_SESSION['norway_journals']);
    print '<div class="error">'.$langs->trans('NorwayJournalsFailed').'</div>';
}
foreach (array('/accountancy/admin/journals_list.php' => 'NorwayAccountingJournalSettings',
    '/custom/norway/admin/wizard.php?step=4' => 'NorwayWizard') as $path => $label) {
    print '<p><a href="'.DOL_URL_ROOT.$path.'">'.$langs->trans($label).'</a></p>';
}
print '</div>'; llxFooter(); $db->close();
