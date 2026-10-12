<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require_once __DIR__.'/NorwayDefaultAccounts.php';
/** Confirmed fill-only ordinary domestic VAT dictionary accounts; never change rates. */
class NorwayVatAccounts
{
    private static function rows($db,$sql)
    {
        $result=$db->query($sql); if (!$result) { throw new RuntimeException('VAT accounts'); }
        $rows=array(); while ($row=$db->fetch_object($result)) { $rows[]=(array)$row; } return $rows;
    }

    public static function preview($db,$entity)
    {
        // Reuse the canonical chart, current entity and unused-ledger evidence.
        $base=NorwayDefaultAccounts::preview($db,$entity,array('ACCOUNTING_VAT_SOLD_ACCOUNT'=>'2700','ACCOUNTING_VAT_BUY_ACCOUNT'=>'2710'));
        $p=$db->prefix();$entity=(int)$entity;
        $rates=self::rows($db,"SELECT t.* FROM ".$p."c_tva t INNER JOIN ".$p."c_country c ON c.rowid=t.fk_pays WHERE t.entity=".$entity." AND c.code='NO' AND t.active=1 ORDER BY t.rowid");
        $labels=array_column(NorwayChartPreview::parse(NorwayChartProfile::csv()),'label','number');
        $accounts=self::rows($db,"SELECT rowid,account_number,label,active FROM ".$p."accounting_account WHERE entity=".$entity." AND fk_pcg_version='".$db->escape(NorwayChartProfile::VERSION)."' AND account_number IN ('2700','2710','2701','2711','2703','2713') ORDER BY rowid");
        $chart=$base['chart'];
        $validChart=$chart && $chart['pcg_version']===NorwayChartProfile::VERSION && $chart['country']==='NO' && (int)$chart['active']===1 && (int)$chart['rowid']===$base['runtime_chart'];
        $rows=array();$map=array('25'=>array('2700','2710'),'15'=>array('2701','2711'),'12'=>array('2703','2713'));
        foreach ($rates as $rate) {
            $key=(string)(float)$rate['taux'];
            // Coded or special rows may describe imports, reverse charge or another purpose.
            $ordinary=isset($map[$key]) && trim((string)$rate['code'])==='' && (int)$rate['recuperableonly']===0
                && empty($rate['fk_department_buyer']) && empty($rate['einvoice_vatex'])
                && (float)$rate['localtax1']==0 && (float)$rate['localtax2']==0
                && empty($rate['localtax1_type']) && empty($rate['localtax2_type'])
                && in_array((int)$rate['type_vat'],array(0,1,2),true);
            foreach (array('sell'=>0,'buy'=>1) as $direction=>$index) {
                if ((int)$rate['type_vat']===($direction==='sell' ? 2 : 1)) { continue; }
                $current=(string)$rate['accountancy_code_'.$direction];
                $number=$ordinary ? $map[$key][$index] : '';
                $matches=array_values(array_filter($accounts,static fn($a)=>$a['account_number']===$number));
                $validAccount=count($matches)===1 && (int)$matches[0]['active']===1 && $matches[0]['label']===$labels[$number];
                $action=trim($current)!=='' ? 'Keep' : (!$ordinary ? 'Separate' : (!$validChart || $base['history'] || !$validAccount ? 'Blocked' : 'Fill'));
                $rows[]=array('id'=>(int)$rate['rowid'],'direction'=>$direction,'rate'=>$key,'code'=>(string)$rate['code'],'note'=>(string)$rate['note'],'current'=>$current,'number'=>$number,'label'=>$number!=='' ? $labels[$number] : '', 'action'=>$action);
            }
        }
        return array('entity'=>$entity,'base'=>$base,'rates'=>$rates,'accounts'=>$accounts,'rows'=>$rows);
    }

    public static function apply($db,$entity,$fingerprint,$user)
    {
        if (empty($user->admin) || (int)$user->id<=0 || !$user->hasRight('accounting','chartofaccount')) { throw new InvalidArgumentException('Permission'); }
        $active=self::rows($db,'SELECT @@in_transaction AS active');
        if (count($active)!==1 || (int)$active[0]['active']!==0 || !$db->query('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE') || $db->begin()<=0) { throw new RuntimeException('Transaction'); }
        try {
            $p=$db->prefix();$entity=(int)$entity;
            if ($entity<=0 || $entity!==(int)$GLOBALS['conf']->entity) { throw new InvalidArgumentException('Entity'); }
            foreach (array(
                "SELECT rowid FROM ".$p."c_tva WHERE entity=".$entity." FOR UPDATE",
                "SELECT rowid FROM ".$p."accounting_system FOR UPDATE",
                "SELECT rowid FROM ".$p."const WHERE entity IN (0,".$entity.") AND name IN ('CHARTOFACCOUNTS','ACCOUNTING_VAT_SOLD_ACCOUNT','ACCOUNTING_VAT_BUY_ACCOUNT') FOR UPDATE",
                "SELECT rowid FROM ".$p."accounting_account WHERE entity=".$entity." FOR UPDATE",
                "SELECT rowid FROM ".$p."facture WHERE entity=".$entity." FOR UPDATE",
                "SELECT rowid FROM ".$p."facture_fourn WHERE entity=".$entity." FOR UPDATE",
                "SELECT rowid FROM ".$p."accounting_bookkeeping WHERE entity=".$entity." FOR UPDATE"
            ) as $sql) { self::rows($db,$sql); }
            $preview=self::preview($db,$entity);
            if (!hash_equals(NorwayDefaultAccounts::fingerprint($preview),(string)$fingerprint)) { throw new InvalidArgumentException('Changed'); }
            $saved=0;
            foreach ($preview['rows'] as $row) {
                if ($row['action']==='Blocked') { throw new InvalidArgumentException('Blocked'); }
                if ($row['action']!=='Fill') { continue; }
                $field='accountancy_code_'.$row['direction'];
                if (!$db->query("UPDATE ".$p."c_tva SET ".$field."='".$row['number']."' WHERE rowid=".$row['id']." AND entity=".$entity." AND (".$field." IS NULL OR TRIM(".$field.")='')")) { throw new RuntimeException('Save'); }
                $stored=self::rows($db,'SELECT '.$field.' AS value FROM '.$p.'c_tva WHERE rowid='.$row['id'].' AND entity='.$entity);
                if (count($stored)!==1 || $stored[0]['value']!==$row['number']) { throw new RuntimeException('Save'); }
                $saved++;
            }
            if ($db->commit()<=0) { throw new RuntimeException('Commit'); } return $saved;
        } catch (Throwable $error) { $db->rollback();throw $error; }
    }
}
