<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require_once __DIR__.'/NorwayVatRates.php';
/** Stable tax roles in native ledger metadata; no amounts, vouchers or history copies. */
class NorwayVatLedger
{
    public static function specs()
    {
        $out=array();
        foreach(NorwayVatRates::codes() as $s){$s['account']=($s['type']===1?'270':'271').array(25=>'0',15=>'1',12=>'3')[$s['rate']];$out[$s['code']]=$s;}
        foreach(NorwayVatRates::zeroCodes() as $s){$out[$s['code']]=$s+array('zero'=>true);}
        return $out;
    }
    private static function rows($db,$sql)
    {
        $r=$db->query($sql);if(!$r){throw new RuntimeException('NorwayVatReportDatabase');}$rows=array();while($o=$db->fetch_object($r)){$rows[]=$o;}return $rows;
    }
    public static function role($line)
    {
        $all=json_decode((string)($line->extraparams??''),true);$role=$all['norway_vat']??null;
        if($role===null){return null;}
        if(!is_array($role) || ($role['v']??null)!==1 || !in_array($role['kind']??'',array('tax','counterpart','other'),true) || !is_array($role['codes']??null)){
            throw new RuntimeException('NorwayVatReportChanged');
        }
        foreach($role['codes'] as $code){if(!preg_match('/^(?:1|11|13|3|31|33|86|87)\/[12]$/D',(string)$code)){throw new RuntimeException('NorwayVatReportChanged');}}
        return $role;
    }
    public static function sourceHash($db,$entity,$type,$id)
    {
        $sale=$type==='customer_invoice';$p=$db->prefix();$header=$sale?'facture':'facture_fourn';$detail=$sale?'facturedet':'facture_fourn_det';$fk=$sale?'fk_facture':'fk_facture_fourn';
        $h=self::rows($db,'SELECT type,datef,fk_soc,total_ht,total_tva,total_ttc,multicurrency_code'.($sale?'':',vat_reverse_charge').' FROM '.$p.$header.' WHERE entity='.(int)$entity.' AND rowid='.(int)$id);
        $l=self::rows($db,'SELECT d.rowid,d.vat_src_code,d.tva_tx,d.total_ht,d.'.($sale?'total_tva':'tva').',d.total_ttc,d.info_bits,d.product_type,d.special_code,d.total_localtax1,d.total_localtax2'.($sale?'':',e.norway_vat_deduction').' FROM '.$p.$detail.' d'.($sale?'':' LEFT JOIN '.$p.'facture_fourn_det_extrafields e ON e.fk_object=d.rowid').' WHERE d.'.$fk.'='.(int)$id.' ORDER BY d.rowid');
        return hash('sha256',json_encode(array($h,$l),JSON_THROW_ON_ERROR));
    }
    public static function taxAccounts($db,$entity)
    {
        $out=array();$p=$db->prefix();
        foreach(self::rows($db,'SELECT numero_compte,extraparams FROM '.$p.'accounting_bookkeeping WHERE entity='.(int)$entity." AND extraparams LIKE '%norway_vat%'") as $b){if((self::role($b)['kind']??'')==='tax'){$out[$b->numero_compte]=true;}}
        foreach(self::rows($db,'SELECT DISTINCT b.numero_compte FROM '.$p.'norway_manual_vat v JOIN '.$p.'accounting_bookkeeping b ON b.entity=v.entity AND b.rowid=v.fk_bookkeeping WHERE v.entity='.(int)$entity) as $b){$out[$b->numero_compte]=true;}
        return $out;
    }
    public static function capture($db,$line,$entity)
    {
        if(!in_array($line->doc_type??'',array('customer_invoice','supplier_invoice'),true) || (int)($line->fk_doc??0)<=0){return;}
        $sale=$line->doc_type==='customer_invoice';$p=$db->prefix();$table=$sale?'facturedet':'facture_fourn_det';$fk=$sale?'fk_facture':'fk_facture_fourn';
        $header=$sale?'facture':'facture_fourn';
        $rows=self::rows($db,'SELECT DISTINCT d.vat_src_code,d.tva_tx FROM '.$p.$table.' d JOIN '.$p.$header.' f ON f.rowid=d.'.$fk.' WHERE f.entity='.(int)$entity.' AND f.rowid='.(int)$line->fk_doc);
        $specs=self::specs();$bindings=array();
        foreach($rows as $r){$code=(string)$r->vat_src_code;$s=$specs[$code]??null;if(!$s || $s['type']!==($sale?1:2) || (float)$s['rate']!==(float)$r->tva_tx){return;}
            if(!empty($s['reverse_rate'])){$bindings[$code.'/1']=$s['reverse_credit'];$bindings[$code.'/2']=$s['reverse_debit'];}
            elseif(empty($s['zero'])){
                $field=$sale?'accountancy_code_sell':'accountancy_code_buy';
                $rates=self::rows($db,'SELECT '.$field.' AS account FROM '.$p.'c_tva WHERE entity='.(int)$entity." AND fk_pays=173 AND active=1 AND code='".$db->escape($code)."' AND taux=".(int)$s['rate']);
                if(count($rates)!==1){return;}$account=trim((string)$rates[0]->account);
                $bindings[$code.'/'.($sale?1:2)]=$account!==''?$account:getDolGlobalString($sale?'ACCOUNTING_VAT_SOLD_ACCOUNT':'ACCOUNTING_VAT_BUY_ACCOUNT');
            }
        }
        $codes=array_keys(array_filter($bindings,static fn($a)=>(string)$a===(string)$line->numero_compte));
        $role=array('v'=>1,'kind'=>!empty($line->subledger_account)?'counterpart':($codes?'tax':'other'),'codes'=>$codes);
        if($role['kind']==='counterpart'){$role['source']=self::sourceHash($db,$entity,$line->doc_type,(int)$line->fk_doc);}
        $r=self::rows($db,'SELECT extraparams FROM '.$p.'accounting_bookkeeping WHERE entity='.(int)$entity.' AND rowid='.(int)$line->id);
        if(count($r)!==1){throw new RuntimeException('NorwayVatReportDatabase');}
        $raw=(string)$r[0]->extraparams;$all=$raw===''?array():json_decode($raw,true);
        if(!is_array($all) || isset($all['norway_vat'])){throw new RuntimeException('NorwayVatReportChanged');}
        $all['norway_vat']=$role;$value=json_encode($all,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
        if(strlen($value)>255 || !$db->query('UPDATE '.$p."accounting_bookkeeping SET extraparams='".$db->escape($value)."' WHERE entity=".(int)$entity.' AND rowid='.(int)$line->id)){
            throw new RuntimeException('NorwayVatReportDatabase');
        }
    }
    /** Native durable roles first. Older rows use explicit rate/code labels or Norwegian accounts.
     * No historical row is backfilled or rewritten with today's configuration.
     */
    public static function bindings($posted,$specs,$db=null,$entity=0)
    {
        $bindings=array();$counterparts=array();$known=array();
        foreach($posted as $b){$role=self::role($b);
            if(isset($role['source']) && (!$db || !is_string($role['source']) || !hash_equals($role['source'],self::sourceHash($db,$entity,$b->doc_type,(int)$b->fk_doc)))){throw new RuntimeException('NorwayVatReportMismatch');}
            if(($role['kind']??'')==='counterpart' || ($role===null && trim((string)$b->subledger_account)!=='')){$counterparts[(string)$b->numero_compte]=true;}
            if($role!==null){foreach($role['codes'] as $key){$bindings[$key][(string)$b->numero_compte]=true;}$known[(string)$b->numero_compte]=true;continue;}
            preg_match_all('/(?:25|15|12)(?:[.,]0+)?\s*\((1|11|13|3|31|33)\)/u',(string)$b->label_operation,$matches);
            foreach($matches[1] as $code){$s=$specs[$code]??null;if($s){$bindings[$code.'/'.$s['type']][(string)$b->numero_compte]=true;}}
        }
        foreach($specs as $code=>$s){
            $fallback=!empty($s['reverse_rate'])?array($code.'/1'=>$s['reverse_credit'],$code.'/2'=>$s['reverse_debit']):(empty($s['zero'])?array($code.'/'.$s['type']=>$s['account']):array());
            foreach($fallback as $key=>$account){if(isset($bindings[$key])){continue;}foreach($posted as $b){if(!isset($known[(string)$b->numero_compte]) && (string)$b->numero_compte===$account){$bindings[$key][$account]=true;}}}
        }
        $accounts=array();foreach($bindings as $key=>$values){if(count($values)!==1){throw new RuntimeException('NorwayVatReportMismatch');}$accounts[$key]=(string)array_key_first($values);}
        if(count($counterparts)!==1){throw new RuntimeException('NorwayVatReportMismatch');}
        return array('accounts'=>$accounts,'counterpart'=>(string)array_key_first($counterparts));
    }
}
