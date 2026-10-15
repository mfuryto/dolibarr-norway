<?php
// SPDX-License-Identifier: GPL-3.0-or-later
define('CSRFCHECK_WITH_TOKEN',1);
require '../../../main.inc.php';
if (GETPOST('errorcode','aZ09')==='InvalidToken') { httponly_accessforbidden('Invalid security token'); }
if (!$user->admin || !isModEnabled('norway') || !isModEnabled('accounting') || !$user->hasRight('accounting','chartofaccount')) { accessforbidden(); }
require_once __DIR__.'/../class/NorwayVatAccounts.php';
$langs->load('norway@norway');header('Cache-Control: no-store');
if ($_SERVER['REQUEST_METHOD']==='POST' && GETPOST('action','aZ09')==='savevataccounts') {
    $pending=$_SESSION['norway_vat_accounts'] ?? null;unset($_SESSION['norway_vat_accounts']);
    try {
        if (!$pending || $pending['expires']<time() || $pending['entity']!==(int)$conf->entity || $pending['user']!==(int)$user->id
            || !hash_equals($pending['nonce'],GETPOST('preview_nonce','aZ09')) || GETPOST('confirmed','aZ09')!=='1') { throw new InvalidArgumentException('Confirmation'); }
        $saved=NorwayVatAccounts::apply($db,$conf->entity,$pending['fingerprint'],$user);
        setEventMessages($langs->trans('NorwayDefaultsSaved',$saved),null,'mesgs');
    } catch (Throwable $error) { setEventMessages($langs->trans('NorwayDefaultsFailed'),null,'errors'); }
    header('Location: '.dol_buildpath('/norway/admin/vataccounts.php',1));exit;
}
llxHeader('',$langs->trans('NorwayVatAccountsTitle'));
print '<div class="norway-page">'.load_fiche_titre($langs->trans('NorwayVatAccountsTitle'),'','accountancy');
print '<p>'.$langs->trans('NorwayVatAccountsScope').'</p><p>'.$langs->trans('NorwayVatAccountsSeparate').'</p>';
print '<p><a href="'.dol_buildpath('/norway/admin/vatrates.php',1).'">'.$langs->trans('NorwayVatRatesTitle').'</a></p>';
try {
    $preview=NorwayVatAccounts::preview($db,$conf->entity);$fills=0;$blocked=false;
    print '<div class="div-table-responsive"><table class="noborder centpercent"><tr>';
    foreach (array('Rate','Direction','Explicit') as $key) { print '<th>'.$langs->trans('NorwayVatSetup'.$key).'</th>'; }
    print '<th>'.$langs->trans('NorwayAccountingSuggested').'</th><th>'.$langs->trans('NorwayVatSetupStatus').'</th></tr>';
    foreach ($preview['rows'] as $row) {
        $fills+=(int)($row['action']==='Fill');$blocked=$blocked || $row['action']==='Blocked';
        print '<tr><td>'.dol_escape_htmltag($row['rate'].' % / '.$row['code'].' (#'.$row['id'].')').'<br>'.dol_escape_htmltag($row['note']).'</td><td>'.$langs->trans('NorwayVatSetup'.$row['direction']).'</td><td>'.dol_escape_htmltag($row['current']).'</td><td>'.dol_escape_htmltag($row['number'].' — '.$row['label']).'</td><td>'.$langs->trans($row['action']==='Separate' ? 'NorwayStandardSeparate' : 'NorwayDefaults'.$row['action']).'</td></tr>';
    }
    print '</table></div>';
    if (!$blocked && $fills>0) {
        $nonce=bin2hex(random_bytes(16));
        $_SESSION['norway_vat_accounts']=array('entity'=>(int)$conf->entity,'user'=>(int)$user->id,'expires'=>time()+600,'nonce'=>$nonce,'fingerprint'=>NorwayDefaultAccounts::fingerprint($preview));
        print '<form method="post"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="savevataccounts"><input type="hidden" name="preview_nonce" value="'.$nonce.'"><p><label><input type="checkbox" name="confirmed" value="1" required> '.$langs->trans('NorwayVatAccountsConfirm').'</label></p><button class="button" type="submit">'.$langs->trans('NorwayDefaultsConfirm').'</button></form>';
    } else { unset($_SESSION['norway_vat_accounts']);print '<p>'.$langs->trans($blocked ? 'NorwayDefaultsBlocked' : 'NorwayDefaultsNothing').'</p>'; }
} catch (Throwable $error) { unset($_SESSION['norway_vat_accounts']);print '<div class="error">'.$langs->trans('NorwayDefaultsFailed').'</div>'; }
foreach (array('/admin/dict.php?id=10'=>'NorwayVatSetup','/custom/norway/admin/standardaccounts.php'=>'NorwayStandardTitle','/admin/dict.php?id=10'=>'NorwayVatSetupDictionary') as $path=>$label) { print '<p><a href="'.DOL_URL_ROOT.$path.'">'.$langs->trans($label).'</a></p>'; }
print '<p><a href="https://github.com/mfuryto/dolibarr-norway/blob/main/docs/vat-accounts.md" target="_blank" rel="noopener noreferrer">'.$langs->trans('NorwayStandardSources').'</a></p>';
print '</div>';llxFooter();$db->close();
