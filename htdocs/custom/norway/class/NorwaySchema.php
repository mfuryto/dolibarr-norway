<?php
// SPDX-License-Identifier: GPL-3.0-or-later
/** Versioned, additive schema foundation. Does not create accounting evidence. */
class NorwaySchema
{
    const VERSION=1;
    const CHECKSUM='6fa45031fee65c6c80562d83290430291d2af375dbb5cbb16268ba85530f17c5';
    private static function prefix($db)
    {
        $p=$db->prefix();
        if (!is_string($p) || !preg_match('/^[A-Za-z0-9_]+$/D',$p) || strlen($p)>35) { throw new RuntimeException('Schema prefix'); }
        return $p;
    }
    private static function query($db,$sql)
    {
        $r=$db->query($sql); if (!$r) { throw new RuntimeException('Norway schema query failed'); } return $r;
    }
    private static function statements()
    {
        $file=__DIR__.'/../migrations/001-tax-evidence.sql';
        if (!is_file($file) || hash_file('sha256',$file)!==self::CHECKSUM) { throw new RuntimeException('Migration checksum'); }
        return array_values(array_filter(array_map('trim',explode(';',file_get_contents($file)))));
    }
    private static function shape($db,$table,$expected,$index,$auto=false)
    {
        $r=self::query($db,'SHOW COLUMNS FROM '.$table); $actual=array();
        while ($row=$db->fetch_object($r)) {
            $actual[$row->Field]=strtolower($row->Type);
            if ($row->Null!=='NO' || ($row->Extra!=='' && !($auto && $row->Field==='rowid' && $row->Extra==='auto_increment'))) { throw new RuntimeException('Schema column attributes'); }
        }
        if ($actual!==$expected) { throw new RuntimeException('Schema column mismatch'); }
        $r=self::query($db,'SHOW INDEX FROM '.$table); $indexes=array();
        while ($row=$db->fetch_object($r)) {
            if ((int)$row->Non_unique!==0 || $row->Sub_part!==null) { throw new RuntimeException('Schema index mismatch'); }
            $indexes[$row->Key_name][(int)$row->Seq_in_index]=$row->Column_name;
        }
        foreach ($indexes as &$columns) { ksort($columns); $columns=array_values($columns); } unset($columns);
        ksort($indexes); ksort($index);
        if ($indexes!==$index) { throw new RuntimeException('Schema index mismatch'); }
        $r=self::query($db,"SHOW TABLE STATUS WHERE Name='".$db->escape($table)."'"); $status=$db->fetch_object($r);
        if (!$status || strtolower($status->Engine)!=='innodb') { throw new RuntimeException('Schema engine mismatch'); }
    }
    private static function shapes($db,$evidence=true)
    {
        $p=self::prefix($db);
        self::shape($db,$p.'norway_schema',array('version'=>'int(11)','checksum'=>'char(64)','applied_at'=>'datetime'),array('PRIMARY'=>array('version')));
        if ($evidence) {
            self::shape($db,$p.'norway_tax_evidence',array('rowid'=>'bigint(20)','entity'=>'int(11)','document_type'=>'varchar(32)','fk_document'=>'int(11)',
                'revision'=>'int(11)','captured_at'=>'datetime','fk_user'=>'int(11)','mapping_revision'=>'int(11)','payload'=>'longtext','payload_sha256'=>'char(64)'),
                array('PRIMARY'=>array('rowid'),'uk_norway_evidence_revision'=>array('entity','document_type','fk_document','revision')),true);
        }
    }
    private static function history($db)
    {
        $r=self::query($db,'SELECT version,checksum FROM '.self::prefix($db).'norway_schema ORDER BY version'); $rows=array();
        while ($row=$db->fetch_object($r)) { $rows[]=$row; }
        if ($rows && (count($rows)!==1 || (int)$rows[0]->version!==self::VERSION || $rows[0]->checksum!==self::CHECKSUM)) { throw new RuntimeException('Unknown or altered schema history'); }
        return $rows;
    }
    public static function verify($db)
    {
        self::statements(); self::shapes($db);
        if (count(self::history($db))!==1) { throw new RuntimeException('Schema migration incomplete'); }
        return true;
    }
    public static function migrate($db)
    {
        $transaction=$db->fetch_object(self::query($db,'SELECT @@in_transaction AS active'));
        if (!$transaction || (int)$transaction->active!==0) { throw new RuntimeException('Schema migration requires a separate transaction boundary'); }
        $p=self::prefix($db); $sql=self::statements();
        if (count($sql)!==2) { throw new RuntimeException('Migration statements'); }
        $database=$db->fetch_object(self::query($db,'SELECT DATABASE() AS name'));
        $lock='norway_schema_'.substr(hash('sha256',$database->name.'/'.$p),0,40);
        $got=$db->fetch_object(self::query($db,"SELECT GET_LOCK('".$lock."',10) AS acquired"));
        if (!$got || (int)$got->acquired!==1) { throw new RuntimeException('Schema migration lock'); }
        try {
            self::query($db,str_replace('llx_',$p,$sql[0])); self::shapes($db,false);
            if (self::history($db)) {
                // Never recreate a lost evidence table after a recorded migration.
                return self::verify($db);
            }
            self::query($db,str_replace('llx_',$p,$sql[1])); self::shapes($db);
            $count=$db->fetch_object(self::query($db,'SELECT COUNT(*) AS n FROM '.$p.'norway_tax_evidence'));
            if (!$count || (int)$count->n!==0) { throw new RuntimeException('Untracked evidence data'); }
            self::query($db,"INSERT INTO ".$p."norway_schema (version,checksum,applied_at) VALUES (1,'".self::CHECKSUM."',UTC_TIMESTAMP())");
            return self::verify($db);
        } finally { self::query($db,"SELECT RELEASE_LOCK('".$lock."')"); }
    }
}
