<?php
// SPDX-License-Identifier: GPL-3.0-or-later
/** Read-only diagnostics. A passing check never certifies accounting compliance. */
class NorwayReadiness
{
    public static function validOrganisationNumber($value)
    {
        $value = preg_replace('/[ \t]/', '', (string) $value);
        if (!preg_match('/^[0-9]{9}$/D', $value) || $value === '000000000') {
            return false;
        }
        $weights = array(3, 2, 7, 6, 5, 4, 3, 2);
        $sum = 0;
        foreach ($weights as $i => $weight) {
            $sum += (int) $value[$i] * $weight;
        }
        $check = (11 - ($sum % 11)) % 11;
        return $check !== 10 && $check === (int) $value[8];
    }

    public static function evaluate($company, $rates)
    {
        $checks = array(
            'Name' => trim((string) ($company['name'] ?? '')) !== '',
            'OrganisationNumber' => self::validOrganisationNumber($company['idprof1'] ?? ''),
            'Country' => ($company['country_code'] ?? '') === 'NO',
            'Currency' => ($company['currency'] ?? '') === 'NOK',
            'Address' => trim((string) ($company['address'] ?? '')) !== ''
                && trim((string) ($company['zip'] ?? '')) !== ''
                && trim((string) ($company['town'] ?? '')) !== '',
        );
        foreach (array(25, 15, 12) as $rate) {
            $sell = $buy = false;
            foreach ($rates as $entry) {
                if ((int) $entry['active'] !== 1 || (float) $entry['taux'] !== (float) $rate) {
                    continue;
                }
                $sell = $sell || in_array((int) $entry['type_vat'], array(0, 1), true);
                $buy = $buy || in_array((int) $entry['type_vat'], array(0, 2), true);
            }
            $checks['Rate'.$rate] = $sell && $buy;
        }
        $historical = false;
        foreach ($rates as $entry) {
            $historical = $historical || ((int) $entry['active'] === 1
                && in_array((float) $entry['taux'], array(8.0, 14.0), true));
        }
        $checks['HistoricalRates'] = !$historical;
        return $checks;
    }
}
