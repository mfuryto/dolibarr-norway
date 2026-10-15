<?php
// SPDX-License-Identifier: GPL-3.0-or-later
/** Core menu extensions; no parallel dashboard or accounting workflow. */
class NorwayNavigation
{
    public static function canReadLedger($user)
    {
        return empty($user->socid) && isModEnabled('norway') && isModEnabled('accounting')
            && $user->hasRight('accounting', 'mouvements', 'lire');
    }

    public static function menus()
    {
        $read = '!$user->socid && $user->hasRight("accounting","mouvements","lire")';
        $admin = '!$user->socid && $user->admin';
        $sales = $admin.' && $user->hasRight("facture","lire")';
        $specs = array(
            array('accountancy', 'accountancy', 'audit', 'NorwayAudit', $read),
            array('accountancy', 'accountancy', 'periods', 'NorwayPeriods', $read),
            array('accountancy', 'accountancy_admin', 'accounting', 'NorwayAccountingSettings', $admin),
            array('accountancy', 'norway_accounting', 'registration', 'NorwayMenuRegistration', $admin),
            array('accountancy', 'norway_accounting', 'taxmappings', 'NorwayMenuMappings', $admin),
            array('accountancy', 'norway_accounting', 'vat', 'NorwayMenuVat', $admin),
            array('accountancy', 'norway_accounting', 'productaccounts', 'NorwayProductAccountsTitle', $admin.' && $user->hasRight("accounting","chartofaccount")'),
            array('commercial', 'propals', 'pricing', 'NorwayPricing', $admin.' && $user->hasRight("propal","read")'),
            array('billing', 'customers_bills', 'invoiceperiod', 'NorwayBatch', $sales),
        );
        $menus = array();
        foreach ($specs as $i => $spec) {
            list($main, $parent, $page, $title, $perms) = $spec;
            $menus[] = array(
                'fk_menu' => 'fk_mainmenu='.$main.($parent ? ',fk_leftmenu='.$parent : ''),
                'mainmenu' => $main, 'leftmenu' => 'norway_'.$page, 'type' => 'left',
                'titre' => $title, 'langs' => 'norway@norway',
                'url' => '/norway/admin/'.$page.'.php?mainmenu='.$main.'&leftmenu='
                    .($parent === 'accountancy' ? 'accountancy_accountancy'
                        : (in_array($parent,array('accountancy_admin','norway_accounting'),true) ? 'accountancy_admin_norway' : ($parent ?: 'norway_'.$page))),
                'position' => 180 + $i, 'enabled' => 'isModEnabled("norway") && isModEnabled("accounting")'
                    .($main === 'billing' ? ' && isModEnabled("invoice")' : '')
                    .($parent === 'accountancy' ? ' && ($leftmenu=="accountancy_accountancy" || $leftmenu=="accountancy_accountancy_closure" || $leftmenu=="accountancy_accountancy_report" || $leftmenu=="all")' : '')
                    .($parent === 'accountancy_admin' ? ' && ($leftmenu=="accountancy_admin" || $leftmenu=="accountancy_admin_norway" || $leftmenu=="all")' : '')
                    .($parent === 'norway_accounting' ? ' && ($leftmenu=="accountancy_admin_norway" || $leftmenu=="all")' : '')
                    .($page === 'productaccounts' ? ' && (isModEnabled("product") || isModEnabled("service"))' : '')
                    .($main === 'commercial' ? ' && isModEnabled("propal")' : ''),
                'perms' => $perms, 'target' => '', 'user' => 0,
            );
        }
        return $menus;
    }
}
