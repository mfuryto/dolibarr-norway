<?php
// SPDX-License-Identifier: GPL-3.0-or-later
if(PHP_SAPI!=='cli'||getenv('NORWAY_SYNTHETIC_TEST')!=='1'||getenv('DOLI_DB_NAME')!=='norway_test'){exit(1);}
require '/var/www/html/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/comm/propal/class/propal.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/norway/core/modules/modNorway.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/norway/class/NorwayCreditReference.php';
function insist($ok,$message){if(!$ok){throw new RuntimeException($message);}}
require __DIR__.'/pdf-assertions.php';
function dfSql($sql){global $db;$r=$db->query($sql);insist($r!==false,'Synthetic document query: '.$db->lasterror());return $r;}
$run=bin2hex(random_bytes(4));$p=$db->prefix();$entity=(int)$conf->entity;$beforeSoc=clone $mysoc;$beforeGlobal=clone $conf->global;$ids=array();$files=array();$payments=array();$createdRate=0;
insist($user->fetch('','testadmin')>0 && $user->admin,'Synthetic admin');$user->loadRights('',1);
$module=new modNorway($db);insist($module->init()>0,'Module hooks');$conf->setValues($db);
$customer=new Societe($db);$customer->name='SYNTHETIC DOCUMENT CUSTOMER';$customer->client=1;$customer->code_client='SYNDOC-'.$run;$customer->country_id=173;$customer->address='SYNTHETIC CUSTOMER ADDRESS';$customer->zip='0000';$customer->town='SYNTHETIC TOWN';
insist($customer->create($user)>0,'Synthetic customer: '.$customer->error);
$mysoc->name='SYNTHETIC DOCUMENT SELLER';$mysoc->country_code='NO';$mysoc->country_id=173;$mysoc->idprof1='123456785';$mysoc->address='SYNTHETIC SELLER ADDRESS';$mysoc->zip='0000';$mysoc->town='SYNTHETIC SELLER TOWN';$mysoc->tva_assuj=1;
$cookie=tempnam(sys_get_temp_dir(),'norway-document-');$curl=curl_init();curl_setopt_array($curl,array(CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_TIMEOUT=>30));
function dfHttp($path,$post=null){global $curl;curl_setopt($curl,CURLOPT_URL,'http://localhost'.$path);curl_setopt($curl,CURLOPT_POST,$post!==null);if($post!==null){curl_setopt($curl,CURLOPT_POSTFIELDS,http_build_query($post));}$body=curl_exec($curl);insist($body!==false&&curl_getinfo($curl,CURLINFO_HTTP_CODE)<500,'Native document HTTP');return $body;}
function dfToken($body){insist(preg_match('/name="token"[^>]*value="([^"]+)"/',$body,$m)===1,'Native token');return html_entity_decode($m[1],ENT_QUOTES,'UTF-8');}
try{
    $tax=$db->fetch_object(dfSql("SELECT rowid FROM ".$p."c_tva WHERE entity=".$entity." AND fk_pays=173 AND code='3' AND taux=25 AND type_vat=1 AND active=1"));
    if(!$tax){dfSql("INSERT INTO ".$p."c_tva (entity,fk_pays,taux,type_vat,code,active,accountancy_code_sell) VALUES (".$entity.",173,25,1,'3',1,'2700')");$createdRate=(int)$db->last_insert_id($p.'c_tva');}
    $ledgerAccount=$db->fetch_object(dfSql("SELECT a.rowid FROM ".$p."accounting_account a JOIN ".$p."accounting_system s ON s.pcg_version=a.fk_pcg_version WHERE a.entity=".$entity." AND s.rowid=".(int)getDolGlobalInt('CHARTOFACCOUNTS')." AND a.account_number='3000' AND a.active=1"));insist((bool)$ledgerAccount,'Native revenue account');
    $body=dfHttp('/index.php');dfHttp('/index.php',array('token'=>dfToken($body),'actionlogin'=>'login','loginfunction'=>'loginfunction','username'=>'testadmin','password'=>getenv('DOLI_ADMIN_PASSWORD')));
    $draft=new Facture($db);$draft->socid=$customer->id;$draft->type=2;$draft->date=dol_mktime(12,0,0,10,5,2026);$draft->multicurrency_code='NOK';$draft->multicurrency_tx=1;
    insist($draft->create($user)>0,'Standalone credit draft');$ids[]=$draft->id;
    insist($draft->addline('SYNTHETIC CREDITED GOODS',100,1,'25 (3)',0,0,0,0,'','',(int)$ledgerAccount->rowid)>0,'Credit line');
    $path='/compta/facture/card.php?facid='.$draft->id;
    foreach(array('nb_NO','en_US') as $language){$body=dfHttp($path.'&lang='.$language);insist(strpos($body,'name="norway_original_invoice_ref"')!==false,'Draft original reference field');}
    $body=dfHttp($path);dfHttp($path,array('token'=>dfToken($body),'action'=>'norway_credit_reference','norway_original_invoice_ref'=>'OLD-2025-0042'));
    insist($draft->fetch($draft->id)>0&&$draft->ref_ext==='OLD-2025-0042','Core reference not saved');
    dfHttp($path,array('token'=>'invalid','action'=>'norway_credit_reference','norway_original_invoice_ref'=>'FORBIDDEN'));
    insist($draft->fetch($draft->id)>0&&$draft->ref_ext==='OLD-2025-0042','Invalid token changed reference');
    $reader=clone $user;$reader->admin=0;$reader->rights=(object)array();
    try{NorwayCreditReference::save($db,$entity,$draft->id,'FORBIDDEN',$reader);throw new LogicException('Reader saved reference');}catch(RuntimeException $expected){}
    try{NorwayCreditReference::save($db,$entity+1,$draft->id,'FORBIDDEN',$user);throw new LogicException('Foreign entity saved reference');}catch(RuntimeException $expected){}
    $draft->cond_reglement_id=1;$draft->date_lim_reglement=$draft->date;
    insist($draft->validate($user,'SYNDOC-CREDIT-'.$run)>0,'External credit validation: '.$draft->error);
    try{NorwayCreditReference::save($db,$entity,$draft->id,'FORBIDDEN',$user);throw new LogicException('Issued reference changed');}catch(RuntimeException $expected){}
    $body=dfHttp($path);insist(strpos($body,'name="norway_original_invoice_ref"')===false&&strpos($body,'OLD-2025-0042')!==false,'Issued reference not read-only');
    // Native fields carry delivery period, delivery place and the buyer's reference.
    $sale=new Facture($db);$sale->socid=$customer->id;$sale->date=dol_mktime(12,0,0,10,5,2026);$sale->multicurrency_code='NOK';$sale->multicurrency_tx=1;$sale->cond_reglement_id=1;$sale->ref_customer='SYNTHETIC BUYER REFERENCE';$sale->note_public='SYNTHETIC DELIVERY SITE';
    insist($sale->create($user)>0,'Native sale draft');$ids[]=$sale->id;
    $start=dol_mktime(12,0,0,10,1,2026);$end=dol_mktime(12,0,0,10,3,2026);
    insist($sale->addline('SYNTHETIC GOODS',100,2,'25 (3)',0,0,0,0,$start,$start,(int)$ledgerAccount->rowid)>0,'Goods line');
    insist($sale->addline('SYNTHETIC SERVICE',100,1,'25 (3)',0,0,0,0,$start,$end,(int)$ledgerAccount->rowid,0,0,'HT',0,1)>0,'Service period line');
    insist($sale->validate($user,'SYNDOC-SALE-'.$run)>0&&$sale->fetch($sale->id)>0,'Native sale validation');
    insist((float)$sale->total_ht===300.0&&(float)$sale->total_tva===75.0&&(float)$sale->total_ttc===375.0,'Native document totals');
    $proposal=new Propal($db);$proposal->socid=$customer->id;$proposal->date=$sale->date;$proposal->duree_validite=30;$proposal->multicurrency_code='NOK';$proposal->multicurrency_tx=1;
    insist($proposal->create($user)>0,'Native proposal create');
    insist($proposal->addline('SYNTHETIC GOODS AND SERVICES',300,1,25)>0&&$proposal->valid($user)>0&&$proposal->fetch($proposal->id)>0,'Native proposal');
    foreach(array(173001=>false,173002=>true,173003=>true) as $form=>$register){
        $mysoc->forme_juridique_code=$form;
        foreach(array('nb_NO','en_US') as $language){
            $lang=new Translate('',$conf);$lang->setDefaultLang($language);$lang->loadLangs(array('main','bills','norway@norway'));
            foreach(array($sale,$draft) as $invoice){
                insist($invoice->generateDocument('sponge',$lang)>0,'Native PDF');
                $file=$conf->facture->dir_output.'/'.dol_sanitizeFileName($invoice->ref).'/'.dol_sanitizeFileName($invoice->ref).'.pdf';$files[]=$file;
                assertPdfLanguage($file,'123 456 785 MVA');assertPdfLanguage($file,'Foretaksregisteret',$register);
                assertPdfLanguage($file,$invoice->ref);assertPdfLanguage($file,$lang->transnoentities('CurrencyNOK'));
                assertPdfLanguage($file,'SYNTHETIC CUSTOMER ADDRESS');assertPdfLanguage($file,'SYNTHETIC SELLER ADDRESS');
                assertPdfLanguage($file,price($invoice->total_ttc,0,$lang));
                assertPdfLanguage($file,array(173001=>'Enkeltpersonforetak',173002=>'Aksjeselskap',173003=>'Allmennaksjeselskap')[$form]);
                if($invoice===$sale){assertPdfLanguage($file,'SYNTHETIC BUYER REFERENCE');assertPdfLanguage($file,'SYNTHETIC DELIVERY SITE');assertPdfLanguage($file,'SYNTHETIC GOODS');assertPdfLanguage($file,'SYNTHETIC SERVICE');assertPdfLanguage($file,dol_print_date($start,'day',false,$lang));assertPdfLanguage($file,dol_print_date($end,'day',false,$lang));}
                else{assertPdfLanguage($file,'OLD-2025-0042');assertPdfLanguage($file,$lang->transnoentities('NorwayCreditReferencePdf'));}
                // PDF generation may populate transient PDF paths; source fields/ledger remain native.
                $stored=$db->fetch_object(dfSql('SELECT ref_ext,total_ht,total_tva,total_ttc FROM '.$p.'facture WHERE rowid='.(int)$invoice->id));
                insist((float)$stored->total_ttc===(float)$invoice->total_ttc,'PDF changed stored total');
                if($invoice===$draft){insist($stored->ref_ext==='OLD-2025-0042','PDF changed original reference');}
            }
            insist($proposal->generateDocument('azur',$lang)>0,'Native offer PDF');
            $file=$conf->propal->dir_output.'/'.dol_sanitizeFileName($proposal->ref).'/'.dol_sanitizeFileName($proposal->ref).'.pdf';$files[]=$file;
            assertPdfLanguage($file,'123 456 785 MVA');assertPdfLanguage($file,'Foretaksregisteret',$register);assertPdfLanguage($file,$lang->transnoentities('CurrencyNOK'));assertPdfLanguage($file,'SYNTHETIC GOODS AND SERVICES');assertPdfLanguage($file,price(375,0,$lang));
        }
    }
    // The migration credit uses core sales posting and refund allocation as ordinary credit notes do.
    dfSql("INSERT INTO ".$p."accounting_journal (entity,code,label,nature,active) VALUES (".$entity.",'SYNDOC".$run."','Synthetic document sales',2,1)");$journal=(int)$db->last_insert_id($p.'accounting_journal');
    $params=array('id_journal'=>$journal,'date_startday'=>1,'date_startmonth'=>10,'date_startyear'=>2026,'date_endday'=>31,'date_endmonth'=>10,'date_endyear'=>2026,'in_bookkeeping'=>'notyet');$journalPath='/accountancy/journal/sellsjournal.php?'.http_build_query($params);
    $body=dfHttp($journalPath);dfHttp($journalPath,$params+array('action'=>'writebookkeeping','token'=>dfToken($body)));
    foreach(array($sale,$draft) as $invoice){
        $r=dfSql("SELECT numero_compte,SUM(debit-credit) AS amount FROM ".$p."accounting_bookkeeping WHERE entity=".$entity." AND doc_type='customer_invoice' AND fk_doc=".(int)$invoice->id." GROUP BY numero_compte");$actual=array();while($row=$db->fetch_object($r)){$actual[$row->numero_compte]=(float)$row->amount;}
        $expected=$invoice===$sale?array('1500'=>375.0,'3000'=>-300.0,'2700'=>-75.0):array('1500'=>-125.0,'3000'=>100.0,'2700'=>25.0);ksort($expected);ksort($actual);insist($actual===$expected,'Native credit/sale posting amounts');
    }
    require_once DOL_DOCUMENT_ROOT.'/custom/norway/class/NorwayVatReport.php';
    $report=NorwayVatReport::build($db,$entity,'2026-10-01','2026-10-31',$user);$found=false;
    foreach($report['entries'] as $entry){if($entry['source']==='customer_invoice'&&(int)$entry['id']===(int)$draft->id){insist($entry['code']==='3'&&$entry['base']===-10000&&$entry['tax']===-2500,'Migration credit VAT signs');$found=true;}}
    insist($found,'Migration credit absent from VAT extract');
    require_once DOL_DOCUMENT_ROOT.'/compta/paiement/class/paiement.class.php';
    $transfer=$db->fetch_object(dfSql("SELECT id FROM ".$p."c_paiement WHERE entity=".$entity." AND code='VIR' AND active=1"));insist((bool)$transfer,'Native transfer');
    $refund=new Paiement($db);$refund->datepaye=$draft->date;$refund->paiementid=(int)$transfer->id;$refund->amounts=array($draft->id=>-125);insist($refund->create($user,1)>0,'Native credit refund: '.$refund->error);$payments[]=(int)$refund->id;
    insist($draft->fetch($draft->id)>0&&(float)$draft->getSommePaiement()===-125.0,'Native refund allocation');
    $create=dfHttp('/compta/facture/card.php?action=create&socid='.$customer->id.'&type=2');
    insist(strpos($create,'id="radio_creditnote"')!==false&&strpos($create,'id="norway-original-reference"')!==false,'Native create migration field');
    $post=array('token'=>dfToken($create),'action'=>'add','socid'=>$customer->id,'type'=>2,'fac_avoir'=>-1,'remonth'=>10,'reday'=>5,'reyear'=>2026,'multicurrency_code'=>'NOK','originmulticurrency_tx'=>1,'norway_original_invoice_ref'=>'OLD-CREATED-2025-0043');
    dfHttp('/compta/facture/card.php',$post);
    $created=$db->fetch_object(dfSql("SELECT rowid,ref_ext FROM ".$p."facture WHERE fk_soc=".(int)$customer->id." AND ref_ext='OLD-CREATED-2025-0043'"));
    insist((bool)$created,'Native create form did not retain original reference');$ids[]=(int)$created->rowid;
    $create=dfHttp('/compta/facture/card.php?action=create&socid='.$customer->id.'&type=2');$post['token']=dfToken($create);$post['norway_original_invoice_ref']='<b>INVALID</b>';
    $before=(int)$db->fetch_object(dfSql('SELECT COUNT(*) n FROM '.$p.'facture WHERE fk_soc='.(int)$customer->id))->n;dfHttp('/compta/facture/card.php',$post);
    insist((int)$db->fetch_object(dfSql('SELECT COUNT(*) n FROM '.$p.'facture WHERE fk_soc='.(int)$customer->id))->n===$before,'Invalid reference left a partial invoice');

}finally{
    $db->rollback();
    if(isset($proposal)&&!empty($proposal->id)){dfSql('DELETE FROM '.$p.'propaldet WHERE fk_propal='.(int)$proposal->id);dfSql('DELETE FROM '.$p.'propal_extrafields WHERE fk_object='.(int)$proposal->id);dfSql('DELETE FROM '.$p.'propal WHERE rowid='.(int)$proposal->id);}
    foreach($payments as $id){dfSql('DELETE FROM '.$p.'paiement_facture WHERE fk_paiement='.$id);dfSql('DELETE FROM '.$p.'paiement WHERE rowid='.$id);}
    if(isset($journal)){dfSql('DELETE FROM '.$p.'accounting_journal WHERE rowid='.$journal);}
    if($createdRate){dfSql('DELETE FROM '.$p.'c_tva WHERE rowid='.$createdRate);}
    foreach($ids as $id){dfSql("DELETE FROM ".$p."accounting_bookkeeping WHERE entity=".$entity." AND doc_type='customer_invoice' AND fk_doc=".(int)$id);dfSql('DELETE FROM '.$p.'facturedet WHERE fk_facture='.(int)$id);dfSql('DELETE FROM '.$p.'facture_extrafields WHERE fk_object='.(int)$id);dfSql('DELETE FROM '.$p.'facture WHERE rowid='.(int)$id);}
    dfSql('DELETE FROM '.$p.'societe_extrafields WHERE fk_object='.(int)$customer->id);dfSql('DELETE FROM '.$p.'societe WHERE rowid='.(int)$customer->id);
    foreach(array_unique($files) as $file){if(is_file($file)){unlink($file);}}
    curl_close($curl);unlink($cookie);$mysoc=$beforeSoc;$conf->global=$beforeGlobal;
}
print "PASS native draft/issued migration credit reference, rights/entity/CSRF, bilingual ENK/AS/ASA PDFs, delivery periods/place, buyer reference, native posting/VAT signs and refund allocation\n";
