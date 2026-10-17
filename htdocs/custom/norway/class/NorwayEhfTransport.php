<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require_once __DIR__.'/NorwayEhfXml.php';
/** Peppyrus wire contract; invoice ownership, durable attempt and duplicate guard stay here. */
class NorwayEhfTransport
{
    private $db;
    private $request;
    public function __construct($db, $request=null) { $this->db=$db;$this->request=$request; }

    /** Sending is deliberately limited to the separate test network until network qualification. */
    public static function enabled()
    {
        return getenv('NORWAY_EHF_MODE')==='test' && (string)getenv('NORWAY_EHF_TEST_API_KEY')!=='';
    }

    private function invoice($id)
    {
        global $user,$conf;
        if (!isModEnabled('norway') || !$user || !empty($user->socid) || !$user->hasRight('facture','lire') || !$user->hasRight('facture','creer')) { throw new RuntimeException('NorwayEhfAccess'); }
        require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
        $invoice=new Facture($this->db);
        if ($invoice->fetch((int)$id)<=0 || (int)$invoice->entity!==(int)$conf->entity || !checkUserAccessToObject($user,array('facture'),$invoice,'','','fk_soc')) { throw new RuntimeException('NorwayEhfAccess'); }
        return $invoice;
    }

    private function directory($invoice)
    {
        global $conf;
        require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
        $root=$conf->facture->multidir_output[(int)$invoice->entity]??$conf->facture->dir_output;
        // Key by immutable invoice ID. Renaming an invoice reference must not reset its send guard.
        $base=$root.'/.norway-ehf-test';$path=$base.'/'.(int)$invoice->id;
        if (is_link($root) || is_link($base) || is_link($path) || dol_mkdir($path)<0 || !is_dir($path)
            || realpath(dirname($base))!==realpath($root) || realpath(dirname($path))!==realpath($base)) { throw new RuntimeException('NorwayEhfStorage'); }
        return $path;
    }

    private function locked($id,$callback)
    {
        if (!self::enabled()) { throw new RuntimeException('NorwayEhfDisabled'); }
        $invoice=$this->invoice($id);$directory=$this->directory($invoice);
        $lock=$directory.'/.norway-ehf-test.lock';
        if (is_link($lock)) { throw new RuntimeException('NorwayEhfStorage'); }
        $handle=fopen($lock,'c');
        if (!$handle || !flock($handle,LOCK_EX|LOCK_NB)) { if ($handle) { fclose($handle); }throw new RuntimeException('NorwayEhfBusy'); }
        try { return $callback($invoice,$directory); }
        finally { flock($handle,LOCK_UN);fclose($handle); }
    }

    private function load($directory)
    {
        $file=$directory.'/norway-ehf-test-state.json';
        if (is_link($file)) { throw new RuntimeException('NorwayEhfStorage'); }
        if (!is_file($file)) { return array('status'=>'ready'); }
        try { $state=json_decode(file_get_contents($file),true,32,JSON_THROW_ON_ERROR); }
        catch (Throwable $error) { throw new RuntimeException('NorwayEhfStorage'); }
        if (!is_array($state) || !in_array($state['status']??'',array('pending','unknown','rejected','accepted','sent','failed'),true)) { throw new RuntimeException('NorwayEhfStorage'); }
        return $state;
    }

    private function write($path,$content)
    {
        if (is_link($path)) { throw new RuntimeException('NorwayEhfStorage'); }
        $temp=tempnam(dirname($path),'.ehf-');$handle=false;$parent=false;
        try {
            if ($temp===false || !chmod($temp,0600)) { throw new RuntimeException('NorwayEhfStorage'); }
            $handle=fopen($temp,'wb');
            if (!$handle || fwrite($handle,$content)!==strlen($content) || !fflush($handle) || !fsync($handle)) { throw new RuntimeException('NorwayEhfStorage'); }
            fclose($handle);$handle=false;
            if (!rename($temp,$path)) { throw new RuntimeException('NorwayEhfStorage'); }
            $parent=fopen(dirname($path),'r');
            if (!$parent || !fsync($parent)) { throw new RuntimeException('NorwayEhfStorage'); }
        } finally {
            if ($handle) { fclose($handle); }if ($parent) { fclose($parent); }
            if ($temp && is_file($temp)) { unlink($temp); }
        }
    }
    private function save($directory,$state)
    {
        $this->write($directory.'/norway-ehf-test-state.json',json_encode($state,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n");
    }

    /** Preserve a document once an access point may have received it, even if the key is later removed. */
    public static function protect($invoice)
    {
        global $conf;
        if (empty($invoice->id) || (int)($invoice->entity??0)!==(int)$conf->entity) { return; }
        $root=$conf->facture->multidir_output[(int)$invoice->entity]??$conf->facture->dir_output;
        $directory=$root.'/.norway-ehf-test/'.(int)$invoice->id;
        if (!is_dir($directory)) { return; }
        if (is_link(dirname($directory)) || is_link($directory) || is_link($directory.'/.norway-ehf-test.lock')) { throw new RuntimeException('NorwayEhfStorage'); }
        $handle=fopen($directory.'/.norway-ehf-test.lock','c');
        if (!$handle || !flock($handle,LOCK_EX|LOCK_NB)) { if ($handle) { fclose($handle); }throw new RuntimeException('NorwayEhfBusy'); }
        try {
            $service=new self(null);$state=$service->load($directory);
            if (!in_array($state['status'],array('ready','rejected'),true)) { throw new RuntimeException('NorwayEhfIssued'); }
        } finally { flock($handle,LOCK_UN);fclose($handle); }
    }

    public function state($id)
    {
        return $this->locked($id, function($invoice,$directory) { return $this->load($directory); });
    }

    public function send($id)
    {
        return $this->locked($id,function($invoice,$directory) {
            $state=$this->load($directory);
            if (!in_array($state['status'],array('ready','rejected'),true)) { throw new RuntimeException('NorwayEhfDuplicate'); }
            if (!$this->db->begin()) { throw new RuntimeException('NorwayEhfStorage'); }
            try {
                $row=$this->db->query('SELECT rowid FROM '.$this->db->prefix().'facture WHERE rowid='.(int)$invoice->id.' FOR UPDATE');
                if (!$row || !$this->db->num_rows($row)) { throw new RuntimeException('NorwayEhfStorage'); }
                $xml=(new NorwayEhfXml($this->db))->generateFromInvoice($invoice->id);
                $document=new DOMDocument();$document->loadXML($xml,LIBXML_NONET);$xpath=new DOMXPath($document);
                $xpath->registerNamespace('cac','urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
                $xpath->registerNamespace('cbc','urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
                $endpoint=static function($party) use($xpath) { return $xpath->evaluate('string(/*/cac:'.$party.'/cac:Party/cbc:EndpointID/@schemeID)').':'.$xpath->evaluate('string(/*/cac:'.$party.'/cac:Party/cbc:EndpointID)'); };
                $type=(int)$invoice->type===2?'CreditNote':'Invoice';
                $body=array('sender'=>$endpoint('AccountingSupplierParty'),'recipient'=>$endpoint('AccountingCustomerParty'),
                    'processType'=>'cenbii-procid-ubl::'.NorwayEhfXml::PROFILE,
                    'documentType'=>'busdox-docid-qns::urn:oasis:names:specification:ubl:schema:xsd:'.$type.'-2::'.$type.'##'.NorwayEhfXml::CUSTOMIZATION.'::2.1',
                    'fileContent'=>base64_encode($xml));
                $attempt=bin2hex(random_bytes(12));$state=array('status'=>'pending','environment'=>'test','attempt'=>$attempt,'sha256'=>hash('sha256',$xml),'started'=>gmdate('c'),'sender'=>$body['sender'],'recipient'=>$body['recipient']);
                $this->write($directory.'/norway-ehf-test-'.$attempt.'.xml',$xml);
                // Persist before the HTTP call. A crash at any point from here must block automatic resending.
                $this->save($directory,$state);
                if (!$this->db->commit()) { throw new RuntimeException('NorwayEhfStorage'); }
            } catch (Throwable $error) { $this->db->rollback();throw $error; }
            try { $response=$this->http('POST','/message',$body); }
            catch (Throwable $error) { $response=array('http'=>0,'data'=>null); }
            $state['http']=(int)($response['http']??0);$data=$response['data']??null;
            $state['status']=in_array($state['http'],array(401,422),true)?'rejected':'unknown';
            if ($state['http']===200 && is_array($data) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/iD',(string)($data['id']??''))) {
                $state['id']=$data['id'];$state['status']='accepted';
            }
            if ($state['http']===422) {
                $reason=is_string($data)?$data:(is_array($data) && is_string($data['message']??null)?$data['message']:'');
                $reason=str_replace((string)getenv('NORWAY_EHF_TEST_API_KEY'),'[redacted]',$reason);
                $state['reason']=mb_substr(trim(strip_tags($reason)),0,1500);
            }
            $state['updated']=gmdate('c');$this->save($directory,$state);
            return $state;
        });
    }

    public function refresh($id)
    {
        return $this->locked($id,function($invoice,$directory) {
            $state=$this->load($directory);
            if (empty($state['id'])) { throw new RuntimeException('NorwayEhfUnknown'); }
            $response=$this->http('GET','/message/'.rawurlencode($state['id']));$data=$response['data']??null;
            if (($response['http']??0)!==200 || !is_array($data) || ($data['id']??'')!==$state['id'] || ($data['sender']??'')!==$state['sender'] || ($data['recipient']??'')!==$state['recipient'] || ($data['direction']??'')!=='OUT') { throw new RuntimeException('NorwayEhfStatusUnavailable'); }
            $folder=strtolower($data['folder']??'');
            if (!in_array($folder,array('outbox','sent','failed'),true)) { throw new RuntimeException('NorwayEhfStatusUnavailable'); }
            $state['status']=array('outbox'=>'accepted','sent'=>'sent','failed'=>'failed')[$folder];$state['updated']=gmdate('c');
            // Keep only the receipt's relevant fields, never headers, key, other messages or API debugging output.
            $receipt=array_intersect_key($data,array_flip(array('id','sender','recipient','direction','folder','created','confirmed')));
            $this->write($directory.'/norway-ehf-test-'.$state['attempt'].'-receipt.json',json_encode($receipt,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n");
            $this->save($directory,$state);return $state;
        });
    }

    private function http($method,$path,$body=null)
    {
        if ($this->request) { return ($this->request)($method,$path,$body); }
        $key=(string)getenv('NORWAY_EHF_TEST_API_KEY');
        if (!self::enabled() || preg_match('/[\r\n]/',$key)) { throw new RuntimeException('NorwayEhfDisabled'); }
        // Fixed host, no redirects, bounded time/response size; no arbitrary URL can receive credentials.
        $curl=curl_init('https://api.test.peppyrus.be/v1'.$path);$response='';
        curl_setopt_array($curl,array(CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>30,
            CURLOPT_HTTPHEADER=>array('Content-Type: application/json','Accept: application/json','X-Api-Key: '.$key),
            CURLOPT_WRITEFUNCTION=>static function($handle,$chunk) use (&$response) { if (strlen($response)+strlen($chunk)>2097152) { return 0; }$response.=$chunk;return strlen($chunk); }));
        if ($body!==null) { curl_setopt($curl,CURLOPT_POSTFIELDS,json_encode($body,JSON_THROW_ON_ERROR)); }
        $ok=curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE);curl_close($curl);
        return array('http'=>$ok===false?0:$status,'data'=>json_decode($response,true));
    }
}
