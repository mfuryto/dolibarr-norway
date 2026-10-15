<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require_once __DIR__.'/NorwayVatRegistration.php';
require_once __DIR__.'/NorwayVatHistory.php';
/** Preserved date comparison only; no legal registration status or period history. */
class NorwayPostingRegistrationContext
{
    public static function evaluate($basis,$entity)
    {
        $result=array('version'=>1,'status'=>'Unavailable','invoice_date'=>'','start'=>'','confirmed_at'=>'','confirmed_by'=>0,'credit'=>false);
        if (!is_int($entity) || $entity<=0 || !is_array($basis) || ($basis['source_complete'] ?? null)!==true
            || !is_array($basis['settings'] ?? null) || (int)($basis['invoice']['entity'] ?? 0)!==$entity
            || (int)($basis['posting']['entity'] ?? 0)!==$entity) { return $result; }
        $date=$basis['invoice']['datef'] ?? null;
        if (!NorwayPeriodSetup::validDate($date)) { return $result; }
        $result['invoice_date']=$date;$result['credit']=(int)($basis['invoice']['type'] ?? -1)===2;
        $settings=array();
        foreach ($basis['settings'] as $row) {
            if ((int)$row['entity']!==$entity) { continue; }
            if (array_key_exists($row['name'],$settings)) { return $result; }
            $settings[$row['name']]=$row['value'];
        }
        $org=preg_replace('/[ \t]/','',(string)($settings['MAIN_INFO_SIREN'] ?? ''));
        if (!NorwayReadiness::validOrganisationNumber($org)
            || (explode(':',(string)($settings['MAIN_INFO_SOCIETE_COUNTRY'] ?? ''))[1] ?? '')!=='NO') { $result['status']='Identity';return $result; }
        if (!array_key_exists(NorwayVatRegistration::SETTING,$settings)) { $result['status']='Missing';return $result; }
        $record=NorwayVatRegistration::inspect($settings[NorwayVatRegistration::SETTING],$entity,$org);
        $result['status']=NorwayVatRegistration::compare($record,$date);
        if ($record['status']==='Recorded') {
            foreach (array('start','confirmed_at','confirmed_by') as $key) { $result[$key]=$record[$key]; }
        }
        return $result;
    }
    public static function usesHistory($basis,$entity)
    {
        foreach($basis['settings'] ?? array() as $row){
            if((int)$row['entity']===$entity&&$row['name']===NorwayVatHistory::SETTING){return true;}
        }
        return false;
    }

    /** Version two is used only by new sales observations with an explicit history record. */
    public static function history($basis,$entity)
    {
        $result=self::evaluate($basis,$entity);$result['version']=2;$result['status']='HistoryUnavailable';
        $result['history_revision']=0;$result['period']=0;$result['end']='';
        // A failed history comparison must never reuse the original first-date result.
        $result['start']='';$result['confirmed_at']='';$result['confirmed_by']=0;
        if(($basis['source_complete']??null)!==true||!is_int($entity)||$entity<=0
            ||(int)($basis['posting']['entity']??0)!==$entity||(int)($basis['invoice']['entity']??0)!==$entity
            ||!NorwayPeriodSetup::validDate($basis['invoice']['datef']??null)){return $result;}
        $values=array();foreach($basis['settings']??array() as $row){
            if((int)$row['entity']!==$entity){continue;}
            if(array_key_exists($row['name'],$values)){$result['status']='HistoryInvalid';return $result;}$values[$row['name']]=$row['value'];
        }
        $org=preg_replace('/[ \t]/','',(string)($values['MAIN_INFO_SIREN']??''));
        if(!NorwayReadiness::validOrganisationNumber($org)||(explode(':',(string)($values['MAIN_INFO_SOCIETE_COUNTRY']??''))[1]??'')!=='NO'){$result['status']='Identity';return $result;}
        $state=NorwayVatHistory::inspect($values[NorwayVatHistory::SETTING]??null,$values[NorwayVatRegistration::SETTING]??null,$entity,$org);
        $comparison=NorwayVatHistory::compare($state,$basis['invoice']['datef']);$result['status']=$comparison['status'];
        $result['history_revision']=$comparison['revision']??0;
        if(isset($comparison['period'])){
            $period=$comparison['period'];$result['period']=$period['period'];$result['start']=$period['start'];$result['end']=$period['end'];
            $result['confirmed_at']=$period['at'];$result['confirmed_by']=$period['user'];
        }
        return $result;
    }

}
