<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// Optional, isolated qualification of downloaded candidates. No sending or module activation.
if(PHP_SAPI!=='cli'||getenv('NORWAY_SYNTHETIC_TEST')!=='1'||getenv('DOLI_DB_NAME')!=='norway_test'||getenv('DOLI_COMPANY_NAME')!=='SYNTHETIC NORWAY TEST ONLY'){exit(1);}
require '/var/www/html/master.inc.php';
foreach(array('/societe/class/societe.class.php','/compta/facture/class/facture.class.php','/core/lib/files.lib.php','/core/lib/pdf.lib.php','/core/class/extrafields.class.php','/compta/bank/class/account.class.php') as $file){require_once DOL_DOCUMENT_ROOT.$file;}
function eqAssert($ok,$message){if(!$ok){throw new RuntimeException($message);}}
function eqSql($sql){global $db;$r=$db->query($sql);eqAssert((bool)$r,$db->lasterror());return $r;}
$kind=$argv[1]??'';eqAssert(in_array($kind,array('marcrant1','doomzlight','cedric-marcoux'),true),'Candidate');
$root='/tmp/norway-ehf-candidates/'.$kind;$out='/tmp/norway-ehf-probe-results/'.$kind;dol_mkdir($out);
$conf->file->dol_document_root['ehf-probe']=$root;
if($kind==='cedric-marcoux'){require $root.'/peppolpeppyrus/class/peppol.class.php';}else{require $root.'/peppolnew/class/ublgenerator.class.php';}
eqAssert($user->fetch('','testadmin')>0,'Synthetic admin');$user->loadRights('',1);$langs->tab_translate['PeppolWarningAutoValidate']='Candidate validation: %s';$p=$db->prefix();$entity=(int)$conf->entity;$run=bin2hex(random_bytes(4));$docs=array();$partyId=0;$bankId=0;$createdField=false;$extra=new ExtraFields($db);$extra->fetch_name_optionals_label('societe');
$mysoc->name='SYNTHETIC NORWEGIAN SELLER ONLY';$mysoc->country_code='NO';$mysoc->country_id=173;$mysoc->idprof1='123456785';$mysoc->idprof6='123456785';$mysoc->address='Synthetic street 1';$mysoc->zip='0001';$mysoc->town='Synthetic';$mysoc->tva_assuj=1;$mysoc->tva_intra='NO123456785MVA';$conf->currency='NOK';
$conf->global->PEPPOLNEW_PEPPOL_ID='0192:123456785';$conf->global->PEPPOL_AP_SENDER_ID='0192:123456785';$conf->global->PEPPOL_MODULE_VERSION='2.2.3';$conf->global->MAIN_INFO_IBAN='NO9386011117947';$conf->global->MAIN_INFO_BIC='DNBANOKKXXX';
try{
 if($kind==='cedric-marcoux' && !isset($extra->attributes['societe']['label']['peppol_id'])){eqAssert($extra->addExtraField('peppol_id','Synthetic Peppol identifier','varchar',100,200,'societe',0,0)>0,'Temporary native candidate field');$createdField=true;}
 $bank=new Account($db);$bank->ref='E'.substr($run,0,7);$bank->label='SYNTHETIC EHF PROBE '.$run;$bank->bank='Synthetic bank';$bank->country_id=173;$bank->currency_code='NOK';$bank->courant=1;$bank->date_solde=dol_mktime(12,0,0,10,17,2044);$bank->balance=0;$bank->iban='NO9386011117947';$bank->bic='DNBANOKKXXX';eqAssert($bank->create($user)>0,'Synthetic bank');$bankId=(int)$bank->id;$conf->global->FACTURE_RIB_NUMBER=$bankId;
 $party=new Societe($db);$party->name='SYNTHETIC PUBLIC BUYER ONLY '.$run;$party->client=1;$party->code_client='EHFP-'.$run;$party->country_id=173;$party->idprof1='123456785';$party->idprof6='0192:123456785';$party->address='Synthetic street 2';$party->zip='0002';$party->town='Synthetic';$party->tva_assuj=0;eqAssert($party->create($user)>0,'Party');$partyId=(int)$party->id;if($kind==='cedric-marcoux'){$party->array_options['options_peppol_id']='0192:123456785';eqAssert($party->insertExtraFields()>0,'Native candidate identifier');}
 foreach(array('ordinary','credit','missing-buyer-reference','external-credit','precise-price') as $scenario){
  $invoice=new Facture($db);$invoice->socid=$partyId;$invoice->type=in_array($scenario,array('credit','external-credit'),true)?2:0;$invoice->date=dol_mktime(12,0,0,10,17,2044);$invoice->date_lim_reglement=dol_mktime(12,0,0,11,17,2044);$invoice->fk_account=$bankId;$invoice->mode_reglement_id=(int)$db->fetch_object(eqSql("SELECT id FROM ".$p."c_paiement WHERE code='VIR' AND entity=$entity"))->id;$invoice->multicurrency_code='NOK';$invoice->multicurrency_tx=1;$invoice->ref_client=$scenario==='missing-buyer-reference'?'':'SYNTHETIC BUYER REF';$invoice->ref_customer=$invoice->ref_client;
  if($scenario==='credit'){$invoice->fk_facture_source=$docs[0]->id;}if($scenario==='external-credit'){$invoice->ref_ext='SYNTHETIC PREVIOUS SYSTEM F000100';}
  eqAssert($invoice->create($user)>0,'Native draft: '.$invoice->error);$docs[]=$invoice;
  eqAssert($invoice->addline('Synthetic goods and services',$scenario==='precise-price'?0.123456:100,$scenario==='precise-price'?100:1,'25 (3)',0,0,0,0,'','',0)>0,'Native line');
  eqAssert($invoice->validate($user)>0,'Native invoice: '.$invoice->error);$invoice->fetch($invoice->id);$invoice->fetch_thirdparty();$invoice->thirdparty->array_options['options_peppol_id']='0192:123456785';
  // Use candidate's documented sender/recipient fields. No source invoice is rewritten.
  $result=array('scenario'=>$scenario,'native_net'=>(string)$invoice->total_ht,'native_tax'=>(string)$invoice->total_tva,'native_gross'=>(string)$invoice->total_ttc,'credit'=>$invoice->type===2,'expected_buyer_reference'=>$scenario==='missing-buyer-reference'?'':'SYNTHETIC BUYER REF','expected_original_reference'=>$scenario==='credit'?$docs[0]->ref:($scenario==='external-credit'?$invoice->ref_ext:''));
  try{
   if($kind==='cedric-marcoux'){$path=$conf->facture->dir_output.'/'.dol_sanitizeFileName($invoice->ref);dol_mkdir($path);$pdf=pdf_getInstance();$pdf->AddPage();$pdf->SetFont('helvetica','',10);$pdf->Write(0,'SYNTHETIC CANDIDATE PROBE ONLY');$pdf->Output($path.'/'.$invoice->ref.'.pdf','F');$adapter=new custom\peppolpeppyrus\Peppol($db,$invoice);$adapter->makePDF($path.'/'.$invoice->ref.'.pdf');$file=$path.'/'.$invoice->ref.'_peppol.xml';$xml=is_file($file)?file_get_contents($file):'';$result['messages']=$adapter->errors;}
   else{$generator=new UBLGenerator($db);$xml=$generator->generateFromInvoice($invoice->id);}
   if($xml){file_put_contents($out.'/'.$scenario.'.xml',$xml);$result['generated']=true;}else{$result['generated']=false;}
  }catch(Throwable $e){$result['generated']=false;$result['error']=$e->getMessage();}
  file_put_contents($out.'/'.$scenario.'.json',json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n");echo json_encode(array('candidate'=>$kind)+$result)."\n";
 }
}finally{
 foreach($docs as $o){eqSql('UPDATE '.$p.'facture SET fk_facture_source=NULL WHERE rowid='.(int)$o->id);}
 foreach($docs as $o){$id=(int)$o->id;eqSql('DELETE e FROM '.$p.'facturedet_extrafields e JOIN '.$p.'facturedet d ON d.rowid=e.fk_object WHERE d.fk_facture='.$id);eqSql('DELETE FROM '.$p.'facturedet WHERE fk_facture='.$id);eqSql('DELETE FROM '.$p.'facture_extrafields WHERE fk_object='.$id);eqSql('DELETE FROM '.$p.'facture WHERE rowid='.$id);$dir=$conf->facture->dir_output.'/'.dol_sanitizeFileName($o->ref);if(is_dir($dir)){dol_delete_dir_recursive($dir);}}
 if($createdField){$extra->delete('peppol_id','societe');}if($bankId){eqSql('DELETE FROM '.$p.'bank WHERE fk_account='.$bankId);eqSql('DELETE FROM '.$p.'bank_account WHERE rowid='.$bankId);}
 if($partyId){eqSql('DELETE FROM '.$p.'societe_commerciaux WHERE fk_soc='.$partyId);eqSql('DELETE FROM '.$p.'societe_extrafields WHERE fk_object='.$partyId);eqSql('DELETE FROM '.$p.'societe WHERE rowid='.$partyId);}
}
