<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require_once __DIR__.'/NorwayTaxReference.php';
require_once __DIR__.'/NorwayPeriodSetup.php';
/** Pure, unsaved candidate comparison. No accounting classification or historical mapping. */
class NorwayTaxMappingPreview
{
    public static function plan($source,$target,$start,$end)
    {
        if (!is_string($source) || trim($source)==='' || strlen($source)>10 || preg_match('/[\x00-\x1f\x7f]/',$source)
            || !is_string($target) || NorwayTaxReference::inspect(array('code_state'=>'Recorded','code'=>$target))!=='Listed'
            || !NorwayPeriodSetup::validDate($start) || !NorwayPeriodSetup::validDate($end) || $start>$end) {
            throw new InvalidArgumentException('Mapping preview');
        }
        return array('source'=>$source,'target'=>$target,'start'=>$start,'end'=>$end);
    }

    public static function compare($plan,$date,$evidence)
    {
        // Revalidate on every entry; caller-supplied arrays cannot bypass plan validation.
        $plan=self::plan($plan['source'] ?? null,$plan['target'] ?? null,$plan['start'] ?? null,$plan['end'] ?? null);
        if (!NorwayPeriodSetup::validDate($date)) { return 'UnknownDate'; }
        if ($date<$plan['start'] || $date>$plan['end']) { return 'Outside'; }
        if (($evidence['code_state'] ?? '')!=='Recorded' || !is_string($evidence['code'] ?? null)) { return 'Unavailable'; }
        if ($evidence['code']!==$plan['source']) { return 'OtherCode'; }
        if (($evidence['flags'] ?? null)!==0) { return 'Markers'; }
        return 'Candidate';
    }
}
