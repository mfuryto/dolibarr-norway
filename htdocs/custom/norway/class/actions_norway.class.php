<?php
// SPDX-License-Identifier: GPL-3.0-or-later
class ActionsNorway
{
    public $results = array();

    public function __construct($db) {}

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
