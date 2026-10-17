<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require '../../../main.inc.php';
if (!$user->admin || !empty($user->socid) || !isModEnabled('norway')) { accessforbidden(); }
require_once __DIR__.'/../class/NorwayReadiness.php';
require_once __DIR__.'/../class/NorwayVatRates.php';
require_once __DIR__.'/../class/NorwayChartGroups.php';
$langs->load('norway@norway');
header('Cache-Control: no-store');
$guide='https://github.com/mfuryto/dolibarr-norway/blob/main/docs/norwegian-setup-guide.md';
$e=static fn($text)=>dol_escape_htmltag((string)$text);
$company=($mysoc->country_code??'')==='NO' && NorwayReadiness::validOrganisationNumber($mysoc->idprof1??'')
    && trim((string)($mysoc->name??''))!=='' && trim((string)($mysoc->address??''))!==''
    && trim((string)($mysoc->zip??''))!=='' && trim((string)($mysoc->town??''))!=='' && !empty($mysoc->forme_juridique_code);
$chartStatus='Missing';$ratesStatus='Missing';
try {
    $r=$db->query('SELECT s.rowid FROM '.$db->prefix().'accounting_system s JOIN '.$db->prefix().'c_country c ON c.rowid=s.fk_country'
        .' WHERE s.rowid='.(int)getDolGlobalInt('CHARTOFACCOUNTS')." AND s.active=1 AND c.code='NO'");
    if(!$r){throw new RuntimeException('Setup read failed');}
    // This status concerns account groups, not statutory reporting or tax support.
    if($db->num_rows($r)>0){$chartStatus=NorwayChartGroups::ready($db,(int)$conf->entity)?'Set':'Partial';}
    $ratesStatus='Set';
    foreach(NorwayVatRates::codes() as $spec){if(NorwayVatRates::defaultCode($db,(int)$conf->entity,$spec['rate'],$spec['type'])===null){$ratesStatus='Missing';break;}}
} catch(Throwable $error){$chartStatus='Unknown';$ratesStatus='Unknown';}
$rows=array(
    'Company'=>array($company?'Set':'Missing',array('/admin/company.php'=>'NorwayCompanySettings','/user/list.php'=>'NorwaySetupLinkUsers')),
    'Currency'=>array($conf->currency==='NOK' && getDolGlobalInt('MAIN_MULTILANGS')===1?'Set':'Missing',array('/admin/company.php'=>'NorwayCompanySettings','/admin/ihm.php'=>'NorwaySetupLinkLanguage')),
    'Registration'=>array('Review',array('/admin/company.php'=>'NorwayCompanySettings')),
    'Chart'=>array($chartStatus,array('/custom/norway/admin/profile.php'=>'NorwaySetupLinkInstallChart','/accountancy/admin/account.php'=>'NorwaySetupLinkChart','/accountancy/admin/closure.php'=>'NorwaySetupLinkYearEnd')),
    'Accounts'=>array(getDolGlobalString('ACCOUNTING_ACCOUNT_CUSTOMER')!=='' && getDolGlobalString('ACCOUNTING_ACCOUNT_SUPPLIER')!=='' && getDolGlobalString('ACCOUNTING_ACCOUNT_CUSTOMER')!=='-1' && getDolGlobalString('ACCOUNTING_ACCOUNT_SUPPLIER')!=='-1'?'Set':'Missing',array('/accountancy/admin/defaultaccounts.php'=>'NorwaySetupLinkAccounts','/accountancy/admin/journals_list.php'=>'NorwayJournals')),
    'Rates'=>array($ratesStatus,array('/custom/norway/admin/vatrates.php'=>'NorwaySetupLinkNorwegianRates','/admin/dict.php?id=10'=>'NorwaySetupLinkVatAccounts')),
    'Bank'=>array('Review',array('/compta/bank/list.php'=>'NorwayWizardTaskBank')),
    'Trade'=>array('Review',array('/societe/list.php'=>'NorwayWizardTaskPartners','/product/list.php'=>'NorwayWizardTaskProducts')),
    'Numbering'=>array(getDolGlobalString('FACTURE_ADDON')!=='' && getDolGlobalString('PROPALE_ADDON')!=='' && getDolGlobalString('SOCIETE_CODECLIENT_ADDON')!==''?'Set':'Missing',array('/custom/norway/admin/numbering.php'=>'NorwayNumberingTitle')),
    'Documents'=>array('Review',array('/admin/invoice.php'=>'NorwayWizardTaskInvoice','/admin/propal.php'=>'NorwayWizardTaskProposal')),
    'Periods'=>array('Review',array('/accountancy/admin/fiscalyear.php'=>'NorwayWizardTaskPeriods','/accountancy/bookkeeping/list.php'=>'NorwaySetupLinkBookkeeping')),
    'Foreign'=>array('Partial',array($guide.'#8-offentlige-kunder-og-utenlandshandel'=>'NorwayWizardGuide','/fourn/facture/list.php'=>'NorwaySetupLinkPurchases')),
    'VatReport'=>array('Partial',array('/custom/norway/vat.php'=>'NorwayVatReportTitle')),
    'Ehf'=>array('Unavailable',array($guide.'#8-offentlige-kunder-og-utenlandshandel'=>'NorwayWizardGuide')),
    'Saft'=>array('Unavailable',array($guide.'#10-rapportering-oppbevaring-og-sikker-drift'=>'NorwayWizardGuide')),
    'YearEnd'=>array('Partial',array('/accountancy/admin/closure.php'=>'NorwaySetupLinkYearEnd',$guide.'#10-rapportering-oppbevaring-og-sikker-drift'=>'NorwayWizardGuide')),
    'Tax'=>array('Unavailable',array($guide.'#10-rapportering-oppbevaring-og-sikker-drift'=>'NorwayWizardGuide')),
    'Retention'=>array('Review',array($guide.'#10-rapportering-oppbevaring-og-sikker-drift'=>'NorwayWizardGuide')),
);
llxHeader('',$langs->trans('NorwayWizard'));
print '<div class="norway-page norway-wizard">'.load_fiche_titre($langs->trans('NorwayWizard'),'','accountancy');
print '<p>'.$e($langs->trans('NorwaySetupOverviewIntro')).'</p><p class="opacitymedium">'.$e($langs->trans('NorwaySetupOverviewStatusHelp')).'</p>';
print '<table class="noborder centpercent norway-setup-overview"><thead><tr>';
foreach(array('Task','Status','Action') as $column){print '<th scope="col">'.$e($langs->trans('NorwaySetupColumn'.$column)).'</th>';}
print '</tr></thead><tbody>';
foreach($rows as $key=>[$status,$links]){
    print '<tr class="oddeven" data-setup-task="'.$key.'"><th scope="row">'.$e($langs->trans('NorwaySetupTask'.$key)).'</th>'
        .'<td data-setup-status="'.$status.'"><strong>'.$e($langs->trans('NorwaySetupStatus'.$status)).'</strong>';
    if($key==='Registration'){print '<br>'.$e($langs->trans(!empty($mysoc->tva_assuj)?'NorwaySetupVatOn':'NorwaySetupVatOff'));}
    print '</td><td><p>'.$e($langs->trans('NorwaySetupHelp'.$key)).'</p><p class="norway-setup-links">';
    foreach($links as $path=>$label){$url=strpos($path,'https://')===0?$path:DOL_URL_ROOT.$path;print '<a href="'.$e($url).'">'.$e($langs->trans($label)).'</a> ';}
    print '</p></td></tr>';
}
print '</tbody></table><p>'.$e($langs->trans('NorwaySetupScopeHelp')).'</p>';
print '<p><a href="'.$guide.'">'.$e($langs->trans('NorwayWizardGuide')).'</a></p></div>';
llxFooter();$db->close();
