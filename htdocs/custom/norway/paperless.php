<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require '../../main.inc.php';
require_once __DIR__.'/class/NorwayPaperless.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
$langs->load('norway@norway');
function norwayPaperlessDeny(){http_response_code(403);exit;}

$kind=GETPOST('kind','aZ09');$id=GETPOSTINT('id');
if (!isModEnabled('norway') || !NorwayPaperless::allowed($user,$kind)) { norwayPaperlessDeny(); }
header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');
try { $owner=NorwayPaperless::owner($db,(int)$conf->entity,$kind,$id); }
catch(Throwable $error) { norwayPaperlessDeny(); }
if($kind==='supplier' && !checkUserAccessToObject($user,array('fournisseur'),$id,'facture_fourn','facture','fk_soc','rowid')){norwayPaperlessDeny();}
if (GETPOSTISSET('file')) {
    $name=GETPOST('file','none');$files=NorwayPaperless::files($owner);
    if (!isset($files[$name]) || !NorwayPaperless::validFile($files[$name],$name)) { norwayPaperlessDeny(); }
    header('Content-Type: '.(new finfo(FILEINFO_MIME_TYPE))->file($files[$name]));
    header("Content-Security-Policy: frame-ancestors 'self'");
    header('Content-Disposition: '.(GETPOSTINT('inline')?'inline':'attachment')."; filename*=UTF-8''".rawurlencode($name));
    header('Content-Length: '.filesize($files[$name])); readfile($files[$name]); exit;
}
if (($_SERVER['REQUEST_METHOD']??'')!=='POST' || GETPOST('action','aZ09')!=='upload'
    || !getDolGlobalInt('MAIN_UPLOAD_DOC') || !NorwayPaperless::allowed($user,$kind,true) || empty($_SESSION['token'])
    || !hash_equals($_SESSION['token'],GETPOST('token','alphanohtml'))) { norwayPaperlessDeny(); }
$lock='norway-paperless-'.(int)$conf->entity.'-'.$kind.'-'.$id;
$locked=false;
try {
    $r=NorwayPaperless::rows($db,"SELECT GET_LOCK('".$db->escape($lock)."',0) AS acquired");
    if ((int)$r[0]->acquired!==1) { throw new RuntimeException('NorwayPaperlessBusy'); } $locked=true;
    $file=$_FILES['userfile']??array();$name=$file['name']??'';$tmp=$file['tmp_name']??'';
    if (!is_string($name) || !is_string($tmp) || ($file['error']??1)!==UPLOAD_ERR_OK || !is_uploaded_file($tmp)
        || $name!==basename($name) || $name!==dol_sanitizeFileName($name) || $name[0]==='.'
        || filesize($tmp)<=0 || filesize($tmp)>20*1024*1024
        || !NorwayPaperless::validFile($tmp,$name)) { throw new RuntimeException('NorwayPaperlessFile'); }
    if (file_exists($owner['dir'].'/'.$name)) { throw new RuntimeException('NorwayPaperlessDuplicate'); }
    foreach (NorwayPaperless::files($owner) as $existing) {
        if (hash_file('sha256',$existing)===hash_file('sha256',$tmp)) { throw new RuntimeException('NorwayPaperlessDuplicate'); }
    }
    // The core uploader handles filesystem permissions, virus scanning and the ECM index.
    $object=$owner['native'];
    if (dol_add_file_process($owner['dir'],0,1,'userfile','',null,'',1,$object)<=0 || !is_file($owner['dir'].'/'.$name)) {
        throw new RuntimeException('NorwayPaperlessFile');
    }
    setEventMessages($langs->trans('NorwayPaperlessSaved'),null,'mesgs');
} catch(Throwable $error) {
    $key=in_array($error->getMessage(),array('NorwayPaperlessFile','NorwayPaperlessDuplicate','NorwayPaperlessBusy'),true)?$error->getMessage():'NorwayPaperlessUnavailable';
    setEventMessages($langs->trans($key),null,'errors');
} finally { if($locked){$db->query("SELECT RELEASE_LOCK('".$db->escape($lock)."')");} }
header('Location: '.DOL_URL_ROOT.$owner['back'].'#norway-paperless'); exit;
