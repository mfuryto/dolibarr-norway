<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require_once __DIR__.'/NorwayEhfTransport.php';
/** A test send choice on the native invoice card; no additional workflow/menu. */
class NorwayEhfInvoice
{
    public static function render($db,$invoice,$user,$langs)
    {
        if (!NorwayEhfTransport::enabled() || !$invoice || empty($invoice->id) || !empty($user->socid) || !$user->hasRight('facture','lire') || !$user->hasRight('facture','creer') || !in_array((int)$invoice->statut,array(1,2),true) || !in_array((int)$invoice->type,array(0,2),true)) { return ''; }
        $langs->load('norway@norway');$service=new NorwayEhfTransport($db);
        try { $state=$service->state($invoice->id); }
        catch (Throwable $error) { return '<p class="warning">'.dol_escape_htmltag($langs->trans('NorwayEhfStorage')).'</p>'; }
        $html='<p class="warning">'.dol_escape_htmltag($langs->trans('NorwayEhfTestOnly')).'</p><p>'.dol_escape_htmltag($langs->trans('NorwayEhfState_'.$state['status'])).'</p>';
        if (!empty($state['reason'])) { $html.='<p>'.dol_escape_htmltag($state['reason']).'</p>'; }
        $action=in_array($state['status'],array('ready','rejected'),true)?'norway_ehf_send':(!empty($state['id'])?'norway_ehf_status':'');
        if ($action!=='') {
            $html.='<form method="post" action="'.DOL_URL_ROOT.'/compta/facture/card.php?facid='.(int)$invoice->id.'"><input type="hidden" name="token" value="'.newToken().'">'
                .'<input type="hidden" name="action" value="'.$action.'"><button class="button" type="submit">'.dol_escape_htmltag($langs->trans($action==='norway_ehf_send'?'NorwayEhfSendTest':'NorwayEhfRefresh')).'</button></form>';
        }
        return '<div class="norway-page">'.$html.'</div>';
    }

    public static function action($db,$invoice,$action,$langs)
    {
        if (!in_array($action,array('norway_ehf_send','norway_ehf_status'),true)) { return false; }
        $langs->load('norway@norway');
        try {
            if ($_SERVER['REQUEST_METHOD']!=='POST' || !hash_equals((string)currentToken(),(string)GETPOST('token','alphanohtml')) || !GETPOST('token','alphanohtml')) { throw new RuntimeException('NorwayEhfAccess'); }
            $service=new NorwayEhfTransport($db);$state=$action==='norway_ehf_send'?$service->send((int)$invoice->id):$service->refresh((int)$invoice->id);
            setEventMessages($langs->trans('NorwayEhfState_'.$state['status']),null,in_array($state['status'],array('unknown','rejected','failed'),true)?'warnings':'mesgs');
        } catch (Throwable $error) {
            $key=$error->getMessage();if (!preg_match('/^NorwayEhf[A-Za-z]+$/D',$key)) { $key='NorwayEhfStorage'; }
            setEventMessages($langs->trans($key),null,'errors');
        }
        return true;
    }
}
