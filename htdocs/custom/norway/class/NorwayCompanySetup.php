<?php
// SPDX-License-Identifier: GPL-3.0-or-later
/** Add Norwegian dictionary choices without selecting or changing any company's form. */
class NorwayCompanySetup
{
    // Stable internal Dolibarr codes; abbreviations/descriptions are from Brreg's public list.
    public static function forms()
    {
        return array(173001=>'ENK — Enkeltpersonforetak',173002=>'AS — Aksjeselskap',173003=>'ASA — Allmennaksjeselskap',
            173004=>'ANS — Ansvarlig selskap med solidarisk ansvar',173005=>'DA — Ansvarlig selskap med delt ansvar',
            173006=>'SA — Samvirkeforetak',173007=>'NUF — Norskregistrert utenlandsk foretak',173008=>'STI — Stiftelse',
            173009=>'FLI — Forening/lag/innretning',173010=>'IKS — Interkommunalt selskap',173011=>'KF — Kommunalt foretak',
            173012=>'SF — Statsforetak',173013=>'KS — Kommandittselskap',173014=>'SE — Europeisk selskap');
    }

    public static function install($db)
    {
        if ($db->begin() <= 0) { throw new RuntimeException('Company dictionary'); }
        try {
            $result=$db->query("SELECT rowid FROM ".$db->prefix()."c_country WHERE code='NO' FOR UPDATE");
            $country=$result ? $db->fetch_object($result) : null;
            if (!$country) { throw new RuntimeException('Company dictionary'); }
            foreach (self::forms() as $code=>$label) {
                $result=$db->query('SELECT fk_pays,libelle FROM '.$db->prefix().'c_forme_juridique WHERE code='.(int)$code.' FOR UPDATE');
                if (!$result) { throw new RuntimeException('Company dictionary'); }
                $existing=$db->fetch_object($result);
                if ($existing) {
                    // Inactive choices stay inactive. Never overwrite an existing code's meaning.
                    if ((int)$existing->fk_pays!==(int)$country->rowid || $existing->libelle!==$label) {
                        throw new RuntimeException('Norwegian legal form code conflict; existing dictionary preserved');
                    }
                    continue;
                }
                if (!$db->query("INSERT INTO ".$db->prefix()."c_forme_juridique (code,fk_pays,libelle,active,module) VALUES ("
                    .(int)$code.",".(int)$country->rowid.",'".$db->escape($label)."',1,'norway')")) {
                    throw new RuntimeException('Company dictionary');
                }
            }
            if ($db->commit() <= 0) { throw new RuntimeException('Company dictionary'); }
        } catch (Throwable $error) { $db->rollback(); throw $error; }
    }
}
