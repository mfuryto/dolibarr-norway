<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require '../../../main.inc.php';
require_once __DIR__.'/../class/NorwayLegacyArchive.php';
if (($_SERVER['REQUEST_METHOD'] ?? '')!=='GET') { http_response_code(405);header('Allow: GET');exit; }
try { $archive=NorwayLegacyArchive::read($db,(int)$conf->entity,$user,GETPOSTINT('after'),GETPOSTINT('record')); }
catch (InvalidArgumentException $error) { accessforbidden(); }
catch (Throwable $error) { http_response_code(503);exit('Archive unavailable'); }
header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
if (GETPOSTINT('download')===1 && GETPOSTINT('record')>0) {
    if (!$archive['rows']) { http_response_code(404);exit; }
    header('Content-Type: application/octet-stream');header('Content-Disposition: attachment; filename="norway-archive-'.(int)$archive['rows'][0]['rowid'].'.json"');
    print $archive['rows'][0]['payload'];exit;
}
$langs->load('norway@norway');llxHeader('',$langs->trans('NorwayArchiveTitle'));
print '<div class="norway-page">'.load_fiche_titre($langs->trans('NorwayArchiveTitle'),'','archive');
print '<p>'.$langs->trans('NorwayArchiveScope').'</p>';
if (!$archive['rows']) { print '<p>'.$langs->trans('NorwayArchiveEmpty').'</p>'; }
foreach ($archive['rows'] as $row) {
    print '<details><summary>'.dol_escape_htmltag($row['document_type'].' #'.$row['fk_document'].' / '.$row['revision'].' / '.$row['captured_at'].' UTC').'</summary>';
    if (!$row['intact']) { print '<div class="warning">'.$langs->trans('NorwayArchiveDamaged').'</div>'; }
    print '<p><a href="'.dol_buildpath('/norway/admin/evidence.php',1).'?download=1&amp;record='.(int)$row['rowid'].'">'.$langs->trans('NorwayArchiveDownload').'</a></p>';
    print '<pre style="max-width:100%;overflow:auto">'.dol_escape_htmltag($row['payload']).'</pre></details>';
}
if ($archive['next']) { print '<p><a href="?after='.$archive['next'].'">'.$langs->trans('Next').'</a></p>'; }
foreach (NorwayLegacyArchive::settings($db,(int)$conf->entity,$user) as $setting) { print '<details><summary>'.dol_escape_htmltag($setting['name']).'</summary><pre style="max-width:100%;overflow:auto">'.dol_escape_htmltag($setting['value']).'</pre></details>'; }
print '<p><a href="'.dol_buildpath('/norway/admin/setup.php',1).'">'.$langs->trans('Back').'</a></p></div>';
llxFooter();$db->close();
