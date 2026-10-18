<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require_once __DIR__.'/NorwaySaftReference.php';
/** Export classification in native entity-scoped constants; no account or posting is changed. */
class NorwaySaftMapping
{
    public static function rows($db,$sql)
    {
        $r=$db->query($sql);if(!$r){throw new RuntimeException('NorwaySaftDatabase');}
        $out=array();while($o=$db->fetch_object($r)){$out[]=$o;}return $out;
    }
    public static function defaults($year)
    {
        $groups=NorwaySaftReference::groups($year);$file=fopen(__DIR__.'/../data/saft/account-mapping.csv','rb');
        if(!$file){throw new RuntimeException('NorwaySaftReference');}
        try{$header=fgetcsv($file,0,';','"','');$out=array();while(($r=fgetcsv($file,0,';','"',''))!==false){
            if(count($r)!==4 || isset($out[$r[0]]) || !isset($groups[$r[2].'/'.$r[3]])){throw new RuntimeException('NorwaySaftReference');}
            $out[$r[0]]=array('category'=>$r[2],'code'=>$r[3],'label'=>$r[1]);
        }return $out;}finally{fclose($file);}
    }
    public static function load($db,$entity,$year,$accounts,$allowMissing=false)
    {
        global $conf;
        if((int)$conf->entity!==(int)$entity){throw new RuntimeException('NorwaySaftAccess');}
        $p=$db->prefix();$chart=self::rows($db,'SELECT pcg_version FROM '.$p.'accounting_system WHERE rowid='.(int)getDolGlobalInt('CHARTOFACCOUNTS'));
        if(count($chart)!==1){throw new RuntimeException('NorwaySaftAccountMapping');}
        $version=$chart[0]->pcg_version;
        $saved=self::rows($db,'SELECT value FROM '.$p."const WHERE entity=".(int)$entity." AND name='NORWAY_SAFT_MAPPING_".(int)$year."'");
        $saved=$saved?json_decode($saved[0]->value,true,512,JSON_THROW_ON_ERROR):array();
        $defaults=self::defaults($year);$choices=array();$details=array();
        foreach(self::rows($db,'SELECT * FROM '.$p.'accounting_account WHERE entity='.(int)$entity." AND fk_pcg_version='".$db->escape($version)."'") as $a){$details[$a->account_number]=$a;}
        foreach($accounts as $number){
            $a=$details[$number]??null;if(!$a){throw new RuntimeException('NorwaySaftAccountMapping:'.$number);}
            $choice=$saved[$version][$number]??null;
            // Defaults apply only to the shipped chart and its original account meaning.
            // A renamed/reused account needs an explicit choice; never classify by prefix at runtime.
            if($choice===null && $version==='NORWAY-NO-1' && isset($defaults[$number]) && $a->label===$defaults[$number]['label']){$choice=$defaults[$number];}
            if($choice!==null || !$allowMissing){$choices[$number]=$choice;}
        }
        return NorwaySaftReference::mapping($year,$choices,$allowMissing?array_keys($choices):$accounts);
    }
    public static function save($db,$entity,$year,$accountId,$group,$user)
    {
        global $conf;
        if((int)$entity!==(int)$conf->entity || !$user || !empty($user->socid) || !$user->hasRight('accounting','chartofaccount')){throw new RuntimeException('NorwaySaftAccess');}
        $groups=NorwaySaftReference::groups($year);if(!isset($groups[$group])){throw new RuntimeException('NorwaySaftAccountMapping');}
        $p=$db->prefix();$a=self::rows($db,'SELECT * FROM '.$p.'accounting_account WHERE entity='.(int)$entity.' AND rowid='.(int)$accountId);
        if(count($a)!==1){throw new RuntimeException('NorwaySaftAccountMapping');}
        require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';$name='NORWAY_SAFT_MAPPING_'.(int)$year;
        $db->begin();try{
            // Serialize updates even before the first mapping constant exists.
            self::rows($db,'SELECT rowid FROM '.$p.'accounting_account WHERE entity='.(int)$entity.' ORDER BY rowid FOR UPDATE');
            $saved=self::rows($db,'SELECT value FROM '.$p."const WHERE entity=".(int)$entity." AND name='$name' FOR UPDATE");
            $saved=$saved?json_decode($saved[0]->value,true,512,JSON_THROW_ON_ERROR):array();
            $saved[$a[0]->fk_pcg_version][$a[0]->account_number]=array_intersect_key($groups[$group],array_flip(array('category','code')));
            if(dolibarr_set_const($db,$name,json_encode($saved,JSON_THROW_ON_ERROR),'chaine',0,'',$entity)<=0 || !$db->commit()){throw new RuntimeException('NorwaySaftDatabase');}
        }catch(Throwable $e){$db->rollback();throw $e;}
    }
}
