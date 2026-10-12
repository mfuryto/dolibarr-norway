<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require_once __DIR__.'/NorwayDefaultAccounts.php';
/** Complete core default-account inventory; explicit purpose-bound choices only. */
class NorwayStandardAccounts
{
    public static function catalog()
    {
        $data=json_decode(file_get_contents(__DIR__.'/../data/standard-accounts-v1.json'),true,512,JSON_THROW_ON_ERROR);
        if ($data['version']!==1 || $data['chart']!==NorwayChartProfile::VERSION || $data['core']!=='24.0.1') { throw new RuntimeException('Standard accounts'); }
        return $data['rules'];
    }
    public static function validate($choices)
    {
        if (!is_array($choices) || !$choices || count($choices)>50) { throw new InvalidArgumentException('Standard account choices'); }
        $catalog=self::catalog();$selected=array();
        foreach ($choices as $key=>$value) {
            if (!isset($catalog[$key]) || !is_string($value) || !in_array($value,$catalog[$key]['options'],true)) { throw new InvalidArgumentException('Standard account choice'); }
            $selected[$key]=$value;
        }
        ksort($selected);return $selected;
    }
}
