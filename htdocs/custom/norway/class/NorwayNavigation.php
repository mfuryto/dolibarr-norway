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
            array('accountancy', 'accountancy_admin', 'accounting', 'NorwayAccountingSetup', $admin),
            array('accountancy', 'accountancy_admin', 'registration', 'NorwayRegistration', $admin),
            array('accountancy', 'accountancy_admin', 'taxmappings', 'NorwayMappingsTitle', $admin),
            array('billing', 'customers_bills', 'invoiceperiod', 'NorwayBatch', $sales),
            array('billing', 'customers_bills', 'numbering', 'NorwayNumberingTitle', $sales),
            array('billing', 'customers_bills', 'documents', 'NorwayDocuments', $sales),
            array('bank', '', 'bankdefaults', 'NorwayBankDefaults', $admin.' && $user->hasRight("banque","lire")'),
        );
        $menus = array();
        foreach ($specs as $i => $spec) {
            list($main, $parent, $page, $title, $perms) = $spec;
            $menus[] = array(
                'fk_menu' => 'fk_mainmenu='.$main.($parent ? ',fk_leftmenu='.$parent : ''),
                'mainmenu' => $main, 'leftmenu' => 'norway_'.$page, 'type' => 'left',
                'titre' => $title, 'langs' => 'norway@norway',
                'url' => '/norway/admin/'.$page.'.php?mainmenu='.$main.'&leftmenu='
                    .($parent === 'accountancy' ? 'accountancy_accountancy' : ($parent ?: 'norway_'.$page)),
                'position' => 180 + $i, 'enabled' => 'isModEnabled("norway") && isModEnabled("accounting")'
                    .($main === 'billing' ? ' && isModEnabled("invoice")' : '')
                    .($main === 'bank' ? ' && isModEnabled("bank")' : ''),
                'perms' => $perms, 'target' => '', 'user' => 0,
            );
        }
        return $menus;
    }
}
