<?php
// SPDX-License-Identifier: GPL-3.0-or-later
/** Settings and user-requested reports follow the native accounting hierarchy. */
class NorwayNavigation
{
    public static function menus()
    {
        return array(array('fk_menu'=>'fk_mainmenu=accountancy,fk_leftmenu=accountancy_admin','mainmenu'=>'accountancy','leftmenu'=>'norway_accounting','type'=>'left',
            'titre'=>'NorwayAccountingSettings','langs'=>'norway@norway','url'=>'/norway/admin/accounting.php?mainmenu=accountancy&leftmenu=accountancy_admin_norway',
            'position'=>180,'enabled'=>'isModEnabled("norway") && isModEnabled("accounting") && ($leftmenu=="accountancy_admin" || $leftmenu=="accountancy_admin_norway" || $leftmenu=="all")',
            'perms'=>'!$user->socid && $user->admin','target'=>'','user'=>0),
            array('fk_menu'=>'fk_mainmenu=accountancy,fk_leftmenu=ca','mainmenu'=>'accountancy','leftmenu'=>'norway_vat_report','type'=>'left',
                'titre'=>'NorwayVatReportTitle','langs'=>'norway@norway','url'=>'/norway/vat.php?mainmenu=accountancy&leftmenu=accountancy_accountancy_report',
                'position'=>185,'enabled'=>'isModEnabled("norway") && isModEnabled("accounting") && ($leftmenu=="accountancy_accountancy_report" || $leftmenu=="all")',
                'perms'=>'!$user->socid && $user->hasRight("accounting","mouvements","lire") && $user->hasRight("accounting","comptarapport","lire")',
                'target'=>'','user'=>0),
            array('fk_menu'=>'fk_mainmenu=accountancy,fk_leftmenu=accountancy','mainmenu'=>'accountancy','leftmenu'=>'norway_balance','type'=>'left',
                'titre'=>'NorwayBalanceTitle','langs'=>'norway@norway','url'=>'/norway/balance.php?mainmenu=accountancy&leftmenu=accountancy_accountancy',
                'position'=>175,'enabled'=>'isModEnabled("norway") && isModEnabled("accounting") && ($leftmenu=="" || $leftmenu=="accountancy_accountancy" || $leftmenu=="accountancy_accountancy_report" || $leftmenu=="accountancy_accountancy_closure" || $leftmenu=="all")',
                'perms'=>'!$user->socid && $user->hasRight("accounting","mouvements","lire")','target'=>'','user'=>0));
    }
}
