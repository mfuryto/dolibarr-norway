<?php
// SPDX-License-Identifier: GPL-3.0-or-later
/** Read-only finite fiscal-period diagnostics, not a posting permission decision. */
class NorwayPeriodSetup
{
    public static function validDate($value)
    {
        if (!is_string($value) || !preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D',$value)) { return false; }
        $date=DateTimeImmutable::createFromFormat('!Y-m-d',$value);
        return $date && $date->format('Y-m-d')===$value;
    }

    public static function evaluate($periods,$date,$defaultId,$mode)
    {
        if (!self::validDate($date)) { throw new InvalidArgumentException('Date'); }
        $rows=array(); $cover=array(); $uncertain=false; $default='Missing';
        foreach ($periods as $period) {
            $valid=self::validDate($period['date_start']) && self::validDate($period['date_end']) && $period['date_start']<=$period['date_end'];
            $uncertain=$uncertain || !$valid;
            $known=in_array((int)$period['statut'],array(0,1),true);
            $overlap=false;
            if ($valid) {
                foreach ($periods as $other) {
                    if ($other['rowid']===$period['rowid'] || !self::validDate($other['date_start']) || !self::validDate($other['date_end']) || $other['date_start']>$other['date_end']) { continue; }
                    if ($period['date_start']<=$other['date_end'] && $other['date_start']<=$period['date_end']) { $overlap=true; }
                }
                if ($period['date_start']<=$date && $date<=$period['date_end']) { $cover[]=$period; }
            }
            $status=!$valid ? 'Dates' : (!$known ? 'Unknown' : ($overlap ? 'Overlap' : ((int)$period['statut']===0 ? 'Open' : 'Closed')));
            if ((int)$period['rowid']===(int)$defaultId) { $default=$status; }
            $rows[]=array('id'=>(int)$period['rowid'],'label'=>$period['label'],'start'=>(string)$period['date_start'],'end'=>(string)$period['date_end'],'status'=>$status);
        }
        $coverage=count($cover)===0 ? 'Missing' : (count($cover)>1 ? 'Overlap' : ((int)$cover[0]['statut']===0 ? 'Open' : ((int)$cover[0]['statut']===1 ? 'Closed' : 'Unknown')));
        if ($uncertain) { $coverage='Dates'; }
        return array('rows'=>$rows,'date'=>$date,'coverage'=>$coverage,'default'=>$default,'default_id'=>(int)$defaultId,'mode'=>$mode);
    }

    public static function report($db,$entity,$date,$defaultId,$mode)
    {
        if ((int)$entity<=0 || (int)$GLOBALS['conf']->entity!==(int)$entity) { throw new InvalidArgumentException('Entity'); }
        $r=$db->query('SELECT rowid,label,date_start,date_end,statut FROM '.$db->prefix().'accounting_fiscalyear WHERE entity='.(int)$entity.' ORDER BY date_start,rowid');
        if (!$r) { throw new RuntimeException('Period setup'); }
        $periods=array(); while ($row=$db->fetch_object($r)) { $periods[]=(array)$row; }
        return self::evaluate($periods,$date,$defaultId,$mode);
    }
}
