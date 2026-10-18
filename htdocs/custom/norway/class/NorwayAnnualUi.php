<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require_once __DIR__.'/NorwayAnnualReport.php';
require_once __DIR__.'/NorwayDeductions.php';
/** Annual basis belongs on the native balance report, with no additional menu or stored workflow. */
final class NorwayAnnualUi
{
    public static function page(){return ($_SERVER['SCRIPT_FILENAME']??'')===DOL_DOCUMENT_ROOT.'/accountancy/bookkeeping/balance.php';}
    public static function allowed($user){return $user && empty($user->socid) && isModEnabled('norway') && $user->hasRight('accounting','mouvements','lire') && $user->hasRight('accounting','mouvements','export');}
    public static function year(){return GETPOSTINT('norway_annual_year')?:min((int)date('Y'),2026);}
    public static function controls($user,$langs)
    {
        if(!self::page() || !self::allowed($user)){return '';}$langs->load('norway@norway');$html='<span id="norway-annual-controls" style="display:flex;flex-wrap:wrap;gap:.5rem;max-width:100%;align-items:center"><label>'.$langs->trans('NorwayAnnualTitle').' <select name="norway_annual_year">';
        foreach(array(2025,2026) as $year){$html.='<option value="'.$year.'"'.(self::year()===$year?' selected':'').'>'.$year.'</option>';}
        return $html.'</select></label> <button class="button" type="submit" name="norway_annual_show" value="1">'.$langs->trans('NorwayAnnualShow').'</button> <button class="button" type="submit" name="action" value="norway_annual_csv">'.$langs->trans('NorwayAnnualDownload').'</button></span>';
    }
    public static function action($db,$action,$user,$langs)
    {
        global $conf;
        if(self::page() && GETPOSTINT('norway_annual_show') && self::allowed($user)){header('Cache-Control: no-store, private');}
        if($action!=='norway_annual_csv'){return false;}
        try{
            if(!self::page() || !self::allowed($user) || ($_SERVER['REQUEST_METHOD']??'')!=='POST' || empty($_SESSION['token']) || !hash_equals($_SESSION['token'],GETPOST('token','alphanohtml'))){throw new RuntimeException('NorwaySaftAccess');}
            $r=NorwayAnnualReport::build($db,(int)$conf->entity,self::year(),$user);
            header('Content-Type: text/csv; charset=UTF-8');header('Content-Disposition: attachment; filename="ENK-'.self::year().'-arsgrunnlag.csv"');header('Cache-Control: no-store, private');header('X-Content-Type-Options: nosniff');
            print self::csv($r,$langs);exit;
        }catch(Throwable $error){$langs->load('norway@norway');setEventMessages($langs->trans('NorwayAnnualFailed'),null,'errors');}return true;
    }
    private static function money($value){return $value===null?'—':NorwayVatReport::money($value);}
    private static function summary($r)
    {
        return array('NorwayAnnualProfit'=>$r['profit'],'NorwayAnnualAddback'=>$r['known_addback'],'NorwayAnnualDeduction'=>$r['known_deduction'],'NorwayAnnualTaxBasis'=>$r['tax_basis_before_other_adjustments'],'NorwayAnnualPhonePrivate'=>$r['phone_private'],'NorwayAnnualDepreciation'=>$r['depreciation'],'NorwayAnnualAdvance'=>$r['advance_tax'],'NorwayAnnualAdditional'=>$r['additional_tax'],'NorwayAnnualOpeningEquity'=>$r['opening_equity'],'NorwayAnnualDisposal'=>$r['disposal'],'NorwayAnnualUnallocated'=>$r['unallocated_result'],'NorwayAnnualOwner'=>$r['owner_cash'],'NorwayAnnualOtherEquity'=>$r['other_equity_movements'],'NorwayAnnualClosingEquity'=>$r['closing_equity']);
    }
    public static function csv($r,$langs)
    {
        $langs->load('norway@norway');$f=fopen('php://temp','w+');$write=static function($cells)use($f){$cells=array_map(static function($v){$v=(string)$v;return !preg_match('/^-?[0-9]+(?:\.[0-9]+)?$/D',$v) && preg_match('/^[\s]*[=+@\-\t\r]/u',$v)?"'".$v:$v;},$cells);fputcsv($f,$cells,';','"','');};
        $write(array('ENK',$r['year'],$langs->trans('NorwayAnnualScope')));
        $write(array('Status',$langs->trans($r['issues']?'NorwayAnnualIncomplete':'NorwayAnnualSupplement')));
        foreach(self::summary($r) as $key=>$amount){$write(array($langs->trans($key),self::money($amount)));}
        $write(array('Kategori','Postkode','Post','Bokført NOK','Kjent korreksjon NOK','Grunnlag etter kjent korreksjon NOK','IB NOK','Konti'));
        foreach($r['fields'] as $row){$write(array($row['category'],$row['code'],$row['label'],self::money($row['value']),self::money($row['adjustment']),self::money($row['basis']),$row['balance']?self::money($row['opening']):'',implode(', ',$row['accounts'])));}
        $write(array('Kontospesifikasjon','Konto','Navn','IB NOK','Bevegelse NOK','UB NOK','Rapportpost'));
        foreach($r['accounts'] as $a){$write(array('Konto',$a['number'],$a['label'],self::money($a['opening']),self::money($a['movement']),self::money($a['closing']),$a['field']??''));}
        $write(array('Skattebetalinger','Dato','Konto','Beløp NOK','Bilag','Referanse'));
        foreach($r['payments'] as $a){$write(array('Betaling',$a['date'],$a['account'],self::money($a['amount']),$a['piece'],$a['reference']));}
        $write(array('Driftsmidler','Konto','Navn','IB NOK','Økninger NOK','Reduksjoner NOK','Bokført avskrivning NOK','UB NOK'));
        foreach($r['assets'] as $a){$write(array('Eiendel',$a['number'],$a['label'],self::money($a['opening']),self::money($a['increases']),self::money($a['reductions']),self::money($a['depreciation']),self::money($a['closing'])));}
        $write(array('Fradragsoversikt','Tema','Bokført kostnadsgrunnlag NOK','Konti','Forklaring','Kilde'));
        foreach(NorwayDeductions::topics($r) as $key=>$topic){$write(array('Fradrag',$langs->trans('NorwayDeduction'.$key),self::money($topic['amount']),implode(', ',$topic['accounts']),$langs->trans('NorwayDeduction'.$key.'Help'),$topic['url']));}
        $write(array('Personlige premier, ikke ekstra driftskostnad','Dato','Konto','Beløp NOK','Bilag','Referanse'));foreach($r['personal_premiums'] as $a){$write(array('Premie',$a['date'],$a['account'],self::money($a['amount']),$a['piece'],$a['reference']));}
        foreach($r['issues'] as $issue){$write(array('AVKLARES',$langs->trans($issue['key']),$issue['detail']));}
        foreach($r['phone_projection'] as $a){$write(array('Bokført EK-tilbakeføring, presentert i 6998',self::money($a['amount']),'Bilag',$a['piece']));}
        $write(array('Tilleggsopplysninger',$langs->trans('NorwayAnnualAdditionalHelp')));rewind($f);$csv=stream_get_contents($f);fclose($f);return "\xEF\xBB\xBF".$csv;
    }
    public static function deductions($r,$langs)
    {
        $e=static fn($v)=>dol_escape_htmltag((string)$v);$html='<h3>'.$e($langs->trans('NorwayDeductionsTitle')).'</h3><p>'.$e($langs->trans('NorwayDeductionsScope')).'</p><div class="div-table-responsive"><table class="noborder centpercent"><tbody>';
        foreach(NorwayDeductions::topics($r) as $key=>$topic){$html.='<tr><th scope="row">'.$e($langs->trans('NorwayDeduction'.$key)).'</th><td class="right">'.self::money($topic['amount']).'</td><td>'.$e($langs->trans('NorwayDeduction'.$key.'Help')).' <a href="'.$e($topic['url']).'">'.$e($langs->trans('NorwayDeductionRules')).'</a>';
            foreach($topic['accounts'] as $n){$html.=' <a href="'.DOL_URL_ROOT.'/accountancy/bookkeeping/list.php?search_accountancy_code_start='.$e($n).'&amp;search_accountancy_code_end='.$e($n).'&amp;date_startday=1&amp;date_startmonth=1&amp;date_startyear='.$r['year'].'&amp;date_endday=31&amp;date_endmonth=12&amp;date_endyear='.$r['year'].'">'.$e($n).'</a>';}$html.='</td></tr>';}
        $html.='</tbody></table></div>';
        foreach($r['personal_premiums'] as $a){$html.='<p>'.$e($langs->trans('NorwayDeductionPersonalPayment').' '.$a['date'].' '.$a['account'].' '.self::money($a['amount'])).' <a href="'.DOL_URL_ROOT.'/accountancy/bookkeeping/card.php?piece_num='.$a['piece'].'">'.$a['piece'].'</a></p>';}
        return $html.'<p><a href="'.DOL_URL_ROOT.'/accountancy/bookkeeping/card.php?action=create&amp;doc_type=manual&amp;doc_dateday=31&amp;doc_datemonth=12&amp;doc_dateyear='.$r['year'].'">'.$e($langs->trans('NorwayDeductionVoucher')).'</a> · <a href="https://github.com/mfuryto/dolibarr-norway/blob/main/docs/deductions.md">'.$e($langs->trans('NorwayWizardGuide')).'</a></p>';
    }

    public static function render($db,$entity,$user,$langs)
    {
        if(!self::page() || !self::allowed($user) || !GETPOSTINT('norway_annual_show')){return '';}$langs->load('norway@norway');$e=static fn($v)=>dol_escape_htmltag((string)$v);
        try{$r=NorwayAnnualReport::build($db,$entity,self::year(),$user);}catch(Throwable $error){return '<p class="error">'.$e($langs->trans('NorwayAnnualFailed')).'</p>';}
        $html='<section id="norway-annual-report" class="clearboth" style="width:100%;max-width:100%;font-family:Arial,sans-serif;font-weight:normal"><h2>'.$e($langs->trans('NorwayAnnualTitle')).' '.$r['year'].'</h2><p>'.$e($langs->trans('NorwayAnnualScope')).'</p>';
        foreach($r['issues'] as $issue){$html.='<p class="warning">'.$e($langs->trans($issue['key']).' '.$issue['detail']).'</p>';}
        $html.='<div class="div-table-responsive"><table class="noborder centpercent"><tbody>';
        foreach(array_slice(self::summary($r),0,4,true) as $key=>$amount){$html.='<tr><th scope="row">'.$e($langs->trans($key)).'</th><td class="right">'.self::money($amount).'</td></tr>';}
        $html.='</tbody></table></div><p>'.$e($langs->trans('NorwayAnnualTaxHelp')).'</p><details><summary>'.$e($langs->trans('NorwayAnnualDetails')).'</summary><div class="div-table-responsive"><table class="noborder centpercent"><tbody>';
        foreach(array_slice(self::summary($r),4,null,true) as $key=>$amount){$html.='<tr><th scope="row">'.$e($langs->trans($key)).'</th><td class="right">'.self::money($amount).'</td></tr>';}
        $html.='</tbody></table></div></details><h3>'.$e($langs->trans('NorwayAnnualFields')).'</h3><div class="div-table-responsive"><table class="noborder centpercent"><thead><tr>';
        foreach(array('NorwayAnnualCode','NorwayAnnualField','NorwayAnnualValue','NorwayAnnualAdjustment','NorwayAnnualBasis','NorwayAnnualAccounts') as $key){$html.='<th>'.$e($langs->trans($key)).'</th>';}$html.='</tr></thead><tbody>';
        foreach($r['fields'] as $row){if(!isset($previousType) || $previousType!==$row['balance']){$html.='<tr><th colspan="6">'.$e($langs->trans($row['balance']?'NorwayAnnualBalanceRows':'NorwayAnnualResultRows')).'</th></tr>';$previousType=$row['balance'];}$html.='<tr><td>'.$e($row['code']).'</td><td>'.$e($row['label']).'</td><td class="right">'.self::money($row['value']).'</td><td class="right">'.self::money($row['adjustment']).'</td><td class="right">'.self::money($row['basis']).'</td><td>';
            foreach($row['accounts'] as $n){$url=DOL_URL_ROOT.'/accountancy/bookkeeping/list.php?'.http_build_query(array('search_accountancy_code_start'=>$n,'search_accountancy_code_end'=>$n,'date_startday'=>1,'date_startmonth'=>1,'date_startyear'=>$r['year'],'date_endday'=>31,'date_endmonth'=>12,'date_endyear'=>$r['year']));$html.='<a href="'.$e($url).'">'.$e($n).'</a> ';}$html.='</td></tr>';}
        $html.='</tbody></table></div>'.self::deductions($r,$langs).'<h3>'.$e($langs->trans('NorwayAnnualAssets')).'</h3><p>'.$e($langs->trans('NorwayAnnualAssetsHelp')).'</p><div class="div-table-responsive"><table class="noborder centpercent"><thead><tr>';
        foreach(array('Account','NorwayAnnualOpening','NorwayAnnualIncreases','NorwayAnnualReductions','NorwayAnnualDepreciation','NorwayAnnualClosing') as $key){$html.='<th>'.$e($langs->trans($key)).'</th>';}$html.='</tr></thead><tbody>';
        foreach($r['assets'] as $a){$html.='<tr><td>'.$e($a['number'].' '.$a['label']).'</td>';foreach(array('opening','increases','reductions','depreciation','closing') as $key){$html.='<td class="right">'.self::money($a[$key]).'</td>';}$html.='</tr>';}$html.='</tbody></table></div><h3>'.$e($langs->trans('NorwayAnnualPayments')).'</h3><div class="div-table-responsive"><table class="noborder centpercent"><tbody>';
        foreach($r['payments'] as $a){$html.='<tr><td>'.$e($a['date']).'</td><td>'.$e($a['account']).'</td><td class="right">'.self::money($a['amount']).'</td><td><a href="'.DOL_URL_ROOT.'/accountancy/bookkeeping/card.php?piece_num='.$a['piece'].'">'.$a['piece'].'</a></td></tr>';}
        return $html.'</tbody></table></div><h3>'.$e($langs->trans('NorwayAnnualAdditionalTitle')).'</h3><p>'.$e($langs->trans('NorwayAnnualAdditionalHelp')).'</p><p><a href="https://github.com/mfuryto/dolibarr-norway/blob/main/docs/enk-tax-return.md">'.$e($langs->trans('NorwayWizardGuide')).'</a></p></section>';
    }
}
