<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// Synthetic native invoice -> EHF qualification. No network access or production data.
if(PHP_SAPI!=='cli'||getenv('NORWAY_SYNTHETIC_TEST')!=='1'||getenv('DOLI_DB_NAME')!=='norway_test'||getenv('DOLI_COMPANY_NAME')!=='SYNTHETIC NORWAY TEST ONLY'){exit(1);}
require '/var/www/html/master.inc.php';
foreach(array('/societe/class/societe.class.php','/compta/facture/class/facture.class.php','/core/lib/files.lib.php','/core/lib/pdf.lib.php','/core/class/extrafields.class.php','/compta/bank/class/account.class.php','/commande/class/commande.class.php') as $file){require_once DOL_DOCUMENT_ROOT.$file;}
function eqAssert($ok,$message){if(!$ok){throw new RuntimeException($message);}}
function eqSql($sql){global $db;$r=$db->query($sql);eqAssert((bool)$r,$db->lasterror());return $r;}
require DOL_DOCUMENT_ROOT.'/custom/norway/class/NorwayEhfInvoice.php';
$out='/tmp/norway-ehf-probe-results/norway';dol_mkdir($out);
eqAssert($user->fetch('','testadmin')>0,'Synthetic admin');$user->loadRights('',1);$langs->tab_translate['PeppolWarningAutoValidate']='Candidate validation: %s';$p=$db->prefix();$entity=(int)$conf->entity;$run=bin2hex(random_bytes(4));$docs=array();$partyId=0;$bankId=0;$orderId=0;$createdField=false;
$mysoc->forme_juridique_code=173002;$mysoc->name='SYNTHETIC SELLER & GOODS ONLY';$mysoc->country_code='NO';$mysoc->country_id=173;$mysoc->idprof1='123456785';$mysoc->idprof6='123456785';$mysoc->address='Synthetic street 1';$mysoc->zip='0001';$mysoc->town='Synthetic';$mysoc->tva_assuj=1;$mysoc->tva_intra='NO123456785MVA';$conf->currency='NOK';$conf->modules['commande']=true;
try{
 $bank=new Account($db);$bank->ref='E'.substr($run,0,7);$bank->label='SYNTHETIC EHF PROBE '.$run;$bank->bank='Synthetic bank';$bank->country_id=173;$bank->currency_code='NOK';$bank->courant=1;$bank->date_solde=dol_mktime(12,0,0,10,17,2044);$bank->balance=0;$bank->iban='NO9386011117947';$bank->bic='DNBANOKKXXX';eqAssert($bank->create($user)>0,'Synthetic bank');$bankId=(int)$bank->id;$conf->global->FACTURE_RIB_NUMBER=$bankId;
 $party=new Societe($db);$party->name='SYNTHETIC PUBLIC BUYER ONLY '.$run;$party->client=1;$party->code_client='EHFP-'.$run;$party->country_id=173;$party->idprof1='123456785';$party->idprof6='0192:123456785';$party->address='Synthetic street 2';$party->zip='0002';$party->town='Synthetic';$party->tva_assuj=0;eqAssert($party->create($user)>0,'Party');$partyId=(int)$party->id;
 $order=new Commande($db);$order->socid=$partyId;$order->date=dol_mktime(12,0,0,10,1,2044);$order->ref_client='SYNTHETIC BUYER ORDER 123';$order->ref_customer=$order->ref_client;eqAssert($order->create($user)>0,'Native customer order');$orderId=(int)$order->id;
 foreach(array('ordinary','credit','missing-buyer-reference','external-credit','precise-price','discount','order-reference','delivery-period','hours','unknown-unit','negative-line','zero','exempt','outside','domestic-export','export','unknown-code','wrong-bank','draft','wrong-entity') as $scenario){
  $invoice=new Facture($db);$invoice->socid=$partyId;$invoice->type=in_array($scenario,array('credit','external-credit'),true)?2:0;$invoice->date=dol_mktime(12,0,0,10,17,2044);$invoice->date_lim_reglement=dol_mktime(12,0,0,11,17,2044);$invoice->fk_account=$bankId;$invoice->mode_reglement_id=(int)$db->fetch_object(eqSql("SELECT id FROM ".$p."c_paiement WHERE code='VIR' AND entity=$entity"))->id;$invoice->multicurrency_code='NOK';$invoice->multicurrency_tx=1;$invoice->ref_client=in_array($scenario,array('missing-buyer-reference','order-reference'),true)?'':'SYNTHETIC BUYER REF';$invoice->ref_customer=$invoice->ref_client;
  if($scenario==='credit'){$invoice->fk_facture_source=$docs[0]->id;}if($scenario==='external-credit'){$invoice->ref_ext='SYNTHETIC PREVIOUS SYSTEM F000100';}
  eqAssert($invoice->create($user)>0,'Native draft: '.$invoice->error);$docs[]=$invoice;if($scenario==='order-reference'){eqAssert($invoice->add_object_linked('commande',$orderId,$user)>0,'Native order link');}
  $rate=array('zero'=>'0 (5)','exempt'=>'0 (6)','outside'=>'0 (7)','domestic-export'=>'0 (52)','export'=>'0 (52)')[$scenario]??'25 (3)';
  eqAssert($invoice->addline('Synthetic goods & services',$scenario==='precise-price'?0.123456:100,$scenario==='precise-price'?100:1,$rate,0,0,0,$scenario==='discount'?12.5:0,$scenario==='delivery-period'?dol_mktime(12,0,0,10,1,2044):'',$scenario==='delivery-period'?dol_mktime(12,0,0,10,17,2044):'',0)>0,'Native line');
  if(in_array($scenario,array('hours','unknown-unit'),true)){eqSql("UPDATE ".$p."facturedet SET fk_unit=(SELECT rowid FROM ".$p."c_units WHERE code='".($scenario==='hours'?'H':'GAL')."') WHERE fk_facture=".(int)$invoice->id);}
  if($scenario==='negative-line'){eqAssert($invoice->addline('Synthetic negative adjustment',-20,1,'25 (3)',0,0,0,0,'','',0)>0,'Negative line');}

  eqAssert($invoice->validate($user)>0,'Native invoice: '.$invoice->error);$invoice->fetch($invoice->id);
  if($scenario==='wrong-bank'){eqSql('UPDATE '.$p.'facture SET fk_account=999999999 WHERE rowid='.(int)$invoice->id);}
  if($scenario==='draft'){eqSql('UPDATE '.$p.'facture SET fk_statut=0 WHERE rowid='.(int)$invoice->id);}
  if($scenario==='unknown-code'){eqSql("UPDATE ".$p."facturedet SET vat_src_code='UNKNOWN' WHERE fk_facture=".(int)$invoice->id);}
  if($scenario==='wrong-entity'){eqSql('UPDATE '.$p.'facture SET entity=999999999 WHERE rowid='.(int)$invoice->id);}
  $result=array('scenario'=>$scenario,'native_net'=>(string)$invoice->total_ht,'native_tax'=>(string)$invoice->total_tva,'native_gross'=>(string)$invoice->total_ttc,'credit'=>(int)$invoice->type===2,'expected_buyer_reference'=>in_array($scenario,array('missing-buyer-reference','order-reference'),true)?'':'SYNTHETIC BUYER REF','expected_order_reference'=>$scenario==='order-reference'?'SYNTHETIC BUYER ORDER 123':'','expected_recipient'=>$scenario==='export'?'0088:1234567890128':'0192:123456785','expected_original_reference'=>$scenario==='credit'?$docs[0]->ref:($scenario==='external-credit'?$invoice->ref_ext:''));
  if($scenario==='export'){eqSql("UPDATE ".$p."societe SET fk_pays=(SELECT rowid FROM ".$p."c_country WHERE code='SE') WHERE rowid=".$partyId);}
  $blocked=array('unknown-unit'=>'NorwayEhfUnit','domestic-export'=>'NorwayEhfExportCountry','missing-buyer-reference'=>'NorwayEhfBuyerReference','unknown-code'=>'NorwayEhfVatCode','wrong-bank'=>'NorwayEhfBank','draft'=>'NorwayEhfDocumentType','wrong-entity'=>'NorwayEhfAccess');
  $failure='';$xml='';
  try{$generator=new NorwayEhfXml($db);$xml=$generator->generateFromInvoice($invoice->id,$scenario==='export'?'0088:1234567890128':'');}
  catch(RuntimeException $error){$failure=$error->getMessage();}
  if(isset($blocked[$scenario])){eqAssert($failure===$blocked[$scenario],$scenario.': '.$failure);$result['blocked']=$failure;}
  else{
   eqAssert($failure==='' && $xml!=='',$scenario.': '.$failure);
   $document=new DOMDocument();eqAssert($document->loadXML($xml),'XML');$xpath=new DOMXPath($document);
   $xpath->registerNamespace('cac','urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');$xpath->registerNamespace('cbc','urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
   $sign=(int)$invoice->type===2?-1:1;
   foreach(array('TaxExclusiveAmount'=>'total_ht','TaxInclusiveAmount'=>'total_ttc','PayableAmount'=>'total_ttc') as $element=>$field){eqAssert($xpath->evaluate('string(/*/cac:LegalMonetaryTotal/cbc:'.$element.')')===number_format($sign*$invoice->$field,2,'.',''),'Native '.$element);}
   if($scenario==='hours'){eqAssert($xpath->evaluate('string(/*/cac:InvoiceLine/cbc:InvoicedQuantity/@unitCode)')==='HUR','Native hours unit');}
   eqAssert($xpath->evaluate('string(/*/cbc:BuyerReference)')===$result['expected_buyer_reference'],'Real buyer reference');
   if($scenario==='order-reference'){eqAssert($xpath->evaluate('string(/*/cac:OrderReference/cbc:ID)')==='SYNTHETIC BUYER ORDER 123','Native customer purchase order');}
   if($scenario==='delivery-period'){eqAssert($xpath->evaluate('string(/*/cac:InvoiceLine/cac:InvoicePeriod/cbc:StartDate)')==='2044-10-01' && $xpath->evaluate('string(/*/cac:InvoiceLine/cac:InvoicePeriod/cbc:EndDate)')==='2044-10-17','Native delivery period');}
   if((int)$invoice->type===2){eqAssert($xpath->evaluate('string(/*/cac:BillingReference/cac:InvoiceDocumentReference/cbc:ID)')===$result['expected_original_reference'],'Original invoice');}
   eqAssert($xpath->evaluate('string(/*/cac:AccountingSupplierParty/cac:Party/cac:PartyName/cbc:Name)')===$mysoc->name,'Escaped identity');
   file_put_contents($out.'/'.$scenario.'.xml',$xml);$result['generated']=true;
   if(in_array($scenario,array('ordinary','credit'),true)){
    putenv('NORWAY_EHF_MODE=test');putenv('NORWAY_EHF_TEST_API_KEY=synthetic-not-a-real-key');
    $id='12345678-1234-1234-1234-123456789abc';$attemptDir=$conf->facture->dir_output.'/.norway-ehf-test/'.$invoice->id;
    foreach(array('accepted','unknown','rejected','malformed') as $outcome){
     if(is_dir($attemptDir)){dol_delete_dir_recursive($attemptDir);}$requests=0;
     $fake=function($method,$path,$body) use(&$requests,$outcome,$id,$invoice){
      $requests++;
      if($method==='POST'){
       eqAssert($path==='/message' && $body['sender']==='0192:123456785' && $body['recipient']==='0192:123456785','Wire endpoints');
       eqAssert(str_contains(base64_decode($body['fileContent']),(int)$invoice->type===2?'<cbc:CreditNoteTypeCode>381</cbc:CreditNoteTypeCode>':'<cbc:InvoiceTypeCode>380</cbc:InvoiceTypeCode>'),'Exact wire document');
       eqAssert($body['fileName']===((int)$invoice->type===2?'creditnote.xml':'invoice.xml'),'Official client filename');
       return match($outcome){'accepted'=>array('http'=>200,'data'=>array('id'=>$id)),'rejected'=>array('http'=>422,'data'=>'Rejected'), 'malformed'=>array('http'=>200,'data'=>array('id'=>'bad')),default=>array('http'=>0,'data'=>null)};
      }
      return array('http'=>200,'data'=>array('id'=>$id,'sender'=>'0192:123456785','recipient'=>'0192:123456785','direction'=>'OUT','folder'=>'Sent','confirmed'=>false));
     };
     $service=new NorwayEhfTransport($db,$fake);$state=$service->send($invoice->id);
     eqAssert($state['status']===($outcome==='malformed'?'unknown':$outcome),'Transport '.$outcome);
     eqAssert($requests===1 && ($service->state($invoice->id)['status'])===$state['status'],'Persisted attempt');
     if($outcome!=='rejected'){$message='';try{NorwayEhfTransport::protect($invoice);}catch(RuntimeException $e){$message=$e->getMessage();}eqAssert($message==='NorwayEhfIssued','Issued document protection');}
     if($outcome==='accepted'){$native=new Facture($db);$native->fetch($invoice->id);eqAssert($native->setDraft($user)<0,'Native redrafting blocked');$native->fetch($invoice->id);eqAssert((int)$native->statut===1 && $native->set_ref_client('SYNTHETIC FORBIDDEN CHANGE')<0,'Native reference edit blocked');$native->fetch($invoice->id);eqAssert($native->ref_customer==='SYNTHETIC BUYER REF','Reference unchanged');$state=$service->refresh($invoice->id);eqAssert($state['status']==='sent' && $requests===2,'Receipt status');}
     if($outcome!=='rejected'){
      $before=$requests;$message='';try{$service->send($invoice->id);}catch(RuntimeException $e){$message=$e->getMessage();}
      eqAssert($message==='NorwayEhfDuplicate' && $requests===$before,'No duplicate after '.$outcome);
     }
     $html=NorwayEhfInvoice::render($db,$invoice,$user,$langs);eqAssert(str_contains($html,'norway_ehf_send')===($outcome==='rejected'),'Only explicit rejection offers retry');
     $handle=fopen($attemptDir.'/.norway-ehf-test.lock','c');eqAssert(flock($handle,LOCK_EX|LOCK_NB),'Fixture lock');
     $message='';try{$service->send($invoice->id);}catch(RuntimeException $e){$message=$e->getMessage();}finally{flock($handle,LOCK_UN);fclose($handle);}
     eqAssert($message==='NorwayEhfBusy','Concurrent send blocked');
    }
    dol_delete_dir_recursive($attemptDir);putenv('NORWAY_EHF_MODE');putenv('NORWAY_EHF_TEST_API_KEY');
    eqAssert(!NorwayEhfTransport::enabled() && NorwayEhfInvoice::render($db,$invoice,$user,$langs)==='','Disabled by default');
   }

  }
  if($scenario==='export'){eqSql('UPDATE '.$p.'societe SET fk_pays=173 WHERE rowid='.$partyId);}
  file_put_contents($out.'/'.$scenario.'.json',json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n");echo json_encode(array('candidate'=>'norway')+$result)."\n";
 }
}finally{
 foreach($docs as $o){eqSql('UPDATE '.$p.'facture SET fk_facture_source=NULL WHERE rowid='.(int)$o->id);}
 foreach($docs as $o){$id=(int)$o->id;eqSql("DELETE FROM ".$p."element_element WHERE (sourcetype='facture' AND fk_source=$id) OR (targettype='facture' AND fk_target=$id)");eqSql('DELETE e FROM '.$p.'facturedet_extrafields e JOIN '.$p.'facturedet d ON d.rowid=e.fk_object WHERE d.fk_facture='.$id);eqSql('DELETE FROM '.$p.'facturedet WHERE fk_facture='.$id);eqSql('DELETE FROM '.$p.'facture_extrafields WHERE fk_object='.$id);eqSql('DELETE FROM '.$p.'facture WHERE rowid='.$id);$stateDir=$conf->facture->dir_output.'/.norway-ehf-test/'.$id;if(is_dir($stateDir)){dol_delete_dir_recursive($stateDir);}$dir=$conf->facture->dir_output.'/'.dol_sanitizeFileName($o->ref);if(is_dir($dir)){dol_delete_dir_recursive($dir);}}
 if($bankId){eqSql('DELETE FROM '.$p.'bank WHERE fk_account='.$bankId);eqSql('DELETE FROM '.$p.'bank_account WHERE rowid='.$bankId);}
 $stateBase=$conf->facture->dir_output.'/.norway-ehf-test';if(is_dir($stateBase) && count(scandir($stateBase))===2){rmdir($stateBase);}
 if($orderId){eqSql('DELETE FROM '.$p.'commande_extrafields WHERE fk_object='.$orderId);eqSql('DELETE FROM '.$p.'commande WHERE rowid='.$orderId);}
 if($partyId){eqSql('DELETE FROM '.$p.'societe_commerciaux WHERE fk_soc='.$partyId);eqSql('DELETE FROM '.$p.'societe_extrafields WHERE fk_object='.$partyId);eqSql('DELETE FROM '.$p.'societe WHERE rowid='.$partyId);}
}
echo "EHF native amounts, credit references, precision, discounts and blocking cases passed\n";
