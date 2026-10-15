<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/** External localisation and sales policy without core modifications. */
class modNorway extends DolibarrModules
{
    public function __construct($db)
    {
        $this->db = $db;
        $this->numero = 510173; // Provisional local ID; reserve before distribution.
        $this->rights_class = 'norway';
        $this->family = 'financial';
        $this->module_position = '90';
        $this->name = 'Norway';
        $this->description = 'NorwayDescription';
        $this->version = '0.61.0';
        $this->editor_name = 'dolibarr-norway contributors';
        $this->editor_url = 'https://github.com/mfuryto/dolibarr-norway';
        $this->const_name = 'MAIN_MODULE_NORWAY';
        $this->picto = 'accountancy';
        $this->module_parts = array('moduleforexternal' => 0, 'models' => 1, 'societe' => 1, 'triggers' => 1, 'css' => array('/norway/css/layout-0.5.0.css'), 'hooks' => array('main', 'index', 'pdfgeneration', 'admincompany', 'invoicesuppliercard'));
        $this->config_page_url = array('setup.php@norway');
        $this->langfiles = array('norway@norway');
        $this->phpmin = array(8, 2);
        $this->need_dolibarr_version = array(24, 0, 1);
        $this->depends = array('modAccounting');
        $this->requiredby = array();
        $this->conflictwith = array();
        $this->dirs = array();
        $this->const = array();
        $this->rights = array();
        require_once __DIR__.'/../../class/NorwayNavigation.php';
        $this->menu = NorwayNavigation::menus();
        $invoiceAccess = '$user->admin && !$user->socid && isModEnabled("norway") && isModEnabled("accounting") && $user->hasRight("facture","lire") && $user->hasRight("accounting","mouvements","lire")';
        $this->tabs = array(
            'invoice:+norwaycontrol:NorwayInvoiceCheck:norway@norway:'.$invoiceAccess.':/norway/admin/invoice.php?id=__ID__',
            'invoice:+norwayevidence:NorwayEvidenceTitle:norway@norway:'.$invoiceAccess.':/norway/admin/evidence.php?id=__ID__',
            'product:+norwayaccounts:NorwayProductAccountsTitle:norway@norway:$user->admin && !$user->socid && isModEnabled("norway") && isModEnabled("accounting") && $user->hasRight("accounting","chartofaccount"):/norway/admin/productaccounts.php?id=__ID__',
        );
    }

    public function insert_tabs()
    {
        // Core insert_tabs ignores existing values; refresh our own definitions
        // inside _init's transaction so upgrades retain the current guards/URLs.
        global $conf;
        foreach ($this->tabs as $i => $tab) {
            if (dolibarr_set_const($this->db, $this->const_name.'_TABS_'.$i, $tab,
                'chaine', 0, '', $conf->entity) <= 0) {
                $this->error = 'Could not refresh Norwegian document tabs';
                return 1;
            }
        }
        return 0;
    }

    public function insert_menus()
    {
        // Core inserts menus without replacing existing entries during an upgrade.
        // _init owns the transaction: a failed replacement restores the old menus.
        $errors = $this->delete_menus();
        return $errors ? $errors : parent::insert_menus();
    }

    public function init($options = '')
    {
        if (DOL_VERSION !== '24.0.1') {
            $this->error = 'Norway 0.61.0 supports Dolibarr 24.0.1 only.';
            return -1;
        }
        require_once __DIR__.'/../../class/NorwaySchema.php';
        try { NorwaySchema::migrate($this->db); }
        catch (RuntimeException $e) { $this->error = $e->getMessage(); return -1; }
        require_once __DIR__.'/../../class/NorwayPolicy.php';
        try { NorwayPolicy::apply($this->db, $GLOBALS['conf']); }
        catch (RuntimeException $e) { $this->error = $e->getMessage(); return -1; }
        // Core _init inserts module parts but does not update existing hook records.
        // Refresh our own registration explicitly when upgrading an enabled module.
        if (dolibarr_set_const($this->db, 'MAIN_MODULE_NORWAY_HOOKS', json_encode($this->module_parts['hooks']),
            'chaine', 0, '', $GLOBALS['conf']->entity) <= 0) {
            $this->error = 'Could not refresh Norwegian dashboard hooks';
            return -1;
        }
        if (dolibarr_set_const($this->db, 'MAIN_MODULE_NORWAY_CSS', json_encode($this->module_parts['css']),
            'chaine', 0, '', $GLOBALS['conf']->entity) <= 0) {
            $this->error = 'Could not refresh Norwegian layout styles';
            return -1;
        }
        return $this->_init(array(), $options);
    }

    public function remove($options = '')
    {
        return $this->_remove(array(), $options);
    }
}
