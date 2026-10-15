<?php
// SPDX-License-Identifier: GPL-3.0-or-later
/** Norwegian organisation-number checksum used by company and document setup. */
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

}
