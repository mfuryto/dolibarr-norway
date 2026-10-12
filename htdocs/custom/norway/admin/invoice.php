<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require '../../../main.inc.php';
if (!$user->admin || !isModEnabled('norway') || !$user->hasRight('facture', 'lire')) { accessforbidden(); }
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once __DIR__.'/../class/NorwayInvoiceCheck.php';
require_once __DIR__.'/../class/NorwayInvoiceAmounts.php';
require_once __DIR__.'/../class/NorwayTaxReference.php';
require_once __DIR__.'/../class/NorwayTaxMappingPreview.php';
require_once __DIR__.'/../class/NorwayVatRegistration.php';
$langs->load('norway@norway');
$id = GETPOSTINT('id');
header('Cache-Control: no-store');
llxHeader('', $langs->trans('NorwayInvoiceCheck'));
print '<div class="norway-page">';
print load_fiche_titre($langs->trans('NorwayInvoiceCheck'), '', 'bill');
print '<p>'.$langs->trans('NorwayInvoiceScope').'</p>';
print '<p><a href="'.dol_buildpath('/norway/admin/invoiceperiod.php',1).'">'.$langs->trans('NorwayBatch').'</a></p>';
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
        print '<h2>'.$langs->trans('NorwayAmounts').'</h2><p>'.$langs->trans('NorwayAmountsScope').'</p><p>'.$langs->trans('NorwayTaxEvidenceScope').'</p>';
        print '<p>'.$langs->trans('NorwayTaxReferenceScope').' <a target="_blank" rel="noopener noreferrer" href="'.NorwayTaxReference::SOURCE.'">'.$langs->trans('NorwayTaxReferenceSource').'</a></p>';
        print '<div class="warning">'.$langs->trans('NorwayAmounts'.$amounts['status']).'</div>';
        print '<div class="div-table-responsive"><table class="noborder centpercent"><tr>';
        foreach (array('Line','Rate','TaxCode','TaxReference','TaxFlags','Net','VAT','Review') as $column) { print '<th>'.$langs->trans('NorwayAmounts'.$column).'</th>'; }
        print '</tr>';
        foreach ($amounts['rows'] as $index => $line) {
            $evidence = $line['evidence'];
            $code = $evidence['code_state'] === 'Recorded' ? dol_escape_htmltag($evidence['code']) : $langs->trans('NorwayTaxCode'.$evidence['code_state']);
            $flags = $evidence['flags'] === null ? $langs->trans('NorwayTaxFlagsUnknown') : ($evidence['flags'] === 0 ? $langs->trans('NorwayTaxFlagsNone') : $langs->trans('NorwayTaxFlagsPresent').' ('.$evidence['flags'].')');
            print '<tr><td>'.($index + 1).'</td><td>'.$formatAmount($line['rate']).'</td><td>'.$code.'</td><td>'.$langs->trans('NorwayTaxReference'.NorwayTaxReference::inspect($evidence)).'</td><td>'.$flags.'</td><td>'.$formatAmount($line['net']).'</td><td>'.$formatAmount($line['vat']).'</td><td>'.$langs->trans('NorwayAmounts'.$line['status']).'</td></tr>';
        }
        foreach (array('Sum'=>array($amounts['net'],$amounts['vat']), 'Header'=>array($amounts['header_net'],$amounts['header_vat'])) as $label=>$values) {
            print '<tr><td colspan="5">'.$langs->trans('NorwayAmounts'.$label).'</td><td>'.$formatAmount($values[0]).'</td><td>'.$formatAmount($values[1]).'</td><td></td></tr>';
        }
        print '</table></div>';
        print '<p><a href="'.dol_buildpath('/norway/admin/taxmappings.php',1).'">'.$langs->trans('NorwayMappingsTitle').'</a></p>';
        print '<h2>'.$langs->trans('NorwayMappingTitle').'</h2><p>'.$langs->trans('NorwayMappingScope').'</p>';
        $mappingInput=array('source'=>GETPOST('mapsource','none'),'target'=>GETPOST('maptarget','none'),
            'start'=>GETPOST('mapstart','none'),'end'=>GETPOST('mapend','none'));
        print '<form method="get" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
        print '<input type="hidden" name="id" value="'.(int)$invoice->id.'"><input type="hidden" name="mapping" value="1">';
        foreach (array('source','target','start','end') as $field) {
            $date=in_array($field,array('start','end'),true);
            $value=is_string($mappingInput[$field]) ? $mappingInput[$field] : '';
            print '<p><label>'.$langs->trans('NorwayMapping'.ucfirst($field)).' <input required type="'.($date ? 'date' : 'text').'" name="map'.$field.'"'.($date ? '' : ' maxlength="10"').' value="'.dol_escape_htmltag($value).'"></label></p>';
        }
        print '<input class="button" type="submit" value="'.$langs->trans('NorwayMappingRun').'"></form>';
        if (GETPOSTISSET('mapping')) {
            try {
                $plan=NorwayTaxMappingPreview::plan($mappingInput['source'],$mappingInput['target'],$mappingInput['start'],$mappingInput['end']);
                $date=empty($invoice->date) ? '' : dol_print_date($invoice->date,'%Y-%m-%d');
                print '<div class="warning">'.$langs->trans('NorwayMappingUnsaved').'</div>';
                print '<div class="div-table-responsive"><table class="noborder centpercent"><tr><th>'.$langs->trans('NorwayAmountsLine').'</th><th>'.$langs->trans('NorwayMappingResult').'</th><th>'.$langs->trans('NorwayMappingTarget').'</th></tr>';
                foreach ($amounts['rows'] as $index=>$line) {
                    $status=NorwayTaxMappingPreview::compare($plan,$date,$line['evidence']);
                    print '<tr><td>'.($index+1).'</td><td>'.$langs->trans('NorwayMapping'.$status).'</td><td>'.($status==='Candidate' ? dol_escape_htmltag($plan['target']) : '—').'</td></tr>';
                }
                if (!$amounts['rows']) { print '<tr><td colspan="3">'.$langs->trans('NorwayMappingNoLines').'</td></tr>'; }
                print '</table></div>';
            } catch (InvalidArgumentException $error) { print '<div class="error">'.$langs->trans('NorwayMappingInvalid').'</div>'; }
        }
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
