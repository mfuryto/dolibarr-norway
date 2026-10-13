<?php
// SPDX-License-Identifier: GPL-3.0-or-later
define('CSRFCHECK_WITH_TOKEN',1);
require '../../../main.inc.php';
if (GETPOST('errorcode','aZ09')==='InvalidToken' || ($_SERVER['REQUEST_METHOD'] ?? '')!=='POST') { httponly_accessforbidden('Invalid request'); }
require_once __DIR__.'/../class/NorwayPurchaseDeduction.php';
$langs->load('norway@norway');header('Cache-Control: no-store');
$id=GETPOSTINT('id');$action=GETPOST('action','aZ09');$key='norway_purchase_deduction';
try {
    if ($action==='preview') {
        unset($_SESSION[$key]);
        $profile=GETPOST('profile','aZ09');$percentage=GETPOST('percentage','alphanohtml');$reason=trim(GETPOST('reason','restricthtml'));
        $preview=NorwayPurchaseDeduction::preview($db,(int)$conf->entity,$id,$user,$profile,$percentage,$reason);
        $_SESSION[$key]=array('entity'=>(int)$conf->entity,'id'=>$id,'user'=>(int)$user->id,'expires'=>time()+600,'nonce'=>bin2hex(random_bytes(16)),'preview'=>$preview);
    } elseif ($action==='save') {
        $pending=$_SESSION[$key] ?? null;unset($_SESSION[$key]);
        if (!$pending || $pending['entity']!==(int)$conf->entity || $pending['user']!==(int)$user->id || $pending['id']!==$id || $pending['expires']<time()
            || GETPOST('confirmed','aZ09')!=='1' || !hash_equals($pending['nonce'],GETPOST('nonce','aZ09'))) { throw new InvalidArgumentException('Purchase confirmation'); }
        $preview=$pending['preview'];$choice=$preview['choice'];
        NorwayPurchaseDeduction::save($db,(int)$conf->entity,$id,$user,$choice['profile'],$choice['percentage'],$choice['reason'],$preview['fingerprint']);
        setEventMessages($langs->trans('NorwayDeductionSaved'),null,'mesgs');
    } else { throw new InvalidArgumentException('Purchase action'); }
} catch (Throwable $error) { unset($_SESSION[$key]);setEventMessages($langs->trans('NorwayDeductionFailed'),null,'errors'); }
header('Location: '.DOL_URL_ROOT.'/fourn/facture/card.php?facid='.$id.'#norway-purchase-allocation');exit;
