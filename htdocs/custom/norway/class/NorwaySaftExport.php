<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require_once __DIR__.'/NorwaySaftLedger.php';
require_once __DIR__.'/NorwaySaftMapping.php';
require_once __DIR__.'/NorwayReadiness.php';
/** One coherent native-ledger export. No export flags, source mutations or stored copies. */
class NorwaySaftExport
{
    const NS='urn:StandardAuditFile-Taxation-Financial:NO';
    private $db;
    private $entity;
    private $doc;
    private $taxes=array();
    private $lineTax=array();
    private $lineParty=array();
    private $parties=array();
    private $sources=array();
    private $aliases=array();
    private $partyRows=array();
    private function rows($sql){return NorwaySaftMapping::rows($this->db,$sql);}
    private function p(){return $this->db->prefix();}
    private function node($parent,$name,$value=null)
    {
        $n=$this->doc->createElementNS(self::NS,$name);$parent->appendChild($n);
        if($value!==null){$value=(string)$value;if(!mb_check_encoding($value,'UTF-8') || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/',$value)){throw new RuntimeException('NorwaySaftSource');}$n->appendChild($this->doc->createTextNode($value));}return $n;
    }
    private static function cents($v)
    {
        $amount=NorwayVatReport::amount($v);$cents=NorwayVatReport::cents($amount);
        if($amount!==$cents*1000000){throw new RuntimeException('NorwaySaftPrecision');}return $cents;
    }
    private function money($parent,$name,$cents){$n=$this->node($parent,$name);$this->node($n,'Amount',NorwayVatReport::money($cents));}
    private function balances($node,$opening,$closing)
    {
        $this->node($node,$opening<0?'OpeningCreditBalance':'OpeningDebitBalance',NorwayVatReport::money(abs($opening)));
        $this->node($node,$closing<0?'ClosingCreditBalance':'ClosingDebitBalance',NorwayVatReport::money(abs($closing)));
    }
    private function address($parent,$party)
    {
        if(!empty($party->address) || !empty($party->zip) || !empty($party->town) || !empty($party->country_code)){
            $a=$this->node($parent,'Address');
            foreach(array('StreetName'=>'address','City'=>'town','PostalCode'=>'zip','Country'=>'country_code') as $tag=>$field){if(trim((string)($party->$field??''))!==''){$this->node($a,$tag,$party->$field);}}
        }
    }
    private function company($node,$party,$header=false,$user=null)
    {
        $org=preg_replace('/[ \t]/','',(string)($party->idprof1??$party->siren??''));
        if($header && !NorwayReadiness::validOrganisationNumber($org)){throw new RuntimeException('NorwaySaftCompany');}
        if($org!==''){$this->node($node,'RegistrationNumber',$org);}
        $name=trim((string)($party->name??$party->nom??''));if($name===''){throw new RuntimeException('NorwaySaftCompany');}$this->node($node,'Name',$name);
        $this->address($node,$party);
        if($header){
            if(!$user || trim((string)$user->firstname)==='' || trim((string)$user->lastname)===''){throw new RuntimeException('NorwaySaftContact');}
            $c=$this->node($node,'Contact');$person=$this->node($c,'ContactPerson');$this->node($person,'FirstName',$user->firstname);$this->node($person,'LastName',$user->lastname);
            foreach(array('Telephone'=>'phone','Email'=>'email','Website'=>'url') as $tag=>$field){if(!empty($party->$field)){$this->node($c,$tag,$party->$field);}}
        }elseif(!empty($party->phone) || !empty($party->email) || !empty($party->url)){
            $c=$this->node($node,'Contact');$person=$this->node($c,'ContactPerson');$this->node($person,'FirstName','NotUsed');$this->node($person,'LastName',$name);
            foreach(array('Telephone'=>'phone','Email'=>'email','Website'=>'url') as $tag=>$field){if(!empty($party->$field)){$this->node($c,$tag,$party->$field);}}
        }
        $vat=trim((string)($party->tva_intra??''));if($vat!==''){$t=$this->node($node,'TaxRegistration');$this->node($t,'TaxRegistrationNumber',$vat);}
    }
    private function source($type,$id)
    {
        $key=$type.'/'.$id;if(isset($this->sources[$key])){return $this->sources[$key];}
        $sale=$type==='customer_invoice';$r=$this->rows('SELECT * FROM '.$this->p().($sale?'facture':'facture_fourn').' WHERE entity='.$this->entity.' AND rowid='.(int)$id);
        if(count($r)!==1 || !in_array((int)$r[0]->type,array(0,2),true) || !in_array((string)$r[0]->multicurrency_code,array('','NOK'),true)){throw new RuntimeException('NorwaySaftSource');}
        return $this->sources[$key]=$r[0];
    }
    private function alias($role,$account,$code,$id)
    {
        if($code===''){return;}$key=$role.'/'.$account.'/'.$code;$this->aliases[$key][$id]=true;
    }
    private function prepareParties($s)
    {
        $p=$this->p();
        foreach($this->rows('SELECT s.*,c.code AS country_code FROM '.$p.'societe s LEFT JOIN '.$p.'c_country c ON c.rowid=s.fk_pays WHERE s.entity='.$this->entity) as $party){$this->partyRows[(int)$party->rowid]=$party;}
        // Source-linked historic subledger codes survive a changed customer/supplier code.
        foreach($this->rows('SELECT * FROM '.$p.'accounting_bookkeeping WHERE entity='.$this->entity." AND doc_type IN ('customer_invoice','supplier_invoice') AND subledger_account IS NOT NULL AND subledger_account<>'' AND doc_date<='".$this->db->escape($s['end'])." 23:59:59'") as $oldRow){$r=(array)$oldRow;if(in_array($r['doc_type'],array('customer_invoice','supplier_invoice'),true)){
            $h=$this->source($r['doc_type'],(int)$r['fk_doc']);$role=$r['doc_type']==='customer_invoice'?'C':'S';
            if(trim((string)$r['subledger_account'])!==''){$this->alias($role,(string)$r['numero_compte'],(string)$r['subledger_account'],(int)$h->fk_soc);}
        }}
        foreach($this->partyRows as $id=>$party){foreach(array('C','S') as $role){
            $field=$role==='C'?'code_compta':'code_compta_fournisseur';$account=$role==='C'?'accountancy_code_customer_general':'accountancy_code_supplier_general';
            $general=trim((string)$party->$account);if($general==='' || $general==='-1'){$general=getDolGlobalString($role==='C'?'ACCOUNTING_ACCOUNT_CUSTOMER':'ACCOUNTING_ACCOUNT_SUPPLIER');}
            $this->alias($role,$general,trim((string)$party->$field),$id);
        }}
        foreach($s['history'] as $r){
            $code=trim((string)$r['subledger_account']);if($code===''){
                if(in_array((string)$r['numero_compte'],array(getDolGlobalString('ACCOUNTING_ACCOUNT_CUSTOMER'),getDolGlobalString('ACCOUNTING_ACCOUNT_SUPPLIER')),true)){throw new RuntimeException('NorwaySaftParty:'.$r['rowid']);}
                continue;
            }
            $candidates=array();foreach(array('C','S') as $role){foreach(array_keys($this->aliases[$role.'/'.$r['numero_compte'].'/'.$code]??array()) as $id){$candidates[$role.$id]=array($role,$id);}}
            if(in_array($r['doc_type'],array('customer_invoice','supplier_invoice'),true)){
                $h=$this->source($r['doc_type'],(int)$r['fk_doc']);$role=$r['doc_type']==='customer_invoice'?'C':'S';$candidates=array($role.$h->fk_soc=>array($role,(int)$h->fk_soc));
            }
            if($r['doc_type']==='bank'){
                $linked=array();$hasLinks=false;
                foreach(array('C','S') as $role){
                    $supplier=$role==='S';$payment=$supplier?'paiementfourn':'paiement';$link=$supplier?'paiementfourn_facturefourn':'paiement_facture';$invoice=$supplier?'facture_fourn':'facture';
                    $fkPay=$supplier?'fk_paiementfourn':'fk_paiement';$fkInvoice=$supplier?'fk_facturefourn':'fk_facture';
                    foreach($this->rows('SELECT DISTINCT f.fk_soc FROM '.$p.$payment.' pay JOIN '.$p.$link.' l ON l.'.$fkPay.'=pay.rowid JOIN '.$p.$invoice.' f ON f.rowid=l.'.$fkInvoice.' WHERE f.entity='.$this->entity.' AND pay.fk_bank='.(int)$r['fk_doc']) as $q){
                        $hasLinks=true;$key=$role.$q->fk_soc;if(isset($candidates[$key])){$linked[$key]=array($role,(int)$q->fk_soc);}
                    }
                }
                if($hasLinks){$candidates=$linked;}
            }
            if(count($candidates)!==1){throw new RuntimeException('NorwaySaftParty:'.$r['rowid']);}
            $key=(string)array_key_first($candidates);[$role,$id]=$candidates[$key];
            if(!isset($this->partyRows[$id])){throw new RuntimeException('NorwaySaftParty:'.$r['rowid']);}
            $this->lineParty[$r['rowid']]=array($role,$key);
            if(!isset($this->parties[$key])){$this->parties[$key]=array('role'=>$role,'row'=>$this->partyRows[$id],'accounts'=>array());}
            $a=(string)$r['numero_compte'];$balance=&$this->parties[$key]['accounts'][$a];if(!$balance){$balance=array('opening'=>0,'closing'=>0);}
            $delta=self::cents($r['debit'])-self::cents($r['credit']);$balance['closing']+=$delta;
            if(substr($r['doc_date'],0,10)<$s['start'] || $r['doc_type']==='closure'){$balance['opening']+=$delta;}unset($balance);
        }
        foreach($this->parties as $key=>&$party){foreach($party['accounts'] as $number=>$balance){
            if(!isset($s['accounts'][$number])){unset($party['accounts'][$number]);}
        }}unset($party);
        foreach($this->parties as $key=>$party){if(!$party['accounts']){unset($this->parties[$key]);}}
        ksort($this->parties,SORT_NATURAL);
    }
    private function info($rowId,$code,$standard,$rate,$base,$tax,$percent)
    {
        $codes=NorwaySaftReference::taxCodes();if(!isset($codes[$standard])){throw new RuntimeException('NorwaySaftTax');}
        $key=$code.'/'.$rate;
        if(isset($this->taxes[$key]) && $this->taxes[$key]['standard']!==$standard){throw new RuntimeException('NorwaySaftTax');}
        $this->taxes[$key]=($this->taxes[$key]??array('code'=>$code,'standard'=>$standard,'rate'=>$rate,'description'=>$codes[$standard],'bases'=>array()));
        $this->taxes[$key]['bases'][(string)$percent]=$percent;
        $this->lineTax[$rowId][]=array('code'=>$code,'rate'=>$rate,'base'=>$base,'tax'=>$tax);
    }
    private function invoiceTax($s,$type,$id,$posted)
    {
        $sale=$type==='customer_invoice';$h=$this->source($type,$id);$specs=NorwayVatLedger::specs();$p=$this->p();
        $piece=null;foreach($posted as $r){
            if(substr((string)$r['doc_date'],0,10)!==substr((string)$h->datef,0,10) || (int)$r['fk_docdet']!==0 || ($piece!==null && $piece!==(int)$r['piece_num'])){throw new RuntimeException('NorwaySaftTax:'.$type.'/'.$id);}$piece=(int)$r['piece_num'];
        }
        $binding=NorwayVatLedger::bindings(array_map(static fn($r)=>(object)$r,$posted),$specs,$this->db,$this->entity);
        $table=$sale?'facturedet':'facture_fourn_det';$fk=$sale?'fk_facture':'fk_facture_fourn';
        $lines=$this->rows('SELECT d.*,a.account_number'.($sale?'':',e.norway_vat_deduction').' FROM '.$p.$table.' d LEFT JOIN '.$p.'accounting_account a ON a.rowid=d.fk_code_ventilation AND a.entity='.$this->entity
            .($sale?'':' LEFT JOIN '.$p.'facture_fourn_det_extrafields e ON e.fk_object=d.rowid').' WHERE d.'.$fk.'='.(int)$id.' ORDER BY d.rowid');
        $expected=array();$infos=array();$gross=0;$net=0;$originalTax=0;
        foreach($lines as $line){
            $code=(string)$line->vat_src_code;$spec=$specs[$code]??null;
            if(!$spec || $spec['type']!==($sale?1:2) || (float)$line->tva_tx!==(float)$spec['rate'] || (int)$line->info_bits!==0 || (int)$line->special_code!==0
                || !in_array((int)$line->product_type,array(0,1),true) || !$line->account_number || (float)$line->total_localtax1!=0 || (float)$line->total_localtax2!=0
                || ($sale && isset($line->situation_percent) && (float)$line->situation_percent!=100)){throw new RuntimeException('NorwaySaftTax:'.$type.'/'.$id);}
            $base=self::cents($line->total_ht);$vat=self::cents($sale?$line->total_tva:$line->tva);$net+=$base;$originalTax+=$vat;
            $percent=$sale?10000:NorwayPurchaseVat::percent($line->norway_vat_deduction,$code);
            $deducted=NorwayVatReport::cents(NorwayPurchaseVat::deductionAmount($vat*1000000,$percent));
            $reverse=!empty($spec['reverse_rate']);$standard=$spec['report_code']??$code;
            if($reverse){
                if($sale || (int)$h->vat_reverse_charge!==1 || (int)$line->product_type!==1 || $vat!==0){throw new RuntimeException('NorwaySaftTax');}
                $output=(int)round($base*0.25,0,PHP_ROUND_HALF_UP);$input=NorwayVatReport::cents(NorwayPurchaseVat::deductionAmount($output*1000000,$percent));
                $cost=$base+$output-$input;$tax=$input-$output;$rate=$spec['reverse_rate'];$standard=$percent===0?'87':'86';
                foreach(array(1=>-$output,2=>$input) as $direction=>$amount){if($amount===0){continue;}$a=$binding['accounts'][$code.'/'.$direction]??null;if(!$a){throw new RuntimeException('NorwaySaftTax');}$expected[$a]=($expected[$a]??0)+$amount;}
            }else{
                $cost=$sale?$base:$base+$vat-$deducted;$tax=$sale?-$vat:$deducted;$rate=$spec['rate'];
                if(!$sale && $percent===0){$standard='0';$rate=0;}
                if($tax!==0 && empty($spec['zero'])){$a=$binding['accounts'][$code.'/'.($sale?1:2)]??null;if(!$a){throw new RuntimeException('NorwaySaftTax');}$expected[$a]=($expected[$a]??0)+$tax;}
            }
            $a=(string)$line->account_number;$expected[$a]=($expected[$a]??0)+($sale?-$cost:$cost);
            $infos[$a][]=array('code'=>(!$sale && $percent===0 && $code!=='NO0' && $code!=='87'?$code.'-NO0':$code),'standard'=>(string)$standard,'rate'=>$rate,'base'=>((string)$standard==='0'?0:($sale?-$cost:($reverse?$base:$cost))),'tax'=>$tax,'percent'=>$percent/100);
            $gross+=self::cents($line->total_ttc);
        }
        if(!$lines || $net!==self::cents($h->total_ht) || $originalTax!==self::cents($h->total_tva) || $gross!==self::cents($h->total_ttc)){throw new RuntimeException('NorwaySaftTax');}
        $counter=$binding['counterpart'];$expected[$counter]=($expected[$counter]??0)+($sale?$gross:-$gross);$actual=array();$accountRows=array();
        foreach($posted as $r){$a=(string)$r['numero_compte'];$actual[$a]=($actual[$a]??0)+self::cents($r['debit'])-self::cents($r['credit']);$accountRows[$a][]=$r['rowid'];}
        foreach(array_unique(array_merge(array_keys($actual),array_keys($expected))) as $a){if(($actual[$a]??0)!==($expected[$a]??0)){throw new RuntimeException('NorwaySaftTax:'.$type.'/'.$id.'/'.$a);}}
        foreach($infos as $a=>$taxes){if(count($accountRows[$a]??array())!==1 || $a===$counter){throw new RuntimeException('NorwaySaftTax');}
            foreach($taxes as $info){$this->info($accountRows[$a][0],$info['code'],$info['standard'],$info['rate'],$info['base'],$info['tax'],$info['percent']);}
        }
    }
    private function prepareTax($s)
    {
        $docs=array();$manual=array();$p=$this->p();
        foreach($s['lines'] as $r){
            if(in_array($r['doc_type'],array('customer_invoice','supplier_invoice'),true)){$docs[$r['doc_type']][(int)$r['fk_doc']][]=$r;}
            elseif(in_array($r['doc_type'],array('manual',''),true)){$manual[$r['piece_num']][]=$r;}
        }
        foreach($docs as $type=>$ids){foreach($ids as $id=>$rows){$this->invoiceTax($s,$type,$id,$rows);}}
        foreach($manual as $piece=>$rows){
            $taxRows=$this->rows('SELECT v.*,b.* FROM '.$p.'norway_manual_vat v JOIN '.$p.'accounting_bookkeeping b ON b.rowid=v.fk_bookkeeping AND b.entity=v.entity WHERE v.entity='.$this->entity.' AND b.piece_num='.(int)$piece);
            if(!$taxRows){foreach($rows as $r){if(preg_match('/^27[01][0-9]$/D',$r['numero_compte'])){throw new RuntimeException('NorwaySaftManualTax:'.$piece);}}continue;}
            $ids=array_map(static fn($r)=>(int)$r->fk_bookkeeping,$taxRows);$bases=array_values(array_filter($rows,static fn($r)=>!in_array((int)$r['rowid'],$ids,true)));
            if(count($bases)!==1){throw new RuntimeException('NorwaySaftManualTax:'.$piece);}
            foreach($taxRows as $t){if(!NorwayManualVat::current($t)){throw new RuntimeException('NorwaySaftManualTax:'.$piece);}$spec=NorwayVatLedger::specs()[$t->tax_code]??null;
                if(!$spec || !empty($spec['reverse_rate'])){throw new RuntimeException('NorwaySaftManualTax:'.$piece);}
                $base=self::cents($t->tax_base);$signedBase=$spec['type']===1?-$base:$base;
                $this->info($bases[0]['rowid'],(string)$t->tax_code,(string)$t->tax_code,$spec['rate'],$base===0?null:$signedBase,self::cents($t->debit)-self::cents($t->credit),100);
            }
        }
    }
    public static function build($db,$entity,$start,$end,$user)
    {
        global $mysoc,$conf;
        NorwaySaftReference::groups((int)substr($start,0,4));
        $instance=new self();$instance->db=$db;$instance->entity=(int)$entity;
        if(!$db->begin()){throw new RuntimeException('NorwaySaftDatabase');}try{
            $s=NorwaySaftLedger::read($db,$entity,$start,$end,$user,true);
            if(($mysoc->country_code??'')!=='NO'){throw new RuntimeException('NorwaySaftCompany');}
            $mapping=NorwaySaftMapping::load($db,$entity,(int)substr($start,0,4),array_keys($s['accounts']));
            foreach($instance->rows('SELECT a.account_number,a.label FROM '.$db->prefix().'accounting_account a JOIN '.$db->prefix().'accounting_system c ON c.pcg_version=a.fk_pcg_version WHERE a.entity='.(int)$entity.' AND c.rowid='.(int)getDolGlobalInt('CHARTOFACCOUNTS')) as $a){if(isset($s['accounts'][$a->account_number])){$s['accounts'][$a->account_number]['label']=$a->label;}}
            foreach(array('customer_invoice'=>'facture','supplier_invoice'=>'facture_fourn') as $type=>$table){
                $pending=$instance->rows('SELECT f.rowid FROM '.$db->prefix().$table.' f WHERE f.entity='.(int)$entity." AND f.fk_statut>0 AND f.datef>='".$db->escape($start)."' AND f.datef<='".$db->escape($end)." 23:59:59' AND NOT EXISTS (SELECT b.rowid FROM ".$db->prefix()."accounting_bookkeeping b WHERE b.entity=f.entity AND b.doc_type='".$type."' AND b.fk_doc=f.rowid) LIMIT 1");
                if($pending){throw new RuntimeException('NorwaySaftUnposted:'.$type.'/'.$pending[0]->rowid);}
            }
            $bank=$instance->rows('SELECT b.rowid FROM '.$db->prefix().'bank b JOIN '.$db->prefix().'bank_account a ON a.rowid=b.fk_account WHERE a.entity='.(int)$entity." AND b.amount<>0 AND b.dateo>='".$db->escape($start)."' AND b.dateo<='".$db->escape($end)." 23:59:59' AND NOT EXISTS (SELECT g.rowid FROM ".$db->prefix()."accounting_bookkeeping g WHERE g.entity=a.entity AND g.doc_type='bank' AND g.fk_doc=b.rowid) LIMIT 1");
            if($bank){throw new RuntimeException('NorwaySaftUnposted:bank/'.$bank[0]->rowid);}
            $instance->prepareParties($s);$instance->prepareTax($s);
            $xml=$instance->xml($s,$mapping,$mysoc,$user);
            NorwaySaftReference::validate($xml);
            if(!$db->commit()){throw new RuntimeException('NorwaySaftDatabase');}
            return array('xml'=>$xml,'entries'=>count($s['vouchers']),'lines'=>count($s['lines']),'debit'=>NorwayVatReport::money(NorwayVatReport::cents($s['total_debit'])),'credit'=>NorwayVatReport::money(NorwayVatReport::cents($s['total_credit'])));
        }catch(Throwable $error){$db->rollback();throw $error;}
    }
    private function xml($s,$mapping,$company,$user)
    {
        $this->doc=new DOMDocument('1.0','UTF-8');$this->doc->formatOutput=true;$root=$this->doc->createElementNS(self::NS,'AuditFile');$this->doc->appendChild($root);
        $h=$this->node($root,'Header');foreach(array('AuditFileVersion'=>'1.40','AuditFileCountry'=>'NO','AuditFileDateCreated'=>gmdate('Y-m-d'),'SoftwareCompanyName'=>'Dolibarr Norway contributors','SoftwareID'=>'Dolibarr / Norway','SoftwareVersion'=>DOL_VERSION.' / '.$this->version()) as $tag=>$value){$this->node($h,$tag,$value);}
        $this->company($this->node($h,'Company'),$company,true,$user);$this->node($h,'DefaultCurrencyCode','NOK');$selection=$this->node($h,'SelectionCriteria');$this->node($selection,'SelectionStartDate',$s['start']);$this->node($selection,'SelectionEndDate',$s['end']);
        $this->node($h,'HeaderComment','MasterFiles and complete general ledger entries, including native customer and supplier subledgers. Native carry-forward is included in opening balances.');$this->node($h,'TaxAccountingBasis','A');$this->node($h,'UserID',$user->login);
        $master=$this->node($root,'MasterFiles');
        if($s['accounts']){$accounts=$this->node($master,'GeneralLedgerAccounts');foreach($s['accounts'] as $number=>$a){$n=$this->node($accounts,'Account');$this->node($n,'AccountID',$number);$this->node($n,'AccountDescription',$a['label']);$this->node($n,'GroupingCategory',$mapping[$number]['category']);$this->node($n,'GroupingCode',$mapping[$number]['code']);$this->node($n,'AccountType','GL');$this->balances($n,NorwayVatReport::cents($a['opening']),NorwayVatReport::cents($a['closing']));}}
        foreach(array('C'=>'Customers','S'=>'Suppliers') as $role=>$tag){$list=null;foreach($this->parties as $id=>$party){if($party['role']!==$role){continue;}if(!$list){$list=$this->node($master,$tag);}$n=$this->node($list,$role==='C'?'Customer':'Supplier');$this->company($n,$party['row']);$this->node($n,$role==='C'?'CustomerID':'SupplierID',$id);foreach($party['accounts'] as $a=>$b){$bn=$this->node($n,'BalanceAccount');$this->node($bn,'AccountID',$a);$this->balances($bn,$b['opening'],$b['closing']);}}}
        if($this->taxes){$tt=$this->node($master,'TaxTable');$entry=$this->node($tt,'TaxTableEntry');$this->node($entry,'TaxType','MVA');$this->node($entry,'Description','Merverdiavgift');ksort($this->taxes);foreach($this->taxes as $tax){$n=$this->node($entry,'TaxCodeDetails');$this->node($n,'TaxCode',$tax['code']);$this->node($n,'Description',$tax['description']);$this->node($n,'TaxPercentage',$tax['rate']);$this->node($n,'Country','NO');$this->node($n,'StandardTaxCode',$tax['standard']);ksort($tax['bases'],SORT_NUMERIC);foreach($tax['bases'] as $base){$this->node($n,'BaseRate',$base);}}}
        $gl=$this->node($root,'GeneralLedgerEntries');$this->node($gl,'NumberOfEntries',count($s['vouchers']));$this->node($gl,'TotalDebit',NorwayVatReport::money(NorwayVatReport::cents($s['total_debit'])));$this->node($gl,'TotalCredit',NorwayVatReport::money(NorwayVatReport::cents($s['total_credit'])));
        $journals=array();foreach($s['vouchers'] as $voucher){$journals[$voucher['journal']][]=$voucher;}ksort($journals);
        foreach($journals as $code=>$vouchers){$j=$this->node($gl,'Journal');$first=$s['lines'][$vouchers[0]['lines'][0]];$this->node($j,'JournalID',$code);$this->node($j,'Description',trim((string)$first['journal_label'])?:$code);$this->node($j,'Type','GL');
            foreach($vouchers as $voucher){$first=$s['lines'][$voucher['lines'][0]];$t=$this->node($j,'Transaction');$date=$voucher['date'];$this->node($t,'TransactionID',$voucher['piece']);$this->node($t,'Period',(int)substr($date,5,2));$this->node($t,'PeriodYear',substr($date,0,4));$this->node($t,'TransactionDate',$date);$this->node($t,'SourceID','Dolibarr');
                if(trim((string)$first['doc_type'])!==''){$this->node($t,'VoucherType',$first['doc_type']);}
                $description=trim((string)$first['doc_ref'])?:$first['label_operation'];
                if(in_array($first['doc_type'],array('customer_invoice','supplier_invoice'),true)){
                    $header=$this->source($first['doc_type'],(int)$first['fk_doc']);
                    if((int)$header->type===2 && (int)$header->fk_facture_source<=0 && trim((string)($header->ref_ext??''))!==''){$description.=' / original: '.$header->ref_ext;}
                }
                $this->node($t,'Description',$description);
                $created=substr((string)$first['date_creation'],0,10);if(!preg_match('/^20\d\d-\d\d-\d\d$/D',$created)){throw new RuntimeException('NorwaySaftSource');}$this->node($t,'SystemEntryDate',$created);$this->node($t,'GLPostingDate',$created);
                if(!empty($first['tms'])){$this->node($t,'ModificationDate',substr($first['tms'],0,10));}
                foreach($voucher['lines'] as $id){$r=$s['lines'][$id];$line=$this->node($t,'Line');$this->node($line,'RecordID',$id);$this->node($line,'AccountID',$r['numero_compte']);
                    if(trim((string)$r['doc_ref'])!==''){
                        // Native bank references may exceed the format's 35 characters.
                        // Use the complete stable native ID; retain the full human reference in Transaction/Description.
                        $sourceId=mb_strlen($r['doc_ref'],'UTF-8')<=35?$r['doc_ref']:((int)$r['fk_doc']>0?$r['doc_type'].'/'.$r['fk_doc']:'voucher/'.$r['piece_num']);
                        $this->node($line,'SourceDocumentID',$sourceId);
                    }
                    if(isset($this->lineParty[$id])){[$role,$party]=$this->lineParty[$id];$this->node($line,$role==='C'?'CustomerID':'SupplierID',$party);}
                    $this->node($line,'Description',trim((string)$r['label_operation'])?:$r['doc_ref']);
                    $debit=self::cents($r['debit']);$credit=self::cents($r['credit']);$this->money($line,$credit!==0?'CreditAmount':'DebitAmount',$credit!==0?$credit:$debit);
                    foreach($this->lineTax[$id]??array() as $tax){$n=$this->node($line,'TaxInformation');$this->node($n,'TaxType','MVA');$this->node($n,'TaxCode',$tax['code']);$this->node($n,'TaxPercentage',$tax['rate']);if($tax['base']!==null){$this->node($n,'TaxBase',NorwayVatReport::money(abs($tax['base'])));}$this->money($n,$tax['tax']<0?'CreditTaxAmount':'DebitTaxAmount',abs($tax['tax']));}
                    if(trim((string)$r['subledger_account'])!==''){$this->node($line,'ReferenceNumber',$r['subledger_account']);}
                    $due=$r['date_lim_reglement']??null;
                    if(!$due && in_array($r['doc_type'],array('customer_invoice','supplier_invoice'),true)){$due=$this->source($r['doc_type'],(int)$r['fk_doc'])->date_lim_reglement??null;}
                    if($due){$this->node($line,'DueDate',substr($due,0,10));}
                    if((int)$r['fk_doc']>0){
                        $ref=$r['doc_type'].'/'.$r['fk_doc'];
                        if(in_array($r['doc_type'],array('customer_invoice','supplier_invoice'),true)){
                            $source=$this->source($r['doc_type'],(int)$r['fk_doc']);
                            if((int)$source->type===2){
                                if((int)$source->fk_facture_source>0){$original=$this->source($r['doc_type'],(int)$source->fk_facture_source);$ref=mb_strlen((string)$original->ref,'UTF-8')<=35?$original->ref:$r['doc_type'].'/'.$original->rowid;}
                                elseif(trim((string)($source->ref_ext??''))!==''){$ref=mb_strlen((string)$source->ref_ext,'UTF-8')<=35?$source->ref_ext:'external/'.substr(hash('sha256',$source->ref_ext),0,26);}
                            }
                        }
                        $this->node($line,'CrossReference',$ref);
                    }
                }
            }
        }
        return $this->doc->saveXML();
    }
    private function version(){require_once __DIR__.'/../core/modules/modNorway.class.php';$m=new modNorway($this->db);return $m->version;}
}
