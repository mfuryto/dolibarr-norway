<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require_once __DIR__.'/NorwayPostingEvidence.php';
/** Optional read-only comparison embedded in the native supplier invoice card. */
class NorwayPurchaseCard
{
    public static function render($db,$object,$user,$langs,$entity,$profile)
    {
        if (empty($user->admin) || !$user->hasRight('fournisseur','facture','lire') || !$user->hasRight('accounting','mouvements','lire')
            || ($object->element ?? '')!=='invoice_supplier' || (int)($object->entity ?? 0)!==$entity || (int)($object->id ?? 0)<=0) { return ''; }
        $langs->load('norway@norway');
        $html='<div class="norway-page clearboth"><details id="norway-purchase-allocation"'.($profile!=='' ? ' open' : '').'><summary>'.$langs->trans('NorwayPurchaseTitle').'</summary><p>'.$langs->trans('NorwayPurchaseScope').'</p>';
        $html.='<form method="get" action="'.DOL_URL_ROOT.'/fourn/facture/card.php"><input type="hidden" name="facid" value="'.(int)$object->id.'"><label>'.$langs->trans('NorwayPurchaseProfile').' <select name="norway_purchase_profile" required><option value="">'.$langs->trans('NorwaySavedMappingChoose').'</option>';
        foreach (NorwayPurchaseAllocation::profiles() as $key=>$rule) { $html.='<option value="'.$key.'"'.($profile===$key ? ' selected' : '').'>'.$rule['rate'].' % – '.$langs->trans('NorwayPurchaseFull').'</option>'; }
        $html.='</select></label><button class="button" type="submit">'.$langs->trans('NorwayInvoiceRun').'</button></form>';
        if ($profile!=='') {
            try {
                $report=NorwayPostingEvidence::report($db,$entity,(int)$object->id,$user,$profile,'supplier_invoice');
                $html.='<div class="warning">'.$langs->trans('NorwayPurchase'.$report['status']).'</div>';
                if ($report['source_changed']) { $html.='<p>'.$langs->trans('NorwayRetainedAllocationChanged').'</p>'; }
                if ($report['totals']) {
                    $html.='<div class="div-table-responsive"><table class="noborder centpercent"><tr>';
                    foreach (array('Role','Expected','Actual','Difference') as $column) { $html.='<th>'.$langs->trans('NorwayAllocation'.$column).'</th>'; }$html.='</tr>';
                    foreach ($report['totals'] as $row) { $html.='<tr><td>'.$langs->trans('NorwayPurchase'.$row['role']).'</td><td>'.dol_escape_htmltag($row['expected']).'</td><td>'.dol_escape_htmltag($row['actual']).'</td><td>'.dol_escape_htmltag($row['difference']).'</td></tr>'; }$html.='</table></div>';
                }
            } catch (Throwable $error) { $html.='<div class="warning">'.$langs->trans('NorwayPurchaseUnavailable').'</div>'; }
        }
        return $html.'</details></div>';
    }
}
