<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require_once __DIR__.'/NorwayVatRegistration.php';
/** Append-only configuration revisions, not an authority registry or filing record. */
class NorwayVatHistory
{
    const SETTING='NORWAY_VAT_REGISTRATION_HISTORY_V1';
    const LIMIT=100;
    const BYTES=60000;

    private static function rows($db,$sql)
    {
        $r=$db->query($sql);if(!$r){throw new RuntimeException('VAT history read');}
        $rows=array();while($row=$db->fetch_object($r)){$rows[]=(array)$row;}return $rows;
    }

    public static function input($id,$start,$end,$reason,$void)
    {
        if(!is_int($id)||$id<0||!NorwayPeriodSetup::validDate($start)||!is_string($end)
            ||($end!==''&&(!NorwayPeriodSetup::validDate($end)||$end<$start))||!is_string($reason)
            ||trim($reason)!==$reason||preg_match('/^.{10,1000}$/usD',$reason)!==1||!is_bool($void)||($void&&$id===0)){
            throw new InvalidArgumentException('VAT history input');
        }
        return array('period'=>$id,'start'=>$start,'end'=>$end,'reason'=>$reason,'void'=>$void);
    }

    public static function validate($record,$entity,$org)
    {
        if(!is_array($record)||($record['version']??null)!==1||($record['entity']??null)!==$entity||($record['org']??null)!==$org
            ||!is_string($record['legacy_raw']??null)||!is_array($record['events']??null)||!$record['events']
            ||count($record['events'])>self::LIMIT||array_keys($record['events'])!==range(0,count($record['events'])-1)){
            throw new RuntimeException('VAT history record');
        }
        $legacy=NorwayVatRegistration::inspect($record['legacy_raw'],$entity,$org);
        if($legacy['status']!=='Recorded'){throw new RuntimeException('VAT history origin');}
        $active=array();$next=1;
        foreach($record['events'] as $index=>$event){
            $input=self::input($event['period']??null,$event['start']??null,$event['end']??null,$event['reason']??null,$event['void']??null);
            if(($event['revision']??null)!==$index+1||!is_int($event['user']??null)||$event['user']<=0
                ||!is_string($event['at']??null)){throw new RuntimeException('VAT history event');}
            $at=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:sP',$event['at']);
            if(!$at||$at->format('Y-m-d\TH:i:sP')!==$event['at']||substr($event['at'],-6)!=='+00:00'){
                throw new RuntimeException('VAT history timestamp');
            }
            if($index===0&&($input!==self::input(1,$legacy['start'],'','Imported original registration date',false)
                ||$event['user']!==$legacy['confirmed_by']||$event['at']!==$legacy['confirmed_at'])){throw new RuntimeException('VAT history seed');}
            $id=$input['period'];
            if($id===$next){if($input['void']){throw new RuntimeException('VAT history new void');}$next++;}
            elseif(!isset($active[$id])||$active[$id]['void']){throw new RuntimeException('VAT history period id');}
            $active[$id]=$event;
            // Validate every historical revision, including inclusive boundary dates.
            $periods=array_values(array_filter($active,static fn($p)=>!$p['void']));
            usort($periods,static fn($a,$b)=>strcmp($a['start'],$b['start']));
            for($i=1;$i<count($periods);$i++){
                if($periods[$i-1]['end']===''||$periods[$i]['start']<=$periods[$i-1]['end']){throw new InvalidArgumentException('VAT history overlap');}
            }
        }
        return array('status'=>'History','record'=>$record,'active'=>$active,'revision'=>count($record['events']),'next'=>$next);
    }

    public static function inspect($raw,$legacyRaw,$entity,$org)
    {
        if(!is_string($raw)||strlen($raw)>self::BYTES||!is_int($entity)||$entity<=0){return array('status'=>'HistoryInvalid');}
        try{
            $record=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
            if(($record['org']??null)!==$org){return array('status'=>'Identity');}
            $state=self::validate($record,$entity,$org);
            if($record['legacy_raw']!==$legacyRaw){return array('status'=>'HistoryInvalid');}
            return $state;
        }catch(Throwable $error){return array('status'=>'HistoryInvalid');}
    }

    public static function compare($state,$date)
    {
        if(!NorwayPeriodSetup::validDate($date)){throw new InvalidArgumentException('VAT history date');}
        if(($state['status']??'')!=='History'){return array('status'=>$state['status']??'HistoryInvalid');}
        foreach($state['active'] as $period){
            if(!$period['void']&&$period['start']<=$date&&($period['end']===''||$date<=$period['end'])){
                return array('status'=>'HistoryCovered','revision'=>$state['revision'],'period'=>$period);
            }
        }
        return array('status'=>'HistoryGap','revision'=>$state['revision']);
    }

    private static function source($db,$entity,$org,$lock=false)
    {
        if(!is_int($entity)||$entity<=0||$entity!==(int)$GLOBALS['conf']->entity||!NorwayReadiness::validOrganisationNumber($org)){
            throw new InvalidArgumentException('VAT history identity');
        }
        $names="'MAIN_INFO_SIREN','MAIN_INFO_SOCIETE_COUNTRY','".self::SETTING."','".NorwayVatRegistration::SETTING."'";
        $rows=self::rows($db,'SELECT rowid,entity,name,value FROM '.$db->prefix().'const WHERE entity IN (0,'.$entity.') AND name IN ('.$names.') ORDER BY entity,name,rowid'.($lock?' FOR UPDATE':''));
        $values=array();
        foreach($rows as $row){
            if((int)$row['entity']!==$entity){if(in_array($row['name'],array(self::SETTING,NorwayVatRegistration::SETTING),true)&&$row['value']!==''){throw new RuntimeException('Global VAT history');}continue;}
            if(array_key_exists($row['name'],$values)){throw new RuntimeException('Duplicate VAT history source');}$values[$row['name']]=$row['value'];
        }
        if(preg_replace('/[ \t]/','',(string)($values['MAIN_INFO_SIREN']??''))!==$org
            ||(explode(':',(string)($values['MAIN_INFO_SOCIETE_COUNTRY']??''))[1]??'')!=='NO'){throw new InvalidArgumentException('VAT history identity');}
        return array('rows'=>$rows,'values'=>$values);
    }

    public static function configured($db,$entity)
    {
        if(!is_int($entity)||$entity<=0||$entity!==(int)$GLOBALS['conf']->entity){throw new InvalidArgumentException('VAT history entity');}
        return (bool)self::rows($db,"SELECT rowid FROM ".$db->prefix()."const WHERE entity=".$entity." AND name='".self::SETTING."'");
    }

    public static function current($db,$entity,$org,$date)
    {
        if(!self::configured($db,$entity)){return NorwayVatRegistration::compare(NorwayVatRegistration::read($db,$entity,$org),$date);}
        return self::compare(self::read($db,$entity,$org),$date)['status'];
    }

    public static function read($db,$entity,$org)
    {
        $org=preg_replace('/[ \t]/','',(string)$org);$source=self::source($db,$entity,$org);$values=$source['values'];
        if(!array_key_exists(self::SETTING,$values)){return array('status'=>'Missing');}
        return self::inspect($values[self::SETTING],$values[NorwayVatRegistration::SETTING]??null,$entity,$org);
    }

    public static function preview($db,$entity,$org,$input,$lock=false)
    {
        $org=preg_replace('/[ \t]/','',(string)$org);$source=self::source($db,$entity,$org,$lock);$v=$source['values'];
        if(isset($v[self::SETTING])){
            $state=self::inspect($v[self::SETTING],$v[NorwayVatRegistration::SETTING]??null,$entity,$org);
            if($state['status']!=='History'){throw new RuntimeException('VAT history invalid');}$record=$state['record'];
        }else{
            $legacy=NorwayVatRegistration::inspect($v[NorwayVatRegistration::SETTING]??null,$entity,$org);
            if($legacy['status']!=='Recorded'){throw new RuntimeException('VAT history requires first date');}
            $seed=self::input(1,$legacy['start'],'','Imported original registration date',false)
                +array('revision'=>1,'user'=>$legacy['confirmed_by'],'at'=>$legacy['confirmed_at']);
            $record=array('version'=>1,'entity'=>$entity,'org'=>$org,'legacy_raw'=>$v[NorwayVatRegistration::SETTING],'events'=>array($seed));
            $state=self::validate($record,$entity,$org);
            if(($input['period']??0)!==1){throw new InvalidArgumentException('VAT history first confirmation');}
        }
        $input=self::input($input['period']??null,$input['start']??null,$input['end']??null,$input['reason']??null,$input['void']??null);
        $input['period']=$input['period']?:$state['next'];
        // A preview has a fixed timestamp and actor added only when committing.
        $event=$input+array('revision'=>count($record['events'])+1,'user'=>1,'at'=>'2000-01-01T00:00:00+00:00');
        $candidate=$record;$candidate['events'][]=$event;self::validate($candidate,$entity,$org);
        if(strlen(json_encode($candidate,JSON_THROW_ON_ERROR))>self::BYTES){throw new RuntimeException('VAT history capacity');}
        return array('entity'=>$entity,'org'=>$org,'input'=>$input,'record'=>$record,'source'=>$source['rows']);
    }

    public static function fingerprint($preview){return hash('sha256',json_encode($preview,JSON_THROW_ON_ERROR));}

    public static function save($db,$entity,$org,$input,$fingerprint,$user)
    {
        if(!$user||!$user->admin||!empty($user->socid)||(int)$user->id<=0||!isModEnabled('norway')||!isModEnabled('accounting')){throw new InvalidArgumentException('VAT history permission');}
        require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
        $had=property_exists($GLOBALS['conf']->global,self::SETTING);$old=$GLOBALS['conf']->global->{self::SETTING}??null;
        if($db->begin()<=0){throw new RuntimeException('VAT history transaction');}
        $savepoint=false;
        try{
            if(!$db->query('SAVEPOINT norway_vat_history_write')){throw new RuntimeException('VAT history savepoint');}$savepoint=true;
            $preview=self::preview($db,$entity,$org,$input,true);
            if(!is_string($fingerprint)||!hash_equals(self::fingerprint($preview),$fingerprint)){throw new RuntimeException('Stale VAT history');}
            $record=$preview['record'];$record['events'][]=$preview['input']+array('revision'=>count($record['events'])+1,'user'=>(int)$user->id,'at'=>gmdate('c'));
            self::validate($record,$entity,$preview['org']);$raw=json_encode($record,JSON_THROW_ON_ERROR);
            if(strlen($raw)>self::BYTES||dolibarr_set_const($db,self::SETTING,$raw,'chaine',0,'Explicit VAT registration history',$entity)<=0){throw new RuntimeException('VAT history save');}
            $check=self::rows($db,"SELECT value FROM ".$db->prefix()."const WHERE entity=".$entity." AND name='".self::SETTING."'");
            if(count($check)!==1||$check[0]['value']!==$raw||$db->commit()<=0){throw new RuntimeException('VAT history verification');}
        }catch(Throwable $error){if($savepoint){$db->query('ROLLBACK TO SAVEPOINT norway_vat_history_write');}$db->rollback();if($had){$GLOBALS['conf']->global->{self::SETTING}=$old;}else{unset($GLOBALS['conf']->global->{self::SETTING});}throw $error;}
    }
}
