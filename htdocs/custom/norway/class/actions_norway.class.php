<?php
// SPDX-License-Identifier: GPL-3.0-or-later
class ActionsNorway
{
    public $results = array();
    public $resprints = '';
    public $error = '';

    public function __construct($db) {}

    private function companyPage()
    {
        global $user;
        return !empty($user->admin) && substr(str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME'] ?? ''), -18) === '/admin/company.php';
    }

    public function doActions($parameters, &$object, &$action, $hookmanager)
    {
        global $db, $langs;
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

    public function tabContentViewSupplierInvoice($parameters, &$object, &$action, $hookmanager)
    {
        global $db,$conf,$user,$langs;
        if (!isModEnabled('norway') || substr(str_replace('\\','/',$_SERVER['SCRIPT_FILENAME'] ?? ''),-strlen('/fourn/facture/card.php'))!=='/fourn/facture/card.php') { return 0; }
        require_once __DIR__.'/NorwayPurchaseCard.php';
        // This core hook expects direct output. Return zero to retain the native card.
        print NorwayPurchaseCard::render($db,$object,$user,$langs,(int)$conf->entity,GETPOST('norway_purchase_profile','aZ09'));
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
