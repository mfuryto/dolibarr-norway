<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require_once __DIR__.'/NorwayPostingEvidence.php';
require_once __DIR__.'/NorwayPurchaseDeduction.php';
require_once __DIR__.'/NorwayPurchasePosting.php';
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
        $postingPending=$_SESSION['norway_purchase_posting'] ?? null;
        $postingPreview=$postingPending && $postingPending['entity']===$entity && $postingPending['id']===(int)$object->id && $postingPending['user']===(int)$user->id && $postingPending['expires']>=time();
        $hasPreview=$pending && $pending['entity']===$entity && $pending['id']===(int)$object->id && $pending['user']===(int)$user->id && $pending['expires']>=time();
        $html='<div class="norway-page clearboth"><details id="norway-purchase-allocation"'.($profile!=='' || $hasPreview || $postingPreview ? ' open' : '').'><summary>'.$langs->trans('NorwayPurchaseTitle').'</summary><p>'.$langs->trans('NorwayPurchaseScope').'</p>';
        $html.=$choiceHtml.self::postingForm($db,$object,$user,$langs,$entity,$postingPreview ? $postingPending : null).self::postingContexts($db,$object,$user,$langs,$entity);
        $html.='<form method="get" action="'.DOL_URL_ROOT.'/fourn/facture/card.php"><input type="hidden" name="facid" value="'.(int)$object->id.'"><label>'.$langs->trans('NorwayPurchaseProfile').' <select name="norway_purchase_profile" required><option value="">'.$langs->trans('NorwaySavedMappingChoose').'</option>';
        if ($profile==='purchase_lines') { $html.='<option value="purchase_lines" selected>'.$langs->trans('NorwayDeductionLinesTitle').'</option>'; }
        foreach (NorwayPurchaseAllocation::profiles() as $key=>$rule) { $html.='<option value="'.$key.'"'.($profile===$key ? ' selected' : '').'>'.$rule['rate'].' % – '.$langs->trans('NorwayPurchase'.ucfirst($rule['deduction'])).'</option>'; }
        $html.='</select></label><label>'.$langs->trans('NorwayPurchaseShare').' <input type="number" name="norway_purchase_share" min="0.01" max="99.99" step="0.01" value="'.dol_escape_htmltag(is_string($percentage) ? $percentage : '').'"></label><button class="button" type="submit">'.$langs->trans('NorwayInvoiceRun').'</button></form>';
        if ($profile!=='') {
            try {
                try { if ($profile!=='purchase_lines') { NorwayPurchaseAllocation::deduction($profile,$percentage); } }
                catch (InvalidArgumentException $error) { return $html.'<div class="warning">'.$langs->trans('NorwayPurchaseShareInvalid').'</div></details></div>'; }
                $report=NorwayPostingEvidence::report($db,$entity,(int)$object->id,$user,$profile,'supplier_invoice',$percentage);
                $html.='<div class="warning">'.$langs->trans('NorwayPurchase'.$report['status']).'</div>';
                if (!empty($report['correction_revision'])) { $html.='<p>'.$langs->trans('NorwayPurchasePostingCorrectionEffective').' #'.(int)$report['correction_revision'].'</p>'; }
                if ($report['source_changed']) { $html.='<p>'.$langs->trans('NorwayRetainedAllocationChanged').'</p>'; }
                if (isset($report['deduction_basis_points'])) { $html.='<p>'.$langs->trans('NorwayPurchaseSelectedShare').' '.dol_escape_htmltag(number_format($report['deduction_basis_points']/100,2,'.','')).' %</p>'; }
                if (isset($report['non_deductible_vat'])) { $html.='<p>'.$langs->trans('NorwayPurchaseNonDeductible').' '.dol_escape_htmltag($report['non_deductible_vat']).' NOK</p>'; }
                if (!empty($report['vat_accounts'])) {
                    $html.='<h4>'.$langs->trans('NorwayDeductionLinesVatAccounts').'</h4><div class="div-table-responsive"><table class="noborder centpercent"><tr><th>'.$langs->trans('NorwayPurchasePostingAccount').'</th>';
                    foreach (array('Expected','Actual','Difference') as $column) { $html.='<th>'.$langs->trans('NorwayAllocation'.$column).'</th>'; }$html.='</tr>';
                    foreach ($report['vat_accounts'] as $row) { $html.='<tr><td>'.dol_escape_htmltag($row['account']).'</td><td>'.dol_escape_htmltag($row['expected']).'</td><td>'.dol_escape_htmltag($row['actual']).'</td><td>'.dol_escape_htmltag($row['difference']).'</td></tr>'; }$html.='</table></div>';
                }
                if ($report['totals']) {
                    $html.='<div class="div-table-responsive"><table class="noborder centpercent"><tr>';
                    foreach (array('Role','Expected','Actual','Difference') as $column) { $html.='<th>'.$langs->trans('NorwayAllocation'.$column).'</th>'; }$html.='</tr>';
                    foreach ($report['totals'] as $row) { $html.='<tr><td>'.$langs->trans('NorwayPurchase'.$row['role']).'</td><td>'.dol_escape_htmltag($row['expected']).'</td><td>'.dol_escape_htmltag($row['actual']).'</td><td>'.dol_escape_htmltag($row['difference']).'</td></tr>'; }$html.='</table></div>';
                }
            } catch (Throwable $error) { $html.='<div class="warning">'.$langs->trans('NorwayPurchaseUnavailable').'</div>'; }
        }
        return $html.'</details></div>';
    }
    private static function postingForm($db,$object,$user,$langs,$entity,$pending)
    {
        if (!$user->hasRight('accounting','bind','write')) { return ''; }
        $id=(int)$object->id;
        $r=$db->query("SELECT rowid FROM ".$db->prefix()."accounting_bookkeeping WHERE entity=".$entity." AND doc_type='supplier_invoice' AND fk_doc=".$id.' LIMIT 1');
        if (!$r) { return '<p class="warning">'.$langs->trans('NorwayPurchasePostingFailed').'</p>'; }
        if ($db->fetch_object($r)) { return self::correctionForm($db,$object,$langs,$entity,$pending); }
        $endpoint=dol_buildpath('/norway/admin/purchasededuction.php',1).'?lang='.rawurlencode($langs->defaultlang);
        $html='<h3>'.$langs->trans('NorwayPurchasePostingTitle').'</h3><p>'.$langs->trans('NorwayPurchasePostingScope').'</p>';
        $html.='<form method="post" action="'.$endpoint.'"><input type="hidden" name="lang" value="'.dol_escape_htmltag($langs->defaultlang).'"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="id" value="'.$id.'"><input type="hidden" name="action" value="previewposting"><p><label>'.$langs->trans('NorwayPurchasePostingJournal').' <select name="journal" required><option value="">'.$langs->trans('NorwaySavedMappingChoose').'</option>';
        $r=$db->query('SELECT rowid,code,label FROM '.$db->prefix().'accounting_journal WHERE entity='.$entity.' AND nature=3 AND active=1 ORDER BY code');
        if (!$r) { return '<p class="warning">'.$langs->trans('NorwayPurchasePostingFailed').'</p>'; }
        while ($journal=$db->fetch_object($r)) { $html.='<option value="'.(int)$journal->rowid.'">'.dol_escape_htmltag($journal->code.' — '.$journal->label).'</option>'; }
        $html.='</select></label></p><button class="button" type="submit">'.$langs->trans('NorwayPurchasePostingPreview').'</button></form>';
        if ($pending) {
            $preview=$pending['preview'];
            $html.='<h4>'.$langs->trans('NorwayPurchasePostingPreview').'</h4><p>'.$langs->trans('NorwayDeductionPostingRevision').' #'.(int)$preview['assessment']['revision'].' / '.dol_escape_htmltag($preview['basis']['invoice']['datef']).' / '.dol_escape_htmltag($preview['journal']['code']).'</p><div class="div-table-responsive"><table class="noborder centpercent"><tr>';
            foreach (array('Line','Account','Debit','Credit') as $key) { $html.='<th>'.$langs->trans('NorwayPurchasePosting'.$key).'</th>'; }$html.='</tr>';
            foreach ($preview['rows'] as $row) { $html.='<tr><td>'.($row['line']===0 ? '—' : $row['line']).'</td><td>'.dol_escape_htmltag($row['account'].' — '.$row['label']).'</td><td>'.dol_escape_htmltag($row['debit']).'</td><td>'.dol_escape_htmltag($row['credit']).'</td></tr>'; }
            $html.='</table></div><form method="post" action="'.$endpoint.'"><input type="hidden" name="lang" value="'.dol_escape_htmltag($langs->defaultlang).'"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="id" value="'.$id.'"><input type="hidden" name="action" value="writeposting"><input type="hidden" name="nonce" value="'.$pending['nonce'].'"><p><label><input type="checkbox" name="confirmed" value="1" required> '.$langs->trans('NorwayPurchasePostingConfirm').'</label></p><button class="button" type="submit">'.$langs->trans('NorwayPurchasePostingWrite').'</button></form>';
        }
        return $html;
    }
    private static function correctionForm($db,$object,$langs,$entity,$pending)
    {
        $id=(int)$object->id;$p=$db->prefix();$r=$db->query("SELECT rowid FROM ".$p."norway_tax_evidence WHERE entity=".$entity." AND document_type='supplier_posting' AND fk_document=".$id.' LIMIT 1');
        if (!$r || !$db->fetch_object($r)) { return '<p>'.$langs->trans('NorwayPurchasePostingAlreadyPosted').'</p>'; }
        $endpoint=dol_buildpath('/norway/admin/purchasededuction.php',1).'?lang='.rawurlencode($langs->defaultlang);
        $html='<h3>'.$langs->trans('NorwayPurchasePostingCorrectionTitle').'</h3><p>'.$langs->trans('NorwayPurchasePostingCorrectionScope').'</p>';
        $html.='<form method="post" action="'.$endpoint.'"><input type="hidden" name="lang" value="'.dol_escape_htmltag($langs->defaultlang).'"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="id" value="'.$id.'"><input type="hidden" name="action" value="previewcorrection"><p><label>'.$langs->trans('NorwayPurchasePostingJournal').' <select name="journal" required><option value="">'.$langs->trans('NorwaySavedMappingChoose').'</option>';
        $r=$db->query('SELECT rowid,code,label FROM '.$p.'accounting_journal WHERE entity='.$entity.' AND nature=3 AND active=1 ORDER BY code');
        if (!$r) { return '<p class="warning">'.$langs->trans('NorwayPurchasePostingFailed').'</p>'; }
        while ($journal=$db->fetch_object($r)) { $html.='<option value="'.(int)$journal->rowid.'">'.dol_escape_htmltag($journal->code.' — '.$journal->label).'</option>'; }
        $html.='</select></label></p><p><label>'.$langs->trans('NorwayPurchasePostingCorrectionDateLabel').' <input type="date" name="correction_date" required></label></p><p><label>'.$langs->trans('NorwayPurchasePostingCorrectionReasonLabel').' <textarea name="correction_reason" rows="3" maxlength="2000" required></textarea></label></p><button class="button" type="submit">'.$langs->trans('NorwayPurchasePostingCorrectionPreview').'</button></form>';
        if ($pending && ($pending['mode'] ?? '')==='correction') {
            $preview=$pending['preview'];$html.='<h4>'.$langs->trans('NorwayPurchasePostingCorrectionPreview').'</h4><p>'.dol_escape_htmltag($preview['date'].' / '.$preview['reason']).'</p><p>'.$langs->trans('NorwayDeductionPostingRevision').' #'.(int)$preview['trail']['assessment_revision'].' → #'.(int)$preview['plan']['assessment']['revision'].'</p>';
            foreach (array('CorrectionReversal'=>$preview['reversals'],'CorrectionReplacement'=>$preview['plan']['rows']) as $heading=>$rows) {
                $html.='<h4>'.$langs->trans('NorwayPurchasePosting'.$heading).'</h4><div class="div-table-responsive"><table class="noborder centpercent"><tr>';
                foreach (array('Line','Account','Debit','Credit') as $column) { $html.='<th>'.$langs->trans('NorwayPurchasePosting'.$column).'</th>'; }$html.='</tr>';
                foreach ($rows as $row) { $html.='<tr><td>'.($row['line']===0 ? '—' : $row['line']).'</td><td>'.dol_escape_htmltag($row['account'].' — '.$row['label']).'</td><td>'.dol_escape_htmltag($row['debit']).'</td><td>'.dol_escape_htmltag($row['credit']).'</td></tr>'; }$html.='</table></div>';
            }
            $html.='<form method="post" action="'.$endpoint.'"><input type="hidden" name="lang" value="'.dol_escape_htmltag($langs->defaultlang).'"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="id" value="'.$id.'"><input type="hidden" name="action" value="writecorrection"><input type="hidden" name="nonce" value="'.$pending['nonce'].'"><p><label><input type="checkbox" name="unreported" value="1" required> '.$langs->trans('NorwayPurchasePostingCorrectionUnreportedConfirm').'</label></p><p><label><input type="checkbox" name="confirmed" value="1" required> '.$langs->trans('NorwayPurchasePostingCorrectionConfirm').'</label></p><button class="button" type="submit">'.$langs->trans('NorwayPurchasePostingCorrectionWrite').'</button></form>';
        }
        return $html;
    }
    private static function postingContexts($db,$object,$user,$langs,$entity)
    {
        try {
            $entries=NorwayPostingEvidence::report($db,$entity,(int)$object->id,$user,null,'supplier_invoice');
            if (!$entries) { return ''; }
            $html='<details id="norway-deduction-postings"><summary>'.$langs->trans('NorwayDeductionPostingTitle').'</summary><p>'.$langs->trans('NorwayDeductionPostingScope').'</p><div class="div-table-responsive"><table class="noborder centpercent"><tr>';
            foreach (array('Posting','Status','Revision','Time') as $column) { $html.='<th>'.$langs->trans('NorwayDeductionPosting'.$column).'</th>'; }$html.='</tr>';
            foreach ($entries as $entry) {
                $context=$entry['data']['deduction_context'] ?? null;
                $status=$context['status'] ?? 'NotCaptured';$revision=$context['assessment']['revision'] ?? null;
                $html.='<tr><td>'.(int)$entry['id'].'</td><td>'.$langs->trans('NorwayDeductionPosting'.$status).'</td><td>'.($revision===null ? '—' : '#'.(int)$revision).'</td><td>'.dol_escape_htmltag($entry['captured_at']).' UTC</td></tr>';
            }
            return $html.'</table></div></details>';
        } catch (Throwable $error) { return '<p class="warning">'.$langs->trans('NorwayPurchaseUnavailable').'</p>'; }
    }
    private static function lineForm($db,$entity,$id,$user,$langs,$endpoint)
    {
        try { $basis=NorwayPurchaseDeduction::basis($db,$entity,$id,$user); }
        catch (Throwable $error) { return ''; }
        foreach ($basis['lines'] as $line) { if (!in_array((float)$line['tva_tx'],array(25.0,15.0,12.0),true)) { return '<p>'.$langs->trans('NorwayDeductionLinesScope').'</p>'; } }
        $html='<details id="norway-line-assessment"><summary>'.$langs->trans('NorwayDeductionLinesTitle').'</summary><p>'.$langs->trans('NorwayDeductionLinesScope').'</p><form method="post" action="'.$endpoint.'"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="id" value="'.$id.'"><input type="hidden" name="action" value="previewlines">';
        foreach ($basis['lines'] as $index=>$line) {
            $lineId=(int)$line['rowid'];
            $html.='<fieldset><legend>'.$langs->trans('NorwayDeductionLine').' '.($index+1).' / #'.$lineId.' — '.dol_escape_htmltag(dol_trunc(strip_tags($line['description']),80)).'</legend><p>'.$langs->trans('NorwayDeductionLinesRate').' '.(int)$line['tva_tx'].' % / '.$langs->trans('NorwayDeductionVat').' '.dol_escape_htmltag(substr(NorwayInvoiceReconciliation::display(NorwayInvoiceReconciliation::amount($line['total_tva'])),0,-6)).'</p><p><label>'.$langs->trans('NorwayPurchaseProfile').' <select name="line_mode['.$lineId.']" required><option value="">'.$langs->trans('NorwaySavedMappingChoose').'</option>';
            foreach (array('full','partial','none') as $mode) { $html.='<option value="'.$mode.'">'.$langs->trans('NorwayPurchase'.ucfirst($mode)).'</option>'; }
            $html.='</select></label></p><p><label>'.$langs->trans('NorwayPurchaseShare').' <input type="number" name="line_percentage['.$lineId.']" min="0.01" max="99.99" step="0.01"></label></p><p><label>'.$langs->trans('NorwayDeductionReason').' <textarea name="line_reason['.$lineId.']" rows="2" maxlength="2000" required></textarea></label></p></fieldset>';
        }
        return $html.'<p><label>'.$langs->trans('NorwayDeductionLinesSummary').' <textarea name="reason" rows="2" maxlength="2000" required></textarea></label></p><button class="button" type="submit">'.$langs->trans('NorwayEvidencePreview').'</button></form></details>';
    }
    private static function choices($db,$object,$user,$langs,$entity,&$profile,&$percentage)
    {
        $html='<h3>'.$langs->trans('NorwayDeductionTitle').'</h3><p>'.$langs->trans('NorwayDeductionScope').'</p>';
        $id=(int)$object->id;
        $render=static function ($data) use ($langs) {
            $choice=$data['choice'];
            $lineMode=$choice['profile']==='purchase_lines';
            $out=$lineMode ? '<p>'.$langs->trans('NorwayDeductionLinesTitle').'</p><p>'.nl2br(dol_escape_htmltag($choice['reason'])).'</p>' : '<p>'.NorwayPurchaseAllocation::profiles()[$choice['profile']]['rate'].' % – '.$langs->trans('NorwayPurchase'.ucfirst(NorwayPurchaseAllocation::profiles()[$choice['profile']]['deduction'])).' / '.number_format($choice['basis_points']/100,2,'.','').' %</p><p>'.nl2br(dol_escape_htmltag($choice['reason'])).'</p>';
            $out.='<div class="div-table-responsive"><table class="noborder centpercent"><tr>';
            foreach (array('Line','Vat','Deductible','NonDeductible') as $heading) { $out.='<th>'.$langs->trans('NorwayDeduction'.$heading).'</th>'; }$out.=($lineMode ? '<th>'.$langs->trans('NorwayDeductionLinesRate').'</th><th>'.$langs->trans('NorwayPurchaseShare').'</th><th>'.$langs->trans('NorwayDeductionReason').'</th>' : '').'</tr>';
            foreach ($data['lines'] as $line) { $out.='<tr><td>'.$line['line'].'</td><td>'.dol_escape_htmltag(substr($line['vat'],0,-6)).'</td><td>'.dol_escape_htmltag(substr($line['deductible'],0,-6)).'</td><td>'.dol_escape_htmltag(substr($line['non_deductible'],0,-6)).'</td>'.($lineMode ? '<td>'.(int)$line['rate'].' %</td><td>'.number_format($line['basis_points']/100,2,'.','').' %</td><td>'.nl2br(dol_escape_htmltag($line['reason'])).'</td>' : '').'</tr>'; }
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
            $endpoint=dol_buildpath('/norway/admin/purchasededuction.php',1).'?lang='.rawurlencode($langs->defaultlang);
            $html.='<form method="post" action="'.$endpoint.'"><input type="hidden" name="lang" value="'.dol_escape_htmltag($langs->defaultlang).'"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="id" value="'.$id.'"><input type="hidden" name="action" value="preview"><p><label>'.$langs->trans('NorwayPurchaseProfile').' <select name="profile" required><option value="">'.$langs->trans('NorwaySavedMappingChoose').'</option>';
            foreach (NorwayPurchaseAllocation::profiles() as $key=>$rule) { $html.='<option value="'.$key.'">'.$rule['rate'].' % – '.$langs->trans('NorwayPurchase'.ucfirst($rule['deduction'])).'</option>'; }
            $html.='</select></label></p><p><label>'.$langs->trans('NorwayPurchaseShare').' <input type="number" name="percentage" min="0.01" max="99.99" step="0.01"></label></p><p><label>'.$langs->trans('NorwayDeductionReason').' <textarea name="reason" rows="3" maxlength="2000" required></textarea></label></p><button class="button" type="submit">'.$langs->trans('NorwayEvidencePreview').'</button></form>';
            $html.=self::lineForm($db,$entity,$id,$user,$langs,$endpoint);
            $pending=$_SESSION['norway_purchase_deduction'] ?? null;
            if ($pending && $pending['entity']===$entity && $pending['id']===$id && $pending['user']===(int)$user->id && $pending['expires']>=time()) {
                $html.='<h4>'.$langs->trans('NorwayEvidencePreview').'</h4>'.$render($pending['preview']);
                $html.='<form method="post" action="'.$endpoint.'"><input type="hidden" name="lang" value="'.dol_escape_htmltag($langs->defaultlang).'"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="id" value="'.$id.'"><input type="hidden" name="action" value="save"><input type="hidden" name="nonce" value="'.$pending['nonce'].'"><p><label><input type="checkbox" name="confirmed" value="1" required> '.$langs->trans('NorwayDeductionConfirm').'</label></p><button class="button" type="submit">'.$langs->trans('NorwayEvidenceSave').'</button></form>';
            }
        } catch (Throwable $error) { $html.='<div class="warning">'.$langs->trans('NorwayDeductionFailed').'</div>'; }
        return $html;
    }

}
