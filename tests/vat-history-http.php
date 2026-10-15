<?php
// SPDX-License-Identifier: GPL-3.0-or-later
if(PHP_SAPI!=='cli'||getenv('NORWAY_SYNTHETIC_TEST')!=='1'||getenv('DOLI_DB_NAME')!=='norway_test'){exit(1);}
require '/var/www/html/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/accountancy/class/bookkeeping.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/norway/class/NorwayPostingEvidence.php';
require_once DOL_DOCUMENT_ROOT.'/custom/norway/class/NorwayInvoiceValidation.php';
function hhAssert($ok,$message){if(!$ok){throw new RuntimeException($message);}}
function hhSql($sql){global $db;$r=$db->query($sql);hhAssert((bool)$r,$db->lasterror());return $r;}
$user->fetch('','testadmin');$user->loadRights('',1);$entity=(int)$conf->entity;$p=$db->prefix();$posted=array();
$names="'MAIN_INFO_SIREN','MAIN_INFO_SOCIETE_COUNTRY','".NorwayVatRegistration::SETTING."','".NorwayVatHistory::SETTING."'";
$r=hhSql('SELECT * FROM '.$p.'const WHERE entity='.$entity.' AND name IN ('.$names.')');$original=array();while($o=$db->fetch_object($r)){$original[]=(array)$o;}
$invoice=$db->fetch_object(hhSql("SELECT f.rowid,f.ref,f.datef FROM ".$p."facture f WHERE f.entity=".$entity." AND f.type=0 AND f.fk_statut IN (1,2) AND f.multicurrency_code='NOK' AND f.datef BETWEEN '2026-01-01' AND '2026-12-31' ORDER BY f.rowid LIMIT 1"));hhAssert((bool)$invoice,'Synthetic 2026 invoice missing');
$cookie=tempnam(sys_get_temp_dir(),'norway-history-http-');$curl=curl_init();curl_setopt_array($curl,array(CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_TIMEOUT=>20));
function hhPage($path,$post=null){global $curl;curl_setopt($curl,CURLOPT_URL,'http://localhost'.$path);curl_setopt($curl,CURLOPT_POST,$post!==null);if($post!==null){curl_setopt($curl,CURLOPT_POSTFIELDS,http_build_query($post));}$body=curl_exec($curl);hhAssert($body!==false&&curl_getinfo($curl,CURLINFO_HTTP_CODE)<500,'History HTTP failed');return $body;}
function hhField($body,$field){hhAssert(preg_match('/name="'.$field.'"[^>]*value="([^"]+)"/',$body,$m)===1,'Missing history form field '.$field);return html_entity_decode($m[1],ENT_QUOTES,'UTF-8');}
function hhCapture(){global $db,$user,$entity,$invoice,$posted,$p;$o=new BookKeeping($db);$o->entity=$entity;$o->doc_date=strtotime('2026-01-01');$o->doc_type='customer_invoice';$o->doc_ref=$invoice->ref;$o->fk_doc=(int)$invoice->rowid;$o->fk_docdet=0;$o->numero_compte='1500';$o->label_compte='Synthetic history observation';$o->label_operation='Synthetic history observation';$o->debit=1;$o->credit=0;$o->montant=1;$o->sens='D';$o->code_journal='SYN-HISTORY';$o->journal_label='Synthetic';$o->piece_num=987703;$o->ref='SYN-HISTORY';hhAssert($o->createStd($user)>0,'Native history capture failed');$posted[]=(int)$o->id;$row=$db->fetch_object(hhSql("SELECT payload FROM ".$p."norway_tax_evidence WHERE entity=".$entity." AND document_type='bookkeeping_event' AND fk_document=".(int)$o->id));return json_decode($row->payload,true,512,JSON_THROW_ON_ERROR);}
try{
 $legacy=json_encode(array('version'=>1,'entity'=>$entity,'org'=>'123456785','start'=>'2024-02-29','confirmed_by'=>(int)$user->id,'confirmed_at'=>'2024-03-01T00:00:00+00:00'));
 hhSql('DELETE FROM '.$p.'const WHERE entity='.$entity.' AND name IN ('.$names.')');
 foreach(array('MAIN_INFO_SIREN'=>'123456785','MAIN_INFO_SOCIETE_COUNTRY'=>'173:NO:Norway',NorwayVatRegistration::SETTING=>$legacy) as $name=>$value){hhSql("INSERT INTO ".$p."const (name,value,type,entity) VALUES ('".$name."','".$db->escape($value)."','chaine',".$entity.")");}
 $before=hhCapture();hhAssert($before['version']===3&&$before['registration_context']['version']===1,'Legacy observation changed before opt-in');
 $path='/custom/norway/admin/registration.php?mainmenu=accountancy&leftmenu=accountancy_admin_norway';
 $body=hhPage($path);hhAssert(strpos($body,'history_start')===false,'History form exposed before login');
 hhPage('/index.php',array('token'=>hhField($body,'token'),'username'=>'testadmin','password'=>getenv('DOLI_ADMIN_PASSWORD'),'actionlogin'=>'login','loginfunction'=>'loginfunction'));
 $body=hhPage($path);$input=array('action'=>'previewhistory','token'=>hhField($body,'token'),'period'=>'1','history_start'=>'2024-02-29','history_end'=>'2025-12-31','history_reason'=>'Synthetic confirmed deregistration');
 $preview=hhPage($path,$input);hhAssert(!NorwayVatHistory::configured($db,$entity),'History preview wrote data');
 $save=array('action'=>'savehistory','token'=>hhField($preview,'token'),'preview_nonce'=>hhField($preview,'preview_nonce'),'confirmed'=>'1');hhPage($path,$save);
 $state=NorwayVatHistory::read($db,$entity,'123456785');hhAssert($state['revision']===2&&$state['active'][1]['end']==='2025-12-31','HTTP history import/save missing');
 $gap=hhCapture();hhAssert($gap['version']===8&&$gap['registration_context']['version']===2&&$gap['registration_context']['status']==='HistoryGap','Native v8 gap not captured');
 $fake=(object)array('type'=>0,'total_tva'=>25,'lines'=>array(),'date'=>strtotime('2026-01-01'));hhAssert(NorwayInvoiceValidation::registrationWarning($db,$fake,(object)array('idprof1'=>'123456785'),$entity)==='HistoryGap','Native gap warning missing');
 hhPage($path,$save);hhAssert(NorwayVatHistory::read($db,$entity,'123456785')['revision']===2,'HTTP replay appended history');
 $body=hhPage($path.'&period=0');$preview=hhPage($path,array('action'=>'previewhistory','token'=>hhField($body,'token'),'period'=>'0','history_start'=>'2026-01-01','history_end'=>'','history_reason'=>'Synthetic new registration'));
 $save=array('action'=>'savehistory','token'=>hhField($preview,'token'),'preview_nonce'=>hhField($preview,'preview_nonce'),'confirmed'=>'1');hhPage($path,$save);
 $covered=hhCapture();hhAssert($covered['version']===8&&$covered['registration_context']['status']==='HistoryCovered'&&$covered['registration_context']['history_revision']===3,'Native v8 period context missing');
 hhAssert(NorwayInvoiceValidation::registrationWarning($db,$fake,(object)array('idprof1'=>'123456785'),$entity)==='','Stated covering period warning inconsistent');
 foreach(array('nb_NO','en_US') as $language){$body=hhPage($path.'&lang='.$language);hhAssert(strpos($body,'NorwayVatHistory')===false&&strpos($body,'2025-12-31')!==false,'History bilingual rendering failed');$body=hhPage('/custom/norway/admin/invoice.php?id='.(int)$invoice->rowid.'&ledger=1&lang='.$language);hhAssert(strpos($body,'NorwayPostingRegistrationHistory')===false&&strpos($body,'NorwayRegistrationHistory')===false,'Invoice history translation missing');}
 $seen=array();foreach(NorwayPostingEvidence::report($db,$entity,(int)$invoice->rowid,$user) as $entry){if(in_array($entry['id'],$posted,true)){$seen[]=$entry['data']['registration_context']['status'];}}
 hhAssert(in_array('After',$seen,true)&&in_array('HistoryGap',$seen,true)&&in_array('HistoryCovered',$seen,true),'Earlier captured contexts reinterpreted');
 hhPage($path,array('action'=>'savehistory','token'=>'invalid','confirmed'=>'1'));hhAssert(curl_getinfo($curl,CURLINFO_HTTP_CODE)===403&&NorwayVatHistory::read($db,$entity,'123456785')['revision']===3,'History CSRF protection failed');
 $legacyRow=$db->fetch_object(hhSql("SELECT value FROM ".$p."const WHERE entity=".$entity." AND name='".NorwayVatRegistration::SETTING."'"));hhAssert($legacyRow->value===$legacy,'History changed original first-date constant');
 print "PASS registration history preview/confirm/replay/CSRF, bilingual native UI, v3 preservation, v8 gap/coverage capture and native warnings\n";
}finally{
 if($posted){hhSql("DELETE FROM ".$p."norway_tax_evidence WHERE entity=".$entity." AND document_type='bookkeeping_event' AND fk_document IN (".implode(',',$posted).")");hhSql('DELETE FROM '.$p.'accounting_bookkeeping WHERE rowid IN ('.implode(',',$posted).') AND entity='.$entity);}
 hhSql('DELETE FROM '.$p.'const WHERE entity='.$entity.' AND name IN ('.$names.')');foreach($original as $row){$values=array_map(static fn($v)=>$v===null?'NULL':"'".$db->escape((string)$v)."'",array_values($row));hhSql('INSERT INTO '.$p.'const ('.implode(',',array_keys($row)).') VALUES ('.implode(',',$values).')');}
 curl_close($curl);unlink($cookie);unset($conf->global->{NorwayVatHistory::SETTING});
}
