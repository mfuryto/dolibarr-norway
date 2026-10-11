<?php
// SPDX-License-Identifier: GPL-3.0-or-later
define('CSRFCHECK_WITH_TOKEN', 1);
require '../../../main.inc.php';
if (GETPOST('errorcode', 'aZ09') === 'InvalidToken') { httponly_accessforbidden('Invalid security token'); }
if (!$user->admin || !isModEnabled('norway')) { accessforbidden(); }
require_once __DIR__.'/../class/NorwayRegistry.php';
$langs->load('norway@norway');
header('Cache-Control: no-store');
llxHeader('', $langs->trans('NorwayRegistry'));
print '<div class="norway-page">';
print load_fiche_titre($langs->trans('NorwayRegistry'), '', 'company');
print '<p>'.$langs->trans('NorwayRegistryScope').'</p>';
print '<p>'.$langs->trans((int) $mysoc->tva_assuj === 1 ? 'NorwayVATEnabled' : 'NorwayVATDisabled').'</p>';
print '<form method="post" action="'.dol_buildpath('/norway/admin/registry.php', 1).'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="lookupregistry">';
print '<button class="button" type="submit">'.$langs->trans('NorwayRegistryLookup').'</button></form>';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && GETPOST('action', 'aZ09') === 'lookupregistry') {
    try {
        $result = NorwayRegistry::fetch($mysoc->idprof1, $mysoc->country_code);
        print '<div class="div-table-responsive"><table class="noborder centpercent">';
        foreach (array('number', 'name', 'time') as $field) {
            print '<tr><td>'.$langs->trans('NorwayRegistry'.ucfirst($field)).'</td><td>'.dol_escape_htmltag($result[$field]).'</td></tr>';
        }
        print '<tr><td>'.$langs->trans('NorwayRegistryVAT').'</td><td>'.$langs->trans($result['vat'] ? 'Yes' : 'No').'</td></tr></table></div>';
        $match = $result['vat'] === ((int) $mysoc->tva_assuj === 1);
        print '<div class="'.($match ? 'info' : 'warning').'">'.$langs->trans($match ? 'NorwayRegistryMatch' : 'NorwayRegistryMismatch').'</div>';
    } catch (RuntimeException $error) {
        $key = in_array($error->getMessage(), array('Invalid', 'NotFound', 'Removed', 'Deleted'), true) ? $error->getMessage() : 'Unavailable';
        print '<div class="warning">'.$langs->trans('NorwayRegistry'.$key).'</div>';
    }
}
print '<p><a href="'.DOL_URL_ROOT.'/admin/company.php">'.$langs->trans('NorwayCompanySettings').'</a></p>';
print '<p><a href="'.dol_buildpath('/norway/admin/setup.php', 1).'">'.$langs->trans('NorwaySetup').'</a></p>';
print '</div>';
llxFooter();
$db->close();
