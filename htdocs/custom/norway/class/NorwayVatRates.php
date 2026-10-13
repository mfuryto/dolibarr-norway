<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require_once __DIR__.'/NorwayDefaultAccounts.php';
/** Confirmed initial setup only: never rewrite rates or historical source data. */
class NorwayVatRates
{
    private static function rows($db,$sql)
    {
        $result=$db->query($sql);if (!$result) { throw new RuntimeException('VAT rate setup'); }
        $rows=array();while ($row=$db->fetch_object($result)) { $rows[]=(array)$row; }return $rows;
    }
    private static function ordinary($r)
    {
        return trim((string)$r['code'])==='' && (int)$r['type_vat']===0 && (int)$r['recuperableonly']===0
            && empty($r['fk_department_buyer']) && empty($r['einvoice_vatex'])
            && (float)$r['localtax1']==0 && (float)$r['localtax2']==0
            && empty($r['localtax1_type']) && empty($r['localtax2_type']);
    }
    public static function preview($db,$entity)
    {
        $base=NorwayDefaultAccounts::preview($db,$entity,array('ACCOUNTING_VAT_SOLD_ACCOUNT'=>'2700','ACCOUNTING_VAT_BUY_ACCOUNT'=>'2710'));
        $entity=(int)$entity;$p=$db->prefix();
        $country=self::rows($db,"SELECT rowid,active FROM ".$p."c_country WHERE code='NO'");
        if (count($country)!==1) { throw new RuntimeException('Country'); }
        $countryId=(int)$country[0]['rowid'];
        $rates=self::rows($db,'SELECT * FROM '.$p.'c_tva WHERE entity='.$entity.' AND fk_pays='.$countryId.' ORDER BY rowid');
        $chart=$base['chart'];
        $blocked=!$chart || $chart['pcg_version']!==NorwayChartProfile::VERSION || $chart['country']!=='NO'
            || (int)$chart['active']!==1 || (int)$chart['rowid']!==$base['runtime_chart'] || (int)$country[0]['active']!==1;
        $usage=array();
        // All document states count, including drafts and quotes. Do not silently change their setup.
        foreach (array('facture','facture_fourn','propal','commande','commande_fournisseur','accounting_bookkeeping') as $table) {
            $usage[$table]=self::rows($db,'SELECT rowid FROM '.$p.$table.' WHERE entity='.$entity.' ORDER BY rowid'.$db->plimit(1));
            $blocked=$blocked || (bool)$usage[$table];
        }
        $usage['products']=self::rows($db,'SELECT rowid FROM '.$p.'product WHERE entity='.$entity.' AND tva_tx IN (8,14) ORDER BY rowid'.$db->plimit(1));
        $blocked=$blocked || (bool)$usage['products'];
        $changes=array();
        foreach (array(25,15,12) as $rate) {
            $matches=array_values(array_filter($rates,static fn($r)=>(float)$r['taux']===$rate*1.0));
            $ordinary=array_values(array_filter($matches,static fn($r)=>self::ordinary($r)));
            $action=!$matches ? 'Create' : (count($ordinary)===1 && (int)$ordinary[0]['active']===1 ? 'Keep' : 'Review');
            $blocked=$blocked || $action==='Review';
            $changes[]=array('id'=>$ordinary ? (int)$ordinary[0]['rowid'] : 0,'rate'=>$rate,'action'=>$action);
        }
        foreach ($rates as $r) {
            if (!in_array((float)$r['taux'],array(8.0,14.0),true) || (int)$r['active']!==1) { continue; }
            $plain=self::ordinary($r) && trim((string)$r['accountancy_code_sell'])==='' && trim((string)$r['accountancy_code_buy'])==='';
            $action=$plain && (int)$r['use_default']===0 ? 'Deactivate' : 'Review';
            $blocked=$blocked || $action==='Review';
            $changes[]=array('id'=>(int)$r['rowid'],'rate'=>(int)$r['taux'],'action'=>$action);
        }
        return array('entity'=>$entity,'base'=>$base,'country'=>$country,'usage'=>$usage,'rates'=>$rates,'changes'=>$changes,'blocked'=>$blocked);
    }
    public static function apply($db,$entity,$fingerprint,$user)
    {
        if (empty($user->admin) || (int)$user->id<=0 || !$user->hasRight('accounting','chartofaccount')) { throw new InvalidArgumentException('Permission'); }
        $active=self::rows($db,'SELECT @@in_transaction AS active');
        if (count($active)!==1 || (int)$active[0]['active']!==0 || !$db->query('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE') || $db->begin()<=0) { throw new RuntimeException('Transaction'); }
        try {
            if ((int)$entity<=0 || (int)$entity!==(int)$GLOBALS['conf']->entity) { throw new InvalidArgumentException('Entity'); }
            $p=$db->prefix();$entity=(int)$entity;
            foreach (array('c_tva','const','facture','facture_fourn','propal','commande','commande_fournisseur','accounting_bookkeeping','product') as $table) {
                self::rows($db,'SELECT rowid FROM '.$p.$table.' WHERE entity IN (0,'.$entity.') FOR UPDATE');
            }
            self::rows($db,'SELECT rowid FROM '.$p.'accounting_system FOR UPDATE');
            self::rows($db,"SELECT rowid FROM ".$p."c_country WHERE code='NO' FOR UPDATE");
            $preview=self::preview($db,$entity);
            if (!hash_equals(NorwayDefaultAccounts::fingerprint($preview),(string)$fingerprint) || $preview['blocked']) { throw new InvalidArgumentException('Changed or blocked'); }
            $saved=0;$country=(int)$preview['country'][0]['rowid'];
            foreach ($preview['changes'] as $change) {
                if ($change['action']==='Create') {
                    $sql='INSERT INTO '.$p."c_tva (entity,fk_pays,taux,type_vat,code,recuperableonly,active,use_default,note) VALUES (".$entity.','.$country.','.$change['rate'].",0,'',0,1,0,'Norwegian ordinary VAT ".$change['rate']."%')";
                } elseif ($change['action']==='Deactivate') {
                    $sql='UPDATE '.$p.'c_tva SET active=0 WHERE entity='.$entity.' AND rowid='.$change['id'].' AND active=1';
                } else { continue; }
                if (!$db->query($sql)) { throw new RuntimeException('Save'); }$saved++;
            }
            $after=self::preview($db,$entity);
            if ($after['blocked'] || count(array_filter($after['changes'],static fn($c)=>$c['action']!=='Keep'))!==0) { throw new RuntimeException('Verification'); }
            if ($db->commit()<=0) { throw new RuntimeException('Commit'); }return $saved;
        } catch (Throwable $error) { $db->rollback();throw $error; }
    }
}
