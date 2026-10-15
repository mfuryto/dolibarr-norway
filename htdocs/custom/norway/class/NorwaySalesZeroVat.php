<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require_once __DIR__.'/NorwayPostingTaxContext.php';
/** Version 1 read-only consistency control; never approval of a VAT exemption. */
class NorwaySalesZeroVat
{
    private const TYPES = array('5'=>'DomesticExempt', '6'=>'OutsideLaw', '7'=>'NotTurnover',
        '51'=>'DomesticReverse', '52'=>'Export');

    private static function decimal($value)
    {
        return (is_string($value) || is_int($value) || is_float($value))
            && strlen((string)$value)<=64 && preg_match('/^-?[0-9]+(?:\.[0-9]+)?$/D',(string)$value);
    }

    private static function zero($value)
    {
        return self::decimal($value) && preg_match('/^-?0+(?:\.0+)?$/D',(string)$value);
    }

    public static function line($line,$target,$linked)
    {
        $linked=$linked===true && is_string($target);
        $result=array('line'=>(int)($line['rowid'] ?? 0),'target'=>$linked===true ? $target : '',
            'category'=>'Unknown','status'=>'Unresolved');
        foreach (array('tva_tx','total_ht','total_tva') as $field) {
            if (!self::decimal($line[$field] ?? null)) { $result['status']='Invalid'; return $result; }
        }
        if ((float)$line['tva_tx']<0) { $result['status']='Invalid'; return $result; }
        $zero=self::zero($line['tva_tx']);
        $category=$linked===true ? (self::TYPES[$target] ?? null) : null;
        // Zero VAT money on a positive-rate line may be rounding or a zero base.
        if (!$zero && $category===null) { $result['status']='NotApplicable'; return $result; }
        foreach (array('info_bits','localtax1_tx','localtax2_tx') as $field) {
            if (!self::zero($line[$field] ?? null)) { $result['status']='Markers'; return $result; }
        }
        if (!$zero || !self::zero($line['total_tva'])) { $result['status']='Conflict'; return $result; }
        if ($linked!==true) { return $result; }
        if ($category===null) {
            $result['status']=in_array($target,array('3','31','32','33'),true) ? 'Conflict' : 'UnsupportedCode';
            return $result;
        }
        $result['category']=$category;
        $result['status']=in_array($target,array('51','52'),true) ? 'SeparateScenario' : 'Stated';
        return $result;
    }

    private static function covered($lines,$objects=false)
    {
        if (!is_array($lines) || !$lines || count($lines)>200) { return false; }
        $ids=array();
        foreach ($lines as $line) {
            $id=$objects ? (int)($line->id ?? 0) : (int)($line['rowid'] ?? 0);
            if ($id<=0 || isset($ids[$id])) { return false; }$ids[$id]=true;
        }
        return true;
    }

    public static function invoice($invoice,$comparison)
    {
        if (!self::covered($invoice->lines ?? null,true) || (int)($invoice->entity ?? 0)!==(int)$GLOBALS['conf']->entity
            || !in_array((int)($invoice->type ?? -1),array(0,2),true)
            || ($invoice->multicurrency_code ?? '')!=='NOK'
            || count($invoice->lines ?? array())!==count($comparison['rows'] ?? array())) {
            return array('version'=>1,'status'=>'Unsupported','rows'=>array());
        }
        $rows=array();
        foreach ($invoice->lines as $i=>$line) {
            $binding=$comparison['rows'][$i]; $raw=get_object_vars($line); $raw['rowid']=$line->id ?? 0;
            $rows[]=self::line($raw,$binding['rule']['target'] ?? '',($binding['status'] ?? '')==='Candidate');
        }
        return array('version'=>1,'status'=>'Evaluated','rows'=>$rows);
    }

    public static function captured($basis,$entity)
    {
        if (!is_int($entity) || $entity<=0 || $entity!==(int)$GLOBALS['conf']->entity
            || !self::covered($basis['lines'] ?? null) || (int)($basis['posting']['entity'] ?? 0)!==$entity
            || (int)($basis['invoice']['entity'] ?? 0)!==$entity || ($basis['source_complete'] ?? null)!==true
            || ($basis['posting']['doc_type'] ?? '')!=='customer_invoice'
            || !in_array((int)($basis['invoice']['type'] ?? -1),array(0,2),true)
            || ($basis['invoice']['multicurrency_code'] ?? '')!=='NOK') {
            return array('version'=>1,'status'=>'Unsupported','rows'=>array());
        }
        $context=NorwayPostingTaxContext::evaluate($basis,$entity); $bindings=array();
        foreach ($context['lines'] as $binding) { $bindings[$binding['line']]=$binding; }
        $rows=array();
        foreach ($basis['lines'] ?? array() as $line) {
            $binding=$bindings[(int)($line['rowid'] ?? 0)] ?? array();
            $rows[]=self::line($line,$binding['target'] ?? '',($binding['status'] ?? '')==='Linked');
        }
        return array('version'=>1,'status'=>'Evaluated','rows'=>$rows);
    }
}
