<?php
// SPDX-License-Identifier: GPL-3.0-or-later
if(PHP_SAPI!=='cli'||getenv('NORWAY_SYNTHETIC_TEST')!=='1'||getenv('DOLI_DB_NAME')!=='norway_test'){exit(1);}
require '/var/www/html/master.inc.php';require_once DOL_DOCUMENT_ROOT.'/custom/norway/class/NorwayNumbering.php';require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
function ppAssert($ok,$message){if(!$ok){throw new RuntimeException($message);}}
function ppSql($sql){global $db;$r=$db->query($sql);ppAssert((bool)$r,'Synthetic prefix query failed: '.$db->lasterror());return $r;}
function ppSave($input){global $db,$conf,$user;$preview=NorwayNumbering::preview($db,(int)$conf->entity,$input,$user);return NorwayNumbering::save($db,(int)$conf->entity,$input,$preview['fingerprint'],$user);}
ppAssert($user->fetch('','testadmin')>0,'Admin missing');$user->loadRights('',1);$oldEntity=$conf->entity;$oldGlobal=clone $conf->global;$entity=987689;$p=$db->prefix();$created=array();
ppAssert((int)$db->fetch_object(ppSql('SELECT COUNT(*) AS n FROM '.$p.'societe WHERE entity='.$entity))->n===0&&(int)$db->fetch_object(ppSql('SELECT COUNT(*) AS n FROM '.$p.'const WHERE entity='.$entity))->n===0,'Prefix entity must be empty');$conf->entity=$entity;
function ppPartner($customer,$supplier){global $db,$user,$created;$o=new Societe($db);$o->name='SYNTHETIC PREFIX PARTNER ONLY';$o->client=$customer;$o->fournisseur=$supplier;$o->code_client=$customer?'auto':'';$o->code_fournisseur=$supplier?'auto':'';$o->country_id=173;ppAssert($o->create($user)>0,'Native partner creation: '.$o->error);$created[]=(int)$o->id;ppAssert($o->fetch($o->id)>0,'Partner reload');return $o;}
try{
 $plainInput=NorwayNumbering::input('F',1,'K',1);ppSave($plainInput);$legacyRecord=NorwayNumbering::record($db,$entity);$legacyRecord['version']=1;unset($legacyRecord['partner_prefixes'],$legacyRecord['input']['customer_prefix'],$legacyRecord['input']['supplier_prefix']);
 ppSql("UPDATE ".$p."const SET value='".$db->escape(json_encode($legacyRecord))."' WHERE entity=".$entity." AND name='".NorwayNumbering::SETTING."'");ppAssert(NorwayNumbering::record($db,$entity)['version']===1,'Legacy stored profile unreadable');
 $input=NorwayNumbering::input('F',1,'K',1,'T',1,1,1,'K','L');$next=ppSave($input);ppAssert($next['customer']==='K00001'&&$next['supplier']==='L00001','Initial prefixed codes wrong');
 $both=ppPartner(1,1);ppAssert($both->code_client==='K00001'&&$both->code_fournisseur==='L00001','Both roles not independent');
 $customer=ppPartner(1,0);ppAssert($customer->code_client==='K00002','Customer counter wrong');
 $input['customer_prefix']='C';$next=ppSave($input);ppAssert($next['customer']==='C00003'&&$next['supplier']==='L00002','Prefix change reset or coupled counters');
 $changed=ppPartner(1,0);ppAssert($changed->code_client==='C00003','New prefix not used');$both->name='SYNTHETIC PREFIX PARTNER RENAMED';ppAssert($both->update($both->id,$user)>0,'Old codes rejected on unrelated edit');ppAssert($both->fetch($both->id)>0&&$both->code_client==='K00001'&&$both->code_fournisseur==='L00001','Existing code rewritten');
 $input['customer_prefix']='';$input['supplier_prefix']='';ppSave($input);$plain=ppPartner(1,1);ppAssert($plain->code_client==='00004'&&$plain->code_fournisseur==='00002','Returning to no prefix reset counters');
 $input['customer_prefix']='K';$input['supplier_prefix']='L';ppSave($input);$back=ppPartner(1,1);ppAssert($back->code_client==='K00005'&&$back->code_fournisseur==='L00003','Returning to old prefix reused numbers');
 $record=NorwayNumbering::record($db,$entity,'customer');ppAssert($record['version']===2&&$record['partner_prefixes']['customer']===array('','K','C'),'Prefix history missing');
 $before=$input;$before['customer_start']=2;try{NorwayNumbering::preview($db,$entity,$before,$user);throw new LogicException('Used starting number reset');}catch(RuntimeException $expected){}
 foreach(array('k','K-',' K','ABCDE') as $prefix){try{NorwayNumbering::input('F',1,'K',1,'T',1,1,1,$prefix,'L');throw new LogicException('Invalid prefix accepted');}catch(InvalidArgumentException $expected){}}
 // Legacy v1 records read with empty prefixes; saving upgrades only configuration.
 $legacy=$record;$legacy['version']=1;unset($legacy['partner_prefixes'],$legacy['input']['customer_prefix'],$legacy['input']['supplier_prefix']);$decoded=NorwayNumbering::decode(json_encode($legacy),$entity);ppAssert($decoded['input']['customer_prefix']===''&&$decoded['input']['supplier_prefix']==='','Legacy profile changed codes');
 $model=new mod_codeclient_norway($db);$bad='K00000';ppAssert($model->verif($db,$bad,new Societe($db),0)<0,'Zero suffix accepted');$bad='C00006';ppAssert($model->verif($db,$bad,new Societe($db),0)<0,'Old prefix accepted for new code');
 // Capacity is shared across remembered prefixes; a new prefix cannot bypass 99999.
 ppSql("UPDATE ".$p."societe SET code_client='C99999' WHERE rowid=".(int)$changed->id);
 try{NorwayNumbering::next($db,$entity,$input,'customer');throw new LogicException('Prefix switch bypassed capacity');}catch(RuntimeException $expected){}
}finally{foreach($created as $id){ppSql('DELETE FROM '.$p.'societe_extrafields WHERE fk_object='.$id);ppSql('DELETE FROM '.$p.'societe WHERE rowid='.$id.' AND entity='.$entity);}ppSql('DELETE FROM '.$p.'const WHERE entity='.$entity);$conf->entity=$oldEntity;$conf->global=$oldGlobal;}
print "PASS native optional partner prefixes, independent counters, role overlap, prefix/empty/back transitions, old-code preservation, legacy v1 decoding, start freeze and shared capacity\n";
