<?php
// SPDX-License-Identifier: GPL-3.0-or-later
define('CSRFCHECK_WITH_TOKEN',1);
require '../../../main.inc.php';
if (GETPOST('errorcode','aZ09')==='InvalidToken') { httponly_accessforbidden('Invalid security token'); }
if (!$user->admin || !isModEnabled('norway')) { accessforbidden(); }
require_once __DIR__.'/../class/NorwayVatRegistration.php';
require_once __DIR__.'/../class/NorwayVatHistoryView.php';
$langs->load('norway@norway'); header('Cache-Control: no-store');
NorwayVatHistoryView::handle($db,(int)$conf->entity,$mysoc->idprof1,$user,$langs);
$action=GETPOST('action','aZ09'); $date=GETPOST('start','alphanohtml');
if ($_SERVER['REQUEST_METHOD']==='POST' && $action==='saveregistration') {
    $pending=$_SESSION['norway_registration'] ?? null; unset($_SESSION['norway_registration']);
    try {
        if (!$pending || $pending['expires']<time() || $pending['entity']!==(int)$conf->entity || $pending['user']!==(int)$user->id
            || !hash_equals($pending['nonce'],GETPOST('preview_nonce','aZ09')) || GETPOST('confirmed','aZ09')!=='1') { throw new InvalidArgumentException('Confirmation'); }
        NorwayVatRegistration::apply($db,$conf->entity,$pending['date'],$pending['fingerprint'],$user);
        setEventMessages($langs->trans('NorwayRegistrationSaved'),null,'mesgs');
    } catch (Throwable $error) { setEventMessages($langs->trans('NorwayRegistrationFailed'),null,'errors'); }
    header('Location: '.dol_buildpath('/norway/admin/registration.php',1)); exit;
}
llxHeader('',$langs->trans('NorwayRegistration'));
print '<div class="norway-page">'.load_fiche_titre($langs->trans('NorwayRegistration'),'','company');
print '<div class="warning">'.$langs->trans('NorwayRegistrationScope').'</div>';
try {
    $record=NorwayVatRegistration::read($db,$conf->entity,$mysoc->idprof1);
    print '<p>'.$langs->trans('NorwayRegistration'.$record['status']).'</p>';
    if ($record['status']==='Recorded') {
        print '<p>'.$langs->trans('NorwayRegistrationStart').': '.dol_escape_htmltag($record['start']).'<br>'.$langs->trans('NorwayRegistrationConfirmedAt').': '.dol_escape_htmltag($record['confirmed_at']).'</p>';
        $check=GETPOST('date','alphanohtml');
        print '<form method="get"><label>'.$langs->trans('NorwayRegistrationCheckDate').' <input type="date" name="date" value="'.dol_escape_htmltag($check).'" required></label> <button class="button" type="submit">'.$langs->trans('NorwayRegistrationCompare').'</button></form>';
        if ($check!=='') { print '<p>'.$langs->trans('NorwayRegistration'.NorwayVatHistory::current($db,(int)$conf->entity,$mysoc->idprof1,$check)).'</p>'; }
    }
    if ($record['status']==='Missing') {
        print '<form method="post"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="previewregistration"><label>'.$langs->trans('NorwayRegistrationStart').' <input type="date" name="start" value="'.dol_escape_htmltag($date).'" required></label> <button class="button" type="submit">'.$langs->trans('NorwayRegistrationPreview').'</button></form>';
    }
    if ($_SERVER['REQUEST_METHOD']==='POST' && $action==='previewregistration') {
        unset($_SESSION['norway_registration']);
        $preview=NorwayVatRegistration::preview($db,$conf->entity,$date);
        if (!$preview['allowed']) { print '<div class="warning">'.$langs->trans('NorwayRegistrationBlocked').'</div>'; }
        else {
            $nonce=bin2hex(random_bytes(16)); $_SESSION['norway_registration']=array('entity'=>(int)$conf->entity,'user'=>(int)$user->id,'date'=>$date,'expires'=>time()+600,'nonce'=>$nonce,'fingerprint'=>NorwayVatRegistration::fingerprint($preview));
            print '<p>'.dol_escape_htmltag($preview['org'].' / '.$date).'</p><form method="post"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="saveregistration"><input type="hidden" name="preview_nonce" value="'.$nonce.'"><label><input type="checkbox" name="confirmed" value="1" required> '.$langs->trans('NorwayRegistrationConfirmScope').'</label><p><button class="button" type="submit">'.$langs->trans('NorwayRegistrationConfirm').'</button></p></form>';
        }
    }
} catch (InvalidArgumentException $error) { print '<div class="error">'.$langs->trans('NorwayRegistrationInvalidDate').'</div>'; }
catch (RuntimeException $error) { print '<div class="error">'.$langs->trans('NorwayRegistrationFailed').'</div>'; }
NorwayVatHistoryView::render($db,(int)$conf->entity,$mysoc->idprof1,$record??array('status'=>'Invalid'),$langs);
print '</div>';
llxFooter(); $db->close();
