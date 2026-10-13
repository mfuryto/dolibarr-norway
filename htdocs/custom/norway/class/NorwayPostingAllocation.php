<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require_once __DIR__.'/NorwayInvoiceAllocation.php';
/** Explicit profile comparison against retained sources, never historical VAT approval. */
class NorwayPostingAllocation
{
    /** Shared retained-source identity and drift checks for sales and purchases. */
    public static function sources($observations,$ledger)
    {
        if ($ledger['truncated'] || count($ledger['rows'])>200 || count($observations)>200) { throw new InvalidArgumentException('Retained source capacity'); }
        if (!$observations || !$ledger['rows']) { return null; }
        $basis=null;$saved=array();$sourceChanged=false;
        foreach ($observations as $observation) {
            $data=$observation['data'] ?? null;
            if (!$data || !in_array($observation['status'],array('Same','Changed'),true) || ($data['basis']['source_complete'] ?? null)!==true) { return null; }
            $candidate=$data['basis'];$posting=$candidate['posting'];unset($candidate['posting']);
            if ($basis===null) { $basis=$candidate; }
            if ($candidate!==$basis || isset($saved[(int)$posting['rowid']])) { return null; }
            $saved[(int)$posting['rowid']]=$posting;$sourceChanged=$sourceChanged || $observation['status']==='Changed';
        }
        if (count($saved)!==count($ledger['rows'])) { return null; }
        $header=(object)$basis['invoice'];$header->buyer_country=$basis['party']['country'] ?? '';
        $header->seller_country='';
        foreach ($basis['settings'] as $setting) {
            if ((int)$setting['entity']===(int)$header->entity && $setting['name']==='MAIN_INFO_SOCIETE_COUNTRY') { $header->seller_country=explode(':',$setting['value'])[1] ?? ''; }
        }
        $lines=array_map(static function($line){return (object)$line;},$basis['lines']);$lineIds=array();
        foreach ($lines as $line) { $lineIds[(int)$line->rowid]=true; }
        $rows=array();$seen=array();
        foreach ($ledger['rows'] as $actual) {
            $id=(int)$actual->rowid;$original=$saved[$id] ?? null;
            if (!$original || isset($seen[$id])) { return null; }$seen[$id]=true;
            foreach (array('code_journal','piece_num','doc_date','doc_ref','fk_docdet','numero_compte') as $key) {
                if ((string)$actual->$key!==(string)$original[$key]) { return null; }
            }
            foreach (array('debit','credit') as $key) {
                if (NorwayInvoiceReconciliation::amount($actual->$key)!==NorwayInvoiceReconciliation::amount($original[$key])) { return null; }
            }
            $row=clone $actual;
            $row->trace=(string)$row->doc_ref!==$header->ref || trim((string)$row->code_journal)==='' || (int)$row->piece_num<=0 ? 'Reference' :
                ($row->fk_docdet===null ? 'MissingLine' : ((int)$row->fk_docdet===0 ? 'Aggregated' : (isset($lineIds[(int)$row->fk_docdet]) ? 'Linked' : 'MissingLine')));
            $rows[]=$row;
        }
        return array('header'=>$header,'lines'=>$lines,'ledger'=>array('truncated'=>false,'rows'=>$rows),'basis'=>$basis,'source_changed'=>$sourceChanged);
    }
    public static function evaluate($observations,$ledger,$profile)
    {
        if (!isset(NorwayInvoiceAllocation::profiles()[$profile]) || $ledger['truncated'] || count($ledger['rows'])>200 || count($observations)>200) { throw new InvalidArgumentException('Retained allocation scope'); }
        $sources=self::sources($observations,$ledger);
        if (!$sources) { return array('status'=>'Review','source_changed'=>false,'totals'=>array(),'line_accounts'=>null); }
        $header=$sources['header'];$lines=$sources['lines'];$current=$sources['ledger'];$sourceChanged=$sources['source_changed'];
        $result=NorwayInvoiceAllocation::evaluate($header,$lines,$current,$profile);
        $result['line_accounts']=NorwayInvoiceAllocation::lineAccounts($lines,$current,$profile);
        if ($result['status']==='Match' && $result['line_accounts']['status']!=='Match') { $result['status']=$result['line_accounts']['status']; }
        $result['source_changed']=$sourceChanged;
        return $result;
    }
}
