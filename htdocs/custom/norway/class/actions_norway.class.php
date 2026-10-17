<?php
// SPDX-License-Identifier: GPL-3.0-or-later
class ActionsNorway
{
    public $results = array();
    public $resprints = '';
    public $error = '';

    public function __construct($db) {}

    private $renderingVat = false;

    /** Reuse the native widget for directional domestic defaults and Norwegian reverse-charge choices. */
    public function load_tva($parameters, &$object, &$action, $hookmanager)
    {
        global $db,$conf;
        $this->resprints='';
        $selected=(string)($parameters['selectedrate'] ?? '');$type=(int)($parameters['type_vat'] ?? 0);
        $seller=$parameters['seller'] ?? null;$buyer=$parameters['buyer'] ?? null;
        $script=str_replace('\\','/',$_SERVER['SCRIPT_FILENAME'] ?? '');
        // Core searches the foreign seller's rates; the purchaser's reverse-charge code is Norwegian.
        $invoice=$GLOBALS['object']??null;
        if(!$this->renderingVat && isModEnabled('norway') && $type===2 && (int)($invoice->vat_reverse_charge??0)===1
            && ($invoice->element??'')==='invoice_supplier' && (int)($invoice->entity??0)===(int)$conf->entity
            && $script===DOL_DOCUMENT_ROOT.'/fourn/facture/card.php' && is_object($seller) && is_object($buyer)
            && ($buyer->country_code??'')==='NO' && preg_match('/^[A-Z]{2}$/D',(string)($seller->country_code??'')) && $seller->country_code!=='NO'
            && empty($parameters['is_npr']) && (int)($parameters['mode']??0)>0){
            $norwegianSeller=clone $seller;$norwegianSeller->country_code='NO';$norwegianSeller->country_id=$buyer->country_id;
            $this->renderingVat=true;
            try{$form=new Form($db);$this->resprints=$form->load_tva($parameters['htmlname'],in_array($selected,array('','-1'),true)?'0':$selected,
                $norwegianSeller,$buyer,(int)($parameters['idprod']??0),0,$parameters['type']??'',$parameters['options_only']??false,(int)$parameters['mode'],2);}
            finally{$this->renderingVat=false;}
            // If all ordinary zero choices were removed, the browser must not default to a reverse-charge code.
            if(in_array($selected,array('','-1'),true) && preg_match('/<option\b[^>]*value="0(?:\.0+)? \((?:86|87)\)"[^>]*\sselected(?=[\s>])[^>]*>/',$this->resprints,$match)){
                $this->resprints=str_replace($match[0],preg_replace('/\sselected(?=[\s>])/','',$match[0]),$this->resprints);
                $plain='<option value="0" selected>0%</option>';
                $this->resprints=!empty($parameters['options_only'])?$plain.$this->resprints:preg_replace('/(<select\b[^>]*>)/','$1'.$plain,$this->resprints,1);
            }return 1;
        }
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

    private function purchaseJournal()
    {
        return ($_SERVER['SCRIPT_FILENAME']??'')===DOL_DOCUMENT_ROOT.'/accountancy/journal/purchasesjournal.php';
    }
    public function printFieldListSelect($parameters,&$object,&$action,$hookmanager)
    {
        $this->resprints='';if(!$this->purchaseJournal()){return 0;}
        require_once __DIR__.'/NorwayPurchaseVat.php';$this->resprints=NorwayPurchaseVat::selectSql();return 0;
    }
    public function printFieldListFrom($parameters,&$object,&$action,$hookmanager)
    {
        global $db;$this->resprints='';if(!$this->purchaseJournal()){return 0;}
        require_once __DIR__.'/NorwayPurchaseVat.php';$this->resprints=NorwayPurchaseVat::fromSql($db);return 0;
    }
    private function invoicePage()
    {
        return ($_SERVER['SCRIPT_FILENAME']??'')===DOL_DOCUMENT_ROOT.'/compta/facture/card.php';
    }

    public function formObjectOptions($parameters,&$object,&$action,$hookmanager)
    {
        global $user,$langs;
        $this->resprints='';
        if(!$this->invoicePage() || !empty($user->socid) || !$user->hasRight('facture','creer') || $action!=='create'){return 0;}
        require_once __DIR__.'/NorwayCreditReference.php';
        $this->resprints='<tr id="norway-original-reference"><td colspan="4">'.NorwayCreditReference::field(GETPOST('norway_original_invoice_ref','alphanohtml'),$langs).'</td></tr>'
            .'<script>jQuery(function(){document.getElementById("norway-original-reference").closest("table").classList.add("norway-invoice-create");function originalReference(){var t=document.querySelector("input[name=type]:checked"),o=document.querySelector("select[name=fac_avoir]");document.getElementById("norway-original-reference").hidden=!(t&&t.value==="2"&&(!o||parseInt(o.value,10)<=0));}jQuery("input[name=type],select[name=fac_avoir]").on("change",originalReference);originalReference();});</script>';
        return 0;
    }

    public function printUnderHeaderPDFline($parameters,&$model,&$action,$hookmanager)
    {
        global $conf;
        $this->results=array();$this->resprints='';$invoice=$parameters['object']??null;
        require_once __DIR__.'/NorwayCreditReference.php';
        if(!is_object($invoice) || (int)($invoice->entity??0)!==(int)$conf->entity || !NorwayCreditReference::external($invoice)){return 0;}
        if(!isset($model->tab_top,$model->page_largeur,$model->marge_gauche,$model->marge_droite)){return 0;}
        $pdf=$parameters['pdf'];$lang=$parameters['outputlangs'];$lang->load('norway@norway');
        $top=$model->tab_top;
        $pdf->SetXY($model->marge_gauche,$top);
        $pdf->MultiCell($model->page_largeur-$model->marge_gauche-$model->marge_droite,4,
            $lang->convToOutputCharset($lang->transnoentities('NorwayCreditReferencePdf').': '.$invoice->ref_ext),0,'L');
        $this->results['extra_under_address_shift']=max(8,$pdf->GetY()-$top+3);
        return 0;
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
        $invoice=$GLOBALS['object']??null;
        if($this->invoicePage() && is_object($invoice) && !empty($invoice->id) && (int)($invoice->entity??0)===(int)$conf->entity
            && (int)($invoice->type??0)===2 && (int)($invoice->fk_facture_source??0)<=0 && empty($user->socid) && $user->hasRight('facture','creer')){
            require_once __DIR__.'/NorwayCreditReference.php';$langs->load('norway@norway');
            if((int)$invoice->statut===0){
                $this->resprints='<div class="fichecenter"><form method="post" action="'.DOL_URL_ROOT.'/compta/facture/card.php?facid='.(int)$invoice->id.'">'
                    .'<input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="norway_credit_reference">'
                    .NorwayCreditReference::field($invoice->ref_ext??'',$langs).'<button class="button" type="submit">'.$langs->trans('Save').'</button></form></div>';
            }elseif(NorwayCreditReference::external($invoice)){$this->resprints='<p>'.dol_escape_htmltag($langs->trans('NorwayCreditReferencePdf').': '.$invoice->ref_ext).'</p>';}
            return 0;
        }
        if(!$this->bookkeepingPage() || !GETPOSTINT('piece_num')){return 0;}
        require_once __DIR__.'/NorwayManualVat.php';
        if(!NorwayManualVat::allowed($user)){return 0;}
        try{$this->resprints=NorwayManualVat::render($db,(int)$conf->entity,GETPOSTINT('piece_num'),$user,$langs);}
        catch(Throwable $error){$langs->load('norway@norway');$this->resprints='<div class="error">'.dol_escape_htmltag($langs->trans('NorwayManualVatDatabase')).'</div>';}
        return 0;
    }

    public function bookkeepingAddLine($parameters,&$object,&$action,$hookmanager)
    {
        global $langs;$this->resprints='';
        if(!$this->bookkeepingPage() || $action!=='create' || GETPOST('doc_type','aZ09')!=='vat_settlement'){return 0;}
        $langs->load('norway@norway');
        $this->resprints='<tr><td>'.dol_escape_htmltag($langs->trans('Type')).'</td><td><input type="hidden" name="doc_type" value="vat_settlement">'.dol_escape_htmltag($langs->trans('NorwayVatSettlementType')).'</td></tr>';
        print $this->resprints; $this->resprints='';return 0;
    }
    public function doActions($parameters, &$object, &$action, $hookmanager)
    {
        global $db, $langs, $conf, $user;
        if(($_SERVER['SCRIPT_FILENAME']??'')===DOL_DOCUMENT_ROOT.'/accountancy/bookkeeping/card.php' && GETPOST('mode','aZ09')==='_tmp' && $action==='valid'){
            // Core transformTransaction filters out closed rows before deleting the draft.
            // Check the whole draft first so no rows can disappear on a closed period.
            require_once DOL_DOCUMENT_ROOT.'/accountancy/class/bookkeeping.class.php';
            require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
            require_once __DIR__.'/NorwayVatPeriod.php';require_once __DIR__.'/NorwayVatReport.php';
            $native=new BookKeeping($db);$rows=array();
            $r=$db->query('SELECT * FROM '.$db->prefix().'accounting_bookkeeping_tmp WHERE entity='.(int)$conf->entity.' AND piece_num='.GETPOSTINT('piece_num'));
            $blocked=!$r;if($r){while($b=$db->fetch_object($r)){$rows[]=$b;if($native->validBookkeepingDate($db->jdate($b->doc_date))!==1){$blocked=true;}}}
            if(array_filter($rows,static fn($b)=>$b->doc_type==='vat_settlement') && !NorwayVatPeriod::settlement($rows,NorwayVatLedger::taxAccounts($db,(int)$conf->entity))){$blocked=true;}
            if($blocked){$langs->load('norway@norway');setEventMessages($langs->trans('NorwayVatPeriodBlocked'),null,'errors');$action='';return 1;}
        }
        if($this->invoicePage() && $action==='norway_credit_reference'){
            require_once __DIR__.'/NorwayCreditReference.php';$langs->load('norway@norway');
            try{
                if(($_SERVER['REQUEST_METHOD']??'')!=='POST' || empty($_SESSION['token']) || !hash_equals($_SESSION['token'],GETPOST('token','alphanohtml'))){throw new RuntimeException('NorwayCreditReferenceBlocked');}
                NorwayCreditReference::save($db,(int)$conf->entity,(int)$object->id,GETPOST('norway_original_invoice_ref','none'),$user);
                setEventMessages($langs->trans('RecordSaved'),null,'mesgs');$object->fetch($object->id);
            }catch(Throwable $error){setEventMessages($langs->trans('NorwayCreditReferenceBlocked'),null,'errors');}
            $action='';return 1;
        }
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
            .((int) ($source->tva_assuj ?? 0) === 1 ? ' MVA' : '')
            .(in_array((int)($source->forme_juridique_code??0),array(173002,173003),true)?"\nForetaksregisteret":''));
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
