<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// Adapted from Dolibarr Peppol Export Contributors (C) 2024, UBLGenerator,
// doomzlight/dolibarr-peppol-export at 506cc2ac8d785acbab6475c60697ad7feba769b3.
// Modified: Norwegian identity/VAT, source amounts, references, discounts and access guards.
require_once __DIR__.'/NorwayInvoiceValidation.php';

/** Outgoing BIS Billing documents from native invoices. No writes and no network calls. */
class NorwayEhfXml
{
    const CUSTOMIZATION = 'urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0';
    const PROFILE = 'urn:fdc:peppol.eu:2017:poacc:billing:01:1.0';
    private $db;
    private $xml;
    private $factor;
    private $outsideVat;

    public function __construct($db) { $this->db=$db; }

    private static function need($condition, $code)
    {
        if (!$condition) { throw new RuntimeException('NorwayEhf'.$code); }
    }

    /** Explicit foreign endpoint must be the recipient's registered EAS identifier, never guessed from VAT. */
    public static function endpoint($party, $explicit='')
    {
        if ($explicit==='') {
            self::need(($party->country_code??'')==='NO', 'Endpoint');
            $number=preg_replace('/\s+/', '', (string)($party->idprof1??''));
            self::need(NorwayReadiness::validOrganisationNumber($number), 'Endpoint');
            return '0192:'.$number;
        }
        self::need(preg_match('/^\d{4}:[A-Za-z0-9][A-Za-z0-9._:\/-]{0,127}$/D', $explicit)===1, 'Endpoint');
        if (substr($explicit,0,5)==='0192:') {
            self::need(preg_match('/^0192:\d{9}$/D',$explicit)===1 && NorwayReadiness::validOrganisationNumber(substr($explicit,5)), 'Endpoint');
        }
        return $explicit;
    }

    public function generateFromInvoice($id, $recipient='')
    {
        global $conf,$user,$mysoc;
        self::need(isModEnabled('norway') && $user && empty($user->socid) && $user->hasRight('facture','lire'), 'Access');
        require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
        require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
        require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';
        $invoice=new Facture($this->db);
        self::need($invoice->fetch((int)$id)>0 && (int)$invoice->entity===(int)$conf->entity, 'Access');
        self::need(checkUserAccessToObject($user,array('facture'),$invoice,'','','fk_soc'), 'Access');
        self::need(in_array((int)$invoice->statut,array(1,2),true) && in_array((int)$invoice->type,array(0,2),true), 'DocumentType');
        $buyer=new Societe($this->db);
        self::need($buyer->fetch($invoice->socid)>0 && (int)$buyer->entity===(int)$conf->entity, 'Access');
        $source=null;
        if ((int)$invoice->type===2 && (int)$invoice->fk_facture_source>0) {
            $source=new Facture($this->db);
            self::need($source->fetch($invoice->fk_facture_source)>0, 'CreditSource');
        }
        self::need(!NorwayInvoiceValidation::missing($invoice,$mysoc,$buyer,$source), 'InvoiceFields');
        self::need($conf->currency==='NOK' && $invoice->multicurrency_code==='NOK', 'Currency');
        $bank=new Account($this->db);
        $bankId=(int)($invoice->fk_account?:($conf->global->FACTURE_RIB_NUMBER??0));
        $bankRow=$this->db->query('SELECT rowid FROM '.$this->db->prefix().'bank_account WHERE rowid='.$bankId.' AND entity='.(int)$conf->entity);
        self::need($bankRow && $this->db->num_rows($bankRow)===1 && $bankId>0 && $bank->fetch($bankId)>0 && (int)$bank->clos===0 && $bank->currency_code==='NOK', 'Bank');
        $iban=strtoupper(preg_replace('/\s+/','',(string)$bank->iban));
        self::need(preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{11,30}$/D',$iban)===1 && self::validIban($iban), 'Bank');
        self::need(($invoice->mode_reglement_code??'')==='VIR', 'PaymentMethod');
        $buyerReference=trim((string)$invoice->ref_customer);$orderReference=$this->orderReference($invoice);
        self::need($buyerReference!=='' || $orderReference!=='', 'BuyerReference');
        $sender=self::endpoint($mysoc);$receiver=self::endpoint($buyer,$recipient);
        self::need(trim((string)$buyer->address)!=='' && trim((string)$buyer->zip)!=='' && trim((string)$buyer->town)!=='' && preg_match('/^[A-Z]{2}$/D',(string)$buyer->country_code), 'BuyerAddress');
        $this->factor=(int)$invoice->type===2?-1:1;
        $groups=array();$lineTotal=0;$vatTotal=0;
        foreach ($invoice->lines as $line) {
            self::need(in_array((int)$line->product_type,array(0,1),true) && empty($line->special_code) && (float)$line->total_localtax1===0.0 && (float)$line->total_localtax2===0.0, 'LineType');
            $tax=$this->tax($line);$key=$tax['category'].':'.$tax['rate'];
            if (!isset($groups[$key])) { $groups[$key]=$tax+array('net'=>0,'tax'=>0); }
            $net=$this->cents($this->factor*$line->total_ht);$vat=$this->cents($this->factor*$line->total_tva);
            $groups[$key]['net']+=$net;$groups[$key]['tax']+=$vat;$lineTotal+=$net;$vatTotal+=$vat;
            // Source price/quantity/discount must explain the source line. No repricing of issued documents.
            $calculated=(float)$line->subprice*(float)$line->qty*(1-(float)$line->remise_percent/100);
            self::need(abs($calculated-(float)$line->total_ht)<0.010001, 'LineAmount');
            self::need((float)$line->remise_percent>=0 && (float)$line->remise_percent<=100, 'Discount');
        }
        self::need($lineTotal===$this->cents($this->factor*$invoice->total_ht) && $vatTotal===$this->cents($this->factor*$invoice->total_tva)
            && $lineTotal+$vatTotal===$this->cents($this->factor*$invoice->total_ttc), 'Totals');
        self::need(!isset($groups['G:0']) || $buyer->country_code!=='NO', 'ExportCountry');
        $this->outsideVat=isset($groups['O:0']);
        self::need(!$this->outsideVat || count($groups)===1, 'MixedOutsideVat');
        foreach ($groups as $group) {
            self::need(abs($group['tax']-round($group['net']*$group['rate']/100))<=1, 'VatRounding');
        }
        if (!$this->outsideVat) {
            self::need(!empty($mysoc->tva_assuj) && strtoupper(preg_replace('/\s+/','',(string)$mysoc->tva_intra))==='NO'.substr($sender,5).'MVA', 'SellerVat');
        }
        $credit=$this->factor===-1;$type=$credit?'CreditNote':'Invoice';
        $this->xml=new DOMDocument('1.0','UTF-8');$this->xml->formatOutput=true;
        $root=$this->xml->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:'.$type.'-2',$type);$this->xml->appendChild($root);
        $root->setAttribute('xmlns:cac','urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $root->setAttribute('xmlns:cbc','urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $this->e($root,'cbc:CustomizationID',self::CUSTOMIZATION);$this->e($root,'cbc:ProfileID',self::PROFILE);
        $this->e($root,'cbc:ID',$invoice->ref);$this->e($root,'cbc:IssueDate',dol_print_date($invoice->date,'%Y-%m-%d'));
        if (!$credit) { $this->e($root,'cbc:DueDate',dol_print_date($invoice->date_lim_reglement,'%Y-%m-%d')); }
        $this->e($root,$credit?'cbc:CreditNoteTypeCode':'cbc:InvoiceTypeCode',$credit?'381':'380');
        if (trim((string)$invoice->note_public)!=='') { $this->e($root,'cbc:Note',$this->plain($invoice->note_public)); }
        $this->e($root,'cbc:DocumentCurrencyCode','NOK');
        if ($buyerReference!=='') { $this->e($root,'cbc:BuyerReference',$buyerReference); }
        if ($orderReference!=='') { $this->e($this->e($root,'cac:OrderReference'),'cbc:ID',$orderReference); }
        if ($credit) {
            $reference=$this->e($this->e($root,'cac:BillingReference'),'cac:InvoiceDocumentReference');
            $this->e($reference,'cbc:ID',$source?$source->ref:trim($invoice->ref_ext));
            if ($source) { $this->e($reference,'cbc:IssueDate',dol_print_date($source->date,'%Y-%m-%d')); }
        }
        $this->party($root,'cac:AccountingSupplierParty',$mysoc,$sender,true);
        $this->party($root,'cac:AccountingCustomerParty',$buyer,$receiver,false);
        $payment=$this->e($root,'cac:PaymentMeans');$this->e($payment,'cbc:PaymentMeansCode','30');
        $this->e($payment,'cbc:PaymentID',$invoice->ref);
        $account=$this->e($payment,'cac:PayeeFinancialAccount');$this->e($account,'cbc:ID',$iban);
        if (!empty($bank->bic)) { $this->e($this->e($account,'cac:FinancialInstitutionBranch'),'cbc:ID',strtoupper(preg_replace('/\s+/','',$bank->bic))); }
        $taxTotal=$this->e($root,'cac:TaxTotal');$this->money($taxTotal,'cbc:TaxAmount',$vatTotal);
        foreach ($groups as $group) {
            $subtotal=$this->e($taxTotal,'cac:TaxSubtotal');$this->money($subtotal,'cbc:TaxableAmount',$group['net']);$this->money($subtotal,'cbc:TaxAmount',$group['tax']);
            $this->taxCategory($subtotal,'cac:TaxCategory',$group,true);
        }
        $total=$this->e($root,'cac:LegalMonetaryTotal');
        foreach (array('LineExtensionAmount'=>$lineTotal,'TaxExclusiveAmount'=>$lineTotal,'TaxInclusiveAmount'=>$lineTotal+$vatTotal,'PayableAmount'=>$lineTotal+$vatTotal) as $name=>$amount) { $this->money($total,'cbc:'.$name,$amount); }
        foreach ($invoice->lines as $i=>$line) { $this->line($root,$line,$i+1,$credit); }
        return $this->xml->saveXML();
    }

    private function orderReference($invoice)
    {
        self::need($invoice->fetchObjectLinked(null,'commande',$invoice->id,'facture')>=0, 'InvoiceFields');
        $references=array();
        foreach ($invoice->linkedObjects['commande']??array() as $order) {
            if ((int)$order->entity!==(int)$invoice->entity || (int)$order->socid!==(int)$invoice->socid) { continue; }
            $value=trim((string)($order->ref_customer??''));
            if ($value!=='') { $references[$value]=true; }
        }
        // More than one purchase order is ambiguous. The native invoice buyer reference remains available.
        return count($references)===1?(string)array_key_first($references):'';
    }

    private function tax($line)
    {
        $rates=array('3'=>25,'31'=>15,'33'=>12,'5'=>0,'6'=>0,'7'=>0,'52'=>0);$code=(string)$line->vat_src_code;
        self::need(isset($rates[$code]) && (float)$line->tva_tx===(float)$rates[$code], 'VatCode');
        // Norwegian EHF guidance: chapter 6 zero-rated Z; exemptions §§3-2–3-20 E; exports G.
        $category=array('3'=>'S','31'=>'S','33'=>'S','5'=>'Z','6'=>'E','7'=>'O','52'=>'G')[$code];
        $reason=array('E'=>'Unntatt merverdiavgift','O'=>'Not subject to VAT','G'=>'Export outside the EU')[$category]??'';
        return array('category'=>$category,'rate'=>$rates[$code],'reason'=>$reason);
    }

    private function party($root,$tag,$company,$endpoint,$seller)
    {
        $party=$this->e($this->e($root,$tag),'cac:Party');list($scheme,$value)=explode(':',$endpoint,2);
        $this->e($party,'cbc:EndpointID',$value)->setAttribute('schemeID',$scheme);
        $this->e($this->e($party,'cac:PartyName'),'cbc:Name',$company->name);
        $address=$this->e($party,'cac:PostalAddress');$parts=preg_split('/\r\n|\r|\n/',trim($company->address));
        $this->e($address,'cbc:StreetName',array_shift($parts));
        if ($parts) { $this->e($address,'cbc:AdditionalStreetName',implode(', ',$parts)); }
        $this->e($address,'cbc:CityName',$company->town);$this->e($address,'cbc:PostalZone',$company->zip);
        $this->e($this->e($address,'cac:Country'),'cbc:IdentificationCode',$company->country_code);
        if (!$this->outsideVat && !empty($company->tva_intra)) {
            $tax=$this->e($party,'cac:PartyTaxScheme');$this->e($tax,'cbc:CompanyID',strtoupper(preg_replace('/\s+/','',$company->tva_intra)));
            $this->e($this->e($tax,'cac:TaxScheme'),'cbc:ID','VAT');
        }
        if ($seller && in_array((int)($company->forme_juridique_code??0),array(173002,173003),true)) {
            $tax=$this->e($party,'cac:PartyTaxScheme');$this->e($tax,'cbc:CompanyID','Foretaksregisteret');$this->e($this->e($tax,'cac:TaxScheme'),'cbc:ID','TAX');
        }
        $legal=$this->e($party,'cac:PartyLegalEntity');$this->e($legal,'cbc:RegistrationName',$company->name);
        if (!empty($company->idprof1)) {
            $id=$this->e($legal,'cbc:CompanyID',preg_replace('/\s+/','',$company->idprof1));
            if ($company->country_code==='NO') { $id->setAttribute('schemeID','0192'); }
        }
    }

    private function line($root,$line,$number,$credit)
    {
        $node=$this->e($root,$credit?'cac:CreditNoteLine':'cac:InvoiceLine');$this->e($node,'cbc:ID',$number);
        // Native credits negate the price/totals, not necessarily the quantity. Preserve mixed-sign lines.
        $price=$this->factor*(float)$line->subprice;$quantity=(float)$line->qty;
        if ($price<0) { $price=-$price;$quantity=-$quantity; }
        $this->e($node,$credit?'cbc:CreditedQuantity':'cbc:InvoicedQuantity',$this->decimal($quantity))->setAttribute('unitCode',$this->unit($line));
        $net=$this->cents($this->factor*$line->total_ht);$this->money($node,'cbc:LineExtensionAmount',$net);
        if (!empty($line->date_start) || !empty($line->date_end)) {
            self::need(empty($line->date_start) || empty($line->date_end) || $line->date_start<=$line->date_end, 'DeliveryPeriod');
            $period=$this->e($node,'cac:InvoicePeriod');
            if (!empty($line->date_start)) { $this->e($period,'cbc:StartDate',dol_print_date($line->date_start,'%Y-%m-%d')); }
            if (!empty($line->date_end)) { $this->e($period,'cbc:EndDate',dol_print_date($line->date_end,'%Y-%m-%d')); }
        }
        if ((float)$line->remise_percent>0) {
            $gross=$price*$quantity;$discount=$this->cents($gross)-$net;
            $allowance=$this->e($node,'cac:AllowanceCharge');$this->e($allowance,'cbc:ChargeIndicator',$discount<0?'true':'false');
            $this->e($allowance,'cbc:AllowanceChargeReason','Discount');$this->money($allowance,'cbc:Amount',abs($discount));
        }
        $item=$this->e($node,'cac:Item');$description=$this->plain($line->desc??'');
        if ($description!=='') { $this->e($item,'cbc:Description',$description); }
        $name=$this->plain($line->product_label?:($line->label??'')?:$description);
        self::need($name!=='','LineDescription');$this->e($item,'cbc:Name',mb_substr($name,0,100));
        $this->taxCategory($item,'cac:ClassifiedTaxCategory',$this->tax($line),false);
        $amount=$this->e($this->e($node,'cac:Price'),'cbc:PriceAmount',$this->decimal($price));$amount->setAttribute('currencyID','NOK');
    }

    private function unit($line)
    {
        if (empty($line->fk_unit)) { return 'C62'; }
        $result=$this->db->query('SELECT code FROM '.$this->db->prefix().'c_units WHERE rowid='.(int)$line->fk_unit);
        $row=$result?$this->db->fetch_object($result):null;
        $map=array('P'=>'C62','SET'=>'SET','S'=>'SEC','MI'=>'MIN','H'=>'HUR','D'=>'DAY','W'=>'WEE','MO'=>'MON','Y'=>'ANN',
            'T'=>'TNE','KG'=>'KGM','G'=>'GRM','MG'=>'MGM','M'=>'MTR','DM'=>'DMT','CM'=>'CMT','MM'=>'MMT','FT'=>'FOT','IN'=>'INH',
            'M2'=>'MTK','DM2'=>'DMK','CM2'=>'CMK','MM2'=>'MMK','M3'=>'MTQ','DM3'=>'DMQ','CM3'=>'CMQ','MM3'=>'MMQ','L'=>'LTR');
        self::need($row && isset($map[$row->code]), 'Unit');return $map[$row->code];
    }

    private function taxCategory($parent,$tag,$group,$breakdown)
    {
        $category=$this->e($parent,$tag);$this->e($category,'cbc:ID',$group['category']);
        if ($group['category']!=='O') { $this->e($category,'cbc:Percent',$group['rate']); }
        if ($breakdown && $group['reason']!=='') { $this->e($category,'cbc:TaxExemptionReason',$group['reason']); }
        $this->e($this->e($category,'cac:TaxScheme'),'cbc:ID','VAT');
    }
    private function cents($value) { self::need(is_finite((float)$value) && abs((float)$value)<1.0e12,'AmountRange');return (int)round((float)$value*100); }
    private function decimal($value) { return rtrim(rtrim(number_format((float)$value,8,'.',''),'0'),'.')?:'0'; }
    private function plain($value) { return trim(html_entity_decode(strip_tags((string)$value),ENT_QUOTES|ENT_HTML5,'UTF-8')); }
    private function money($parent,$name,$cents) { $node=$this->e($parent,$name,number_format($cents/100,2,'.',''));$node->setAttribute('currencyID','NOK');return $node; }
    private function e($parent,$name,$value=null)
    {
        $node=$this->xml->createElement($name);$parent->appendChild($node);
        if ($value!==null) { $node->appendChild($this->xml->createTextNode((string)$value)); }return $node;
    }
    private static function validIban($iban)
    {
        $digits=substr($iban,4).substr($iban,0,4);$remainder=0;
        foreach (str_split($digits) as $char) {
            foreach (str_split(ctype_alpha($char)?(string)(ord($char)-55):$char) as $digit) { $remainder=($remainder*10+(int)$digit)%97; }
        }
        return $remainder===1;
    }
}
