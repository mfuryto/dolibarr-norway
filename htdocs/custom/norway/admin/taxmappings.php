<?php
// SPDX-License-Identifier: GPL-3.0-or-later
define('CSRFCHECK_WITH_TOKEN',1);
require '../../../main.inc.php';
if (GETPOST('errorcode','aZ09')==='InvalidToken') { httponly_accessforbidden('Invalid security token'); }
if (!$user->admin || !isModEnabled('norway')) { accessforbidden(); }
require_once __DIR__.'/../class/NorwayTaxMappings.php';
$langs->load('norway@norway'); header('Cache-Control: no-store');
$action=GETPOST('action','aZ09');
if ($_SERVER['REQUEST_METHOD']==='POST' && $action==='savemapping') {
    $pending=$_SESSION['norway_tax_mapping'] ?? null; unset($_SESSION['norway_tax_mapping']);
    try {
        if (!$pending || $pending['expires']<time() || $pending['entity']!==(int)$conf->entity || $pending['user']!==(int)$user->id
            || !hash_equals($pending['nonce'],GETPOST('preview_nonce','aZ09')) || GETPOST('confirmed','aZ09')!=='1') { throw new InvalidArgumentException('Confirmation'); }
        NorwayTaxMappings::save($db,$conf->entity,$pending['input'],$pending['fingerprint'],$user);
        setEventMessages($langs->trans('NorwayMappingsSaved'),null,'mesgs');
    } catch (Throwable $error) { setEventMessages($langs->trans('NorwayMappingsFailed'),null,'errors'); }
    header('Location: '.dol_buildpath('/norway/admin/taxmappings.php',1)); exit;
}
llxHeader('',$langs->trans('NorwayMappingsTitle'));
print '<div class="norway-page">'.load_fiche_titre($langs->trans('NorwayMappingsTitle'),'','setup');
print '<div class="warning">'.$langs->trans('NorwayMappingsScope').'</div>';
print '<p><a target="_blank" rel="noopener noreferrer" href="'.NorwayTaxReference::SOURCE.'">'.$langs->trans('NorwayTaxReferenceSource').'</a></p>';
$showRule=static function($rule) use ($langs) {
    return dol_escape_htmltag('#'.$rule['id'].' / '.$rule['source'].' → '.$rule['target'].' / '.$rule['start'].' – '.$rule['end']).' / '.$langs->trans('NorwayMappings'.ucfirst($rule['direction'])).' / '.$langs->trans($rule['retire'] ? 'NorwayMappingsRetired' : 'NorwayMappingsActive');
};
try {
    $state=NorwayTaxMappings::read($db,$conf->entity);
    print '<h2>'.$langs->trans('NorwayMappingsCurrent').'</h2>';
    if (!$state['active']) { print '<p>'.$langs->trans('NorwayMappingsEmpty').'</p>'; }
    print '<ul>';
    foreach ($state['active'] as $rule) {
        print '<li>'.$showRule($rule);
        if (!$rule['retire']) { print ' <a href="'.dol_buildpath('/norway/admin/taxmappings.php',1).'?rule='.(int)$rule['id'].'">'.$langs->trans('NorwayMappingsEdit').'</a>'; }
        print '</li>';
    }
    print '</ul><p>'.$langs->trans('NorwayMappingsCapacity').' '.count($state['record']['events']).'/'.NorwayTaxMappings::LIMIT.'</p>';
    $id=GETPOSTINT('rule');
    $selected=$id>0 ? ($state['active'][$id] ?? null) : null;
    if ($id>0 && (!$selected || $selected['retire'])) { throw new InvalidArgumentException('Unknown rule'); }
    $input=$selected ?: array('id'=>0,'source'=>'','target'=>'','start'=>'','end'=>'','direction'=>'sale','retire'=>false);
    if ($_SERVER['REQUEST_METHOD']==='POST' && $action==='previewmapping') {
        unset($_SESSION['norway_tax_mapping']);
        $input=NorwayTaxMappings::input(GETPOST('source','none'),GETPOST('target','none'),GETPOST('start','none'),GETPOST('end','none'),
            GETPOST('direction','aZ09'),GETPOSTINT('rule'),GETPOST('retire','aZ09')==='1');
        $preview=NorwayTaxMappings::preview($db,$conf->entity,$input);
        $nonce=bin2hex(random_bytes(16));
        $_SESSION['norway_tax_mapping']=array('entity'=>(int)$conf->entity,'user'=>(int)$user->id,'expires'=>time()+600,
            'nonce'=>$nonce,'input'=>$preview['input'],'fingerprint'=>$preview['fingerprint']);
        print '<h2>'.$langs->trans('NorwayMappingsPreview').'</h2><p>'.$showRule($preview['input']).'</p>';
        if ($input['id']>0) { print '<p>'.$langs->trans('NorwayMappingsPrevious').': '.$showRule($state['active'][$input['id']]).'</p>'; }
        print '<form method="post"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="savemapping"><input type="hidden" name="preview_nonce" value="'.$nonce.'">';
        print '<label><input type="checkbox" name="confirmed" value="1" required> '.$langs->trans('NorwayMappingsConfirm').'</label><p><button class="button" type="submit">'.$langs->trans('NorwayMappingsSave').'</button></p></form>';
    }
    print '<h2>'.$langs->trans($id>0 ? 'NorwayMappingsEdit' : 'NorwayMappingsNew').'</h2>';
    print '<form method="post"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="previewmapping"><input type="hidden" name="rule" value="'.(int)$input['id'].'">';
    foreach (array('source','target','start','end') as $field) {
        $date=in_array($field,array('start','end'),true);
        print '<p><label>'.$langs->trans('NorwayMapping'.ucfirst($field)).' <input required type="'.($date ? 'date' : 'text').'" name="'.$field.'"'.($date ? '' : ' maxlength="10"').' value="'.dol_escape_htmltag($input[$field]).'"></label></p>';
    }
    print '<p><label>'.$langs->trans('NorwayMappingsDirection').' <select name="direction">';
    foreach (array('sale','purchase') as $direction) { print '<option value="'.$direction.'"'.($input['direction']===$direction ? ' selected' : '').'>'.$langs->trans('NorwayMappings'.ucfirst($direction)).'</option>'; }
    print '</select></label></p>';
    if ($id>0) { print '<p><label><input type="checkbox" name="retire" value="1"> '.$langs->trans('NorwayMappingsRetire').'</label></p>'; }
    print '<button class="button" type="submit">'.$langs->trans('NorwayMappingsPreview').'</button></form>';
    print '<p><a href="'.dol_buildpath('/norway/admin/taxmappings.php',1).'">'.$langs->trans('NorwayMappingsNew').'</a></p>';
    print '<details><summary>'.$langs->trans('NorwayMappingsHistory').'</summary><ol>';
    foreach ($state['record']['events'] as $event) {
        print '<li>'.$showRule($event).' / '.$langs->trans('NorwayMappingsRevision').' '.(int)$event['revision'].' / '.dol_escape_htmltag($event['at']).' / '.$langs->trans('NorwayMappingsUser').' '.(int)$event['user'].'</li>';
    }
    print '</ol></details>';
} catch (Throwable $error) { print '<div class="error">'.$langs->trans('NorwayMappingsFailed').'</div>'; }
print '<p><a href="'.dol_buildpath('/norway/admin/invoice.php',1).'">'.$langs->trans('NorwayInvoiceCheck').'</a></p></div>';
llxFooter(); $db->close();
