<?php
// SPDX-License-Identifier: GPL-3.0-or-later
if (PHP_SAPI !== 'cli' || getenv('NORWAY_SYNTHETIC_TEST') !== '1') {
    exit(1);
}
$cookie = tempnam(sys_get_temp_dir(), 'norway-http-');
$curl = curl_init();
curl_setopt_array($curl, array(
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEJAR => $cookie,
    CURLOPT_COOKIEFILE => $cookie,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 20,
));
function request($path, $post = null)
{
    global $curl;
    curl_setopt($curl, CURLOPT_URL, 'http://localhost'.$path);
    curl_setopt($curl, CURLOPT_POST, $post !== null);
    if ($post !== null) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $body = curl_exec($curl);
    if ($body === false || curl_getinfo($curl, CURLINFO_HTTP_CODE) >= 500) {
        throw new RuntimeException('HTTP request failed: '.curl_error($curl));
    }
    return $body;
}
try {
    $productCodes=request('/custom/norway/admin/productaccounts.php');
    if (strpos($productCodes,'name="scope"')!==false) { throw new RuntimeException('Product codes exposed before login'); }
    $standard=request('/custom/norway/admin/standardaccounts.php');
    if (strpos($standard,'previewstandard')!==false) { throw new RuntimeException('Standard account form exposed before login'); }
    $mapping=request('/custom/norway/admin/taxmappings.php');
    if (strpos($mapping,'previewmapping')!==false) { throw new RuntimeException('Mapping form exposed before login'); }
    $batch=request('/custom/norway/admin/invoiceperiod.php?start=2026-10-01&end=2026-10-01');
    if (strpos($batch,'name="start"')!==false || strpos($batch,'invoice.php?id=')!==false) { throw new RuntimeException('Invoice period exposed before login'); }
    $bankDefaults = request('/custom/norway/admin/bankdefaults.php');
    if (strpos($bankDefaults,'value="savebankdefaults"')!==false) { throw new RuntimeException('Bank mapping writable before login'); }
    $registration=request('/custom/norway/admin/registration.php');
    if (strpos($registration,'previewregistration')!==false) { throw new RuntimeException('Registration setup exposed before login'); }
    $vatRates=request('/custom/norway/admin/vatrates.php');
    if (strpos($vatRates,'value="savevatrates"')!==false || strpos($vatRates,'norway-page')!==false) { throw new RuntimeException('VAT setup exposed before login'); }
    $vat=request('/custom/norway/admin/vat.php');
    if (strpos($vat,'VAT account mapping check')!==false || strpos($vat,'Kontroll av MVA-kontokoblinger')!==false) { throw new RuntimeException('VAT report exposed before login'); }
    $periods=request('/custom/norway/admin/periods.php');
    if (strpos($periods,'Fiscal period setup check')!==false || strpos($periods,'Kontroll av regnskapsperioder')!==false) { throw new RuntimeException('Period report exposed before login'); }
    $documents=request('/custom/norway/admin/documents.php');
    if (strpos($documents,'Invoice and proposal setup check')!==false || strpos($documents,'Kontroll av faktura- og tilbudsoppsett')!==false) { throw new RuntimeException('Document setup exposed before login'); }
    $journals = request('/custom/norway/admin/journals.php');
    if (strpos($journals, 'value="createjournals"') !== false) { throw new RuntimeException('Journal confirmation visible before login'); }
    $defaults = request('/custom/norway/admin/defaults.php');
    if (strpos($defaults, 'value="savedefaults"') !== false) { throw new RuntimeException('Defaults confirmation visible before login'); }
    $wizard = request('/custom/norway/admin/wizard.php');
    if (strpos($wizard, 'Norwegian setup wizard') !== false || strpos($wizard, 'Norsk oppsettsveiviser') !== false) {
        throw new RuntimeException('Wizard visible before authentication');
    }
    $preview = request('/custom/norway/admin/chart.php');
    if (strpos($preview, 'name="chart_csv"') !== false) { throw new RuntimeException('Chart preview visible before authentication'); }
    $accounting = request('/custom/norway/admin/accounting.php');
    if (strpos($accounting, 'Chart and accounting setup check') !== false
        || strpos($accounting, 'Kontroll av kontoplan og bokføringsoppsett') !== false) {
        throw new RuntimeException('Accounting setup visible before authentication');
    }
    $body = request('/custom/norway/admin/setup.php');
    if (strpos($body, 'Not ready for production accounting.') !== false
        || strpos($body, 'Ikke klart for produksjonsregnskap.') !== false
        || strpos($body, 'Company configuration checks') !== false
        || strpos($body, 'Kontroll av foretaksoppsett') !== false) {
        throw new RuntimeException('Status visible before authentication');
    }
    if (!preg_match('/name="token"[^>]*value="([^"]+)"/', $body, $match)) {
        throw new RuntimeException('Missing login CSRF token');
    }
    request('/index.php', array(
        'token' => html_entity_decode($match[1]),
        'actionlogin' => 'login',
        'loginfunction' => 'loginfunction',
        'username' => 'testadmin',
        'password' => getenv('DOLI_ADMIN_PASSWORD'),
    ));
    $invoiceList=request('/custom/norway/admin/invoice.php');
    if (!preg_match('/invoice\.php\?id=([0-9]+)/',$invoiceList,$allocationFixture)) { throw new RuntimeException('Allocation invoice fixture missing'); }
    $allocationId=(int)$allocationFixture[1];
    foreach (array('nb_NO'=>'Bokført fordeling på kunde, salg og MVA','en_US'=>'Posted customer, sales and VAT allocation') as $locale=>$title) {
        $allocation=request('/custom/norway/admin/invoice.php?id='.$allocationId.'&ledger=1&allocation=domestic25&lang='.$locale);
        if (strpos(html_entity_decode($allocation,ENT_QUOTES,'UTF-8'),$title)===false || strpos($allocation,'NorwayAllocation')!==false || strpos($allocation,'NorwayLineAccounts')!==false || strpos($allocation,'NorwayPosting')!==false) { throw new RuntimeException('Allocation rendering failed'); }
    }
    foreach (array('nb_NO'=>'Regnskapskoder på varer og tjenester','en_US'=>'Accounting codes on products and services') as $locale=>$title) {
        foreach (array('domestic','export','intra') as $scope) {
            $codes=request('/custom/norway/admin/productaccounts.php?lang='.$locale.'&scope='.$scope);
            if (strpos(html_entity_decode($codes,ENT_QUOTES,'UTF-8'),$title)===false || strpos($codes,'NorwayProductAccounts')!==false || strpos($codes,'Could not read account codes.')!==false || strpos($codes,'Kunne ikke lese kontokodene.')!==false) { throw new RuntimeException('Product code rendering failed'); }
        }
    }
    $codes=request('/custom/norway/admin/productaccounts.php?lang=en_US&scope=invalid');
    if (strpos($codes,'Could not read account codes.')===false) { throw new RuntimeException('Invalid product scope accepted'); }
    foreach (array('nb_NO'=>'Norske standardkontoer under Regnskap','en_US'=>'Norwegian default accounts under Accounting') as $locale=>$title) {
        $standard=request('/custom/norway/admin/standardaccounts.php?lang='.$locale);
        if (strpos(html_entity_decode($standard,ENT_QUOTES,'UTF-8'),$title)===false || strpos($standard,'NorwayStandard')!==false || strpos($standard,'choices[ACCOUNTING_RESULT_LOSS]')===false || strpos($standard,'accountancy/admin/closure.php')===false || strpos($standard,'choices[ACCOUNTING_SERVICE_SOLD_EXPORT_ACCOUNT]')===false || strpos($standard,'choices[ACCOUNTING_SERVICE_BUY_EXPORT_ACCOUNT]')===false || strpos($standard,'trade-account-suggestions.md')===false) { throw new RuntimeException('Complete translated standard/closing page missing'); }
    }
    foreach (array('nb_NO'=>'Fyll MVA-kontoer for salg og innkjøp','en_US'=>'Fill VAT accounts for sales and purchases') as $locale=>$title) {
        $vatAccounts=request('/custom/norway/admin/vataccounts.php?lang='.$locale);
        if (strpos(html_entity_decode($vatAccounts,ENT_QUOTES,'UTF-8'),$title)===false || strpos($vatAccounts,'NorwayVatAccounts')!==false || strpos($vatAccounts,'Saving could not be confirmed.')!==false || strpos($vatAccounts,'Kunne ikke bekrefte lagringen.')!==false) { throw new RuntimeException('VAT accounts rendering failed'); }
    }
    foreach (array('nb_NO'=>'Sett opp ordinære norske MVA-satser','en_US'=>'Set up ordinary Norwegian VAT rates') as $locale=>$title) {
        $vatRates=request('/custom/norway/admin/vatrates.php?lang='.$locale);
        if (strpos(html_entity_decode($vatRates,ENT_QUOTES,'UTF-8'),$title)===false || strpos($vatRates,'NorwayVatRates')!==false || strpos($vatRates,'Saving could not be confirmed.')!==false) { throw new RuntimeException('VAT rate setup rendering failed'); }
    }
    request('/custom/norway/admin/vatrates.php',array('action'=>'savevatrates','token'=>'invalid','confirmed'=>'1'));
    if (curl_getinfo($curl,CURLINFO_HTTP_CODE)!==403) { throw new RuntimeException('VAT rate setup CSRF rejection missing'); }
    request('/custom/norway/admin/vataccounts.php',array('action'=>'savevataccounts','token'=>'invalid','confirmed'=>'1'));
    if (curl_getinfo($curl,CURLINFO_HTTP_CODE)!==403) { throw new RuntimeException('VAT accounts CSRF rejection missing'); }
    $standard=request('/custom/norway/admin/standardaccounts.php?lang=en_US');
    if (!preg_match('/name="token" value="([^"]+)"/',$standard,$standardToken)) { throw new RuntimeException('Standard token missing'); }
    foreach (array('vatrates'=>'savevatrates','vataccounts'=>'savevataccounts') as $page=>$action) {
        curl_setopt($curl,CURLOPT_FOLLOWLOCATION,false);
        $rejected=request('/custom/norway/admin/'.$page.'.php',array('action'=>$action,'token'=>html_entity_decode($standardToken[1]),'confirmed'=>'1'));
        if (curl_getinfo($curl,CURLINFO_HTTP_CODE)!==302 || strpos($rejected,'<p>')!==false) { throw new RuntimeException('Unconfirmed VAT setup did not redirect cleanly'); }
        curl_setopt($curl,CURLOPT_FOLLOWLOCATION,true);
    }
    $standard=request('/custom/norway/admin/standardaccounts.php',array('action'=>'previewstandard','token'=>html_entity_decode($standardToken[1]),'choices'=>array('ACCOUNTING_RESULT_PROFIT'=>'8800')));
    if (strpos($standard,'Saving could not be confirmed.')===false || strpos($standard,'value="savestandard"')!==false) { throw new RuntimeException('Wrong-purpose closing choice accepted'); }
    request('/custom/norway/admin/standardaccounts.php',array('action'=>'previewstandard','token'=>'invalid','choices'=>array('ACCOUNTING_RESULT_PROFIT'=>'2050')));
    if (curl_getinfo($curl,CURLINFO_HTTP_CODE)!==403) { throw new RuntimeException('Standard account CSRF rejection missing'); }
    foreach (array('nb_NO'=>'Lagrede koblinger av avgiftskoder','en_US'=>'Stored tax code mappings') as $locale=>$title) {
        $mapping=request('/custom/norway/admin/taxmappings.php?lang='.$locale);
        if (strpos(html_entity_decode($mapping,ENT_QUOTES,'UTF-8'),$title)===false || strpos($mapping,'NorwayMappings')!==false) { throw new RuntimeException('Translated mapping page missing'); }
    }
    foreach (array('nb_NO', 'en_US') as $wizardLanguage) {
        for ($wizardStep = 1; $wizardStep <= 11; $wizardStep++) {
            $wizard = request('/custom/norway/admin/wizard.php?step='.$wizardStep.'&lang='.$wizardLanguage);
            if (curl_getinfo($curl, CURLINFO_HTTP_CODE) !== 200
                || strpos($wizard, $wizardStep.' / 11') === false
                || strpos($wizard, 'aria-current="step"') === false
                || strpos($wizard, 'norwegian-setup-guide.md') === false
                || strpos($wizard, 'NorwayWizard') !== false
                || strpos($wizard, 'NorwayWizardLimit') !== false
                || strpos($wizard, 'Could not read accounting setup') !== false
                || strpos($wizard, 'Kunne ikke lese bokføringsoppsettet') !== false
                || substr_count($wizard, 'value="saveprogress"') !== 1
                || substr_count($wizard, '<form') !== 1) {
                throw new RuntimeException('Wizard step missing, untranslated, failed or writable');
            }
        }
    }
    $wizard=request('/custom/norway/admin/wizard.php?step=1&lang=en_US');
    if (!preg_match('/option value="([A-Za-z]+)" selected/', $wizard, $originalState)
        || !preg_match('/name="token" value="([^"]+)"/', $wizard, $workToken)
        || !preg_match('/name="progress_nonce" value="([^"]+)"/', $wizard, $workNonce)) { throw new RuntimeException('Work status form missing'); }
    $workPost=array('action'=>'saveprogress','step'=>1,'workstate'=>'Waiting','token'=>html_entity_decode($workToken[1]),'progress_nonce'=>$workNonce[1]);
    $wizard=request('/custom/norway/admin/wizard.php', $workPost);
    if (strpos($wizard,'value="Waiting" selected')===false || strpos($wizard,'Work status saved.')===false) { throw new RuntimeException('Work status did not persist'); }
    $resume=request('/custom/norway/admin/wizard.php');
    if (strpos($resume,'1 / 11')===false || strpos($resume,'This step is suggested from saved work status.')===false) { throw new RuntimeException('Saved work not resumed on entry'); }
    $wizard=request('/custom/norway/admin/wizard.php', $workPost);
    if (strpos($wizard,'Could not save work status.')===false) { throw new RuntimeException('Replayed work status nonce accepted'); }
    $wizard=request('/custom/norway/admin/wizard.php', array('action'=>'saveprogress','step'=>1,'workstate'=>'Reviewed','token'=>'invalid'));
    if (curl_getinfo($curl,CURLINFO_HTTP_CODE)!==403) { throw new RuntimeException('Work status CSRF rejection missing'); }
    $wizard=request('/custom/norway/admin/wizard.php?step=1');
    preg_match('/name="token" value="([^"]+)"/', $wizard, $workToken);
    preg_match('/name="progress_nonce" value="([^"]+)"/', $wizard, $workNonce);
    $wizard=request('/custom/norway/admin/wizard.php', array('action'=>'saveprogress','step'=>1,'workstate'=>$originalState[1],'token'=>html_entity_decode($workToken[1]),'progress_nonce'=>$workNonce[1]));
    if (strpos($wizard,'value="'.$originalState[1].'" selected')===false) { throw new RuntimeException('Work status fixture restore failed'); }
    foreach (array(-2 => 1, 99 => 11) as $inputStep => $expectedStep) {
        $wizard = request('/custom/norway/admin/wizard.php?step='.$inputStep);
        if (strpos($wizard, $expectedStep.' / 11') === false) { throw new RuntimeException('Invalid wizard step not bounded'); }
    }
    foreach (array('nb_NO','en_US') as $documentLang) {
        $documents=request('/custom/norway/admin/documents.php?lang='.$documentLang);
        if (curl_getinfo($curl,CURLINFO_HTTP_CODE)!==200 || strpos($documents,'sponge')===false || strpos($documents,'NorwayDocuments')!==false || strpos($documents,'<form')!==false || strpos($documents,'admin/propal.php')===false) { throw new RuntimeException('Document setup missing, untranslated or writable'); }
    }
    foreach (array('nb_NO','en_US') as $periodLang) {
        $periods=request('/custom/norway/admin/periods.php?date=2024-02-29&lang='.$periodLang);
        if (curl_getinfo($curl,CURLINFO_HTTP_CODE)!==200 || strpos($periods,'2024-02-29')===false || strpos($periods,'NorwayPeriods')!==false || strpos($periods,'method="post"')!==false || strpos($periods,'fiscalyear.php')===false) { throw new RuntimeException('Period report missing, untranslated or writable'); }
    }
    $periods=request('/custom/norway/admin/periods.php?date=2023-02-29&lang=en_US');
    if (strpos($periods,'Enter a valid date')===false) { throw new RuntimeException('Invalid period check date accepted'); }
    foreach (array('nb_NO','en_US') as $vatLang) {
        $vat=request('/custom/norway/admin/vat.php?lang='.$vatLang);
        if (curl_getinfo($curl,CURLINFO_HTTP_CODE)!==200 || strpos($vat,'NorwayVatSetup')!==false || strpos($vat,'<form')!==false || strpos($vat,'dict.php?id=10')===false) { throw new RuntimeException('VAT report missing, untranslated or writable'); }
    }
    foreach (array('nb_NO','en_US') as $registrationLang) {
        $registration=request('/custom/norway/admin/registration.php?lang='.$registrationLang);
        if (curl_getinfo($curl,CURLINFO_HTTP_CODE)!==200 || strpos($registration,'NorwayRegistration')!==false) { throw new RuntimeException('Registration page missing or untranslated'); }
    }
    request('/custom/norway/admin/registration.php',array('action'=>'saveregistration','token'=>'invalid','confirmed'=>'1'));
    if (curl_getinfo($curl,CURLINFO_HTTP_CODE)!==403) { throw new RuntimeException('Registration CSRF rejection missing'); }
    $company=request('/admin/company.php?lang=nb_NO');
    foreach (array('Organisasjonsnummer','Organisasjonsform','MVA-nummer (ved registrering)','ENK — Enkeltpersonforetak','AS — Aksjeselskap','value="173001"','value="173002"') as $required) {
        if (strpos(html_entity_decode($company,ENT_QUOTES,'UTF-8'),$required)===false) { throw new RuntimeException('Norwegian company field/form missing: '.$required); }
    }
    $bankDefaults=request('/custom/norway/admin/bankdefaults.php');
    if (curl_getinfo($curl,CURLINFO_HTTP_CODE)!==200 || strpos($bankDefaults,'NorwayBankDefaultsReason')!==false || strpos($bankDefaults,'value="savebankdefaults"')!==false) { throw new RuntimeException('Bank mapping missing or offers write with accounting history'); }
    request('/custom/norway/admin/bankdefaults.php',array('action'=>'savebankdefaults','token'=>'invalid'));
    if (curl_getinfo($curl,CURLINFO_HTTP_CODE)!==403) { throw new RuntimeException('Bank mapping CSRF guard missing'); }
    $accounting = request('/custom/norway/admin/accounting.php');
    if (curl_getinfo($curl, CURLINFO_HTTP_CODE) !== 200
        || (strpos($accounting, 'Chart and accounting setup check') === false
            && strpos($accounting, 'Kontroll av kontoplan og bokføringsoppsett') === false)
        || strpos($accounting, '3000') === false
        || strpos($accounting, 'accountancy/admin/defaultaccounts.php') === false
        || strpos($accounting, 'compta/bank/list.php') === false
        || (strpos($accounting, 'Bank and cash mapping checks') === false
            && strpos($accounting, 'Kontroll av bank- og kassekoblinger') === false)
        || strpos($accounting, '/norway/css/layout-0.5.0.css') === false
        || strpos($accounting, 'Could not read accounting setup') !== false
        || strpos($accounting, 'Kunne ikke lese bokføringsoppsettet') !== false) {
        throw new RuntimeException('Accounting setup report missing or failed');
    }
    $preview = request('/custom/norway/admin/chart.php');
    if (!preg_match('/name="token"[^>]*value="([^"]+)"/', $preview, $previewToken)
        || strpos($preview, 'chart_csv') === false) { throw new RuntimeException('Chart preview form missing'); }
    $preview = request('/custom/norway/admin/chart.php', array('action' => 'previewchart', 'token' => html_entity_decode($previewToken[1]),
        'chart_csv' => "account_number;label\n1500;Different label\n8888;Synthetic & label"));
    if (strpos($preview, 'Different label') === false || strpos($preview, 'norway-chart-csv/1 / SYN-') === false
        || strpos($preview, 'Synthetic &amp; label') === false
        || (strpos(html_entity_decode($preview, ENT_QUOTES | ENT_HTML5, 'UTF-8'), 'Conflict – existing account preserved') === false
            && strpos(html_entity_decode($preview, ENT_QUOTES | ENT_HTML5, 'UTF-8'), 'Konflikt – eksisterende konto bevares') === false)) {
        throw new RuntimeException('Chart preview failed or label HTML not escaped');
    }
    if (strpos($preview, 'value="importchart"') !== false) { throw new RuntimeException('Conflicting preview offers import'); }
    if (!preg_match('/name="token"[^>]*value="([^"]+)"/', $preview, $previewToken)) { throw new RuntimeException('Preview token missing'); }
    $number = (string) random_int(89000000, 89999999);
    $preview = request('/custom/norway/admin/chart.php', array('action' => 'previewchart', 'token' => html_entity_decode($previewToken[1]),
        'chart_csv' => "account_number;label\n".$number.';Synthetic HTTP import'));
    if (!preg_match('/name="preview_nonce"[^>]*value="([^"]+)"/', $preview, $nonce)
        || !preg_match('/name="token"[^>]*value="([^"]+)"/', $preview, $previewToken)) { throw new RuntimeException('Import confirmation missing'); }
    $preview = request('/custom/norway/admin/chart.php', array('action' => 'importchart', 'token' => html_entity_decode($previewToken[1]), 'preview_nonce' => $nonce[1]));
    if (strpos($preview, 'Created 1 new accounts') === false && strpos($preview, 'Opprettet 1 nye kontoer') === false) {
        throw new RuntimeException('Confirmed HTTP import failed');
    }
    if (!preg_match('/name="token"[^>]*value="([^"]+)"/', $preview, $previewToken)) { throw new RuntimeException('Import result token missing'); }
    $preview = request('/custom/norway/admin/chart.php', array('action' => 'importchart', 'token' => html_entity_decode($previewToken[1]), 'preview_nonce' => $nonce[1]));
    if (strpos($preview, 'Preview expired, changed') === false && strpos($preview, 'Forhåndsvisningen er utløpt, endret') === false) {
        throw new RuntimeException('Consumed preview can be replayed');
    }
    if (!preg_match('/name="token"[^>]*value="([^"]+)"/', $preview, $previewToken)) { throw new RuntimeException('Preview token missing'); }
    $preview = request('/custom/norway/admin/chart.php', array('action' => 'previewchart', 'token' => html_entity_decode($previewToken[1]), 'chart_csv' => 'invalid'));
    if (strpos($preview, 'Invalid CSV, duplicate') === false && strpos($preview, 'Ugyldig CSV, duplikat') === false) {
        throw new RuntimeException('Invalid chart CSV not rejected');
    }
    request('/custom/norway/admin/chart.php', array('action' => 'previewchart', 'token' => 'invalid', 'chart_csv' => 'invalid'));
    if (curl_getinfo($curl, CURLINFO_HTTP_CODE) !== 403) { throw new RuntimeException('Chart preview CSRF rejection missing'); }
    request('/custom/norway/admin/chart.php', array('action' => 'importchart', 'token' => 'invalid', 'preview_nonce' => 'invalid'));
    if (curl_getinfo($curl, CURLINFO_HTTP_CODE) !== 403) { throw new RuntimeException('Chart import CSRF rejection missing'); }
    $defaults = request('/custom/norway/admin/defaults.php');
    if (strpos($defaults, 'value="savedefaults"') !== false
        || (strpos($defaults, 'Filling blocked') === false && strpos($defaults, 'Utfylling sperret') === false
            && strpos($defaults, 'No empty choices') === false && strpos($defaults, 'Ingen tomme valg') === false)) {
        throw new RuntimeException('Defaults offers unsafe replacement or failed to render');
    }
    $journals = request('/custom/norway/admin/journals.php');
    if (curl_getinfo($curl,CURLINFO_HTTP_CODE)!==200 || strpos($journals,'NorwayJournalsScope')!==false
        || (strpos($journals,'Create missing journal types')===false && strpos($journals,'Opprett manglende journaltyper')===false)) {
        throw new RuntimeException('Journal preview missing');
    }
    request('/custom/norway/admin/journals.php', array('action'=>'createjournals','token'=>'invalid'));
    if (curl_getinfo($curl,CURLINFO_HTTP_CODE)!==403) { throw new RuntimeException('Journal CSRF guard missing'); }
    request('/custom/norway/admin/defaults.php', array('action'=>'savedefaults','token'=>'invalid'));
    if (curl_getinfo($curl,CURLINFO_HTTP_CODE)!==403) { throw new RuntimeException('Defaults CSRF guard missing'); }
    $profile = request('/custom/norway/admin/profile.php');
    if (strpos($profile, 'installprofile') !== false
        || (strpos($profile, 'The Norwegian chart cannot be installed now') === false
            && strpos($profile, 'Kontoplanen kan ikke installeres nå') === false)) {
        throw new RuntimeException('Profile offers to replace existing chart');
    }
    request('/custom/norway/admin/profile.php', array('action' => 'installprofile', 'token' => 'invalid'));
    if (curl_getinfo($curl, CURLINFO_HTTP_CODE) !== 403) { throw new RuntimeException('Profile CSRF rejection missing'); }
    $registry = request('/custom/norway/admin/registry.php');
    if (curl_getinfo($curl, CURLINFO_HTTP_CODE) !== 200
        || !preg_match('/name="token"[^>]*value="([^"]+)"/', $registry, $registryToken)
        || strpos($registry, 'lookupregistry') === false) {
        throw new RuntimeException('Registry form missing');
    }
    $registry = request('/custom/norway/admin/registry.php', array('action' => 'lookupregistry', 'token' => html_entity_decode($registryToken[1])));
    if (strpos($registry, 'Set a valid Norwegian organisation number') === false
        && strpos($registry, 'Oppgi et gyldig norsk organisasjonsnummer') === false) {
        throw new RuntimeException('Registry invalid company must fail before network');
    }
    $registry = request('/custom/norway/admin/registry.php', array('action' => 'lookupregistry', 'token' => 'invalid'));
    if (curl_getinfo($curl, CURLINFO_HTTP_CODE) !== 403
        || strpos($registry, 'Set a valid Norwegian organisation number') !== false
        || strpos($registry, 'Oppgi et gyldig norsk organisasjonsnummer') !== false
        || strpos($registry, 'lookupregistry') !== false) {
        throw new RuntimeException('Registry CSRF rejection missing');
    }
    $body = request('/custom/norway/admin/setup.php');
    if (strpos($body, 'Not ready for production accounting.') === false
        && strpos($body, 'Ikke klart for produksjonsregnskap.') === false) {
        throw new RuntimeException('Authenticated status page missing');
    }
    if (strpos($body, 'Company configuration checks') === false
        && strpos($body, 'Kontroll av foretaksoppsett') === false) {
        throw new RuntimeException('Configuration checks missing');
    }
    if (strpos($body, 'Could not read VAT configuration') !== false
        || strpos($body, 'Kunne ikke lese MVA-oppsettet') !== false) {
        throw new RuntimeException('VAT configuration query failed');
    }
    if (strpos($body, 'SAF-T 1.40') === false || strpos($body, 'EHF/Peppol') === false) {
        throw new RuntimeException('Unfinished accounting requirements missing');
    }
    if (strpos($body, '/norway/css/layout-0.5.0.css') === false) {
        throw new RuntimeException('Module layout stylesheet not loaded');
    }
    $recent = request('/custom/norway/admin/invoice.php');
    if (!preg_match('/invoice\.php\?id=([0-9]+)/', $recent, $invoiceLink)) { throw new RuntimeException('Synthetic invoice link missing'); }
    foreach (array('nb_NO'=>'MVA-beløp på faktura', 'en_US'=>'Invoice VAT amounts') as $locale=>$title) {
        $body = request('/custom/norway/admin/invoice.php?id='.$invoiceLink[1].'&lang='.$locale);
        $text = html_entity_decode($body, ENT_QUOTES, 'UTF-8');
        if (strpos($body,'05179521e435d82feb0b2d6c89a92a32a4f2d02f')===false || strpos($text, $locale==='nb_NO' ? 'SAF-T-referanse' : 'SAF-T reference')===false) { throw new RuntimeException('Pinned translated tax reference missing'); }
        if (strpos($text,$title)===false || strpos($text,'NorwayAmounts')!==false || strpos($text,'NorwayTax')!==false || strpos($text,$locale==='nb_NO' ? 'Hovedboksreferanser for fakturaen' : 'Bookkeeping references for this invoice')===false || strpos($text,'NorwayInvoiceLedger')!==false || strpos($text,'NorwaySavedMapping')!==false || strpos($text,$locale==='nb_NO' ? 'Sammenlign med en lagret oppsettsversjon' : 'Compare with a stored configuration revision')===false) { throw new RuntimeException('Translated invoice amount report missing'); }
    }
    foreach (array('nb_NO'=>'Prøv en mapping uten å lagre','en_US'=>'Try a mapping without saving') as $locale=>$title) {
        $base='/custom/norway/admin/invoice.php?id='.$invoiceLink[1].'&lang='.$locale;
        $body=request($base.'&mapping=1&mapsource=LOCAL25&maptarget=3&mapstart=2026-01-01&mapend=2026-12-31');
        $text=html_entity_decode($body,ENT_QUOTES,'UTF-8');
        if (strpos($text,$title)===false || strpos($text,'NorwayMapping')!==false || strpos($body,'name="mapsource"')===false) { throw new RuntimeException('Bilingual mapping preview missing'); }
        $body=request($base.'&mapping=1&mapsource=LOCAL25&maptarget=03&mapstart=2026-01-01&mapend=2026-12-31');
        if (strpos($body,$locale==='nb_NO' ? 'Angi en ikke-tom lokal kode' : 'Enter a non-empty local code')===false) { throw new RuntimeException('Invalid mapping plan accepted over HTTP'); }
    }
    foreach (array('nb_NO'=>'Fakturakontroll for periode','en_US'=>'Invoice checks for a period') as $locale=>$title) {
        $body=request('/custom/norway/admin/invoiceperiod.php?start=2026-10-01&end=2026-10-01&lang='.$locale);
        if (strpos(html_entity_decode($body,ENT_QUOTES,'UTF-8'),$title)===false || strpos($body,'NorwayBatch')!==false || strpos($body,'NorwayTax')!==false || strpos($body,'invoice.php?id=')===false) { throw new RuntimeException('Invoice period translation/data missing'); }
    }
    $body=request('/custom/norway/admin/invoiceperiod.php?start=2026-02-30&end=2026-03-01&lang=en_US');
    if (strpos($body,'Choose valid dates')===false || strpos($body,'invoice.php?id=')!==false) { throw new RuntimeException('Invalid period accepted'); }
    $body = request('/custom/norway/admin/invoice.php?id=0');
    if (strpos($body, 'Fakturaen kunne ikke leses i aktivt foretak.') === false
        && strpos($body, 'Invoice could not be read in the active entity.') === false) {
        throw new RuntimeException('Invalid invoice ID accepted');
    }
    $body = request('/custom/norway/admin/audit.php?date_start=2026-01-01&date_end=2026-12-31');
    if ((strpos($body, 'Bilagskontroll') === false && strpos($body, 'Voucher checks') === false)
        || strpos($body, 'Could not read the ledger') !== false || strpos($body, 'Kunne ikke lese hovedboken') !== false) {
        throw new RuntimeException('Voucher control page missing or failed');
    }
    $body = request('/custom/norway/admin/audit.php?date_start=2026-02-30&date_end=2026-03-01');
    if (strpos($body, 'Velg gyldige datoer') === false && strpos($body, 'Select valid ordered dates') === false) {
        throw new RuntimeException('Invalid audit period accepted');
    }
    $body = request('/societe/index.php?lang=nb_NO');
    if (strpos(html_entity_decode($body, ENT_QUOTES, 'UTF-8'), 'Kunder og leverandører') === false) { throw new RuntimeException('Norwegian customer/supplier menu missing'); }
    if (strpos($body, '/compta/paiement/cheque/') !== false) {
        throw new RuntimeException('Cheque dashboard or menu still visible');
    }
    $body = request('/index.php?lang=nb_NO');
    $text = html_entity_decode($body, ENT_QUOTES, 'UTF-8');
    if (strpos($body, 'bg-infobox-bank_account') === false) { throw new RuntimeException('Bank dashboard absent; cheque assertion would be vacuous'); }
    if (stripos($text, 'sjekkinnskudd') !== false || stripos($text, 'sjekkinskudd') !== false
        || stripos($body, 'BankChecksToReceipt') !== false || strpos($body, '/compta/paiement/cheque/') !== false) {
        throw new RuntimeException('Cheque deposit still visible in populated bank dashboard');
    }
    foreach (array('index.php', 'list.php', 'card.php?action=create2') as $page) {
        request('/compta/paiement/cheque/'.$page);
        if (curl_getinfo($curl, CURLINFO_HTTP_CODE) !== 403) { throw new RuntimeException('Direct cheque page accessible'); }
    }
    $body = request('/custom/norway/admin/pricing.php');
    if (strpos($body, 'Veiledende EUR-pris') === false && strpos($body, 'Indicative EUR price') === false) {
        throw new RuntimeException('Indicative price calculator missing');
    }
    print "PASS login and authenticated module status and configuration checks\n";
} finally {
    curl_close($curl);
    unlink($cookie);
}
