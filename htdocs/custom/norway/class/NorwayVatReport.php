<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require_once __DIR__.'/NorwayManualVat.php';
require_once __DIR__.'/NorwayPurchaseVat.php';
/** Read-only supported-code extract, reconciled to native vouchers, never a complete VAT return. */
class NorwayVatReport
{
    public static function allowed($user)
    {
        return NorwayManualVat::allowed($user) && $user->hasRight('accounting','comptarapport','lire');
    }
    private static function rows($db,$sql)
    {
        $r=$db->query($sql);if(!$r){throw new RuntimeException('NorwayVatReportDatabase');}
        $rows=array();while($row=$db->fetch_object($r)){$rows[]=$row;}return $rows;
    }
    /** Exact fixed-point arithmetic over the eight stored decimals; reject overflow. */
    public static function amount($value)
    {
        if(!preg_match('/^(-?)([0-9]{1,10})(?:\.([0-9]{1,8}))?$/D',(string)$value,$m)){throw new RuntimeException('NorwayVatReportAmount');}
        return ($m[1]==='-'?-1:1)*((int)$m[2]*100000000+(int)str_pad($m[3]??'',8,'0'));
    }
    private static function add($a,$b)
    {
        $sum=$a+$b;if(!is_int($sum) || $sum===PHP_INT_MIN){throw new RuntimeException('NorwayVatReportAmount');}return $sum;
    }
    public static function cents($amount)
    {
        return ($amount<0?-1:1)*(intdiv(abs($amount),1000000)+(abs($amount)%1000000>=500000?1:0));
    }
    public static function money($cents)
    {
        return ($cents<0?'-':'').intdiv(abs($cents),100).'.'.str_pad((string)(abs($cents)%100),2,'0',STR_PAD_LEFT);
    }
    private static function reverseTax($base)
    {
        return self::cents(intdiv($base,100)*25+intdiv(($base%100)*25,100))*1000000;
    }
    private static function period($start,$end)
    {
        foreach(array($start,$end) as $date){
            if(!preg_match('/^(20[0-9]{2})-([0-9]{2})-([0-9]{2})$/D',$date,$m) || !checkdate((int)$m[2],(int)$m[3],(int)$m[1])){throw new RuntimeException('NorwayVatReportPeriod');}
        }
        if($end<$start || substr($start,0,4)!==substr($end,0,4)){throw new RuntimeException('NorwayVatReportPeriod');}
    }
    private static function issue(&$report,$reason,$source,$id,$piece=0)
    {
        $key=$reason.'/'.$source.'/'.$id.'/'.$piece;
        $report['issues'][$key]=array('reason'=>$reason,'source'=>$source,'id'=>(int)$id,'piece'=>(int)$piece);
    }
    private static function entry(&$report,$source,$id,$piece,$code,$base,$tax,$spec)
    {
        $report['entries'][]=array('source'=>$source,'id'=>(int)$id,'piece'=>(int)$piece,'code'=>(string)($spec['report_code']??$code),
            'type'=>$spec['type'],'rate'=>$spec['rate'],'base'=>($spec['type']===1 || ($spec['reported']??true)===false)?self::cents($base):null,'tax'=>self::cents($tax),'reported'=>$spec['reported']??true);
    }
    public static function build($db,$entity,$start,$end,$user)
    {
        global $conf,$mysoc;
        if((int)$entity<=0 || (int)$entity!==(int)$conf->entity || !self::allowed($user)){throw new RuntimeException('NorwayVatReportAccess');}
        self::period($start,$end);
        // The pinned database's repeatable-read transaction provides one view across ledger and source reads.
        $isolation=self::rows($db,'SELECT @@tx_isolation AS level');
        if(($isolation[0]->level??'')!=='REPEATABLE-READ'){throw new RuntimeException('NorwayVatReportDatabase');}
        $db->begin();
        try {
            $report=self::read($db,(int)$entity,$start,$end);
            if($db->commit()<=0){throw new RuntimeException('NorwayVatReportDatabase');}return $report;
        } catch(Throwable $error){$db->rollback();throw $error;}
    }
    private static function read($db,$entity,$start,$end)
    {
        global $conf,$mysoc;
        $p=$db->prefix();$report=array('start'=>$start,'end'=>$end,'groups'=>array(),'entries'=>array(),'issues'=>array(),'other_lines'=>0,'net'=>null);
        if($conf->currency!=='NOK' || ($mysoc->country_code??'')!=='NO'){self::issue($report,'Setup','',0);}
        $manualChoices=NorwayManualVat::choices($db,$entity);
        $choices=$manualChoices;unset($choices['86'],$choices['87']); // Manual deduction metadata must never substitute an inactive invoice rate.
        $choices=NorwayVatRates::zeroChoices($db,$entity)+$choices;$specs=array();foreach(NorwayVatRates::codes() as $spec){$specs[$spec['code']]=$spec;}
        foreach($manualChoices as $code=>$spec){if(!empty($spec['manual_only'])){$specs[$code]=$spec;}}
        $watch=array();foreach(self::rows($db,'SELECT accountancy_code_sell,accountancy_code_buy FROM '.$p.'c_tva WHERE entity='.$entity.' AND taux<>0') as $row){
            foreach(array($row->accountancy_code_sell,$row->accountancy_code_buy) as $account){if(trim((string)$account)!==''){$watch[$account]=true;}}
        }
        foreach(array('ACCOUNTING_VAT_SOLD_ACCOUNT','ACCOUNTING_VAT_BUY_ACCOUNT') as $name){$account=getDolGlobalString($name);if($account!==''){$watch[$account]=true;}}
        $ledger=self::rows($db,'SELECT b.*,v.tax_code,v.tax_base,v.source_hash FROM '.$p.'accounting_bookkeeping b LEFT JOIN '.$p.'norway_manual_vat v ON v.entity=b.entity AND v.fk_bookkeeping=b.rowid WHERE b.entity='.$entity." AND b.doc_date>='".$start." 00:00:00' AND b.doc_date<='".$end." 23:59:59' ORDER BY b.rowid");
        $documents=array();$balances=array();
        foreach($ledger as $line){
            $debit=self::amount($line->debit);$credit=self::amount($line->credit);
            $piece=(int)$line->piece_num;$balances[$piece]=self::add($balances[$piece]??0,$debit-$credit);
            $taxAccount=isset($watch[$line->numero_compte]) || preg_match('/^27[01][0-9]$/D',$line->numero_compte)===1;
            if(in_array($line->doc_type,array('customer_invoice','supplier_invoice'),true) && (int)$line->fk_doc>0){
                $documents[$line->doc_type][(int)$line->fk_doc][]=$line;continue;
            }
            if(!$taxAccount && empty($line->tax_code)){$report['other_lines']++;continue;}
            $spec=$specs[$line->tax_code??'']??null;
            if(!in_array((string)$line->doc_type,array('','manual'),true) || (int)$line->fk_doc!==0 || (int)$line->fk_docdet!==0){self::issue($report,'Unsupported','ledger',(int)$line->rowid,$piece);continue;}
            if(!$spec || (!empty($spec['manual_only']) && $line->numero_compte!==$spec['account'])){self::issue($report,'MissingCode','ledger',(int)$line->rowid,$piece);continue;}
            if(!NorwayManualVat::current($line)){self::issue($report,'Changed','ledger',(int)$line->rowid,$piece);continue;}
            if(($debit!==0 && $credit!==0) || ($debit===0 && $credit===0)){self::issue($report,'Mismatch','ledger',(int)$line->rowid,$piece);continue;}
            $tax=$spec['type']===1?$credit-$debit:$debit-$credit;$base=self::amount($line->tax_base);
            if(($spec['type']===2 && $base!==0) || ($spec['type']===1 && ($base*$tax<=0 || abs(round(self::cents($base)*$spec['rate']/100)-self::cents($tax))>1))){self::issue($report,'Mismatch','ledger',(int)$line->rowid,$piece);continue;}
            self::entry($report,'ledger',(int)$line->rowid,$piece,$line->tax_code,$base,$tax,$spec);
        }
        foreach($balances as $piece=>$balance){if(self::cents($balance)!==0){self::issue($report,'Balance','ledger',0,$piece);}}
        foreach(array('customer_invoice','supplier_invoice') as $type){
            $sale=$type==='customer_invoice';$header=$sale?'facture':'facture_fourn';$lines=$sale?'facturedet':'facture_fourn_det';$fk=$sale?'fk_facture':'fk_facture_fourn';
            $docs=$documents[$type]??array();
            // Validated source documents in the period cannot silently disappear just because transfer is missing.
            foreach(self::rows($db,'SELECT rowid FROM '.$p.$header.' WHERE entity='.$entity." AND fk_statut>0 AND datef>='".$start."' AND datef<='".$end." 23:59:59'") as $pending){
                if(!isset($docs[(int)$pending->rowid])){self::issue($report,'NotPosted',$type,(int)$pending->rowid);}
            }
            foreach($docs as $id=>$posted){
                $piece=(int)$posted[0]->piece_num;$before=count($report['issues']);
                $headers=self::rows($db,'SELECT f.*,s.accountancy_code_customer_general,s.accountancy_code_supplier_general,c.code AS country_code FROM '.$p.$header.' f LEFT JOIN '.$p.'societe s ON s.rowid=f.fk_soc AND s.entity='.$entity.' LEFT JOIN '.$p.'c_country c ON c.rowid=s.fk_pays WHERE f.entity='.$entity.' AND f.rowid='.$id);
                $h=$headers[0]??null;
                if(!$h || (int)$h->fk_statut<=0 || !in_array((int)$h->type,array(0,2),true) || !preg_match('/^[A-Z]{2}$/D',(string)$h->country_code) || (!$sale && $h->country_code!=='NO' && (int)($h->vat_reverse_charge??0)!==1)
                    || !in_array((string)$h->multicurrency_code,array('','NOK'),true)) {self::issue($report,'Unsupported',$type,$id,$piece);continue;}
                $all=self::rows($db,'SELECT * FROM '.$p.'accounting_bookkeeping WHERE entity='.$entity." AND doc_type='".$type."' AND fk_doc=".$id);
                if(count($all)!==count($posted)){self::issue($report,'Date',$type,$id,$piece);}
                foreach($posted as $b){if(substr($b->doc_date,0,10)!==substr($h->datef,0,10) || (int)$b->piece_num!==$piece || (int)$b->fk_docdet!==0){self::issue($report,'Date',$type,$id,$piece);}}
                $groups=array();$expected=array();$sumBase=0;$sumTax=0;
                $lineSql=$sale?'SELECT * FROM '.$p.$lines.' WHERE '.$fk.'='.$id.' ORDER BY rowid':'SELECT d.*,e.norway_vat_deduction FROM '.$p.$lines.' d LEFT JOIN '.$p.'facture_fourn_det_extrafields e ON e.fk_object=d.rowid WHERE d.'.$fk.'='.$id.' ORDER BY d.rowid';
                foreach(self::rows($db,$lineSql) as $line){
                    $code=(string)$line->vat_src_code;$spec=$choices[$code]??null;
                    if($spec && (!empty($spec['manual_only']) || ((!empty($spec['export']) || !empty($spec['reverse_rate']))!==($h->country_code!=='NO')
                        || (!empty($spec['reverse_rate']) && ($sale || empty($mysoc->tva_assuj) || (int)($h->vat_reverse_charge??0)!==1 || (int)$line->product_type!==1))))){self::issue($report,'Unsupported',$type,$id,$piece);continue;}
                    if(!$spec || $spec['type']!==($sale?1:2) || (float)$line->tva_tx!==(float)$spec['rate'] || (int)$line->info_bits!==0
                        || !in_array((int)$line->product_type,array(0,1),true) || (int)$line->special_code!==0
                        || self::amount($line->total_localtax1??'0')!==0 || self::amount($line->total_localtax2??'0')!==0){self::issue($report,'Code',$type,$id,$piece);continue;}
                    $base=self::amount($line->total_ht);$tax=self::amount($sale?$line->total_tva:$line->tva);
                    if((!empty($spec['zero']) && $tax!==0) || abs(round(self::cents($base)*$spec['rate']/100)-self::cents($tax))>1 || ($tax!==0 && ($base===0 || (($base<0)!==($tax<0))))){self::issue($report,'Mismatch',$type,$id,$piece);}
                    $sumBase=self::add($sumBase,$base);$sumTax=self::add($sumTax,$tax);
                    $sourceCode=$code;$deducted=$tax;
                    if(!$sale){
                        try{$percent=NorwayPurchaseVat::percent($line->norway_vat_deduction,$code);}
                        catch(Throwable $error){self::issue($report,'Code',$type,$id,$piece);continue;}
                        if(empty($spec['zero']) && $percent!==10000){$deducted=NorwayPurchaseVat::deductionAmount($tax,$percent);}
                        if(empty($spec['zero']) && $percent===0){$code='NO0';$spec=$choices['NO0']??null;if(!$spec){self::issue($report,'Code',$type,$id,$piece);continue;}}
                        if(!empty($spec['reverse_rate']) && $percent===0){$code='87';$spec=$choices['87']??null;if(!$spec){self::issue($report,'Code',$type,$id,$piece);continue;}}
                    }
                    $groups[$code]['base']=self::add($groups[$code]['base']??0,(!$sale && $sourceCode!==$code && $code==='NO0')?self::add($base,$tax):$base);$groups[$code]['tax']=self::add($groups[$code]['tax']??0,$sale?$tax:$deducted);
                    if(!empty($spec['reverse_rate'])){
                        $reverse=self::reverseTax($base);$groups[$code]['reverse_tax']=self::add($groups[$code]['reverse_tax']??0,$reverse);
                        $expected[$spec['reverse_credit']]=self::add($expected[$spec['reverse_credit']]??0,-$reverse);
                        $recoverable=NorwayPurchaseVat::deductionAmount($reverse,$percent);
                        $groups[$code]['reverse_deduction']=self::add($groups[$code]['reverse_deduction']??0,$recoverable);
                        $expected[$spec['reverse_debit']]=self::add($expected[$spec['reverse_debit']]??0,$recoverable);
                    }
                    if(empty($spec['zero'])){$expected[$spec['account']]=self::add($expected[$spec['account']]??0,$sale?$tax:$deducted);}
                }
                if(!$groups || self::cents($sumBase)!==self::cents(self::amount($h->total_ht)) || self::cents($sumTax)!==self::cents(self::amount($h->total_tva))){self::issue($report,'Mismatch',$type,$id,$piece);}
                $counterpart=$sale?$h->accountancy_code_customer_general:$h->accountancy_code_supplier_general;
                if(!$counterpart || $counterpart==='-1'){$counterpart=getDolGlobalString($sale?'ACCOUNTING_ACCOUNT_CUSTOMER':'ACCOUNTING_ACCOUNT_SUPPLIER');}
                $gross=0;
                $actual=array();foreach($posted as $b){
                    if($b->numero_compte===$counterpart){$gross=self::add($gross,$sale?self::amount($b->debit)-self::amount($b->credit):self::amount($b->credit)-self::amount($b->debit));}
                    if(isset($expected[$b->numero_compte]) || isset($watch[$b->numero_compte]) || preg_match('/^27[01][0-9]$/D',$b->numero_compte)){
                        $amount=self::amount($b->credit)-self::amount($b->debit);if(!$sale){$amount=-$amount;}
                        $actual[$b->numero_compte]=self::add($actual[$b->numero_compte]??0,$amount);
                    }
                }
                if(!$counterpart || isset($expected[$counterpart]) || self::cents($gross)!==self::cents(self::amount($h->total_ttc))
                    || self::cents(self::add($sumBase,$sumTax))!==self::cents($gross)){self::issue($report,'Mismatch',$type,$id,$piece);}
                foreach(array_unique(array_merge(array_keys($expected),array_keys($actual))) as $account){
                    if(self::cents($expected[$account]??0)!==self::cents($actual[$account]??0)){self::issue($report,'Mismatch',$type,$id,$piece);}
                }
                // Rounding across codes sharing an account must still equal the actual booked amount.
                $allocated=array();foreach($groups as $code=>$g){if(!empty($choices[$code]['zero'])){continue;}$account=$choices[$code]['account'];$allocated[$account]=self::add($allocated[$account]??0,self::cents($g['tax']));}
                foreach($allocated as $account=>$amount){if($amount!==self::cents($actual[$account]??0)){self::issue($report,'Mismatch',$type,$id,$piece);}}
                if(count($report['issues'])!==$before){continue;}
                foreach($groups as $code=>$g){$spec=$choices[$code];
                    if(!empty($spec['reverse_rate'])){
                        self::entry($report,$type,$id,$piece,$code,$g['base'],$g['reverse_tax'],array_replace($spec,array('type'=>1,'rate'=>$spec['reverse_rate'])));
                        if((string)$code!=='87'){self::entry($report,$type,$id,$piece,$code,0,$g['reverse_deduction'],array_replace($spec,array('type'=>2,'rate'=>$spec['reverse_rate'])));}
                    }else{self::entry($report,$type,$id,$piece,$code,$g['base'],$g['tax'],$spec);}
                }
            }
        }
        foreach($report['entries'] as $entry){$code=$entry['code'];$key=in_array($code,array('86'),true)?$code.'/'.$entry['type']:$code;if(!isset($report['groups'][$key])){$report['groups'][$key]=array('code'=>$code,'type'=>$entry['type'],'rate'=>$entry['rate'],'base'=>$entry['base']===null?null:0,'tax'=>0,'reported'=>$entry['reported']);}
            $report['groups'][$key]['tax']=self::add($report['groups'][$key]['tax'],$entry['tax']);
            if($entry['base']!==null){$report['groups'][$key]['base']=self::add($report['groups'][$key]['base'],$entry['base']);}
        }
        ksort($report['groups'],SORT_NUMERIC);
        if(!$report['issues']){$report['net']=0;foreach($report['groups'] as $group){if(!$group['reported']){continue;}$report['net']=self::add($report['net'],$group['type']===1?$group['tax']:-$group['tax']);}}
        return $report;
    }
    public static function csv($report)
    {
        if($report['issues']){throw new RuntimeException('NorwayVatReportIncomplete');}
        $out=fopen('php://temp','w+');
        fputcsv($out,array('period_start','period_end','source_type','source_id','piece_num','vat_code','direction','rate','output_base_nok','vat_nok','vat_return','excluded_base_nok'),';','"','');
        foreach($report['entries'] as $e){fputcsv($out,array($report['start'],$report['end'],$e['source'],$e['id'],$e['piece'],$e['code'],$e['type']===1?'output':'input',$e['type']===1 && !in_array($e['code'],array('6','7'),true)?$e['rate']:'',$e['base']===null || !$e['reported']?'':self::money($e['base']),self::money($e['tax']),$e['reported']?'included':'excluded',$e['reported'] || $e['base']===null?'':self::money($e['base'])),';','"','');}
        rewind($out);$data=stream_get_contents($out);fclose($out);return $data;
    }
}
