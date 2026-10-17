<?php
// SPDX-License-Identifier: GPL-3.0-or-later
/** Derived portal figures and native settlement classification; no accounting writes. */
class NorwayVatPeriod
{
    public static function dates($year,$term)
    {
        if(!preg_match('/^20[0-9]{2}$/D',(string)$year) || !preg_match('/^[1-6]$/D',(string)$term)){throw new RuntimeException('NorwayVatReportPeriod');}
        $month=(int)$term*2;$start=sprintf('%04d-%02d-01',$year,$month-1);
        return array($start,sprintf('%04d-%02d-%02d',$year,$month,cal_days_in_month(CAL_GREGORIAN,$month,(int)$year)));
    }
    public static function complete($start,$end)
    {
        foreach(range(1,6) as $term){if(self::dates(substr($start,0,4),$term)===array($start,$end)){return true;}}return false;
    }
    public static function portal($report)
    {
        if($report['issues'] || !self::complete($report['start'],$report['end'])){throw new RuntimeException('NorwayVatReportIncomplete');}
        $out=array('rows'=>array(),'net'=>0);
        foreach($report['groups'] as $g){if(!$g['reported']){continue;}$g['base']=$g['base']===null?null:intdiv($g['base'],100);$g['tax']=intdiv($g['tax'],100);$out['rows'][]=$g;$out['net']+=($g['type']===1?1:-1)*$g['tax'];}
        $out['rounding']=$out['net']*100-$report['net'];return $out;
    }
    public static function reference($start,$end){return 'MVA '.$start.' / '.$end;}
    public static function settlement($rows,$taxAccounts=array())
    {
        $hasTax=false;$hasSettlement=false;$date=null;$ref=null;$balance=0;
        foreach($rows as $b){
            if($b->numero_compte===null && (float)$b->debit===0.0 && (float)$b->credit===0.0){continue;}
            if($b->doc_type!=='vat_settlement' || (int)$b->fk_doc!==0 || (int)$b->fk_docdet!==0 || ($date!==null && $date!==substr($b->doc_date,0,10)) || ($ref!==null && $ref!==$b->doc_ref)){return false;}
            $date=substr($b->doc_date,0,10);$ref=$b->doc_ref;$a=(string)$b->numero_compte;
            $isTax=isset($taxAccounts[$a]) || preg_match('/^27[01][0-9]$/D',$a);
            if(!$isTax && !in_array($a,array('2740','7740'),true)){return false;}
            if((float)$b->debit!=0 && (float)$b->credit!=0){return false;}
            $hasTax=$hasTax || $isTax;$hasSettlement=$hasSettlement || $a==='2740';
            $balance+=NorwayVatReport::amount($b->debit)-NorwayVatReport::amount($b->credit);
        }
        return $hasTax && $hasSettlement && NorwayVatReport::cents($balance)===0;
    }
    public static function proposal($report)
    {
        $portal=self::portal($report);$rows=array();$sum=0;
        foreach($report['tax_accounts'] as $account=>$amount){$amount=-NorwayVatReport::cents($amount);if($amount!==0){$rows[(string)$account]=$amount;$sum+=$amount;}}
        $liability=-$portal['net']*100-NorwayVatReport::cents($report['settled']);
        if($liability!==0){$rows['2740']=$liability;$sum+=$liability;}
        if($sum!==0){$rows['7740']=-$sum;}return $rows;
    }
}
