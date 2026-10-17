<?php
// SPDX-License-Identifier: GPL-3.0-or-later
/** Invoice-line deduction, projected into the pinned core purchase journal. No ledger writes. */
class NorwayPurchaseVat
{
    public static function percent($value,$code)
    {
        if($value===null || $value===''){return $code==='87'?0:10000;}
        if(!preg_match('/^(?:[0-9]{1,3})(?:\.[0-9]{1,2})?$/D',(string)$value) || (float)$value>100){throw new RuntimeException('NorwayPurchaseVatInvalid');}
        $parts=explode('.',(string)$value);$percent=(int)$parts[0]*100+(int)str_pad($parts[1]??'',2,'0');
        if($code==='87' && $percent!==0){throw new RuntimeException('NorwayPurchaseVatInvalid');}return $percent;
    }
    /** Exact eight-decimal source amount, rounded once to cents; symmetric for credits. */
    public static function deductionAmount($amount,$percent)
    {
        $share=intdiv($amount,10000)*$percent+intdiv(($amount%10000)*$percent,10000);
        return ($share<0?-1:1)*(intdiv(abs($share),1000000)+(abs($share)%1000000>=500000?1:0))*1000000;
    }
    private static function rows($db,$sql)
    {
        $r=$db->query($sql);if(!$r){throw new RuntimeException('NorwayPurchaseVatInvalid');}
        $out=array();while($row=$db->fetch_object($r)){$out[]=$row;}return $out;
    }
    public static function lines($db,$id,$entity)
    {
        return self::rows($db,'SELECT d.*,e.norway_vat_deduction,f.entity,f.fk_statut,f.vat_reverse_charge,f.multicurrency_code,c.code AS supplier_country FROM '.$db->prefix().'facture_fourn_det d'
            .' JOIN '.$db->prefix().'facture_fourn f ON f.rowid=d.fk_facture_fourn'
            .' LEFT JOIN '.$db->prefix().'facture_fourn_det_extrafields e ON e.fk_object=d.rowid'
            .' LEFT JOIN '.$db->prefix().'societe s ON s.rowid=f.fk_soc LEFT JOIN '.$db->prefix().'c_country c ON c.rowid=s.fk_pays'
            .' WHERE f.entity='.(int)$entity.($id>0?' AND f.rowid='.(int)$id:' AND f.fk_statut>0'));
    }
    public static function check($line)
    {
        global $conf,$mysoc;
        $explicit=$line->norway_vat_deduction!==null && $line->norway_vat_deduction!=='';
        $reverse=in_array((string)$line->vat_src_code,array('86','87'),true);
        if(!$explicit && !$reverse){return;}
        $percent=self::percent($line->norway_vat_deduction,$line->vat_src_code);
        $rates=array('1'=>25,'11'=>15,'13'=>12,'NO0'=>0,'86'=>0,'87'=>0);
        if(($mysoc->country_code??'')!=='NO' || $conf->currency!=='NOK' || !in_array((string)$line->multicurrency_code,array('','NOK'),true)
            || !isset($rates[$line->vat_src_code]) || (float)$line->tva_tx!==(float)$rates[$line->vat_src_code]
            || (int)$line->info_bits!==0 || (int)$line->special_code!==0 || !in_array((int)$line->product_type,array(0,1),true)
            || (float)$line->total_localtax1!=0 || (float)$line->total_localtax2!=0 || getDolGlobalInt('ACCOUNTING_PURCHASES_DO_NOT_DISPATCH_TAXES')){
            throw new RuntimeException('NorwayPurchaseVatInvalid');
        }
        if($reverse){
            if(empty($mysoc->tva_assuj) || (int)$line->vat_reverse_charge!==1 || $line->supplier_country==='NO' || !$line->supplier_country
                || (int)$line->product_type!==1 || (float)$line->tva!=0){throw new RuntimeException('NorwayPurchaseVatInvalid');}
        }elseif($line->supplier_country!=='NO' || (int)$line->vat_reverse_charge===1 || (empty($mysoc->tva_assuj) && $percent>0 && (float)$line->tva!=0)){
            throw new RuntimeException('NorwayPurchaseVatInvalid');
        }
    }
    public static function guard($db,$id,$entity,$editing=false)
    {
        foreach(self::lines($db,$id,$entity) as $line){
            self::check($line);
            if($editing && (int)$line->fk_statut!==0){throw new RuntimeException('NorwayPurchaseVatInvalid');}
        }
    }
    /** Documented SELECT/FROM hooks: later aliases are used by MariaDB fetch_object.
     * Only journal inputs change; the core still previews, exports and posts its own voucher.
     * The original amount/rate/code and core payment liability are never rewritten.
     */
    private static function foreignSql()
    {
        return "(f.vat_reverse_charge=1 AND fd.vat_src_code IN ('86','87') AND fd.tva_tx=0 AND fd.product_type=1 AND co.code<>'NO')";
    }
    public static function selectSql()
    {
        $foreign=self::foreignSql();$output="($foreign AND nvp.part=1)";
        $domestic="(co.code='NO' AND fd.vat_src_code IN ('1','11','13') AND nv.norway_vat_deduction IS NOT NULL)";
        $percent="COALESCE(nv.norway_vat_deduction,CASE WHEN fd.vat_src_code='87' THEN 0 ELSE 100 END)";
        $tax='CAST(fd.tva AS DECIMAL(24,8))';$base='CAST(fd.total_ttc AS DECIMAL(24,8))';
        $reverse='ROUND('.$base.'*0.25,2)';$recoverable="ROUND($reverse*$percent/100,2)";
        $lost="CASE WHEN $foreign THEN $reverse-$recoverable WHEN $domestic THEN $tax-ROUND($tax*$percent/100,2) ELSE 0 END";
        // Core chooses ordinary VAT OR reverse VAT for a whole invoice. Feed the reverse liability
        // as a second journal input on 2704; core groups both inputs into the same native voucher.
        return ", CASE WHEN $output THEN -$reverse ELSE fd.total_ht+($lost) END AS total_ht"
            .", CASE WHEN $output THEN 0 WHEN $foreign THEN $recoverable ELSE fd.tva-($lost) END AS total_tva"
            .", CASE WHEN $output THEN 0 ELSE fd.total_ttc END AS total_ttc"
            .", CASE WHEN $foreign THEN 0 ELSE f.vat_reverse_charge END AS vat_reverse_charge"
            .", CASE WHEN $output THEN '2704' ELSE aa.account_number END AS compte";
    }
    public static function fromSql($db)
    {
        return ' LEFT JOIN '.$db->prefix().'facture_fourn_det_extrafields nv ON nv.fk_object=fd.rowid'
            .' JOIN (SELECT 0 AS part UNION ALL SELECT 1 AS part) nvp ON (nvp.part=0 OR '.self::foreignSql().')';
    }
    public static function completeAccounts($db,$entity)
    {
        // Fill only missing native reverse-charge mappings, never change an explicit user value.
        foreach(array('accountancy_code_buy'=>'2714') as $column=>$account){
            $sql='UPDATE '.$db->prefix()."c_tva SET $column='$account' WHERE entity=".(int)$entity." AND fk_pays=173 AND code IN ('86','87') AND type_vat=2 AND taux=0 AND ($column IS NULL OR $column='')";
            if(!$db->query($sql)){throw new RuntimeException('NorwayPurchaseVatInvalid');}
        }
    }
    public static function guardJournal($db,$entity,$id)
    {
        self::guard($db,$id,$entity);
        $lines=self::lines($db,$id,$entity);
        foreach($lines as $line){if(!in_array((string)$line->vat_src_code,array('86','87'),true)){continue;}
            $rows=self::rows($db,'SELECT * FROM '.$db->prefix().'c_tva WHERE entity='.(int)$entity." AND fk_pays=173 AND code='".$line->vat_src_code."'");
            $r=$rows[0]??null;
            if(count($rows)!==1 || !$r || !(int)$r->active || (float)$r->taux!=0 || (int)$r->type_vat!==2 || $r->accountancy_code_buy!=='2714'
               ){
                throw new RuntimeException('NorwayPurchaseVatInvalid');
            }
        }
    }
}
