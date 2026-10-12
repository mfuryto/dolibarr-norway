<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require_once __DIR__.'/NorwayTaxMappingPreview.php';
require_once __DIR__.'/NorwayReadiness.php';
/** Entity-bound configuration history; not immutable accounting evidence. */
class NorwayTaxMappings
{
    const SETTING='NORWAY_TAX_MAPPINGS_V1';
    const LIMIT=100;

    private static function rows($db,$entity,$lock=false)
    {
        if ((int)$entity<=0 || (int)$entity!==(int)$GLOBALS['conf']->entity) { throw new InvalidArgumentException('Entity'); }
        $r=$db->query("SELECT value FROM ".$db->prefix()."const WHERE entity=".(int)$entity." AND name='".self::SETTING."'".($lock ? ' FOR UPDATE' : ''));
        if (!$r) { throw new RuntimeException('Mapping read'); }
        $out=array(); while ($row=$db->fetch_object($r)) { $out[]=$row->value; } return $out;
    }

    private static function identity($db,$entity,$lock=false)
    {
        global $mysoc;
        $r=$db->query("SELECT name,value FROM ".$db->prefix()."const WHERE entity=".(int)$entity." AND name IN ('MAIN_INFO_SIREN','MAIN_INFO_SOCIETE_COUNTRY') ORDER BY name".($lock ? ' FOR UPDATE' : ''));
        if (!$r) { throw new RuntimeException('Mapping identity'); }
        $stored=array(); while ($row=$db->fetch_object($r)) {
            if (isset($stored[$row->name])) { throw new RuntimeException('Duplicate identity'); }
            $stored[$row->name]=$row->value;
        }
        $org=preg_replace('/[ \t]/','',(string)($mysoc->idprof1 ?? ''));
        if (!NorwayReadiness::validOrganisationNumber($org) || ($mysoc->country_code ?? '')!=='NO'
            || preg_replace('/[ \t]/','',(string)($stored['MAIN_INFO_SIREN'] ?? ''))!==$org
            || (explode(':',(string)($stored['MAIN_INFO_SOCIETE_COUNTRY'] ?? ''))[1] ?? '')!=='NO') { throw new InvalidArgumentException('Identity'); }
        return $org;
    }

    public static function input($source,$target,$start,$end,$direction,$id,$retire)
    {
        $plan=NorwayTaxMappingPreview::plan($source,$target,$start,$end);
        if (!in_array($direction,array('sale','purchase'),true) || !is_int($id) || $id<0 || !is_bool($retire) || ($retire && !$id)) { throw new InvalidArgumentException('Mapping input'); }
        return $plan+array('direction'=>$direction,'id'=>$id,'retire'=>$retire);
    }

    /** Validate every historical revision and rebuild its latest configuration. */
    public static function validate($record,$entity,$org)
    {
        if (!is_array($record) || ($record['version'] ?? null)!==1 || ($record['entity'] ?? null)!==(int)$entity || ($record['org'] ?? null)!==$org
            || ($record['reference'] ?? null)!==NorwayTaxReference::SOURCE || !is_array($record['events'] ?? null)
            || array_keys($record['events'])!==range(0,count($record['events'])-1) || count($record['events'])>self::LIMIT) {
            // An empty newly-created record is handled by read(), never stored as history.
            throw new RuntimeException('Invalid mapping history');
        }
        $active=array(); $next=1;
        foreach ($record['events'] as $index=>$event) {
            if (!is_array($event) || ($event['revision'] ?? null)!==$index+1 || !is_int($event['user'] ?? null) || $event['user']<=0
                || !is_string($event['at'] ?? null) || !preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}\+00:00$/D',$event['at'])) { throw new RuntimeException('Invalid mapping event'); }
            $input=self::input($event['source'] ?? null,$event['target'] ?? null,$event['start'] ?? null,$event['end'] ?? null,
                $event['direction'] ?? null,$event['id'] ?? null,$event['retire'] ?? null);
            if ($input['id']===$next) { if ($input['retire']) { throw new RuntimeException('Invalid retirement'); } $next++; }
            elseif (!isset($active[$input['id']]) || $active[$input['id']]['retire']) { throw new RuntimeException('Invalid correction'); }
            self::assertNoOverlap($active,$input);
            $active[$input['id']]=$event;
        }
        return array('active'=>$active,'next'=>$next);
    }

    private static function assertNoOverlap($active,$input)
    {
        if ($input['retire']) {
            $old=$active[$input['id']] ?? null;
            foreach (array('source','target','start','end','direction') as $field) {
                if (!$old || $old[$field]!==$input[$field]) { throw new InvalidArgumentException('Retirement changed rule'); }
            }
            return;
        }
        foreach ($active as $id=>$rule) {
            if ($id!==$input['id'] && !$rule['retire'] && $rule['source']===$input['source'] && $rule['direction']===$input['direction']
                && $rule['start']<=$input['end'] && $input['start']<=$rule['end']) { throw new InvalidArgumentException('Overlapping mapping'); }
        }
    }

    public static function read($db,$entity)
    {
        $rows=self::rows($db,$entity); $org=self::identity($db,$entity);
        if (!$rows) {
            return array('record'=>array('version'=>1,'entity'=>(int)$entity,'org'=>$org,'reference'=>NorwayTaxReference::SOURCE,'events'=>array()),
                'active'=>array(),'next'=>1,'fingerprint'=>hash('sha256',json_encode(array($org,$rows),JSON_THROW_ON_ERROR)));
        }
        if (count($rows)!==1 || strlen($rows[0])>60000) { throw new RuntimeException('Invalid mapping rows'); }
        $record=json_decode($rows[0],true); $state=self::validate($record,$entity,$org);
        return $state+array('record'=>$record,'fingerprint'=>hash('sha256',json_encode(array($org,$rows),JSON_THROW_ON_ERROR)));
    }

    public static function preview($db,$entity,$input)
    {
        $input=self::input($input['source'] ?? null,$input['target'] ?? null,$input['start'] ?? null,$input['end'] ?? null,
            $input['direction'] ?? null,$input['id'] ?? null,$input['retire'] ?? null);
        $state=self::read($db,$entity);
        if (count($state['record']['events'])>=self::LIMIT) { throw new RuntimeException('History capacity'); }
        if (!$input['id']) { $input['id']=$state['next']; }
        elseif (!isset($state['active'][$input['id']]) || $state['active'][$input['id']]['retire']) { throw new InvalidArgumentException('Unknown rule'); }
        self::assertNoOverlap($state['active'],$input);
        return array('input'=>$input,'fingerprint'=>$state['fingerprint'],'org'=>$state['record']['org']);
    }

    public static function save($db,$entity,$input,$fingerprint,$user)
    {
        if (empty($user->admin) || (int)$user->id<=0 || (int)$entity!==(int)$GLOBALS['conf']->entity) { throw new InvalidArgumentException('Administrator'); }
        $had=property_exists($GLOBALS['conf']->global,self::SETTING); $old=$GLOBALS['conf']->global->{self::SETTING} ?? null;
        if ($db->begin()<=0) { throw new RuntimeException('Mapping transaction'); }
        $savepoint=false;
        try {
            if (!$db->query('SAVEPOINT norway_tax_mapping_write')) { throw new RuntimeException('Mapping savepoint'); }
            $savepoint=true;
            // Existing company identity row serialises even the first insert of mapping history.
            self::identity($db,$entity,true); self::rows($db,$entity,true);
            $state=self::read($db,$entity);
            if (!hash_equals($state['fingerprint'],(string)$fingerprint)) { throw new InvalidArgumentException('Stale preview'); }
            $previewInput=$input;
            // An assigned ID equal to the next unused ID is a new rule, not a correction.
            if (($input['id'] ?? null)===$state['next']) { $previewInput['id']=0; }
            $preview=self::preview($db,$entity,$previewInput);
            if ($preview['input']!==$input) { throw new InvalidArgumentException('Changed input'); }
            $record=$state['record'];
            $record['events'][]=$input+array('revision'=>count($record['events'])+1,'user'=>(int)$user->id,'at'=>gmdate('c'));
            self::validate($record,$entity,$record['org']);
            $encoded=json_encode($record,JSON_THROW_ON_ERROR);
            if (strlen($encoded)>60000) { throw new RuntimeException('Mapping capacity'); }
            $where="name='".self::SETTING."' AND entity=".(int)$entity;
            // Update this module's configuration atomically; never delete the existing history row.
            $sql=$state['record']['events']
                ? "UPDATE ".$db->prefix()."const SET value='".$db->escape($encoded)."' WHERE ".$where
                : "INSERT INTO ".$db->prefix()."const (name,value,type,visible,note,entity) VALUES ('".self::SETTING."','".$db->escape($encoded)."','chaine',0,'Versioned tax mapping setup; not posting evidence',".(int)$entity.")";
            if (!$db->query($sql) || self::rows($db,$entity)!==array($encoded) || $db->commit()<=0) { throw new RuntimeException('Mapping save'); }
            $GLOBALS['conf']->global->{self::SETTING}=$encoded;
        } catch (Throwable $error) {
            if ($savepoint) { $db->query('ROLLBACK TO SAVEPOINT norway_tax_mapping_write'); }
            $db->rollback(); if ($had) { $GLOBALS['conf']->global->{self::SETTING}=$old; } else { unset($GLOBALS['conf']->global->{self::SETTING}); } throw $error;
        }
    }
}
