<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require_once DOL_DOCUMENT_ROOT.'/core/modules/propale/modules_propale.php';
require_once __DIR__.'/../../../class/NorwayNumbering.php';
/** Short proposal references using the core mask allocator. */
class mod_propale_norway extends ModeleNumRefPropales
{
    public $name='Norway';
    public $version='dolibarr';
    public $position=55;
    public $error='';
    public function info($langs) { $langs->load('norway@norway'); return $langs->trans('NorwayNumberingModelInfo'); }
    public function getExample() { return 'T000001'; }
    public function getNextValue($objsoc,$proposal='',$mode='next')
    {
        global $db,$conf;
        try {
            if (!isModEnabled('norway') || !is_object($proposal) || (int)($proposal->entity ?? 0)!==(int)$conf->entity) { throw new RuntimeException('Numbering proposal/entity'); }
            $record=NorwayNumbering::record($db,(int)$conf->entity,'proposal');
            return NorwayNumbering::next($db,(int)$conf->entity,$record['input'],'proposal',$mode);
        } catch (Throwable $error) { $this->error='NotConfigured'; return 0; }
    }
}
