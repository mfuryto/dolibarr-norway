<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require_once __DIR__.'/NorwayVatRegistration.php';
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
}
