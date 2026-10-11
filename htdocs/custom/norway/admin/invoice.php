<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require '../../../main.inc.php';
if (!$user->admin || !isModEnabled('norway') || !$user->hasRight('facture', 'lire')) { accessforbidden(); }
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once __DIR__.'/../class/NorwayInvoiceCheck.php';
require_once __DIR__.'/../class/NorwayInvoiceAmounts.php';
require_once __DIR__.'/../class/NorwayVatRegistration.php';
$langs->load('norway@norway');
$id = GETPOSTINT('id');
llxHeader('', $langs->trans('NorwayInvoiceCheck'));
print '<div class="norway-page">';
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
        print '<div class="div-table-responsive"><table class="noborder centpercent">';
        foreach (NorwayInvoiceCheck::evaluate($invoice, $mysoc, $customer, $source) as $key => $status) {
            print '<tr><td>'.$langs->trans('NorwayInvoice'.$key).'</td><td>'.$langs->trans('NorwayInvoice'.$status).'</td></tr>';
        }
        print '</table></div>';
        $amounts = NorwayInvoiceAmounts::report($invoice);
        $formatAmount = static function ($value) { return $value === null ? '—' : price($value); };
        print '<h2>'.$langs->trans('NorwayAmounts').'</h2><p>'.$langs->trans('NorwayAmountsScope').'</p>';
        print '<div class="warning">'.$langs->trans('NorwayAmounts'.$amounts['status']).'</div>';
        print '<div class="div-table-responsive"><table class="noborder centpercent"><tr>';
        foreach (array('Line','Rate','Net','VAT','Review') as $column) { print '<th>'.$langs->trans('NorwayAmounts'.$column).'</th>'; }
        print '</tr>';
        foreach ($amounts['rows'] as $index => $line) {
            print '<tr><td>'.($index + 1).'</td><td>'.$formatAmount($line['rate']).'</td><td>'.$formatAmount($line['net']).'</td><td>'.$formatAmount($line['vat']).'</td><td>'.$langs->trans('NorwayAmounts'.$line['status']).'</td></tr>';
        }
        foreach (array('Sum'=>array($amounts['net'],$amounts['vat']), 'Header'=>array($amounts['header_net'],$amounts['header_vat'])) as $label=>$values) {
            print '<tr><td colspan="2">'.$langs->trans('NorwayAmounts'.$label).'</td><td>'.$formatAmount($values[0]).'</td><td>'.$formatAmount($values[1]).'</td><td></td></tr>';
        }
        print '</table></div>';
        try {
            $registration=NorwayVatRegistration::read($db,$conf->entity,$mysoc->idprof1);
            $result=NorwayVatRegistration::compare($registration,dol_print_date($invoice->date,'%Y-%m-%d'));
            print '<div class="warning">'.$langs->trans('NorwayRegistration'.$result).'</div>';
        } catch (Throwable $error) { print '<div class="warning">'.$langs->trans('NorwayRegistrationFailed').'</div>'; }
    }
}
print '</div>';
llxFooter();
$db->close();
