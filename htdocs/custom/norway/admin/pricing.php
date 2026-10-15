<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require '../../../main.inc.php';
if (!empty($user->socid) || !$user->admin || !isModEnabled('norway') || !isModEnabled('propal') || !$user->hasRight('propal','read')) { accessforbidden(); }
require_once __DIR__.'/../class/NorwayIndicativeEuro.php';
$langs->load('norway@norway');
$nok = GETPOST('nok', 'alphanohtml');
llxHeader('', $langs->trans('NorwayPricing'));
print '<div class="norway-page">';
print load_fiche_titre($langs->trans('NorwayPricing'), '', 'accountancy');
print '<p>'.$langs->trans('NorwayPricingScope').'</p>';
print '<form method="get"><label>'.$langs->trans('NorwayPricingNok').' <input type="number" name="nok" min="0" max="1000000000" step="0.01" required value="'.dol_escape_htmltag($nok).'"></label> ';
print '<input type="submit" class="button" value="'.$langs->trans('NorwayPricingCalculate').'"></form>';
if ($nok !== '') {
    try {
        NorwayIndicativeEuro::estimate($nok, 1); // Validate before contacting the rate service.
        $rate = NorwayIndicativeEuro::fetch();
        $eur = NorwayIndicativeEuro::estimate($nok, $rate['nok_per_eur']);
        print '<p>'.dol_escape_htmltag($langs->trans('NorwayPricingResult', number_format($eur, 2, '.', ' '), $rate['date'])).'</p>';
        print '<p>1 EUR = '.dol_escape_htmltag((string) $rate['nok_per_eur']).' NOK · <a href="https://www.norges-bank.no/tema/Statistikk/Valutakurser/">Norges Bank</a></p>';
    } catch (InvalidArgumentException $e) {
        print '<div class="error">'.$langs->trans('NorwayPricingInvalid').'</div>';
    } catch (RuntimeException $e) {
        print '<div class="warning">'.$langs->trans('NorwayPricingUnavailable').'</div>';
    }
}
print '</div>';
llxFooter(); $db->close();
