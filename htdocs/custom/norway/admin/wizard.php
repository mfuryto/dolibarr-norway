<?php
// SPDX-License-Identifier: GPL-3.0-or-later
define('CSRFCHECK_WITH_TOKEN', 1);
require '../../../main.inc.php';
if (GETPOST('errorcode', 'aZ09') === 'InvalidToken') { httponly_accessforbidden('Invalid security token'); }
if (!$user->admin || !isModEnabled('norway')) { accessforbidden(); }
require_once __DIR__.'/../class/NorwayReadiness.php';
require_once __DIR__.'/../class/NorwayAccountingSetup.php';
require_once __DIR__.'/../class/NorwayBankSetup.php';
require_once __DIR__.'/../class/NorwayWizardProgress.php';
$langs->loadLangs(array('norway@norway', 'accountancy'));
header('Cache-Control: no-store');
$step = max(1, min(11, GETPOSTINT('step') ?: 1));
$base = dol_buildpath('/norway/admin/wizard.php', 1);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && GETPOST('action', 'aZ09') === 'saveprogress') {
    try {
        if (GETPOSTINT('step') < 1 || GETPOSTINT('step') > 11) { throw new InvalidArgumentException('Step'); }
        $pending = $_SESSION['norway_wizard_progress'][$step] ?? null;
        unset($_SESSION['norway_wizard_progress'][$step]);
        if (!$pending || $pending['entity'] !== (int) $conf->entity || $pending['user'] !== (int) $user->id
            || $pending['org'] !== (string) $mysoc->idprof1 || $pending['expires'] < time()
            || !hash_equals($pending['nonce'], GETPOST('progress_nonce', 'aZ09'))) { throw new InvalidArgumentException('Progress session'); }
        NorwayWizardProgress::save($db, $conf->entity, $step, GETPOST('workstate', 'aZ09'), $mysoc->idprof1, $pending['fingerprint'], $user);
        setEventMessages($langs->trans('NorwayWizardProgressSaved'), null, 'mesgs');
    } catch (Throwable $error) { setEventMessages($langs->trans('NorwayWizardProgressFailed'), null, 'errors'); }
    header('Location: '.$base.'?step='.$step); exit;
}
$progress = array(); $progressError = false;
try {
    for ($i = 1; $i <= 11; $i++) { $progress[$i] = NorwayWizardProgress::read($db, $conf->entity, $i, $mysoc->idprof1); }
} catch (RuntimeException $error) { $progressError = true; }
$suggested = !$progressError ? NorwayWizardProgress::suggestedStep($progress) : 0;
if (!GETPOSTISSET('step') && !$progressError) { $step = $suggested ?: 11; }


// Ordered tasks are distinct from optional read-only diagnostics.
$tasks = array(
    1 => array('Scope'=>null),
    2 => array('Company'=>'/admin/company.php', 'Users'=>'/user/list.php'),
    3 => array('VATCompany'=>'/admin/company.php', 'Registration'=>'/custom/norway/admin/registration.php'),
    4 => array('Chart'=>'/custom/norway/admin/profile.php', 'Defaults'=>'/custom/norway/admin/defaults.php', 'OtherAccounts'=>'/custom/norway/admin/standardaccounts.php', 'VatRates'=>'/custom/norway/admin/vatrates.php', 'VatAccounts'=>'/custom/norway/admin/vataccounts.php', 'Journals'=>'/custom/norway/admin/journals.php'),
    5 => array('Bank'=>'/compta/bank/list.php', 'BankLink'=>'/custom/norway/admin/bankdefaults.php'),
    6 => array('Partners'=>'/societe/list.php', 'Products'=>'/custom/norway/admin/productaccounts.php'),
    7 => array('Numbering'=>'/custom/norway/admin/numbering.php', 'Invoice'=>'/admin/invoice.php', 'Proposal'=>'/admin/propal.php', 'PDF'=>null),
    8 => array('Public'=>null, 'Foreign'=>null),
    9 => array('Balances'=>null, 'Periods'=>'/accountancy/admin/fiscalyear.php'),
    10 => array('Reporting'=>null, 'Backup'=>null),
    11 => array('Review'=>null),
);
$optional = array(
    1=>array('Registry'=>'/custom/norway/admin/registry.php'),
    2=>array(),
    3=>array('Registry'=>'/custom/norway/admin/registry.php', 'VATCheck'=>'/custom/norway/admin/vat.php'),
    4=>array('TaxAccounts'=>'/custom/norway/admin/standardaccounts.php#norway-tax-accounts', 'JournalSettings'=>'/accountancy/admin/journals_list.php', 'AccountingCheck'=>'/custom/norway/admin/accounting.php'),
    5=>array(), 6=>array(),
    7=>array('DocumentCheck'=>'/custom/norway/admin/documents.php'),
    8=>array(),
    9=>array(),
    10=>array('Coverage'=>'/custom/norway/admin/setup.php'),
    11=>array('AccountingCheck'=>'/custom/norway/admin/accounting.php', 'Coverage'=>'/custom/norway/admin/setup.php'),
);
llxHeader('', $langs->trans('NorwayWizard'));
print '<div class="norway-page norway-wizard">';
print load_fiche_titre($langs->trans('NorwayWizard'), '', 'accountancy');
print '<p>'.$langs->trans('NorwayWizardScope').'</p>';
if (!$progressError && !GETPOSTISSET('step')) {
    print '<p>'.$langs->trans($suggested ? 'NorwayWizardResumeHelp' : 'NorwayWizardResumeNone').'</p>';
} elseif (!$progressError && $suggested && $suggested !== $step) {
    print '<p>'.$langs->trans('NorwayWizardResume').': <a href="'.$base.'?step='.$suggested.'">'.dol_escape_htmltag($suggested.' / 11 — '.$langs->trans('NorwayWizardTitle'.$suggested)).'</a></p>';
}
print '<details><summary>'.$langs->trans('NorwayWizardOverview').'</summary><ol>';
for ($i = 1; $i <= 11; $i++) {
    print '<li><a href="'.$base.'?step='.$i.'"'.($i === $step ? ' aria-current="step"' : '').'>'
        .$langs->trans('NorwayWizardTitle'.$i).'</a>'.(!$progressError ? ' — '.$langs->trans('NorwayWizardState'.($progress[$i]['stale'] ? 'Stale' : $progress[$i]['state'])) : '').'</li>';
}
print '</ol></details>';
print '<h2>'.dol_escape_htmltag($step.' / 11 — '.$langs->trans('NorwayWizardTitle'.$step)).'</h2>';
if ($progressError) { print '<div class="error">'.$langs->trans('NorwayWizardProgressReadError').'</div>'; }
else {
    print '<p class="norway-wizard-workstate">'.$langs->trans('NorwayWizardProgress').': <strong>'.$langs->trans('NorwayWizardState'.($progress[$step]['stale'] ? 'Stale' : $progress[$step]['state'])).'</strong></p>';
}

print '<p class="norway-wizard-intro">'.$langs->trans('NorwayWizardBody'.$step).'</p>';
print '<section aria-labelledby="norway-tasks"><h3 id="norway-tasks">'.$langs->trans('NorwayWizardTasks').'</h3>';
print '<p>'.$langs->trans(count(array_filter($tasks[$step])) ? 'NorwayWizardOpenHelp' : 'NorwayWizardManualHelp').'</p><ol class="norway-wizard-tasks">';
foreach ($tasks[$step] as $key=>$path) {
    print '<li><h4>'.$langs->trans('NorwayWizardTask'.$key).'</h4><p>'.$langs->trans('NorwayWizardTask'.$key.'Help').'</p>';
    if ($path !== null) {
        print '<a class="norway-wizard-open" target="_blank" rel="noopener noreferrer" href="'.DOL_URL_ROOT.$path.'">'.$langs->trans('NorwayWizardOpen').' '.$langs->trans('NorwayWizardTask'.$key).'<span class="norway-wizard-tab"> — '.$langs->trans('NorwayWizardNewTab').'</span></a>';
    }
    print '</li>';
}
print '</ol></section>';
print '<section class="norway-wizard-before" aria-labelledby="norway-before"><h3 id="norway-before">'.$langs->trans('NorwayWizardBefore').'</h3><p>'.$langs->trans('NorwayWizardBefore'.$step).'</p></section>';
if ($step === 2) {
    print '<details><summary>'.$langs->trans('NorwayWizardCompanyHelp').'</summary><p>'.$langs->trans('NorwayCompanyFieldsHelp').'</p><p>'.$langs->trans('NorwayVatAutoHelp').'</p></details>';
}
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
            $label = strpos($check['key'], 'ACCOUNTING_') === 0 ? 'NorwayWizardAccount'.$check['key'] : 'NorwayAccounting'.$check['key'];
            $checks[] = array($langs->trans($label).' — '.$check['value'], $langs->trans('NorwayAccountingStatus'.$check['status']));
        }
    } elseif ($step === 5) {
        $banks = (new NorwayBankSetup($db, $conf->entity))->report(getDolGlobalInt('CHARTOFACCOUNTS'));
        if (!$banks) { $checks[] = array($langs->trans('NorwayWizardTaskBank'), $langs->trans('NorwayBankSetupEmpty')); }
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
        print '<details><summary>'.$langs->trans('NorwayWizardCurrent').'</summary><p>'.$langs->trans('NorwayWizardCurrentHelp').'</p>';
        print '<div class="div-table-responsive"><table class="noborder centpercent">';
        foreach ($checks as $check) {
            print '<tr><td>'.dol_escape_htmltag((string) $check[0]).'</td><td>'.dol_escape_htmltag((string) $check[1]).'</td></tr>';
        }
        print '</table></div></details>';
    }
} catch (RuntimeException $error) {
    print '<div class="error">'.$langs->trans('NorwayAccountingReadError').'</div>';
}
if ($optional[$step]) {
    print '<details><summary>'.$langs->trans('NorwayWizardOptional').'</summary><p>'.$langs->trans('NorwayWizardOptionalHelp').'</p><ul class="norway-wizard-optional">';
    foreach ($optional[$step] as $key=>$path) {
        print '<li><a target="_blank" rel="noopener noreferrer" href="'.DOL_URL_ROOT.$path.'">'.$langs->trans('NorwayWizardExtra'.$key).' — '.$langs->trans('NorwayWizardNewTab').'</a><p>'.$langs->trans('NorwayWizardExtra'.$key.'Help').'</p></li>';
    }
    print '</ul></details>';
}
print '<details><summary>'.$langs->trans('NorwayWizardLimits').'</summary><p>'.$langs->trans('NorwayWizardLimit'.$step).'</p></details>';
if (!$progressError) {
    $nonce = bin2hex(random_bytes(16));
    $_SESSION['norway_wizard_progress'][$step] = array('entity'=>(int) $conf->entity, 'user'=>(int) $user->id,
        'org'=>(string) $mysoc->idprof1, 'nonce'=>$nonce, 'fingerprint'=>$progress[$step]['fingerprint'], 'expires'=>time()+1800);
    print '<section class="norway-wizard-before" aria-labelledby="norway-progress"><h3 id="norway-progress">'.$langs->trans('NorwayWizardProgress').'</h3><p>'.$langs->trans('NorwayWizardProgressHelp').'</p>';
    if ($progress[$step]['stale']) { print '<p>'.$langs->trans('NorwayWizardProgressStale').'</p>'; }
    print '<form method="post" action="'.$base.'"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="saveprogress"><input type="hidden" name="step" value="'.$step.'"><input type="hidden" name="progress_nonce" value="'.$nonce.'">';
    print '<label for="norway-workstate">'.$langs->trans('NorwayWizardProgressChoose').'</label><select id="norway-workstate" name="workstate">';
    foreach (NorwayWizardProgress::states() as $state) { print '<option value="'.$state.'"'.($state === ($progress[$step]['stale'] ? 'NotStarted' : $progress[$step]['state']) ? ' selected' : '').'>'.$langs->trans('NorwayWizardState'.$state).'</option>'; }
    print '</select><button class="button" type="submit">'.$langs->trans('NorwayWizardProgressSave').'</button></form>';
    if ($progress[$step]['updated'] !== '') { print '<p>'.$langs->trans('NorwayWizardProgressUpdated').': '.dol_escape_htmltag($progress[$step]['updated']).'</p>'; }
    print '</section>';
}
print '<nav class="norway-wizard-nav" aria-label="'.$langs->trans('NorwayWizardNavigation').'">';
if ($step > 1) { print '<a class="butAction" href="'.$base.'?step='.($step - 1).'">'.$langs->trans('NorwayWizardPrevious').'</a> '; }
if ($step < 11) { print '<a class="butAction" href="'.$base.'?step='.($step + 1).'">'.$langs->trans('NorwayWizardNext').'</a>'; }
print '</nav><p>'.$langs->trans('NorwayWizardNoSave').'</p><p><a target="_blank" rel="noopener noreferrer" href="https://github.com/mfuryto/dolibarr-norway/blob/main/docs/norwegian-setup-guide.md">'
    .$langs->trans('NorwayWizardGuide').'</a></p>';
print '</div>';
llxFooter();
$db->close();
