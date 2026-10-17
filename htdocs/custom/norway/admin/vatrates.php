<?php
// SPDX-License-Identifier: GPL-3.0-or-later
define('CSRFCHECK_WITH_TOKEN',1);
require '../../../main.inc.php';
if (GETPOST('errorcode','aZ09')==='InvalidToken') { httponly_accessforbidden('Invalid security token'); }
if (!$user->admin || !isModEnabled('norway') || !isModEnabled('accounting') || !$user->hasRight('accounting','chartofaccount')) { accessforbidden(); }
require_once __DIR__.'/../class/NorwayVatRates.php';
$langs->load('norway@norway');header('Cache-Control: no-store');
if ($_SERVER['REQUEST_METHOD']==='POST' && GETPOST('action','aZ09')==='savevatrates') {
    $pending=$_SESSION['norway_vat_rates'] ?? null;unset($_SESSION['norway_vat_rates']);
    try {
        if (!$pending || $pending['expires']<time() || $pending['entity']!==(int)$conf->entity || $pending['user']!==(int)$user->id
            || !hash_equals($pending['nonce'],GETPOST('preview_nonce','aZ09')) || GETPOST('confirmed','aZ09')!=='1') { throw new InvalidArgumentException('Confirmation'); }
        $saved=NorwayVatRates::apply($db,$conf->entity,$pending['fingerprint'],$user);
        setEventMessages($langs->trans('NorwayDefaultsSaved',$saved),null,'mesgs');
    } catch (Throwable $error) { setEventMessages($langs->trans('NorwayDefaultsFailed'),null,'errors'); }
    header('Location: '.dol_buildpath('/norway/admin/vatrates.php',1));exit;
}
llxHeader('',$langs->trans('NorwayVatRatesTitle'));
print '<div class="norway-page">'.load_fiche_titre($langs->trans('NorwayVatRatesTitle'),'','accountancy').'<p>'.$langs->trans('NorwayVatRatesScope').'</p><p>'.$langs->trans('NorwayVatRatesLimits').'</p>';
try {
    $preview=NorwayVatRates::preview($db,$conf->entity);$count=0;
    print '<div class="div-table-responsive"><table class="noborder centpercent"><tr><th>'.$langs->trans('NorwayVatSetupRate').'</th><th>'.$langs->trans('NorwayVatSetupDirection').'</th><th>'.$langs->trans('NorwayNativeVatCode').'</th><th>'.$langs->trans('NorwayVatTreatment').'</th><th>'.$langs->trans('NorwayVatSetupStatus').'</th></tr>';
    foreach ($preview['changes'] as $change) {
        $count+=(int)in_array($change['action'],array('Create','Deactivate'),true);
        print '<tr><td>'.dol_escape_htmltag($change['rate'].' %'.($change['id'] ? ' (#'.$change['id'].')' : '')).'</td><td>'.$langs->trans($change['type']===1?'NorwayVatSetupsell':($change['type']===2?'NorwayVatSetupbuy':'NorwayNativeVatBoth')).'</td><td>'.dol_escape_htmltag($change['code']).'</td><td>'.$langs->trans(in_array($change['code'],array('NO0','5','6','7','52','86'),true)?'NorwayVatCode'.$change['code']:'NorwayVatOrdinary').'</td><td>'.$langs->trans('NorwayVatRates'.$change['action']).'</td></tr>';
    }print '</table></div>';
    if (!$preview['blocked'] && $count>0) {
        $nonce=bin2hex(random_bytes(16));
        $_SESSION['norway_vat_rates']=array('entity'=>(int)$conf->entity,'user'=>(int)$user->id,'expires'=>time()+600,'nonce'=>$nonce,'fingerprint'=>NorwayVatRates::fingerprint($preview));
        print '<form method="post"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="savevatrates"><input type="hidden" name="preview_nonce" value="'.$nonce.'"><p><label><input type="checkbox" name="confirmed" value="1" required> '.$langs->trans('NorwayVatRatesConfirm').'</label></p><button class="button" type="submit">'.$langs->trans('NorwayDefaultsConfirm').'</button></form>';
    } else { unset($_SESSION['norway_vat_rates']);print '<p>'.$langs->trans(count(array_filter($preview['changes'],static fn($c)=>$c['action']!=='Keep'))>0 ? 'NorwayVatRatesBlocked' : 'NorwayDefaultsNothing').'</p>'; }
} catch (Throwable $error) { unset($_SESSION['norway_vat_rates']);print '<div class="error">'.$langs->trans('NorwayDefaultsFailed').'</div>'; }
foreach (array('/admin/dict.php?id=10'=>'NorwayVatSetupDictionary','/custom/norway/admin/wizard.php?step=4'=>'NorwayWizard') as $path=>$label) { print '<p><a href="'.DOL_URL_ROOT.$path.'">'.$langs->trans($label).'</a></p>'; }
print '<p><a href="https://skatteetaten.github.io/mva-meldingen/mvameldingen/informasjonsmodell/" target="_blank" rel="noopener noreferrer">'.$langs->trans('NorwayVatRatesSource').'</a></p></div>';llxFooter();$db->close();
