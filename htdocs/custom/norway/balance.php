<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require '../../main.inc.php';
require_once __DIR__.'/class/NorwayBalance.php';
if(!NorwayBalance::allowed($user)){http_response_code(403);exit;}
$langs->load('norway@norway');header('Cache-Control: private, no-store');
$date=GETPOSTISSET('asof')?GETPOST('asof','alphanohtml'):dol_print_date(dol_now(),'%Y-%m-%d');$report=null;$error='';$detail='';
try{$report=NorwayBalance::build($db,(int)$conf->entity,$date,$user);}catch(Throwable $failure){$parts=explode(':',$failure->getMessage(),2);$detail=isset($parts[1])&&preg_match('/^[0-9]{1,16}$/D',$parts[1])?$parts[1]:'';$error=in_array($parts[0],array('NorwayBalanceDate','NorwayBalanceAccount','NorwayBalanceOpening','NorwayBalancePrecision','NorwayBalanceScope'),true)?$parts[0]:'NorwayBalanceDatabase';}
$e=static fn($text)=>dol_escape_htmltag((string)$text);$money=static fn($amount)=>$e(price(NorwayVatReport::money($amount),0,$langs,1,2,2)).' NOK';
llxHeader('',$langs->trans('NorwayBalanceTitle'));
print '<div class="norway-page" id="norway-balance">'.load_fiche_titre($langs->trans('NorwayBalanceTitle'),'','accountancy');
print '<p>'.$e($langs->trans('NorwayBalanceHelp')).'</p><form method="get"><input type="hidden" name="mainmenu" value="accountancy"><input type="hidden" name="leftmenu" value="accountancy_accountancy">'
    .'<label>'.$e($langs->trans('NorwayBalanceAsOf')).' <input type="date" name="asof" required value="'.$e($date).'"></label> <button class="button" type="submit">'.$e($langs->trans('Refresh')).'</button></form>';
if($error){print '<p class="error">'.$e($langs->trans($error)).($detail?' ('.$e($detail).')':'').'</p><p><a href="'.DOL_URL_ROOT.'/custom/norway/admin/accounting.php">'.$e($langs->trans('NorwayAccountingSettings')).'</a></p>';}
if($report){
    print '<h2>'.$e($langs->trans('NorwayBalanceAsOf')).' '.$e($report['date']).'</h2>';
    if(array_sum($report['unposted'])>0){print '<div class="warning"><p>'.$e($langs->trans('NorwayBalanceUnposted')).'</p><ul>';foreach($report['unposted'] as $kind=>$count){if(!$count){continue;}$url=$kind==='draft'?'/accountancy/bookkeeping/list.php?mode=_tmp':('/accountancy/journal/'.array('customer_invoice'=>'sells','supplier_invoice'=>'purchases','bank'=>'bank')[$kind].'journal.php');print '<li><a href="'.DOL_URL_ROOT.$url.'">'.$e($langs->trans('NorwayBalancePending'.$kind)).': '.$count.'</a></li>';}print '</ul></div>';}
    print '<div class="norway-balance-summary">';
    foreach(array('Assets'=>$report['assets'],'Equity'=>$report['equity_with_result'],'Debt'=>$report['debt']) as $key=>$amount){print '<div data-balance="'.strtolower($key).'" data-amount="'.$amount.'"><strong>'.$e($langs->trans('NorwayBalance'.$key)).'</strong><p>'.$money($amount).'</p></div>';}
    print '</div><div class="norway-balance-columns">';
    foreach(array('Assets','Funding') as $side){print '<section><h2>'.$e($langs->trans('NorwayBalance'.$side)).'</h2>';
        $resultShown=false;
        foreach(NorwayBalance::groups() as $group=>$groupSide){
            if($side==='Funding' && $groupSide==='Debt' && !$resultShown){print '<p class="norway-balance-result"><strong>'.$e($langs->trans('NorwayBalanceResult')).': '.$money($report['result']).'</strong></p><p class="opacitymedium">'.$e($langs->trans('NorwayBalanceResultHelp')).'</p>';$resultShown=true;}
            if(($side==='Assets')!==($groupSide==='Assets') || empty($report['groups'][$group])){continue;}$data=$report['groups'][$group];
            print '<details open><summary>'.$e($langs->trans('NorwayBalanceGroup'.$group)).' <strong>'.$money($data['amount']).'</strong></summary><table class="noborder centpercent"><thead><tr class="liste_titre"><th>'.$e($langs->trans('Account')).'</th><th class="right">'.$e($langs->trans('Balance')).'</th></tr></thead><tbody>';
            foreach($data['accounts'] as $account){print '<tr class="oddeven"><td><a href="'.$e(NorwayBalance::accountUrl($report,$account['number'])).'">'.$e($account['number'].' '.$account['label']).'</a></td><td class="right">'.$money($account['amount']).'</td></tr>';}
            print '</tbody></table></details>';
        }
        print '<p class="norway-balance-total"><strong>'.$e($langs->trans($side==='Assets'?'NorwayBalanceTotalAssets':'NorwayBalanceTotalFunding')).': '.$money($side==='Assets'?$report['assets']:$report['total_funding']).'</strong></p></section>';
    }
    print '</div><p class="'.($report['difference']===0?'opacitymedium':'error').'"><strong>'.$e($langs->trans('NorwayBalanceDifference')).': '.$money($report['difference']).'</strong> '.$e($langs->trans($report['difference']===0?'NorwayBalanceMatches':'NorwayBalanceMismatch')).'</p>';
    print '<p><a href="'.DOL_URL_ROOT.'/accountancy/bookkeeping/balance.php?mainmenu=accountancy&amp;leftmenu=accountancy_accountancy">'.$e($langs->trans('AccountBalance')).'</a></p>';
}
print '<style>#norway-balance form{display:flex;flex-wrap:wrap;align-items:center;gap:.5em;margin:1em 0}.norway-balance-summary{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1em;margin:1em 0}.norway-balance-summary>div{border:1px solid #ccc;padding:1em;overflow-wrap:anywhere}.norway-balance-columns{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1.5em}.norway-balance-columns section{min-width:0}.norway-balance-columns table{table-layout:fixed}.norway-balance-columns td,.norway-balance-columns th{overflow-wrap:anywhere}.norway-balance-columns th:first-child{width:65%}.norway-balance-columns details{margin:1em 0}.norway-balance-columns summary{cursor:pointer;overflow-wrap:anywhere}.norway-balance-columns summary strong{display:block;text-align:right}.norway-balance-total{border-top:2px solid #999;padding-top:1em}@media(max-width:1000px){.norway-balance-columns{display:block}}@media(max-width:600px){.norway-balance-summary{grid-template-columns:1fr}}</style></div>';
llxFooter();$db->close();
