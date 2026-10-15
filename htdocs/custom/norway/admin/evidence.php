<?php
// SPDX-License-Identifier: GPL-3.0-or-later
define('CSRFCHECK_WITH_TOKEN',1);
require '../../../main.inc.php';
if (GETPOST('errorcode','aZ09')==='InvalidToken') { httponly_accessforbidden('Invalid security token'); }
if (!$user->admin || !isModEnabled('norway') || !isModEnabled('accounting') || !$user->hasRight('facture','lire') || !$user->hasRight('accounting','mouvements','lire')) { accessforbidden(); }
require_once __DIR__.'/../class/NorwayInvoiceEvidence.php';
$langs->load('norway@norway'); header('Cache-Control: no-store');
$id=GETPOSTINT('id'); $revision=GETPOSTINT('mapping_revision'); $action=GETPOST('action','aZ09');
if ($_SERVER['REQUEST_METHOD']==='POST' && $action==='saveevidence') {
    $pending=$_SESSION['norway_evidence'] ?? null; unset($_SESSION['norway_evidence']);
    try {
        if (!$pending || $pending['expires']<time() || $pending['entity']!==(int)$conf->entity || $pending['user']!==(int)$user->id
            || $pending['id']!==$id || !hash_equals($pending['nonce'],GETPOST('preview_nonce','aZ09')) || GETPOST('confirmed','aZ09')!=='1') { throw new InvalidArgumentException('Confirmation'); }
        NorwayInvoiceEvidence::save($db,(int)$conf->entity,$id,$pending['mapping_revision'],$pending['fingerprint'],$user);
        setEventMessages($langs->trans('NorwayEvidenceSaved'),null,'mesgs');
    } catch (Throwable $error) { setEventMessages($langs->trans('NorwayEvidenceFailed'),null,'errors'); }
    header('Location: '.dol_buildpath('/norway/admin/evidence.php',1).'?id='.$id); exit;
}
llxHeader('',$langs->trans('NorwayEvidenceTitle'));
print '<div class="norway-page">'.load_fiche_titre($langs->trans('NorwayEvidenceTitle'),'','bill');
print '<div class="warning">'.$langs->trans('NorwayEvidenceScope').'</div>';
if ($id>0) {
    $visible=$db->query('SELECT rowid FROM '.MAIN_DB_PREFIX.'facture WHERE rowid='.(int)$id.' AND entity='.(int)$conf->entity);
    if ($visible && $db->num_rows($visible)) { print '<p><a href="'.DOL_URL_ROOT.'/compta/facture/card.php?facid='.(int)$id.'">'.$langs->trans('NorwayInvoiceBack').'</a></p>'; }
}
$render=static function ($data) use ($langs) {
    print '<p>'.dol_escape_htmltag($data['invoice']['ref']).' / '.$langs->trans('NorwaySavedMappingRevision').' #'.(int)$data['mapping']['revision'].' / '.count($data['lines']).' '.$langs->trans('NorwayEvidenceLines').' / '.count($data['ledger']).' '.$langs->trans('NorwayEvidenceLedger').'</p>';
    print '<div class="div-table-responsive"><table class="noborder centpercent"><tr><th>'.$langs->trans('NorwayAmountsLine').'</th><th>'.$langs->trans('NorwayAmountsTaxCode').'</th><th>'.$langs->trans('NorwayAmountsTaxFlags').'</th><th>'.$langs->trans('NorwayMappingResult').'</th><th>'.$langs->trans('NorwayMappingTarget').'</th></tr>';
    foreach ($data['lines'] as $i=>$line) {
        $comparison=$data['mapping']['comparison']['rows'][$i];
        print '<tr><td>'.(int)$line['rowid'].'</td><td>'.dol_escape_htmltag($line['vat_src_code'] ?? '—').'</td><td>'.dol_escape_htmltag($line['info_bits'] ?? '—').'</td><td>'.$langs->trans('NorwaySavedMapping'.$comparison['status']).'</td><td>'.dol_escape_htmltag($comparison['rule']['target'] ?? '—').'</td></tr>';
    }
    print '</table></div><details><summary>'.$langs->trans('NorwayEvidenceDetails').'</summary><pre style="max-width:100%;overflow:auto">'.dol_escape_htmltag(json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)).'</pre></details>';
};
try {
    $r=$id>0 ? $db->query('SELECT ref FROM '.$db->prefix().'facture WHERE entity='.(int)$conf->entity.' AND rowid='.$id) : false;
    $invoice=$r ? $db->fetch_object($r) : null;
    if (!$invoice) { throw new InvalidArgumentException('Invoice'); }
    print '<h2>'.dol_escape_htmltag($invoice->ref).'</h2><p><a href="'.dol_buildpath('/norway/admin/invoice.php',1).'?id='.$id.'">'.$langs->trans('NorwayInvoiceCheck').'</a></p>';
    $state=NorwayTaxMappings::read($db,(int)$conf->entity);
    print '<form method="post"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="id" value="'.$id.'"><input type="hidden" name="action" value="previewevidence"><p><label>'.$langs->trans('NorwaySavedMappingRevision').' <select name="mapping_revision" required><option value="">'.$langs->trans('NorwaySavedMappingChoose').'</option>';
    foreach ($state['record']['events'] as $event) { print '<option value="'.(int)$event['revision'].'"'.($revision===$event['revision'] ? ' selected' : '').'>'.dol_escape_htmltag('#'.$event['revision'].' / '.$event['at']).'</option>'; }
    print '</select></label></p><button class="button" type="submit">'.$langs->trans('NorwayEvidencePreview').'</button></form>';
    if ($_SERVER['REQUEST_METHOD']==='POST' && $action==='previewevidence') {
        unset($_SESSION['norway_evidence']);
        $preview=NorwayInvoiceEvidence::preview($db,(int)$conf->entity,$id,$revision,$user);
        $render($preview['basis']);
        $nonce=bin2hex(random_bytes(16));
        $_SESSION['norway_evidence']=array('entity'=>(int)$conf->entity,'user'=>(int)$user->id,'id'=>$id,'mapping_revision'=>$revision,'expires'=>time()+600,'nonce'=>$nonce,'fingerprint'=>$preview['fingerprint']);
        print '<form method="post"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="id" value="'.$id.'"><input type="hidden" name="action" value="saveevidence"><input type="hidden" name="preview_nonce" value="'.$nonce.'"><p><label><input type="checkbox" name="confirmed" value="1" required> '.$langs->trans('NorwayEvidenceConfirmScope').'</label></p><button class="button" type="submit">'.$langs->trans('NorwayEvidenceSave').'</button></form>';
    }
    $copyRevision=GETPOSTINT('copy_revision');
    if ($copyRevision>0) {
        print '<h2>'.$langs->trans('NorwayEvidenceCompare').' #'.$copyRevision.'</h2><p>'.$langs->trans('NorwayEvidenceCompareScope').'</p>';
        try {
            $difference=NorwayInvoiceEvidence::compare($db,(int)$conf->entity,$id,$copyRevision,$user);
            if (!$difference['changes'] && !$difference['mapping_changed'] && !$difference['organisation_changed']) { print '<p>'.$langs->trans('NorwayEvidenceUnchanged').'</p>'; }
            else {
                if ($difference['mapping_changed'] || $difference['organisation_changed']) { print '<div class="warning">'.$langs->trans('NorwayEvidenceContextChanged').'</div>'; }
                print '<div class="div-table-responsive"><table class="noborder centpercent"><tr>';
                foreach (array('Group','Field','Change','Before','After') as $heading) { print '<th>'.$langs->trans('NorwayEvidence'.$heading).'</th>'; }
                print '</tr>';
                foreach ($difference['changes'] as $change) {
                    $value=static function ($v) { return $v===null ? '—' : dol_escape_htmltag(dol_trunc((string)$v,300)); };
                    print '<tr><td>'.$langs->trans('NorwayEvidenceGroup'.$change['group']).' #'.(int)$change['id'].'</td><td>'.$langs->trans('NorwayEvidenceField'.$change['field']).'</td><td>'.$langs->trans('NorwayEvidence'.$change['status']).'</td><td>'.$value($change['before']).'</td><td>'.$value($change['after']).'</td></tr>';
                }
                print '</table></div>';
            }
        } catch (Throwable $error) { print '<div class="warning">'.$langs->trans('NorwayEvidenceCompareUnavailable').'</div>'; }
    }
    print '<h2>'.$langs->trans('NorwayEvidenceHistory').'</h2>';
    $history=NorwayInvoiceEvidence::history($db,(int)$conf->entity,$id,$user);
    if (!$history) { print '<p>'.$langs->trans('NorwayEvidenceEmpty').'</p>'; }
    foreach (array_reverse($history) as $copy) {
        print '<details><summary>'.dol_escape_htmltag('#'.$copy['revision'].' / '.$copy['captured_at'].' UTC / '.$langs->trans('NorwayEvidenceUser').' #'.$copy['fk_user']).'</summary>';
        print '<p><a href="'.dol_buildpath('/norway/admin/evidence.php',1).'?id='.$id.'&amp;copy_revision='.(int)$copy['revision'].'">'.$langs->trans('NorwayEvidenceCompare').'</a></p>';
        $render($copy['data']); print '</details>';
    }
} catch (Throwable $error) { print '<div class="error">'.$langs->trans('NorwayEvidenceFailed').'</div>'; }
print '</div>'; llxFooter(); $db->close();
