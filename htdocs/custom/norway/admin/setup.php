<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require '../../../main.inc.php';

if (!$user->admin || !isModEnabled('norway')) {
    accessforbidden();
}
require_once __DIR__.'/../class/NorwayReadiness.php';
$langs->load('norway@norway');
llxHeader('', $langs->trans('NorwaySetup'));
print '<div class="norway-page">';
print load_fiche_titre($langs->trans('NorwaySetup'), '', 'accountancy');
print '<div class="warning">'.$langs->trans('NorwayPrototypeWarning').'</div>';
print '<p>'.$langs->trans('NorwayVersionScope').'</p>';
print '<p>'.$langs->trans('NorwayNextSteps').'</p>';
print '<p>'.$langs->trans((int) $mysoc->tva_assuj === 1 ? 'NorwayVATEnabled' : 'NorwayVATDisabled').'</p>';
$rates = array();
$sql = "SELECT t.taux,t.type_vat,t.active FROM ".$db->prefix()."c_tva t"
    ." INNER JOIN ".$db->prefix()."c_country c ON c.rowid=t.fk_pays"
    ." WHERE c.code='NO' AND t.entity=".(int) $conf->entity;
$result = $db->query($sql);
if (!$result) {
    print '<div class="error">'.$langs->trans('NorwayReadError').'</div>';
} else {
    while ($row = $db->fetch_array($result)) {
        $rates[] = $row;
    }
    $checks = NorwayReadiness::evaluate(array(
        'name' => $mysoc->name, 'idprof1' => $mysoc->idprof1,
        'country_code' => $mysoc->country_code,
        'currency' => $conf->currency,
        'address' => $mysoc->address, 'zip' => $mysoc->zip, 'town' => $mysoc->town,
    ), $rates);
    print '<h2>'.$langs->trans('NorwayChecks').'</h2>';
    print '<p>'.$langs->trans('NorwayChecksScope').'</p>';
    print '<div class="div-table-responsive"><table class="noborder centpercent">';
    foreach ($checks as $key => $passed) {
        print '<tr><td>'.$langs->trans('NorwayCheck'.$key).'</td><td>'
            .$langs->trans($passed ? 'NorwayCheckOK' : 'NorwayCheckMissing').'</td></tr>';
    }
    foreach (array('Registration', 'Chart', 'VATReport', 'SAFT', 'EHF', 'FX') as $key) {
        print '<tr><td>'.$langs->trans('NorwayCheck'.$key).'</td><td>'
            .$langs->trans('NorwayCheckUnverified').'</td></tr>';
    }
    print '</table></div>';
    print '<p><a href="'.DOL_URL_ROOT.'/admin/company.php">'.$langs->trans('NorwayCompanySettings').'</a></p>';
}
print '<p><a href="'.dol_buildpath('/norway/admin/audit.php', 1).'">'.$langs->trans('NorwayAudit').'</a></p>';
print '<p><a href="'.dol_buildpath('/norway/admin/pricing.php', 1).'">'.$langs->trans('NorwayPricing').'</a></p>';
print '<p><a href="'.dol_buildpath('/norway/admin/invoice.php', 1).'">'.$langs->trans('NorwayInvoiceCheck').'</a></p>';
print '<p><a href="'.dol_buildpath('/norway/admin/registry.php', 1).'">'.$langs->trans('NorwayRegistry').'</a></p>';
print '</div>';
llxFooter();
$db->close();
