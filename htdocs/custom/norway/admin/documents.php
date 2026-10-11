<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require '../../../main.inc.php';
if (!$user->admin || !isModEnabled('norway')) { accessforbidden(); }
require_once __DIR__.'/../class/NorwayDocumentSetup.php';
$langs->load('norway@norway'); header('Cache-Control: no-store');
llxHeader('', $langs->trans('NorwayDocuments'));
print '<div class="norway-page">'.load_fiche_titre($langs->trans('NorwayDocuments'),'','bill');
print '<div class="warning">'.$langs->trans('NorwayDocumentsScope').'</div>';
try {
    $settings=array(); foreach (NorwayDocumentSetup::settings() as $key) { $settings[$key]=getDolGlobalString($key); }
    $rows=NorwayDocumentSetup::report($db,$conf->entity,$settings,$conf->currency);
    print '<div class="div-table-responsive"><table class="noborder centpercent"><tr class="liste_titre">';
    foreach (array('Item','Value','Status') as $key) { print '<th>'.$langs->trans('NorwayAccounting'.$key).'</th>'; }
    print '</tr>';
    foreach ($rows as $row) {
        print '<tr><td>'.$langs->trans('NorwayDocuments'.$row['key']).'</td><td>'.dol_escape_htmltag($row['value']).'</td><td>'.$langs->trans('NorwayDocuments'.$row['status']).'</td></tr>';
    }
    print '</table></div>';
} catch (RuntimeException $error) { print '<div class="error">'.$langs->trans('NorwayDocumentsReadError').'</div>'; }
print '<p>'.$langs->trans('NorwayDocumentsNext').'</p>';
foreach (array('/admin/invoice.php'=>'NorwayWizardInvoiceSettings','/admin/propal.php'=>'NorwayWizardProposalSettings','/custom/norway/admin/wizard.php?step=7'=>'NorwayWizard') as $path=>$label) {
    print '<p><a href="'.DOL_URL_ROOT.$path.'">'.$langs->trans($label).'</a></p>';
}
print '</div>'; llxFooter(); $db->close();
