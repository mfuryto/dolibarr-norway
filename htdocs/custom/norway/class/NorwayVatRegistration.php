<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require_once __DIR__.'/NorwayReadiness.php';
require_once __DIR__.'/NorwayPeriodSetup.php';
/** Explicit first-time registration-date record; never changes VAT status or documents. */
class NorwayVatRegistration
{
    const SETTING='NORWAY_VAT_REGISTRATION_START';
    private static function rows($db,$sql)
    {
        $r=$db->query($sql); if (!$r) { throw new RuntimeException('Registration'); }
        $rows=array(); while ($row=$db->fetch_object($r)) { $rows[]=(array)$row; } return $rows;
    }

    public static function preview($db,$entity,$date)
    {
        global $mysoc;
        if ((int)$entity<=0 || (int)$GLOBALS['conf']->entity!==(int)$entity || !NorwayPeriodSetup::validDate($date)) { throw new InvalidArgumentException('Date/entity'); }
        $constants=self::rows($db,"SELECT name,value,entity FROM ".$db->prefix()."const WHERE entity IN (0,".(int)$entity.") AND name IN ('".self::SETTING."','MAIN_INFO_SIREN','MAIN_INFO_SOCIETE_COUNTRY') ORDER BY entity,name");
        $stored=array(); foreach ($constants as $row) { $stored[$row['name']]=$row['value']; }
        $org=preg_replace('/[ \t]/','',(string)($mysoc->idprof1 ?? ''));
        $storedOrg=preg_replace('/[ \t]/','',(string)($stored['MAIN_INFO_SIREN'] ?? ''));
        $existing=false; foreach ($constants as $row) { if ($row['name']===self::SETTING && $row['value']!=='') { $existing=true; } }
        $existing=$existing || getDolGlobalString(self::SETTING)!=='';
        return array('entity'=>(int)$entity,'date'=>$date,'org'=>$org,'constants'=>$constants,
            'allowed'=>!$existing && ($mysoc->country_code ?? '')==='NO' && NorwayReadiness::validOrganisationNumber($org) && $storedOrg===$org && (explode(':',(string)($stored['MAIN_INFO_SOCIETE_COUNTRY'] ?? ''))[1] ?? '')==='NO',
            'existing'=>$existing);
    }

    public static function fingerprint($preview) { return hash('sha256',json_encode($preview,JSON_THROW_ON_ERROR)); }

    public static function apply($db,$entity,$date,$fingerprint,$user)
    {
        if (empty($user->admin) || (int)$user->id<=0 || (int)$entity!==(int)$GLOBALS['conf']->entity) { throw new InvalidArgumentException('Confirmation'); }
        require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
        $had=property_exists($GLOBALS['conf']->global,self::SETTING); $old=$GLOBALS['conf']->global->{self::SETTING} ?? null;
        if ($db->begin()<=0) { throw new RuntimeException('Registration'); }
        try {
            self::rows($db,"SELECT rowid FROM ".$db->prefix()."const WHERE entity IN (0,".(int)$entity.") AND name IN ('".self::SETTING."','MAIN_INFO_SIREN','MAIN_INFO_SOCIETE_COUNTRY') FOR UPDATE");
            $preview=self::preview($db,$entity,$date);
            if (!$preview['allowed'] || !hash_equals(self::fingerprint($preview),(string)$fingerprint)) { throw new InvalidArgumentException('Changed/blocked'); }
            $record=json_encode(array('version'=>1,'entity'=>(int)$entity,'org'=>$preview['org'],'start'=>$date,'confirmed_by'=>(int)$user->id,'confirmed_at'=>gmdate('c')),JSON_THROW_ON_ERROR);
            if (dolibarr_set_const($db,self::SETTING,$record,'chaine',0,'Explicit registration date; no tax decision',(int)$entity)<=0) { throw new RuntimeException('Registration'); }
            $rows=self::rows($db,"SELECT value FROM ".$db->prefix()."const WHERE name='".self::SETTING."' AND entity=".(int)$entity);
            if (count($rows)!==1 || $rows[0]['value']!==$record || $db->commit()<=0) { throw new RuntimeException('Registration'); }
        } catch (Throwable $error) { $db->rollback(); if ($had) { $GLOBALS['conf']->global->{self::SETTING}=$old; } else { unset($GLOBALS['conf']->global->{self::SETTING}); } throw $error; }
    }

    public static function read($db,$entity,$org)
    {
        if ((int)$entity<=0 || (int)$GLOBALS['conf']->entity!==(int)$entity) { throw new InvalidArgumentException('Entity'); }
        $rows=self::rows($db,"SELECT value FROM ".$db->prefix()."const WHERE name='".self::SETTING."' AND entity=".(int)$entity);
        if (!$rows) { return array('status'=>'Missing'); }
        if (count($rows)!==1) { return array('status'=>'Invalid'); }
        return self::inspect($rows[0]['value'],(int)$entity,$org);
    }

    /** Validate a retained record without consulting today's database or company. */
    public static function inspect($raw,$entity,$org)
    {
        if (!is_string($raw) || strlen($raw)>10000 || !is_int($entity) || $entity<=0) { return array('status'=>'Invalid'); }
        if ($raw==='') { return array('status'=>'Missing'); }
        $r=json_decode($raw,true);
        if (!is_array($r) || ($r['version'] ?? null)!==1 || ($r['entity'] ?? null)!==$entity || !NorwayPeriodSetup::validDate($r['start'] ?? null)
            || !NorwayReadiness::validOrganisationNumber($r['org'] ?? '') || !is_int($r['confirmed_by'] ?? null) || $r['confirmed_by']<=0
            || !is_string($r['confirmed_at'] ?? null) || !preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}\+00:00$/D',$r['confirmed_at'])) { return array('status'=>'Invalid'); }
        $confirmed=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:sP',$r['confirmed_at']);
        if (!$confirmed || $confirmed->format('Y-m-d\TH:i:sP')!==$r['confirmed_at']) { return array('status'=>'Invalid'); }
        if ($r['org']!==preg_replace('/[ \t]/','',(string)$org)) { return array('status'=>'Identity'); }
        return array('status'=>'Recorded','start'=>$r['start'],'confirmed_at'=>$r['confirmed_at'],'confirmed_by'=>$r['confirmed_by']);
    }

    public static function compare($record,$date)
    {
        if (!NorwayPeriodSetup::validDate($date)) { throw new InvalidArgumentException('Date'); }
        if (($record['status'] ?? '')!=='Recorded') { return $record['status'] ?? 'Invalid'; }
        return $date<$record['start'] ? 'Before' : 'After';
    }
}
