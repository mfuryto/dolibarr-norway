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
        $this->version = '0.18.0';
        $this->editor_name = 'dolibarr-norway contributors';
        $this->editor_url = 'https://github.com/mfuryto/dolibarr-norway';
        $this->const_name = 'MAIN_MODULE_NORWAY';
        $this->picto = 'accountancy';
        $this->module_parts = array('moduleforexternal' => 0, 'triggers' => 1, 'css' => array('/norway/css/layout-0.4.0.css'), 'hooks' => array('main', 'index', 'pdfgeneration', 'admincompany'));
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
        $this->menu = array();
        $this->tabs = array();
    }

    public function init($options = '')
    {
        if (DOL_VERSION !== '24.0.1') {
            $this->error = 'Norway 0.18.0 supports Dolibarr 24.0.1 only.';
            return -1;
        }
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
