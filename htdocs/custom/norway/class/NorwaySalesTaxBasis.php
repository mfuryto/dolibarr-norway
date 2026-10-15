<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require_once __DIR__.'/NorwaySchema.php';
require_once __DIR__.'/NorwaySalesZeroVat.php';
/** Versioned documentary references; never tax approval. */
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
        self::guard($entity,$id,$user);return self::readBasis($db,$entity,$id,$lock);
    }
    private static function readBasis($db,$entity,$id,$lock=false)
    {
        NorwaySchema::verify($db);$p=$db->prefix();$suffix=$lock ? ' FOR UPDATE' : '';
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
        self::guard($entity,$id,$user);return self::retained($db,$entity,$id,$lock);
    }
    private static function retained($db,$entity,$id,$lock=false)
    {
        NorwaySchema::verify($db);
        $rows=self::rows($db,"SELECT revision,captured_at,fk_user,mapping_revision,payload,payload_sha256 FROM ".$db->prefix()."norway_tax_evidence WHERE entity=".$entity." AND document_type='".self::TYPE."' AND fk_document=".$id.' ORDER BY revision LIMIT 101'.($lock ? ' FOR UPDATE' : ''));
        if (count($rows)>100) { throw new RuntimeException('Sales basis capacity'); }$bytes=0;
        foreach ($rows as $i=>&$row) {
            $bytes+=strlen($row['payload']);if ($bytes>5000000 || (int)$row['revision']!==$i+1 || !hash_equals($row['payload_sha256'],hash('sha256',$row['payload']))) { throw new RuntimeException('Sales basis integrity'); }
            $data=json_decode($row['payload'],true,512,JSON_THROW_ON_ERROR);
            $entry=$row;$entry['data']=$data;unset($entry['payload']);self::validateEntry($entry,$entity,$id);
            $row['data']=$data;unset($row['payload']);
        }unset($row);return $rows;
    }
    private static function validateEntry($entry,$entity,$id)
    {
        $data=$entry['data'] ?? null;
        if (!is_array($data) || !is_string($entry['payload_sha256'] ?? null) || !hash_equals($entry['payload_sha256'],hash('sha256',self::encode($data)))
            || ($data['version'] ?? null)!==1 || ($data['kind'] ?? null)!==self::TYPE || ($data['entity'] ?? null)!==$entity || ($data['document_id'] ?? null)!==$id
            || !is_int($data['revision'] ?? null) || $data['revision']<1 || $data['revision']>100 || $data['revision']!==(int)($entry['revision'] ?? 0)
            || ($data['at'] ?? null)!==($entry['captured_at'] ?? null) || !is_int($data['user'] ?? null) || $data['user']<=0 || $data['user']!==(int)($entry['fk_user'] ?? 0)
            || (int)($entry['mapping_revision'] ?? -1)!==0 || (int)($data['basis']['invoice']['rowid'] ?? 0)!==$id
            || self::choices($data['basis'],$entity,$data['choices'])!==$data['choices']) { throw new RuntimeException('Sales basis metadata'); }
        return $data;
    }
    /** Internal native create path. The core owns the transaction and permissions. */
    public static function captureContext($db,$entity,$id,$posting)
    {
        $active=self::rows($db,'SELECT @@in_transaction AS active');
        if (!is_int($entity) || $entity<=0 || $entity!==(int)$GLOBALS['conf']->entity || !is_int($id) || $id<=0 || (int)$active[0]['active']!==1) { throw new InvalidArgumentException('Sales context native boundary'); }
        // No record at this event means no claim of historical documentary evidence.
        $exists=self::rows($db,"SELECT revision FROM ".$db->prefix()."norway_tax_evidence WHERE entity=".$entity." AND document_type='".self::TYPE."' AND fk_document=".$id.' LIMIT 1');
        if (!$exists) { return null; }
        $source=null;try { $source=self::readBasis($db,$entity,$id,true); } catch (InvalidArgumentException $unsupported) { }
        $history=self::retained($db,$entity,$id,true);if (!$history) { throw new RuntimeException('Sales context disappeared'); }
        return self::context(end($history),$source,$posting,$entity,$id);
    }
    /** Historical interpretation uses only the embedded record and captured sources. */
    public static function context($entry,$source,$posting,$entity,$id)
    {
        if (!is_int($entity) || $entity<=0 || !is_int($id) || $id<=0 || ($posting['posting']['doc_type'] ?? null)!=='customer_invoice'
            || (int)($posting['posting']['entity'] ?? 0)!==$entity || (int)($posting['posting']['fk_doc'] ?? 0)!==$id) { throw new InvalidArgumentException('Sales context identity'); }
        $data=self::validateEntry($entry,$entity,$id);
        if ($source!==null) {
            if (!is_array($source) || (int)($source['invoice']['rowid'] ?? 0)!==$id) { throw new RuntimeException('Sales context source'); }
            self::eligible($source,$entity);
        }
        $linked=$source!==null && $source===$data['basis'] && self::samePostingSource($source,$posting);
        return array('version'=>1,'status'=>$linked ? 'Linked' : 'Changed','assessment'=>$entry,'source'=>$source);
    }
    private static function samePostingSource($source,$posting)
    {
        if (($posting['source_complete'] ?? null)!==true || !is_array($posting['invoice'] ?? null) || !is_array($posting['lines'] ?? null)) { return false; }
        foreach (array('rowid','entity','type','fk_soc','datef','multicurrency_code','total_ht','total_tva','total_ttc') as $key) {
            if (!array_key_exists($key,$posting['invoice']) || $source['invoice'][$key]!==$posting['invoice'][$key]) { return false; }
        }
        if (count($source['lines'])!==count($posting['lines'])) { return false; }
        // Description, quantity and price are retained in the separate locked source.
        foreach ($source['lines'] as $i=>$line) {
            foreach (array('rowid','fk_facture','product_type','tva_tx','vat_src_code','info_bits','localtax1_tx','localtax2_tx','total_ht','total_tva','total_ttc') as $key) {
                if (!array_key_exists($key,$posting['lines'][$i]) || $line[$key]!==$posting['lines'][$i][$key]) { return false; }
            }
        }
        foreach ($source['party'] as $key=>$value) { if (!array_key_exists($key,$posting['party'] ?? array()) || $posting['party'][$key]!==$value) { return false; } }
        $settings=array();foreach ($posting['settings'] ?? array() as $row) { $settings[$row['entity'].'/'.$row['name']]=$row['value']; }
        foreach ($source['settings'] as $row) { if (($settings[$row['entity'].'/'.$row['name']] ?? null)!==$row['value']) { return false; } }
        return true;
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
