<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require '../../../main.inc.php';
if (!$user->admin || !isModEnabled('norway') || !$user->hasRight('facture', 'lire')) { accessforbidden(); }
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once __DIR__.'/../class/NorwayInvoiceCheck.php';
require_once __DIR__.'/../class/NorwayInvoiceAmounts.php';
require_once __DIR__.'/../class/NorwaySalesZeroVat.php';
require_once __DIR__.'/../class/NorwayTaxReference.php';
require_once __DIR__.'/../class/NorwayTaxMappingPreview.php';
require_once __DIR__.'/../class/NorwayInvoiceMapping.php';
require_once __DIR__.'/../class/NorwayInvoiceLedger.php';
require_once __DIR__.'/../class/NorwayInvoiceReconciliation.php';
require_once __DIR__.'/../class/NorwayInvoiceAllocation.php';
require_once __DIR__.'/../class/NorwayPostingEvidence.php';
require_once __DIR__.'/../class/NorwayVatRegistration.php';
$langs->load('norway@norway');
$renderZeroVat=static function ($report,$source) use ($langs) {
    print '<section class="norway-zero-vat"><h3>'.$langs->trans('NorwayZeroVatTitle').'</h3><p>'.$langs->trans('NorwayZeroVatScope').'</p><p>'.$langs->trans('NorwayZeroVat'.$source).'</p>';
    if ($report['status']!=='Evaluated') { print '<div class="warning">'.$langs->trans('NorwayZeroVatUnsupported').'</div></section>';return; }
    $rows=array_values(array_filter($report['rows'],static fn($row)=>$row['status']!=='NotApplicable'));
    if (!$rows) { print '<p>'.$langs->trans('NorwayZeroVatEmpty').'</p></section>';return; }
    print '<div class="div-table-responsive"><table class="noborder centpercent"><tr>';
    foreach (array('Line','Target','Category','Result','Action') as $key) { print '<th>'.$langs->trans('NorwayZeroVat'.$key).'</th>'; }print '</tr>';
    foreach ($rows as $row) {
        print '<tr><td>'.(int)$row['line'].'</td><td>'.dol_escape_htmltag($row['target']).'</td><td>'.$langs->trans('NorwayZeroVat'.$row['category']).'</td><td>'.$langs->trans('NorwayZeroVat'.$row['status']).'</td><td>'.$langs->trans('NorwayZeroVatAction'.$row['status']).'</td></tr>';
    }
    print '</table></div></section>';
};
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
        if (isModEnabled('accounting') && $user->hasRight('accounting','mouvements','lire')) {
            print '<p><a href="'.dol_buildpath('/norway/admin/evidence.php',1).'?id='.(int)$invoice->id.'">'.$langs->trans('NorwayEvidenceTitle').'</a></p>';
            print '<details'.(GETPOSTISSET('ledger') ? ' open' : '').'><summary>'.$langs->trans('NorwayInvoiceLedgerTitle').'</summary><p>'.$langs->trans('NorwayInvoiceLedgerScope').'</p>';
            try {
                $ledger=NorwayInvoiceLedger::report($db,(int)$conf->entity,(int)$invoice->id);
                if (!$ledger['rows']) { print '<p>'.$langs->trans('NorwayInvoiceLedgerEmpty').'</p>'; }
                else {
                    if ($ledger['truncated']) { print '<div class="warning">'.$langs->trans('NorwayInvoiceLedgerTruncated').'</div>'; }
                    print '<div class="div-table-responsive"><table class="noborder centpercent"><tr>';
                    foreach (array('ID','Journal','Voucher','Date','Line','Account','Debit','Credit','Trace') as $column) { print '<th>'.$langs->trans('NorwayInvoiceLedger'.$column).'</th>'; }
                    print '</tr>';
                    foreach ($ledger['rows'] as $row) {
                        print '<tr><td>'.(int)$row->rowid.'</td><td>'.dol_escape_htmltag($row->code_journal).'</td><td>'.(int)$row->piece_num.'</td><td>'.dol_escape_htmltag($row->doc_date).'</td><td>'.($row->fk_docdet===null ? '—' : (int)$row->fk_docdet).'</td><td>'.dol_escape_htmltag($row->numero_compte).'</td><td>'.price($row->debit).'</td><td>'.price($row->credit).'</td><td>'.$langs->trans('NorwayInvoiceLedger'.$row->trace).'</td></tr>';
                    }
                    print '</table></div>';
                }
                print '<h3>'.$langs->trans('NorwayReconcileTitle').'</h3><p>'.$langs->trans('NorwayReconcileScope').'</p>';
                try {
                    $totals=NorwayInvoiceReconciliation::report($db,(int)$conf->entity,(int)$invoice->id);
                    print '<div class="warning">'.$langs->trans('NorwayReconcile'.$totals['status']).'</div>';
                    print '<div class="div-table-responsive"><table class="noborder centpercent">';
                    foreach (array('gross'=>'Gross','debit'=>'Debit','credit'=>'Credit','difference'=>'Difference') as $key=>$label) {
                        print '<tr><td>'.$langs->trans('NorwayReconcile'.$label).'</td><td>'.($totals[$key]===null ? '—' : dol_escape_htmltag($totals[$key])).' NOK</td></tr>';
                    }
                    print '</table></div>';
                } catch (Throwable $error) { print '<div class="warning">'.$langs->trans('NorwayReconcileUnavailable').'</div>'; }
                print '<h3>'.$langs->trans('NorwayAllocationTitle').'</h3><p>'.$langs->trans('NorwayAllocationScope').'</p>';
                $allocation=GETPOST('allocation','aZ09');
                print '<form method="get"><input type="hidden" name="id" value="'.(int)$invoice->id.'"><input type="hidden" name="ledger" value="1"><p><label>'.$langs->trans('NorwayAllocationProfile').' <select name="allocation" required><option value="">'.$langs->trans('NorwaySavedMappingChoose').'</option>';
                foreach (NorwayInvoiceAllocation::profiles() as $key=>$rule) { print '<option value="'.$key.'"'.($allocation===$key ? ' selected' : '').'>'.dol_escape_htmltag($rule['rate'].' % / '.$rule['customer'].' / '.implode(', ',$rule['sales']).' / '.$rule['vat']).'</option>'; }
                print '</select></label></p><button class="button" type="submit">'.$langs->trans('NorwayInvoiceRun').'</button></form>';
                if (GETPOSTISSET('allocation')) {
                    try {
                        $allocationReport=NorwayInvoiceAllocation::report($db,(int)$conf->entity,(int)$invoice->id,$allocation);
                        print '<div class="warning">'.$langs->trans('NorwayAllocation'.$allocationReport['status']).'</div><div class="div-table-responsive"><table class="noborder centpercent"><tr>';
                        foreach (array('Role','Expected','Actual','Difference') as $column) { print '<th>'.$langs->trans('NorwayAllocation'.$column).'</th>'; }print '</tr>';
                        foreach ($allocationReport['totals'] as $total) { print '<tr><td>'.$langs->trans('NorwayAllocation'.$total['role']).'</td><td>'.dol_escape_htmltag($total['expected']).'</td><td>'.dol_escape_htmltag($total['actual']).'</td><td>'.dol_escape_htmltag($total['difference']).'</td></tr>'; }print '</table></div>';
                        print '<div class="div-table-responsive"><table class="noborder centpercent"><tr><th>'.$langs->trans('NorwayInvoiceLedgerID').'</th><th>'.$langs->trans('NorwayInvoiceLedgerAccount').'</th><th>'.$langs->trans('NorwayAllocationRole').'</th><th>'.$langs->trans('NorwayAllocationActual').'</th></tr>';
                        foreach ($allocationReport['rows'] as $row) { print '<tr><td>'.(int)$row['id'].'</td><td>'.dol_escape_htmltag($row['account']).'</td><td>'.$langs->trans('NorwayAllocation'.$row['role']).'</td><td>'.dol_escape_htmltag($row['amount']).'</td></tr>'; }print '</table></div>';
                        $lineAccounts=$allocationReport['line_accounts'];
                        print '<h4>'.$langs->trans('NorwayLineAccountsTitle').'</h4><p>'.$langs->trans('NorwayLineAccountsScope').'</p><div class="warning">'.$langs->trans('NorwayLineAccounts'.$lineAccounts['status']).'</div>';
                        print '<div class="div-table-responsive"><table class="noborder centpercent"><tr>';
                        foreach (array('Line','Binding','Account','Status') as $column) { print '<th>'.$langs->trans('NorwayLineAccounts'.$column).'</th>'; }print '</tr>';
                        foreach ($lineAccounts['rows'] as $row) { print '<tr><td>'.(int)$row['line'].'</td><td>'.(int)$row['binding'].'</td><td>'.dol_escape_htmltag($row['account'].' — '.$row['label']).($row['active']===0 ? '<br>'.$langs->trans('NorwayLineAccountsInactive') : '').'</td><td>'.$langs->trans('NorwayLineAccounts'.$row['status']).'</td></tr>'; }print '</table></div>';
                        print '<div class="div-table-responsive"><table class="noborder centpercent"><tr><th>'.$langs->trans('NorwayLineAccountsAccount').'</th>';
                        foreach (array('Expected','Actual','Difference') as $column) { print '<th>'.$langs->trans('NorwayAllocation'.$column).'</th>'; }print '</tr>';
                        foreach ($lineAccounts['totals'] as $total) { print '<tr><td>'.dol_escape_htmltag($total['account']).'</td><td>'.dol_escape_htmltag($total['expected']).'</td><td>'.dol_escape_htmltag($total['actual']).'</td><td>'.dol_escape_htmltag($total['difference']).'</td></tr>'; }print '</table></div>';
                    } catch (Throwable $error) { print '<div class="warning">'.$langs->trans('NorwayAllocationUnavailable').'</div>'; }
                }
                if (GETPOSTISSET('allocation')) {
                    print '<h4>'.$langs->trans('NorwayRetainedAllocationTitle').'</h4><p>'.$langs->trans('NorwayRetainedAllocationScope').'</p>';
                    try {
                        $retained=NorwayPostingEvidence::report($db,(int)$conf->entity,(int)$invoice->id,$user,$allocation);
                        print '<div class="warning">'.$langs->trans('NorwayRetainedAllocation'.$retained['status']).'</div>';
                        if ($retained['source_changed']) { print '<p>'.$langs->trans('NorwayRetainedAllocationChanged').'</p>'; }
                        if ($retained['totals']) {
                            print '<div class="div-table-responsive"><table class="noborder centpercent"><tr>';
                            foreach (array('Role','Expected','Actual','Difference') as $column) { print '<th>'.$langs->trans('NorwayAllocation'.$column).'</th>'; }print '</tr>';
                            foreach ($retained['totals'] as $total) { print '<tr><td>'.$langs->trans('NorwayAllocation'.$total['role']).'</td><td>'.dol_escape_htmltag($total['expected']).'</td><td>'.dol_escape_htmltag($total['actual']).'</td><td>'.dol_escape_htmltag($total['difference']).'</td></tr>'; }print '</table></div>';
                        }
                    } catch (Throwable $error) { print '<div class="warning">'.$langs->trans('NorwayRetainedAllocationUnavailable').'</div>'; }
                }
                print '<h3>'.$langs->trans('NorwayPostingTitle').'</h3><p>'.$langs->trans('NorwayPostingScope').'</p>';
                try {
                    $observations=NorwayPostingEvidence::report($db,(int)$conf->entity,(int)$invoice->id,$user);
                    if (!$observations) { print '<p>'.$langs->trans('NorwayInvoiceLedgerEmpty').'</p>'; }
                    print '<div class="div-table-responsive"><table class="noborder centpercent"><tr>';
                    foreach (array('ID','Captured','Status') as $column) { print '<th>'.$langs->trans('NorwayPosting'.$column).'</th>'; }print '</tr>';
                    foreach ($observations as $observation) {
                        print '<tr><td>'.(int)$observation['id'].'</td><td>'.dol_escape_htmltag($observation['captured_at']).'</td><td>'.$langs->trans('NorwayPosting'.$observation['status']);
                        if ($observation['data']) {
                            $basis=$observation['data']['basis'];
                            $renderZeroVat(NorwaySalesZeroVat::captured($basis,(int)$conf->entity),'Captured');
                            $registrationContext=$observation['data']['registration_context'] ?? null;
                            print '<p>'.$langs->trans('NorwayPostingRegistration'.($registrationContext ? $registrationContext['status'] : 'Legacy'));
                            if ($registrationContext && $registrationContext['start']!=='') { print ' '.dol_escape_htmltag($registrationContext['invoice_date'].' / '.$registrationContext['start']); }
                            if ($registrationContext && ($registrationContext['version'] ?? 1)===2) { print ' / #'.(int)$registrationContext['history_revision'].' / '.dol_escape_htmltag($registrationContext['end']); }
                            if ($registrationContext && $registrationContext['credit']) { print ' '.$langs->trans('NorwayPostingRegistrationCredit'); }print '</p>';
                            $context=$observation['data']['tax_context'] ?? null;
                            print '<p>'.$langs->trans('NorwayPostingTax'.($context ? $context['status'] : 'Legacy')).'</p>';
                            if ($context && $context['lines']) {
                                print '<div class="div-table-responsive"><table class="noborder centpercent"><tr>';
                                foreach (array('Line','Code') as $column) { print '<th>'.$langs->trans('NorwayPosting'.$column).'</th>'; }
                                foreach (array('Target','Rule','State') as $column) { print '<th>'.$langs->trans('NorwayPostingTax'.$column).'</th>'; }print '</tr>';
                                foreach ($context['lines'] as $taxLine) {
                                    print '<tr><td>'.(int)$taxLine['line'].'</td><td>'.dol_escape_htmltag($taxLine['source']).'</td><td>'.dol_escape_htmltag($taxLine['target']).'</td><td>'.(int)$taxLine['rule'].' / '.(int)$taxLine['rule_revision'].'</td><td>'.$langs->trans('NorwayPostingTax'.$taxLine['status']).'</td></tr>';
                                }print '</table></div>';
                            }
                            print '<details><summary>'.$langs->trans('NorwayPostingSaved').'</summary><p>'.dol_escape_htmltag($basis['posting']['numero_compte'].' / '.$basis['posting']['debit'].' / '.$basis['posting']['credit']).'</p><div class="div-table-responsive"><table class="noborder centpercent"><tr>';
                            foreach (array('Line','Account','Rate','Code','Net','Vat') as $column) { print '<th>'.$langs->trans('NorwayPosting'.$column).'</th>'; }print '</tr>';
                            foreach ($basis['lines'] as $line) { print '<tr><td>'.(int)$line['rowid'].'</td><td>'.dol_escape_htmltag((string)$line['bound_number']).'</td><td>'.dol_escape_htmltag($line['tva_tx']).'</td><td>'.dol_escape_htmltag((string)$line['vat_src_code']).'</td><td>'.dol_escape_htmltag($line['total_ht']).'</td><td>'.dol_escape_htmltag($line['total_tva']).'</td></tr>'; }print '</table></div></details>';
                        }print '</td></tr>';
                    }print '</table></div>';
                } catch (Throwable $error) { print '<div class="warning">'.$langs->trans('NorwayPostingUnavailable').'</div>'; }
                print '<p><a href="'.DOL_URL_ROOT.'/accountancy/bookkeeping/list.php">'.$langs->trans('NorwayInvoiceLedgerOpen').'</a></p>';
            } catch (Throwable $error) { print '<div class="warning">'.$langs->trans('NorwayInvoiceLedgerFailed').'</div>'; }
            print '</details>';
        }
        print '<p><a href="'.dol_buildpath('/norway/admin/taxmappings.php',1).'">'.$langs->trans('NorwayMappingsTitle').'</a></p>';
        $selectedRevision=GETPOSTINT('mapping_revision');
        print '<details'.(GETPOSTISSET('mapping_revision') ? ' open' : '').'><summary>'.$langs->trans('NorwaySavedMappingTitle').'</summary><p>'.$langs->trans('NorwaySavedMappingScope').'</p>';
        try {
            $mappingState=NorwayTaxMappings::read($db,$conf->entity);
            if (!$mappingState['record']['events']) { print '<p>'.$langs->trans('NorwayMappingsEmpty').'</p>'; }
            else {
                print '<form method="get"><input type="hidden" name="id" value="'.(int)$invoice->id.'"><p><label>'.$langs->trans('NorwaySavedMappingRevision').' <select name="mapping_revision" required><option value="">'.$langs->trans('NorwaySavedMappingChoose').'</option>';
                foreach ($mappingState['record']['events'] as $event) {
                    print '<option value="'.(int)$event['revision'].'"'.($selectedRevision===$event['revision'] ? ' selected' : '').'>'.dol_escape_htmltag('#'.$event['revision'].' / '.$event['at']).'</option>';
                }
                print '</select></label></p><button class="button" type="submit">'.$langs->trans('NorwaySavedMappingRun').'</button></form>';
                if (GETPOSTISSET('mapping_revision')) {
                    $date=empty($invoice->date) ? '' : dol_print_date($invoice->date,'%Y-%m-%d');
                    $comparison=NorwayInvoiceMapping::report($mappingState['record'],$selectedRevision,$invoice,$date);
                    $renderZeroVat(NorwaySalesZeroVat::invoice($invoice,$comparison),'Current');
                    print '<p>'.$langs->trans('NorwaySavedMappingSelected').' #'.(int)$comparison['revision'].' / '.dol_escape_htmltag($comparison['at']).'</p>';
                    print '<div class="div-table-responsive"><table class="noborder centpercent"><tr><th>'.$langs->trans('NorwayAmountsLine').'</th><th>'.$langs->trans('NorwayMappingResult').'</th><th>'.$langs->trans('NorwaySavedMappingRule').'</th><th>'.$langs->trans('NorwayMappingTarget').'</th></tr>';
                    foreach ($comparison['rows'] as $index=>$row) {
                        $rule=$row['rule'];
                        print '<tr><td>'.($index+1).'</td><td>'.$langs->trans('NorwaySavedMapping'.$row['status']).'</td><td>'.($rule ? '#'.(int)$rule['id'].' / '.$langs->trans('NorwayMappingsRevision').' '.(int)$rule['revision'] : '—').'</td><td>'.($rule ? dol_escape_htmltag($rule['target']) : '—').'</td></tr>';
                    }
                    if (!$comparison['rows']) { print '<tr><td colspan="4">'.$langs->trans('NorwayMappingNoLines').'</td></tr>'; }
                    print '</table></div>';
                }
            }
        } catch (Throwable $error) { print '<div class="warning">'.$langs->trans('NorwaySavedMappingFailed').'</div>'; }
        print '</details>';
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
            $result=NorwayVatHistory::current($db,(int)$conf->entity,$mysoc->idprof1,dol_print_date($invoice->date,'%Y-%m-%d'));
            print '<div class="warning">'.$langs->trans('NorwayRegistration'.$result).'</div>';
        } catch (Throwable $error) { print '<div class="warning">'.$langs->trans('NorwayRegistrationFailed').'</div>'; }
    }
}
print '</div>';
llxFooter();
$db->close();
