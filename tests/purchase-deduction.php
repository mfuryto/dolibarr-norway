<?php
// SPDX-License-Identifier: GPL-3.0-or-later
if(PHP_SAPI!=='cli'||getenv('NORWAY_SYNTHETIC_TEST')!=='1'||getenv('DOLI_DB_NAME')!=='norway_test'){exit(1);}
require '/var/www/html/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/norway/class/NorwayPurchaseDeduction.php';
function pdAssert($ok,$message){if(!$ok){throw new RuntimeException($message);}}
function pdSql($sql){global $db;$r=$db->query($sql);pdAssert((bool)$r,'Synthetic deduction query');return $r;}
function pdBlocked($call,$message){$blocked=false;try{$call();}catch(Throwable $error){$blocked=true;}pdAssert($blocked,$message);}
pdAssert($user->fetch('','testadmin')>0,'Admin missing');$user->loadRights('',1);$p=$db->prefix();$entity=(int)$conf->entity;
$row=$db->fetch_object(pdSql("SELECT rowid FROM ".$p."facture_fourn WHERE entity=".$entity." AND ref_supplier LIKE 'SYND-%-0' ORDER BY rowid DESC LIMIT 1"));pdAssert((bool)$row,'Purchase fixture missing');$id=(int)$row->rowid;
$reason='Synthetic shared business use, documented 50 percent';
$ledger=pdSql("SELECT rowid,debit,credit FROM ".$p."accounting_bookkeeping WHERE entity=".$entity." AND doc_type='supplier_invoice' AND fk_doc=".$id);$before=array();while($r=$db->fetch_object($ledger)){$before[]=$r;}
try{
 $preview=NorwayPurchaseDeduction::preview($db,$entity,$id,$user,'purchase25partial','50',$reason);
 pdAssert($preview['revision']===1&&$preview['choice']['basis_points']===5000&&$preview['lines'][0]['deductible']==='12.50000000','Line preview failed');
 pdBlocked(fn()=>NorwayPurchaseDeduction::preview($db,$entity,$id,$user,'purchase25partial',null,$reason),'Missing share accepted');
 pdBlocked(fn()=>NorwayPurchaseDeduction::preview($db,$entity,$id,$user,'purchase25partial','50',''),'Missing reason accepted');
 pdBlocked(fn()=>NorwayPurchaseDeduction::preview($db,$entity+1,$id,$user,'purchase25full',null,$reason),'Cross entity accepted');
 $restricted=clone $user;$restricted->admin=0;pdBlocked(fn()=>NorwayPurchaseDeduction::history($db,$entity,$id,$restricted),'Non-admin accepted');
 $line=(int)$preview['basis']['lines'][0]['rowid'];$description=$preview['basis']['lines'][0]['description'];
 pdSql("UPDATE ".$p."facture_fourn_det SET description='Synthetic changed basis' WHERE rowid=".$line);
 pdBlocked(fn()=>NorwayPurchaseDeduction::save($db,$entity,$id,$user,'purchase25partial','50',$reason,$preview['fingerprint']),'Stale source accepted');
 pdSql("UPDATE ".$p."facture_fourn_det SET description='".$db->escape($description)."' WHERE rowid=".$line);
 pdAssert(NorwayPurchaseDeduction::save($db,$entity,$id,$user,'purchase25partial','50',$reason,$preview['fingerprint'])===1,'Save failed');
 pdBlocked(fn()=>NorwayPurchaseDeduction::save($db,$entity,$id,$user,'purchase25partial','50',$reason,$preview['fingerprint']),'Replay accepted');
 $next=NorwayPurchaseDeduction::preview($db,$entity,$id,$user,'purchase25none',null,'Synthetic revised business use');
 pdAssert(NorwayPurchaseDeduction::save($db,$entity,$id,$user,'purchase25none',null,'Synthetic revised business use',$next['fingerprint'])===2,'Second revision failed');
 $history=NorwayPurchaseDeduction::history($db,$entity,$id,$user);pdAssert(count($history)===2&&$history[0]['data']['choice']['basis_points']===5000&&$history[1]['data']['choice']['basis_points']===0,'Append history lost');
 pdSql("UPDATE ".$p."norway_tax_evidence SET payload_sha256=REPEAT('0',64) WHERE entity=".$entity." AND document_type='supplier_deduction' AND fk_document=".$id.' AND revision=2');
 pdBlocked(fn()=>NorwayPurchaseDeduction::history($db,$entity,$id,$user),'Corrupt payload accepted');
}finally{pdSql("DELETE FROM ".$p."norway_tax_evidence WHERE entity=".$entity." AND document_type='supplier_deduction' AND fk_document=".$id);}
$ledger=pdSql("SELECT rowid,debit,credit FROM ".$p."accounting_bookkeeping WHERE entity=".$entity." AND doc_type='supplier_invoice' AND fk_doc=".$id);$after=array();while($r=$db->fetch_object($ledger)){$after[]=$r;}pdAssert($before==$after,'Decision changed ledger');
// Independent rate/mode and credit-note calculations use native synthetic invoices.
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';
$source=$db->fetch_object(pdSql('SELECT fk_soc FROM '.$p.'facture_fourn WHERE rowid='.$id));
foreach(array(15,12) as $rate){
 $invoice=new FactureFournisseur($db);$invoice->socid=(int)$source->fk_soc;$invoice->date=dol_mktime(12,0,0,1,10,2026);$invoice->type=0;$invoice->ref_supplier='SYNPDR-'.$rate.'-'.bin2hex(random_bytes(4));$invoice->multicurrency_code='NOK';$invoice->multicurrency_tx=1;
 pdAssert($invoice->create($user)>0&&$invoice->addline('Synthetic retained deduction rate',100,$rate,0,0,1)>0&&$invoice->validate($user,$invoice->ref_supplier)>0,'Rate invoice fixture');
 foreach(array('full'=>10000,'partial'=>5000,'none'=>0) as $mode=>$bp){$pv=NorwayPurchaseDeduction::preview($db,$entity,(int)$invoice->id,$user,'purchase'.$rate.$mode,$mode==='partial'?'50':null,$reason);pdAssert($pv['choice']['basis_points']===$bp&&$pv['lines'][0]['deductible']===NorwayInvoiceReconciliation::display($rate*100000000*$bp/10000),'Rate/mode amount');}
}
$credit=$db->fetch_object(pdSql("SELECT rowid FROM ".$p."facture_fourn WHERE entity=".$entity." AND ref_supplier LIKE 'SYND-%-2' ORDER BY rowid DESC LIMIT 1"));pdAssert((bool)$credit,'Credit fixture');
$pv=NorwayPurchaseDeduction::preview($db,$entity,(int)$credit->rowid,$user,'purchase25partial','50',$reason);pdAssert($pv['lines'][0]['deductible']==='-2.50000000','Credit sign lost');
$db->begin();try{pdBlocked(fn()=>NorwayPurchaseDeduction::save($db,$entity,$id,$user,'purchase25partial','50',$reason,'invalid'),'Nested transaction accepted');}finally{$db->rollback();}

// Exercise native card -> protected endpoint -> preview on card -> confirm -> history.
$cookie=tempnam(sys_get_temp_dir(),'norway-deduction-');$curl=curl_init();curl_setopt_array($curl,array(CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_TIMEOUT=>30));
function pdHttp($path,$post=null){global $curl;curl_setopt($curl,CURLOPT_URL,'http://localhost'.$path);curl_setopt($curl,CURLOPT_POST,$post!==null);if($post!==null){curl_setopt($curl,CURLOPT_POSTFIELDS,http_build_query($post));}$body=curl_exec($curl);pdAssert($body!==false,'HTTP failed');return $body;}
function pdToken($body){pdAssert(preg_match('/name="token"[^>]*value="([^"]+)"/',$body,$m)===1,'Token missing');return html_entity_decode($m[1]);}
try{
 $body=pdHttp('/index.php');pdHttp('/index.php',array('token'=>pdToken($body),'actionlogin'=>'login','loginfunction'=>'loginfunction','username'=>'testadmin','password'=>getenv('DOLI_ADMIN_PASSWORD')));
 $card='/fourn/facture/card.php?facid='.$id.'&lang=nb_NO';$endpoint='/custom/norway/admin/purchasededuction.php';$body=pdHttp($card);
 $data=array('token'=>pdToken($body),'id'=>$id,'action'=>'preview','profile'=>'purchase25partial','percentage'=>'50','reason'=>$reason);
 $body=pdHttp($endpoint,$data);pdAssert(strpos($body,'name="nonce"')!==false&&strpos($body,'12.50')!==false,'Preview absent from native card');
 pdAssert(preg_match('/name="nonce"[^>]*value="([^"]+)"/',$body,$m)===1,'Nonce missing');$nonce=$m[1];
 $body=pdHttp($endpoint,array('token'=>pdToken($body),'id'=>$id,'action'=>'save','nonce'=>$nonce,'confirmed'=>'1'));
 pdAssert(count(NorwayPurchaseDeduction::history($db,$entity,$id,$user))===1,'HTTP save absent');
 $body=pdHttp($endpoint,array('token'=>pdToken($body),'id'=>$id,'action'=>'save','nonce'=>$nonce,'confirmed'=>'1'));pdAssert(count(NorwayPurchaseDeduction::history($db,$entity,$id,$user))===1,'HTTP replay created revision');
 foreach(array('nb_NO','en_US') as $locale){$body=pdHttp('/fourn/facture/card.php?facid='.$id.'&lang='.$locale);pdAssert(strpos($body,'NorwayDeduction')===false&&strpos($body,$reason)!==false,'History missing/untranslated');}
 pdSql("UPDATE ".$p."facture_fourn_det SET description='Synthetic source drift' WHERE rowid=".$line);
 try{$body=pdHttp($card);pdAssert(strpos($body,'Fakturagrunnlaget er endret')!==false,'Source drift warning absent');}finally{pdSql("UPDATE ".$p."facture_fourn_det SET description='".$db->escape($description)."' WHERE rowid=".$line);}
 $body=pdHttp($endpoint,array('token'=>'invalid','id'=>$id,'action'=>'preview','profile'=>'purchase25none','reason'=>$reason));pdAssert(strpos($body,'name="nonce"')===false,'Bad CSRF preview accepted');
}finally{pdSql("DELETE FROM ".$p."norway_tax_evidence WHERE entity=".$entity." AND document_type='supplier_deduction' AND fk_document=".$id);curl_close($curl);@unlink($cookie);}
print "PASS reasoned line deduction history, stale/replay/entity/access/integrity guards, native preview/confirm HTTP, CSRF and unchanged ledger\n";
