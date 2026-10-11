<?php
// SPDX-License-Identifier: GPL-3.0-or-later
/** Read-only comparison of stored net/VAT amounts, without selecting tax treatment. */
class NorwayInvoiceAmounts
{
    private static function number($value)
    {
        if (!is_numeric($value) || !is_finite((float) $value)) { return null; }
        return (float) $value;
    }

    public static function report($invoice)
    {
        $rows = array(); $net = 0.0; $vat = 0.0; $valid = true;
        foreach (($invoice->lines ?? array()) as $line) {
            $base = self::number($line->total_ht ?? null);
            $tax = self::number($line->total_tva ?? null);
            $rate = self::number($line->tva_tx ?? null);
            $status = ($base === null || $tax === null || $rate === null) ? 'Invalid' : (($rate == 0 || $tax == 0) ? 'ZeroReview' : 'Review');
            $rows[] = array('line'=>(int) ($line->id ?? 0), 'net'=>$base, 'vat'=>$tax, 'rate'=>$rate, 'status'=>$status);
            if ($base === null || $tax === null || $rate === null) { $valid = false; }
            else { $net += $base; $vat += $tax; }
        }
        $headerNet = self::number($invoice->total_ht ?? null);
        $headerVat = self::number($invoice->total_tva ?? null);
        $status = !$rows ? 'Missing' : ((!$valid || $headerNet === null || $headerVat === null || !is_finite($net) || !is_finite($vat)) ? 'Invalid' :
            ((abs($net - $headerNet) > 0.010000001 || abs($vat - $headerVat) > 0.010000001) ? 'Different' : 'Match'));
        return array('rows'=>$rows, 'net'=>$valid && $rows && is_finite($net) ? $net : null,
            'vat'=>$valid && $rows && is_finite($vat) ? $vat : null,
            'header_net'=>$headerNet, 'header_vat'=>$headerVat, 'status'=>$status);
    }
}
