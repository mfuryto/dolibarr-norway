<?php
// SPDX-License-Identifier: GPL-3.0-or-later
/** Supported Norwegian sales configuration; historical documents are never rewritten. */
class NorwayPolicy
{
    public static function apply($db, $conf)
    {
        require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
        require_once __DIR__.'/NorwayCompanySetup.php';
        NorwayCompanySetup::install($db);
        if ($conf->currency !== 'NOK') {
            // Older foundation releases stored an informational NOK setting, while
            // core actually reads MAIN_MONNAIE. Correct only an unused installation.
            if ($conf->currency !== 'EUR' || getDolGlobalString('MAIN_INFO_SOCIETE_DEVISE') !== 'NOK') {
                throw new RuntimeException('Norway requires NOK as company currency; no automatic conversion is performed.');
            }
            foreach (array('facture', 'facture_fourn', 'propal', 'commande', 'commande_fournisseur',
                'accounting_bookkeeping', 'bank', 'paiement', 'paiementfourn') as $table) {
                $res = $db->query('SELECT COUNT(*) AS n FROM '.MAIN_DB_PREFIX.$table);
                if (!$res || (int) $db->fetch_object($res)->n !== 0) {
                    throw new RuntimeException('Existing financial activity found with a non-NOK base currency. Retain backup; review currency migration before continuing.');
                }
            }
            if (dolibarr_set_const($db, 'MAIN_MONNAIE', 'NOK', 'chaine', 0, '', $conf->entity) <= 0) {
                throw new RuntimeException('Could not initialise core NOK currency');
            }
            $conf->setValues($db);
        }
        foreach (array('MAIN_MULTILANGS', 'BANK_DISABLE_CHECK_DEPOSIT', 'MAIN_ENABLE_OVERWRITE_TRANSLATION') as $name) {
            if (dolibarr_set_const($db, $name, '1', 'chaine', 0, '', $conf->entity) <= 0) {
                throw new RuntimeException('Could not apply Norwegian sales configuration');
            }
            $conf->global->$name = '1';
        }
        if (getDolGlobalString('INVOICE_CREDIT_NOTE_STANDALONE')==='') {
            if (dolibarr_set_const($db,'INVOICE_CREDIT_NOTE_STANDALONE','1','chaine',0,'Core credit notes for system migration',$conf->entity)<=0) { throw new RuntimeException('Could not enable core standalone credit notes'); }
            $conf->global->INVOICE_CREDIT_NOTE_STANDALONE='1';
        }
        if (!$db->query("UPDATE ".MAIN_DB_PREFIX."c_paiement SET active=0 WHERE code='CHQ' AND entity=".(int) $conf->entity)) {
            throw new RuntimeException('Could not disable cheque payment selection');
        }
        // Use core translation overrides scoped to the active entity and language.
        $translations=array('nb_NO'=>array('ProfId1NO' => 'Organisasjonsnummer', 'ProfId1ShortNO' => 'Org.nr.',
            'VATIntra' => 'MVA-nummer (ved registrering)', 'JuridicalStatus' => 'Organisasjonsform',
            'MenuNewThirdParty' => 'Ny kunde/leverandør', 'NewThirdParty' => 'Ny kunde eller leverandør',
            'Prospects' => 'Potensielle kunder', 'Prospect' => 'Potensiell kunde',
            'MenuNewProspect' => 'Ny potensiell kunde', 'ThirdPartyProspects' => 'Potensielle kunder',
            'ThirdPartyProspectsStats' => 'Potensielle kunder',
            'ThirdParties' => 'Kunder og leverandører', 'ThirdParty' => 'Kunde/leverandør',
            'ThirdPartiesArea' => 'Kunder og leverandører / Kontakter', 'ThirdPartyName' => 'Kunde-/leverandørnavn',
            'ThirdPartyType' => 'Kunde-/leverandørtype', 'ThirdPartyAddress' => 'Kunde-/leverandøradresse',
            'ThirdPartyContacts' => 'Kunde-/leverandørkontakter', 'ThirdPartyContact' => 'Kunde-/leverandørkontakt',
            'ThirdPartyEmail' => 'Kunde-/leverandørens e-post',
            'NonPercuRecuperable' => 'Fransk NPR-ordning', 'VATNPR' => 'Fransk NPR-ordning'),
            'en_US'=>array('NonPercuRecuperable'=>'French NPR scheme','VATNPR'=>'French NPR scheme'));
        foreach ($translations as $locale => $entries) {
            foreach ($entries as $key => $value) {
                $where = "entity=".(int) $conf->entity." AND lang='".$db->escape($locale)."' AND transkey='".$db->escape($key)."'";
                $res = $db->query('SELECT rowid FROM '.MAIN_DB_PREFIX.'overwrite_trans WHERE '.$where);
                if (!$res) { throw new RuntimeException('Could not read Norwegian terminology'); }
                if ($db->num_rows($res)) {
                    $sql = "UPDATE ".MAIN_DB_PREFIX."overwrite_trans SET transvalue='".$db->escape($value)."' WHERE ".$where;
                } else {
                    $sql = "INSERT INTO ".MAIN_DB_PREFIX."overwrite_trans (entity,lang,transkey,transvalue) VALUES (".
                        (int) $conf->entity.",'".$db->escape($locale)."','".$db->escape($key)."','".$db->escape($value)."')";
                }
                if (!$db->query($sql)) { throw new RuntimeException('Could not configure Norwegian terminology'); }
            }
        }
        if (isModEnabled('multicurrency')) {
            $error = unActivateModule('modMultiCurrency');
            if ($error !== '') { throw new RuntimeException('Could not disable document multicurrency'); }
        }
    }
}
