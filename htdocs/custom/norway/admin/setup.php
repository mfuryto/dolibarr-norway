<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require '../../../main.inc.php';

if (!$user->admin || !isModEnabled('norway')) {
    accessforbidden();
}
$langs->load('norway@norway');
llxHeader('', $langs->trans('NorwaySetup'));
print load_fiche_titre($langs->trans('NorwaySetup'), '', 'accountancy');
print '<div class="warning">'.$langs->trans('NorwayPrototypeWarning').'</div>';
print '<p>'.$langs->trans('NorwayVersionScope').'</p>';
print '<p>'.$langs->trans('NorwayNextSteps').'</p>';
llxFooter();
$db->close();
