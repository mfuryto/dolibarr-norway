<?php
// SPDX-License-Identifier: GPL-3.0-or-later
define('CSRFCHECK_WITH_TOKEN',1);
require '../../../main.inc.php';
if (GETPOST('errorcode','aZ09')==='InvalidToken') { httponly_accessforbidden('Invalid security token'); }
if (!$user->admin || $user->socid || !isModEnabled('norway') || !isModEnabled('accounting') || !$user->hasRight('facture','lire') || !$user->hasRight('accounting','mouvements','lire')) { accessforbidden(); }
require_once __DIR__.'/../class/NorwaySalesTaxBasis.php';
require_once __DIR__.'/../class/NorwayPostingEvidence.php';
$langs->load('norway@norway');header('Cache-Control: no-store');
$id=GETPOSTINT('id');$action=GETPOST('action','aZ09');$key='norway_sales_basis';
if ($_SERVER['REQUEST_METHOD']==='POST' && $action==='save') {
    $pending=$_SESSION[$key] ?? null;unset($_SESSION[$key]);
    try {
        if (!$pending || $pending['expires']<time() || $pending['entity']!==(int)$conf->entity || $pending['user']!==(int)$user->id || $pending['id']!==$id
            || GETPOST('confirmed','aZ09')!=='1' || !hash_equals($pending['nonce'],GETPOST('nonce','aZ09'))) { throw new InvalidArgumentException('Confirmation'); }
        NorwaySalesTaxBasis::save($db,(int)$conf->entity,$id,$user,$pending['preview']['choices'],$pending['preview']['fingerprint']);
        setEventMessages($langs->trans('NorwaySalesBasisSaved'),null,'mesgs');
    } catch (Throwable $error) { setEventMessages($langs->trans('NorwaySalesBasisFailed'),null,'errors'); }
    header('Location: '.dol_buildpath('/norway/admin/salestaxbasis.php',1).'?id='.$id);exit;
}
llxHeader('',$langs->trans('NorwaySalesBasisTitle'));
print '<div class="norway-page">'.load_fiche_titre($langs->trans('NorwaySalesBasisTitle'),'','bill');
print '<div class="warning">'.$langs->trans('NorwaySalesBasisScope').'</div>';
$category=static function ($target) use ($langs) { return $langs->trans('NorwayZeroVat'.array('5'=>'DomesticExempt','6'=>'OutsideLaw','7'=>'NotTurnover')[$target]); };
$render=static function ($choices,$source) use ($langs,$category) {
    $descriptions=array();foreach ($source['lines'] as $line) { $descriptions[(int)$line['rowid']]=$line['description']; }
    foreach ($choices as $choice) {
        print '<div class="norway-sales-basis-line"><h3>'.$langs->trans('NorwayAmountsLine').' #'.(int)$choice['line'].' · '.$langs->trans('NorwayMappingTarget').' '.dol_escape_htmltag($choice['target']).' · '.dol_escape_htmltag($category($choice['target'])).'</h3><p>'.nl2br(dol_escape_htmltag($descriptions[$choice['line']] ?? '')).'</p>';
        foreach (array('reason'=>'NorwaySalesBasisReason','reference'=>'NorwaySalesBasisReference') as $field=>$label) {
            print '<p><strong>'.$langs->trans($label).'</strong><br>'.nl2br(dol_escape_htmltag($choice[$field])).'</p>';
        }print '</div>';
    }
};
try {
    $history=NorwaySalesTaxBasis::history($db,(int)$conf->entity,$id,$user);
    $basis=null;try { $basis=NorwaySalesTaxBasis::basis($db,(int)$conf->entity,$id,$user); } catch (InvalidArgumentException $unsupported) { }
    $visible=$db->query('SELECT ref FROM '.$db->prefix().'facture WHERE entity='.(int)$conf->entity.' AND rowid='.$id);
    $invoice=$visible ? $db->fetch_object($visible) : null;if (!$invoice) { throw new InvalidArgumentException('Invoice'); }
    print '<h2>'.dol_escape_htmltag($invoice->ref).'</h2><p><a href="'.DOL_URL_ROOT.'/compta/facture/card.php?facid='.$id.'">'.$langs->trans('NorwayInvoiceBack').'</a></p>';
    $last=$history ? end($history) : null;
    print '<p class="norway-sales-basis-status">'.$langs->trans(!$last ? 'NorwaySalesBasisMissing' : ($basis===$last['data']['basis'] ? 'NorwaySalesBasisCurrent' : 'NorwaySalesBasisChanged')).'</p>';
    if ($basis && $user->hasRight('facture','creer')) {
        $eligible=NorwaySalesTaxBasis::eligible($basis,(int)$conf->entity);$descriptions=array();foreach ($basis['lines'] as $line) { $descriptions[(int)$line['rowid']]=$line['description']; }$previous=array();foreach ($last['data']['choices'] ?? array() as $choice) { $previous[$choice['line']]=$choice; }
        print '<form method="post" class="norway-sales-basis-form"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="id" value="'.$id.'"><input type="hidden" name="action" value="preview">';
        foreach ($eligible as $line=>$target) {
            print '<fieldset><legend>'.$langs->trans('NorwayAmountsLine').' #'.$line.' · '.$langs->trans('NorwayMappingTarget').' '.dol_escape_htmltag($target).' · '.dol_escape_htmltag($category($target)).'</legend><p>'.nl2br(dol_escape_htmltag($descriptions[$line])).'</p>';
            print '<p><label>'.$langs->trans('NorwaySalesBasisReason').'<br><textarea name="reason['.$line.']" rows="3" minlength="10" maxlength="2000" required>'.dol_escape_htmltag($previous[$line]['reason'] ?? '').'</textarea></label></p>';
            print '<p><label>'.$langs->trans('NorwaySalesBasisReference').'<br><textarea name="reference['.$line.']" rows="2" minlength="3" maxlength="500" required>'.dol_escape_htmltag($previous[$line]['reference'] ?? '').'</textarea></label></p></fieldset>';
        }
        print '<button class="button" type="submit">'.$langs->trans('NorwayEvidencePreview').'</button></form>';
        if ($_SERVER['REQUEST_METHOD']==='POST' && $action==='preview') {
            unset($_SESSION[$key]);$reasons=GETPOST('reason','array');$refs=GETPOST('reference','array');$ids=array_keys($eligible);sort($ids,SORT_NUMERIC);
            foreach (array($reasons,$refs) as $values) { if (!is_array($values)) { throw new InvalidArgumentException('Inputs'); }$keys=array_keys($values);sort($keys,SORT_NUMERIC);if ($keys!==$ids) { throw new InvalidArgumentException('Coverage'); } }
            $inputs=array();foreach ($eligible as $line=>$target) { if (!is_string($reasons[$line]) || !is_string($refs[$line])) { throw new InvalidArgumentException('Text'); }$inputs[]=array('line'=>$line,'target'=>$target,'reason'=>trim($reasons[$line]),'reference'=>trim($refs[$line])); }
            $preview=NorwaySalesTaxBasis::preview($db,(int)$conf->entity,$id,$user,$inputs);$nonce=bin2hex(random_bytes(16));
            $_SESSION[$key]=array('entity'=>(int)$conf->entity,'user'=>(int)$user->id,'id'=>$id,'expires'=>time()+600,'nonce'=>$nonce,'preview'=>$preview);
            print '<h2>'.$langs->trans('NorwayEvidencePreview').'</h2>';$render($preview['choices'],$preview['basis']);
            print '<form method="post"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="id" value="'.$id.'"><input type="hidden" name="action" value="save"><input type="hidden" name="nonce" value="'.$nonce.'"><p><label><input type="checkbox" name="confirmed" value="1" required> '.$langs->trans('NorwaySalesBasisConfirm').'</label></p><button class="button" type="submit">'.$langs->trans('NorwayEvidenceSave').'</button></form>';
        }
    } elseif (!$basis) { print '<div class="warning">'.$langs->trans('NorwaySalesBasisUnsupported').'</div>'; }
    print '<h2>'.$langs->trans('NorwaySalesPostingTitle').'</h2><p>'.$langs->trans('NorwaySalesPostingScope').'</p>';
    try {
        $observations=NorwayPostingEvidence::report($db,(int)$conf->entity,$id,$user);
        if (!$observations) { print '<p>'.$langs->trans('NorwayInvoiceLedgerEmpty').'</p>'; }
        foreach ($observations as $observation) {
            $context=$observation['data']['sales_basis_context'] ?? null;
            print '<details class="norway-sales-posting"><summary>'.$langs->trans('NorwayPostingID').' #'.(int)$observation['id'].' · '.dol_escape_htmltag($observation['captured_at']).' UTC · '.$langs->trans('NorwaySalesPosting'.($context ? $context['status'] : 'NotRetained')).'</summary>';
            print '<p>'.$langs->trans('NorwayPosting'.$observation['status']).'</p>';
            if ($context) {
                $entry=$context['assessment'];print '<p>'.$langs->trans('NorwaySalesPostingRevision').' #'.(int)$entry['revision'].' · '.dol_escape_htmltag($entry['captured_at']).' UTC · '.$langs->trans('NorwayEvidenceUser').' #'.(int)$entry['fk_user'].'</p>';
                $render($entry['data']['choices'],$entry['data']['basis']);
            }print '</details>';
        }
    } catch (Throwable $error) { print '<div class="warning">'.$langs->trans('NorwayPostingUnavailable').'</div>'; }
    print '<h2>'.$langs->trans('NorwayEvidenceHistory').'</h2>';
    foreach (array_reverse($history) as $entry) {
        print '<details><summary>'.dol_escape_htmltag('#'.$entry['revision'].' / '.$entry['captured_at'].' UTC / '.$langs->trans('NorwayEvidenceUser').' #'.$entry['fk_user']).'</summary>';
        $render($entry['data']['choices'],$entry['data']['basis']);
        print '<details><summary>'.$langs->trans('NorwayEvidenceDetails').'</summary><pre style="max-width:100%;overflow:auto">'.dol_escape_htmltag(json_encode($entry['data'],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)).'</pre></details></details>';
    }
} catch (Throwable $error) { unset($_SESSION[$key]);print '<div class="error">'.$langs->trans('NorwaySalesBasisFailed').'</div>'; }
print '</div>';llxFooter();$db->close();
