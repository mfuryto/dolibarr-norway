<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require_once __DIR__.'/NorwayPeriodSetup.php';
require_once __DIR__.'/NorwayInvoiceAmounts.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
/** Bounded read-only invoice amount diagnostics; no tax-return totals. */
class NorwayInvoicePeriod
{
    const LIMIT = 200;
    public static function report($db, $entity, $start, $end)
    {
        if ((int) $entity <= 0 || (int) $GLOBALS['conf']->entity !== (int) $entity
            || !NorwayPeriodSetup::validDate($start) || !NorwayPeriodSetup::validDate($end) || $start > $end) { throw new InvalidArgumentException('Period/entity'); }
        if ($db->begin() <= 0) { throw new RuntimeException('Period read'); }
        try {
            $result = $db->query("SELECT rowid FROM ".$db->prefix()."facture WHERE entity=".(int) $entity
                ." AND datef>='".$db->escape($start)."' AND datef<='".$db->escape($end)."' ORDER BY datef,rowid".$db->plimit(self::LIMIT + 1));
            if (!$result) { throw new RuntimeException('Period read'); }
            $ids = array(); while ($row = $db->fetch_object($result)) { $ids[] = (int) $row->rowid; }
            $truncated = count($ids) > self::LIMIT; $rows = array();
            foreach (array_slice($ids, 0, self::LIMIT) as $id) {
                $invoice = new Facture($db);
                if ($invoice->fetch($id) <= 0 || (int) $invoice->entity !== (int) $entity) { throw new RuntimeException('Invoice read'); }
                $amounts = NorwayInvoiceAmounts::report($invoice); $zero = 0;
                foreach ($amounts['rows'] as $line) { if ($line['status'] === 'ZeroReview') { $zero++; } }
                $rows[] = array('id'=>$id, 'ref'=>$invoice->ref, 'date'=>dol_print_date($invoice->date, '%Y-%m-%d'),
                    'type'=>(int) $invoice->type, 'status'=>(int) $invoice->statut, 'currency'=>(string) $invoice->multicurrency_code,
                    'net'=>$amounts['header_net'], 'vat'=>$amounts['header_vat'], 'amount_status'=>$amounts['status'], 'zero'=>$zero);
            }
            if ($db->commit() <= 0) { throw new RuntimeException('Period read'); }
            return array('rows'=>$rows, 'truncated'=>$truncated);
        } catch (Throwable $error) { $db->rollback(); throw $error; }
    }
}
