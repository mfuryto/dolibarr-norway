<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require_once __DIR__.'/NorwayTaxMappings.php';
/** Resolve explicit setup from captured sources only. Never assess lawful VAT treatment. */
class NorwayPostingTaxContext
{
    public static function evaluate($basis, $entity)
    {
        return self::resolve($basis,$entity,'sale',1);
    }

    public static function evaluatePurchase($basis,$entity)
    {
        $result=self::resolve($basis,$entity,'purchase',2);
        $result['direction']='purchase';
        if (($basis['posting']['doc_type'] ?? null)!=='supplier_invoice'
            || !preg_match('/^0(?:\.0+)?$/D',(string)($basis['invoice']['vat_reverse_charge'] ?? ''))
            || !preg_match('/^0(?:\.0+)?$/D',(string)($basis['invoice']['revenuestamp'] ?? ''))) {
            $result['status']='Unresolved';
            foreach ($result['lines'] as &$line) { $line['status']='Markers'; }unset($line);
        }
        return $result;
    }

    /** Version one sales semantics must remain unchanged for existing observations. */
    private static function resolve($basis,$entity,$direction,$version)
    {
        $result = array('version'=>$version, 'status'=>'Unresolved', 'mapping_revision'=>0, 'date'=>'', 'lines'=>array());
        if (!is_int($entity) || $entity<=0 || !is_array($basis) || ($basis['source_complete'] ?? null)!==true
            || !is_array($basis['lines'] ?? null) || !is_array($basis['settings'] ?? null)
            || (int)($basis['posting']['entity'] ?? 0)!==$entity
            || (int)($basis['invoice']['entity'] ?? 0)!==$entity) { return $result; }
        $date=(string)($basis['invoice']['datef'] ?? '');
        if (!NorwayPeriodSetup::validDate($date)) { return $result; }
        $result['date']=$date;
        // Entity zero is never a fallback for identity-bound mapping/registration records.
        $settings=array();
        foreach ($basis['settings'] ?? array() as $row) {
            if ((int)$row['entity']!==$entity) { continue; }
            if (array_key_exists($row['name'],$settings)) { return $result; }
            $settings[$row['name']]=$row['value'];
        }
        $org=preg_replace('/[ \t]/','',(string)($settings['MAIN_INFO_SIREN'] ?? ''));
        if (!NorwayReadiness::validOrganisationNumber($org)
            || (explode(':',(string)($settings['MAIN_INFO_SOCIETE_COUNTRY'] ?? ''))[1] ?? '')!=='NO') { return $result; }
        $raw=$settings[NorwayTaxMappings::SETTING] ?? '';
        if (!is_string($raw) || strlen($raw)>60000 || $raw==='') { return $result; }
        try {
            $record=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
            $state=NorwayTaxMappings::validate($record,$entity,$org);
        } catch (Throwable $error) { return $result; }
        $result['mapping_revision']=count($record['events']);
        $all=true;
        foreach ($basis['lines'] as $line) {
            $entry=array('line'=>(int)$line['rowid'],'source'=>(string)($line['vat_src_code'] ?? ''),
                'status'=>'MissingCode','target'=>'','rule'=>0,'rule_revision'=>0);
            if ($entry['source']!=='') {
                $entry['status']='NoRule';$matches=array();
                foreach ($state['active'] as $rule) {
                    if (!$rule['retire'] && $rule['direction']===$direction && $rule['source']===$entry['source']
                        && $rule['start']<=$date && $date<=$rule['end']) { $matches[]=$rule; }
                }
                if (count($matches)===1) {
                    $rule=$matches[0];$entry['target']=$rule['target'];$entry['rule']=$rule['id'];$entry['rule_revision']=$rule['revision'];
                    $entry['status']='Linked';
                }
            }
            // Special flags and local taxes need their own qualified treatment; no automatic approval.
            if (!preg_match('/^0(?:\.0+)?$/D',(string)($line['info_bits'] ?? ''))
                || !preg_match('/^0(?:\.0+)?$/D',(string)($line['localtax1_tx'] ?? ''))
                || !preg_match('/^0(?:\.0+)?$/D',(string)($line['localtax2_tx'] ?? ''))) { $entry['status']='Markers'; }
            $all=$all && $entry['status']==='Linked';$result['lines'][]=$entry;
        }
        $result['status']=$all && count($result['lines'])>0 ? 'Linked' : 'Unresolved';
        return $result;
    }
}
