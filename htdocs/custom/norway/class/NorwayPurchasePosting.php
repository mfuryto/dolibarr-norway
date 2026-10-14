<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require_once __DIR__.'/NorwayPostingEvidence.php';
require_once __DIR__.'/NorwayPeriodSetup.php';
/** Confirmed first posting only, using the native ledger API and retained assessment. */
class NorwayPurchasePosting
{
    const TYPE='supplier_posting';
    const CORRECTION='supplier_correction';
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
            if ($markers && self::$active!==array($entity,$id)) { throw new RuntimeException('AlreadyPosted'); }
        }
        $piece=(int)($object->piece_num ?? 0);$own=self::$active===array($entity,$id) ? ' AND b.fk_doc<>'.$id : '';
        if ($piece>0) {
            $conflicts=self::rows($db,"SELECT b.rowid FROM ".$p."accounting_bookkeeping b INNER JOIN ".$p."norway_tax_evidence m ON m.entity=b.entity AND m.fk_document=b.fk_doc AND m.document_type='".self::TYPE."' WHERE b.entity=".$entity." AND b.doc_type='supplier_invoice' AND b.piece_num=".$piece.' AND b.rowid<>'.(int)$object->id.$own.' LIMIT 1 FOR UPDATE');
            if ($conflicts) { throw new RuntimeException('ProtectedVoucher'); }
        }
    }
    public static function assertAllocationPath($context,$entity,$id)
    {
        $choice=$context['assessment']['data']['choice'] ?? null;$bp=$choice['basis_points'] ?? null;
        if ((($choice['profile'] ?? null)==='purchase_lines' || ($bp!==null && $bp<10000)) && self::$active!==array($entity,$id)) { throw new RuntimeException('ConfirmedDeductionRequired'); }
    }
    public static function preview($db,$entity,$id,$journalId,$user,$lock=false)
    {
        return self::plan($db,$entity,$id,$journalId,$user,$lock,true);
    }
    private static function plan($db,$entity,$id,$journalId,$user,$lock,$first)
    {
        self::guard($entity,$id,$user);$p=$db->prefix();$suffix=$lock ? ' FOR UPDATE' : '';NorwaySchema::verify($db);
        $existing=self::rows($db,"SELECT rowid FROM ".$p."accounting_bookkeeping WHERE entity=".$entity." AND doc_type='supplier_invoice' AND fk_doc=".$id.' LIMIT 1'.$suffix);
        $markers=self::rows($db,"SELECT rowid FROM ".$p."norway_tax_evidence WHERE entity=".$entity." AND document_type='".self::TYPE."' AND fk_document=".$id.' LIMIT 1'.$suffix);
        $archived=self::rows($db,"SELECT rowid FROM ".$p."norway_tax_evidence WHERE entity=".$entity." AND document_type='bookkeeping_event' AND JSON_VALID(payload)=1 AND JSON_UNQUOTE(JSON_EXTRACT(payload,'$.basis.posting.doc_type'))='supplier_invoice' AND CAST(JSON_UNQUOTE(JSON_EXTRACT(payload,'$.basis.posting.fk_doc')) AS UNSIGNED)=".$id.' LIMIT 1'.$suffix);
        if ($first && ($existing || $markers || $archived)) { throw new InvalidArgumentException('AlreadyPosted'); }
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
        $accounts=array();$rows=array();$vat=array();$sum=0;
        $rule=$choice['profile']==='purchase_lines' ? null : NorwayPurchaseAllocation::profiles()[$choice['profile']];
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
            $rows[]=array('line'=>(int)$line['rowid'],'account'=>$bound['account_number'],'label'=>$bound['label'],'amount'=>$value);$vatCode=$validated['lines'][$i]['vat_account'] ?? $rule['vat'];$vat[$vatCode]=($vat[$vatCode] ?? 0)+NorwayInvoiceReconciliation::amount($validated['lines'][$i]['deductible']);$sum+=$value;
        }
        $supplier=$account('2400');ksort($vat);foreach ($vat as $code=>$amount) { $vatAccount=$account((string)$code);if ($amount!==0) { $rows[]=array('line'=>0,'account'=>(string)$code,'label'=>$vatAccount['label'],'amount'=>$amount);$sum+=$amount; } }
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
            self::$active=array($entity,$id);
            $ids=self::writeBatch($db,$entity,$id,$preview,$preview['rows'],$preview['basis']['invoice']['datef'],$piece,$ref,$user,'');
            $choice=$preview['assessment']['data']['choice'];
            $report=NorwayPurchaseAllocation::evaluate(self::observations($db,$entity,$ids,$preview['assessment']),NorwayInvoiceLedger::report($db,$entity,$id,'supplier_invoice'),$choice['profile'],$choice['percentage'],null,$choice['profile']==='purchase_lines' ? $choice : null);
            if ($report['status']!=='Match') { throw new RuntimeException('Verification'); }
            $data=array('version'=>1,'kind'=>self::TYPE,'entity'=>$entity,'document_id'=>$id,'revision'=>1,'at'=>gmdate('Y-m-d H:i:s'),'user'=>(int)$user->id,'preview'=>$preview,'postings'=>$ids,'piece'=>$piece,'ref'=>$ref);
            $json=self::encode($data);$hash=hash('sha256',$json);
            if (!$db->query("INSERT INTO ".$db->prefix()."norway_tax_evidence (entity,document_type,fk_document,revision,captured_at,fk_user,mapping_revision,payload,payload_sha256) VALUES (".$entity.",'".self::TYPE."',".$id.",1,'".$data['at']."',".(int)$user->id.",0,'".$db->escape($json)."','".$hash."')") ) { throw new RuntimeException('Storage'); }
            $stored=self::rows($db,"SELECT payload,payload_sha256 FROM ".$db->prefix()."norway_tax_evidence WHERE entity=".$entity." AND document_type='".self::TYPE."' AND fk_document=".$id.' AND revision=1');
            if (count($stored)!==1 || $stored[0]['payload']!==$json || $stored[0]['payload_sha256']!==$hash || $db->commit()<=0) { throw new RuntimeException('Storage'); }
            return $piece;
        } catch (Throwable $error) { $db->rollback();throw $error; } finally { self::$active=null; }
    }
    private static function writeBatch($db,$entity,$id,$plan,$rows,$date,$piece,$ref,$user,$label)
    {
        $ids=array();
        foreach ($rows as $row) {
            $o=new BookKeeping($db);$o->entity=$entity;$o->doc_date=$db->jdate($date);$due=$row['date_lim_reglement'] ?? ($plan['invoice_state'][0]['date_lim_reglement'] ?? null);$o->date_lim_reglement=empty($due) ? null : $db->jdate($due);$o->doc_ref=$plan['basis']['invoice']['ref'];$o->doc_type='supplier_invoice';$o->fk_doc=$id;$o->fk_docdet=$row['line'];
            $o->thirdparty_code=$row['thirdparty_code'] ?? $plan['party']['code_fournisseur'];$o->subledger_account=$row['subledger_account'] ?? ($row['account']==='2400' ? $plan['party']['code_compta_fournisseur'] : '');$o->subledger_label=$row['subledger_label'] ?? ($row['account']==='2400' ? $plan['party']['nom'] : '');
            $o->numero_compte=$row['account'];$o->label_compte=$row['label'];$o->label_operation=$label==='' ? $plan['basis']['invoice']['ref_supplier'] : $label;$o->debit=$row['debit'];$o->credit=$row['credit'];$o->montant=$row['debit']!=='0.00' ? $row['debit'] : '-'.$row['credit'];$o->sens=$row['debit']!=='0.00' ? 'D' : 'C';$o->code_journal=$plan['journal']['code'];$o->journal_label=$plan['journal']['label'];$o->piece_num=$piece;$o->ref=$ref;
            if ($o->createStd($user)<=0) { throw new RuntimeException('Posting'); }$ids[]=(int)$o->id;
        }
        return $ids;
    }
    private static function record($row,$entity,$id,$type,$revision)
    {
        if (strlen($row['payload'])>1000000 || !hash_equals($row['payload_sha256'],hash('sha256',$row['payload']))) { throw new RuntimeException('CorrectionIntegrity'); }
        $data=json_decode($row['payload'],true,512,JSON_THROW_ON_ERROR);
        if (($data['version'] ?? null)!==1 || ($data['kind'] ?? null)!==$type || ($data['entity'] ?? null)!==$entity || ($data['document_id'] ?? null)!==$id
            || ($data['revision'] ?? null)!==$revision || (int)$row['revision']!==$revision || ($data['at'] ?? null)!==$row['captured_at'] || ($data['user'] ?? null)!==(int)$row['fk_user'] || (int)$row['mapping_revision']!==0) { throw new RuntimeException('CorrectionIntegrity'); }
        $preview=$data['preview'] ?? null;
        if (!is_array($preview) || !is_string($preview['fingerprint'] ?? null)) { throw new RuntimeException('CorrectionIntegrity'); }
        $fingerprint=$preview['fingerprint'];unset($preview['fingerprint']);
        if (!hash_equals($fingerprint,hash('sha256',self::encode($preview)))) { throw new RuntimeException('CorrectionIntegrity'); }
        return $data;
    }
    /** Verify every retained batch and exact reversal before selecting effective postings. */
    private static function trail($db,$entity,$id,$user,$observations=null)
    {
        $p=$db->prefix();$sql="SELECT revision,captured_at,fk_user,mapping_revision,payload,payload_sha256 FROM ".$p."norway_tax_evidence WHERE entity=".$entity." AND fk_document=".$id;
        $roots=self::rows($db,$sql." AND document_type='".self::TYPE."' ORDER BY revision LIMIT 2");
        if (count($roots)!==1) { throw new InvalidArgumentException('CorrectionOrigin'); }
        $root=self::record($roots[0],$entity,$id,self::TYPE,1);$hashes=array($roots[0]['payload_sha256']);$bytes=strlen($roots[0]['payload']);
        $records=self::rows($db,$sql." AND document_type='".self::CORRECTION."' ORDER BY revision LIMIT 21");
        if (count($records)>20) { throw new RuntimeException('Capacity'); }
        $actual=self::rows($db,'SELECT rowid,entity,doc_type,fk_doc,fk_docdet,doc_ref,doc_date,code_journal,piece_num,ref,numero_compte,label_compte,label_operation,subledger_account,subledger_label,thirdparty_code,date_lim_reglement,debit,credit,date_export,date_validated FROM '.$p."accounting_bookkeeping WHERE entity=".$entity." AND doc_type='supplier_invoice' AND fk_doc=".$id.' ORDER BY rowid LIMIT 201');
        if (count($actual)>200) { throw new RuntimeException('Capacity'); }$byId=array();$finalized=false;foreach ($actual as $row) { $finalized=$finalized || !empty($row['date_export']) || !empty($row['date_validated']);unset($row['date_export'],$row['date_validated']);$byId[(int)$row['rowid']]=$row; }
        if ($observations===null) {
            $active=self::rows($db,'SELECT @@in_transaction AS active');
            $observations=(int)$active[0]['active']===1 ? NorwayPostingEvidence::transactionReport($db,$entity,$id,$user) : NorwayPostingEvidence::report($db,$entity,$id,$user,null,'supplier_invoice');
        }
        if (count($observations)!==count($actual)) { throw new RuntimeException('CorrectionIntegrity'); }
        foreach ($observations as $entry) { if ($entry['status']!=='Same' || !$entry['data'] || ($entry['data']['basis']['source_complete'] ?? null)!==true) { throw new RuntimeException('CorrectionIntegrity'); } }
        $seen=array();
        $verify=static function($ids,$rows,$date,$journal,$piece,$ref) use (&$seen,$byId,$db,$entity,$id,$p,$root) {
            if (!is_array($ids) || !is_array($rows) || count($ids)!==count($rows) || !$ids || !NorwayPeriodSetup::validDate($date) || !is_int($piece) || $piece<=0) { throw new RuntimeException('CorrectionIntegrity'); }
            $out=array();foreach ($ids as $i=>$key) {
                if (!is_int($key) || isset($seen[$key]) || !isset($byId[$key])) { throw new RuntimeException('CorrectionIntegrity'); }$r=$byId[$key];$wanted=$rows[$i];
                if ((int)$r['fk_docdet']!==$wanted['line'] || $r['numero_compte']!==$wanted['account'] || $r['label_compte']!==$wanted['label'] || substr($r['doc_date'],0,10)!==$date
                    || $r['code_journal']!==$journal || (int)$r['piece_num']!==$piece || $r['ref']!==$ref || $r['doc_ref']!==$root['preview']['basis']['invoice']['ref']
                    || NorwayInvoiceReconciliation::amount($r['debit'])!==NorwayInvoiceReconciliation::amount($wanted['debit']) || NorwayInvoiceReconciliation::amount($r['credit'])!==NorwayInvoiceReconciliation::amount($wanted['credit'])) { throw new RuntimeException('CorrectionIntegrity'); }
                $seen[$key]=true;$out[]=$r;
            }
            $extra=self::rows($db,'SELECT rowid FROM '.$p.'accounting_bookkeeping WHERE entity='.$entity.' AND piece_num='.$piece." AND (doc_type<>'supplier_invoice' OR doc_type IS NULL OR fk_doc<>".$id.' OR fk_doc IS NULL) LIMIT 1');
            if ($extra) { throw new RuntimeException('CorrectionIntegrity'); }return $out;
        };
        $plan=$root['preview'];$activeIds=$root['postings'];$date=$plan['basis']['invoice']['datef'];
        $activeRows=$verify($activeIds,$plan['rows'],$date,$plan['journal']['code'],$root['piece'],$root['ref']);$assessment=$plan['assessment'];$previousPiece=$root['piece'];
        foreach ($records as $index=>$row) {
            $bytes+=strlen($row['payload']);if ($bytes>5000000) { throw new RuntimeException('Capacity'); }
            $data=self::record($row,$entity,$id,self::CORRECTION,$index+1);$preview=$data['preview'];
            if (($preview['trail']['hashes'] ?? null)!==$hashes || ($preview['trail']['active_ids'] ?? null)!==$activeIds || ($preview['trail']['active_rows'] ?? null)!==$activeRows
                || ($preview['unreported'] ?? null)!==true || ($preview['plan']['basis'] ?? null)!==$root['preview']['basis'] || ($preview['plan']['assessment']['revision'] ?? 0)<=$assessment['revision']) { throw new RuntimeException('CorrectionIntegrity'); }
            $reverse=self::reversalRows($activeRows);
            if ($reverse!==$preview['reversals']) { throw new RuntimeException('CorrectionIntegrity'); }
            $verify($data['reversal_ids'],$reverse,$preview['date'],$preview['plan']['journal']['code'],$data['reversal_piece'],$data['reversal_ref']);
            $activeIds=$data['replacement_ids'];$activeRows=$verify($activeIds,$preview['plan']['rows'],$preview['date'],$preview['plan']['journal']['code'],$data['replacement_piece'],$data['replacement_ref']);
            $assessment=$preview['plan']['assessment'];$date=$preview['date'];$previousPiece=$data['replacement_piece'];$hashes[]=$row['payload_sha256'];
        }
        if (count($seen)!==count($actual)) { throw new RuntimeException('CorrectionIntegrity'); }
        return array('hashes'=>$hashes,'revision'=>count($records),'active_ids'=>$activeIds,'active_rows'=>$activeRows,'active_date'=>$date,'assessment_revision'=>$assessment['revision'],'basis_points'=>$assessment['data']['choice']['basis_points'],'deduction_vector'=>self::vector($assessment),'basis'=>$root['preview']['basis'],'piece'=>$previousPiece,'count'=>count($actual),'finalized'=>$finalized);
    }
    private static function vector($assessment)
    {
        $v=array();foreach ($assessment['data']['lines'] as $line) { $v[$line['line']]=$line['basis_points']; }ksort($v);return $v;
    }
    private static function reversalRows($rows)
    {
        $out=array();foreach ($rows as $r) {
            $out[]=array('line'=>(int)$r['fk_docdet'],'account'=>$r['numero_compte'],'label'=>$r['label_compte'],'debit'=>self::money(NorwayInvoiceReconciliation::amount($r['credit'])),'credit'=>self::money(NorwayInvoiceReconciliation::amount($r['debit'])),
                'thirdparty_code'=>$r['thirdparty_code'],'subledger_account'=>$r['subledger_account'],'subledger_label'=>$r['subledger_label'],'date_lim_reglement'=>$r['date_lim_reglement']);
        }return $out;
    }
    public static function effective($db,$entity,$id,$user,$observations,$ledger)
    {
        $r=self::rows($db,"SELECT rowid FROM ".$db->prefix()."norway_tax_evidence WHERE entity=".$entity." AND document_type='".self::CORRECTION."' AND fk_document=".$id.' LIMIT 1');
        if (!$r) { return array('observations'=>$observations,'ledger'=>$ledger,'date'=>null,'revision'=>0); }
        $trail=self::trail($db,$entity,$id,$user,$observations);$ids=array_fill_keys($trail['active_ids'],true);
        return array('observations'=>array_values(array_filter($observations,static fn($r)=>isset($ids[$r['id']]))),'ledger'=>array('truncated'=>false,'rows'=>array_values(array_filter($ledger['rows'],static fn($r)=>isset($ids[(int)$r->rowid])))),'date'=>$trail['active_date'],'revision'=>$trail['revision']);
    }
    public static function previewCorrection($db,$entity,$id,$journalId,$date,$reason,$user)
    {
        self::guard($entity,$id,$user);
        if (!is_string($reason) || trim($reason)!==$reason || strlen($reason)<3 || strlen($reason)>2000 || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/',$reason)) { throw new InvalidArgumentException('CorrectionReason'); }
        $trail=self::trail($db,$entity,$id,$user);if ($trail['finalized']) { throw new InvalidArgumentException('CorrectionFinalized'); }if ($trail['revision']>=20) { throw new RuntimeException('Capacity'); }
        $plan=self::plan($db,$entity,$id,$journalId,$user,false,false);
        if ($plan['basis']!==$trail['basis'] || $plan['assessment']['revision']<=$trail['assessment_revision'] || self::vector($plan['assessment'])===$trail['deduction_vector']) { throw new InvalidArgumentException('CorrectionAssessment'); }
        if (!NorwayPeriodSetup::validDate($date) || substr($date,0,7)!==substr($plan['basis']['invoice']['datef'],0,7) || $date<$trail['active_date']) { throw new InvalidArgumentException('CorrectionDate'); }
        $period=NorwayPeriodSetup::report($db,$entity,$date,0,'');if ($period['coverage']!=='Open') { throw new InvalidArgumentException('Period'); }
        $reversals=self::reversalRows($trail['active_rows']);
        if ($trail['count']+count($reversals)+count($plan['rows'])>200) { throw new RuntimeException('Capacity'); }
        $preview=array('entity'=>$entity,'id'=>$id,'trail'=>$trail,'plan'=>$plan,'reversals'=>$reversals,'date'=>$date,'reason'=>$reason,'period'=>$period,'unreported'=>true);
        return $preview+array('fingerprint'=>hash('sha256',self::encode($preview)));
    }
    public static function applyCorrection($db,$entity,$id,$journalId,$date,$reason,$fingerprint,$user,$unreported=false)
    {
        self::guard($entity,$id,$user);if ($unreported!==true) { throw new InvalidArgumentException('CorrectionUnreported'); }
        $active=self::rows($db,'SELECT @@in_transaction AS active');
        if ((int)$active[0]['active']!==0 || !$db->query('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE') || $db->begin()<=0) { throw new RuntimeException('Transaction'); }
        try {
            NorwayPurchaseDeduction::lockForPosting($db,$entity,$id);
            self::rows($db,'SELECT piece_num FROM '.$db->prefix().'accounting_bookkeeping WHERE entity='.$entity.' ORDER BY piece_num DESC LIMIT 1 FOR UPDATE');
            $preview=self::previewCorrection($db,$entity,$id,$journalId,$date,$reason,$user);
            if (!is_string($fingerprint) || !hash_equals($preview['fingerprint'],$fingerprint)) { throw new InvalidArgumentException('Changed'); }
            require_once DOL_DOCUMENT_ROOT.'/accountancy/class/bookkeeping.class.php';self::$active=array($entity,$id);$numbering=new BookKeeping($db);$numbering->doc_date=$db->jdate($date);
            unset($GLOBALS['conf']->cache['closed_fiscal_period_cached'],$GLOBALS['conf']->cache['active_fiscal_period_cached']);
            $reversePiece=(int)$numbering->getNextNumMvt();$reverseRef=$numbering->getNextNumRef();
            if ($reversePiece<=0 || trim($reverseRef)==='') { throw new RuntimeException('Numbering'); }
            $GLOBALS['langs']->load('norway@norway');
            $reversalIds=self::writeBatch($db,$entity,$id,$preview['plan'],$preview['reversals'],$date,$reversePiece,$reverseRef,$user,dol_trunc($GLOBALS['langs']->transnoentities('NorwayPurchasePostingCorrectionReversal').' '.$preview['trail']['piece'].' / '.$reason,200));
            $replacementPiece=(int)$numbering->getNextNumMvt();$replacementRef=$numbering->getNextNumRef();
            if ($replacementPiece<=$reversePiece || trim($replacementRef)==='') { throw new RuntimeException('Numbering'); }
            $replacementIds=self::writeBatch($db,$entity,$id,$preview['plan'],$preview['plan']['rows'],$date,$replacementPiece,$replacementRef,$user,dol_trunc($GLOBALS['langs']->transnoentities('NorwayPurchasePostingCorrectionReplacement').' '.$preview['trail']['piece'].' / '.$reason,200));
            $revision=$preview['trail']['revision']+1;$at=gmdate('Y-m-d H:i:s');
            $data=array('version'=>1,'kind'=>self::CORRECTION,'entity'=>$entity,'document_id'=>$id,'revision'=>$revision,'at'=>$at,'user'=>(int)$user->id,'preview'=>$preview,'reversal_ids'=>$reversalIds,'replacement_ids'=>$replacementIds,'reversal_piece'=>$reversePiece,'replacement_piece'=>$replacementPiece,'reversal_ref'=>$reverseRef,'replacement_ref'=>$replacementRef);
            $json=self::encode($data);$hash=hash('sha256',$json);
            if (!$db->query("INSERT INTO ".$db->prefix()."norway_tax_evidence (entity,document_type,fk_document,revision,captured_at,fk_user,mapping_revision,payload,payload_sha256) VALUES (".$entity.",'".self::CORRECTION."',".$id.','.$revision.",'".$at."',".(int)$user->id.",0,'".$db->escape($json)."','".$hash."')")) { throw new RuntimeException('Storage'); }
            $entries=NorwayPostingEvidence::transactionReport($db,$entity,$id,$user);$ledger=NorwayInvoiceLedger::report($db,$entity,$id,'supplier_invoice');$effective=self::effective($db,$entity,$id,$user,$entries,$ledger);$choice=$preview['plan']['assessment']['data']['choice'];
            if (NorwayPurchaseAllocation::evaluate($effective['observations'],$effective['ledger'],$choice['profile'],$choice['percentage'],$date,$choice['profile']==='purchase_lines' ? $choice : null)['status']!=='Match') { throw new RuntimeException('Verification'); }
            if ($db->commit()<=0) { throw new RuntimeException('Storage'); }return $revision;
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
