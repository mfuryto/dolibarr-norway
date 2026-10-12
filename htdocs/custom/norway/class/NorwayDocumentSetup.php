<?php
// SPDX-License-Identifier: GPL-3.0-or-later
/** Read-only configured-model diagnostics; never requests/reserves a document number. */
class NorwayDocumentSetup
{
    public static function settings()
    {
        return array('FACTURE_ADDON','PROPALE_ADDON','FACTURE_ADDON_PDF','PROPALE_ADDON_PDF',
            'FACTURE_ADDON_PDF_0','FACTURE_ADDON_PDF_1','FACTURE_ADDON_PDF_2','FACTURE_ADDON_PDF_3','FACTURE_ADDON_PDF_5',
            'FACTURE_MERCURE_MASK_INVOICE','FACTURE_MERCURE_MASK_CREDIT','PROPALE_SAPHIR_MASK','MAIN_MULTILANGS','MAIN_LANG_DEFAULT','INVOICE_USE_SITUATION');
    }

    public static function evaluate($settings,$models,$currency)
    {
        $rows=array();
        foreach (array('FACTURE_ADDON'=>array('mod_facture_terre','mod_facture_mars','mod_facture_mercure','mod_facture_norway'),
            'PROPALE_ADDON'=>array('mod_propale_marbre','mod_propale_saphir','mod_propale_norway')) as $key=>$known) {
            $value=trim((string)($settings[$key] ?? ''));
            $name=preg_replace('/\.php$/D','',$value);
            $rows[]=array('key'=>$key,'value'=>$value,'status'=>$value==='' ? 'Missing' : (in_array($name,$known,true) ? 'Configured' : 'Review'));
            $masks=$name==='mod_facture_mercure' ? array('FACTURE_MERCURE_MASK_INVOICE','FACTURE_MERCURE_MASK_CREDIT') : ($name==='mod_propale_saphir' ? array('PROPALE_SAPHIR_MASK') : array());
            foreach ($masks as $mask) {
                $value=trim((string)($settings[$mask] ?? ''));
                // A nonempty mask still requires a real synthetic numbering test; do not guess its grammar.
                $rows[]=array('key'=>$mask,'value'=>$value,'status'=>$value==='' ? 'Missing' : 'Review');
            }
        }
        foreach (array('FACTURE_ADDON_PDF'=>'invoice','PROPALE_ADDON_PDF'=>'propal') as $key=>$type) {
            $keys=array($key);
            if ($type==='invoice') { foreach (array(0,1,2,3) as $i) { $keys[]='FACTURE_ADDON_PDF_'.$i; } if ((int)($settings['INVOICE_USE_SITUATION'] ?? 0)!==0) { $keys[]='FACTURE_ADDON_PDF_5'; } }
            foreach ($keys as $modelKey) {
                $override=trim((string)($settings[$modelKey] ?? ''));
                $value=$override!=='' ? $override : trim((string)($settings[$key] ?? ''));
                $count=count(array_filter($models,static function($m)use($type,$value){return $m['type']===$type && $m['nom']===$value;}));
                $tested=$value===($type==='invoice' ? 'sponge' : 'azur');
                $rows[]=array('key'=>$modelKey,'value'=>$value,'status'=>$value==='' || $count===0 ? 'Missing' : ($count!==1 || !$tested ? 'Review' : 'Configured'));
            }
        }
        $rows[]=array('key'=>'MAIN_MULTILANGS','value'=>(string)($settings['MAIN_MULTILANGS'] ?? ''),'status'=>(string)($settings['MAIN_MULTILANGS'] ?? '')==='1' ? 'Configured' : 'Missing');
        $language=(string)($settings['MAIN_LANG_DEFAULT'] ?? '');
        $rows[]=array('key'=>'MAIN_LANG_DEFAULT','value'=>$language,'status'=>in_array($language,array('nb_NO','en_US'),true) ? 'Configured' : 'Review');
        $rows[]=array('key'=>'Currency','value'=>$currency,'status'=>$currency==='NOK' ? 'Configured' : 'Review');
        return $rows;
    }

    public static function report($db,$entity,$settings,$currency)
    {
        if ((int)$entity<=0 || (int)$GLOBALS['conf']->entity!==(int)$entity) { throw new InvalidArgumentException('Entity'); }
        $result=$db->query("SELECT nom,type FROM ".$db->prefix()."document_model WHERE entity=".(int)$entity." AND type IN ('invoice','propal') ORDER BY type,nom");
        if (!$result) { throw new RuntimeException('Document setup'); }
        $models=array(); while ($row=$db->fetch_object($result)) { $models[]=(array)$row; }
        return self::evaluate($settings,$models,$currency);
    }
}
