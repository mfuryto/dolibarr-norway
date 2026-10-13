<?php
// SPDX-License-Identifier: GPL-3.0-or-later
/** Confirmed six-digit sales-document numbering using the core mask allocator. */
class NorwayNumbering
{
    const SETTING='NORWAY_NUMBERING_V1';
    const MODULE='mod_facture_norway';
    const PROPOSAL_MODULE='mod_propale_norway';
    const PARTNER_MODULE='mod_codeclient_norway';
    const KINDS=array('invoice','credit','proposal','customer','supplier');
    const MAX=999999;
    const SCAN_LIMIT=100000;

    public static function input($invoicePrefix,$invoiceStart,$creditPrefix,$creditStart,$proposalPrefix='T',$proposalStart=1,$customerStart=1,$supplierStart=1,$customerPrefix='',$supplierPrefix='')
    {
        $input=array('invoice_prefix'=>$invoicePrefix,'invoice_start'=>$invoiceStart,'credit_prefix'=>$creditPrefix,'credit_start'=>$creditStart,'proposal_prefix'=>$proposalPrefix,'proposal_start'=>$proposalStart,'customer_start'=>$customerStart,'supplier_start'=>$supplierStart,'customer_prefix'=>$customerPrefix,'supplier_prefix'=>$supplierPrefix);
        foreach (array('invoice','credit','proposal') as $kind) {
            $prefix=$input[$kind.'_prefix']; $start=$input[$kind.'_start'];
            if (!is_string($prefix) || !preg_match('/^[A-Z]{1,4}$/D',$prefix) || !is_int($start) || $start<1 || $start>self::MAX) { throw new InvalidArgumentException('Numbering input'); }
        }
        foreach (array($customerStart,$supplierStart) as $start) { if (!is_int($start) || $start<1 || $start>99999) { throw new InvalidArgumentException('Partner start'); } }
        foreach (array($customerPrefix,$supplierPrefix) as $prefix) { if (!is_string($prefix) || !preg_match('/^[A-Z]{0,4}$/D',$prefix)) { throw new InvalidArgumentException('Partner prefix'); } }
        if (count(array_unique(array($invoicePrefix,$creditPrefix,$proposalPrefix)))!==3) { throw new InvalidArgumentException('Separate prefixes'); }
        return $input;
    }

    public static function decode($raw,$entity)
    {
        if (!is_string($raw) || strlen($raw)>4000) { throw new RuntimeException('Numbering record'); }
        $record=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
        if (!is_array($record) || !in_array($record['version'] ?? null,array(1,2),true) || ($record['entity'] ?? null)!==$entity
            || !is_int($record['user'] ?? null) || $record['user']<=0 || !is_string($record['at'] ?? null)
            || !preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}\+00:00$/D',$record['at'])
            || !is_string($record['baseline'] ?? null) || !preg_match('/^[a-f0-9]{64}$/D',$record['baseline'])
            || !is_string($record['partner_baseline'] ?? null) || !preg_match('/^[a-f0-9]{64}$/D',$record['partner_baseline'])
            || !in_array($record['partner_fallback'] ?? '',array('mod_codeclient_leopard','mod_codeclient_monkey','mod_codeclient_elephant'),true)
            || !in_array($record['fallback'] ?? '',array('mod_facture_mars','mod_facture_terre','mod_facture_mercure'),true) || !in_array($record['proposal_fallback'] ?? '',array('mod_propale_marbre','mod_propale_saphir'),true)) { throw new RuntimeException('Numbering record'); }
        $input=$record['input'] ?? array();
        $normalized=self::input($input['invoice_prefix'] ?? null,$input['invoice_start'] ?? null,$input['credit_prefix'] ?? null,$input['credit_start'] ?? null,$input['proposal_prefix'] ?? null,$input['proposal_start'] ?? null,$input['customer_start'] ?? null,$input['supplier_start'] ?? null,$input['customer_prefix'] ?? '',$input['supplier_prefix'] ?? '');
        if ($record['version']===1) {
            $legacy=$normalized;unset($legacy['customer_prefix'],$legacy['supplier_prefix']);
            if ($legacy!==$input) { throw new RuntimeException('Legacy numbering input'); }
            $record['input']=$normalized;$record['partner_prefixes']=array('customer'=>array(''),'supplier'=>array(''));
        } else {
            if ($normalized!==$input || !is_array($record['partner_prefixes'] ?? null) || array_keys($record['partner_prefixes'])!==array('customer','supplier')) { throw new RuntimeException('Numbering input record'); }
            foreach (array('customer','supplier') as $kind) {
                $prefixes=$record['partner_prefixes'][$kind];
                if (!is_array($prefixes) || !$prefixes || count($prefixes)>100 || array_keys($prefixes)!==range(0,count($prefixes)-1) || count(array_unique($prefixes,SORT_REGULAR))!==count($prefixes)
                    || !in_array('',$prefixes,true) || !in_array($input[$kind.'_prefix'],$prefixes,true)) { throw new RuntimeException('Partner prefix history'); }
                foreach ($prefixes as $prefix) { if (!is_string($prefix) || !preg_match('/^[A-Z]{0,4}$/D',$prefix)) { throw new RuntimeException('Partner prefix history'); } }
            }
        }
        return $record;
    }

    private static function guard($entity,$user)
    {
        if (!is_int($entity) || $entity<=0 || $entity!==(int)$GLOBALS['conf']->entity || !$user || !$user->admin || (int)$user->id<=0 || !isModEnabled('norway')) { throw new InvalidArgumentException('Numbering access'); }
    }

    private static function rows($db,$sql)
    {
        $r=$db->query($sql); if (!$r) { throw new RuntimeException('Numbering query'); }
        $rows=array(); while ($row=$db->fetch_object($r)) { $rows[]=(array)$row; } return $rows;
    }

    private static function settings($db,$entity)
    {
        $rows=self::rows($db,"SELECT rowid,entity,name,value FROM ".$db->prefix()."const WHERE entity IN (0,".$entity.") AND name IN ('FACTURE_ADDON','PROPALE_ADDON','SOCIETE_CODECLIENT_ADDON','".self::SETTING."') ORDER BY entity,name,rowid");
        $seen=array(); $effective=array();
        foreach ($rows as $row) {
            $key=$row['entity'].':'.$row['name']; if (isset($seen[$key])) { throw new RuntimeException('Duplicate numbering settings'); } $seen[$key]=true;
            if ($row['name']===self::SETTING && (int)$row['entity']!==$entity) { throw new RuntimeException('Global numbering profile'); }
            $effective[$row['name']]=$row['value'];
        }
        return array('rows'=>$rows,'effective'=>$effective);
    }

    public static function record($db,$entity,$kind='invoice')
    {
        if (!is_int($entity) || $entity<=0 || $entity!==(int)$GLOBALS['conf']->entity) { throw new InvalidArgumentException('Numbering entity'); }
        $settings=self::settings($db,$entity);
        if (($settings['effective'][in_array($kind,array('customer','supplier'),true) ? 'SOCIETE_CODECLIENT_ADDON' : ($kind==='proposal' ? 'PROPALE_ADDON' : 'FACTURE_ADDON')] ?? '')!==(in_array($kind,array('customer','supplier'),true) ? self::PARTNER_MODULE : ($kind==='proposal' ? self::PROPOSAL_MODULE : self::MODULE))) { throw new RuntimeException('Numbering module changed'); }
        return self::decode($settings['effective'][self::SETTING] ?? '',$entity);
    }

    public static function mask($prefix,$start,$digits=6) { return $prefix.'{'.str_repeat('0',$digits).($start>1 ? '+'.($start-1) : '').'}'; }

    /** No reservation: the core chooses the reference again during actual validation. */
    public static function next($db,$entity,$input,$kind,$mode='next')
    {
        if (!in_array($kind,self::KINDS,true) || !in_array($mode,array('next','last'),true) || $entity!==(int)$GLOBALS['conf']->entity) { throw new InvalidArgumentException('Numbering next'); }
        $input=self::input($input['invoice_prefix'] ?? null,$input['invoice_start'] ?? null,$input['credit_prefix'] ?? null,$input['credit_start'] ?? null,$input['proposal_prefix'] ?? null,$input['proposal_start'] ?? null,$input['customer_start'] ?? null,$input['supplier_start'] ?? null,$input['customer_prefix'] ?? '',$input['supplier_prefix'] ?? '');
        $partner=in_array($kind,array('customer','supplier'),true);
        $prefix=$input[$kind.'_prefix']; $table=$partner ? 'societe' : ($kind==='proposal' ? 'propal' : 'facture');
        $field=$partner ? ($kind==='customer' ? 'code_client' : 'code_fournisseur') : 'ref'; $digits=$partner ? 5 : 6;
        $conflicts=self::rows($db,"SELECT rowid FROM ".$db->prefix().$table." WHERE entity=".$entity." AND ".$field." REGEXP '^".$db->escape($prefix)."[0-9]+$' AND CHAR_LENGTH(".$field.")<>".(strlen($prefix)+$digits).' LIMIT 1');
        if ($conflicts) { throw new RuntimeException('Numbering digit width conflict'); }
        $start=$input[$kind.'_start'];
        if ($partner) {
            $settings=self::settings($db,$entity);$raw=$settings['effective'][self::SETTING] ?? null;
            $record=$raw!==null ? self::decode($raw,$entity) : null;
            $prefixes=array_values(array_unique(array_merge(array(''),$record['partner_prefixes'][$kind] ?? array(),array($prefix))));
            $pattern='^('.implode('|',$prefixes).')[0-9]{5}$';
            $used=self::rows($db,"SELECT ".$field." AS code FROM ".$db->prefix()."societe WHERE entity=".$entity." AND ".$field." REGEXP '".$db->escape($pattern)."' ORDER BY CAST(RIGHT(".$field.",5) AS UNSIGNED) DESC,rowid DESC LIMIT 1");
            if ($mode==='last') { return $used ? $used[0]['code'] : ''; }
            $maximum=$used ? (int)substr($used[0]['code'],-5) : 0;
            if ($maximum>=99999) { throw new RuntimeException('Partner numbering exhausted'); }
            $start=max($start,$maximum+1);
        }
        require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
        // Scope the core's optional mask flags to this call, then restore other models' behaviour.
        $values=array('SEARCH_FOR_NEXT_VAL_ON_START_ONLY'=>'0','MAIN_NUMBERING_OFFSET_ONLY_FOR_FIRST'=>'0','MAIN_NUMBERING_FILTER_ON_INT_ONLY'=>'1');
        $global=$GLOBALS['conf']->global; $old=array();
        try {
            foreach ($values as $name=>$value) { $old[$name]=array(property_exists($global,$name),$global->$name ?? null); $global->$name=$value; }
            foreach ($values as $name=>$value) { if ((string)getDolGlobalString($name)!==$value) { throw new RuntimeException('Numbering environment override'); } }
            $mask=self::mask($prefix,$start,$digits);
            $ref=get_next_value($db,$mask,$table,$field,'',null,'',$mode,false,null,$entity);
            if ($mode==='last' && $ref==='') { return ''; }
            if (!is_string($ref) || !preg_match('/^'.preg_quote($prefix,'/').'[0-9]{'.$digits.'}$/D',$ref) || (int)substr($ref,strlen($prefix))<1) { throw new RuntimeException('Numbering exhausted or invalid'); }
            return $ref;
        } finally {
            foreach ($old as $name=>[$exists,$value]) { if ($exists) { $global->$name=$value; } else { unset($global->$name); } }
        }
    }

    public static function preview($db,$entity,$input,$user)
    {
        self::guard($entity,$user);
        $input=self::input($input['invoice_prefix'] ?? null,$input['invoice_start'] ?? null,$input['credit_prefix'] ?? null,$input['credit_start'] ?? null,$input['proposal_prefix'] ?? null,$input['proposal_start'] ?? null,$input['customer_start'] ?? null,$input['supplier_start'] ?? null,$input['customer_prefix'] ?? '',$input['supplier_prefix'] ?? '');
        $settings=self::settings($db,$entity); $effective=$settings['effective'];
        $record=isset($effective[self::SETTING]) ? self::decode($effective[self::SETTING],$entity) : null;
        $module=$effective['FACTURE_ADDON'] ?? 'mod_facture_mars';
        if (($record && $module!==self::MODULE) || (!$record && $module===self::MODULE)
            || (!$record && !in_array($module,array('mod_facture_mars','mod_facture_terre','mod_facture_mercure'),true))) { throw new RuntimeException('Numbering module conflict'); }
        $proposalModule=$effective['PROPALE_ADDON'] ?? 'mod_propale_marbre';
        if (($record && $proposalModule!==self::PROPOSAL_MODULE) || (!$record && !in_array($proposalModule,array('mod_propale_marbre','mod_propale_saphir'),true))) { throw new RuntimeException('Proposal numbering module conflict'); }
        $partnerModule=$effective['SOCIETE_CODECLIENT_ADDON'] ?? 'mod_codeclient_leopard';
        if (($record && $partnerModule!==self::PARTNER_MODULE) || (!$record && !in_array($partnerModule,array('mod_codeclient_leopard','mod_codeclient_monkey','mod_codeclient_elephant'),true))) { throw new RuntimeException('Partner numbering module conflict'); }
        $all=hash_init('sha256'); $issued=hash_init('sha256'); $count=0; $issuedCount=0;
        foreach (array('facture','propal') as $table) {
        $r=$db->query('SELECT rowid,ref,'.($table==='facture' ? 'type' : '0 AS type').',fk_statut FROM '.$db->prefix().$table.' WHERE entity='.$entity.' ORDER BY rowid LIMIT '.(self::SCAN_LIMIT+1));
        if (!$r) { throw new RuntimeException('Numbering history'); }
        while ($row=$db->fetch_object($r)) {
            if (++$count>self::SCAN_LIMIT) { throw new RuntimeException('Numbering history capacity'); }
            hash_update($all,$table.json_encode((array)$row,JSON_THROW_ON_ERROR)."\n");
            if ((int)$row->fk_statut!==0) { $issuedCount++; hash_update($issued,$table.json_encode(array($row->rowid,$row->ref,$row->type),JSON_THROW_ON_ERROR)."\n"); }
            foreach ($table==='facture' ? array('invoice','credit') : array('proposal') as $kind) {
                if (preg_match('/^'.preg_quote($input[$kind.'_prefix'],'/').'([0-9]+)$/D',$row->ref,$matches) && strlen($matches[1])!==6) { throw new RuntimeException('Numbering digit width conflict'); }
            }
        }
        }
        $partners=hash_init('sha256');
        $r=$db->query('SELECT rowid,code_client,code_fournisseur FROM '.$db->prefix().'societe WHERE entity='.$entity.' ORDER BY rowid LIMIT '.(self::SCAN_LIMIT+1));
        if (!$r) { throw new RuntimeException('Partner numbering history'); }
        while ($row=$db->fetch_object($r)) {
            if (++$count>self::SCAN_LIMIT) { throw new RuntimeException('Numbering history capacity'); }
            hash_update($partners,json_encode((array)$row,JSON_THROW_ON_ERROR)."\n");
        }
        $partnerBaseline=hash_final($partners);
        $partnersLocked=$record && $record['partner_baseline']!==$partnerBaseline;
        $baseline=hash_final($issued); $locked=$record && $record['baseline']!==$baseline;
        foreach (self::KINDS as $kind) {
            $freeze=in_array($kind,array('customer','supplier'),true) ? $partnersLocked : $locked;
            foreach (in_array($kind,array('customer','supplier'),true) ? array('start') : array('start','prefix') as $field) {
                if ($freeze && $record['input'][$kind.'_'.$field]!==$input[$kind.'_'.$field]) { throw new RuntimeException('Numbering locked after use'); }
            }
        }
        $next=array(); foreach (self::KINDS as $kind) { $next[$kind]=self::next($db,$entity,$input,$kind); }
        $state=array('entity'=>$entity,'input'=>$input,'settings'=>$settings,'record'=>$record,'baseline'=>$baseline,'history'=>hash_final($all),'count'=>$count,'issued_count'=>$issuedCount,'locked'=>(bool)$locked,'partner_baseline'=>$partnerBaseline,'partners_locked'=>(bool)$partnersLocked,'next'=>$next);
        return $state+array('fingerprint'=>hash('sha256',json_encode($state,JSON_THROW_ON_ERROR)));
    }

    public static function save($db,$entity,$input,$fingerprint,$user)
    {
        self::guard($entity,$user);
        $active=self::rows($db,'SELECT @@in_transaction AS active');
        if (count($active)!==1 || (int)$active[0]['active']!==0) { throw new RuntimeException('Numbering transaction already active'); }
        if (!$db->query('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE') || !$db->begin()) { throw new RuntimeException('Numbering transaction'); }
        try {
            self::rows($db,"SELECT rowid FROM ".$db->prefix()."const WHERE entity IN (0,".$entity.") AND name IN ('FACTURE_ADDON','PROPALE_ADDON','SOCIETE_CODECLIENT_ADDON','".self::SETTING."') ORDER BY entity,name FOR UPDATE");
            // Lock existing documents and the entity range against concurrent creation/validation.
            foreach (array('facture','propal','societe') as $table) { self::rows($db,'SELECT rowid FROM '.$db->prefix().$table.' WHERE entity='.$entity.' ORDER BY rowid LIMIT '.(self::SCAN_LIMIT+1).' FOR UPDATE'); }
            $preview=self::preview($db,$entity,$input,$user);
            if (!is_string($fingerprint) || !hash_equals($preview['fingerprint'],$fingerprint)) { throw new RuntimeException('Numbering changed'); }
            $record=$preview['record'];
            if (!$record || $record['version']!==2 || $record['input']!==$input) {
                $prefixes=array();foreach (array('customer','supplier') as $kind) { $prefixes[$kind]=array_values(array_unique(array_merge(array(''),$record['partner_prefixes'][$kind] ?? array(),array($input[$kind.'_prefix']))));if (count($prefixes[$kind])>100) { throw new RuntimeException('Partner prefix history capacity'); } }
                $record=array('version'=>2,'entity'=>$entity,'input'=>$input,'partner_prefixes'=>$prefixes,'fallback'=>$record ? $record['fallback'] : ($preview['settings']['effective']['FACTURE_ADDON'] ?? 'mod_facture_mars'),
                    'proposal_fallback'=>$record ? $record['proposal_fallback'] : ($preview['settings']['effective']['PROPALE_ADDON'] ?? 'mod_propale_marbre'),
                    'partner_fallback'=>$record ? $record['partner_fallback'] : ($preview['settings']['effective']['SOCIETE_CODECLIENT_ADDON'] ?? 'mod_codeclient_leopard'),
                    'partner_baseline'=>$preview['partners_locked'] ? $record['partner_baseline'] : $preview['partner_baseline'],
                    'baseline'=>$preview['locked'] ? $record['baseline'] : $preview['baseline'],'user'=>(int)$user->id,'at'=>gmdate('c'));
            }
            $values=array(self::SETTING=>json_encode($record,JSON_THROW_ON_ERROR), 'FACTURE_ADDON'=>self::MODULE,'PROPALE_ADDON'=>self::PROPOSAL_MODULE,'SOCIETE_CODECLIENT_ADDON'=>self::PARTNER_MODULE);
            foreach ($values as $name=>$value) {
                $local=array_values(array_filter($preview['settings']['rows'],static fn($row)=>(int)$row['entity']===$entity && $row['name']===$name));
                $sql=$local ? "UPDATE ".$db->prefix()."const SET value='".$db->escape($value)."' WHERE rowid=".(int)$local[0]['rowid']." AND entity=".$entity
                    : "INSERT INTO ".$db->prefix()."const (name,value,type,visible,entity) VALUES ('".$name."','".$db->escape($value)."','chaine',0,".$entity.")";
                if (!$db->query($sql)) { throw new RuntimeException('Numbering write'); }
            }
            if (self::record($db,$entity)!==$record) { throw new RuntimeException('Numbering verification'); }
            if (!$db->commit()) { throw new RuntimeException('Numbering commit'); }
            foreach ($values as $name=>$value) { $GLOBALS['conf']->global->$name=$value; }
            return $preview['next'];
        } catch (Throwable $error) { $db->rollback(); throw $error; }
    }
}
