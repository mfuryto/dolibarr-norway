<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require_once DOL_DOCUMENT_ROOT.'/core/modules/societe/mod_codeclient_elephant.php';
require_once __DIR__.'/../../../class/NorwayNumbering.php';
/** Five-digit customer/supplier codes; unchanged legacy codes remain valid. */
class mod_codeclient_norway extends mod_codeclient_elephant
{
    public $name='Norway';
    public function __construct($db)
    {
        parent::__construct($db);
        $this->code_modifiable=0; $this->code_modifiable_invalide=0; $this->code_modifiable_null=1;
    }
    public function verif_prefixIsUsed() { return 0; }
    public function info($langs) { $langs->load('norway@norway'); return $langs->trans('NorwayNumberingPartnerModelInfo'); }
    public function getNextValue($objsoc='',$type=-1)
    {
        global $conf;
        try {
            if (!isModEnabled('norway') || (is_object($objsoc) && (int)($objsoc->entity ?? 0)>0 && (int)$objsoc->entity!==(int)$conf->entity)) { throw new RuntimeException('Partner numbering entity'); }
            if ($type===-1 && is_object($objsoc)) { $type=empty($objsoc->client) && !empty($objsoc->fournisseur) ? 1 : 0; }
            if (!in_array($type,array(0,1),true)) { throw new InvalidArgumentException('Partner type'); }
            $kind=$type===1 ? 'supplier' : 'customer'; $record=NorwayNumbering::record($this->db,(int)$conf->entity,$kind);
            return NorwayNumbering::next($this->db,(int)$conf->entity,$record['input'],$kind);
        } catch (Throwable $error) { $this->error='NotConfigured'; return ''; }
    }
    public function verif($db,&$code,$soc,$type)
    {
        global $conf;
        if (!isModEnabled('norway') || !in_array($type,array(0,1),true) || !is_object($soc)
            || ((int)($soc->entity ?? 0)>0 && (int)$soc->entity!==(int)$conf->entity)) { return -6; }
        $field=$type===1 ? 'code_fournisseur' : 'code_client'; $id=(int)($soc->id ?? 0);
        if ($id>0) {
            $r=$db->query('SELECT '.$field.' AS code FROM '.$db->prefix().'societe WHERE entity='.(int)$conf->entity.' AND rowid='.$id);
            if (!$r) { return -6; } $old=$db->fetch_object($r);
            if (!$old) { return -6; }
            // Preserve even non-numeric or empty historical codes on an unrelated edit.
            if ((string)$old->code===(string)$code) { return 0; }
        }
        if ($code===null || $code==='') { return -2; }
        if (!is_string($code) || !preg_match('/^[0-9]{5}$/D',$code) || (int)$code<1) { return -1; }
        $r=$db->query('SELECT rowid FROM '.$db->prefix().'societe WHERE entity='.(int)$conf->entity.' AND '.$field."='".$db->escape($code)."' AND rowid<>".$id.' LIMIT 1');
        if (!$r) { return -6; } return $db->num_rows($r)>0 ? -3 : 0;
    }
}
