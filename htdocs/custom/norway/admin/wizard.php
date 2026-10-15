<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require '../../../main.inc.php';
if (!$user->admin || !empty($user->socid) || !isModEnabled('norway')) { accessforbidden(); }
$langs->load('norway@norway');$step=GETPOSTINT('step');if ($step<1 || $step>11) { $step=1; }
$tasks=array(
    1=>array(),
    2=>array('Company'=>'/admin/company.php','Users'=>'/user/list.php'),
    3=>array('VATCompany'=>'/admin/company.php'),
    4=>array('Chart'=>'/custom/norway/admin/profile.php','Defaults'=>'/custom/norway/admin/defaults.php','OtherAccounts'=>'/custom/norway/admin/standardaccounts.php','VatRates'=>'/custom/norway/admin/vatrates.php','VatAccounts'=>'/custom/norway/admin/vataccounts.php','Journals'=>'/custom/norway/admin/journals.php'),
    5=>array('Bank'=>'/compta/bank/list.php','BankLink'=>'/custom/norway/admin/bankdefaults.php'),
    6=>array('Partners'=>'/societe/list.php','Products'=>'/product/list.php'),
    7=>array('Numbering'=>'/custom/norway/admin/numbering.php','Invoice'=>'/admin/invoice.php','Proposal'=>'/admin/propal.php'),
    8=>array(),9=>array('Periods'=>'/accountancy/admin/fiscalyear.php'),10=>array(),11=>array(),
);
$base=dol_buildpath('/norway/admin/wizard.php',1);llxHeader('',$langs->trans('NorwayWizard'));
print '<div class="norway-page norway-wizard">'.load_fiche_titre($langs->trans('NorwayWizard'),'','accountancy');
print '<p>'.$langs->trans('NorwaySimpleWizard').'</p><details><summary>'.$langs->trans('NorwayWizardOverview').'</summary><ol>';
for ($i=1;$i<=11;$i++) { print '<li><a href="'.$base.'?step='.$i.'">'.$langs->trans('NorwayWizardTitle'.$i).'</a></li>'; }print '</ol></details>';
print '<h2>'.$step.' / 11 — '.$langs->trans('NorwayWizardTitle'.$step).'</h2><p>'.$langs->trans('NorwayWizardBody'.$step).'</p><ol class="norway-wizard-tasks">';
foreach ($tasks[$step] as $key=>$path) {
    print '<li><h3>'.$langs->trans('NorwayWizardTask'.$key).'</h3><p>'.$langs->trans('NorwayWizardTask'.$key.'Help').'</p><a href="'.DOL_URL_ROOT.$path.'" target="_blank" rel="noopener noreferrer">'.$langs->trans('NorwayWizardOpen').' '.$langs->trans('NorwayWizardTask'.$key).'</a></li>';
}print '</ol><p>'.$langs->trans('NorwayWizardBefore'.$step).'</p>';
if ($step===2) { print '<p>'.$langs->trans('NorwayCompanyFieldsHelp').'</p><p>'.$langs->trans('NorwayVatAutoHelp').'</p>'; }
if ($step===3) { print '<p>'.$langs->trans('NorwaySimpleRegistration').'</p>'; }
print '<nav class="norway-wizard-nav">';if ($step>1) { print '<a class="butAction" href="'.$base.'?step='.($step-1).'">'.$langs->trans('NorwayWizardPrevious').'</a> '; }if ($step<11) { print '<a class="butAction" href="'.$base.'?step='.($step+1).'">'.$langs->trans('NorwayWizardNext').'</a>'; }print '</nav>';
print '<p><a href="https://github.com/mfuryto/dolibarr-norway/blob/main/docs/norwegian-setup-guide.md">'.$langs->trans('NorwayWizardGuide').'</a></p></div>';
llxFooter();$db->close();
