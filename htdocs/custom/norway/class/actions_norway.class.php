<?php
// SPDX-License-Identifier: GPL-3.0-or-later
class ActionsNorway
{
    public $results = array();
    public $resprints = '';
    public $error = '';

    public function __construct($db) {}

    private $renderingVat = false;

    /** Core default detection ignores sale/purchase type; keep its widget and fix only an unselected domestic default. */
    public function load_tva($parameters, &$object, &$action, $hookmanager)
    {
        global $db,$conf;
        $this->resprints='';
        $selected=(string)($parameters['selectedrate'] ?? '');$type=(int)($parameters['type_vat'] ?? 0);
        $seller=$parameters['seller'] ?? null;$buyer=$parameters['buyer'] ?? null;
        $script=str_replace('\\','/',$_SERVER['SCRIPT_FILENAME'] ?? '');
        $productForm=$type===0 && in_array($script,array(DOL_DOCUMENT_ROOT.'/product/card.php',DOL_DOCUMENT_ROOT.'/product/price.php'),true)
            && preg_match('/^tva_tx(?:\[|$)/D',$parameters['htmlname'] ?? '')===1;
        $productDefault=$productForm && $script===DOL_DOCUMENT_ROOT.'/product/card.php'
            && GETPOST('action','aZ09')==='create' && !GETPOSTISSET('tva_tx');
        if($productForm){$type=1;}
        $buyerForDefault=is_object($buyer)?$buyer:($productForm?$seller:null);
        if($this->renderingVat || !isModEnabled('norway') || !in_array($type,array(1,2),true) || !empty($parameters['is_npr'])
            || !is_object($seller) || !is_object($buyerForDefault) || ($seller->country_code ?? '')!=='NO' || ($buyerForDefault->country_code ?? '')!=='NO'){return 0;}
        $unselected=in_array($selected,array('','-1'),true);
        if(!$unselected && !$productForm){return 0;}
        // Preserve a saved or explicitly posted incoming code on an existing product for manual review.
        if($productForm && preg_match('/\(([^)]+)\)/',$selected,$chosen)
            && !in_array($chosen[1],array('3','31','33'),true)
            && (!$productDefault || !in_array($chosen[1],array('1','11','13'),true))){return 0;}
        $default=$selected;
        if($unselected || $productDefault){
            $native=$unselected?(string)get_default_tva($seller,$buyerForDefault,(int)($parameters['idprod'] ?? 0)):$selected;
            if(preg_match('/^(25|15|12)(?:\.0+)?(?:\s*\((1|11|13|3|31|33)\))?$/D',$native,$match)){
                require_once __DIR__.'/NorwayVatRates.php';
                try{$mapped=NorwayVatRates::defaultCode($db,(int)$conf->entity,(int)$match[1],$type);}catch(Throwable $error){return 0;}
                if($mapped===null){return 0;}$default=$mapped;
            }elseif(!$productForm || preg_match('/[(*]/',$native)){return 0;}
        }
        $this->renderingVat=true;
        try {
            $form=new Form($db);
            $html=$form->load_tva($parameters['htmlname'],$default,$seller,$buyer,(int)($parameters['idprod'] ?? 0),
                0,$parameters['type'] ?? '',$parameters['options_only'] ?? false,(int)($parameters['mode'] ?? 0),$type);
        } finally {$this->renderingVat=false;}
        $this->resprints=$html;return 1;
    }

    private function companyPage()
    {
        global $user;
        return !empty($user->admin) && substr(str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME'] ?? ''), -18) === '/admin/company.php';
    }

    private function bookkeepingPage()
    {
        return ($_SERVER['SCRIPT_FILENAME']??'')===DOL_DOCUMENT_ROOT.'/accountancy/bookkeeping/card.php'
            && GETPOST('mode','aZ09')==='';
    }

    public function printCommonFooter($parameters, &$object, &$action, $hookmanager)
    {
        global $db,$conf,$user,$langs;
        $this->resprints='';
        if(!$this->bookkeepingPage() || !GETPOSTINT('piece_num')){return 0;}
        require_once __DIR__.'/NorwayManualVat.php';
        if(!NorwayManualVat::allowed($user)){return 0;}
        try{$this->resprints=NorwayManualVat::render($db,(int)$conf->entity,GETPOSTINT('piece_num'),$user,$langs);}
        catch(Throwable $error){$langs->load('norway@norway');$this->resprints='<div class="error">'.dol_escape_htmltag($langs->trans('NorwayManualVatDatabase')).'</div>';}
        return 0;
    }

    public function doActions($parameters, &$object, &$action, $hookmanager)
    {
        global $db, $langs, $conf, $user;
        if($this->bookkeepingPage() && $action==='norway_save_manual_vat'){
            require_once __DIR__.'/NorwayManualVat.php';$langs->load('norway@norway');
            try {
                // Also enforce the native session token if the global CSRF option was disabled.
                if(($_SERVER['REQUEST_METHOD']??'')!=='POST' || empty($_SESSION['token']) || !hash_equals($_SESSION['token'],GETPOST('token','alphanohtml'))){throw new RuntimeException('NorwayManualVatAccess');}
                NorwayManualVat::save($db,(int)$conf->entity,GETPOSTINT('piece_num'),GETPOSTINT('norway_vat_line'),
                    GETPOST('norway_vat_code','alphanohtml'),GETPOST('norway_vat_base','alphanohtml'),GETPOST('norway_vat_token','alphanohtml'),$user);
                setEventMessages($langs->trans('RecordSaved'),null,'mesgs');
            } catch(Throwable $error){
                $key=strpos($error->getMessage(),'NorwayManualVat')===0?$error->getMessage():'NorwayManualVatDatabase';
                setEventMessages($langs->trans($key),null,'errors');
            }
            $action='';return 1;
        }
        if (!$this->companyPage() || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
            || !in_array($action, array('update', 'updateedit'), true) || GETPOST('cancel', 'alpha')
            || GETPOST('optiontva', 'aZ09') !== '1' || trim(GETPOST('tva', 'alphanohtml')) !== '') { return 0; }
        $country = getCountry(GETPOSTINT('country_id'), 'all', $db, $langs, 0);
        if (($country['code'] ?? '') !== 'NO') { return 0; }
        require_once __DIR__.'/NorwayReadiness.php';
        $number = GETPOST('siren', 'alphanohtml');
        if (!NorwayReadiness::validOrganisationNumber($number)) {
            $langs->load('norway@norway');
            $this->error = $langs->trans('NorwayVatAutoInvalid');
            $action = ''; // Core does not stop its save handler for a negative hook result alone.
            return -1;
        }
        // Core saves this value in its existing transaction; no independent database write.
        $_POST['tva'] = preg_replace('/[ \t]/', '', $number).'MVA';
        return 0;
    }

    public function addOpenElementsDashboardGroup($parameters, &$object, &$action, $hookmanager)
    {
        $this->results = array();
        if (isset($parameters['dashboardgroup']['bank_account'])) {
            $bank = $parameters['dashboardgroup']['bank_account'];
            $bank['stats'] = array_values(array_filter($bank['stats'], static function ($stat) {
                return $stat !== 'chequereceipt';
            }));
            $this->results['bank_account'] = $bank;
        }
        return 0;
    }

    public function pdf_build_address($parameters, &$object, &$action, $hookmanager)
    {
        global $conf;
        $this->resprints = '';
        $source = $parameters['sourcecompany'] ?? null;
        if (($parameters['mode'] ?? '') !== 'source' || !is_object($source)
            || ($source->country_code ?? '') !== 'NO'
            || !in_array($object->element ?? '', array('facture', 'propal'), true)
            || (int) ($object->entity ?? 0) !== (int) $conf->entity) { return 0; }
        require_once __DIR__.'/NorwayReadiness.php';
        if (!NorwayReadiness::validOrganisationNumber($source->idprof1 ?? '')) { return 0; }
        $number = preg_replace('/[ \t]/', '', $source->idprof1);
        $number = substr($number, 0, 3).' '.substr($number, 3, 3).' '.substr($number, 6, 3);
        $outputlangs = $parameters['outputlangs'];
        $outputlangs->load('norway@norway');
        $this->resprints = $outputlangs->convToOutputCharset($outputlangs->transnoentities('NorwayOrganisationLabel').': '.$number
            .((int) ($source->tva_assuj ?? 0) === 1 ? ' MVA' : ''));
        // Append to the standard address through the core hook; no party fields change.
        return 0;
    }

    public function updateSession($parameters, &$object, &$action, $hookmanager)
    {
        global $conf, $langs;
        // Keep the core dashboard/menu hidden even if its optional setting was changed.
        $conf->global->BANK_DISABLE_CHECK_DEPOSIT = '1';
        $path = str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME'] ?? '');
        if (strpos($path, '/compta/paiement/cheque/') !== false) {
            $langs->load('norway@norway');
            http_response_code(403);
            accessforbidden($langs->trans('NorwayNoCheques'));
        }
        return 0;
    }
}
