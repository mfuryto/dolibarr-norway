<?php
// SPDX-License-Identifier: GPL-3.0-or-later
/** Guard the native close action; core remains responsible for posting and closing. */
final class NorwayYearEnd
{
    public static function guard($db, $entity, $source, $target, $generate, $auxiliary)
    {
        $p=$db->prefix();
        $read=static function($sql)use($db){$r=$db->query($sql);if(!$r){throw new RuntimeException('NorwayYearEndBlocked');}$out=array();while($row=$db->fetch_object($r)){$out[]=$row;}return $out;};
        // Connection-scoped lock stays held until the request closes its DB connection.
        // A simultaneous close fails closed before either request reads or posts balances.
        $lock=$read("SELECT GET_LOCK('norway-year-end-".(int)$entity."',0) acquired");
        if((int)($lock[0]->acquired??0)!==1){throw new RuntimeException('NorwayYearEndBusy');}
        $periods=$read("SELECT rowid,date_start,date_end,statut FROM {$p}accounting_fiscalyear WHERE entity=".(int)$entity.' AND rowid IN ('.(int)$source.','.(int)$target.')');
        $byId=array();foreach($periods as $row){$byId[(int)$row->rowid]=$row;}
        $old=$byId[$source]??null;$new=$byId[$target]??null;
        if(!$old || !$new || $source===$target || (int)$old->statut!==0 || (int)$new->statut!==0
            || strtotime(substr($new->date_start,0,10).' UTC')!==strtotime(substr($old->date_end,0,10).' UTC')+86400){throw new RuntimeException('NorwayYearEndBlocked');}
        if($read("SELECT rowid FROM {$p}accounting_bookkeeping WHERE entity=".(int)$entity." AND doc_type='closure' AND fk_doc=".(int)$source.' LIMIT 1')){throw new RuntimeException('NorwayYearEndBlocked');}
        if(!$generate){return;}
        // Native UI defaults this off; retain every existing auxiliary opening balance.
        if(!$auxiliary){throw new RuntimeException('NorwayYearEndAuxiliary');}
        $groups=array_unique(array_map('trim',array_merge(explode(',',getDolGlobalString('ACCOUNTING_CLOSURE_ACCOUNTING_GROUPS_USED_FOR_BALANCE_SHEET_ACCOUNT')),explode(',',getDolGlobalString('ACCOUNTING_CLOSURE_ACCOUNTING_GROUPS_USED_FOR_INCOME_STATEMENT')))));
        $rows=$read("SELECT b.numero_compte,SUM(b.debit-b.credit) amount,COUNT(DISTINCT aa.rowid) matches,MAX(aa.pcg_type) pcg_type FROM {$p}accounting_bookkeeping b LEFT JOIN {$p}accounting_account aa ON aa.entity=b.entity AND aa.account_number=b.numero_compte AND aa.fk_pcg_version IN (SELECT pcg_version FROM {$p}accounting_system WHERE rowid=".(int)getDolGlobalInt('CHARTOFACCOUNTS').") WHERE b.entity=".(int)$entity." AND b.doc_date>='".$db->escape($old->date_start)."' AND b.doc_date<='".$db->escape(substr($old->date_end,0,10))." 23:59:59' GROUP BY b.numero_compte");
        $sum=0;foreach($rows as $row){if((int)$row->matches!==1 || !in_array($row->pcg_type,$groups,true)){throw new RuntimeException('NorwayYearEndBlocked');}$sum+=(float)$row->amount;}
        if(abs($sum)>0.00001){throw new RuntimeException('NorwayYearEndBlocked');}
    }
}
