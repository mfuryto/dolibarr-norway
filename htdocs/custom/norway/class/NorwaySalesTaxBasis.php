<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require_once __DIR__.'/NorwaySchema.php';
require_once __DIR__.'/NorwaySalesZeroVat.php';
/** Versioned documentary references; never tax approval or posting-time evidence. */
class NorwaySalesTaxBasis
{
    const TYPE='sales_tax_basis';
    private static function guard($entity,$id,$user,$write=false)
    {
        if (!is_int($entity) || $entity<=0 || $entity!==(int)$GLOBALS['conf']->entity || !is_int($id) || $id<=0
            || empty($user->admin) || !empty($user->socid) || (int)$user->id<=0 || !isModEnabled('norway') || !isModEnabled('accounting')
            || !$user->hasRight('facture','lire') || !$user->hasRight('accounting','mouvements','lire')
            || ($write && !$user->hasRight('facture','creer'))) { throw new InvalidArgumentException('Sales basis access'); }
    }
    private static function rows($db,$sql)
    {
        $r=$db->query($sql);if (!$r) { throw new RuntimeException('Sales basis query'); }
        $rows=array();while ($row=$db->fetch_object($r)) { $rows[]=(array)$row; }return $rows;
    }
    private static function encode($data)
    {
        $json=json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        if (strlen($json)>500000) { throw new RuntimeException('Sales basis size'); }return $json;
    }
    public static function basis($db,$entity,$id,$user,$lock=false)
    {
        self::guard($entity,$id,$user);NorwaySchema::verify($db);$p=$db->prefix();$suffix=$lock ? ' FOR UPDATE' : '';
        // Configuration first, then document/lines/party. Serializable save also locks absent keys.
        $settings=self::rows($db,"SELECT entity,name,value FROM ".$p."const WHERE entity=".$entity." AND name IN ('MAIN_INFO_SIREN','MAIN_INFO_SOCIETE_COUNTRY','NORWAY_TAX_MAPPINGS_V1','NORWAY_VAT_REGISTRATION_START','NORWAY_VAT_REGISTRATION_HISTORY_V1') ORDER BY name".$suffix);
        $headers=self::rows($db,'SELECT rowid,entity,type,fk_statut,fk_soc,datef,multicurrency_code,multicurrency_tx,total_ht,total_tva,total_ttc FROM '.$p.'facture WHERE entity='.$entity.' AND rowid='.$id.$suffix);
        if (count($headers)!==1) { throw new InvalidArgumentException('Sales basis invoice'); }$header=$headers[0];
        $status=(int)$header['fk_statut'];unset($header['fk_statut']);
        if ((int)$header['type']!==0 || !in_array($status,array(0,1,2),true) || $header['multicurrency_code']!=='NOK' || (float)$header['multicurrency_tx']!==1.0) { throw new InvalidArgumentException('Sales basis scope'); }
        $lines=self::rows($db,'SELECT rowid,fk_facture,description,fk_product,product_type,qty,subprice,remise_percent,tva_tx,vat_src_code,info_bits,localtax1_tx,localtax2_tx,total_ht,total_tva,total_ttc,total_localtax1,total_localtax2 FROM '.$p.'facturedet WHERE fk_facture='.$id.' ORDER BY rowid LIMIT 201'.$suffix);
        $party=self::rows($db,'SELECT s.rowid,s.entity,s.nom,s.siren,s.fk_pays,c.code AS country FROM '.$p.'societe s LEFT JOIN '.$p.'c_country c ON c.rowid=s.fk_pays WHERE s.entity='.$entity.' AND s.rowid='.(int)$header['fk_soc'].$suffix);
        if (!$lines || count($lines)>200 || count($party)!==1 || $party[0]['country']!=='NO') { throw new InvalidArgumentException('Sales basis domestic scope'); }
        $basis=array('source_complete'=>true,'posting'=>array('entity'=>$entity,'doc_type'=>'customer_invoice'),'invoice'=>$header,'lines'=>$lines,'party'=>$party[0],'settings'=>$settings);
        self::eligible($basis,$entity);self::encode($basis);return $basis;
    }
    public static function eligible($basis,$entity)
    {
        if ((int)($basis['invoice']['type'] ?? -1)!==0 || ($basis['party']['country'] ?? '')!=='NO') { throw new InvalidArgumentException('Sales basis scope'); }
        $report=NorwaySalesZeroVat::captured($basis,$entity);$eligible=array();
        if ($report['status']!=='Evaluated') { throw new InvalidArgumentException('Sales basis unsupported'); }
        foreach ($report['rows'] as $i=>$row) {
            $line=$basis['lines'][$i];
            foreach (array('total_localtax1','total_localtax2') as $key) {
                if (!preg_match('/^0(?:\.0+)?$/D',(string)($line[$key] ?? ''))) { throw new InvalidArgumentException('Sales basis special taxes'); }
            }
            if ($row['status']==='NotApplicable') { continue; }
            if ($row['status']!=='Stated') { throw new InvalidArgumentException('Sales basis explicit domestic mapping required'); }
            $eligible[$row['line']]=$row['target'];
        }
        if (!$eligible) { throw new InvalidArgumentException('Sales basis no zero rate lines'); }return $eligible;
    }
    public static function choices($basis,$entity,$inputs)
    {
        $eligible=self::eligible($basis,$entity);
        if (!is_array($inputs) || count($inputs)!==count($eligible) || array_keys($inputs)!==range(0,count($inputs)-1)) { throw new InvalidArgumentException('Sales basis coverage'); }
        $out=array();foreach ($inputs as $input) {
            if (!is_array($input) || array_keys($input)!==array('line','target','reason','reference') || !is_int($input['line']) || isset($out[$input['line']])
                || ($eligible[$input['line']] ?? null)!==$input['target']) { throw new InvalidArgumentException('Sales basis line identity'); }
            foreach (array('reason'=>array(10,2000),'reference'=>array(3,500)) as $key=>$limits) {
                $v=$input[$key];if (!is_string($v) || trim($v)!==$v || !preg_match('//u',$v) || mb_strlen($v,'UTF-8')<$limits[0] || mb_strlen($v,'UTF-8')>$limits[1]
                    || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/',$v)) { throw new InvalidArgumentException('Sales basis text'); }
            }
            $out[$input['line']]=$input;
        }ksort($out,SORT_NUMERIC);return array_values($out);
    }
    public static function history($db,$entity,$id,$user,$lock=false)
    {
        self::guard($entity,$id,$user);NorwaySchema::verify($db);
        $rows=self::rows($db,"SELECT revision,captured_at,fk_user,mapping_revision,payload,payload_sha256 FROM ".$db->prefix()."norway_tax_evidence WHERE entity=".$entity." AND document_type='".self::TYPE."' AND fk_document=".$id.' ORDER BY revision LIMIT 101'.($lock ? ' FOR UPDATE' : ''));
        if (count($rows)>100) { throw new RuntimeException('Sales basis capacity'); }$bytes=0;
        foreach ($rows as $i=>&$row) {
            $bytes+=strlen($row['payload']);if ($bytes>5000000 || (int)$row['revision']!==$i+1 || !hash_equals($row['payload_sha256'],hash('sha256',$row['payload']))) { throw new RuntimeException('Sales basis integrity'); }
            $data=json_decode($row['payload'],true,512,JSON_THROW_ON_ERROR);self::encode($data);
            if (($data['version'] ?? null)!==1 || ($data['kind'] ?? null)!==self::TYPE || ($data['entity'] ?? null)!==$entity || ($data['document_id'] ?? null)!==$id
                || ($data['revision'] ?? null)!==$i+1 || ($data['at'] ?? null)!==$row['captured_at'] || ($data['user'] ?? null)!==(int)$row['fk_user'] || $data['user']<=0
                || (int)$row['mapping_revision']!==0 || (int)($data['basis']['invoice']['rowid'] ?? 0)!==$id
                || self::choices($data['basis'],$entity,$data['choices'])!==$data['choices']) { throw new RuntimeException('Sales basis metadata'); }
            $row['data']=$data;unset($row['payload']);
        }unset($row);return $rows;
    }
    public static function preview($db,$entity,$id,$user,$inputs,$lock=false)
    {
        self::guard($entity,$id,$user,true);$basis=self::basis($db,$entity,$id,$user,$lock);$choices=self::choices($basis,$entity,$inputs);
        $history=self::history($db,$entity,$id,$user,$lock);if (count($history)>=100) { throw new RuntimeException('Sales basis capacity'); }
        $data=array('basis'=>$basis,'choices'=>$choices,'revision'=>count($history)+1);
        return $data+array('fingerprint'=>hash('sha256',self::encode(array($data,array_column($history,'payload_sha256')))));
    }
    public static function save($db,$entity,$id,$user,$inputs,$fingerprint)
    {
        self::guard($entity,$id,$user,true);$active=self::rows($db,'SELECT @@in_transaction AS active');
        if ((int)$active[0]['active']!==0 || !$db->query('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE') || $db->begin()<=0) { throw new RuntimeException('Sales basis transaction'); }
        try {
            $preview=self::preview($db,$entity,$id,$user,$inputs,true);
            if (!is_string($fingerprint) || !hash_equals($preview['fingerprint'],$fingerprint)) { throw new RuntimeException('Sales basis changed'); }
            $data=array('version'=>1,'kind'=>self::TYPE,'entity'=>$entity,'document_id'=>$id,'revision'=>$preview['revision'],'at'=>gmdate('Y-m-d H:i:s'),'user'=>(int)$user->id,'basis'=>$preview['basis'],'choices'=>$preview['choices']);
            $json=self::encode($data);$hash=hash('sha256',$json);
            if (!$db->query("INSERT INTO ".$db->prefix()."norway_tax_evidence (entity,document_type,fk_document,revision,captured_at,fk_user,mapping_revision,payload,payload_sha256) VALUES (".$entity.",'".self::TYPE."',".$id.','.$data['revision'].",'".$data['at']."',".$data['user'].",0,'".$db->escape($json)."','".$hash."')")) { throw new RuntimeException('Sales basis write'); }
            $history=self::history($db,$entity,$id,$user,true);$last=end($history);if ($last['data']!==$data || $db->commit()<=0) { throw new RuntimeException('Sales basis verification'); }
            return $data['revision'];
        } catch (Throwable $error) { $db->rollback();throw $error; }
    }
}
