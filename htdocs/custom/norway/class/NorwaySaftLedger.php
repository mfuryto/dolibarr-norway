<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require_once __DIR__.'/NorwayVatReport.php';
/** One read-only period snapshot for SAF-T; no UI filters, export flags or source writes. */
class NorwaySaftLedger
{
    private static function rows($db,$sql)
    {
        $r=$db->query($sql);if(!$r){throw new RuntimeException('NorwaySaftDatabase');}
        $out=array();while($row=$db->fetch_object($r)){$out[]=$row;}return $out;
    }
    private static function add($a,$b)
    {
        $sum=$a+$b;if(!is_int($sum) || $sum===PHP_INT_MIN){throw new RuntimeException('NorwaySaftAmount');}return $sum;
    }
    private static function date($date)
    {
        if(!is_string($date) || !preg_match('/^(20\d{2})-(\d{2})-(\d{2})$/D',$date,$m) || !checkdate((int)$m[2],(int)$m[3],(int)$m[1])){throw new RuntimeException('NorwaySaftPeriod');}
        return $date;
    }
    public static function read($db,$entity,$start,$end,$user,$snapshot=false)
    {
        global $conf;
        if((int)$entity<=0 || (int)$entity!==(int)$conf->entity || !isModEnabled('norway') || !$user || !empty($user->socid)
            || !$user->hasRight('accounting','mouvements','lire') || !$user->hasRight('accounting','mouvements','export')){throw new RuntimeException('NorwaySaftAccess');}
        self::date($start);self::date($end);
        if($start>$end || substr($start,0,4)!==substr($end,0,4) || $conf->currency!=='NOK' || !in_array(getDolGlobalInt('SOCIETE_FISCAL_MONTH_START'),array(0,1),true)){throw new RuntimeException('NorwaySaftPeriod');}
        $p=$db->prefix();$isolation=self::rows($db,'SELECT @@tx_isolation AS level');
        if(($isolation[0]->level??'')!=='REPEATABLE-READ' || (!$snapshot && !$db->begin())){throw new RuntimeException('NorwaySaftDatabase');}
        try{
            $out=array('entity'=>(int)$entity,'start'=>$start,'end'=>$end,'currency'=>'NOK','lines'=>array(),'vouchers'=>array(),'accounts'=>array(),'total_debit'=>0,'total_credit'=>0);
            // Read every posted row through the period end, including rows previously marked as exported.
            // A native carry-forward replaces earlier history; it is an opening balance, not a new turnover.
            $all=self::rows($db,'SELECT b.* FROM '.$p.'accounting_bookkeeping b WHERE b.entity='.(int)$entity." AND (b.doc_date<='".$db->escape($end)." 23:59:59' OR b.doc_date IS NULL) ORDER BY b.rowid");
            $seen=array();$complete=array();$pieceCounts=array();$baseline=null;
            foreach($all as $row){if($row->doc_type==='closure'){
                $date=substr((string)$row->doc_date,0,10);
                if(substr($date,5)!=='01-01' || $date>$start || (int)$row->fk_doc<=0){throw new RuntimeException('NorwaySaftOpeningBalance');}
                if($baseline===null || $date>$baseline){$baseline=$date;}
            }}
            // A previous fiscal year needs the core annual carry-forward first.
            // Do not silently carry prior-year revenue into this year's opening balance.
            if($baseline===null){foreach($all as $row){if(substr((string)$row->doc_date,0,4)<substr($start,0,4)){throw new RuntimeException('NorwaySaftOpeningBalance');}}}
            if($baseline!==null && $baseline!==substr($start,0,4).'-01-01'){throw new RuntimeException('NorwaySaftOpeningBalance');}
            $out['history']=array();$out['baseline']=$baseline;
            $closureSources=array();
            foreach($all as $row){if($row->doc_type==='closure' && substr($row->doc_date,0,10)===$baseline){$closureSources[(int)$row->fk_doc]=true;}}
            if(count($closureSources)>1){throw new RuntimeException('NorwaySaftOpeningBalance');}
            if($closureSources){
                $source=(int)array_key_first($closureSources);
                $fiscal=self::rows($db,'SELECT date_end FROM '.$p.'accounting_fiscalyear WHERE entity='.(int)$entity.' AND rowid='.$source);
                if(count($fiscal)!==1 || substr((string)$fiscal[0]->date_end,0,10)!==((int)substr($start,0,4)-1).'-12-31'){throw new RuntimeException('NorwaySaftOpeningBalance');}
                $keys=array();foreach($all as $row){if($row->doc_type!=='closure' || substr($row->doc_date,0,10)!==$baseline){continue;}
                    $key=$row->numero_compte.'/'.(string)$row->subledger_account;if(isset($keys[$key])){throw new RuntimeException('NorwaySaftOpeningBalance');}$keys[$key]=true;
                }
            }
            foreach($all as $row){
                if($baseline!==null && substr((string)$row->doc_date,0,10)<$baseline){continue;}
                if(!in_array((string)$row->doc_type,array('','manual','customer_invoice','supplier_invoice','bank','vat_settlement','closure'),true)){throw new RuntimeException('NorwaySaftSource');}
                $date=substr((string)$row->doc_date,0,10);self::date($date);
                if(!in_array((string)$row->multicurrency_code,array('','NOK'),true)){throw new RuntimeException('NorwaySaftPeriod');}
                $out['history'][]=(array)$row;
                $piece=(int)$row->piece_num;$id=(int)$row->rowid;
                if($piece<=0 || $id<=0 || isset($seen[$id]) || trim((string)$row->numero_compte)===''){throw new RuntimeException('NorwaySaftSource');}$seen[$id]=true;
                $debit=NorwayVatReport::amount($row->debit);$credit=NorwayVatReport::amount($row->credit);
                if($debit%1000000!==0 || $credit%1000000!==0){throw new RuntimeException('NorwaySaftPrecision');}
                if($debit<0 || $credit<0 || ($debit!==0 && $credit!==0)){throw new RuntimeException('NorwaySaftAmount');}
                $account=(string)$row->numero_compte;
                if(!isset($out['accounts'][$account])){$out['accounts'][$account]=array('number'=>$account,'label'=>(string)$row->label_compte,'opening'=>0,'movement'=>0,'closing'=>0);}
                $pieceCounts[$piece]=($pieceCounts[$piece]??0)+1;
                $delta=$debit-$credit;$complete[$piece]=self::add($complete[$piece]??0,$delta);
                $out['accounts'][$account]['closing']=self::add($out['accounts'][$account]['closing'],$delta);
                if($date<$start || $row->doc_type==='closure'){$out['accounts'][$account]['opening']=self::add($out['accounts'][$account]['opening'],$delta);continue;}
                $out['accounts'][$account]['movement']=self::add($out['accounts'][$account]['movement'],$delta);
                $key=(string)$piece;
                if(!isset($out['vouchers'][$key])){$out['vouchers'][$key]=array('piece'=>$piece,'date'=>$date,'journal'=>(string)$row->code_journal,'lines'=>array(),'balance'=>0);}
                $voucher=&$out['vouchers'][$key];
                if($voucher['date']!==$date || $voucher['journal']!==(string)$row->code_journal || trim($voucher['journal'])===''){throw new RuntimeException('NorwaySaftVoucher');}
                $voucher['lines'][]=$id;$voucher['balance']=self::add($voucher['balance'],$delta);unset($voucher);
                $out['lines'][$id]=(array)$row;$out['total_debit']=self::add($out['total_debit'],$debit);$out['total_credit']=self::add($out['total_credit'],$credit);
            }
            // One grouped read also detects balanced subsets straddling a boundary.
            $counts=self::rows($db,'SELECT piece_num,COUNT(*) AS n FROM '.$p.'accounting_bookkeeping WHERE entity='.(int)$entity.' GROUP BY piece_num');
            foreach($counts as $count){$piece=(int)$count->piece_num;if(isset($pieceCounts[$piece]) && (int)$count->n!==$pieceCounts[$piece]){throw new RuntimeException('NorwaySaftVoucher');}}
            foreach($out['vouchers'] as $voucher){if($voucher['balance']!==0){throw new RuntimeException('NorwaySaftVoucher');}}
            if($out['total_debit']!==$out['total_credit'] || array_filter($complete,static fn($value)=>$value!==0)){throw new RuntimeException('NorwaySaftBalance');}
            foreach($out['accounts'] as $account){if(self::add($account['opening'],$account['movement'])!==$account['closing']){throw new RuntimeException('NorwaySaftBalance');}}
            ksort($out['accounts'],SORT_NATURAL);ksort($out['vouchers'],SORT_NUMERIC);
            if(!$snapshot && !$db->commit()){throw new RuntimeException('NorwaySaftDatabase');}return $out;
        }catch(Throwable $error){if(!$snapshot){$db->rollback();}throw $error;}
    }
}
