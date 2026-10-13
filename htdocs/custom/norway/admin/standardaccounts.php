<?php
// SPDX-License-Identifier: GPL-3.0-or-later
define('CSRFCHECK_WITH_TOKEN',1);
require '../../../main.inc.php';
if (GETPOST('errorcode','aZ09')==='InvalidToken') { httponly_accessforbidden('Invalid security token'); }
if (!$user->admin || !isModEnabled('norway') || !isModEnabled('accounting') || !$user->hasRight('accounting','chartofaccount')) { accessforbidden(); }
require_once __DIR__.'/../class/NorwayStandardAccounts.php';
$langs->load('norway@norway');header('Cache-Control: no-store');
$action=GETPOST('action','aZ09');
if ($_SERVER['REQUEST_METHOD']==='POST' && $action==='savestandard') {
    $pending=$_SESSION['norway_standard_accounts'] ?? null;unset($_SESSION['norway_standard_accounts']);
    try {
        if (!$pending || $pending['expires']<time() || $pending['entity']!==(int)$conf->entity || $pending['user']!==(int)$user->id
            || !hash_equals($pending['nonce'],GETPOST('preview_nonce','aZ09')) || GETPOST('confirmed','aZ09')!=='1') { throw new InvalidArgumentException('Confirmation'); }
        $saved=NorwayDefaultAccounts::apply($db,(int)$conf->entity,$pending['fingerprint'],$user,$pending['choices']);
        setEventMessages($langs->trans('NorwayDefaultsSaved',$saved),null,'mesgs');
    } catch (Throwable $error) { setEventMessages($langs->trans('NorwayDefaultsFailed'),null,'errors'); }
    header('Location: '.dol_buildpath('/norway/admin/standardaccounts.php',1));exit;
}
llxHeader('',$langs->trans('NorwayStandardTitle'));
print '<div class="norway-page">'.load_fiche_titre($langs->trans('NorwayStandardTitle'),'','accountancy').'<p>'.$langs->trans('NorwayStandardScope').'</p>';
try {
    print '<h2>'.$langs->trans('NorwayStandardProductCodes').'</h2><p>'.$langs->trans('NorwayStandardProductCodesHelp').'</p><p><a href="'.DOL_URL_ROOT.'/product/list.php?type=0">'.$langs->trans('NorwayStandardProducts').'</a> · <a href="'.DOL_URL_ROOT.'/product/list.php?type=1">'.$langs->trans('NorwayStandardServices').'</a></p>';
    print '<p><a href="'.dol_buildpath('/norway/admin/productaccounts.php',1).'">'.$langs->trans('NorwayProductAccountsTitle').'</a></p>';
    $catalog=NorwayStandardAccounts::catalog();
    $labels=array_column(NorwayChartPreview::parse(NorwayChartProfile::csv()),'label','number');
    print '<form method="post" class="norway-standard-accounts"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="previewstandard">';
    $groups=array('Parties'=>array(),'Trade'=>array(),'Foreign'=>array(),'Vat'=>array(),'Closing'=>array(),'Other'=>array());
    foreach ($catalog as $key=>$rule) {
        $group=in_array($key,array('ACCOUNTING_ACCOUNT_CUSTOMER','ACCOUNTING_ACCOUNT_SUPPLIER'),true) ? 'Parties'
            : (in_array($key,array('ACCOUNTING_PRODUCT_SOLD_ACCOUNT','ACCOUNTING_PRODUCT_BUY_ACCOUNT','ACCOUNTING_SERVICE_SOLD_ACCOUNT','ACCOUNTING_SERVICE_BUY_ACCOUNT'),true) ? 'Trade'
            : (strpos($key,'_EXPORT_ACCOUNT')!==false ? 'Foreign'
            : (in_array($key,array('ACCOUNTING_VAT_SOLD_ACCOUNT','ACCOUNTING_VAT_BUY_ACCOUNT','ACCOUNTING_VAT_PAY_ACCOUNT'),true) ? 'Vat'
            : (strpos($key,'ACCOUNTING_RESULT_')===0 ? 'Closing' : 'Other'))));
        $groups[$group][$key]=$rule;
    }
    foreach ($groups as $group=>$rules) {
        print $group==='Other' ? '<details><summary>'.$langs->trans('NorwayStandardGroup'.$group).'</summary>' : '<h2>'.$langs->trans('NorwayStandardGroup'.$group).'</h2>';
        print '<div class="div-table-responsive"><table class="noborder centpercent"><tr>';
        foreach (array('Field','Current','Choice') as $column) { print '<th>'.$langs->trans('NorwayStandard'.$column).'</th>'; }print '</tr>';
        foreach ($rules as $key=>$rule) {
            $value=getDolGlobalString($key);
            print '<tr><td>'.$langs->trans('NorwayStandardKey'.$key).'<br><span class="opacitymedium">'.$langs->trans('NorwayStandardReason'.$rule['reason']).'</span></td><td>'.dol_escape_htmltag($value==='' || $value==='-1' ? '—' : $value).'</td><td>';
            if ($rule['options']) {
                print '<select name="choices['.$key.']"><option value="">'.$langs->trans('NorwayStandardNoChange').'</option>';
                foreach ($rule['options'] as $number) { print '<option value="'.$number.'">'.dol_escape_htmltag($number.' — '.$labels[$number]).'</option>'; }print '</select>';
            } else { print $langs->trans('NorwayStandardSeparate'); }print '</td></tr>';
        }
        print '</table></div>';if ($group==='Other') { print '</details>'; }
    }
    print '<p><button class="button" type="submit">'.$langs->trans('NorwayStandardPreview').'</button></p></form>';
    if ($_SERVER['REQUEST_METHOD']==='POST' && $action==='previewstandard') {
        unset($_SESSION['norway_standard_accounts']);
        $choices=GETPOST('choices','array');
        if (!is_array($choices)) { throw new InvalidArgumentException('Choices'); }
        $choices=array_filter($choices,static fn($v)=>$v!=='');
        $choices=NorwayStandardAccounts::validate($choices);
        $preview=NorwayDefaultAccounts::preview($db,(int)$conf->entity,$choices);$blocked=false;$fills=0;
        print '<h2>'.$langs->trans('NorwayStandardPreview').'</h2><div class="div-table-responsive"><table class="noborder centpercent">';
        foreach ($preview['rows'] as $row) {
            $blocked=$blocked || $row['action']==='Blocked';$fills+=(int)($row['action']==='Fill');
            print '<tr><td>'.$langs->trans('NorwayStandardKey'.$row['key']).'</td><td>'.dol_escape_htmltag($row['number'].' — '.$row['label']).'</td><td>'.$langs->trans('NorwayDefaults'.$row['action']).'</td></tr>';
        }print '</table></div>';
        if (!$blocked && $fills>0) {
            $nonce=bin2hex(random_bytes(16));
            $_SESSION['norway_standard_accounts']=array('entity'=>(int)$conf->entity,'user'=>(int)$user->id,'expires'=>time()+600,'nonce'=>$nonce,'choices'=>$choices,'fingerprint'=>NorwayDefaultAccounts::fingerprint($preview));
            print '<form method="post"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="savestandard"><input type="hidden" name="preview_nonce" value="'.$nonce.'"><p><label><input type="checkbox" name="confirmed" value="1" required> '.$langs->trans('NorwayStandardConfirmScope').'</label></p><button class="button" type="submit">'.$langs->trans('NorwayStandardSave').'</button></form>';
        } else { print '<p>'.$langs->trans($blocked ? 'NorwayDefaultsBlocked' : 'NorwayDefaultsNothing').'</p>'; }
    }
} catch (Throwable $error) { unset($_SESSION['norway_standard_accounts']);print '<div class="warning">'.$langs->trans('NorwayDefaultsFailed').'</div>'; }
print '<p><a href="'.dol_buildpath('/norway/admin/vataccounts.php',1).'">'.$langs->trans('NorwayVatAccountsTitle').'</a></p>';
print '<section id="norway-tax-accounts"><h2>'.$langs->trans('NorwayTaxAccountsTitle').'</h2><p>'.$langs->trans('NorwayTaxAccountsHelp').'</p><p>'.$langs->trans('NorwayTaxAccountsEnk').'</p><p>'.$langs->trans('NorwayTaxAccountsOther').'</p>';
if (isModEnabled('tax')) { print '<p><a href="'.DOL_URL_ROOT.'/admin/dict.php?id=7&amp;from=accountancy&amp;search_country_id='.(int)$mysoc->country_id.'">'.$langs->trans('NorwayTaxAccountsOpen').'</a></p>'; }
print '<p><a href="https://github.com/mfuryto/dolibarr-norway/blob/main/docs/tax-accounts.md" target="_blank" rel="noopener noreferrer">'.$langs->trans('NorwayTaxAccountsGuide').'</a></p></section>';
print '<p><a href="https://github.com/mfuryto/dolibarr-norway/blob/main/docs/trade-account-suggestions.md" target="_blank" rel="noopener noreferrer">'.$langs->trans('NorwayStandardSources').'</a></p>';
print '<p>'.$langs->trans('NorwayStandardClosingScope').'</p><p><a href="'.DOL_URL_ROOT.'/accountancy/admin/closure.php">'.$langs->trans('NorwayStandardClosingOpen').'</a></p>';
print '<p><a href="'.DOL_URL_ROOT.'/accountancy/admin/defaultaccounts.php">'.$langs->trans('NorwayAccountingDefaultSettings').'</a></p><p><a href="'.dol_buildpath('/norway/admin/wizard.php',1).'?step=4">'.$langs->trans('NorwayWizard').'</a></p></div>';
llxFooter();$db->close();
