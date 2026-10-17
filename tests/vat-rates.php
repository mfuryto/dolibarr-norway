<?php
// SPDX-License-Identifier: GPL-3.0-or-later
if (PHP_SAPI!=='cli' || getenv('NORWAY_SYNTHETIC_TEST')!=='1' || getenv('DOLI_DB_NAME')!=='norway_test') { exit(1); }
require '/var/www/html/master.inc.php';require_once DOL_DOCUMENT_ROOT.'/custom/norway/class/NorwayVatRates.php';
require_once DOL_DOCUMENT_ROOT.'/custom/norway/class/NorwayVatAccounts.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';
function vrAssert($ok,$message) { if (!$ok) { throw new RuntimeException($message); } }
function vrSql($sql) { global $db;$r=$db->query($sql);vrAssert($r!==false,'Synthetic rate query: '.$db->lasterror());return $r; }
vrAssert($user->fetch('','testadmin')>0 && $user->admin,'Admin missing');$user->loadRights('accounting',1);
require_once DOL_DOCUMENT_ROOT.'/custom/norway/core/modules/modNorway.class.php';$module=new modNorway($db);vrAssert($module->init()>0,'Module refresh failed');$conf->setValues($db);
$p=$db->prefix();$original=$conf->entity;$entity=987647;$conf->entity=$entity;
$old=$db->fetch_object(vrSql("SELECT rowid FROM ".$p."accounting_system WHERE pcg_version='".NorwayChartProfile::VERSION."'"));
$before=array();foreach(array('CHARTOFACCOUNTS','ACCOUNTING_VAT_SOLD_ACCOUNT','ACCOUNTING_VAT_BUY_ACCOUNT') as $key){$before[$key]=array(property_exists($conf->global,$key),$conf->global->$key ?? null);unset($conf->global->$key);}
try {
 $chart=NorwayChartProfile::preview($db,$entity);vrAssert(NorwayChartProfile::install($db,$entity,NorwayChartProfile::fingerprint($entity,$chart),$user)===745,'Chart failed');
 foreach(array(0,8,14,25) as $rate){vrSql("INSERT INTO ".$p."c_tva (entity,fk_pays,taux,type_vat,code,active) VALUES (".$entity.",173,".$rate.",0,'',1)");}
 vrSql("UPDATE ".$p."c_tva SET accountancy_code_sell='CUSTOM',use_default=1 WHERE entity=".$entity." AND taux=25");
 vrSql("INSERT INTO ".$p."c_tva (entity,fk_pays,taux,type_vat,code,active) VALUES (".$entity.",1,8,0,'',1)");
 $custom=NorwayVatRates::preview($db,$entity);vrAssert($custom['blocked'],'Existing default/account was silently reclassified');
 try{NorwayVatRates::apply($db,$entity,NorwayDefaultAccounts::fingerprint($custom),$user);throw new LogicException('Custom default changed');}catch(InvalidArgumentException $expected){}
 $retained=$db->fetch_object(vrSql('SELECT accountancy_code_sell,use_default,active FROM '.$p.'c_tva WHERE entity='.$entity.' AND fk_pays=173 AND taux=25'));vrAssert($retained->accountancy_code_sell==='CUSTOM'&&(int)$retained->use_default===1&&(int)$retained->active===1,'Blocked preview changed choices');
 // Clear only this synthetic choice explicitly; production requires native manual review.
 vrSql("UPDATE ".$p."c_tva SET accountancy_code_sell='',use_default=0 WHERE entity=".$entity." AND fk_pays=173 AND taux=25");
 $preview=NorwayVatRates::preview($db,$entity);vrAssert(!$preview['blocked'] && count($preview['changes'])===13,'Initial code scope failed');
 $reader=clone $user;$reader->admin=0;
 try{NorwayVatRates::apply($db,$entity,NorwayDefaultAccounts::fingerprint($preview),$reader);throw new LogicException('Nonadmin accepted');}catch(InvalidArgumentException $expected){}
 $failing=new class($db){private $db;private $writes=0;public function __construct($db){$this->db=$db;}public function __call($name,$args){return $this->db->$name(...$args);}public function query($sql,...$args){if((strpos($sql,'INSERT INTO '.$this->db->prefix().'c_tva ')===0 || strpos($sql,'UPDATE '.$this->db->prefix().'c_tva SET ')===0) && ++$this->writes===3){return false;}return $this->db->query($sql,...$args);}};
 try{NorwayVatRates::apply($failing,$entity,NorwayDefaultAccounts::fingerprint($preview),$user);throw new LogicException('Partial write accepted');}catch(RuntimeException $expected){}
 vrAssert(NorwayVatRates::preview($db,$entity)===$preview,'Atomic rollback failed');
 $db->begin();try{NorwayVatRates::apply($db,$entity,NorwayDefaultAccounts::fingerprint($preview),$user);throw new LogicException('Nested transaction accepted');}catch(RuntimeException $expected){}finally{$db->rollback();}
 vrAssert(NorwayVatRates::apply($db,$entity,NorwayDefaultAccounts::fingerprint($preview),$user)===13,'Expected ten coded rows and three retained/deactivated rows');
 $after=NorwayVatRates::preview($db,$entity);vrAssert(!$after['blocked'] && count($after['rates'])===14,'Rows deleted or duplicated');
 foreach($after['rates'] as $r){$expectedActive=$r['code']!=='' || (float)$r['taux']===0.0;vrAssert((int)$r['active']===(int)$expectedActive,'Incorrect active coded/legacy row');}
 $accountPreview=NorwayVatAccounts::preview($db,$entity);vrAssert(NorwayVatAccounts::apply($db,$entity,NorwayDefaultAccounts::fingerprint($accountPreview),$user)===6,'Six directional VAT accounts not filled');
 foreach(NorwayVatRates::codes() as $spec){$row=$db->fetch_object(vrSql("SELECT * FROM ".$p."c_tva WHERE entity=".$entity." AND code='".$spec['code']."'"));$map=array(25=>array('2700','2710'),15=>array('2701','2711'),12=>array('2703','2713'));
  vrAssert((int)$row->type_vat===$spec['type']&&(float)$row->taux===(float)$spec['rate'],'Wrong directional code/rate');
  vrAssert($row->{$spec['type']===1?'accountancy_code_sell':'accountancy_code_buy'}===$map[$spec['rate']][$spec['type']-1],'Wrong directional VAT account');
 }
 $after=NorwayVatRates::preview($db,$entity);
 $foreign=$db->fetch_object(vrSql('SELECT active FROM '.$p.'c_tva WHERE entity='.$entity.' AND fk_pays=1'));vrAssert((int)$foreign->active===1,'Foreign rate changed');
 vrAssert(NorwayVatRates::apply($db,$entity,NorwayDefaultAccounts::fingerprint($after),$user)===0,'Repeat failed');
 try{NorwayVatRates::apply($db,$entity,NorwayDefaultAccounts::fingerprint($preview),$user);throw new LogicException('Stale preview accepted');}catch(InvalidArgumentException $expected){}
 vrSql("UPDATE ".$p."c_tva SET type_vat=2 WHERE entity=".$entity." AND code='31'");vrAssert(NorwayVatRates::preview($db,$entity)['blocked'],'Wrong code direction accepted');vrSql("UPDATE ".$p."c_tva SET type_vat=1 WHERE entity=".$entity." AND code='31'");
 vrSql("INSERT INTO ".$p."c_tva(entity,fk_pays,code,type_vat,taux,active) VALUES (".$entity.",173,'3',1,24,1)");$duplicate=(int)$db->last_insert_id($p.'c_tva');vrAssert(NorwayVatRates::preview($db,$entity)['blocked'],'Duplicate standard code accepted');vrSql('DELETE FROM '.$p.'c_tva WHERE rowid='.$duplicate);
 vrSql('UPDATE '.$p.'c_tva SET active=0 WHERE entity='.$entity.' AND taux=15');vrAssert(NorwayVatRates::preview($db,$entity)['blocked'],'Inactive current rate overwritten');
 vrSql('UPDATE '.$p.'c_tva SET active=1 WHERE entity='.$entity.' AND fk_pays=173 AND taux IN (8,15)');
 vrSql("UPDATE ".$p."c_tva SET code='SPECIAL' WHERE entity=".$entity." AND fk_pays=173 AND taux=8");vrAssert(NorwayVatRates::preview($db,$entity)['blocked'],'Special historical rate accepted');
 vrSql("UPDATE ".$p."c_tva SET code='',use_default=1 WHERE entity=".$entity." AND fk_pays=173 AND taux=8");vrAssert(NorwayVatRates::preview($db,$entity)['blocked'],'Historical default overwritten');
 vrSql('UPDATE '.$p.'c_tva SET use_default=0 WHERE entity='.$entity.' AND fk_pays=173 AND taux=8');
 vrSql("INSERT INTO ".$p."product (ref,label,entity,tva_tx) VALUES ('SYN-OLD-RATE','Synthetic',".$entity.",8)");vrAssert(NorwayVatRates::preview($db,$entity)['blocked'],'Product use ignored');vrSql('DELETE FROM '.$p.'product WHERE entity='.$entity);
 vrSql("INSERT INTO ".$p."societe (nom,entity,client,status) VALUES ('Synthetic rate history',".$entity.",1,1)");$party=(int)$db->last_insert_id($p.'societe');
 // Render the actual core widgets: core default lookup chooses code 1 for both directions.
 require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
 $partyObject=new Societe($db);$partyObject->country_code='NO';$partyObject->tva_assuj=1;
 $oldVat=$mysoc->tva_assuj;$oldCountryCode=$mysoc->country_code;$oldCountryId=$mysoc->country_id;$mysoc->tva_assuj=1;$mysoc->country_code='NO';$mysoc->country_id=173;
 try{foreach(array(1=>'3',2=>'1') as $direction=>$expected){$form=new Form($db);$seller=$direction===1?$mysoc:$partyObject;$buyer=$direction===1?$partyObject:$mysoc;
  $html=$form->load_tva('synthetic_vat',-1,$seller,$buyer,0,0,'',false,1,$direction);
  $dom=new DOMDocument();@$dom->loadHTML($html);$nodes=(new DOMXPath($dom))->query('//select[@name="synthetic_vat"]/option[@selected]');
  vrAssert($nodes->length===1 && preg_match('/^25(?:\.0+)? \('.$expected.'\)$/D',$nodes->item(0)->getAttribute('value'))===1,'Core widget chose wrong directional default');
  if($direction===1){foreach(array('5','6','7') as $zeroCode){$zeroHtml=$form->load_tva('zero_vat','0 ('.$zeroCode.')',$seller,$buyer,0,0,'',false,1,1);$zdom=new DOMDocument();@$zdom->loadHTML($zeroHtml);$z=(new DOMXPath($zdom))->query('//select[@name="zero_vat"]/option[@selected]');vrAssert($z->length===1 && preg_match('/^0(?:\.0+)? \('.$zeroCode.'\)$/D',$z->item(0)->getAttribute('value'))===1,'Explicit native zero selection lost');}}
  if($direction===2){foreach(array(false,true) as $optionsOnly){$html=$form->load_tva('zero_purchase','0 (NO0)',$seller,$buyer,0,0,'',$optionsOnly,1,2);$dom=new DOMDocument();@$dom->loadHTML($optionsOnly?'<select>'.$html.'</select>':$html);$xp=new DOMXPath($dom);$sel=$xp->query('//option[@selected]');vrAssert($sel->length===1 && preg_match('/^0(?:\.0+)? \(NO0\)$/D',$sel->item(0)->getAttribute('value'))===1,'Explicit NO0 native selection lost');}}
  $explicit=$form->load_tva('explicit_vat','0',$seller,$buyer,0,0,'',false,1,$direction);$dom=new DOMDocument();@$dom->loadHTML($explicit);$nodes=(new DOMXPath($dom))->query('//select[@name="explicit_vat"]/option[@selected]');vrAssert($nodes->length===1&&(float)$nodes->item(0)->getAttribute('value')===0.0,'Explicit zero rate changed');
 }
 // New product cards use the same core widget without a direction argument.
 $oldScript=$_SERVER['SCRIPT_FILENAME'] ?? null;$oldAction=$_GET['action'] ?? null;$_SERVER['SCRIPT_FILENAME']=DOL_DOCUMENT_ROOT.'/product/card.php';$_GET['action']='create';
 try{$form=new Form($db);$native=(string)get_default_tva($mysoc,$mysoc);$html=$form->load_tva('tva_tx',$native,$mysoc,$mysoc,0,0,'',false,1,0);
  $dom=new DOMDocument();@$dom->loadHTML($html);$xpath=new DOMXPath($dom);$nodes=$xpath->query('//select[@name="tva_tx"]/option[@selected]');vrAssert($nodes->length===1&&preg_match('/^25(?:\.0+)? \(3\)$/D',$nodes->item(0)->getAttribute('value'))===1,'New product default is incoming VAT');
  foreach($xpath->query('//option') as $option){vrAssert(!preg_match('/\((1|11|13)\)/',$option->getAttribute('value')),'Incoming code appears in sales product widget');}
  $hook=new ActionsNorway($db);$dummy=null;$action='';vrAssert($hook->load_tva(array('htmlname'=>'tva_tx','selectedrate'=>'25 (SPECIAL)','type_vat'=>0,'seller'=>$mysoc,'buyer'=>$mysoc),$dummy,$action,$hookmanager)===0,'Special product code overridden');
 }finally{if($oldScript===null){unset($_SERVER['SCRIPT_FILENAME']);}else{$_SERVER['SCRIPT_FILENAME']=$oldScript;}if($oldAction===null){unset($_GET['action']);}else{$_GET['action']=$oldAction;}}
 require_once DOL_DOCUMENT_ROOT.'/custom/norway/class/actions_norway.class.php';$hook=new ActionsNorway($db);$dummy=null;$action='';
 $foreignParty=clone $partyObject;$foreignParty->country_code='SE';
 foreach(array(array('seller'=>$mysoc,'buyer'=>$foreignParty,'selectedrate'=>-1,'type_vat'=>1),array('seller'=>$mysoc,'buyer'=>$partyObject,'selectedrate'=>'12 (33)','type_vat'=>1),array('seller'=>$mysoc,'buyer'=>$partyObject,'selectedrate'=>-1,'type_vat'=>1,'is_npr'=>1)) as $parameters){vrAssert($hook->load_tva($parameters,$dummy,$action,$hookmanager)===0&&$hook->resprints==='','Explicit/foreign/NPR choice overridden');}
 }finally{$mysoc->tva_assuj=$oldVat;$mysoc->country_code=$oldCountryCode;$mysoc->country_id=$oldCountryId;}
 // Exercise native document APIs, not direct synthetic line inserts.
 foreach(array(1,2) as $direction){$invoice=$direction===1?new Facture($db):new FactureFournisseur($db);$invoice->socid=$party;$invoice->date=dol_mktime(12,0,0,1,10,2026);$invoice->multicurrency_code='NOK';$invoice->multicurrency_tx=1;$invoice->ref_supplier='SYN-CODE-ONLY';
  vrAssert($invoice->create($user)>0,'Native coded document create: '.$invoice->error);
  foreach(array_merge(NorwayVatRates::codes(),NorwayVatRates::zeroCodes()) as $spec){if($spec['type']!==$direction){continue;}$vat=$spec['rate'].' ('.$spec['code'].')';
   $line=$direction===1?$invoice->addline('Synthetic coded sale',100,1,$vat):$invoice->addline('Synthetic coded purchase',100,$vat,0,0,1);
   vrAssert($line>0,'Native coded line: '.$invoice->error);
  }
  vrAssert($invoice->fetch($invoice->id)>0,'Native coded document fetch');$expected=$direction===1?array('3','31','33','5','6','7'):array('1','11','13','NO0');
  vrAssert(array_map(static fn($line)=>(string)$line->vat_src_code,$invoice->lines)===$expected,'Native document did not preserve directional tax codes');
  if($direction===2){$last=end($invoice->lines);vrAssert($invoice->updateline($last->id,'Synthetic purchase edit',125,'0 (NO0)')>0,'Native purchase edit failed');vrAssert($invoice->fetch($invoice->id)>0 && end($invoice->lines)->vat_src_code==='NO0','Native purchase edit lost classification');}

 }
 // Append-only upgrade is safe even with documents: existing choices and their references stay unchanged.
 vrSql("UPDATE ".$p."c_tva SET active=0 WHERE entity=".$entity." AND code='NO0'");vrAssert(NorwayVatRates::ensureZeroCodes($db,$entity)===0 && !isset(NorwayVatRates::zeroChoices($db,$entity)['NO0']),'Inactive purchase zero reactivated');vrSql("UPDATE ".$p."c_tva SET active=1 WHERE entity=".$entity." AND code='NO0'");
 $zeroBefore=NorwayVatRates::zeroChoices($db,$entity);vrAssert(count($zeroBefore)===4,'Zero classifications missing');
 vrAssert(NorwayVatRates::ensureZeroCodes($db,$entity)===0,'Repeat zero seed duplicated rows');
 vrSql("UPDATE ".$p."c_tva SET active=0 WHERE entity=".$entity." AND code='7'");vrAssert(NorwayVatRates::ensureZeroCodes($db,$entity)===0 && !isset(NorwayVatRates::zeroChoices($db,$entity)['7']),'Inactive zero code reactivated');vrSql("UPDATE ".$p."c_tva SET active=1 WHERE entity=".$entity." AND code='7'");
 vrSql("UPDATE ".$p."c_tva SET type_vat=2 WHERE entity=".$entity." AND code='6'");vrAssert(NorwayVatRates::ensureZeroCodes($db,$entity)===0 && !isset(NorwayVatRates::zeroChoices($db,$entity)['6']),'Conflicting zero direction overwritten');vrSql("UPDATE ".$p."c_tva SET type_vat=1 WHERE entity=".$entity." AND code='6'");
 vrSql("UPDATE ".$p."c_tva SET code='6 ' WHERE entity=".$entity." AND code='6'");vrAssert(NorwayVatRates::ensureZeroCodes($db,$entity)===0 && !isset(NorwayVatRates::zeroChoices($db,$entity)['6']),'Ambiguous stored code silently replaced');vrSql("UPDATE ".$p."c_tva SET code='6' WHERE entity=".$entity." AND code='6 '");
 vrSql("DELETE FROM ".$p."c_tva WHERE entity=".$entity." AND code IN ('NO0','5','6','7')");
 $failingZero=new class($db){private $db;private $writes=0;public function __construct($db){$this->db=$db;}public function __call($name,$args){return $this->db->$name(...$args);}public function query($sql,...$args){if(strpos($sql,'INSERT INTO '.$this->db->prefix().'c_tva ')===0 && ++$this->writes===2){return false;}return $this->db->query($sql,...$args);}};
 try{NorwayVatRates::ensureZeroCodes($failingZero,$entity);throw new LogicException('Partial zero seed accepted');}catch(RuntimeException $expected){}
 vrAssert(count(NorwayVatRates::zeroChoices($db,$entity))===0,'Partial zero seed not rolled back');
 vrAssert(NorwayVatRates::ensureZeroCodes($db,$entity)===4 && NorwayVatRates::ensureZeroCodes($db,$entity)===0,'Missing zero rows not provisioned with existing documents');
 vrAssert(NorwayVatRates::zeroChoices($db,$entity)===$zeroBefore,'Zero choice semantics changed');

 $blocked=NorwayVatRates::preview($db,$entity);vrAssert($blocked['blocked'],'Draft document use ignored');
 try{NorwayVatRates::apply($db,$entity,NorwayDefaultAccounts::fingerprint($blocked),$user);throw new LogicException('Used setup accepted');}catch(InvalidArgumentException $expected){}
 try{NorwayVatRates::preview($db,$entity+1);throw new LogicException('Foreign entity accepted');}catch(InvalidArgumentException $expected){}
} finally {
 $db->rollback();vrSql('DELETE FROM '.$p.'facturedet WHERE fk_facture IN (SELECT rowid FROM '.$p.'facture WHERE entity='.$entity.')');vrSql('DELETE FROM '.$p.'facture_fourn_det WHERE fk_facture_fourn IN (SELECT rowid FROM '.$p.'facture_fourn WHERE entity='.$entity.')');foreach(array('c_tva','facture_fourn','facture','product','societe','accounting_account','const') as $table){vrSql('DELETE FROM '.$p.$table.' WHERE entity='.$entity);}
 if(!$old){vrSql("DELETE FROM ".$p."accounting_system WHERE pcg_version='".NorwayChartProfile::VERSION."'");}
 foreach($before as $key=>$value){if($value[0]){$conf->global->$key=$value[1];}else{unset($conf->global->$key);}}$conf->entity=$original;
}
print "PASS native ordinary/zero VAT codes, append-only zero upgrade and conflicts, directional accounts and actual invoice/purchase line persistence; retained legacy/zero/foreign rows, custom/default/conflict/history guards, rollback, replay and permissions\n";
