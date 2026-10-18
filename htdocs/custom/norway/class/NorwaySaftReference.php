<?php
// SPDX-License-Identifier: GPL-3.0-or-later
/** Pinned official SAF-T vocabulary. No account meaning is inferred from matching numbers. */
class NorwaySaftReference
{
    const REVISION='10c8fd82a6ff47e53385bc33c7638c5fde239a4f';
    const FORMAT='1.40';
    public static function file($name)
    {
        $root=__DIR__.'/../data/saft/';
        $manifest=json_decode(file_get_contents($root.'manifest.json'),true,32,JSON_THROW_ON_ERROR);
        $spec=$manifest['files'][$name]??null;
        if(($manifest['revision']??'')!==self::REVISION || !$spec || basename($name)!==$name
            || !is_file($root.$name) || hash_file('sha256',$root.$name)!==$spec['sha256']){throw new RuntimeException('NorwaySaftReference');}
        return $root.$name;
    }
    private static function csv($name)
    {
        $stream=fopen(self::file($name),'rb');
        if(!$stream){throw new RuntimeException('NorwaySaftReference');}
        try {
            $header=fgetcsv($stream,0,';','"','');$header[0]=preg_replace('/^\xEF\xBB\xBF/','',$header[0]);$out=array();
            while(($row=fgetcsv($stream,0,';','"',''))!==false){if(count($row)!==count($header)){throw new RuntimeException('NorwaySaftReference');}$out[]=array_combine($header,$row);}
            return $out;
        }finally{fclose($stream);}
    }
    public static function groups($year)
    {
        if(!in_array((int)$year,array(2025,2026),true)){throw new RuntimeException('NorwaySaftGroupingYear');}
        $out=array();foreach(self::csv('grouping-2025-2026.csv') as $row){
            $category=$row['GroupingCategory'];$code=$row['GroupingCode'];$key=$category.'/'.$code;
            if(!$category || !$code || isset($out[$key])){throw new RuntimeException('NorwaySaftReference');}
            $out[$key]=array('category'=>$category,'code'=>$code,'label'=>$row['CodeDescriptionNOB']);
        }return $out;
    }
    public static function taxCodes()
    {
        $out=array();foreach(self::csv('tax-codes.csv') as $row){$out[(string)$row['Code']]=$row['DescriptionNOB'];}return $out;
    }
    public static function mapping($year,$mapping,$usedAccounts)
    {
        $groups=self::groups($year);$out=array();
        foreach($usedAccounts as $account){$choice=$mapping[(string)$account]??null;
            if(!is_array($choice) || !isset($groups[($choice['category']??'').'/'.($choice['code']??'')])){throw new RuntimeException('NorwaySaftAccountMapping:'.(string)$account);}
            $out[(string)$account]=$groups[$choice['category'].'/'.$choice['code']];
        }return $out;
    }
    public static function validate($xml)
    {
        if(!is_string($xml) || preg_match('/<!\s*(?:DOCTYPE|ENTITY)\b/i',$xml)){throw new RuntimeException('NorwaySaftXml');}
        $old=libxml_use_internal_errors(true);
        try{
            $doc=new DOMDocument();
            if(!$doc->loadXML($xml,LIBXML_NONET) || !$doc->schemaValidate(self::file('schema-1.40.xsd'),LIBXML_NONET)){throw new RuntimeException('NorwaySaftXml');}
            $xp=new DOMXPath($doc);$xp->registerNamespace('s','urn:StandardAuditFile-Taxation-Financial:NO');
            if($xp->evaluate('string(/s:AuditFile/s:Header/s:AuditFileVersion)')!==self::FORMAT){throw new RuntimeException('NorwaySaftXml');}
            return $doc;
        }finally{libxml_clear_errors();libxml_use_internal_errors($old);}
    }
}
