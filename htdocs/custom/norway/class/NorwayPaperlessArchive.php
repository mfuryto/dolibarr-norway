<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require_once __DIR__.'/NorwayPaperless.php';
/** Export original purchase/manual evidence and native posting references without installation secrets. */
class NorwayPaperlessArchive
{
    public static function allowed($user)
    {
        return NorwayPaperless::allowed($user,'supplier') && NorwayPaperless::allowed($user,'voucher')
            && $user->hasRight('accounting','mouvements','export');
    }
    public static function build($db,$entity,$start,$end,$user)
    {
        global $conf;
        if(!self::allowed($user) || (int)$conf->entity!==$entity){throw new RuntimeException('NorwayPaperlessUnavailable');}
        foreach(array($start,$end) as $date){if(!preg_match('/^(20\d{2})-(\d{2})-(\d{2})$/D',$date,$m) || !checkdate((int)$m[2],(int)$m[3],(int)$m[1])){throw new RuntimeException('NorwayPaperlessArchivePeriod');}}
        if($start>$end || substr($start,0,4)!==substr($end,0,4)){throw new RuntimeException('NorwayPaperlessArchivePeriod');}
        $path=tempnam(sys_get_temp_dir(),'norway-documents-');$zip=new ZipArchive();$open=false;$total=0;
        try{
            if($zip->open($path,ZipArchive::OVERWRITE)!==true){throw new RuntimeException('NorwayPaperlessUnavailable');}$open=true;
            if(!$db->begin()){throw new RuntimeException('NorwayPaperlessUnavailable');}
            $p=$db->prefix();$range=" BETWEEN '".$db->escape($start)." 00:00:00' AND '".$db->escape($end)." 23:59:59'";
            $ledger=NorwayPaperless::rows($db,"SELECT * FROM {$p}accounting_bookkeeping WHERE entity=$entity AND doc_date".$range.' ORDER BY piece_num,rowid');
            $owners=array();foreach($ledger as $row){if($row->doc_type==='supplier_invoice'){$owners['supplier/'.(int)$row->fk_doc]=array('supplier',(int)$row->fk_doc);}elseif(in_array($row->doc_type,array('','manual','various'),true)){$owners['voucher/'.(int)$row->piece_num]=array('voucher',(int)$row->piece_num);}}
            // Include unposted and draft purchases too; an uploaded document is not proof of bookkeeping.
            foreach(NorwayPaperless::rows($db,"SELECT rowid FROM {$p}facture_fourn WHERE entity=$entity AND datef".$range.' ORDER BY rowid') as $row){$owners['supplier/'.(int)$row->rowid]=array('supplier',(int)$row->rowid);}
            $manifest=array('format'=>'norway-paperless-1','entity'=>$entity,'start'=>$start,'end'=>$end,'currency'=>$conf->currency,
                'scope'=>'Original PDF/PNG/JPEG attachments on supplier invoices and manual vouchers. Not a SAF-T export or a full installation backup.',
                'sources'=>array(),'ledger'=>$ledger);
            foreach($owners as $key=>[$kind,$id]){
                if($kind==='supplier' && !checkUserAccessToObject($user,array('fournisseur'),$id,'facture_fourn','facture','fk_soc','rowid')){throw new RuntimeException('NorwayPaperlessUnavailable');}
                $owner=NorwayPaperless::owner($db,$entity,$kind,$id);$source=array('kind'=>$kind,'id'=>$id,'ref'=>$owner['ref'],'posted'=>(bool)$owner['entries'],'files'=>array());
                foreach(NorwayPaperless::files($owner) as $name=>$file){
                    // Read once so the manifest hash describes the exact bytes archived, even during an upload.
                    $size=filesize($file);if($size===false || $size>200*1024*1024-$total){throw new RuntimeException('NorwayPaperlessArchiveSize');}
                    $bytes=file_get_contents($file);if($bytes===false){throw new RuntimeException('NorwayPaperlessUnavailable');}
                    $total+=strlen($bytes);if($total>200*1024*1024){throw new RuntimeException('NorwayPaperlessArchiveSize');}
                    $archiveName=$key.'/'.$name;if(!$zip->addFromString($archiveName,$bytes)){throw new RuntimeException('NorwayPaperlessUnavailable');}
                    $source['files'][]=array('path'=>$archiveName,'bytes'=>strlen($bytes),'sha256'=>hash('sha256',$bytes));
                }
                $source['documentation_missing']=!$source['files'];$manifest['sources'][]=$source;
            }
            if(!$zip->addFromString('manifest.json',json_encode($manifest,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)) || !$zip->close()){throw new RuntimeException('NorwayPaperlessUnavailable');}$open=false;
            $db->commit();return $path;
        }catch(Throwable $error){$db->rollback();if($open){$zip->close();}if(is_file($path)){unlink($path);}throw $error;}
    }
    public static function fields($user,$langs)
    {
        if(!self::allowed($user)){return '';}$langs->load('norway@norway');
        // Reuse the dates already present on the native export page; no second form or menu.
        return '<button class="button" type="submit" name="action" value="norway_paperless_export">'.dol_escape_htmltag($langs->trans('NorwayPaperlessArchive')).'</button>';
    }
    public static function action($db,$action,$user,$langs)
    {
        global $conf;
        if($action!=='norway_paperless_export' || ($_SERVER['SCRIPT_FILENAME']??'')!==DOL_DOCUMENT_ROOT.'/accountancy/bookkeeping/export.php'){return false;}
        if(($_SERVER['REQUEST_METHOD']??'')!=='POST' || !self::allowed($user) || empty($_SESSION['token']) || !hash_equals($_SESSION['token'],GETPOST('token','alphanohtml'))){http_response_code(403);exit;}
        try{
            $path=self::build($db,(int)$conf->entity,GETPOST('norway_saft_start','alphanohtml'),GETPOST('norway_saft_end','alphanohtml'),$user);
            header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');header('Content-Type: application/zip');header('Content-Disposition: attachment; filename="voucher-documents.zip"');header('Content-Length: '.filesize($path));
            try{readfile($path);}finally{unlink($path);}exit;
        }catch(Throwable $error){$langs->load('norway@norway');$key=in_array($error->getMessage(),array('NorwayPaperlessArchivePeriod','NorwayPaperlessArchiveSize'),true)?$error->getMessage():'NorwayPaperlessUnavailable';setEventMessages($langs->trans($key),null,'errors');return true;}
    }
}
