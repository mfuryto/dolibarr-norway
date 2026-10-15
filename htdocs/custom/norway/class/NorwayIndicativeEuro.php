<?php
// SPDX-License-Identifier: GPL-3.0-or-later
/** Read-only indicative pricing. Never writes currency tables or accounting documents. */
class NorwayIndicativeEuro
{
    public static function parse($csv, $today)
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $csv); rewind($stream);
        $header = fgetcsv($stream, 0, ';', '"', '');
        $latest = null;
        while (($values = fgetcsv($stream, 0, ';', '"', '')) !== false) {
            if (!$header || count($values) !== count($header)) { continue; }
            $row = array_combine($header, $values);
            $date = $row['TIME_PERIOD'] ?? '';
            $rate = $row['OBS_VALUE'] ?? '';
            if (($row['BASE_CUR'] ?? '') !== 'EUR' || ($row['QUOTE_CUR'] ?? '') !== 'NOK'
                || ($row['FREQ'] ?? '') !== 'B' || ($row['TENOR'] ?? '') !== 'SP'
                || ($row['UNIT_MULT'] ?? '') !== '0'
                || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date)
                || date('Y-m-d', strtotime($date)) !== $date || $date > $today
                || !is_numeric($rate) || !is_finite((float) $rate) || (float) $rate <= 0) { continue; }
            if ($latest === null || $date > $latest['date']) { $latest = array('date' => $date, 'nok_per_eur' => (float) $rate); }
        }
        fclose($stream);
        if (!$latest || (strtotime($today)-strtotime($latest['date'])) > 7*86400) {
            throw new RuntimeException('No recent valid EUR/NOK reference rate');
        }
        return $latest;
    }

    public static function fetch()
    {
        $today = gmdate('Y-m-d');
        $url = 'https://data.norges-bank.no/api/data/EXR/B.EUR.NOK.SP?format=csv&locale=en&startPeriod='.
            gmdate('Y-m-d', strtotime($today.' -15 days')).'&endPeriod='.$today;
        $curl = curl_init($url);
        $body = '';
        curl_setopt_array($curl, array(CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 8,
            CURLOPT_FOLLOWLOCATION => false, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_USERAGENT => 'dolibarr-norway/0.63.1',
            CURLOPT_WRITEFUNCTION => static function ($handle, $chunk) use (&$body) {
                if (strlen($body) + strlen($chunk) > 262144) { return 0; }
                $body .= $chunk; return strlen($chunk);
            }));
        $result = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        if ($result === false || $status !== 200) { throw new RuntimeException('Rate service unavailable'); }
        return self::parse($body, $today);
    }

    public static function estimate($nok, $rate)
    {
        if (!is_numeric($nok) || !is_finite((float) $nok) || (float) $nok < 0 || (float) $nok > 1000000000
            || !is_finite((float) $rate) || (float) $rate <= 0) {
            throw new InvalidArgumentException('Invalid price or rate');
        }
        return round((float) $nok / (float) $rate, 2);
    }
}
