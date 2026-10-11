<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require_once __DIR__.'/NorwayReadiness.php';
/** Explicit, read-only lookup of the current company. No persistent cache. */
class NorwayRegistry
{
    public static function organisationNumber($value, $country)
    {
        if ($country !== 'NO' || !NorwayReadiness::validOrganisationNumber($value)) {
            throw new RuntimeException('Invalid');
        }
        return preg_replace('/[ \t]/', '', (string) $value);
    }

    public static function parse($status, $body, $number)
    {
        if ($status === 404) { throw new RuntimeException('NotFound'); }
        if ($status === 410) { throw new RuntimeException('Removed'); }
        if ($status !== 200 || strlen($body) > 262144) { throw new RuntimeException('Unavailable'); }
        $data = json_decode($body, true);
        if (!is_array($data) || ($data['organisasjonsnummer'] ?? null) !== $number) {
            throw new RuntimeException('Unavailable');
        }
        if (isset($data['slettedato'])) { throw new RuntimeException('Deleted'); }
        if (!is_bool($data['registrertIMvaregisteret'] ?? null)
            || !is_string($data['navn'] ?? null) || trim($data['navn']) === '') {
            throw new RuntimeException('Unavailable');
        }
        return array('number' => $number, 'name' => $data['navn'],
            'vat' => $data['registrertIMvaregisteret'], 'time' => gmdate('Y-m-d H:i:s').' UTC');
    }

    public static function fetch($value, $country)
    {
        $number = self::organisationNumber($value, $country);
        $curl = curl_init('https://data.brreg.no/enhetsregisteret/api/enheter/'.$number);
        $body = '';
        curl_setopt_array($curl, array(CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 8,
            CURLOPT_FOLLOWLOCATION => false, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_USERAGENT => 'dolibarr-norway/0.23.0',
            CURLOPT_HTTPHEADER => array('Accept: application/vnd.brreg.enhetsregisteret.enhet.v2+json'),
            CURLOPT_WRITEFUNCTION => static function ($handle, $chunk) use (&$body) {
                if (strlen($body) + strlen($chunk) > 262144) { return 0; }
                $body .= $chunk; return strlen($chunk);
            }));
        $result = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        if ($result === false) { throw new RuntimeException('Unavailable'); }
        return self::parse($status, $body, $number);
    }
}
