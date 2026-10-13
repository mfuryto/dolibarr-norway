<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require_once __DIR__.'/NorwaySchema.php';
require_once __DIR__.'/NorwayPurchaseAllocation.php';
/** Append-only, explicit purchase choices. Does not establish entitlement or change postings. */
class NorwayPurchaseDeduction
{
    const TYPE='supplier_deduction';
    private static function guard($entity,$id,$user,$write=false)
    {
        if (!is_int($entity) || $entity<=0 || $entity!==(int)$GLOBALS['conf']->entity || !is_int($id) || $id<=0
            || empty($user->admin) || (int)$user->id<=0 || !isModEnabled('norway') || !isModEnabled('accounting')
            || !$user->hasRight('fournisseur','facture','lire') || !$user->hasRight('accounting','mouvements','lire')
            || ($write && !$user->hasRight('fournisseur','facture','creer'))) { throw new InvalidArgumentException('Purchase choice access'); }
    }
    private static function rows($db,$sql)
    {
        $r=$db->query($sql);if (!$r) { throw new RuntimeException('Purchase choice query'); }
        $rows=array();while ($row=$db->fetch_object($r)) { $rows[]=(array)$row; }return $rows;
    }
    private static function encode($data)
    {
        $json=json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        if (strlen($json)>1000000) { throw new RuntimeException('Purchase choice size'); }return $json;
    }
    public static function history($db,$entity,$id,$user)
    {
        self::guard($entity,$id,$user);NorwaySchema::verify($db);
        $rows=self::rows($db,"SELECT revision,captured_at,fk_user,mapping_revision,payload,payload_sha256 FROM ".$db->prefix()."norway_tax_evidence WHERE entity=".$entity." AND document_type='".self::TYPE."' AND fk_document=".$id.' ORDER BY revision LIMIT 101');
        if (count($rows)>100) { throw new RuntimeException('Purchase choice history size'); }$bytes=0;
        foreach ($rows as $index=>&$row) {
            $bytes+=strlen($row['payload']);
            if ($bytes>5000000 || !hash_equals($row['payload_sha256'],hash('sha256',$row['payload']))) { throw new RuntimeException('Purchase choice integrity'); }
            $data=json_decode($row['payload'],true,512,JSON_THROW_ON_ERROR);
            if (($data['version'] ?? null)!==1 || ($data['entity'] ?? null)!==$entity || ($data['document_id'] ?? null)!==$id
                || ($data['kind'] ?? null)!==self::TYPE || ($data['revision'] ?? null)!==$index+1 || (int)$row['revision']!==$index+1
                || ($data['at'] ?? null)!==$row['captured_at'] || ($data['user'] ?? null)!==(int)$row['fk_user'] || (int)$row['mapping_revision']!==0
                || !is_array($data['basis'] ?? null) || !is_array($data['choice'] ?? null)
                || (int)($data['basis']['invoice']['rowid'] ?? 0)!==$id || (int)($data['basis']['invoice']['entity'] ?? 0)!==$entity
                || self::choice($data['choice']['profile'] ?? '',$data['choice']['percentage'] ?? null,$data['choice']['reason'] ?? '')!==$data['choice']
                || self::lineChoices($data['basis'],$data['choice'])!==($data['lines'] ?? null)) { throw new RuntimeException('Purchase choice metadata'); }
            $row['data']=$data;unset($row['payload']);
        }unset($row);return $rows;
    }
    public static function choice($profile,$percentage,$reason)
    {
        $bp=NorwayPurchaseAllocation::deduction($profile,$percentage);
        if (!is_string($reason) || trim($reason)!==$reason || strlen($reason)<3 || strlen($reason)>2000 || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/',$reason)) { throw new InvalidArgumentException('Purchase choice reason'); }
        $mode=NorwayPurchaseAllocation::profiles()[$profile]['deduction'];
        return array('profile'=>$profile,'percentage'=>$mode==='partial' ? number_format($bp/100,2,'.','') : null,'basis_points'=>$bp,'reason'=>$reason);
    }
    public static function basis($db,$entity,$id,$user,$lock=false)
    {
        self::guard($entity,$id,$user);NorwaySchema::verify($db);$p=$db->prefix();$suffix=$lock ? ' FOR UPDATE' : '';
        $headers=self::rows($db,'SELECT rowid,entity,ref,ref_supplier,type,fk_statut,fk_soc,datef,fk_facture_source,multicurrency_code,multicurrency_tx,total_ht,total_tva,total_ttc,vat_reverse_charge,revenuestamp,localtax1,localtax2 FROM '.$p.'facture_fourn WHERE entity='.$entity.' AND rowid='.$id.$suffix);
        if (count($headers)!==1) { throw new InvalidArgumentException('Purchase choice invoice'); }$header=$headers[0];
        // Payment status is deliberately excluded: it does not change the deduction basis.
        $status=(int)$header['fk_statut'];unset($header['fk_statut']);
        if (!in_array((int)$header['type'],array(0,2),true) || !in_array($status,array(1,2),true) || $header['multicurrency_code']!=='NOK' || (float)$header['multicurrency_tx']!==1.0) { throw new InvalidArgumentException('Purchase choice scope'); }
        $lines=self::rows($db,'SELECT rowid,fk_facture_fourn,description,fk_product,product_type,qty,pu_ht,remise_percent,fk_code_ventilation,tva_tx,vat_src_code,info_bits,localtax1_tx,localtax2_tx,total_ht,tva AS total_tva,total_ttc,total_localtax1,total_localtax2 FROM '.$p.'facture_fourn_det WHERE fk_facture_fourn='.$id.' ORDER BY rowid LIMIT 201'.$suffix);
        $party=self::rows($db,'SELECT s.rowid,s.entity,s.fk_pays,c.code AS country FROM '.$p.'societe s LEFT JOIN '.$p.'c_country c ON c.rowid=s.fk_pays WHERE s.entity='.$entity.' AND s.rowid='.(int)$header['fk_soc'].$suffix);
        $settings=self::rows($db,"SELECT name,value,entity FROM ".$p."const WHERE entity IN (0,".$entity.") AND name IN ('MAIN_INFO_SIREN','MAIN_INFO_SOCIETE_COUNTRY','NORWAY_VAT_REGISTRATION_START') ORDER BY entity,name".$suffix);
        if (!$lines || count($lines)>200 || count($party)!==1 || $party[0]['country']!=='NO') { throw new InvalidArgumentException('Purchase choice source'); }
        $country='';foreach ($settings as $setting) { if ($setting['name']==='MAIN_INFO_SOCIETE_COUNTRY') { $parts=explode(':',$setting['value']);$country=$parts[1] ?? ''; } }
        if ($country!=='NO') { throw new InvalidArgumentException('Purchase choice buyer country'); }
        foreach (array('vat_reverse_charge','revenuestamp','localtax1','localtax2') as $key) { if ((float)$header[$key]!==0.0) { throw new InvalidArgumentException('Purchase choice taxes'); } }
        return array('invoice'=>$header,'lines'=>$lines,'party'=>$party[0],'settings'=>$settings);
    }
    private static function lineChoices($basis,$choice)
    {
        if (!is_array($basis['lines'] ?? null) || !$basis['lines'] || count($basis['lines'])>200) { throw new InvalidArgumentException('Purchase choice lines'); }
        $rate=NorwayPurchaseAllocation::profiles()[$choice['profile']]['rate'];$lineChoices=array();$net=0;$totalVat=0;$gross=0;
        $header=$basis['invoice'] ?? array();$sign=(int)($header['type'] ?? -1)===2 ? -1 : 1;$ids=array();
        foreach ($basis['lines'] as $line) {
            if ((float)$line['tva_tx']!== (float)$rate || (int)$line['info_bits']!==0 || (float)$line['localtax1_tx']!==0.0 || (float)$line['localtax2_tx']!==0.0 || (float)$line['total_localtax1']!==0.0 || (float)$line['total_localtax2']!==0.0) { throw new InvalidArgumentException('Purchase choice line scope'); }
            $id=(int)$line['rowid'];$n=NorwayInvoiceReconciliation::amount($line['total_ht']);$vat=NorwayInvoiceReconciliation::amount($line['total_tva']);$g=NorwayInvoiceReconciliation::amount($line['total_ttc']);
            if ($id<=0 || (int)$line['fk_facture_fourn']!==(int)$header['rowid'] || isset($ids[$id]) || $n*$sign<0 || $vat*$sign<0 || abs($n+$vat-$g)>1000000 || abs($vat*100-$n*$rate)>100000000) { throw new InvalidArgumentException('Purchase choice amounts'); }
            $ids[$id]=true;$net+=$n;$totalVat+=$vat;$gross+=$g;
            $deductible=NorwayPurchaseAllocation::deductibleVat($vat,$choice['basis_points']);
            $lineChoices[]=array('line'=>(int)$line['rowid'],'basis_points'=>$choice['basis_points'],'vat'=>NorwayInvoiceReconciliation::display($vat),'deductible'=>NorwayInvoiceReconciliation::display($deductible),'non_deductible'=>NorwayInvoiceReconciliation::display($vat-$deductible));
        }
        if (abs($net-NorwayInvoiceReconciliation::amount($header['total_ht']))>1000000 || abs($totalVat-NorwayInvoiceReconciliation::amount($header['total_tva']))>1000000 || abs($gross-NorwayInvoiceReconciliation::amount($header['total_ttc']))>1000000) { throw new InvalidArgumentException('Purchase choice totals'); }
        return $lineChoices;
    }
    public static function preview($db,$entity,$id,$user,$profile,$percentage,$reason,$lock=false)
    {
        self::guard($entity,$id,$user,true);$choice=self::choice($profile,$percentage,$reason);$basis=self::basis($db,$entity,$id,$user,$lock);
        $lineChoices=self::lineChoices($basis,$choice);
        $history=self::history($db,$entity,$id,$user);if (count($history)>=100) { throw new RuntimeException('Purchase choice history full'); }
        $data=array('basis'=>$basis,'choice'=>$choice,'lines'=>$lineChoices);
        return $data+array('revision'=>count($history)+1,'fingerprint'=>hash('sha256',self::encode(array($data,array_column($history,'payload_sha256')))));
    }
    public static function save($db,$entity,$id,$user,$profile,$percentage,$reason,$fingerprint)
    {
        self::guard($entity,$id,$user,true);$active=self::rows($db,'SELECT @@in_transaction AS active');
        if ((int)$active[0]['active']!==0 || !$db->query('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE') || $db->begin()<=0) { throw new RuntimeException('Purchase choice transaction'); }
        try {
            $preview=self::preview($db,$entity,$id,$user,$profile,$percentage,$reason,true);
            if (!is_string($fingerprint) || !hash_equals($preview['fingerprint'],$fingerprint)) { throw new RuntimeException('Purchase choice changed'); }
            $data=array('version'=>1,'kind'=>self::TYPE,'entity'=>$entity,'document_id'=>$id,'revision'=>$preview['revision'],'at'=>gmdate('Y-m-d H:i:s'),'user'=>(int)$user->id,'basis'=>$preview['basis'],'choice'=>$preview['choice'],'lines'=>$preview['lines']);
            $json=self::encode($data);$hash=hash('sha256',$json);
            if (!$db->query("INSERT INTO ".$db->prefix()."norway_tax_evidence (entity,document_type,fk_document,revision,captured_at,fk_user,mapping_revision,payload,payload_sha256) VALUES (".$entity.",'".self::TYPE."',".$id.','.$data['revision'].",'".$data['at']."',".$data['user'].",0,'".$db->escape($json)."','".$hash."')")) { throw new RuntimeException('Purchase choice write'); }
            $history=self::history($db,$entity,$id,$user);$last=end($history);if ($last['data']!==$data || $last['payload_sha256']!==$hash || $db->commit()<=0) { throw new RuntimeException('Purchase choice verification'); }
            return $data['revision'];
        } catch (Throwable $error) { $db->rollback();throw $error; }
    }
}
