<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require_once __DIR__.'/NorwaySaftExport.php';
/** Small additions to the existing accounting export and chart setup pages. */
class NorwaySaftUi
{
    public static function exportPage(){return ($_SERVER['SCRIPT_FILENAME']??'')===DOL_DOCUMENT_ROOT.'/accountancy/bookkeeping/export.php';}
    public static function setupPage(){return ($_SERVER['SCRIPT_FILENAME']??'')===DOL_DOCUMENT_ROOT.'/accountancy/admin/account.php';}
    public static function allowed($user){return $user && empty($user->socid) && isModEnabled('norway') && $user->hasRight('accounting','mouvements','lire') && $user->hasRight('accounting','mouvements','export');}
    public static function action($db,$action,$user,$langs)
    {
        global $conf;
        if(!in_array($action,array('norway_saft_export','norway_saft_mapping'),true)){return false;}
        $langs->load('norway@norway');
        try{
            if(($_SERVER['REQUEST_METHOD']??'')!=='POST' || empty($_SESSION['token']) || !hash_equals($_SESSION['token'],GETPOST('token','alphanohtml'))){throw new RuntimeException('NorwaySaftAccess');}
            if($action==='norway_saft_mapping'){
                if(!self::setupPage()){throw new RuntimeException('NorwaySaftAccess');}
                NorwaySaftMapping::save($db,(int)$conf->entity,GETPOSTINT('norway_saft_year'),GETPOSTINT('norway_saft_account'),GETPOST('norway_saft_group','alphanohtml'),$user);
                setEventMessages($langs->trans('RecordSaved'),null,'mesgs');
            }else{
                if(!self::exportPage() || !self::allowed($user)){throw new RuntimeException('NorwaySaftAccess');}
                $result=NorwaySaftExport::build($db,(int)$conf->entity,GETPOST('norway_saft_start','alphanohtml'),GETPOST('norway_saft_end','alphanohtml'),$user);
                $org=preg_replace('/\D/','',(string)$GLOBALS['mysoc']->idprof1);
                $filename='SAF-T_Financial_'.$org.'_'.gmdate('YmdHis').'_1_1.xml';
                header('Content-Type: application/xml; charset=UTF-8');header('Content-Disposition: attachment; filename="'.$filename.'"');
                header('Cache-Control: no-store, private');header('Pragma: no-cache');header('X-Content-Type-Options: nosniff');
                print $result['xml'];exit;
            }
        }catch(Throwable $error){
            $parts=explode(':',$error->getMessage(),2);$key=$parts[0];
            $known=array('NorwaySaftAccess','NorwaySaftAccountMapping','NorwaySaftGroupingYear','NorwaySaftOpeningBalance','NorwaySaftParty','NorwaySaftTax','NorwaySaftManualTax','NorwaySaftCompany','NorwaySaftContact','NorwaySaftPeriod','NorwaySaftPrecision','NorwaySaftUnposted');
            $message=$langs->trans(in_array($key,$known,true)?$key:'NorwaySaftFailed');
            // Error details are internal native IDs/account numbers, not raw SQL or XML.
            if(isset($parts[1]) && preg_match('/^[a-zA-Z0-9_\/-]{1,100}$/D',$parts[1])){$message.=' ('.$parts[1].')';}
            setEventMessages($message,null,'errors');
        }
        return true;
    }
    public static function exportFields($user,$langs)
    {
        if(!self::allowed($user)){return '';}$langs->load('norway@norway');
        $year=(int)gmdate('Y');
        // The core already owns this POST form and its CSRF token. Ignore native list filters.
        return '<span id="norway-saft-export"><label>SAF-T 1.40 <input type="date" aria-label="'.dol_escape_htmltag($langs->trans('DateStart')).'" name="norway_saft_start" value="'.$year.'-01-01"></label> '
            .'<input type="date" aria-label="'.dol_escape_htmltag($langs->trans('DateEnd')).'" name="norway_saft_end" value="'.gmdate('Y-m-d').'"> '
            .'<button class="button" type="submit" name="action" value="norway_saft_export">'.$langs->trans('NorwaySaftDownload').'</button> '
            .'<span class="opacitymedium">'.$langs->trans('NorwaySaftCompletePeriod').'</span></span>';
    }
    public static function setupFields($db,$user,$langs)
    {
        global $conf;
        if(!$user || !empty($user->socid) || !$user->hasRight('accounting','chartofaccount')){return '';}
        $langs->load('norway@norway');$p=$db->prefix();$year=GETPOSTINT('norway_saft_year')?:min(2026,(int)gmdate('Y'));
        if(!in_array($year,array(2025,2026),true)){$year=2026;}
        $accounts=NorwaySaftMapping::rows($db,'SELECT a.* FROM '.$p.'accounting_account a JOIN '.$p.'accounting_system s ON s.pcg_version=a.fk_pcg_version WHERE a.entity='.(int)$conf->entity.' AND s.rowid='.(int)getDolGlobalInt('CHARTOFACCOUNTS').' ORDER BY a.account_number');
        // This hook is inside the native chart list POST form. A button overrides its action.
        $html='<div id="norway-saft-mapping" style="max-width:100%"><p>'.$langs->trans('NorwaySaftMappingHelp').'</p><label>'.$langs->trans('Year').' <select name="norway_saft_year">';
        foreach(array(2025,2026) as $y){$html.='<option value="'.$y.'"'.($y===$year?' selected':'').'>'.$y.'</option>';}$html.='</select></label> <select style="max-width:100%" name="norway_saft_account" aria-label="'.$langs->trans('Account').'">';
        $g=NorwaySaftMapping::load($db,(int)$conf->entity,$year,array_map(static fn($a)=>$a->account_number,$accounts),true);
        foreach($accounts as $a){$current=isset($g[$a->account_number])?' → '.$g[$a->account_number]['category'].'/'.$g[$a->account_number]['code']:'';
            $html.='<option value="'.(int)$a->rowid.'">'.dol_escape_htmltag($a->account_number.' '.$a->label.$current).'</option>';}
        $html.='</select> <select style="max-width:100%" name="norway_saft_group" aria-label="SAF-T Grouping"><option value="">—</option>';
        foreach(NorwaySaftReference::groups($year) as $key=>$g){$html.='<option value="'.dol_escape_htmltag($key).'">'.dol_escape_htmltag($g['category'].'/'.$g['code'].' '.$g['label']).'</option>';}
        return $html.'</select> <button class="button" type="submit" name="action" value="norway_saft_mapping">'.$langs->trans('NorwaySaftSaveMapping').'</button></div>';
    }
}
