<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require_once __DIR__.'/NorwayVatHistory.php';
class NorwayVatHistoryView
{
    public static function handle($db,$entity,$org,$user,$langs)
    {
        $action=GETPOST('action','aZ09');
        if($_SERVER['REQUEST_METHOD']!=='POST'||!in_array($action,array('previewhistory','savehistory'),true)){return;}
        if($action==='savehistory'){
            $pending=$_SESSION['norway_vat_history']??null;unset($_SESSION['norway_vat_history']);
            try{
                if(!$pending||$pending['expires']<time()||$pending['entity']!==$entity||$pending['user']!==(int)$user->id
                    ||!hash_equals($pending['nonce'],GETPOST('preview_nonce','aZ09'))||GETPOST('confirmed','aZ09')!=='1'){
                    throw new InvalidArgumentException('VAT history confirmation');
                }
                NorwayVatHistory::save($db,$entity,$org,$pending['input'],$pending['fingerprint'],$user);
                setEventMessages($langs->trans('NorwayVatHistorySaved'),null,'mesgs');
            }catch(Throwable $error){setEventMessages($langs->trans('NorwayVatHistoryFailed'),null,'errors');}
            header('Location: '.dol_buildpath('/norway/admin/registration.php',1).'?mainmenu=accountancy&leftmenu=accountancy_admin_norway');exit;
        }
        unset($_SESSION['norway_vat_history']);
        try{
            $input=NorwayVatHistory::input(GETPOSTINT('period'),GETPOST('history_start','alphanohtml'),GETPOST('history_end','alphanohtml'),GETPOST('history_reason','alphanohtml'),GETPOST('history_void','aZ09')==='1');
            $preview=NorwayVatHistory::preview($db,$entity,$org,$input);
            $_SESSION['norway_vat_history']=array('entity'=>$entity,'user'=>(int)$user->id,'org'=>$preview['org'],
                'input'=>$preview['input'],'fingerprint'=>NorwayVatHistory::fingerprint($preview),'expires'=>time()+600,'nonce'=>bin2hex(random_bytes(16)));
        }catch(Throwable $error){setEventMessages($langs->trans('NorwayVatHistoryFailed'),null,'errors');}
    }

    public static function render($db,$entity,$org,$legacy,$langs)
    {
        print '<section class="norway-vat-history"><h2>'.$langs->trans('NorwayVatHistoryTitle').'</h2><p>'.$langs->trans('NorwayVatHistoryScope').'</p>';
        try{
            $state=NorwayVatHistory::read($db,$entity,$org);
            if(!in_array($state['status'],array('Missing','History'),true)){
                print '<div class="warning">'.$langs->trans('NorwayVatHistoryFailed').'</div></section>';return;
            }
            if($legacy['status']!=='Recorded'){print '<p>'.$langs->trans('NorwayVatHistoryFirstDate').'</p></section>';return;}
            $editing=GETPOSTISSET('period')?GETPOSTINT('period'):($state['status']==='Missing'?1:0);
            $selected=$state['active'][$editing]??null;
            if($selected&&!$selected['void']){$start=$selected['start'];$end=$selected['end'];}
            elseif($state['status']==='Missing'){$start=$legacy['start'];$end='';}
            else{$start='';$end='';$editing=0;}
            if($state['status']==='History'){
                print '<p>'.$langs->trans('NorwayVatHistoryActive').' #'.(int)$state['revision'].'</p><div class="div-table-responsive"><table class="noborder centpercent"><tr>';
                foreach(array('Period','Start','End','Reason','State','Edit') as $key){print '<th>'.$langs->trans('NorwayVatHistory'.$key).'</th>';}print '</tr>';
                foreach($state['active'] as $period){
                    print '<tr><td>#'.(int)$period['period'].'</td><td>'.dol_escape_htmltag($period['start']).'</td><td>'.dol_escape_htmltag($period['end']).'</td><td>'.dol_escape_htmltag($period['reason']).'</td><td>'.$langs->trans('NorwayVatHistory'.($period['void']?'Voided':'Recorded')).'</td><td>';
                    if(!$period['void']){print '<a href="'.dol_buildpath('/norway/admin/registration.php',1).'?mainmenu=accountancy&amp;leftmenu=accountancy_admin_norway&amp;period='.(int)$period['period'].'">'.$langs->trans('NorwayVatHistoryEdit').'</a>';}print '</td></tr>';
                }print '</table></div><details><summary>'.$langs->trans('NorwayVatHistoryAll').'</summary><ul>';
                foreach($state['record']['events'] as $event){print '<li>#'.(int)$event['revision'].' / #'.(int)$event['period'].' / '.dol_escape_htmltag($event['start'].' – '.$event['end'].' / '.$event['at']).' / '.(int)$event['user'].' / '.dol_escape_htmltag($event['reason']).'</li>';}
                print '</ul></details>';
            }else{print '<p>'.$langs->trans('NorwayVatHistoryImport').'</p>';}
            print '<h3>'.$langs->trans($editing?'NorwayVatHistoryEdit':'NorwayVatHistoryNew').($editing?' #'.$editing:'').'</h3>';
            print '<form method="post"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="previewhistory"><input type="hidden" name="period" value="'.(int)$editing.'">';
            print '<p><label>'.$langs->trans('NorwayVatHistoryStart').' <input type="date" name="history_start" value="'.dol_escape_htmltag($start).'" required></label></p>';
            print '<p><label>'.$langs->trans('NorwayVatHistoryEnd').' <input type="date" name="history_end" value="'.dol_escape_htmltag($end).'"></label><br>'.$langs->trans('NorwayVatHistoryEndHelp').'</p>';
            print '<p><label>'.$langs->trans('NorwayVatHistoryReason').' <textarea name="history_reason" maxlength="1000" required></textarea></label></p>';
            if($editing){print '<p><label><input type="checkbox" name="history_void" value="1"> '.$langs->trans('NorwayVatHistoryVoidHelp').'</label></p>';}
            print '<button class="button" type="submit">'.$langs->trans('NorwayVatHistoryPreview').'</button></form>';
            if($state['status']==='History'&&$editing){print '<p><a href="'.dol_buildpath('/norway/admin/registration.php',1).'?mainmenu=accountancy&amp;leftmenu=accountancy_admin_norway&amp;period=0">'.$langs->trans('NorwayVatHistoryNew').'</a></p>';}
            $pending=$_SESSION['norway_vat_history']??null;
            if($pending&&$pending['entity']===$entity&&$pending['user']===(int)$GLOBALS['user']->id&&$pending['expires']>=time()){
                $input=$pending['input'];print '<div class="warning">'.$langs->trans('NorwayVatHistoryConfirmHelp').'</div><p>'.dol_escape_htmltag($pending['org'].' / #'.$input['period'].' / '.$input['start'].' – '.$input['end'].' / '.$input['reason']).'</p>';
                if($input['void']){print '<p>'.$langs->trans('NorwayVatHistoryVoided').'</p>';}
                print '<form method="post"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="savehistory"><input type="hidden" name="preview_nonce" value="'.$pending['nonce'].'"><label><input type="checkbox" name="confirmed" value="1" required> '.$langs->trans('NorwayVatHistoryConfirmHelp').'</label><p><button class="button" type="submit">'.$langs->trans('NorwayVatHistoryConfirm').'</button></p></form>';
            }
        }catch(Throwable $error){print '<div class="error">'.$langs->trans('NorwayVatHistoryFailed').'</div>';}
        print '</section>';
    }
}
