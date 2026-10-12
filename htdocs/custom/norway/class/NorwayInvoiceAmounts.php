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

    /** Stored source fields only; no lookup in today's rate dictionary. */
    public static function evidence($line)
    {
        $available = property_exists($line, 'vat_src_code') && ($line->vat_src_code === null || is_string($line->vat_src_code) || is_int($line->vat_src_code));
        $code = $available ? (string) $line->vat_src_code : '';
        $state = !$available ? 'Unknown' : (trim($code) === '' ? 'Missing' : 'Recorded');
        $raw = $line->info_bits ?? null;
        $flags = (is_int($raw) || is_string($raw)) && preg_match('/^[0-9]+$/D', (string) $raw)
            ? filter_var($raw, FILTER_VALIDATE_INT, array('options'=>array('min_range'=>0))) : false;
        return array('code'=>$code, 'code_state'=>$state, 'flags'=>$flags === false ? null : $flags);
    }

    public static function report($invoice)
    {
        $rows = array(); $net = 0.0; $vat = 0.0; $valid = true;
        foreach (($invoice->lines ?? array()) as $line) {
            $base = self::number($line->total_ht ?? null);
            $tax = self::number($line->total_tva ?? null);
            $rate = self::number($line->tva_tx ?? null);
            $status = ($base === null || $tax === null || $rate === null) ? 'Invalid' : (($rate == 0 || $tax == 0) ? 'ZeroReview' : 'Review');
            $rows[] = array('line'=>(int) ($line->id ?? 0), 'net'=>$base, 'vat'=>$tax, 'rate'=>$rate, 'status'=>$status, 'evidence'=>self::evidence($line));
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
