<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require_once __DIR__.'/NorwayPostingAllocation.php';
/** Explicit deduction comparison only. Never infer the right to deduct VAT. */
class NorwayPurchaseAllocation
{
    public static function profiles()
    {
        $profiles=array();
        foreach (array(25=>'2710',15=>'2711',12=>'2713') as $rate=>$vat) {
            foreach (array('full','partial','none') as $mode) { $profiles['purchase'.$rate.$mode]=array('rate'=>$rate,'supplier'=>'2400','vat'=>$vat,'deduction'=>$mode); }
        }
        return $profiles;
    }
    /** Basis points, explicitly supplied for partial comparisons; no inferred percentage. */
    public static function deduction($profile,$percentage=null)
    {
        $profiles=self::profiles();if (!isset($profiles[$profile])) { throw new InvalidArgumentException('Purchase profile'); }
        $mode=$profiles[$profile]['deduction'];
        if ($mode==='full') { return 10000; }if ($mode==='none') { return 0; }
        if (!is_string($percentage) || !preg_match('/^(0|[1-9][0-9]?)(?:\.([0-9]{1,2}))?$/D',$percentage,$m)) { throw new InvalidArgumentException('Partial deduction percentage'); }
        $bp=(int)$m[1]*100+(int)str_pad($m[2] ?? '',2,'0');
        if ($bp<=0 || $bp>=10000) { throw new InvalidArgumentException('Partial deduction range'); }return $bp;
    }
    /** Round each two-decimal VAT line to cents, symmetrically for credit notes. */
    public static function deductibleVat($vat,$basisPoints)
    {
        if (!is_int($vat) || !is_int($basisPoints) || $basisPoints<0 || $basisPoints>10000 || $vat%1000000!==0) { throw new InvalidArgumentException('Deduction amount scope'); }
        $cents=intdiv(abs($vat),1000000);
        return ($vat<0 ? -1 : 1)*intdiv($cents*$basisPoints+5000,10000)*1000000;
    }
    public static function evaluate($observations,$ledger,$profile,$percentage=null)
    {
        $profiles=self::profiles();if (!isset($profiles[$profile])) { throw new InvalidArgumentException('Purchase profile'); }
        $basisPoints=self::deduction($profile,$percentage);
        $pending=array('status'=>'Review','source_changed'=>false,'totals'=>array());
        foreach ($observations as $entry) { if (($entry['data']['basis']['posting']['doc_type'] ?? null)!=='supplier_invoice') { return $pending; } }
        $sources=NorwayPostingAllocation::sources($observations,$ledger);if (!$sources) { return $pending; }
        $h=$sources['header'];$lines=$sources['lines'];$ledger=$sources['ledger'];$rule=$profiles[$profile];
        if ($h->buyer_country!=='NO' || $h->seller_country!=='NO' || $h->multicurrency_code!=='NOK'
            || (float)($h->multicurrency_tx ?? 0)!==1.0) { return $pending; }
        foreach (array('vat_reverse_charge','revenuestamp','localtax1','localtax2') as $key) {
            if (!preg_match('/^0(?:\.0+)?$/D',(string)($h->$key ?? ''))) { return $pending; }
        }
        $gross=NorwayInvoiceReconciliation::evaluate($h,$ledger);$sign=(int)$h->type===2 ? -1 : 1;
        $expectedCosts=array();$byLine=array();$net=0;$vat=0;$deductible=0;
        foreach ($lines as $line) {
            foreach (array('info_bits','localtax1_tx','localtax2_tx','total_localtax1','total_localtax2') as $key) {
                if (!preg_match('/^0(?:\.0+)?$/D',(string)($line->$key ?? ''))) { return $pending; }
            }
            $id=(int)$line->rowid;$account=(string)($line->bound_number ?? '');
            if ($id<=0 || isset($byLine[$id]) || empty($line->fk_code_ventilation) || trim($account)===''
                || in_array($account,array('2400','2710','2711','2713'),true)
                || !preg_match('/^'.$rule['rate'].'(?:\.0+)?$/D',(string)$line->tva_tx)) { return $pending; }
            $n=NorwayInvoiceReconciliation::amount($line->total_ht);$v=NorwayInvoiceReconciliation::amount($line->total_tva);
            if ($n*$sign<0 || $v*$sign<0 || abs($v*100-$n*$rule['rate'])>100000000) { return $pending; }
            if ($basisPoints!==10000 && $v%1000000!==0) { return $pending; }
            $d=$basisPoints===10000 ? $v : self::deductibleVat($v,$basisPoints);$cost=$n+$v-$d;
            $byLine[$id]=array('account'=>$account,'amount'=>$cost);$expectedCosts[$account]=($expectedCosts[$account] ?? 0)+$cost;$net+=$n;$vat+=$v;$deductible+=$d;
        }
        if (!$lines || abs($net-NorwayInvoiceReconciliation::amount($h->total_ht))>1000000 || abs($vat-NorwayInvoiceReconciliation::amount($h->total_tva))>1000000) { return $pending; }
        $expected=array('Supplier'=>-NorwayInvoiceReconciliation::amount($h->total_ttc),'Purchase'=>$net+$vat-$deductible,'Vat'=>$deductible,'Other'=>0);
        $actual=array_fill_keys(array_keys($expected),0);$costs=array();$linked=array();$grouped=false;$different=false;
        foreach ($ledger['rows'] as $row) {
            $number=(string)$row->numero_compte;$value=NorwayInvoiceReconciliation::amount($row->debit)-NorwayInvoiceReconciliation::amount($row->credit);
            $role=$number===$rule['supplier'] ? 'Supplier' : ($number===$rule['vat'] ? 'Vat' : (array_key_exists($number,$expectedCosts) ? 'Purchase' : 'Other'));
            $actual[$role]+=$value;$different=$different || $role==='Other';
            if ($role==='Purchase') {
                $costs[$number]=($costs[$number] ?? 0)+$value;$id=(int)$row->fk_docdet;
                if ($id===0) { $grouped=true;continue; }
                if (!isset($byLine[$id]) || $byLine[$id]['account']!==$number) { $different=true;continue; }
                $linked[$id]=($linked[$id] ?? 0)+$value;
            }
        }
        foreach ($expectedCosts as $number=>$value) { $different=$different || abs(($costs[$number] ?? 0)-$value)>1000000; }
        foreach ($byLine as $id=>$line) {
            $value=$linked[$id] ?? 0;
            if (!$grouped || ($value!==0 && ($value<0)!==($line['amount']<0)) || abs($value)>abs($line['amount'])+1000000) { $different=$different || abs($value-$line['amount'])>1000000; }
        }
        $totals=array();foreach ($expected as $role=>$value) {
            $delta=$actual[$role]-$value;$different=$different || abs($delta)>1000000;
            $totals[]=array('role'=>$role,'expected'=>NorwayInvoiceReconciliation::display($value),'actual'=>NorwayInvoiceReconciliation::display($actual[$role]),'difference'=>NorwayInvoiceReconciliation::display($delta));
        }
        $status=$gross['status']!=='Match' ? ($gross['status']==='Different' ? 'Different' : 'Review') : ($different ? 'Different' : ($grouped ? 'Grouped' : 'Match'));
        return array('profile'=>$profile,'status'=>$status,'source_changed'=>$sources['source_changed'],'totals'=>$totals,'deduction_basis_points'=>$basisPoints,'non_deductible_vat'=>NorwayInvoiceReconciliation::display($vat-$deductible));
    }
}
