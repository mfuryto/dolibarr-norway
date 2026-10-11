<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require_once __DIR__.'/NorwayReadiness.php';
/** Presence checks on current invoice data; no edits or compliance certification. */
class NorwayInvoiceCheck
{
    private static function text($value)
    {
        $clean = preg_replace('/[\s\x{00a0}]+/u', '', html_entity_decode(strip_tags((string) $value), ENT_QUOTES, 'UTF-8'));
        return is_string($clean) && $clean !== '';
    }

    public static function evaluate($invoice, $seller, $customer, $source = null)
    {
        $address = static function ($party) {
            return self::text($party->address ?? '') && self::text($party->zip ?? '') && self::text($party->town ?? '');
        };
        $descriptions = !empty($invoice->lines);
        foreach ($invoice->lines ?? array() as $line) {
            $descriptions = $descriptions && (self::text($line->desc ?? '') || self::text($line->label ?? '') || self::text($line->product_label ?? ''));
        }
        $credit = (int) $invoice->type === 2;
        $sourceValid = !$credit || ($source && (int) $source->id === (int) $invoice->fk_facture_source
            && (int) $source->entity === (int) $invoice->entity
            && (int) $source->socid === (int) $invoice->socid
            && (int) $source->statut > 0 && (int) $source->type !== 2);
        $present = array(
            'SellerCountry' => ($seller->country_code ?? '') === 'NO',
            'SellerName' => self::text($seller->name ?? ''),
            'SellerOrganisation' => NorwayReadiness::validOrganisationNumber($seller->idprof1 ?? ''),
            'SellerAddress' => $address($seller),
            'Customer' => self::text($customer->name ?? '') && ($address($customer) || self::text($customer->idprof1 ?? '')),
            'Date' => !empty($invoice->date),
            'DueDate' => !empty($invoice->date_lim_reglement),
            'Currency' => ($invoice->multicurrency_code ?? '') === 'NOK',
            'Descriptions' => $descriptions,
            'CreditSource' => $sourceValid,
        );
        $result = array();
        foreach ($present as $key => $ok) { $result[$key] = $ok ? 'Present' : 'Missing'; }
        // A reference alone cannot prove automatic assignment or sequence completeness.
        $result['Numbering'] = 'Review';
        $result['Delivery'] = 'Review';
        $result['VAT'] = 'Review';
        $result['PDF'] = 'Review';
        if (!$credit) { unset($result['CreditSource']); }
        if ($credit && empty($invoice->date_lim_reglement)) { $result['DueDate'] = 'Review'; }
        return $result;
    }
}
