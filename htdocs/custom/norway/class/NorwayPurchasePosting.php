<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require_once __DIR__.'/NorwayPostingEvidence.php';
require_once __DIR__.'/NorwayPeriodSetup.php';
/** Confirmed first posting only, using the native ledger API and retained assessment. */
class NorwayPurchasePosting
{
    const TYPE='supplier_posting';
    private static $active=null;
    private static function guard($entity,$id,$user)
    {
        if (!is_int($entity) || $entity<=0 || $entity!==(int)$GLOBALS['conf']->entity || !is_int($id) || $id<=0 || empty($user->admin) || (int)$user->id<=0
            || !isModEnabled('norway') || !isModEnabled('accounting') || !$user->hasRight('fournisseur','facture','lire')
            || !$user->hasRight('accounting','mouvements','lire') || !$user->hasRight('accounting','bind','write')) { throw new InvalidArgumentException('Access'); }
    }
    private static function rows($db,$sql)
    {
        $r=$db->query($sql);if (!$r) { throw new RuntimeException('Storage'); }$out=array();while ($row=$db->fetch_object($r)) { $out[]=(array)$row; }return $out;
    }
    private static function encode($data)
    {
        $json=json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        if (strlen($json)>1000000) { throw new RuntimeException('Capacity'); }return $json;
    }
    /** A protected first-posting record also prevents an unsafe native re-transfer. */
    public static function guardNative($db,$entity,$object)
    {
        $p=$db->prefix();$id=(int)($object->fk_doc ?? 0);
        if (($object->doc_type ?? '')==='supplier_invoice' && $id>0) {
            $markers=self::rows($db,"SELECT rowid FROM ".$p."norway_tax_evidence WHERE entity=".$entity." AND document_type='".self::TYPE."' AND fk_document=".$id.' LIMIT 1 FOR UPDATE');
            if ($markers) { throw new RuntimeException('AlreadyPosted'); }
        }
        $piece=(int)($object->piece_num ?? 0);
        if ($piece>0) {
            $conflicts=self::rows($db,"SELECT b.rowid FROM ".$p."accounting_bookkeeping b INNER JOIN ".$p."norway_tax_evidence m ON m.entity=b.entity AND m.fk_document=b.fk_doc AND m.document_type='".self::TYPE."' WHERE b.entity=".$entity." AND b.doc_type='supplier_invoice' AND b.piece_num=".$piece.' AND b.rowid<>'.(int)$object->id.' LIMIT 1 FOR UPDATE');
            if ($conflicts) { throw new RuntimeException('ProtectedVoucher'); }
        }
    }
    public static function assertAllocationPath($context,$entity,$id)
    {
        $bp=$context['assessment']['data']['choice']['basis_points'] ?? null;
        if ($bp!==null && $bp<10000 && self::$active!==array($entity,$id)) { throw new RuntimeException('ConfirmedDeductionRequired'); }
    }
    public static function preview($db,$entity,$id,$journalId,$user,$lock=false)
    {
        self::guard($entity,$id,$user);$p=$db->prefix();$suffix=$lock ? ' FOR UPDATE' : '';NorwaySchema::verify($db);
        $existing=self::rows($db,"SELECT rowid FROM ".$p."accounting_bookkeeping WHERE entity=".$entity." AND doc_type='supplier_invoice' AND fk_doc=".$id.' LIMIT 1'.$suffix);
        $markers=self::rows($db,"SELECT rowid FROM ".$p."norway_tax_evidence WHERE entity=".$entity." AND document_type='".self::TYPE."' AND fk_document=".$id.' LIMIT 1'.$suffix);
        $archived=self::rows($db,"SELECT rowid FROM ".$p."norway_tax_evidence WHERE entity=".$entity." AND document_type='bookkeeping_event' AND JSON_VALID(payload)=1 AND JSON_UNQUOTE(JSON_EXTRACT(payload,'$.basis.posting.doc_type'))='supplier_invoice' AND CAST(JSON_UNQUOTE(JSON_EXTRACT(payload,'$.basis.posting.fk_doc')) AS UNSIGNED)=".$id.' LIMIT 1'.$suffix);
        if ($existing || $markers || $archived) { throw new InvalidArgumentException('AlreadyPosted'); }
        $history=NorwayPurchaseDeduction::history($db,$entity,$id,$user);if (!$history) { throw new InvalidArgumentException('Assessment'); }$assessment=end($history);
        $basis=NorwayPurchaseDeduction::basis($db,$entity,$id,$user,$lock);
        if ($basis!==$assessment['data']['basis']) { throw new InvalidArgumentException('AssessmentChanged'); }
        $state=self::rows($db,'SELECT rowid,close_code,date_lim_reglement FROM '.$p.'facture_fourn WHERE entity='.$entity.' AND rowid='.$id.$suffix);
        if (count($state)!==1 || trim((string)$state[0]['close_code'])!=='') { throw new InvalidArgumentException('Assessment'); }
        $sourceInvoice=array();
        if ((int)$basis['invoice']['type']===2) {
            $sourceInvoice=self::rows($db,'SELECT rowid,entity,type,fk_soc,multicurrency_code,close_code FROM '.$p.'facture_fourn WHERE entity='.$entity.' AND rowid='.(int)$basis['invoice']['fk_facture_source'].$suffix);
            if (count($sourceInvoice)!==1 || (int)$sourceInvoice[0]['type']!==0 || $sourceInvoice[0]['fk_soc']!==$basis['invoice']['fk_soc'] || $sourceInvoice[0]['multicurrency_code']!=='NOK' || trim((string)$sourceInvoice[0]['close_code'])!=='') { throw new InvalidArgumentException('Assessment'); }
        }
        $choice=$assessment['data']['choice'];
        // Reuse the validated per-line amounts and retain the exact saved revision.
        $validated=$assessment['data'];
        $date=$basis['invoice']['datef'];
        $period=NorwayPeriodSetup::report($db,$entity,$date,0,'');
        if ($period['coverage']!=='Open') { throw new InvalidArgumentException('Period'); }
        $journal=self::rows($db,'SELECT rowid,code,label,nature,active FROM '.$p.'accounting_journal WHERE entity='.$entity.' AND rowid='.(int)$journalId.$suffix);
        if (count($journal)!==1 || (int)$journal[0]['active']!==1 || (int)$journal[0]['nature']!==3 || trim($journal[0]['code'])==='') { throw new InvalidArgumentException('Journal'); }
        $settings=self::rows($db,"SELECT entity,name,value FROM ".$p."const WHERE entity IN (0,".$entity.") AND name IN ('CHARTOFACCOUNTS','ACCOUNTING_ACCOUNT_SUPPLIER','BOOKKEEPING_ADDON','ACCOUNTANCY_FISCAL_PERIOD_MODE') ORDER BY entity,name".$suffix);
        $effective=array();foreach ($settings as $row) { $effective[$row['name']]=$row['value']; }
        foreach (array('CHARTOFACCOUNTS','ACCOUNTING_ACCOUNT_SUPPLIER','BOOKKEEPING_ADDON','ACCOUNTANCY_FISCAL_PERIOD_MODE') as $name) { if ((string)getDolGlobalString($name)!==(string)($effective[$name] ?? '')) { throw new InvalidArgumentException('Accounts'); } }
        if (($effective['ACCOUNTING_ACCOUNT_SUPPLIER'] ?? '')!=='2400' || empty($effective['BOOKKEEPING_ADDON'])) { throw new InvalidArgumentException('Accounts'); }
        $chart=self::rows($db,'SELECT rowid,pcg_version,active FROM '.$p.'accounting_system WHERE rowid='.(int)($effective['CHARTOFACCOUNTS'] ?? 0).$suffix);
        if (count($chart)!==1 || (int)$chart[0]['active']!==1) { throw new InvalidArgumentException('Accounts'); }
        $party=self::rows($db,'SELECT rowid,code_fournisseur,code_compta_fournisseur,nom FROM '.$p.'societe WHERE entity='.$entity.' AND rowid='.(int)$basis['invoice']['fk_soc'].$suffix);
        if (count($party)!==1 || trim((string)$party[0]['code_compta_fournisseur'])==='') { throw new InvalidArgumentException('Party'); }
        $accounts=array();$rows=array();$vat=0;$sum=0;
        $rule=NorwayPurchaseAllocation::profiles()[$choice['profile']];
        $account=static function ($number,$boundId=0) use ($db,$p,$entity,$chart,$suffix,&$accounts) {
            $sql='SELECT rowid,account_number,label,active,fk_pcg_version FROM '.$p.'accounting_account WHERE entity='.$entity." AND fk_pcg_version='".$db->escape($chart[0]['pcg_version'])."' AND ".($boundId>0 ? 'rowid='.(int)$boundId : "account_number='".$db->escape($number)."'").$suffix;
            $r=self::rows($db,$sql);
            if (count($r)!==1 || (int)$r[0]['active']!==1 || !preg_match('/^[0-9]{4}$/D',$r[0]['account_number'])) { throw new InvalidArgumentException('Accounts'); }
            $accounts[$r[0]['account_number']]=$r[0];return $r[0];
        };
        foreach ($basis['lines'] as $i=>$line) {
            $bound=$account('',(int)$line['fk_code_ventilation']);
            if (!preg_match('/^[4-7][0-9]{3}$/D',$bound['account_number'])) { throw new InvalidArgumentException('Binding'); }
            $n=NorwayInvoiceReconciliation::amount($line['total_ht']);$v=NorwayInvoiceReconciliation::amount($validated['lines'][$i]['non_deductible']);$value=$n+$v;
            if ($value%1000000!==0) { throw new InvalidArgumentException('Precision'); }
            $rows[]=array('line'=>(int)$line['rowid'],'account'=>$bound['account_number'],'label'=>$bound['label'],'amount'=>$value);$vat+=NorwayInvoiceReconciliation::amount($validated['lines'][$i]['deductible']);$sum+=$value;
        }
        $supplier=$account('2400');$vatAccount=$account($rule['vat']);
        if ($vat!==0) { $rows[]=array('line'=>0,'account'=>$rule['vat'],'label'=>$vatAccount['label'],'amount'=>$vat);$sum+=$vat; }
        $gross=NorwayInvoiceReconciliation::amount($basis['invoice']['total_ttc']);
        if ($gross===0 || $gross%1000000!==0 || $sum!==$gross || count($rows)>198) { throw new InvalidArgumentException('Precision'); }
        $rows[]=array('line'=>0,'account'=>'2400','label'=>$supplier['label'],'amount'=>-$gross);
        foreach ($rows as &$row) { $row['debit']=self::money(max(0,$row['amount']));$row['credit']=self::money(max(0,-$row['amount']));unset($row['amount']); }unset($row);ksort($accounts);
        $data=array('entity'=>$entity,'id'=>$id,'assessment'=>$assessment,'basis'=>$basis,'invoice_state'=>$state,'source_invoice'=>$sourceInvoice,'journal'=>$journal[0],'period'=>$period,'settings'=>$settings,'accounts'=>$accounts,'party'=>$party[0],'rows'=>$rows);
        return $data+array('fingerprint'=>hash('sha256',self::encode($data)));
    }
    private static function money($amount)
    {
        if (!is_int($amount) || $amount%1000000!==0) { throw new InvalidArgumentException('Precision'); }
        return substr(NorwayInvoiceReconciliation::display($amount),0,-6);
    }
    public static function apply($db,$entity,$id,$journalId,$fingerprint,$user)
    {
        self::guard($entity,$id,$user);
        $active=self::rows($db,'SELECT @@in_transaction AS active');
        if ((int)$active[0]['active']!==0 || !$db->query('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE') || $db->begin()<=0) { throw new RuntimeException('Transaction'); }
        try {
            NorwayPurchaseDeduction::lockForPosting($db,$entity,$id);
            self::rows($db,'SELECT piece_num FROM '.$db->prefix().'accounting_bookkeeping WHERE entity='.$entity.' ORDER BY piece_num DESC LIMIT 1 FOR UPDATE');
            $preview=self::preview($db,$entity,$id,$journalId,$user,true);
            if (!is_string($fingerprint) || !hash_equals($preview['fingerprint'],$fingerprint)) { throw new InvalidArgumentException('Changed'); }
            require_once DOL_DOCUMENT_ROOT.'/accountancy/class/bookkeeping.class.php';
            $numbering=new BookKeeping($db);$numbering->doc_date=$db->jdate($preview['basis']['invoice']['datef']);$piece=(int)$numbering->getNextNumMvt();$ref=$numbering->getNextNumRef();
            if ($piece<=0 || !is_string($ref) || trim($ref)==='') { throw new RuntimeException('Numbering'); }
            unset($GLOBALS['conf']->cache['closed_fiscal_period_cached'],$GLOBALS['conf']->cache['active_fiscal_period_cached']);
            $ids=array();self::$active=array($entity,$id);
            foreach ($preview['rows'] as $row) {
                $o=new BookKeeping($db);$o->entity=$entity;$o->doc_date=$db->jdate($preview['basis']['invoice']['datef']);$o->date_lim_reglement=empty($preview['invoice_state'][0]['date_lim_reglement']) ? null : $db->jdate($preview['invoice_state'][0]['date_lim_reglement']);$o->doc_ref=$preview['basis']['invoice']['ref'];$o->doc_type='supplier_invoice';$o->fk_doc=$id;$o->fk_docdet=$row['line'];
                $o->thirdparty_code=$preview['party']['code_fournisseur'];$o->subledger_account=$row['account']==='2400' ? $preview['party']['code_compta_fournisseur'] : '';$o->subledger_label=$row['account']==='2400' ? $preview['party']['nom'] : '';
                $o->numero_compte=$row['account'];$o->label_compte=$row['label'];$o->label_operation=$preview['basis']['invoice']['ref_supplier'];$o->debit=$row['debit'];$o->credit=$row['credit'];$o->montant=$row['debit']!=='0.00' ? $row['debit'] : '-'.$row['credit'];$o->sens=$row['debit']!=='0.00' ? 'D' : 'C';$o->code_journal=$preview['journal']['code'];$o->journal_label=$preview['journal']['label'];$o->piece_num=$piece;$o->ref=$ref;
                if ($o->createStd($user)<=0) { throw new RuntimeException('Posting'); }$ids[]=(int)$o->id;
            }
            $choice=$preview['assessment']['data']['choice'];
            $report=NorwayPurchaseAllocation::evaluate(self::observations($db,$entity,$ids,$preview['assessment']),NorwayInvoiceLedger::report($db,$entity,$id,'supplier_invoice'),$choice['profile'],$choice['percentage']);
            if ($report['status']!=='Match') { throw new RuntimeException('Verification'); }
            $data=array('version'=>1,'kind'=>self::TYPE,'entity'=>$entity,'document_id'=>$id,'revision'=>1,'at'=>gmdate('Y-m-d H:i:s'),'user'=>(int)$user->id,'preview'=>$preview,'postings'=>$ids,'piece'=>$piece,'ref'=>$ref);
            $json=self::encode($data);$hash=hash('sha256',$json);
            if (!$db->query("INSERT INTO ".$db->prefix()."norway_tax_evidence (entity,document_type,fk_document,revision,captured_at,fk_user,mapping_revision,payload,payload_sha256) VALUES (".$entity.",'".self::TYPE."',".$id.",1,'".$data['at']."',".(int)$user->id.",0,'".$db->escape($json)."','".$hash."')") ) { throw new RuntimeException('Storage'); }
            $stored=self::rows($db,"SELECT payload,payload_sha256 FROM ".$db->prefix()."norway_tax_evidence WHERE entity=".$entity." AND document_type='".self::TYPE."' AND fk_document=".$id.' AND revision=1');
            if (count($stored)!==1 || $stored[0]['payload']!==$json || $stored[0]['payload_sha256']!==$hash || $db->commit()<=0) { throw new RuntimeException('Storage'); }
            return $piece;
        } catch (Throwable $error) { $db->rollback();throw $error; } finally { self::$active=null; }
    }
    private static function observations($db,$entity,$ids,$assessment)
    {
        $rows=self::rows($db,"SELECT payload,payload_sha256 FROM ".$db->prefix()."norway_tax_evidence WHERE entity=".$entity." AND document_type='bookkeeping_event' AND fk_document IN (".implode(',',$ids).') ORDER BY fk_document');$out=array();
        if (count($rows)!==count($ids)) { throw new RuntimeException('Verification'); }
        foreach ($rows as $row) { if (!hash_equals($row['payload_sha256'],hash('sha256',$row['payload']))) { throw new RuntimeException('Verification'); }$data=json_decode($row['payload'],true,512,JSON_THROW_ON_ERROR);if (($data['deduction_context']['status'] ?? null)!=='Linked' || ($data['deduction_context']['assessment'] ?? null)!==$assessment) { throw new RuntimeException('Verification'); }$out[]=array('data'=>$data,'status'=>'Same'); }
        return $out;
    }
}
