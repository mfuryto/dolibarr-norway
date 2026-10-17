<?php
// SPDX-License-Identifier: GPL-3.0-or-later
/** External original invoice reference in the existing core ref_ext field. */
class NorwayCreditReference
{
    public static function valid($value)
    {
        return is_string($value) && trim($value)!=='' && strlen($value)<=255 && preg_match('//u',$value)
            && !preg_match('/[\x00-\x1f\x7f<>]/u',$value);
    }

    public static function external($invoice)
    {
        return ($invoice->element??'facture')==='facture' && (int)($invoice->type??0)===2
            && (int)($invoice->fk_facture_source??0)<=0 && self::valid($invoice->ref_ext??'');
    }

    public static function save($db,$entity,$id,$value,$user)
    {
        global $conf;
        if((int)$entity!==(int)$conf->entity || !$user || !empty($user->socid) || !$user->hasRight('facture','creer')
            || !isModEnabled('norway') || !self::valid($value)){throw new RuntimeException('NorwayCreditReferenceBlocked');}
        require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
        if(!$db->begin()){throw new RuntimeException('NorwayCreditReferenceBlocked');}
        try{
            $r=$db->query('SELECT rowid FROM '.$db->prefix().'facture WHERE rowid='.(int)$id.' AND entity='.(int)$entity.' FOR UPDATE');
            $invoice=new Facture($db);
            if(!$r || !$db->num_rows($r) || $invoice->fetch($id)<=0 || (int)$invoice->entity!==(int)$entity
                || (int)$invoice->statut!==0 || (int)$invoice->type!==2 || (int)$invoice->fk_facture_source>0){throw new RuntimeException('NorwayCreditReferenceBlocked');}
            if($invoice->setValueFrom('ref_ext',trim($value),'',null,'','',$user,'BILL_MODIFY')<=0 || !$db->commit()){throw new RuntimeException('NorwayCreditReferenceBlocked');}
        }catch(Throwable $error){$db->rollback();throw $error;}
    }

    public static function field($value,$langs)
    {
        $langs->load('norway@norway');
        return '<label>'.dol_escape_htmltag($langs->trans('NorwayCreditReferenceLabel')).' <input type="text" name="norway_original_invoice_ref" maxlength="255" value="'.dol_escape_htmltag((string)$value).'" class="maxwidth300"></label>'
            .'<p class="opacitymedium">'.dol_escape_htmltag($langs->trans('NorwayCreditReferenceHelp')).'</p>';
    }
}
