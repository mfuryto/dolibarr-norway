<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require_once __DIR__.'/NorwayPostingEvidence.php';
require_once __DIR__.'/NorwayPurchaseDeduction.php';
/** Explicit choices and read-only comparison in the native supplier invoice card. */
class NorwayPurchaseCard
{
    public static function render($db,$object,$user,$langs,$entity,$profile,$percentage=null)
    {
        if (empty($user->admin) || !$user->hasRight('fournisseur','facture','lire') || !$user->hasRight('accounting','mouvements','lire')
            || ($object->element ?? '')!=='invoice_supplier' || (int)($object->entity ?? 0)!==$entity || (int)($object->id ?? 0)<=0) { return ''; }
        $langs->load('norway@norway');
        $choiceHtml=self::choices($db,$object,$user,$langs,$entity,$profile,$percentage);
        $pending=$_SESSION['norway_purchase_deduction'] ?? null;
        $hasPreview=$pending && $pending['entity']===$entity && $pending['id']===(int)$object->id && $pending['user']===(int)$user->id && $pending['expires']>=time();
        $html='<div class="norway-page clearboth"><details id="norway-purchase-allocation"'.($profile!=='' || $hasPreview ? ' open' : '').'><summary>'.$langs->trans('NorwayPurchaseTitle').'</summary><p>'.$langs->trans('NorwayPurchaseScope').'</p>';
        $html.=$choiceHtml;
        $html.='<form method="get" action="'.DOL_URL_ROOT.'/fourn/facture/card.php"><input type="hidden" name="facid" value="'.(int)$object->id.'"><label>'.$langs->trans('NorwayPurchaseProfile').' <select name="norway_purchase_profile" required><option value="">'.$langs->trans('NorwaySavedMappingChoose').'</option>';
        foreach (NorwayPurchaseAllocation::profiles() as $key=>$rule) { $html.='<option value="'.$key.'"'.($profile===$key ? ' selected' : '').'>'.$rule['rate'].' % – '.$langs->trans('NorwayPurchase'.ucfirst($rule['deduction'])).'</option>'; }
        $html.='</select></label><label>'.$langs->trans('NorwayPurchaseShare').' <input type="number" name="norway_purchase_share" min="0.01" max="99.99" step="0.01" value="'.dol_escape_htmltag(is_string($percentage) ? $percentage : '').'"></label><button class="button" type="submit">'.$langs->trans('NorwayInvoiceRun').'</button></form>';
        if ($profile!=='') {
            try {
                try { NorwayPurchaseAllocation::deduction($profile,$percentage); }
                catch (InvalidArgumentException $error) { return $html.'<div class="warning">'.$langs->trans('NorwayPurchaseShareInvalid').'</div></details></div>'; }
                $report=NorwayPostingEvidence::report($db,$entity,(int)$object->id,$user,$profile,'supplier_invoice',$percentage);
                $html.='<div class="warning">'.$langs->trans('NorwayPurchase'.$report['status']).'</div>';
                if ($report['source_changed']) { $html.='<p>'.$langs->trans('NorwayRetainedAllocationChanged').'</p>'; }
                if (isset($report['deduction_basis_points'])) { $html.='<p>'.$langs->trans('NorwayPurchaseSelectedShare').' '.dol_escape_htmltag(number_format($report['deduction_basis_points']/100,2,'.','')).' %</p><p>'.$langs->trans('NorwayPurchaseNonDeductible').' '.dol_escape_htmltag($report['non_deductible_vat']).' NOK</p>'; }
                if ($report['totals']) {
                    $html.='<div class="div-table-responsive"><table class="noborder centpercent"><tr>';
                    foreach (array('Role','Expected','Actual','Difference') as $column) { $html.='<th>'.$langs->trans('NorwayAllocation'.$column).'</th>'; }$html.='</tr>';
                    foreach ($report['totals'] as $row) { $html.='<tr><td>'.$langs->trans('NorwayPurchase'.$row['role']).'</td><td>'.dol_escape_htmltag($row['expected']).'</td><td>'.dol_escape_htmltag($row['actual']).'</td><td>'.dol_escape_htmltag($row['difference']).'</td></tr>'; }$html.='</table></div>';
                }
            } catch (Throwable $error) { $html.='<div class="warning">'.$langs->trans('NorwayPurchaseUnavailable').'</div>'; }
        }
        return $html.'</details></div>';
    }
    private static function choices($db,$object,$user,$langs,$entity,&$profile,&$percentage)
    {
        $html='<h3>'.$langs->trans('NorwayDeductionTitle').'</h3><p>'.$langs->trans('NorwayDeductionScope').'</p>';
        $id=(int)$object->id;
        $render=static function ($data) use ($langs) {
            $choice=$data['choice'];
            $out='<p>'.NorwayPurchaseAllocation::profiles()[$choice['profile']]['rate'].' % – '.$langs->trans('NorwayPurchase'.ucfirst(NorwayPurchaseAllocation::profiles()[$choice['profile']]['deduction'])).' / '.number_format($choice['basis_points']/100,2,'.','').' %</p><p>'.nl2br(dol_escape_htmltag($choice['reason'])).'</p>';
            $out.='<div class="div-table-responsive"><table class="noborder centpercent"><tr>';
            foreach (array('Line','Vat','Deductible','NonDeductible') as $heading) { $out.='<th>'.$langs->trans('NorwayDeduction'.$heading).'</th>'; }$out.='</tr>';
            foreach ($data['lines'] as $line) { $out.='<tr><td>'.$line['line'].'</td><td>'.dol_escape_htmltag(substr($line['vat'],0,-6)).'</td><td>'.dol_escape_htmltag(substr($line['deductible'],0,-6)).'</td><td>'.dol_escape_htmltag(substr($line['non_deductible'],0,-6)).'</td></tr>'; }
            return $out.'</table></div>';
        };
        try {
            $history=NorwayPurchaseDeduction::history($db,$entity,$id,$user);
            if ($history) {
                $last=end($history);$changed=true;
                try { $changed=NorwayPurchaseDeduction::basis($db,$entity,$id,$user)!==$last['data']['basis']; } catch (Throwable $error) { /* Out-of-scope source changes must not hide the retained history. */ }
                $html.='<div class="warning">'.$langs->trans($changed ? 'NorwayDeductionChanged' : 'NorwayDeductionCurrent').'</div>';
                if (!$changed && $profile==='') { $profile=$last['data']['choice']['profile'];$percentage=$last['data']['choice']['percentage']; }
                foreach (array_reverse($history) as $entry) {
                    $html.='<details><summary>'.dol_escape_htmltag('#'.$entry['revision'].' / '.$entry['captured_at'].' UTC / '.$langs->trans('NorwayEvidenceUser').' #'.$entry['fk_user']).'</summary>'.$render($entry['data']).'</details>';
                }
            }
            if (!$user->hasRight('fournisseur','facture','creer')) { return $html; }
            $endpoint=dol_buildpath('/norway/admin/purchasededuction.php',1);
            $html.='<form method="post" action="'.$endpoint.'"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="id" value="'.$id.'"><input type="hidden" name="action" value="preview"><p><label>'.$langs->trans('NorwayPurchaseProfile').' <select name="profile" required><option value="">'.$langs->trans('NorwaySavedMappingChoose').'</option>';
            foreach (NorwayPurchaseAllocation::profiles() as $key=>$rule) { $html.='<option value="'.$key.'">'.$rule['rate'].' % – '.$langs->trans('NorwayPurchase'.ucfirst($rule['deduction'])).'</option>'; }
            $html.='</select></label></p><p><label>'.$langs->trans('NorwayPurchaseShare').' <input type="number" name="percentage" min="0.01" max="99.99" step="0.01"></label></p><p><label>'.$langs->trans('NorwayDeductionReason').' <textarea name="reason" rows="3" maxlength="2000" required></textarea></label></p><button class="button" type="submit">'.$langs->trans('NorwayEvidencePreview').'</button></form>';
            $pending=$_SESSION['norway_purchase_deduction'] ?? null;
            if ($pending && $pending['entity']===$entity && $pending['id']===$id && $pending['user']===(int)$user->id && $pending['expires']>=time()) {
                $html.='<h4>'.$langs->trans('NorwayEvidencePreview').'</h4>'.$render($pending['preview']);
                $html.='<form method="post" action="'.$endpoint.'"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="id" value="'.$id.'"><input type="hidden" name="action" value="save"><input type="hidden" name="nonce" value="'.$pending['nonce'].'"><p><label><input type="checkbox" name="confirmed" value="1" required> '.$langs->trans('NorwayDeductionConfirm').'</label></p><button class="button" type="submit">'.$langs->trans('NorwayEvidenceSave').'</button></form>';
            }
        } catch (Throwable $error) { $html.='<div class="warning">'.$langs->trans('NorwayDeductionFailed').'</div>'; }
        return $html;
    }

}
