<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require_once DOL_DOCUMENT_ROOT.'/core/modules/facture/modules_facture.php';
require_once __DIR__.'/../../../class/NorwayNumbering.php';
/** Short invoice references; other document types retain the selected core model. */
class mod_facture_norway extends ModeleNumRefFactures
{
    public $name='Norway';
    public $version='dolibarr';
    public $position=55;
    public $error='';
    public function info($langs) { $langs->load('norway@norway'); return $langs->trans('NorwayNumberingModelInfo'); }
    public function getExample() { return 'F000001 / K000001'; }
    public function getNextValue($objsoc,$invoice,$mode='next')
    {
        global $db,$conf;
        try {
            if (!isModEnabled('norway') || !is_object($invoice) || (int)($invoice->entity ?? 0)!==(int)$conf->entity) { throw new RuntimeException('Numbering invoice/entity'); }
            $record=NorwayNumbering::record($db,(int)$conf->entity);
            if (in_array((int)$invoice->type,array(0,2),true)) {
                return NorwayNumbering::next($db,(int)$conf->entity,$record['input'],(int)$invoice->type===2 ? 'credit' : 'invoice',$mode);
            }
            $fallback=$record['fallback']; require_once DOL_DOCUMENT_ROOT.'/core/modules/facture/'.$fallback.'.php';
            $model=new $fallback($db); return $model->getNextValue($objsoc,$invoice,$mode);
        } catch (Throwable $error) { $this->error='NotConfigured'; return 0; }
    }
    public function getNumRef($objsoc,$invoice,$mode='next') { return $this->getNextValue($objsoc,$invoice,$mode); }
}
