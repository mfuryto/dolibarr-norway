<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require_once __DIR__.'/NorwayVatReport.php';
/** Read-only presentation of native account balances, including undisposed result. */
final class NorwayBalance
{
    public static function allowed($user)
    {
        return $user && empty($user->socid) && isModEnabled('norway') && isModEnabled('accounting')
            && $user->hasRight('accounting','mouvements','lire');
    }
    private static function rows($db,$sql)
    {
        $result=$db->query($sql);if(!$result){throw new RuntimeException('NorwayBalanceDatabase');}
        $rows=array();while($row=$db->fetch_object($result)){$rows[]=$row;}return $rows;
    }
    private static function add($a,$b)
    {
        $sum=$a+$b;if(!is_int($sum) || $sum===PHP_INT_MIN){throw new RuntimeException('NorwayBalancePrecision');}return $sum;
    }
    private static function cents($amount)
    {
        if($amount%1000000!==0){throw new RuntimeException('NorwayBalancePrecision');}return intdiv($amount,1000000);
    }
    public static function groups()
    {
        return array('FixedAssets'=>'Assets','Inventory'=>'Assets','Receivables'=>'Assets','OtherReceivables'=>'Assets',
            'Investments'=>'Assets','Bank'=>'Assets','Equity'=>'Equity','LongDebt'=>'Debt','ShortLoans'=>'Debt',
            'Suppliers'=>'Debt','Tax'=>'Debt','OtherDebt'=>'Debt');
    }
    private static function group($number,$banks)
    {
        if($number[0]==='1' && (isset($banks[$number]) || substr($number,0,2)==='19')){return 'Bank';}
        if($number[0]==='1'){
            $prefix=(int)substr($number,0,2);
            if($prefix<=13){return 'FixedAssets';}if($prefix===14){return 'Inventory';}
            if(preg_match('/^150[0-9]$/D',$number)){return 'Receivables';}
            if($prefix===18){return 'Investments';}return 'OtherReceivables';
        }
        $prefix=substr($number,0,2);
        if($prefix==='20'){return 'Equity';}if(in_array($prefix,array('21','22'),true)){return 'LongDebt';}
        if($prefix==='23'){return 'ShortLoans';}if($prefix==='24'){return 'Suppliers';}
        if(in_array($prefix,array('25','26','27'),true)){return 'Tax';}return 'OtherDebt';
    }
    public static function build($db,$entity,$date,$user)
    {
        global $conf;
        require_once DOL_DOCUMENT_ROOT.'/accountancy/class/bookkeeping.class.php';
        require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
        if(!self::allowed($user) || $entity!==(int)$conf->entity || $entity<=0){throw new RuntimeException('NorwayBalanceAccess');}
        if(!preg_match('/^(20\d{2})-(\d{2})-(\d{2})$/D',(string)$date,$m) || !checkdate((int)$m[2],(int)$m[3],(int)$m[1])){throw new RuntimeException('NorwayBalanceDate');}
        if($conf->currency!=='NOK' || !in_array(getDolGlobalInt('SOCIETE_FISCAL_MONTH_START'),array(0,1),true)){throw new RuntimeException('NorwayBalanceScope');}
        $end=dol_mktime(23,59,59,(int)$m[2],(int)$m[3],(int)$m[1]);$p=$db->prefix();
        $isolation=self::rows($db,'SELECT @@tx_isolation AS level');
        if(($isolation[0]->level??'')!=='REPEATABLE-READ' || !$db->begin()){throw new RuntimeException('NorwayBalanceDatabase');}
        try{
            $report=array('date'=>$date,'baseline'=>null,'groups'=>array(),'assets'=>0,'equity'=>0,'debt'=>0,'result'=>0,'difference'=>0,'unposted'=>array());
            $closing=self::rows($db,"SELECT MAX(DATE(doc_date)) AS date FROM {$p}accounting_bookkeeping WHERE entity=$entity AND doc_type='closure' AND doc_date<='".$db->idate($end)."'");
            $filter=array('t.doc_date<='=>$end);
            if(!empty($closing[0]->date)){
                $baseline=substr($closing[0]->date,0,10);
                if(substr($baseline,5)!=='01-01'){throw new RuntimeException('NorwayBalanceOpening');}
                $sources=self::rows($db,"SELECT DISTINCT b.fk_doc,f.date_end FROM {$p}accounting_bookkeeping b LEFT JOIN {$p}accounting_fiscalyear f ON f.rowid=b.fk_doc AND f.entity=b.entity WHERE b.entity=$entity AND b.doc_type='closure' AND DATE(b.doc_date)='".$db->escape($closing[0]->date)."'");
                if(count($sources)!==1 || (int)$sources[0]->fk_doc<=0 || substr((string)$sources[0]->date_end,0,10)!==((int)substr($baseline,0,4)-1).'-12-31'){throw new RuntimeException('NorwayBalanceOpening');}
                $filter['t.doc_date>=']=$db->jdate($closing[0]->date);$report['baseline']=$baseline;
            }
            $first=self::rows($db,"SELECT MIN(doc_date) AS date FROM {$p}accounting_bookkeeping WHERE entity=$entity AND doc_date<='".$db->idate($end)."'");
            $report['range_start']=$report['baseline']??(!empty($first[0]->date)?substr($first[0]->date,0,10):$date);
            $range="doc_date<='".$db->idate($end)."'".($report['baseline']?" AND doc_date>='".$db->escape($report['baseline'])." 00:00:00'":'');
            $invalid=self::rows($db,"SELECT COUNT(*) n FROM {$p}accounting_bookkeeping WHERE entity=$entity AND (doc_date IS NULL OR (($range) AND (numero_compte IS NULL OR numero_compte='')))");
            if((int)$invalid[0]->n>0){throw new RuntimeException('NorwayBalanceAccount');}
            // Same native aggregation as Kontobalanse; no pagination, export flag or journal filter.
            $native=new BookKeeping($db);if($native->fetchAllBalance('','',0,0,$filter)<0){throw new RuntimeException('NorwayBalanceDatabase');}
            $accounts=array();foreach(self::rows($db,"SELECT a.account_number,a.label,a.pcg_type FROM {$p}accounting_account a JOIN {$p}accounting_system s ON s.pcg_version=a.fk_pcg_version WHERE a.entity=$entity AND s.rowid=".(int)getDolGlobalInt('CHARTOFACCOUNTS')) as $a){if(isset($accounts[$a->account_number])){throw new RuntimeException('NorwayBalanceAccount');}$accounts[$a->account_number]=$a;}
            if(!$accounts){throw new RuntimeException('NorwayBalanceAccount');}
            $balanceGroups=array_filter(array_map('trim',explode(',',getDolGlobalString('ACCOUNTING_CLOSURE_ACCOUNTING_GROUPS_USED_FOR_BALANCE_SHEET_ACCOUNT'))));
            $incomeGroups=array_filter(array_map('trim',explode(',',getDolGlobalString('ACCOUNTING_CLOSURE_ACCOUNTING_GROUPS_USED_FOR_INCOME_STATEMENT'))));
            if(!$balanceGroups || !$incomeGroups || array_intersect($balanceGroups,$incomeGroups)){throw new RuntimeException('NorwayBalanceAccount');}
            $banks=array();foreach(self::rows($db,"SELECT account_number FROM {$p}bank_account WHERE entity=$entity") as $bank){if($bank->account_number!==''){$banks[$bank->account_number]=true;}}
            $balance=0;
            foreach($native->lines as $line){
                $number=(string)$line->numero_compte;$a=$accounts[$number]??null;
                if(!$a || !preg_match('/^[1-8][0-9]{3}$/D',$number)){throw new RuntimeException('NorwayBalanceAccount:'.$number);}
                $debit=NorwayVatReport::amount($line->debit);$credit=NorwayVatReport::amount($line->credit);
                $amount=self::cents(self::add($debit,-$credit));$balance=self::add($balance,$amount);
                $isBalance=in_array($number[0],array('1','2'),true);
                if(!in_array(trim($a->pcg_type),$isBalance?$balanceGroups:$incomeGroups,true)
                    || (preg_match('/^[1-8]$/D',trim($a->pcg_type)) && $a->pcg_type!==$number[0])){throw new RuntimeException('NorwayBalanceAccount:'.$number);}
                if(!$isBalance){$report['result']=self::add($report['result'],-$amount);continue;}
                if($amount===0){continue;}
                $group=self::group($number,$banks);
                if(!isset($report['groups'][$group])){$report['groups'][$group]=array('amount'=>0,'accounts'=>array());}
                $sign=$number[0]==='1'?1:-1;$amount*=$sign;
                $report['groups'][$group]['amount']=self::add($report['groups'][$group]['amount'],$amount);
                $report['groups'][$group]['accounts'][]=array('number'=>$number,'label'=>$a->label,'amount'=>$amount);
                $side=self::groups()[$group];$key=strtolower($side);$report[$key]=self::add($report[$key],$amount);
            }
            // Remaining P&L is added once as a presentation row. Disposal journals already reduce it.
            $report['equity_with_result']=self::add($report['equity'],$report['result']);
            $report['total_funding']=self::add($report['equity_with_result'],$report['debt']);
            $report['difference']=self::add($report['assets'],-$report['total_funding']);
            if($report['difference']!==$balance){throw new RuntimeException('NorwayBalanceDatabase');}
            // Incomplete source transfer is visible without altering the ledger or its balances.
            $from=$report['baseline']?" AND f.datef>='".$db->escape($report['baseline'])."'":'';
            foreach(array('customer_invoice'=>'facture','supplier_invoice'=>'facture_fourn') as $kind=>$table){$count=self::rows($db,"SELECT COUNT(*) n FROM {$p}{$table} f WHERE f.entity=$entity AND f.fk_statut>0 AND f.datef<='".$db->idate($end)."'$from AND NOT EXISTS (SELECT b.rowid FROM {$p}accounting_bookkeeping b WHERE b.entity=f.entity AND b.doc_type='$kind' AND b.fk_doc=f.rowid)");$report['unposted'][$kind]=(int)$count[0]->n;}
            $from=$report['baseline']?" AND b.dateo>='".$db->escape($report['baseline'])."'":'';
            $count=self::rows($db,"SELECT COUNT(*) n FROM {$p}bank b JOIN {$p}bank_account a ON a.rowid=b.fk_account WHERE a.entity=$entity AND b.amount<>0 AND b.dateo<='".$db->idate($end)."'$from AND NOT EXISTS (SELECT g.rowid FROM {$p}accounting_bookkeeping g WHERE g.entity=a.entity AND g.doc_type='bank' AND g.fk_doc=b.rowid)");$report['unposted']['bank']=(int)$count[0]->n;
            $from=$report['baseline']?" AND doc_date>='".$db->escape($report['baseline'])."'":'';
            $count=self::rows($db,"SELECT COUNT(*) n FROM {$p}accounting_bookkeeping_tmp WHERE entity=$entity AND doc_date<='".$db->idate($end)."'$from AND (debit<>0 OR credit<>0)");$report['unposted']['draft']=(int)$count[0]->n;
            if(!$db->commit()){throw new RuntimeException('NorwayBalanceDatabase');}return $report;
        }catch(Throwable $error){$db->rollback();throw $error;}
    }
    public static function accountUrl($report,$number)
    {
        $start=$report['range_start'];
        return DOL_URL_ROOT.'/accountancy/bookkeeping/listbyaccount.php?'.http_build_query(array('mainmenu'=>'accountancy','leftmenu'=>'accountancy_accountancy',
            'search_accountancy_code_start'=>$number,'search_accountancy_code_end'=>$number,'search_date_startday'=>(int)substr($start,8,2),'search_date_startmonth'=>(int)substr($start,5,2),'search_date_startyear'=>(int)substr($start,0,4),
            'search_date_endday'=>(int)substr($report['date'],8,2),'search_date_endmonth'=>(int)substr($report['date'],5,2),'search_date_endyear'=>(int)substr($report['date'],0,4)));
    }
}
