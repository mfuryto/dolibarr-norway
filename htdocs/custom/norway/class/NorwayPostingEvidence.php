<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require_once __DIR__.'/NorwaySchema.php';
require_once __DIR__.'/NorwayPostingTaxContext.php';
require_once __DIR__.'/NorwayPostingRegistrationContext.php';
require_once __DIR__.'/NorwayPostingAllocation.php';
require_once __DIR__.'/NorwayPurchaseAllocation.php';
require_once __DIR__.'/NorwayPurchaseDeduction.php';
/** Raw observations at a native create event, not tax qualification or backfill. */
class NorwayPostingEvidence
{
    const TYPE='bookkeeping_event';
    const BYTES=1000000;
    private static function rows($db,$sql)
    {
        $r=$db->query($sql);if (!$r) { throw new RuntimeException('Posting observation query'); }
        $rows=array();while ($row=$db->fetch_object($r)) { $rows[]=(array)$row; }return $rows;
    }
    private static function encode($data)
    {
        $json=json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        if (strlen($json)>self::BYTES) { throw new RuntimeException('Posting observation capacity'); }return $json;
    }
    private static function source($db,$entity,$id)
    {
        $p=$db->prefix();
        $rows=self::rows($db,'SELECT rowid,entity,doc_type,fk_doc,fk_docdet,code_journal,piece_num,doc_date,doc_ref,numero_compte,label_compte,subledger_account,subledger_label,debit,credit,date_creation,fk_user_author FROM '.$p.'accounting_bookkeeping WHERE entity='.$entity.' AND rowid='.$id);
        if (count($rows)!==1 || !in_array($rows[0]['doc_type'],array('customer_invoice','supplier_invoice'),true)) { return null; }$posting=$rows[0];
        $invoiceId=(int)$posting['fk_doc'];
        $supplier=$posting['doc_type']==='supplier_invoice';
        $table=$supplier ? 'facture_fourn' : 'facture';$detail=$supplier ? 'facture_fourn_det' : 'facturedet';$foreign=$supplier ? 'fk_facture_fourn' : 'fk_facture';
        $extra=$supplier ? ',ref_supplier,vat_reverse_charge,revenuestamp,localtax1,localtax2,multicurrency_tx,multicurrency_total_ht,multicurrency_total_tva,multicurrency_total_ttc' : '';
        $invoices=self::rows($db,'SELECT rowid,entity,ref,type,fk_statut,fk_soc,datef,fk_facture_source,multicurrency_code,total_ht,total_tva,total_ttc'.$extra.' FROM '.$p.$table.' WHERE entity='.$entity.' AND rowid='.$invoiceId);
        $vat=$supplier ? 'd.tva AS total_tva' : 'd.total_tva';
        $extraLine=$supplier ? ',d.fk_facture_fourn,d.pu_ht,d.qty,d.remise_percent,d.total_localtax1,d.total_localtax2,d.multicurrency_code,d.multicurrency_subprice,d.multicurrency_total_ht,d.multicurrency_total_tva,d.multicurrency_total_ttc' : '';
        $lines=$invoices ? self::rows($db,'SELECT d.rowid,d.'.$foreign.' AS fk_facture,d.fk_code_ventilation,d.product_type,d.tva_tx,d.vat_src_code,d.info_bits,d.localtax1_tx,d.localtax2_tx,d.total_ht,'.$vat.',d.total_ttc,a.account_number AS bound_number,a.label AS bound_label'.$extraLine.' FROM '.$p.$detail.' d LEFT JOIN '.$p.'accounting_account a ON a.rowid=d.fk_code_ventilation AND a.entity='.$entity.' WHERE d.'.$foreign.'='.$invoiceId.' ORDER BY d.rowid LIMIT 201') : array();
        $parties=$invoices ? self::rows($db,'SELECT s.rowid,s.entity,s.nom,s.address,s.zip,s.town,s.siren,s.tva_intra,s.fk_pays,c.code AS country FROM '.$p.'societe s LEFT JOIN '.$p.'c_country c ON c.rowid=s.fk_pays WHERE s.entity='.$entity.' AND s.rowid='.(int)$invoices[0]['fk_soc']) : array();
        $complete=(bool)$parties && (bool)$invoices && (bool)$lines && count($lines)<=200;
        if (count($lines)>200) { array_pop($lines); }
        $constants=self::rows($db,"SELECT entity,name,value FROM ".$p."const WHERE entity IN (0,".$entity.") AND name IN ('MAIN_INFO_SIREN','MAIN_INFO_SOCIETE_COUNTRY','MAIN_INFO_SOCIETE_NOM','MAIN_INFO_SOCIETE_ADDRESS','MAIN_INFO_SOCIETE_ZIP','MAIN_INFO_SOCIETE_TOWN','NORWAY_VAT_REGISTRATION_START','NORWAY_TAX_MAPPINGS_V1') ORDER BY entity,name");
        return array('source_complete'=>$complete,'posting'=>$posting,'invoice'=>$invoices ? $invoices[0] : null,'lines'=>$lines,'party'=>$parties ? $parties[0] : null,'settings'=>$constants);
    }
    /** Called only inside the core transaction; commits/rollbacks are owned by the core. */
    public static function capture($db,$entity,$object,$user)
    {
        if ((int)$entity<=0 || (int)$entity!==(int)$GLOBALS['conf']->entity || (int)$user->id<=0 || (int)($object->id ?? 0)<=0) { throw new InvalidArgumentException('Posting observation identity'); }
        if (!in_array($object->doc_type ?? '',array('customer_invoice','supplier_invoice'),true)) { return 0; }
        $entity=(int)$entity;$id=(int)$object->id;
        $active=self::rows($db,'SELECT @@in_transaction AS active');
        if (count($active)!==1 || (int)$active[0]['active']!==1) { throw new RuntimeException('Posting observation requires native transaction'); }
        NorwaySchema::verify($db);
        if ($object->doc_type==='supplier_invoice' && (int)($object->fk_doc ?? 0)>0) { NorwayPurchaseDeduction::lockForPosting($db,$entity,(int)$object->fk_doc); }
        require_once __DIR__.'/NorwayPurchasePosting.php';
        NorwayPurchasePosting::guardNative($db,$entity,$object);
        $basis=self::source($db,$entity,$id);
        // Temporary/unproven create paths must not label an unrelated existing row as newly observed.
        if (!$basis || $basis['posting']['doc_type']!==$object->doc_type || $basis['posting']['doc_ref']!==($object->doc_ref ?? null)
            || (int)$basis['posting']['fk_doc']!==(int)($object->fk_doc ?? 0)
            || $basis['posting']['numero_compte']!==(string)($object->numero_compte ?? '')) { return 0; }
        $p=$db->prefix();$old=self::rows($db,"SELECT rowid,payload,payload_sha256 FROM ".$p."norway_tax_evidence WHERE entity=".$entity." AND document_type='".self::TYPE."' AND fk_document=".$id.' AND revision=1 FOR UPDATE');
        if ($old) {
            $data=self::decode($old[0],$entity,$id);
            if ($data['basis']!==$basis) { throw new RuntimeException('Posting observation replay changed'); }return 0;
        }
        $supplier=$basis['posting']['doc_type']==='supplier_invoice';
        $data=array('version'=>$supplier ? ((int)$basis['posting']['fk_doc']>0 ? 6 : 5) : 3,'kind'=>self::TYPE,'entity'=>$entity,'posting_id'=>$id,'event'=>'BOOKKEEPING_CREATE','captured_at'=>gmdate('Y-m-d H:i:s'),'user'=>(int)$user->id,'basis'=>$basis,'tax_context'=>$supplier ? NorwayPostingTaxContext::evaluatePurchase($basis,$entity) : NorwayPostingTaxContext::evaluate($basis,$entity),'registration_context'=>$supplier ? null : NorwayPostingRegistrationContext::evaluate($basis,$entity));
        if ($supplier && $data['version']===6) { $data['deduction_context']=NorwayPurchaseDeduction::captureContext($db,$entity,(int)$basis['posting']['fk_doc'],$basis);NorwayPurchasePosting::assertAllocationPath($data['deduction_context'],$entity,(int)$basis['posting']['fk_doc']); }
        $json=self::encode($data);$hash=hash('sha256',$json);
        $sql="INSERT INTO ".$p."norway_tax_evidence (entity,document_type,fk_document,revision,captured_at,fk_user,mapping_revision,payload,payload_sha256) VALUES (".$entity.",'".self::TYPE."',".$id.",1,'".$data['captured_at']."',".(int)$user->id.",0,'".$db->escape($json)."','".$hash."')";
        if (!$db->query($sql)) { throw new RuntimeException('Posting observation write'); }return 1;
    }
    private static function decode($row,$entity,$id)
    {
        if (strlen($row['payload'])>self::BYTES || !hash_equals($row['payload_sha256'],hash('sha256',$row['payload']))) { throw new RuntimeException('Posting observation integrity'); }
        $data=json_decode($row['payload'],true,512,JSON_THROW_ON_ERROR);
        if (!in_array($data['version'] ?? null,array(1,2,3,4,5,6),true) || ($data['kind'] ?? null)!==self::TYPE || ($data['event'] ?? null)!=='BOOKKEEPING_CREATE'
            || ($data['entity'] ?? null)!==$entity || ($data['posting_id'] ?? null)!==$id || !is_array($data['basis'] ?? null)
            || !is_int($data['user'] ?? null) || $data['user']<=0 || !is_string($data['captured_at'] ?? null)
            || !is_bool($data['basis']['source_complete'] ?? null) || !is_array($data['basis']['lines'] ?? null) || count($data['basis']['lines'])>200 || !is_array($data['basis']['settings'] ?? null)
            || !array_key_exists('invoice',$data['basis']) || ($data['basis']['posting']['doc_type'] ?? null)!==($data['version']>=4 ? 'supplier_invoice' : 'customer_invoice')
            || (int)($data['basis']['posting']['rowid'] ?? 0)!==$id || (int)($data['basis']['posting']['entity'] ?? 0)!==$entity) { throw new RuntimeException('Posting observation metadata'); }
        if (in_array($data['version'],array(2,3),true) && ($data['tax_context'] ?? null)!==NorwayPostingTaxContext::evaluate($data['basis'],$entity)) { throw new RuntimeException('Posting tax context integrity'); }
        if ($data['version']===3 && ($data['registration_context'] ?? null)!==NorwayPostingRegistrationContext::evaluate($data['basis'],$entity)) { throw new RuntimeException('Posting registration context integrity'); }
        if ($data['version']===4 && (!array_key_exists('tax_context',$data) || $data['tax_context']!==null || !array_key_exists('registration_context',$data) || $data['registration_context']!==null)) { throw new RuntimeException('Supplier observation is not tax qualification'); }
        if (in_array($data['version'],array(5,6),true) && (($data['tax_context'] ?? null)!==NorwayPostingTaxContext::evaluatePurchase($data['basis'],$entity) || !array_key_exists('registration_context',$data) || $data['registration_context']!==null)) { throw new RuntimeException('Purchase tax context integrity'); }
        if ($data['version']===6) {
            $context=$data['deduction_context'] ?? null;
            if (!is_array($context) || !array_key_exists('assessment',$context) || !array_key_exists('source',$context)
                || $context!==NorwayPurchaseDeduction::context($context['assessment'],$context['source'],$data['basis'],$entity,(int)$data['basis']['posting']['fk_doc'])) { throw new RuntimeException('Purchase deduction context integrity'); }
        }
        return $data;
    }
    public static function report($db,$entity,$invoiceId,$user,$profile=null,$documentType='customer_invoice',$deductionPercentage=null)
    {
        if (!in_array($documentType,array('customer_invoice','supplier_invoice'),true)) { throw new InvalidArgumentException('Observation document type'); }
        $supplier=$documentType==='supplier_invoice';
        if (!is_int($entity) || $entity<=0 || $entity!==(int)$GLOBALS['conf']->entity || !is_int($invoiceId) || $invoiceId<=0
            || empty($user->admin) || !($supplier ? $user->hasRight('fournisseur','facture','lire') : $user->hasRight('facture','lire')) || !$user->hasRight('accounting','mouvements','lire')) { throw new InvalidArgumentException('Posting observation access'); }
        $active=self::rows($db,'SELECT @@in_transaction AS active');
        if ((int)$active[0]['active']!==0 || !$db->query('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ') || !$db->query('SET TRANSACTION READ ONLY') || $db->begin()<=0) { throw new RuntimeException('Posting observation read boundary'); }
        try { $result=self::readReport($db,$entity,$invoiceId,$documentType);
            if ($profile!==null) {
                $ledger=NorwayInvoiceLedger::report($db,$entity,$invoiceId,$documentType);
                NorwayInvoiceAllocation::assertCompleteVoucher($db,$entity,$invoiceId,$ledger,$documentType);
                if ($supplier) {
                    require_once __DIR__.'/NorwayPurchasePosting.php';
                    $effective=NorwayPurchasePosting::effective($db,$entity,$invoiceId,$user,$result,$ledger);
                    $result=NorwayPurchaseAllocation::evaluate($effective['observations'],$effective['ledger'],$profile,$deductionPercentage,$effective['date']);$result['correction_revision']=$effective['revision'];
                } else { $result=NorwayPostingAllocation::evaluate($result,$ledger,$profile); }
            }
            if ($db->commit()<=0) { throw new RuntimeException('Observation read commit'); }return $result; }
        catch (Throwable $error) { $db->rollback();throw $error; }
    }
    /** Already-guarded native operation owns the transaction; retain the normal read permissions. */
    public static function transactionReport($db,$entity,$id,$user)
    {
        if (!is_int($entity) || $entity!==(int)$GLOBALS['conf']->entity || $entity<=0 || !is_int($id) || $id<=0 || empty($user->admin)
            || !$user->hasRight('fournisseur','facture','lire') || !$user->hasRight('accounting','mouvements','lire')) { throw new InvalidArgumentException('Observation access'); }
        $active=self::rows($db,'SELECT @@in_transaction AS active');if ((int)$active[0]['active']!==1) { throw new RuntimeException('Observation transaction'); }
        return self::readReport($db,$entity,$id,'supplier_invoice');
    }
    private static function readReport($db,$entity,$invoiceId,$documentType)
    {
        NorwaySchema::verify($db);$p=$db->prefix();$table=$documentType==='supplier_invoice' ? 'facture_fourn' : 'facture';
        $invoice=self::rows($db,'SELECT rowid FROM '.$p.$table.' WHERE entity='.$entity.' AND rowid='.$invoiceId);
        if (count($invoice)!==1) { throw new RuntimeException('Posting observation invoice'); }
        $postings=self::rows($db,"SELECT rowid FROM ".$p."accounting_bookkeeping WHERE entity=".$entity." AND doc_type='".$documentType."' AND fk_doc=".$invoiceId.' ORDER BY rowid LIMIT 201');
        $archived=self::rows($db,"SELECT fk_document AS rowid FROM ".$p."norway_tax_evidence WHERE entity=".$entity." AND document_type='".self::TYPE."' AND JSON_VALID(payload)=1 AND JSON_UNQUOTE(JSON_EXTRACT(payload,'$.basis.posting.doc_type'))='".$documentType."' AND CAST(JSON_UNQUOTE(JSON_EXTRACT(payload,'$.basis.posting.fk_doc')) AS UNSIGNED)=".$invoiceId.' ORDER BY fk_document LIMIT 201');
        $ids=array();foreach (array_merge($postings,$archived) as $row) { $ids[(int)$row['rowid']]=array('rowid'=>(int)$row['rowid']); }$postings=array_values($ids);
        if (count($postings)>200 || count($archived)>200) { throw new RuntimeException('Posting observation report capacity'); }

        $result=array();$bytes=0;
        foreach ($postings as $posting) {
            $id=(int)$posting['rowid'];$rows=self::rows($db,"SELECT rowid,captured_at,fk_user,revision,mapping_revision,payload,payload_sha256 FROM ".$p."norway_tax_evidence WHERE entity=".$entity." AND document_type='".self::TYPE."' AND fk_document=".$id.' ORDER BY revision');
            if (!$rows) { $result[]=array('id'=>$id,'status'=>'Missing','captured_at'=>'','user'=>0,'data'=>null);continue; }
            if (count($rows)!==1 || (int)$rows[0]['revision']!==1 || (int)$rows[0]['mapping_revision']!==0) { throw new RuntimeException('Posting observation revisions'); }
            $bytes+=strlen($rows[0]['payload']);if ($bytes>5000000) { throw new RuntimeException('Posting observation report size'); }
            $data=self::decode($rows[0],$entity,$id);
            if ($data['captured_at']!==$rows[0]['captured_at'] || (int)$data['user']!==(int)$rows[0]['fk_user']) { throw new RuntimeException('Posting observation envelope'); }
            if ($data['basis']['posting']['doc_type']!==$documentType) { throw new RuntimeException('Observation source type'); }
            $current=self::source($db,$entity,$id);
            if ($current && $current['posting']['doc_type']!==$documentType) { $current=null; }
            $status=$current===null ? 'Removed' : ($data['basis']===$current ? 'Same' : 'Changed');
            if (!$data['basis']['source_complete']) { $status='Incomplete'; }
            $result[]=array('id'=>$id,'status'=>$status,'captured_at'=>$data['captured_at'],'user'=>$data['user'],'data'=>$data);
        }
        return $result;
    }
}
