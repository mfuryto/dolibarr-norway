<?php
// SPDX-License-Identifier: GPL-3.0-or-later
define('CSRFCHECK_WITH_TOKEN',1);
require '../../../main.inc.php';
if (GETPOST('errorcode','aZ09')==='InvalidToken') { httponly_accessforbidden('Invalid security token'); }
if (!$user->admin || !isModEnabled('norway')) { accessforbidden(); }
require_once __DIR__.'/../class/NorwayNumbering.php';
$langs->load('norway@norway'); header('Cache-Control: no-store');
$action=GETPOST('action','aZ09');
if ($_SERVER['REQUEST_METHOD']==='POST' && $action==='savenumbering') {
    $pending=$_SESSION['norway_numbering'] ?? null; unset($_SESSION['norway_numbering']);
    try {
        if (!$pending || $pending['expires']<time() || $pending['entity']!==(int)$conf->entity || $pending['user']!==(int)$user->id
            || !hash_equals($pending['nonce'],GETPOST('preview_nonce','aZ09')) || GETPOST('confirmed','aZ09')!=='1') { throw new InvalidArgumentException('Confirmation'); }
        NorwayNumbering::save($db,(int)$conf->entity,$pending['input'],$pending['fingerprint'],$user);
        setEventMessages($langs->trans('NorwayNumberingSaved'),null,'mesgs');
    } catch (Throwable $error) { setEventMessages($langs->trans('NorwayNumberingFailed'),null,'errors'); }
    header('Location: '.dol_buildpath('/norway/admin/numbering.php',1)); exit;
}
llxHeader('',$langs->trans('NorwayNumberingTitle'));
print '<div class="norway-page">'.load_fiche_titre($langs->trans('NorwayNumberingTitle'),'','bill');
print '<p>'.$langs->trans('NorwayNumberingScope').'</p>';
print '<p>'.$langs->trans('NorwayNumberingPayments').'</p>';
try {
    $r=$db->query("SELECT value FROM ".$db->prefix()."const WHERE entity=".(int)$conf->entity." AND name='".NorwayNumbering::SETTING."'");
    if (!$r) { throw new RuntimeException('Numbering settings'); } $row=$db->fetch_object($r);
    $record=$row ? NorwayNumbering::decode($row->value,(int)$conf->entity) : null;
    $input=$record ? $record['input'] : NorwayNumbering::input('F',1,'K',1);
    if ($_SERVER['REQUEST_METHOD']==='POST' && $action==='previewnumbering') {
        unset($_SESSION['norway_numbering']);
        $values=array();
        foreach (NorwayNumbering::KINDS as $kind) {
            $raw=GETPOST($kind.'_start','none');
            if (!is_string($raw) || !preg_match('/^[0-9]{1,6}$/D',$raw)) { throw new InvalidArgumentException('Start number'); }
            $values[$kind.'_start']=(int)$raw;
        }
        $input=NorwayNumbering::input(GETPOST('invoice_prefix','none'),$values['invoice_start'],GETPOST('credit_prefix','none'),$values['credit_start'],GETPOST('proposal_prefix','none'),$values['proposal_start'],$values['customer_start'],$values['supplier_start']);
    }
    $preview=NorwayNumbering::preview($db,(int)$conf->entity,$input,$user);
    if ($record) { print '<p>'.$langs->trans('NorwayNumberingActive').'</p>'; }
    else { print '<div class="warning">'.$langs->trans('NorwayNumberingNotActive').'</div>'; }
    if ($preview['locked']) { print '<div class="info">'.$langs->trans('NorwayNumberingLocked').'</div>'; }
    if ($preview['partners_locked']) { print '<div class="info">'.$langs->trans('NorwayNumberingPartnersLocked').'</div>'; }
    print '<h2>'.$langs->trans('NorwayNumberingNext').'</h2><p>'.$langs->trans('NorwayNumberingNotReserved').'</p>';
    print '<div class="div-table-responsive"><table class="noborder centpercent"><tr><th>'.$langs->trans('NorwayNumberingType').'</th><th>'.$langs->trans('NorwayNumberingStart').'</th><th>'.$langs->trans('NorwayNumberingNext').'</th></tr>';
    foreach (NorwayNumbering::KINDS as $kind) { print '<tr><td>'.$langs->trans('NorwayNumbering'.ucfirst($kind)).'</td><td>'.(int)$input[$kind.'_start'].'</td><td>'.dol_escape_htmltag($preview['next'][$kind]).'</td></tr>'; }
    print '</table></div>';
    if (!$preview['locked'] || !$preview['partners_locked']) {
        print '<h2>'.$langs->trans('NorwayNumberingConfigure').'</h2><form method="post"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="previewnumbering">';
        foreach (NorwayNumbering::KINDS as $kind) {
            $partner=in_array($kind,array('customer','supplier'),true); $readonly=($partner ? $preview['partners_locked'] : $preview['locked']) ? ' readonly' : '';
            print '<fieldset><legend>'.$langs->trans('NorwayNumbering'.ucfirst($kind)).'</legend>';
            if (!$partner) { print '<p><label>'.$langs->trans('NorwayNumberingPrefix').' <input type="text" maxlength="4" pattern="[A-Z]{1,4}" name="'.$kind.'_prefix" required'.$readonly.' value="'.dol_escape_htmltag($input[$kind.'_prefix']).'"></label></p>'; }
            print '<p><label>'.$langs->trans('NorwayNumberingStart').' <input type="number" min="1" max="'.($partner ? '99999' : '999999').'" name="'.$kind.'_start" required'.$readonly.' value="'.(int)$input[$kind.'_start'].'"></label></p></fieldset>';
        }
        print '<p>'.$langs->trans('NorwayNumberingStartHelp').'</p><button class="button" type="submit">'.$langs->trans('NorwayNumberingPreview').'</button></form>';
        if ($_SERVER['REQUEST_METHOD']==='POST' && $action==='previewnumbering') {
            print '<div class="warning">'.$langs->trans('NorwayNumberingConfirmHelp').' '.(int)$preview['issued_count'].' '.$langs->trans('NorwayNumberingExisting').'</div>';
            $nonce=bin2hex(random_bytes(16));
            $_SESSION['norway_numbering']=array('entity'=>(int)$conf->entity,'user'=>(int)$user->id,'expires'=>time()+600,'nonce'=>$nonce,'input'=>$input,'fingerprint'=>$preview['fingerprint']);
            print '<form method="post"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="savenumbering"><input type="hidden" name="preview_nonce" value="'.$nonce.'"><p><label><input type="checkbox" name="confirmed" value="1" required> '.$langs->trans('NorwayNumberingConfirm').'</label></p><button class="button" type="submit">'.$langs->trans('NorwayNumberingSave').'</button></form>';
        }
    }
} catch (Throwable $error) { print '<div class="error">'.$langs->trans('NorwayNumberingFailed').'</div>'; }
print '<p><a href="'.dol_buildpath('/norway/admin/documents.php',1).'">'.$langs->trans('NorwayDocuments').'</a></p>';
print '<p><a href="'.dol_buildpath('/norway/admin/wizard.php',1).'?step=7">'.$langs->trans('NorwayWizard').'</a></p></div>';
llxFooter(); $db->close();
