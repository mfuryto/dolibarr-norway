<?php
// SPDX-License-Identifier: GPL-3.0-or-later
/** Read-only dictionary mappings, not invoice-line tax selection or a VAT report. */
class NorwayVatSetup
{
    private static function rows($db,$sql)
    {
        $r=$db->query($sql); if (!$r) { throw new RuntimeException('VAT setup'); }
        $rows=array(); while ($row=$db->fetch_object($r)) { $rows[]=(array)$row; } return $rows;
    }

    public static function report($db,$entity,$chartId,$defaults)
    {
        if ((int)$entity<=0 || (int)$GLOBALS['conf']->entity!==(int)$entity) { throw new InvalidArgumentException('Entity'); }
        $entity=(int)$entity; $p=$db->prefix();
        $charts=self::rows($db,'SELECT pcg_version,active FROM '.$p.'accounting_system WHERE rowid='.(int)$chartId);
        $chart=$charts ? $charts[0] : null;
        $rates=self::rows($db,"SELECT t.rowid,t.code,t.type_vat,t.taux,t.accountancy_code_sell,t.accountancy_code_buy FROM ".$p."c_tva t INNER JOIN ".$p."c_country c ON c.rowid=t.fk_pays WHERE c.code='NO' AND t.entity=".$entity." AND t.active=1 ORDER BY t.taux,t.code,t.rowid");
        $report=array();
        foreach ($rates as $rate) {
            $directions=(int)$rate['type_vat']===1 ? array('sell') : ((int)$rate['type_vat']===2 ? array('buy') : ((int)$rate['type_vat']===0 ? array('sell','buy') : array('unknown')));
            foreach ($directions as $direction) {
                $explicit=$direction==='unknown' || empty($rate['accountancy_code_'.$direction]) ? '' : (string)$rate['accountancy_code_'.$direction];
                $fallback=$direction==='unknown' ? '' : (string)($defaults[$direction] ?? '');
                $number=$explicit!=='' ? $explicit : $fallback;
                $status='Unset'; $label='';
                if (!in_array($number,array('','-1','NotDefined'),true)) {
                    $accounts=$chart ? self::rows($db,"SELECT active,label FROM ".$p."accounting_account WHERE entity=".$entity." AND fk_pcg_version='".$db->escape($chart['pcg_version'])."' AND account_number='".$db->escape($number)."'".$db->plimit(2)) : array();
                    $status=!$accounts ? 'Missing' : (count($accounts)>1 ? 'Ambiguous' : ((int)$chart['active']!==1 || (int)$accounts[0]['active']!==1 ? 'Inactive' : 'Available'));
                    if (count($accounts)===1) { $label=$accounts[0]['label']; }
                }
                $report[]=array('id'=>(int)$rate['rowid'],'code'=>$rate['code'],'rate'=>(string)$rate['taux'],'direction'=>$direction,'explicit'=>$explicit,'fallback'=>$fallback,'number'=>$number,'label'=>$label,'source'=>$explicit!=='' ? 'Explicit' : 'Fallback','status'=>$status);
            }
        }
        return $report;
    }
}
