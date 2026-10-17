<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require_once __DIR__.'/NorwayVatRates.php';
/** Missing tax fields on native manual VAT lines; never writes accounting amounts. */
class NorwayManualVat
{
    private static function query($db,$sql)
    {
        $result=$db->query($sql);
        if (!$result) { throw new RuntimeException('NorwayManualVatDatabase'); }
        return $result;
    }
    public static function allowed($user,$write=false)
    {
        return empty($user->socid) && !empty($user->id) && isModEnabled('accounting') && isModEnabled('norway')
            && $user->hasRight('accounting','mouvements',$write?'creer':'lire');
    }
    private static function entity($entity)
    {
        if ((int)$entity<=0 || (int)$entity!==(int)$GLOBALS['conf']->entity) { throw new RuntimeException('NorwayManualVatAccess'); }
    }
    public static function sourceHash($line)
    {
        $fields=array('rowid','entity','piece_num','doc_date','doc_type','doc_ref','fk_doc','fk_docdet','numero_compte','debit','credit','code_journal');
        $values=array(); foreach($fields as $key){$values[$key]=(string)$line->$key;}
        return hash('sha256',json_encode($values,JSON_THROW_ON_ERROR));
    }
    public static function current($line)
    {
        return !empty($line->source_hash) && hash_equals($line->source_hash,self::sourceHash($line));
    }
    public static function editToken($line)
    {
        return hash('sha256',self::sourceHash($line).'|'.($line->tax_code??'').'|'.($line->tax_base??'').'|'.($line->source_hash??''));
    }
    public static function lines($db,$entity,$piece,$user)
    {
        self::entity($entity);
        if(!self::allowed($user)){throw new RuntimeException('NorwayManualVatAccess');}
        $r=self::query($db,'SELECT b.*,v.tax_code,v.tax_base,v.source_hash FROM '.$db->prefix().'accounting_bookkeeping b LEFT JOIN '
            .$db->prefix().'norway_manual_vat v ON v.entity=b.entity AND v.fk_bookkeeping=b.rowid WHERE b.entity='.(int)$entity
            .' AND b.piece_num='.(int)$piece." AND COALESCE(b.doc_type,'') IN ('','manual') AND COALESCE(b.fk_doc,0)=0 AND COALESCE(b.fk_docdet,0)=0 ORDER BY b.rowid");
        $rows=array();while($line=$db->fetch_object($r)){$rows[]=$line;}return $rows;
    }
    public static function choices($db,$entity)
    {
        self::entity($entity);$result=array();
        foreach(NorwayVatRates::codes() as $spec){
            if(NorwayVatRates::defaultCode($db,$entity,$spec['rate'],$spec['type'])===null){continue;}
            $field=$spec['type']===1?'accountancy_code_sell':'accountancy_code_buy';
            $r=self::query($db,'SELECT '.$field.' AS account FROM '.$db->prefix().'c_tva WHERE entity='.(int)$entity." AND code='".$spec['code']."' AND fk_pays=(SELECT rowid FROM ".$db->prefix()."c_country WHERE code='NO')");
            $row=$db->fetch_object($r);if(!$row || trim((string)$row->account)===''){continue;}
            $spec['account']=$row->account;$result[$spec['code']]=$spec;
        }return $result;
    }
    public static function editable($db,$line,$user)
    {
        if(!self::allowed($user,true) || !empty($line->date_validated) || !empty($line->date_export)){return false;}
        $r=self::query($db,'SELECT COUNT(*) AS n FROM '.$db->prefix().'accounting_bookkeeping WHERE entity='.(int)$line->entity.' AND piece_num='.(int)$line->piece_num.' AND (date_validated IS NOT NULL OR date_export IS NOT NULL)');
        if((int)$db->fetch_object($r)->n>0){return false;}
        require_once DOL_DOCUMENT_ROOT.'/accountancy/class/bookkeeping.class.php';
        $native=new BookKeeping($db);
        return $native->validBookkeepingDate($db->jdate($line->doc_date))===1;
    }
    /** Source row lock and optimistic metadata token prevent concurrent edits from being adopted. */
    public static function save($db,$entity,$piece,$id,$code,$base,$token,$user)
    {
        self::entity($entity);
        if(!self::allowed($user,true) || !self::allowed($user)){throw new RuntimeException('NorwayManualVatAccess');}
        $db->begin();
        try {
            $r=self::query($db,'SELECT * FROM '.$db->prefix().'accounting_bookkeeping WHERE entity='.(int)$entity.' AND rowid='.(int)$id.' AND piece_num='.(int)$piece.' FOR UPDATE');
            $line=$db->fetch_object($r);
            if(!$line || !in_array((string)$line->doc_type,array('','manual'),true) || (int)$line->fk_doc!==0 || (int)$line->fk_docdet!==0){throw new RuntimeException('NorwayManualVatAccess');}
            $r=self::query($db,'SELECT tax_code,tax_base,source_hash FROM '.$db->prefix().'norway_manual_vat WHERE entity='.(int)$entity.' AND fk_bookkeeping='.(int)$id.' FOR UPDATE');
            $old=$db->fetch_object($r);foreach(array('tax_code','tax_base','source_hash') as $key){$line->$key=$old->$key??null;}
            if(!self::editable($db,$line,$user)){throw new RuntimeException('NorwayManualVatLocked');}
            if(!hash_equals(self::editToken($line),(string)$token)){throw new RuntimeException('NorwayManualVatChanged');}
            if($code===''){
                self::query($db,'DELETE FROM '.$db->prefix().'norway_manual_vat WHERE entity='.(int)$entity.' AND fk_bookkeeping='.(int)$id);
            } else {
                $spec=self::choices($db,$entity)[$code]??null;
                if(!$spec || $spec['account']!==$line->numero_compte || ((float)$line->debit!==0.0 && (float)$line->credit!==0.0)
                    || ((float)$line->debit===0.0 && (float)$line->credit===0.0)){throw new RuntimeException('NorwayManualVatCode');}
                $base=trim(str_replace(',','.',(string)$base));
                if($spec['type']===2){if($base!==''){throw new RuntimeException('NorwayManualVatBase');}$base='0';}
                else {
                    if(!preg_match('/^-?(?:0|[1-9][0-9]{0,12})(?:\.[0-9]{1,2})?$/D',$base)) {throw new RuntimeException('NorwayManualVatBase');}
                    $tax=(float)$line->credit-(float)$line->debit;
                    if(abs(round((float)$base*$spec['rate']/100,2)-$tax)>0.010001){throw new RuntimeException('NorwayManualVatBase');}
                }
                $hash=self::sourceHash($line);
                self::query($db,'INSERT INTO '.$db->prefix().'norway_manual_vat (entity,fk_bookkeeping,tax_code,tax_base,source_hash,fk_user,updated_at) VALUES ('
                    .(int)$entity.','.(int)$id.","."'".$db->escape($code)."',".$base.",'".$hash."',".(int)$user->id.',UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE '
                    ."tax_code=VALUES(tax_code),tax_base=VALUES(tax_base),source_hash=VALUES(source_hash),fk_user=VALUES(fk_user),updated_at=VALUES(updated_at)");
            }
            if($db->commit()<=0){throw new RuntimeException('NorwayManualVatDatabase');}
        } catch(Throwable $error){$db->rollback();throw $error;}
    }
    public static function render($db,$entity,$piece,$user,$langs)
    {
        $lines=self::lines($db,$entity,$piece,$user);if(!$lines){return '';}
        $choices=self::choices($db,$entity);$langs->load('norway@norway');
        $e=static fn($s)=>dol_escape_htmltag((string)$s);
        $html='<section id="norway-manual-vat" class="clearboth"><h2>'.$e($langs->trans('NorwayManualVatTitle')).'</h2><p>'.$e($langs->trans('NorwayManualVatHelp')).'</p>';
        $visible=0;
        foreach($lines as $line){
            $options=array_filter($choices,static fn($spec)=>$spec['account']===$line->numero_compte);
            if(!$options && empty($line->tax_code)){continue;}$visible++;
            $editable=self::editable($db,$line,$user);
            $html.='<div class="border" style="padding:0.7em;margin-bottom:0.7em"><p>'.$e($langs->trans('Line')).' '.$e($line->rowid).' · '.$e($line->numero_compte).' · '.$e($langs->trans('Debit')).' '.$e(price($line->debit)).' / '.$e($langs->trans('Credit')).' '.$e(price($line->credit)).'</p>';
            if(!empty($line->tax_code)){$html.='<p>'.$e($langs->trans('NorwayManualVatCodeLabel')).': '.$e($line->tax_code).(self::current($line)?'':' — '.$e($langs->trans('NorwayManualVatChanged'))).'</p>';}
            if($editable){
                $html.='<form method="POST" action="'.DOL_URL_ROOT.'/accountancy/bookkeeping/card.php"><input type="hidden" name="token" value="'.$e(newToken()).'">'
                    .'<input type="hidden" name="action" value="norway_save_manual_vat"><input type="hidden" name="piece_num" value="'.(int)$piece.'">'
                    .'<input type="hidden" name="norway_vat_line" value="'.(int)$line->rowid.'"><input type="hidden" name="norway_vat_token" value="'.self::editToken($line).'">';
                $html.='<label>'.$e($langs->trans('NorwayManualVatCodeLabel')).' <select name="norway_vat_code"><option value="">'.$e($langs->trans('None')).'</option>';
                foreach($options as $code=>$spec){$html.='<option value="'.$e($code).'"'.((string)$code===(string)$line->tax_code?' selected':'').'>'.$e($code).' · '.$e($spec['rate']).'% · '.$e($langs->trans($spec['type']===1?'NorwayManualVatOutput':'NorwayManualVatInput')).'</option>';}
                $html.='</select></label> <label>'.$e($langs->trans('NorwayManualVatBaseLabel')).' <input type="text" inputmode="decimal" name="norway_vat_base" size="14" value="'
                    .$e(in_array((string)$line->tax_code,array('3','31','33'),true)?rtrim(rtrim((string)$line->tax_base,'0'),'.'):'').'"></label> '
                    .'<button class="button" type="submit">'.$e($langs->trans('Save')).'</button></form>';
            } else {$html.='<p>'.$e($langs->trans('NorwayManualVatLocked')).'</p>';if(in_array((string)$line->tax_code,array('3','31','33'),true)){$html.='<p>'.$e($langs->trans('NorwayManualVatBaseLabel')).': '.$e(price($line->tax_base)).'</p>';}}
            $html.='</div>';
        }
        if(!$visible){return '';}
        // The native footer hook is after the content wrapper; put the section back inside the voucher.
        return $html.'</section><script>document.querySelector("#id-right > .fiche, body > .fiche")?.appendChild(document.getElementById("norway-manual-vat"));</script>';
    }
}
