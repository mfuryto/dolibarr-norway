<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require '../../main.inc.php';
require_once __DIR__.'/class/NorwayVatReport.php';
if(!NorwayVatReport::allowed($user)){accessforbidden();}
$langs->load('norway@norway');header('Cache-Control: no-store');
$start=GETPOSTISSET('date_start')?GETPOST('date_start','alphanohtml'):date('Y-m-01');
$end=GETPOSTISSET('date_end')?GETPOST('date_end','alphanohtml'):date('Y-m-t');
if(GETPOSTISSET('term')){try{[$start,$end]=NorwayVatPeriod::dates(GETPOST('year','alphanohtml'),GETPOST('term','alphanohtml'));}catch(Throwable $e){$start='invalid';$end='invalid';}}
$report=null;$error='';
try{$report=NorwayVatReport::build($db,(int)$conf->entity,$start,$end,$user);}
catch(Throwable $e){$error=strpos($e->getMessage(),'NorwayVatReport')===0?$e->getMessage():'NorwayVatReportDatabase';}
if(GETPOST('format','aZ09')==='csv'){
    if(!$report || $report['issues']){http_response_code(409);$error=$error?:'NorwayVatReportIncomplete';}
    else {header('Content-Type: text/csv; charset=UTF-8');header('Content-Disposition: attachment; filename="norway-vat-'.$start.'-'.$end.'.csv"');header('Cache-Control: no-store');print NorwayVatReport::csv($report);$db->close();exit;}
}
$e=static fn($s)=>dol_escape_htmltag((string)$s);
$money=static fn($n)=>$e(NorwayVatReport::money($n));
$link=static function($source,$id,$piece) use($e){
    if($source==='customer_invoice'){$url='/compta/facture/card.php?facid='.(int)$id;}
    elseif($source==='supplier_invoice'){$url='/fourn/facture/card.php?facid='.(int)$id;}
    elseif($piece>0){$url='/accountancy/bookkeeping/card.php?piece_num='.(int)$piece;}
    else{return '';}
    return '<a href="'.DOL_URL_ROOT.$url.'">'.$e($source==='ledger'?$piece:$id).'</a>';
};
llxHeader('',$langs->trans('NorwayVatReportTitle'));
print '<div class="norway-page" id="norway-vat-report">'.load_fiche_titre($langs->trans('NorwayVatReportTitle'),'','accountancy');
print '<p>'.$e($langs->trans('NorwayVatReportScope')).'</p>';
print '<form method="GET"><label>'.$e($langs->trans('Year')).' <input type="number" name="year" min="2000" max="2099" value="'.$e(substr($start,0,4)).'"></label> <label>'.$e($langs->trans('NorwayVatTerm')).' <select name="term">';
foreach(range(1,6) as $term){print '<option value="'.$term.'"'.(GETPOSTINT('term')===$term?' selected':'').'>'.$term.'</option>';}
print '</select></label> <button class="button" type="submit">'.$e($langs->trans('Refresh')).'</button></form>';
print '<form method="GET"><input type="hidden" name="mainmenu" value="accountancy"><input type="hidden" name="leftmenu" value="accountancy_accountancy_report">'
    .'<label>'.$e($langs->trans('DateStart')).' <input type="date" name="date_start" value="'.$e($start).'"></label> '
    .'<label>'.$e($langs->trans('DateEnd')).' <input type="date" name="date_end" value="'.$e($end).'"></label> '
    .'<button class="button" type="submit">'.$e($langs->trans('Refresh')).'</button></form>';
if($error){print '<p class="error">'.$e($langs->trans($error)).'</p>';}
if($report){
    if($report['issues']){
        print '<p class="warning">'.$e($langs->trans('NorwayVatReportIncomplete')).'</p>';
        print '<ul>';foreach($report['issues'] as $issue){print '<li>'.$e($langs->trans('NorwayVatReport'.$issue['reason'])).' '
            .($issue['source']===''?'':$e($langs->trans('NorwayVatReportSource'.($issue['source']==='ledger'?'Manual':($issue['source']==='customer_invoice'?'Sale':'Purchase'))))).' '
            .$link($issue['source'],$issue['id'],$issue['piece']).'</li>';}
        print '</ul>';
    } else {print '<p>'.$e($langs->trans('NorwayVatReportReconciled')).'</p>';}
    print '<div class="div-table-responsive"><table class="noborder centpercent"><tr class="liste_titre">';
    foreach(array('NorwayManualVatCodeLabel','NorwayVatReportDirection','NorwayVatReportRate','NorwayVatReportBase','NorwayVatReportTax','NorwayVatReportUsage') as $key){print '<th>'.$e($langs->trans($key)).'</th>';}
    print '</tr>';
    foreach($report['groups'] as $group){$code=$group['code'];print '<tr class="oddeven"><td>'.$e($code).(in_array((string)$code,array('0','5','6','7','52','86','87'),true)?' · '.$e($langs->trans('NorwayVatCode'.$code)):'').'</td><td>'.$e($langs->trans($group['type']===1?'NorwayManualVatOutput':'NorwayManualVatInput')).'</td><td>'.($group['type']===1 && !in_array((string)$code,array('6','7'),true)?$e($group['rate']).'%':'').'</td><td>'.($group['base']===null?'':$money($group['base'])).'</td><td>'.$money($group['tax']).'</td><td>'.$e($langs->trans($group['reported']?'NorwayVatReportIncluded':'NorwayVatReportExcluded')).'</td></tr>';}
    print '</table></div>';
    if($report['net']!==null){
        print '<p><strong>'.$e($langs->trans('NorwayVatReportNet')).': '.$money($report['net']).' NOK</strong></p>';
        print '<p><a class="button" href="?'.http_build_query(array('mainmenu'=>'accountancy','leftmenu'=>'accountancy_accountancy_report','date_start'=>$start,'date_end'=>$end,'format'=>'csv')).'">'.$e($langs->trans('NorwayVatReportCsv')).'</a></p>';
    }
    if(!$report['issues'] && NorwayVatPeriod::complete($start,$end)){
        $portal=NorwayVatPeriod::portal($report);
        print '<section id="norway-vat-portal"><h2>'.$e($langs->trans('NorwayVatPortalTitle')).'</h2><p>'.$e($langs->trans('NorwayVatPortalHelp')).'</p>';
        print '<div class="div-table-responsive"><table class="noborder centpercent"><tr class="liste_titre"><th>'.$e($langs->trans('NorwayManualVatCodeLabel')).'</th><th>'.$e($langs->trans('NorwayVatReportDirection')).'</th><th>'.$e($langs->trans('NorwayVatPortalBase')).'</th><th>'.$e($langs->trans('NorwayVatPortalTax')).'</th></tr>';
        foreach($portal['rows'] as $g){print '<tr class="oddeven"><td>'.$e($g['code']).'</td><td>'.$e($langs->trans($g['type']===1?'NorwayManualVatOutput':'NorwayManualVatInput')).'</td><td>'.$e($g['base']??'').'</td><td>'.$e($g['tax']).'</td></tr>';}
        print '</table></div><p><strong>'.$e($langs->trans('NorwayVatReportNet')).': '.$e($portal['net']).' NOK</strong></p>';
        print '<p><a class="button" href="https://www.skatteetaten.no/bedrift-og-organisasjon/avgifter/mva/mva-melding/" target="_blank" rel="noopener noreferrer">'.$e($langs->trans('NorwayVatPortalOpen')).'</a></p>';
        $proposal=NorwayVatPeriod::proposal($report);
        print '<h3>'.$e($langs->trans('NorwayVatSettlementType')).'</h3><p>'.$e($langs->trans('NorwayVatSettlementHelp')).'</p>';
        if($proposal){
            print '<div class="div-table-responsive"><table class="noborder centpercent"><tr class="liste_titre"><th>'.$e($langs->trans('Account')).'</th><th>'.$e($langs->trans('Debit')).'</th><th>'.$e($langs->trans('Credit')).'</th></tr>';
            foreach($proposal as $account=>$amount){print '<tr class="oddeven"><td>'.$e($account).'</td><td>'.$money(max(0,$amount)).'</td><td>'.$money(max(0,-$amount)).'</td></tr>';}
            print '</table></div>';
            if(NorwayManualVat::allowed($user,true)){print '<p><a class="button" href="'.DOL_URL_ROOT.'/accountancy/bookkeeping/card.php?'.http_build_query(array('action'=>'create','doc_type'=>'vat_settlement','doc_ref'=>NorwayVatPeriod::reference($start,$end))).'">'.$e($langs->trans('NorwayVatSettlementCreate')).'</a></p>';}
        }else{print '<p>'.$e($langs->trans('NorwayVatSettlementDone')).'</p>';}
        print '<p>'.$e($langs->trans('NorwayVatPortalReceipt')).' <a href="'.DOL_URL_ROOT.'/compta/tva/list.php">'.$e($langs->trans('NorwayVatPayment')).'</a> · <a href="'.DOL_URL_ROOT.'/accountancy/admin/fiscalyear.php">'.$e($langs->trans('NorwayWizardTaskPeriods')).'</a></p></section>';
    }
    print '<p>'.$e($langs->trans('NorwayVatReportOther',$report['other_lines'])).'</p>';
    if($report['entries']){
        print '<details><summary>'.$e($langs->trans('NorwayVatReportSources')).'</summary><div class="div-table-responsive"><table class="noborder centpercent"><tr class="liste_titre">';
        foreach(array('NorwayVatReportSources','NorwayManualVatCodeLabel','NorwayVatReportBase','NorwayVatReportTax') as $key){print '<th>'.$e($langs->trans($key)).'</th>';}
        print '</tr>';foreach($report['entries'] as $entry){print '<tr class="oddeven"><td>'.$e($langs->trans('NorwayVatReportSource'.($entry['source']==='ledger'?'Manual':($entry['source']==='customer_invoice'?'Sale':'Purchase')))).' '.$link($entry['source'],$entry['id'],$entry['piece']).'</td><td>'.$e($entry['code']).(!$entry['reported']?' · '.$e($langs->trans('NorwayVatReportExcluded')):'').'</td><td>'.($entry['base']===null?'':$money($entry['base'])).'</td><td>'.$money($entry['tax']).'</td></tr>';}
        print '</table></div></details>';
    }
}
print '</div>';llxFooter();$db->close();
