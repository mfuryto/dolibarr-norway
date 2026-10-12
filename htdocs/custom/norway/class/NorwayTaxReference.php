<?php
// SPDX-License-Identifier: GPL-3.0-or-later
/** Reference membership only: never a mapping or an assessment of tax treatment. */
class NorwayTaxReference
{
    // Numeric identifiers from the official Standard Tax Codes CSV at this revision.
    // No descriptions, rates or treatment rules are redistributed or inferred.
    const SOURCE = 'https://github.com/Skatteetaten/saf-t/blob/05179521e435d82feb0b2d6c89a92a32a4f2d02f/Standard%20Tax%20Codes/CSV/Standard_Tax_Codes.csv';
    private const CODES = array('0','1','11','12','13','14','15','20','21','22','3','31','32','33','5','51','52','6','7','81','82','83','84','85','86','87','88','89','91','92');

    public static function inspect($evidence)
    {
        if (($evidence['code_state'] ?? '') !== 'Recorded') { return 'Unavailable'; }
        // Exact lexical match: whitespace, leading zeroes and local aliases are not mapped.
        return in_array($evidence['code'] ?? null, self::CODES, true) ? 'Listed' : 'Unlisted';
    }
}
