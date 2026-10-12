<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require_once __DIR__.'/NorwaySchema.php';
require_once __DIR__.'/NorwayInvoiceMapping.php';
/** Explicit control copies of current data, never approval or a posting-time record. */
class NorwayInvoiceEvidence
{
    const LIMIT=100;
    const BYTES=1000000;
    const HISTORY_BYTES=5000000;

    private static function guard($entity,$id,$user)
    {
        if (!is_int($entity) || $entity<=0 || $entity!==(int)$GLOBALS['conf']->entity || !is_int($id) || $id<=0
            || !$user || !$user->admin || (int)$user->id<=0 || !isModEnabled('norway') || !isModEnabled('accounting')
            || !$user->hasRight('facture','lire') || !$user->hasRight('accounting','mouvements','lire')) { throw new InvalidArgumentException('Evidence access'); }
    }

    private static function rows($db,$sql)
    {
        $r=$db->query($sql); if (!$r) { throw new RuntimeException('Evidence read'); }
        $out=array(); while ($row=$db->fetch_object($r)) { $out[]=(array)$row; } return $out;
    }

    private static function encode($value)
    {
        $json=json_encode($value,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        if (strlen($json)>self::BYTES) { throw new RuntimeException('Evidence capacity'); } return $json;
    }

    public static function history($db,$entity,$id,$user)
    {
        self::guard($entity,$id,$user); NorwaySchema::verify($db);
        $size=self::rows($db,"SELECT COUNT(*) AS entries,COALESCE(SUM(OCTET_LENGTH(payload)),0) AS bytes FROM ".$db->prefix()."norway_tax_evidence WHERE entity=".$entity." AND document_type='customer_invoice' AND fk_document=".$id);
        if (count($size)!==1 || (int)$size[0]['entries']>self::LIMIT || (int)$size[0]['bytes']>self::HISTORY_BYTES) { throw new RuntimeException('Evidence history capacity'); }
        $rows=self::rows($db,"SELECT rowid,revision,captured_at,fk_user,mapping_revision,payload,payload_sha256 FROM ".$db->prefix()."norway_tax_evidence WHERE entity=".$entity." AND document_type='customer_invoice' AND fk_document=".$id.' ORDER BY revision LIMIT 101');
        if (count($rows)>self::LIMIT) { throw new RuntimeException('Evidence history capacity'); }
        foreach ($rows as $index=>&$row) {
            if ((int)$row['revision']!==$index+1 || strlen($row['payload'])>self::BYTES || !hash_equals($row['payload_sha256'],hash('sha256',$row['payload']))) { throw new RuntimeException('Evidence integrity'); }
            $data=json_decode($row['payload'],true,512,JSON_THROW_ON_ERROR);
            if (($data['version'] ?? null)!==1 || ($data['entity'] ?? null)!==$entity || ($data['document_type'] ?? null)!=='customer_invoice'
                || ($data['document_id'] ?? null)!==$id || ($data['revision'] ?? null)!==$index+1
                || ($data['captured_at'] ?? null)!==$row['captured_at'] || ($data['user'] ?? null)!==(int)$row['fk_user']
                || ($data['mapping']['revision'] ?? null)!==(int)$row['mapping_revision']) { throw new RuntimeException('Evidence metadata'); }
            $row['data']=$data; unset($row['payload']);
        }
        unset($row); return $rows;
    }

    private static function basis($db,$entity,$id,$revision,$user,$lock,$reading=false)
    {
        self::guard($entity,$id,$user); NorwaySchema::verify($db);
        $p=$db->prefix(); $suffix=$lock ? ' FOR UPDATE' : '';
        // Same lock order as configuration writes. SERIALIZABLE also locks source ranges against new rows.
        if ($lock) {
            self::rows($db,"SELECT rowid FROM ".$p."const WHERE entity=".$entity." AND name IN ('MAIN_INFO_SIREN','MAIN_INFO_SOCIETE_COUNTRY') ORDER BY name FOR UPDATE");
            self::rows($db,"SELECT rowid FROM ".$p."const WHERE entity=".$entity." AND name='".NorwayTaxMappings::SETTING."' FOR UPDATE");
        }
        $headers=self::rows($db,'SELECT rowid,entity,ref,type,fk_statut,fk_soc,datef,fk_facture_source,multicurrency_code,total_ht,total_tva,total_ttc FROM '.$p.'facture WHERE entity='.$entity.' AND rowid='.$id.$suffix);
        if (count($headers)!==1) { throw new InvalidArgumentException('Evidence invoice'); } $header=$headers[0];
        if (!in_array((int)$header['type'],array(0,2),true) || !in_array((int)$header['fk_statut'],array(1,2),true) || $header['multicurrency_code']!=='NOK') { throw new InvalidArgumentException('Evidence scope'); }
        $lines=self::rows($db,'SELECT rowid,fk_facture,rang,description,fk_product,product_type,qty,subprice,remise_percent,tva_tx,vat_src_code,info_bits,total_ht,total_tva,total_ttc FROM '.$p.'facturedet WHERE fk_facture='.$id.' ORDER BY rowid LIMIT 201'.$suffix);
        $ledger=self::rows($db,"SELECT rowid,entity,doc_type,fk_doc,fk_docdet,code_journal,piece_num,doc_date,doc_ref,numero_compte,debit,credit FROM ".$p."accounting_bookkeeping WHERE entity=".$entity." AND fk_doc=".$id." AND doc_type='customer_invoice' ORDER BY rowid LIMIT 201".$suffix);
        if (!$lines || !$ledger || count($lines)>200 || count($ledger)>200) { throw new RuntimeException('Evidence incomplete or too large'); }
        $mapping=NorwayTaxMappings::read($db,$entity);
        $invoice=(object)array('entity'=>$entity,'lines'=>array_map(static fn($line)=>(object)$line,$lines));
        $comparison=NorwayInvoiceMapping::report($mapping['record'],$revision,$invoice,substr($header['datef'],0,10));
        $selected=$mapping['record']; $selected['events']=array_slice($selected['events'],0,$revision);
        $history=self::history($db,$entity,$id,$user);
        if (!$reading && count($history)>=self::LIMIT) { throw new RuntimeException('Evidence history capacity'); }
        $basis=array('version'=>1,'entity'=>$entity,'document_type'=>'customer_invoice','document_id'=>$id,'org'=>$selected['org'],
            'invoice'=>$header,'lines'=>$lines,'ledger'=>$ledger,'mapping'=>array('revision'=>$revision,'record'=>$selected,'comparison'=>$comparison));
        $fingerprint=hash('sha256',self::encode(array($basis,$mapping['fingerprint'],array_column($history,'payload_sha256'))));
        return array('basis'=>$basis,'fingerprint'=>$fingerprint,'revision'=>count($history)+1);
    }

    /** Read-only comparison against the same explicitly selected mapping revision. */
    public static function compare($db,$entity,$id,$copyRevision,$user)
    {
        $history=self::history($db,$entity,$id,$user);
        if (!is_int($copyRevision) || $copyRevision<1 || !isset($history[$copyRevision-1])) { throw new InvalidArgumentException('Evidence revision'); }
        $saved=$history[$copyRevision-1]['data'];
        $current=self::basis($db,$entity,$id,$saved['mapping']['revision'],$user,false,true)['basis'];
        return self::differences($saved,$current);
    }

    public static function differences($saved,$current)
    {
        foreach (array('version','entity','document_type','document_id') as $key) {
            if (!array_key_exists($key,$saved) || !array_key_exists($key,$current) || $saved[$key]!==$current[$key]) { throw new InvalidArgumentException('Evidence identity'); }
        }
        $changes=array();
        foreach (array('invoice','lines','ledger') as $group) {
            if (!isset($saved[$group],$current[$group]) || !is_array($saved[$group]) || !is_array($current[$group])) { throw new InvalidArgumentException('Evidence content'); }
            $before=$group==='invoice' ? array($saved[$group]) : $saved[$group];
            $after=$group==='invoice' ? array($current[$group]) : $current[$group];
            $index=static function ($rows) {
                $out=array();
                foreach ($rows as $row) {
                    if (!is_array($row) || !isset($row['rowid']) || isset($out[(string)$row['rowid']])) { throw new InvalidArgumentException('Evidence row'); }
                    $out[(string)$row['rowid']]=$row;
                }
                return $out;
            };
            $before=$index($before); $after=$index($after);
            $ids=array_unique(array_merge(array_keys($before),array_keys($after))); sort($ids,SORT_NUMERIC);
            foreach ($ids as $id) {
                if (!isset($before[$id]) || !isset($after[$id])) {
                    $changes[]=array('group'=>$group,'id'=>$id,'field'=>'row','status'=>isset($after[$id]) ? 'Added' : 'Removed','before'=>null,'after'=>null);
                    continue;
                }
                foreach (array_unique(array_merge(array_keys($before[$id]),array_keys($after[$id]))) as $field) {
                    $old=$before[$id][$field] ?? null; $new=$after[$id][$field] ?? null;
                    if ($old!==$new) { $changes[]=array('group'=>$group,'id'=>$id,'field'=>$field,'status'=>'Changed','before'=>$old,'after'=>$new); }
                }
            }
        }
        return array('changes'=>$changes,'mapping_changed'=>$saved['mapping']!==$current['mapping'],'organisation_changed'=>$saved['org']!==$current['org']);
    }

    public static function preview($db,$entity,$id,$revision,$user)
    {
        return self::basis($db,$entity,$id,$revision,$user,false);
    }

    public static function save($db,$entity,$id,$revision,$fingerprint,$user)
    {
        self::guard($entity,$id,$user);
        // Own transaction is required: avoid nested rollback semantics and pre-existing read snapshots.
        $active=self::rows($db,'SELECT @@in_transaction AS active');
        if (count($active)!==1 || (int)$active[0]['active']!==0) { throw new RuntimeException('Evidence transaction already active'); }
        if (!$db->query('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE')) { throw new RuntimeException('Evidence isolation'); }
        if (!$db->begin()) { throw new RuntimeException('Evidence begin'); }
        try {
            $preview=self::basis($db,$entity,$id,$revision,$user,true);
            if (!is_string($fingerprint) || !hash_equals($preview['fingerprint'],$fingerprint)) { throw new RuntimeException('Evidence changed; preview again'); }
            $data=$preview['basis']+array('revision'=>$preview['revision'],'captured_at'=>gmdate('Y-m-d H:i:s'),'user'=>(int)$user->id);
            $json=self::encode($data); $hash=hash('sha256',$json);
            $sql="INSERT INTO ".$db->prefix()."norway_tax_evidence (entity,document_type,fk_document,revision,captured_at,fk_user,mapping_revision,payload,payload_sha256) VALUES (".$entity.",'customer_invoice',".$id.','.$preview['revision'].",'".$data['captured_at']."',".(int)$user->id.','.$revision.",'".$db->escape($json)."','".$hash."')";
            if (!$db->query($sql)) { throw new RuntimeException('Evidence write'); }
            $stored=self::history($db,$entity,$id,$user); $last=end($stored);
            if (!$last || $last['payload_sha256']!==$hash || $last['data']!==$data) { throw new RuntimeException('Evidence write verification'); }
            if (!$db->commit()) { throw new RuntimeException('Evidence commit'); } return $preview['revision'];
        } catch (Throwable $error) { $db->rollback(); throw $error; }
    }
}
