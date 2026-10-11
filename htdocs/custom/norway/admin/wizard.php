<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require '../../../main.inc.php';
if (!$user->admin || !isModEnabled('norway')) { accessforbidden(); }
require_once __DIR__.'/../class/NorwayReadiness.php';
require_once __DIR__.'/../class/NorwayAccountingSetup.php';
require_once __DIR__.'/../class/NorwayBankSetup.php';
$langs->loadLangs(array('norway@norway', 'accountancy'));
header('Cache-Control: no-store');
$step = max(1, min(11, GETPOSTINT('step') ?: 1));
$base = dol_buildpath('/norway/admin/wizard.php', 1);
$links = array(
    1 => array('/custom/norway/admin/registry.php' => 'NorwayRegistry'),
    2 => array('/admin/company.php' => 'NorwayCompanySettings', '/user/list.php' => 'NorwayWizardUsers'),
    3 => array('/custom/norway/admin/registry.php' => 'NorwayRegistry', '/custom/norway/admin/setup.php' => 'NorwaySetup'),
    4 => array('/custom/norway/admin/profile.php' => 'NorwayProfile', '/accountancy/admin/defaultaccounts.php' => 'NorwayAccountingDefaultSettings', '/accountancy/admin/journals_list.php' => 'NorwayAccountingJournalSettings', '/custom/norway/admin/accounting.php' => 'NorwayAccountingSetup'),
    5 => array('/compta/bank/list.php' => 'NorwayBankSetupOpen'),
    6 => array('/societe/list.php' => 'NorwayWizardPartners', '/product/list.php' => 'NorwayWizardProducts'),
    7 => array('/admin/invoice.php' => 'NorwayWizardInvoiceSettings', '/admin/propal.php' => 'NorwayWizardProposalSettings', '/custom/norway/admin/invoice.php' => 'NorwayInvoiceCheck'),
    8 => array('/custom/norway/admin/pricing.php' => 'NorwayPricing'),
    9 => array('/accountancy/admin/fiscalyear.php' => 'NorwayWizardPeriods', '/custom/norway/admin/audit.php' => 'NorwayAudit'),
    10 => array('/custom/norway/admin/setup.php' => 'NorwaySetup'),
    11 => array('/custom/norway/admin/accounting.php' => 'NorwayAccountingSetup', '/custom/norway/admin/audit.php' => 'NorwayAudit'),
);
llxHeader('', $langs->trans('NorwayWizard'));
print '<div class="norway-page">';
print load_fiche_titre($langs->trans('NorwayWizard'), '', 'accountancy');
print '<div class="warning">'.$langs->trans('NorwayWizardScope').'</div>';
print '<details><summary>'.$langs->trans('NorwayWizardOverview').'</summary><ol>';
for ($i = 1; $i <= 11; $i++) {
    print '<li><a href="'.$base.'?step='.$i.'"'.($i === $step ? ' aria-current="step"' : '').'>'
        .$langs->trans('NorwayWizardTitle'.$i).'</a></li>';
}
print '</ol></details>';
print '<h2>'.dol_escape_htmltag($step.' / 11 — '.$langs->trans('NorwayWizardTitle'.$step)).'</h2>';
print '<p>'.$langs->trans('NorwayWizardBody'.$step).'</p>';
print '<div class="info">'.$langs->trans('NorwayWizardLimit'.$step).'</div>';
try {
    $checks = array();
    if ($step === 2) {
        $companyChecks = NorwayReadiness::evaluate(array('name' => $mysoc->name, 'idprof1' => $mysoc->idprof1,
            'country_code' => $mysoc->country_code, 'currency' => $conf->currency,
            'address' => $mysoc->address, 'zip' => $mysoc->zip, 'town' => $mysoc->town), array());
        foreach (array('Name', 'OrganisationNumber', 'Country', 'Currency', 'Address') as $key) {
            $checks[] = array($langs->trans('NorwayCheck'.$key), $langs->trans($companyChecks[$key] ? 'NorwayCheckOK' : 'NorwayCheckMissing'));
        }
    } elseif ($step === 3) {
        $checks[] = array($langs->trans('NorwayWizardCurrentVAT'), $langs->trans((int) $mysoc->tva_assuj === 1 ? 'NorwayVATEnabled' : 'NorwayVATDisabled'));
    } elseif ($step === 4) {
        $settings = array();
        foreach (NorwayAccountingSetup::defaults() as $key) { $settings[$key] = getDolGlobalString($key); }
        foreach ((new NorwayAccountingSetup($db, $conf->entity))->report(getDolGlobalInt('CHARTOFACCOUNTS'), $settings) as $check) {
            $label = strpos($check['key'], 'ACCOUNTING_') === 0 ? $check['key'] : 'NorwayAccounting'.$check['key'];
            $checks[] = array($langs->trans($label).' — '.$check['value'], $langs->trans('NorwayAccountingStatus'.$check['status']));
        }
    } elseif ($step === 5) {
        $banks = (new NorwayBankSetup($db, $conf->entity))->report(getDolGlobalInt('CHARTOFACCOUNTS'));
        if (!$banks) { print '<div class="warning">'.$langs->trans('NorwayBankSetupEmpty').'</div>'; }
        foreach ($banks as $bank) {
            $label = $bank['ref'].' — '.$bank['label'];
            foreach (array('account_status' => 'Ledger', 'journal_status' => 'Journal', 'currency_status' => 'Currency') as $key => $field) {
                $checks[] = array($label.' / '.$langs->trans('NorwayBankSetup'.$field), $langs->trans('NorwayAccountingStatus'.$bank[$key]));
            }
            $checks[] = array($label.' / '.$langs->trans('NorwayBankSetupShared'), $langs->trans('NorwayBankSetupShared'.$bank['shared_status']));
        }
    } elseif ($step === 7) {
        $checks[] = array($langs->trans('NorwayCheckCurrency'), $conf->currency === 'NOK' ? 'NOK' : $langs->trans('NorwayCheckMissing'));
        $checks[] = array($langs->trans('NorwayWizardDocumentLanguages'), $langs->trans(getDolGlobalInt('MAIN_MULTILANGS') === 1 ? 'NorwayCheckOK' : 'NorwayCheckMissing'));
    }
    if ($checks) {
        print '<h3>'.$langs->trans('NorwayWizardCurrent').'</h3>';
        print '<div class="div-table-responsive"><table class="noborder centpercent">';
        foreach ($checks as $check) {
            print '<tr><td>'.dol_escape_htmltag((string) $check[0]).'</td><td>'.dol_escape_htmltag((string) $check[1]).'</td></tr>';
        }
        print '</table></div>';
    }
} catch (RuntimeException $error) {
    print '<div class="error">'.$langs->trans('NorwayAccountingReadError').'</div>';
}
print '<h3>'.$langs->trans('NorwayWizardSettings').'</h3><ul>';
foreach ($links[$step] as $path => $label) {
    print '<li><a href="'.DOL_URL_ROOT.$path.'">'.$langs->trans($label).'</a></li>';
}
print '</ul><p>';
if ($step > 1) { print '<a class="butAction" href="'.$base.'?step='.($step - 1).'">'.$langs->trans('NorwayWizardPrevious').'</a> '; }
if ($step < 11) { print '<a class="butAction" href="'.$base.'?step='.($step + 1).'">'.$langs->trans('NorwayWizardNext').'</a>'; }
print '</p><p><a href="https://github.com/mfuryto/dolibarr-norway/blob/main/docs/norwegian-setup-guide.md">'
    .$langs->trans('NorwayWizardGuide').'</a></p>';
print '<p>'.$langs->trans('NorwayWizardNoSave').'</p></div>';
llxFooter();
$db->close();
