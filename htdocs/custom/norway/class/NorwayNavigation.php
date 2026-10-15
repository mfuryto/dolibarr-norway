<?php
// SPDX-License-Identifier: GPL-3.0-or-later
/** A single entry for Norwegian defaults under the native accounting settings. */
class NorwayNavigation
{
    public static function menus()
    {
        return array(array('fk_menu'=>'fk_mainmenu=accountancy,fk_leftmenu=accountancy_admin','mainmenu'=>'accountancy','leftmenu'=>'norway_accounting','type'=>'left',
            'titre'=>'NorwayAccountingSettings','langs'=>'norway@norway','url'=>'/norway/admin/accounting.php?mainmenu=accountancy&leftmenu=accountancy_admin_norway',
            'position'=>180,'enabled'=>'isModEnabled("norway") && isModEnabled("accounting") && ($leftmenu=="accountancy_admin" || $leftmenu=="accountancy_admin_norway" || $leftmenu=="all")',
            'perms'=>'!$user->socid && $user->admin','target'=>'','user'=>0));
    }
}
