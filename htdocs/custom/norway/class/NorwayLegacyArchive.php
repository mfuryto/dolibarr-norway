<?php
// SPDX-License-Identifier: GPL-3.0-or-later
/** Read-only access to retired additions. Never creates accounting or evidence data. */
class NorwayLegacyArchive
{
    private static function rows($db,$sql)
    {
        $r=$db->query($sql);if (!$r) { throw new RuntimeException('Archive storage'); }
        $rows=array();while ($row=$db->fetch_object($r)) { $rows[]=(array)$row; }return $rows;
    }
    private static function guard($entity,$user)
    {
        if (!is_int($entity) || $entity<=0 || $entity!==(int)$GLOBALS['conf']->entity || empty($user->admin) || !empty($user->socid)
            || !isModEnabled('norway') || !$user->hasRight('accounting','mouvements','lire')) { throw new InvalidArgumentException('Archive access'); }
    }
    public static function read($db,$entity,$user,$after=0,$id=0)
    {
        self::guard($entity,$user);
        if (!is_int($after) || $after<0 || !is_int($id) || $id<0) { throw new InvalidArgumentException('Archive selection'); }
        $rows=self::rows($db,'SELECT rowid,document_type,fk_document,revision,captured_at,fk_user,mapping_revision,payload,payload_sha256 FROM '.$db->prefix().'norway_tax_evidence WHERE entity='.$entity.($id ? ' AND rowid='.$id : ' AND rowid>'.$after).' ORDER BY rowid LIMIT '.($id ? '1' : '21'));
        $next=count($rows)>20 ? (int)$rows[19]['rowid'] : 0;if ($next) { array_pop($rows); }$bytes=0;
        foreach ($rows as &$row) {
            $bytes+=strlen($row['payload']);if ($bytes>25000000) { throw new RuntimeException('Archive page capacity'); }
            $row['intact']=hash_equals($row['payload_sha256'],hash('sha256',$row['payload']));
        }unset($row);return array('rows'=>$rows,'next'=>$next);
    }
    public static function hasData($db,$entity,$user)
    {
        self::guard($entity,$user);return (bool)self::rows($db,'SELECT rowid FROM '.$db->prefix().'norway_tax_evidence WHERE entity='.$entity.' LIMIT 1') || (bool)self::settings($db,$entity,$user);
    }
    public static function settings($db,$entity,$user)
    {
        self::guard($entity,$user);$names=array('NORWAY_VAT_REGISTRATION_START','NORWAY_VAT_REGISTRATION_HISTORY_V1','NORWAY_TAX_MAPPINGS_V1');
        for($i=1;$i<=11;$i++){ $names[]='NORWAY_WIZARD_STEP_'.$i; }
        return self::rows($db,"SELECT name,value FROM ".$db->prefix()."const WHERE entity=".$entity." AND name IN ('".implode("','",$names)."') ORDER BY name");
    }
    /** Existing custom purchase vouchers must not be transferred a second time. Core owns transaction/rights. */
    public static function guardPosting($db,$entity,$object)
    {
        if ($entity<=0 || $entity!==(int)$GLOBALS['conf']->entity || (int)($object->entity ?? $entity)!==$entity) { throw new InvalidArgumentException('Archive entity'); }
        $p=$db->prefix();$id=(int)($object->fk_doc ?? 0);
        if (($object->doc_type ?? '')==='supplier_invoice' && $id>0 && self::rows($db,"SELECT rowid FROM ".$p."norway_tax_evidence WHERE entity=".$entity." AND document_type='supplier_posting' AND fk_document=".$id.' LIMIT 1 FOR UPDATE')) { throw new RuntimeException('Previously posted purchase'); }
        $piece=(int)($object->piece_num ?? 0);
        if ($piece>0 && self::rows($db,"SELECT b.rowid FROM ".$p."accounting_bookkeeping b INNER JOIN ".$p."norway_tax_evidence m ON m.entity=b.entity AND m.fk_document=b.fk_doc AND m.document_type='supplier_posting' WHERE b.entity=".$entity." AND b.doc_type='supplier_invoice' AND b.piece_num=".$piece.' AND b.rowid<>'.(int)($object->id ?? 0).' LIMIT 1 FOR UPDATE')) { throw new RuntimeException('Previously posted voucher'); }
    }
}
