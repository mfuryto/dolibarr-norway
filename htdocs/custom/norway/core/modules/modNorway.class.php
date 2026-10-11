<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/** External localisation foundation. No accounting changes at activation. */
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
        $this->version = '0.1.0';
        $this->editor_name = 'dolibarr-norway contributors';
        $this->editor_url = 'https://github.com/mfuryto/dolibarr-norway';
        $this->const_name = 'MAIN_MODULE_NORWAY';
        $this->picto = 'accountancy';
        $this->module_parts = array('moduleforexternal' => 0);
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
            $this->error = 'Norway 0.1.0 supports Dolibarr 24.0.1 only.';
            return -1;
        }
        return $this->_init(array(), $options);
    }

    public function remove($options = '')
    {
        return $this->_remove(array(), $options);
    }
}
