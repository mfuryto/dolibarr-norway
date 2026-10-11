<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require '../../../main.inc.php';
if (!$user->admin || !isModEnabled('norway') || !$user->hasRight('facture', 'lire')) { accessforbidden(); }
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once __DIR__.'/../class/NorwayInvoiceCheck.php';
$langs->load('norway@norway');
$id = GETPOSTINT('id');
llxHeader('', $langs->trans('NorwayInvoiceCheck'));
print load_fiche_titre($langs->trans('NorwayInvoiceCheck'), '', 'bill');
print '<p>'.$langs->trans('NorwayInvoiceScope').'</p>';
print '<form method="get" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
print '<label>'.$langs->trans('NorwayInvoiceID').' <input type="number" min="1" name="id" value="'.($id > 0 ? $id : '').'"></label> ';
print '<input class="button" type="submit" value="'.$langs->trans('NorwayInvoiceRun').'"></form>';
if (!GETPOSTISSET('id')) {
    $recent = $db->query('SELECT rowid, ref FROM '.MAIN_DB_PREFIX.'facture WHERE entity='.(int) $conf->entity.' ORDER BY rowid DESC LIMIT 20');
    if (!$recent) {
        print '<div class="error">'.$langs->trans('NorwayInvoiceNotFound').'</div>';
    } else {
        print '<h2>'.$langs->trans('NorwayInvoiceRecent').'</h2><ul>';
        while ($row = $db->fetch_object($recent)) {
            print '<li><a href="'.dol_buildpath('/norway/admin/invoice.php', 1).'?id='.(int) $row->rowid.'">'.dol_escape_htmltag($row->ref).'</a></li>';
        }
        print '</ul>';
    }
}
if (GETPOSTISSET('id')) {
    // Scope before fetching: do not expose references or parties from another entity.
    $res = $id > 0 ? $db->query('SELECT rowid FROM '.MAIN_DB_PREFIX.'facture WHERE rowid='.(int) $id.' AND entity='.(int) $conf->entity) : false;
    $invoice = new Facture($db);
    $customer = new Societe($db);
    if (!$res || !$db->num_rows($res) || $invoice->fetch($id) <= 0 || $customer->fetch($invoice->socid) <= 0) {
        print '<div class="error">'.$langs->trans('NorwayInvoiceNotFound').'</div>';
    } else {
        $source = null;
        if ((int) $invoice->type === 2 && $invoice->fk_facture_source > 0) {
            $res = $db->query('SELECT rowid FROM '.MAIN_DB_PREFIX.'facture WHERE rowid='.(int) $invoice->fk_facture_source.' AND entity='.(int) $conf->entity);
            if ($res && $db->num_rows($res)) {
                $candidate = new Facture($db);
                if ($candidate->fetch($invoice->fk_facture_source) > 0) { $source = $candidate; }
            }
        }
        print '<p><a href="'.DOL_URL_ROOT.'/compta/facture/card.php?facid='.(int) $invoice->id.'">'.dol_escape_htmltag($invoice->ref).'</a></p>';
        print '<table class="noborder centpercent">';
        foreach (NorwayInvoiceCheck::evaluate($invoice, $mysoc, $customer, $source) as $key => $status) {
            print '<tr><td>'.$langs->trans('NorwayInvoice'.$key).'</td><td>'.$langs->trans('NorwayInvoice'.$status).'</td></tr>';
        }
        print '</table>';
    }
}
llxFooter();
$db->close();
