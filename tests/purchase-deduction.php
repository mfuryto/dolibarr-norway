<?php
// SPDX-License-Identifier: GPL-3.0-or-later
if(PHP_SAPI!=='cli'||getenv('NORWAY_SYNTHETIC_TEST')!=='1'||getenv('DOLI_DB_NAME')!=='norway_test'){exit(1);}
require '/var/www/html/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/paiementfourn.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/accountancy/class/accountingaccount.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/norway/class/NorwayVatReport.php';
require_once DOL_DOCUMENT_ROOT.'/custom/norway/core/modules/modNorway.class.php';
function pdAssert($ok,$message){if(!$ok){throw new RuntimeException($message);}}
function pdSql($sql){global $db;$r=$db->query($sql);pdAssert((bool)$r,'Synthetic deduction SQL: '.$db->lasterror());return $r;}
function pdRows($sql){global $db;$rows=array();$r=pdSql($sql);while($o=$db->fetch_object($r)){$rows[]=$o;}return $rows;}
function pdRestore($table,$rows){global $db;foreach($rows as $row){$row=(array)$row;$v=array_map(fn($v)=>$v===null?'NULL':"'".$db->escape((string)$v)."'",array_values($row));pdSql('INSERT INTO '.$table.' ('.implode(',',array_keys($row)).') VALUES ('.implode(',',$v).')');}}
pdAssert($user->fetch('','testadmin')>0,'Admin missing');$user->loadRights('',1);pdAssert((new modNorway($db))->init()>0,'Native module refresh');$conf->setValues($db);
$p=$db->prefix();$entity=(int)$conf->entity;$run=bin2hex(random_bytes(4));$oldGlobal=clone $conf->global;$oldSoc=clone $mysoc;
$settings=array('INVOICE_MAX_FUTURE_DELAY'=>'900000000','MAIN_INFO_TVAASSUJ'=>'1','ACCOUNTING_ACCOUNT_SUPPLIER'=>'2400','ACCOUNTING_SERVICE_BUY_ACCOUNT'=>'6790','ACCOUNTING_PRODUCT_BUY_ACCOUNT'=>'4000','ACCOUNTING_VAT_BUY_ACCOUNT'=>'2710','ACCOUNTING_VAT_BUY_REVERSE_CHARGES_CREDIT'=>'2704','ACCOUNTING_VAT_BUY_REVERSE_CHARGES_DEBIT'=>'2714','ACCOUNTING_FORCE_ENABLE_VAT_REVERSE_CHARGE'=>'0','ACCOUNTING_REVERSE_CHARGE_ALSO_NON_EEC'=>'0','ACCOUNTING_PURCHASES_DO_NOT_DISPATCH_TAXES'=>'0');
$oldSettings=array();foreach($settings as $name=>$v){$oldSettings[$name]=pdRows("SELECT * FROM ".$p."const WHERE entity=".$entity." AND name='$name'");}
$codes="'1','11','13','NO0','86','87'";$oldRates=pdRows('SELECT * FROM '.$p.'c_tva WHERE entity='.$entity.' AND code IN ('.$codes.')');
$ids=array();$parties=array();$payments=array();$journal=0;$expected=array();$source=array();$productId=0;$newAccountIds=array();$expectedReport=array();
$cookie=tempnam(sys_get_temp_dir(),'norway-purchase-');$curl=curl_init();curl_setopt_array($curl,array(CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_TIMEOUT=>30));
function pdHttp($path,$post=null){global $curl;curl_setopt($curl,CURLOPT_URL,'http://localhost'.$path);curl_setopt($curl,CURLOPT_POST,$post!==null);if($post!==null){curl_setopt($curl,CURLOPT_POSTFIELDS,http_build_query($post));}$body=curl_exec($curl);pdAssert($body!==false && curl_getinfo($curl,CURLINFO_HTTP_CODE)<400,'Native HTTP failed');return $body;}
function pdToken($body){pdAssert(preg_match('/name="token"[^>]*value="([^"]+)"/',$body,$m)===1,'Native token');return html_entity_decode($m[1]);}
function pdInvoice($supplier,$reverse=0,$type=0,$original=0){global $db,$user,$run,$ids;$o=new FactureFournisseur($db);$o->socid=$supplier;$o->ref_supplier='SYNP-'.$run.'-'.count($ids);$o->date=dol_mktime(12,0,0,2,10,2039);$o->type=$type;$o->fk_facture_source=$original;$o->vat_reverse_charge=$reverse;$o->multicurrency_code='NOK';$o->multicurrency_tx=1;pdAssert($o->create($user)>0,'Native supplier invoice create: '.$o->error);$ids[]=(int)$o->id;return $o;}
function pdLine($o,$base,$rate,$code,$percent,$kind=0,$http=false,$product=0){global $accounts,$p,$db,$expected,$source,$expectedReport;
 $account=$kind===0?'4000':'6790';$accountId=$accounts[$account];
 if($http){$url='/fourn/facture/card.php?facid='.(int)$o->id;$card=pdHttp($url);pdAssert(strpos($card,'options_norway_vat_deduction')!==false,'Native deduction field missing');
  pdHttp($url,array('token'=>pdToken($card),'action'=>'addline','facid'=>$o->id,'type'=>$kind,'prod_entry_mode'=>'free','dp_desc'=>'SYNTHETIC deduction line','qty'=>1,'price_ht'=>$base,'tva_tx'=>$rate.' ('.$code.')','options_norway_vat_deduction'=>$percent,'fk_code_ventilation'=>$accountId));
  $rows=pdRows('SELECT d.rowid,e.norway_vat_deduction FROM '.$p.'facture_fourn_det d LEFT JOIN '.$p.'facture_fourn_det_extrafields e ON e.fk_object=d.rowid WHERE d.fk_facture_fourn='.(int)$o->id.' ORDER BY d.rowid DESC');pdAssert(count($rows)>0 && (float)$rows[0]->norway_vat_deduction===(float)$percent,'Posted deduction not stored');
  pdSql('UPDATE '.$p.'facture_fourn_det SET fk_code_ventilation='.$accountId.' WHERE rowid='.(int)$rows[0]->rowid);
 }else{$options=$percent===null?array():array('options_norway_vat_deduction'=>$percent);pdAssert($o->addline('SYNTHETIC deduction line',$base,$rate.' ('.$code.')',0,0,1,$product,0,0,0,$accountId,0,'HT',$kind,-1,0,$options)>0,'Native supplier line: '.$o->error);}
 $foreign=in_array($code,array('86','87'),true);$vat=(int)round($base*($foreign?25:$rate));$deduct=(int)round($vat*($percent===null?($code==='87'?0:100):(float)$percent)/100);
 $cost=(int)round($base*100)+$vat-$deduct;$gross=(int)round($base*100)+($foreign?0:$vat);
 foreach(array($account=>$cost,'2400'=>-$gross,($foreign?'2714':array('1'=>'2710','11'=>'2711','13'=>'2713')[$code])=>$deduct) as $a=>$amount){$expected[$o->id][$a]=($expected[$o->id][$a]??0)+$amount;}
 if($foreign){$expected[$o->id]['2704']=($expected[$o->id]['2704']??0)-$vat;}
 $official=$foreign?($deduct===0?'87':'86'):($deduct===0?'0':$code);
 if($foreign){$key=$official.'/1';$expectedReport[$o->id][$key]=($expectedReport[$o->id][$key]??0)+$vat;if($official==='86'){$key=$official.'/2';$expectedReport[$o->id][$key]=($expectedReport[$o->id][$key]??0)+$deduct;}}
 else{$key=$official.'/2';$expectedReport[$o->id][$key]=($expectedReport[$o->id][$key]??0)+$deduct;}
}
function pdSnapshot($id){global $p;return json_encode(array(pdRows('SELECT total_ht,total_tva,total_ttc,vat_reverse_charge FROM '.$p.'facture_fourn WHERE rowid='.$id),pdRows('SELECT d.*,e.norway_vat_deduction FROM '.$p.'facture_fourn_det d LEFT JOIN '.$p.'facture_fourn_det_extrafields e ON e.fk_object=d.rowid WHERE d.fk_facture_fourn='.$id.' ORDER BY d.rowid')),JSON_THROW_ON_ERROR);}
try{
 foreach($settings as $name=>$value){pdAssert(dolibarr_set_const($db,$name,$value,'chaine',0,'',$entity)>0,'Synthetic setting');$conf->global->$name=$value;}$mysoc->tva_assuj=1;$mysoc->country_code='NO';
 $chart=$db->fetch_object(pdSql('SELECT pcg_version FROM '.$p.'accounting_system WHERE rowid='.(int)getDolGlobalInt('CHARTOFACCOUNTS')));pdAssert((bool)$chart,'Native chart');$accounts=array();foreach(pdRows('SELECT rowid,account_number FROM '.$p.'accounting_account WHERE entity='.$entity." AND fk_pcg_version='".$db->escape($chart->pcg_version)."'") as $a){$accounts[$a->account_number]=(int)$a->rowid;}
 foreach(array('4000','6790','2710','2711','2713','2714','2704','2400') as $number){if(isset($accounts[$number])){continue;}$a=new AccountingAccount($db);$a->fk_pcg_version=$chart->pcg_version;$a->account_number=$number;$a->label='SYNTHETIC '.$number;$a->active=1;pdAssert($a->create($user)>0,'Synthetic account');$accounts[$number]=(int)$a->id;$newAccountIds[]=(int)$a->id;}
 pdSql('DELETE FROM '.$p.'c_tva WHERE entity='.$entity.' AND code IN ('.$codes.')');
 foreach(array(array('1',25,'2710'),array('11',15,'2711'),array('13',12,'2713'),array('NO0',0,''),array('86',0,'2714'),array('87',0,'2714')) as [$code,$rate,$a]){pdSql("INSERT INTO ".$p."c_tva (entity,fk_pays,code,type_vat,taux,recuperableonly,active,accountancy_code_buy) VALUES ($entity,173,'$code',2,$rate,0,1,'$a')");}
 pdSql("INSERT INTO ".$p."accounting_journal (entity,code,label,nature,active) VALUES ($entity,'SYNP-$run','Synthetic deduction',3,1)");$journal=(int)$db->last_insert_id($p.'accounting_journal');
 foreach(array('NO','SE','US') as $country){$c=$db->fetch_object(pdSql("SELECT rowid FROM ".$p."c_country WHERE code='$country'"));$s=new Societe($db);$s->name='SYNTHETIC PURCHASE ONLY '.$run.' '.$country;$s->fournisseur=1;$s->country_id=(int)$c->rowid;$s->code_fournisseur='SYNP-'.$run.'-'.$country;$s->code_compta_fournisseur='SYNP-'.$country;pdAssert($s->create($user)>0,'Supplier');$parties[$country]=(int)$s->id;}
 $body=pdHttp('/index.php');pdHttp('/index.php',array('token'=>pdToken($body),'actionlogin'=>'login','loginfunction'=>'loginfunction','username'=>'testadmin','password'=>getenv('DOLI_ADMIN_PASSWORD')));
 // Independent expected amounts: 1000+250 at 60% -> cost1100/input150/liability1250.
 foreach(array(0,1) as $kind){foreach(array(0,60,100,37.5) as $percent){$o=pdInvoice($parties['NO']);foreach(array(25=>'1',15=>'11',12=>'13') as $rate=>$code){pdLine($o,1000,$rate,$code,$percent,$kind,$kind===0 && $percent===60 && $rate===25);}
  pdAssert($o->validate($user,$o->ref_supplier)>0,'Native domestic validation: '.$o->error);$source[$o->id]=pdSnapshot($o->id);
  // Native "credit with lines" action copies native line extrafields without a custom credit handler.
  $body=pdHttp('/fourn/facture/card.php?action=create&socid='.$parties['NO']);$ref='SYNP-C-'.$run.'-'.$o->id;
  pdHttp('/fourn/facture/card.php',array('token'=>pdToken($body),'action'=>'add','type'=>2,'socid'=>$parties['NO'],'fac_avoir'=>$o->id,'invoiceAvoirWithLines'=>1,'ref_supplier'=>$ref,'reday'=>11,'remonth'=>2,'reyear'=>2039,'multicurrency_code'=>'NOK','originmulticurrency_tx'=>1));
  $credit=$db->fetch_object(pdSql("SELECT rowid FROM ".$p."facture_fourn WHERE entity=$entity AND ref_supplier='".$db->escape($ref)."'"));pdAssert((bool)$credit,'Native credit creation');$ids[]=(int)$credit->rowid;$c=new FactureFournisseur($db);pdAssert($c->fetch($credit->rowid)>0 && count($c->lines)===3,'Credit lines');
  foreach($c->lines as $l){$l->fetch_optionals();pdAssert((float)$l->array_options['options_norway_vat_deduction']==(float)$percent,'Credit lost deduction');pdSql('UPDATE '.$p.'facture_fourn_det SET fk_code_ventilation='.$accounts[$kind===0?'4000':'6790'].' WHERE rowid='.(int)$l->id);}
  pdAssert($c->validate($user,$ref)>0,'Credit validate: '.$c->error);foreach($expected[$o->id] as $a=>$amount){$expected[$c->id][$a]=-$amount;}$source[$c->id]=pdSnapshot($c->id);foreach($expectedReport[$o->id] as $key=>$amount){$expectedReport[$c->id][$key]=-$amount;}
 }}
 foreach(array('SE','US') as $country){foreach(array(0,60,100) as $percent){$foreignOriginal=0;foreach(array(0,2) as $type){$o=pdInvoice($parties[$country],1,$type,$type===2?$foreignOriginal:0);if($type===0){$foreignOriginal=(int)$o->id;}pdLine($o,$type===2?-1000:1000,0,'86',$percent,1,true);pdAssert($o->validate($user,$o->ref_supplier)>0,'Foreign validation: '.$o->error);$source[$o->id]=pdSnapshot($o->id);}}
  foreach(array(0,2) as $type){$o=pdInvoice($parties[$country],1,$type);pdLine($o,$type===2?-1000:1000,0,'87',null,1);pdAssert($o->validate($user,$o->ref_supplier)>0,'Native 87 validation');$source[$o->id]=pdSnapshot($o->id);}
 }
 // The rate is saved on the document, not read back from a mutable product at transfer time.
 require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';$product=new Product($db);$product->ref='SYNP-'.$run;$product->label='SYNTHETIC service only';$product->type=1;$product->status=1;$product->status_buy=1;$product->tva_tx=25;$product->default_vat_code='3';pdAssert($product->create($user)>0,'Service product');$productId=(int)$product->id;
 foreach(array(0,2) as $type){$o=pdInvoice($parties['US'],1,$type);foreach(array(100.10,50.02,0.03) as $base){pdLine($o,$type===2?-$base:$base,0,'86',60,1,false,$productId);}pdAssert($o->validate($user,$o->ref_supplier)>0,'Product invoice');$source[$o->id]=pdSnapshot($o->id);}
 pdSql('UPDATE '.$p."product SET tva_tx=12,default_vat_code='33' WHERE rowid=".$productId);
 // A failed choice must roll back the native line, rather than leave a partial registration.
 $bad=pdInvoice($parties['NO']);$before=count(pdRows('SELECT rowid FROM '.$p.'facture_fourn_det WHERE fk_facture_fourn='.(int)$bad->id));
 pdAssert($bad->addline('SYNTHETIC invalid deduction',100,'25 (1)',0,0,1,0,0,0,0,$accounts['4000'],0,'HT',0,-1,0,array('options_norway_vat_deduction'=>101))<0,'Invalid percentage accepted');
 pdAssert(count(pdRows('SELECT rowid FROM '.$p.'facture_fourn_det WHERE fk_facture_fourn='.(int)$bad->id))===$before,'Failed line survived rollback');
 $line=$bad->addline('SYNTHETIC editable deduction',100,'25 (1)',0,0,1,0,0,0,0,$accounts['4000'],0,'HT',0,-1,0,array('options_norway_vat_deduction'=>60));pdAssert($line>0,'Editable draft line');
 $editPath='/fourn/facture/card.php?lang=nb_NO&facid='.(int)$bad->id;$editCard=pdHttp($editPath);$edit=pdHttp($editPath.'&action=editline&lineid='.$line.'&token='.urlencode(pdToken($editCard)));pdAssert(strpos($edit,'name="options_norway_vat_deduction"')!==false,'Native edit field');
 pdHttp($editPath,array('token'=>pdToken($edit),'action'=>'updateline','lineid'=>$line,'price_ht'=>100,'qty'=>1,'tva_tx'=>'25 (1)','type'=>0,'product_desc'=>'SYNTHETIC edited deduction','options_norway_vat_deduction'=>'37,5'));
 $edited=$db->fetch_object(pdSql('SELECT norway_vat_deduction FROM '.$p.'facture_fourn_det_extrafields WHERE fk_object='.$line));pdAssert($edited && $edited->norway_vat_deduction==='37.50','Native line edit lost fractional deduction');
 $params=array('id_journal'=>$journal,'date_startday'=>1,'date_startmonth'=>2,'date_startyear'=>2039,'date_endday'=>28,'date_endmonth'=>2,'date_endyear'=>2039,'in_bookkeeping'=>'notyet');$path='/accountancy/journal/purchasesjournal.php?'.http_build_query($params);$preview=pdHttp($path);pdAssert(strpos($preview,'SYNP-')!==false,'Native purchase preview');pdHttp($path,$params+array('token'=>pdToken($preview),'action'=>'writebookkeeping'));
 foreach($expected as $id=>$accountsExpected){$actual=array();$pieces=array();foreach(pdRows('SELECT numero_compte,debit,credit,piece_num FROM '.$p."accounting_bookkeeping WHERE entity=$entity AND doc_type='supplier_invoice' AND fk_doc=".$id) as $l){$actual[$l->numero_compte]=($actual[$l->numero_compte]??0)+(int)round(((float)$l->debit-(float)$l->credit)*100);$pieces[(int)$l->piece_num]=true;}
  pdAssert(count($pieces)===1 && array_sum($actual)===0,'Expected one balanced core voucher '.$id.': '.json_encode($actual));foreach(array_unique(array_merge(array_keys($actual),array_keys($accountsExpected))) as $a){pdAssert(($actual[$a]??0)===($accountsExpected[$a]??0),'Incorrect cost/input/output/liability '.$id.' '.$a.': '.json_encode($actual).' expected '.json_encode($accountsExpected));}pdAssert(pdSnapshot($id)===$source[$id],'Original source amount/deduction changed');
 }
 $report=NorwayVatReport::build($db,$entity,'2039-02-01','2039-02-28',$user);pdAssert(!$report['issues'],'Normal deduction report: '.json_encode($report['issues']));pdAssert($report['net']===0,'Invoices and full credits must offset');
 $seen=array();foreach($report['entries'] as $e){if($e['source']!=='supplier_invoice'){continue;}$key=$e['code'].'/'.$e['type'];$seen[$e['id']][$key]=($seen[$e['id']][$key]??0)+$e['tax'];}
 foreach($expectedReport as $id=>$groups){ksort($groups);$actual=$seen[$id]??array();ksort($actual);pdAssert($actual===$groups,'Per-invoice VAT classification/amount '.$id.': '.json_encode($actual).' expected '.json_encode($groups));}
 // Changing a stored deduction after posting must make the extract refuse those figures.
 $check=$ids[0];$db->begin();pdSql('UPDATE '.$p.'facture_fourn_det_extrafields e JOIN '.$p.'facture_fourn_det d ON d.rowid=e.fk_object SET e.norway_vat_deduction=99 WHERE d.fk_facture_fourn='.$check);
 // build() deliberately owns its snapshot transaction; inspect the report after a committed synthetic mutation.
 $db->commit();$broken=NorwayVatReport::build($db,$entity,'2039-02-01','2039-02-28',$user);pdAssert($broken['issues'] && $broken['net']===null,'Changed deduction exported as correct');
 pdSql('UPDATE '.$p.'facture_fourn_det_extrafields e JOIN '.$p.'facture_fourn_det d ON d.rowid=e.fk_object SET e.norway_vat_deduction=0 WHERE d.fk_facture_fourn='.$check);

 // Settle an invoice and refund its credit with the actual supplier payment class.
 $transfer=$db->fetch_object(pdSql("SELECT id FROM ".$p."c_paiement WHERE entity=$entity AND code='VIR' AND active=1"));pdAssert((bool)$transfer,'Transfer');
 foreach(array_slice($ids,0,2) as $id){$o=new FactureFournisseur($db);pdAssert($o->fetch($id)>0,'Payment invoice');$pay=new PaiementFourn($db);$pay->datepaye=dol_mktime(12,0,0,2,12,2039);$pay->paiementid=(int)$transfer->id;$pay->amounts=array($id=>(float)$o->total_ttc);pdAssert($pay->create($user,1)>0,'Native supplier settlement/refund: '.$pay->error);$payments[]=(int)$pay->id;pdAssert(abs($o->getSommePaiement()-(float)$o->total_ttc)<0.001,'Wrong payable after settlement');}
 $count=count(pdRows('SELECT rowid FROM '.$p."accounting_bookkeeping WHERE entity=$entity AND doc_type='supplier_invoice' AND fk_doc IN (".implode(',',$ids).')'));$body=pdHttp($path);pdHttp($path,$params+array('token'=>pdToken($body),'action'=>'writebookkeeping'));pdAssert(count(pdRows('SELECT rowid FROM '.$p."accounting_bookkeeping WHERE entity=$entity AND doc_type='supplier_invoice' AND fk_doc IN (".implode(',',$ids).')'))===$count,'Duplicate native transfer');
 echo "PASS normal domestic goods/services 25/15/12, 0/37.5/60/100% deduction; native HTTP entry and copied credits, source preservation, one native voucher per invoice, reconciled VAT and supplier settlement/refund\n";
 echo "PASS foreign free-text services SE/US, native 86/87, full/partial/no deduction, invoices/credits, full output and actual input, preserved liability and no manual correction vouchers\n";
}finally{
 $db->rollback();foreach($payments as $id){pdSql('DELETE FROM '.$p.'paiementfourn_facturefourn WHERE fk_paiementfourn='.$id);pdSql('DELETE FROM '.$p.'paiementfourn WHERE rowid='.$id);}
 foreach(array_reverse($ids) as $id){pdSql('DELETE e FROM '.$p.'facture_fourn_det_extrafields e JOIN '.$p.'facture_fourn_det d ON d.rowid=e.fk_object WHERE d.fk_facture_fourn='.$id);pdSql('DELETE FROM '.$p."accounting_bookkeeping WHERE entity=$entity AND doc_type='supplier_invoice' AND fk_doc=".$id);pdSql('DELETE FROM '.$p.'facture_fourn_det WHERE fk_facture_fourn='.$id);pdSql('DELETE FROM '.$p.'facture_fourn_extrafields WHERE fk_object='.$id);pdSql('DELETE FROM '.$p.'facture_fourn WHERE rowid='.$id);}
 foreach($parties as $id){pdSql('DELETE FROM '.$p.'societe_extrafields WHERE fk_object='.$id);pdSql('DELETE FROM '.$p.'societe WHERE rowid='.$id);}if($journal){pdSql('DELETE FROM '.$p.'accounting_journal WHERE rowid='.$journal);}
 if($newAccountIds){pdSql('DELETE FROM '.$p.'accounting_account WHERE rowid IN ('.implode(',',$newAccountIds).')');}
 if($productId){foreach(array('product_lang'=>'fk_product','product_price'=>'fk_product','product_extrafields'=>'fk_object','product'=>'rowid') as $table=>$fk){pdSql('DELETE FROM '.$p.$table.' WHERE '.$fk.'='.$productId);}}
 pdSql('DELETE FROM '.$p.'c_tva WHERE entity='.$entity.' AND code IN ('.$codes.')');pdRestore($p.'c_tva',$oldRates);foreach($settings as $name=>$value){pdSql("DELETE FROM ".$p."const WHERE entity=$entity AND name='$name'");pdRestore($p.'const',$oldSettings[$name]);}$conf->global=$oldGlobal;$mysoc=$oldSoc;curl_close($curl);unlink($cookie);
}
