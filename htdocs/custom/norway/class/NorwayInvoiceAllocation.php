<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require_once __DIR__.'/NorwayInvoiceReconciliation.php';
/** Explicit current-source comparison; never infer historical tax treatment from an account. */
class NorwayInvoiceAllocation
{
    public static function profiles()
    {
        return array('domestic25'=>array('rate'=>25,'customer'=>'1500','sales'=>array('3000','3010','3020'),'vat'=>'2700'),
            'domestic15'=>array('rate'=>15,'customer'=>'1500','sales'=>array('3030','3040'),'vat'=>'2701'),
            'domestic12'=>array('rate'=>12,'customer'=>'1500','sales'=>array('3050'),'vat'=>'2703'));
    }
    public static function evaluate($header,$lines,$ledger,$profile)
    {
        $profiles=self::profiles();if (!isset($profiles[$profile])) { throw new InvalidArgumentException('Allocation profile'); }
        $rule=$profiles[$profile];$gross=NorwayInvoiceReconciliation::evaluate($header,$ledger);
        if ($ledger['truncated'] || count($lines)>200) { throw new RuntimeException('Allocation truncated'); }
        if (!$lines || ($header->buyer_country ?? '')!=='NO' || ($header->seller_country ?? '')!=='NO') { throw new InvalidArgumentException('Domestic scope'); }
        $net=0;$vat=0;$ids=array();$sign=(int)$header->type===2 ? -1 : 1;
        foreach ($lines as $line) {
            if (!isset($line->tva_tx,$line->localtax1_tx,$line->localtax2_tx)) { throw new InvalidArgumentException('Missing tax fields'); }
            $n=NorwayInvoiceReconciliation::amount($line->total_ht);$v=NorwayInvoiceReconciliation::amount($line->total_tva);
            if ((float)$line->tva_tx!==(float)$rule['rate'] || (int)$line->rowid<=0 || isset($ids[$line->rowid])
                || !isset($line->info_bits) || (string)$line->info_bits!=='0' || (float)$line->localtax1_tx!=0 || (float)$line->localtax2_tx!=0
                || $n*$sign<0 || $v*$sign<0 || abs($v*100-$n*$rule['rate'])>100000000) { throw new InvalidArgumentException('Line scope'); }
            $net+=$n;$vat+=$v;$ids[$line->rowid]=true;
        }
        $expected=array('Customer'=>NorwayInvoiceReconciliation::amount($header->total_ttc),'Sales'=>-$net,'Vat'=>-$vat,'Other'=>0);
        if (abs($net-NorwayInvoiceReconciliation::amount($header->total_ht))>1000000 || abs($vat-NorwayInvoiceReconciliation::amount($header->total_tva))>1000000) { throw new InvalidArgumentException('Source totals'); }
        $actual=array_fill_keys(array_keys($expected),0);$rows=array();$unknown=false;
        foreach ($ledger['rows'] as $row) {
            $number=(string)$row->numero_compte;
            $role=$number===$rule['customer'] ? 'Customer' : ($number===$rule['vat'] ? 'Vat' : (in_array($number,$rule['sales'],true) ? 'Sales' : 'Other'));
            $value=NorwayInvoiceReconciliation::amount($row->debit)-NorwayInvoiceReconciliation::amount($row->credit);
            $actual[$role]+=$value;$unknown=$unknown || $role==='Other';
            $rows[]=array('id'=>(int)$row->rowid,'account'=>$number,'role'=>$role,'amount'=>NorwayInvoiceReconciliation::display($value));
        }
        $totals=array();$different=$unknown || $gross['status']==='Different';
        foreach ($expected as $role=>$value) {
            $delta=$actual[$role]-$value;$different=$different || abs($delta)>1000000;
            $totals[]=array('role'=>$role,'expected'=>NorwayInvoiceReconciliation::display($value),'actual'=>NorwayInvoiceReconciliation::display($actual[$role]),'difference'=>NorwayInvoiceReconciliation::display($delta));
        }
        $status=$gross['status']==='Empty' ? 'Empty' : ($gross['status']==='Review' ? 'Review' : ($different ? 'Different' : 'Match'));
        return array('profile'=>$profile,'status'=>$status,'totals'=>$totals,'rows'=>$rows);
    }
    public static function report($db,$entity,$id,$profile)
    {
        if (!is_int($entity) || $entity<=0 || $entity!==(int)$GLOBALS['conf']->entity || !is_int($id) || $id<=0) { throw new InvalidArgumentException('Entity'); }
        $active=$db->query('SELECT @@in_transaction AS active');$state=$active ? $db->fetch_object($active) : null;
        if (!$state || (int)$state->active!==0 || !$db->query('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ') || !$db->query('SET TRANSACTION READ ONLY') || $db->begin()<=0) { throw new RuntimeException('Read snapshot'); }
        try {
            $result=self::read($db,$entity,$id,$profile);
            if ($db->commit()<=0) { throw new RuntimeException('Snapshot commit'); }return $result;
        } catch (Throwable $error) { $db->rollback();throw $error; }
    }
    private static function read($db,$entity,$id,$profile)
    {
        $p=$db->prefix();
        $r=$db->query('SELECT f.ref,f.type,f.fk_statut,f.datef,f.multicurrency_code,f.total_ht,f.total_tva,f.total_ttc,c.code AS buyer_country FROM '.$p.'facture f LEFT JOIN '.$p.'societe s ON s.rowid=f.fk_soc AND s.entity=f.entity LEFT JOIN '.$p.'c_country c ON c.rowid=s.fk_pays WHERE f.entity='.$entity.' AND f.rowid='.$id);
        $header=$r ? $db->fetch_object($r) : null;if (!$header) { throw new RuntimeException('Invoice'); }
        $header->seller_country=$GLOBALS['mysoc']->country_code ?? '';
        $r=$db->query('SELECT rowid,total_ht,total_tva,tva_tx,info_bits,localtax1_tx,localtax2_tx FROM '.$p.'facturedet WHERE fk_facture='.$id.' ORDER BY rowid LIMIT 201');
        if (!$r) { throw new RuntimeException('Lines'); }$lines=array();while ($line=$db->fetch_object($r)) { $lines[]=$line; }
        $ledger=NorwayInvoiceLedger::report($db,$entity,$id);$keys=array();
        foreach ($ledger['rows'] as $row) {
            $key=$row->code_journal.'|'.$row->piece_num;if(isset($keys[$key])) { continue; }$keys[$key]=true;
            $extra=$db->query("SELECT rowid FROM ".$p."accounting_bookkeeping WHERE entity=".$entity." AND code_journal='".$db->escape($row->code_journal)."' AND piece_num=".(int)$row->piece_num." AND (doc_type IS NULL OR doc_type<>'customer_invoice' OR fk_doc IS NULL OR fk_doc<>".$id.") LIMIT 1");
            if (!$extra || $db->fetch_object($extra)) { throw new RuntimeException('Incomplete voucher'); }
        }
        return self::evaluate($header,$lines,$ledger,$profile);
    }
}
