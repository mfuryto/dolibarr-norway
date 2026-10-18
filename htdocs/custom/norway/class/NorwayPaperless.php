<?php
// SPDX-License-Identifier: GPL-3.0-or-later
/** Attach originals to native purchases/vouchers; never create another journal or inbox model. */
class NorwayPaperless
{
    public static function allowed($user, $kind, $write = false)
    {
        if (!empty($user->socid)) { return false; }
        if ($kind === 'supplier') {
            return $user->hasRight('fournisseur', 'facture', $write ? 'creer' : 'lire')
                || $user->hasRight('supplier_invoice', $write ? 'creer' : 'lire');
        }
        return $kind === 'voucher' && $user->hasRight('accounting', 'mouvements', $write ? 'creer' : 'lire');
    }

    public static function rows($db, $sql)
    {
        $result = $db->query($sql);
        if (!$result) { throw new RuntimeException('NorwayPaperlessUnavailable'); }
        $rows = array(); if($result===true){return $rows;} while ($row = $db->fetch_object($result)) { $rows[] = $row; } return $rows;
    }

    public static function owner($db, $entity, $kind, $id)
    {
        global $conf;
        require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
        if ($entity !== (int)$conf->entity || $id <= 0) { throw new RuntimeException('NorwayPaperlessUnavailable'); }
        if ($kind === 'supplier') {
            require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';
            $native = new FactureFournisseur($db);
            if ($native->fetch($id) <= 0 || (int)$native->entity !== $entity) { throw new RuntimeException('NorwayPaperlessUnavailable'); }
            $native->fetch_thirdparty();
            $relative = get_exdir($id, 2, 0, 0, $native, 'invoice_supplier').dol_sanitizeFileName($native->ref);
            $dir = getMultidirOutput($native).'/'.$relative;
            $entries = self::rows($db, 'SELECT * FROM '.$db->prefix().'accounting_bookkeeping WHERE entity='.$entity." AND doc_type='supplier_invoice' AND fk_doc=".$id.' ORDER BY rowid');
            return array('kind'=>$kind, 'id'=>$id, 'native'=>$native, 'dir'=>$dir, 'relative'=>$relative,
                'ref'=>$native->ref, 'entries'=>$entries, 'protected'=>(int)$native->statut !== 0 || (bool)$entries,
                'back'=>'/fourn/facture/card.php?id='.$id);
        }
        if ($kind !== 'voucher') { throw new RuntimeException('NorwayPaperlessUnavailable'); }
        $entries = self::rows($db, 'SELECT * FROM '.$db->prefix().'accounting_bookkeeping WHERE entity='.$entity.' AND piece_num='.$id.' ORDER BY rowid');
        if (!$entries) { throw new RuntimeException('NorwayPaperlessUnavailable'); }
        // For derived journals use the original source card, not a second attachment location.
        foreach ($entries as $entry) {
            if (!in_array($entry->doc_type, array('', 'manual', 'various'), true)) { throw new RuntimeException('NorwayPaperlessSource'); }
        }
        require_once DOL_DOCUMENT_ROOT.'/accountancy/class/bookkeeping.class.php';
        $native=new BookKeeping($db);
        if($native->fetch((int)$entries[0]->rowid)<=0){throw new RuntimeException('NorwayPaperlessUnavailable');}
        return array('kind'=>$kind, 'id'=>$id, 'native'=>$native,
            'dir'=>$conf->accounting->dir_output.'/norway-vouchers/'.$entity.'/'.$id.'/'.(int)$entries[0]->rowid,
            'ref'=>(string)$id, 'entries'=>$entries, 'protected'=>true,
            'back'=>'/accountancy/bookkeeping/card.php?piece_num='.$id);
    }

    public static function files($owner)
    {
        global $db;
        $generated=array();
        if($owner['kind']==='supplier'){
            foreach(self::rows($db,'SELECT filename FROM '.$db->prefix()."ecm_files WHERE entity=".(int)$owner['native']->entity." AND src_object_type IN ('facture_fourn','invoice_supplier') AND src_object_id=".$owner['id']." AND gen_or_uploaded='generated'") as $row){$generated[$row->filename]=true;}
        }
        $out = array();
        if (!is_dir($owner['dir'])) { return $out; }
        foreach (scandir($owner['dir']) as $name) {
            if (isset($generated[$name]) || $name[0] === '.' || !preg_match('/\.(pdf|png|jpe?g)$/iD', $name) || strpos($name, '_preview') !== false) { continue; }
            $path = $owner['dir'].'/'.$name;
            if (!is_link($path) && is_file($path) && realpath(dirname($path)) === realpath($owner['dir'])) { $out[$name] = $path; }
        }
        return $out;
    }

    public static function url($owner, $name, $inline = false)
    {
        return DOL_URL_ROOT.'/custom/norway/paperless.php?kind='.$owner['kind'].'&id='.$owner['id'].'&file='.rawurlencode($name).($inline ? '&inline=1' : '');
    }

    public static function validFile($path, $name)
    {
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
        return ($extension === 'pdf' && $mime === 'application/pdf' && file_get_contents($path, false, null, 0, 5) === '%PDF-')
            || (in_array($extension, array('png','jpg','jpeg'), true) && in_array($mime, array('image/png','image/jpeg'), true)
                && @getimagesize($path) !== false && (($extension === 'png') === ($mime === 'image/png')));
    }

    public static function panel($db, $entity, $kind, $id, $user, $langs)
    {
        if (!self::allowed($user, $kind)) { return ''; }
        $langs->load('norway@norway'); $e = static fn($s)=>dol_escape_htmltag($s);
        $owner = self::owner($db, $entity, $kind, $id); $files = self::files($owner);
        $html = '<section id="norway-paperless" class="norway-paperless"><h3>'.$e($langs->trans('NorwayPaperlessTitle')).'</h3>';
        $html .= '<p class="'.($files?'opacitymedium':'warning').'">'.$e($langs->trans($files?'NorwayPaperlessHelp':'NorwayPaperlessMissing')).'</p>';
        $html .= '<p>'.$e($langs->trans($owner['entries']?'NorwayPaperlessPosted':'NorwayPaperlessUnposted')).'</p>';
        if ($kind === 'supplier') {
            $native = $owner['native'];
            $duplicate = self::rows($db, 'SELECT rowid,ref FROM '.$db->prefix().'facture_fourn WHERE entity='.$entity.' AND fk_soc='.(int)$native->socid
                ." AND ref_supplier='".$db->escape($native->ref_supplier)."' AND rowid<>".$id);
            if ($duplicate) { $html .= '<p class="warning">'.$e($langs->trans('NorwayPaperlessDuplicate')).'</p>'; }
            $html .= '<p><a href="'.DOL_URL_ROOT.'/fourn/facture/document.php?id='.$id.'">'.$e($langs->trans('Documents')).'</a> · '
                .'<a href="'.DOL_URL_ROOT.'/fourn/facture/list.php">'.$e($langs->trans('BackToList')).'</a></p>';
        }
        if (self::allowed($user, $kind, true)) {
            $html .= '<form method="post" enctype="multipart/form-data" action="'.DOL_URL_ROOT.'/custom/norway/paperless.php">'
                .'<input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="kind" value="'.$kind.'">'
                .'<input type="hidden" name="id" value="'.$id.'"><input type="hidden" name="action" value="upload">'
                .'<label>'.$e($langs->trans('NorwayPaperlessUpload')).' <input type="file" name="userfile" accept="application/pdf,image/png,image/jpeg" required></label> '
                .'<button class="button" type="submit">'.$e($langs->trans('Upload')).'</button></form>';
        }
        foreach ($files as $name=>$path) {
            $html .= '<details class="norway-paperless-preview"><summary>'.$e($name).'</summary><p><a href="'.$e(self::url($owner,$name)).'">'.$e($langs->trans('Download')).'</a> · <a target="_blank" rel="noopener noreferrer" href="'.$e(self::url($owner,$name,true)).'">'.$e($langs->trans('NorwayPaperlessOpen')).'</a></p>';
            if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) === 'pdf') {
                $html .= '<iframe loading="lazy" title="'.$e($name).'" src="'.$e(self::url($owner,$name,true)).'"></iframe>';
            } else { $html .= '<img loading="lazy" alt="'.$e($name).'" src="'.$e(self::url($owner,$name,true)).'">'; }
            $html .= '</details>';
        }
        if ($files && $owner['protected']) { $html .= '<p class="opacitymedium">'.$e($langs->trans('NorwayPaperlessRetained')).'</p>'; }
        $html .= '</section><style>.norway-paperless{clear:both;width:100%;box-sizing:border-box;max-width:100%;min-width:0;overflow-wrap:anywhere;border:1px solid #ccc;padding:1em;margin:1em 0}.norway-paperless form{display:flex;flex-wrap:wrap;gap:.5em;align-items:center}.norway-paperless input[type=file]{max-width:100%}.norway-paperless-preview{margin:.7em 0}.norway-paperless-preview iframe{width:100%;height:65vh;border:0}.norway-paperless-preview img{max-width:100%;height:auto}.norway-paperless-workspace{display:block}@media(min-width:1800px){.norway-paperless-workspace{display:grid;grid-template-columns:minmax(1000px,1fr) minmax(350px,32%);gap:1em;align-items:start}}.norway-paperless-workspace> *{min-width:0}.norway-paperless-workspace>#norway-paperless{position:sticky;top:1em;max-height:90vh;overflow:auto}@media(max-width:1799px){.norway-paperless-workspace{display:block}.norway-paperless-workspace>#norway-paperless{position:static;max-height:none}}</style>';
        // Place the existing card and originals together; do not wrap/move individual native forms.
        if ($kind === 'supplier') {
            $html .= '<script>jQuery(function(){var p=document.getElementById("norway-paperless"),f=document.querySelector(".fiche");if(p&&f&&!p.contains(f)&&!f.contains(p)){var w=document.createElement("div");w.className="norway-paperless-workspace";f.parentNode.insertBefore(w,f);w.appendChild(f);w.appendChild(p);}});</script>';
        }
        return $html;
    }

    public static function guardSource($db, $entity, $action, $object)
    {
        if(in_array($action,array('BILL_SUPPLIER_DELETE','BILL_SUPPLIER_UNVALIDATE'),true)){
            $owner=self::owner($db,$entity,'supplier',(int)$object->id);
            if(self::files($owner) && ($owner['protected'] || $action==='BILL_SUPPLIER_UNVALIDATE')){throw new RuntimeException('NorwayPaperlessRetained');}
        }
        if($action==='BOOKKEEPING_DELETE' && (int)($object->piece_num??0)>0){
            try{$owner=self::owner($db,$entity,'voucher',(int)$object->piece_num);}catch(RuntimeException $error){if($error->getMessage()==='NorwayPaperlessSource'){return;}throw $error;}
            if(self::files($owner)){throw new RuntimeException('NorwayPaperlessRetained');}
        }
    }

    /** Map a file back to its native invoice even when called by the ECM module. */
    public static function protectedFile($db, $entity, $path)
    {
        global $conf;
        if (strpos($path, '..') !== false) { return true; }
        $manualRoot = $conf->accounting->dir_output.'/norway-vouchers/';
        if (strpos($path, $manualRoot) === 0) { return true; }
        $relative = ltrim(substr($path, strlen(DOL_DATA_ROOT)), '/');
        if (strpos($path, DOL_DATA_ROOT.'/') !== 0) { return false; }
        $rows = self::rows($db, 'SELECT f.rowid,f.fk_statut,e.gen_or_uploaded FROM '.$db->prefix().'ecm_files e JOIN '.$db->prefix()
            .'facture_fourn f ON f.rowid=e.src_object_id AND f.entity=e.entity WHERE e.entity='.$entity
            ." AND e.src_object_type IN ('facture_fourn','invoice_supplier') AND e.filepath='".$db->escape(dirname($relative))."' AND e.filename='".$db->escape(basename($relative))."'");
        foreach ($rows as $row) {
            if ($row->gen_or_uploaded === 'generated') { continue; }
            $owner = self::owner($db, $entity, 'supplier', (int)$row->rowid);
            if ($owner['protected']) { return true; }
        }
        return false;
    }
}
