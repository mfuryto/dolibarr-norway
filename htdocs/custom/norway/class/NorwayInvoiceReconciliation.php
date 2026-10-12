<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require_once __DIR__.'/NorwayInvoiceLedger.php';
/** Exact eight-decimal gross comparison, without inferring account/tax roles. */
class NorwayInvoiceReconciliation
{
    public static function amount($value)
    {
        if ((!is_string($value) && !is_int($value)) || !preg_match('/^(-?)([0-9]{1,9})(?:\.([0-9]{1,8}))?$/D',(string)$value,$m)) { throw new InvalidArgumentException('Amount'); }
        $whole=(int)$m[2];$fraction=(int)str_pad($m[3] ?? '',8,'0');
        if (PHP_INT_SIZE<8 || $whole>100000000 || ($whole===100000000 && $fraction!==0)) { throw new InvalidArgumentException('Amount capacity'); }
        return ($m[1]==='-' ? -1 : 1)*($whole*100000000+$fraction);
    }
    public static function display($amount)
    {
        $sign=$amount<0 ? '-' : ''; $a=abs($amount);
        return $sign.intdiv($a,100000000).'.'.str_pad((string)($a%100000000),8,'0',STR_PAD_LEFT);
    }
    public static function evaluate($header,$ledger)
    {
        if (!in_array((int)$header->type,array(0,2),true) || !in_array((int)$header->fk_statut,array(1,2),true) || $header->multicurrency_code!=='NOK') { throw new InvalidArgumentException('Reconciliation scope'); }
        $net=self::amount($header->total_ht);$vat=self::amount($header->total_tva);$gross=self::amount($header->total_ttc);
        if ($ledger['truncated'] || count($ledger['rows'])>200) { throw new RuntimeException('Reconciliation truncated'); }
        if (!$ledger['rows']) { return array('status'=>'Empty','debit'=>null,'credit'=>null,'gross'=>self::display(abs($gross)),'difference'=>null); }
        $debit=0;$credit=0;$keys=array();$valid=true;
        foreach ($ledger['rows'] as $row) {
            $d=self::amount($row->debit);$c=self::amount($row->credit);
            if ($d<0 || $c<0 || ($d>0 && $c>0) || !in_array($row->trace,array('Linked','Aggregated'),true)
                || substr((string)$row->doc_date,0,10)!==substr((string)$header->datef,0,10)) { $valid=false; }
            $debit+=$d;$credit+=$c;$keys[(string)$row->code_journal.'|'.(string)$row->piece_num]=true;
        }
        // Do not conceal missing references, split vouchers or unsupported signs behind balanced sums.
        $expectedSign=(int)$header->type===2 ? -1 : 1;
        $valid=$valid && count($keys)===1 && $gross*$expectedSign>0 && $net*$expectedSign>=0 && $vat*$expectedSign>=0;
        $status=!$valid ? 'Review' : (($debit!==$credit || abs($net+$vat-$gross)>1000000
            || abs($debit-abs($gross))>1000000 || abs($credit-abs($gross))>1000000) ? 'Different' : 'Match');
        return array('status'=>$status,'debit'=>self::display($debit),'credit'=>self::display($credit),'gross'=>self::display(abs($gross)),
            'difference'=>self::display($debit-$credit));
    }
    public static function report($db,$entity,$invoiceId)
    {
        if (!is_int($entity) || $entity<=0 || $entity!==(int)$GLOBALS['conf']->entity || !is_int($invoiceId) || $invoiceId<=0) { throw new InvalidArgumentException('Reconciliation entity'); }
        $r=$db->query('SELECT ref,type,fk_statut,datef,multicurrency_code,total_ht,total_tva,total_ttc FROM '.$db->prefix().'facture WHERE entity='.$entity.' AND rowid='.$invoiceId);
        $header=$r ? $db->fetch_object($r) : null;
        if (!$header) { throw new RuntimeException('Reconciliation invoice'); }
        return self::evaluate($header,NorwayInvoiceLedger::report($db,$entity,$invoiceId));
    }
}
