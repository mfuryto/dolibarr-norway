<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require_once __DIR__.'/NorwayChartProfile.php';
/** Native Norwegian VAT choices; preserve used settings and historical source data. */
class NorwayVatRates
{
    private static function rows($db,$sql)
    {
        $result=$db->query($sql);if (!$result) { throw new RuntimeException('VAT rate setup'); }
        $rows=array();while ($row=$db->fetch_object($result)) { $rows[]=(array)$row; }return $rows;
    }
    /** Ordinary domestic codes only; zero-rate/import/deduction choices are separate. */
    public static function codes()
    {
        return array(
            array('rate'=>25,'type'=>1,'code'=>'3'),array('rate'=>25,'type'=>2,'code'=>'1'),
            array('rate'=>15,'type'=>1,'code'=>'31'),array('rate'=>15,'type'=>2,'code'=>'11'),
            array('rate'=>12,'type'=>1,'code'=>'33'),array('rate'=>12,'type'=>2,'code'=>'13'),
        );
    }
    /** Explicit zero-VAT classifications; 0/7 are outside the VAT return. Never infer treatment from a zero amount. */
    public static function zeroCodes()
    {
        return array(array('rate'=>0,'type'=>1,'code'=>'5','reported'=>true),
            array('rate'=>0,'type'=>1,'code'=>'6','reported'=>true),array('rate'=>0,'type'=>1,'code'=>'7','reported'=>false),
            array('rate'=>0,'type'=>2,'code'=>'NO0','reported'=>false,'report_code'=>'0'),
            array('rate'=>0,'type'=>1,'code'=>'52','reported'=>true,'export'=>true),
            array('rate'=>0,'type'=>2,'code'=>'86','reported'=>true,'reverse_rate'=>25,'reverse_credit'=>'2704','reverse_debit'=>'2714'));
    }
    public static function zeroChoices($db,$entity)
    {
        if((int)$entity<=0 || (int)$entity!==(int)$GLOBALS['conf']->entity){throw new InvalidArgumentException('Entity');}
        $choices=array();
        foreach(self::zeroCodes() as $spec){
            if(self::zeroRow($db,$entity,$spec)!==null){$choices[$spec['code']]=$spec+array('zero'=>true);}
        }return $choices;
    }
    private static function zeroRow($db,$entity,$spec)
    {
        if((int)$entity<=0 || (int)$entity!==(int)$GLOBALS['conf']->entity){throw new InvalidArgumentException('Entity');}
        $rows=self::rows($db,"SELECT t.* FROM ".$db->prefix()."c_tva t JOIN ".$db->prefix()."c_country c ON c.rowid=t.fk_pays WHERE t.entity=".(int)$entity." AND c.code='NO' AND c.active=1 AND t.code='".$spec['code']."'");
        return count($rows)===1 && (string)$rows[0]['code']===$spec['code'] && (int)$rows[0]['active']===1 && (int)$rows[0]['type_vat']===$spec['type']
            && (float)$rows[0]['taux']===0.0 && self::plain($rows[0])?$rows[0]:null;
    }
    /** Add missing explicit zero choices on activation; never repair, default or reclassify existing rows. */
    public static function ensureZeroCodes($db,$entity)
    {
        if((int)$entity<=0 || (int)$entity!==(int)$GLOBALS['conf']->entity){throw new InvalidArgumentException('Entity');}
        $db->begin();
        try {
            $country=self::rows($db,"SELECT rowid FROM ".$db->prefix()."c_country WHERE code='NO' AND active=1 FOR UPDATE");
            if(count($country)!==1){throw new RuntimeException('Norwegian country');}
            $country=(int)$country[0]['rowid'];$p=$db->prefix();
            self::rows($db,'SELECT code FROM '.$p.'c_tva WHERE entity='.(int)$entity.' AND fk_pays='.$country.' FOR UPDATE');
            $created=0;
            foreach(self::zeroCodes() as $spec){
                // Use the register's collation: preserve case/space-equivalent user choices too.
                if(self::rows($db,'SELECT code FROM '.$p.'c_tva WHERE entity='.(int)$entity.' AND fk_pays='.$country." AND code='".$spec['code']."'")){continue;}
                if(!$db->query('INSERT INTO '.$p.'c_tva (entity,fk_pays,taux,type_vat,code,recuperableonly,active,use_default,note) VALUES ('
                    .(int)$entity.','.$country.",0,".$spec['type'].",'".$spec['code']."',0,1,0,'Norwegian VAT code ".$spec['code']."')")){throw new RuntimeException('Norwegian zero code');}$created++;
            }
            if($db->commit()<=0){throw new RuntimeException('Commit');}return $created;
        }catch(Throwable $error){$db->rollback();throw $error;}
    }
    private static function plain($r)
    {
        return (int)$r['recuperableonly']===0 && empty($r['fk_department_buyer']) && empty($r['einvoice_vatex'])
            && (float)$r['localtax1']==0 && (float)$r['localtax2']==0
            && empty($r['localtax1_type']) && empty($r['localtax2_type']);
    }
    private static function ordinary($r)
    {
        return trim((string)$r['code'])==='' && (int)$r['type_vat']===0 && self::plain($r);
    }
    public static function defaultCode($db,$entity,$rate,$type)
    {
        if((int)$entity<=0 || (int)$entity!==(int)$GLOBALS['conf']->entity){throw new InvalidArgumentException('Entity');}
        foreach(self::codes() as $spec){if((float)$rate!==(float)$spec['rate'] || $type!==$spec['type']){continue;}
            $rows=self::rows($db,"SELECT t.* FROM ".$db->prefix()."c_tva t JOIN ".$db->prefix()."c_country c ON c.rowid=t.fk_pays WHERE t.entity=".(int)$entity." AND c.code='NO' AND c.active=1 AND t.code='".$spec['code']."'");
            if(count($rows)===1 && (string)$rows[0]['code']===$spec['code'] && (int)$rows[0]['active']===1 && (int)$rows[0]['type_vat']===$type && (float)$rows[0]['taux']===(float)$spec['rate'] && self::plain($rows[0])){return $spec['rate'].' ('.$spec['code'].')';}
        }return null;
    }
    public static function preview($db,$entity)
    {
        if ((int)$entity<=0 || (int)$entity!==(int)$GLOBALS['conf']->entity) { throw new InvalidArgumentException('Entity'); }
        $selected=self::rows($db,"SELECT value FROM ".$db->prefix()."const WHERE entity=".(int)$entity." AND name='CHARTOFACCOUNTS'");
        $chartId=count($selected)===1 ? (int)$selected[0]['value'] : 0;
        $charts=self::rows($db,"SELECT s.rowid,s.pcg_version,s.active,c.code AS country FROM ".$db->prefix()."accounting_system s JOIN ".$db->prefix()."c_country c ON c.rowid=s.fk_country WHERE s.rowid=".$chartId);
        $base=array('chart'=>count($charts)===1?$charts[0]:null,'runtime_chart'=>getDolGlobalInt('CHARTOFACCOUNTS'));
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

        }
        $usage['products']=self::rows($db,'SELECT rowid FROM '.$p.'product WHERE entity='.$entity.' ORDER BY rowid'.$db->plimit(1));
        $hasUsage=(bool)array_filter($usage);
        $changes=array();
        foreach (array_merge(self::codes(),self::zeroCodes()) as $spec) {
            $matches=array_values(array_filter($rates,static fn($r)=>trim((string)$r['code'])===$spec['code']));
            $valid=count($matches)===1 && (string)$matches[0]['code']===$spec['code'] && (int)$matches[0]['type_vat']===$spec['type']
                && (float)$matches[0]['taux']===(float)$spec['rate'] && self::plain($matches[0]) && (int)$matches[0]['active']===1;
            $action=!$matches ? 'Create' : ($valid ? 'Keep' : 'Review');
            $blocked=$blocked || $action==='Review';
            $changes[]=$spec+array('id'=>$matches ? (int)$matches[0]['rowid'] : 0,'action'=>$action);
        }
        foreach ($rates as $r) {
            if (!in_array((float)$r['taux'],array(8.0,14.0,25.0,15.0,12.0),true) || (int)$r['active']!==1) { continue; }
            // Preserve other coded treatments; never infer their purpose from the rate.
            if(trim((string)$r['code'])!=='' && in_array((float)$r['taux'],array(25.0,15.0,12.0),true)){continue;}
            $plain=self::ordinary($r) && trim((string)$r['accountancy_code_sell'])==='' && trim((string)$r['accountancy_code_buy'])==='';
            $action=$hasUsage ? 'Keep' : ($plain && (int)$r['use_default']===0 ? 'Deactivate' : 'Review');
            $blocked=$blocked || $action==='Review';
            $changes[]=array('id'=>(int)$r['rowid'],'rate'=>(int)$r['taux'],'type'=>(int)$r['type_vat'],'code'=>(string)$r['code'],'action'=>$action);
        }
        return array('entity'=>$entity,'base'=>$base,'country'=>$country,'usage'=>$usage,'rates'=>$rates,'changes'=>$changes,'blocked'=>$blocked);
    }
    public static function fingerprint($preview) { return hash('sha256',json_encode($preview,JSON_THROW_ON_ERROR)); }
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
            if (!hash_equals(self::fingerprint($preview),(string)$fingerprint) || $preview['blocked']) { throw new InvalidArgumentException('Changed or blocked'); }
            $saved=0;$country=(int)$preview['country'][0]['rowid'];
            foreach ($preview['changes'] as $change) {
                if ($change['action']==='Create') {
                    $sql='INSERT INTO '.$p."c_tva (entity,fk_pays,taux,type_vat,code,recuperableonly,active,use_default,note) VALUES (".$entity.','.$country.','.$change['rate'].','.$change['type'].",'".$db->escape($change['code'])."',0,1,0,'Norwegian VAT code ".$change['code']."')";
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
