<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require_once __DIR__.'/NorwayTaxMappings.php';
require_once __DIR__.'/NorwayInvoiceAmounts.php';
/** Read-only comparison with an explicitly chosen configuration revision. */
class NorwayInvoiceMapping
{
    public static function report($record,$revision,$invoice,$date)
    {
        if (!is_int($revision) || $revision<=0 || !is_array($record) || $revision>count($record['events'] ?? array())
            || ($record['entity'] ?? null)!==(int)$GLOBALS['conf']->entity || (int)($invoice->entity ?? 0)!==(int)$GLOBALS['conf']->entity) { throw new InvalidArgumentException('Mapping revision/entity'); }
        // Validate the whole history, including events after the selected revision.
        NorwayTaxMappings::validate($record,$record['entity'],$record['org'] ?? '');
        $selected=$record; $selected['events']=array_slice($record['events'],0,$revision);
        $rules=NorwayTaxMappings::validate($selected,$record['entity'],$record['org'])['active'];
        $rows=array();
        foreach (($invoice->lines ?? array()) as $line) {
            $e=NorwayInvoiceAmounts::evidence($line); $rule=null;
            if (!NorwayPeriodSetup::validDate($date)) { $status='UnknownDate'; }
            elseif ($e['code_state']!=='Recorded') { $status='Unavailable'; }
            elseif ($e['flags']!==0) { $status='Markers'; }
            else {
                $status='NoRule';
                foreach ($rules as $candidate) {
                    if (!$candidate['retire'] && $candidate['direction']==='sale' && $candidate['source']===$e['code']
                        && $candidate['start']<=$date && $date<=$candidate['end']) {
                        $rule=$candidate; $status='Candidate'; break;
                    }
                }
            }
            $rows[]=array('status'=>$status,'rule'=>$rule);
        }
        return array('revision'=>$revision,'at'=>$selected['events'][$revision-1]['at'],'rows'=>$rows);
    }
}
