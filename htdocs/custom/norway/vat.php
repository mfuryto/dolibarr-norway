<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require '../../main.inc.php';
require_once __DIR__.'/class/NorwayVatReport.php';
if(!NorwayVatReport::allowed($user)){accessforbidden();}
$langs->load('norway@norway');header('Cache-Control: no-store');
$start=GETPOSTISSET('date_start')?GETPOST('date_start','alphanohtml'):date('Y-m-01');
$end=GETPOSTISSET('date_end')?GETPOST('date_end','alphanohtml'):date('Y-m-t');
$report=null;$error='';
try{$report=NorwayVatReport::build($db,(int)$conf->entity,$start,$end,$user);}
catch(Throwable $e){$error=strpos($e->getMessage(),'NorwayVatReport')===0?$e->getMessage():'NorwayVatReportDatabase';}
if(GETPOST('format','aZ09')==='csv'){
    if(!$report || $report['issues']){http_response_code(409);$error=$error?:'NorwayVatReportIncomplete';}
    else {header('Content-Type: text/csv; charset=UTF-8');header('Content-Disposition: attachment; filename="ordinary-vat-'.$start.'-'.$end.'.csv"');header('Cache-Control: no-store');print NorwayVatReport::csv($report);$db->close();exit;}
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
    foreach(array('NorwayManualVatCodeLabel','NorwayVatReportDirection','NorwayVatReportRate','NorwayManualVatBaseLabel','NorwayVatReportTax') as $key){print '<th>'.$e($langs->trans($key)).'</th>';}
    print '</tr>';
    foreach($report['groups'] as $code=>$group){print '<tr class="oddeven"><td>'.$e($code).'</td><td>'.$e($langs->trans($group['type']===1?'NorwayManualVatOutput':'NorwayManualVatInput')).'</td><td>'.($group['type']===1?$e($group['rate']).'%':'').'</td><td>'.($group['base']===null?'':$money($group['base'])).'</td><td>'.$money($group['tax']).'</td></tr>';}
    print '</table></div>';
    if($report['net']!==null){
        print '<p><strong>'.$e($langs->trans('NorwayVatReportNet')).': '.$money($report['net']).' NOK</strong></p>';
        print '<p><a class="button" href="?'.http_build_query(array('mainmenu'=>'accountancy','leftmenu'=>'accountancy_accountancy_report','date_start'=>$start,'date_end'=>$end,'format'=>'csv')).'">'.$e($langs->trans('NorwayVatReportCsv')).'</a></p>';
    }
    print '<p>'.$e($langs->trans('NorwayVatReportOther',$report['other_lines'])).'</p>';
    if($report['entries']){
        print '<details><summary>'.$e($langs->trans('NorwayVatReportSources')).'</summary><div class="div-table-responsive"><table class="noborder centpercent"><tr class="liste_titre">';
        foreach(array('NorwayVatReportSources','NorwayManualVatCodeLabel','NorwayManualVatBaseLabel','NorwayVatReportTax') as $key){print '<th>'.$e($langs->trans($key)).'</th>';}
        print '</tr>';foreach($report['entries'] as $entry){print '<tr class="oddeven"><td>'.$e($langs->trans('NorwayVatReportSource'.($entry['source']==='ledger'?'Manual':($entry['source']==='customer_invoice'?'Sale':'Purchase')))).' '.$link($entry['source'],$entry['id'],$entry['piece']).'</td><td>'.$e($entry['code']).'</td><td>'.($entry['base']===null?'':$money($entry['base'])).'</td><td>'.$money($entry['tax']).'</td></tr>';}
        print '</table></div></details>';
    }
}
print '</div>';llxFooter();$db->close();
