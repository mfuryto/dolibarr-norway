<?php
// SPDX-License-Identifier: GPL-3.0-or-later
if (PHP_SAPI!=='cli' || getenv('NORWAY_SYNTHETIC_TEST')!=='1' || getenv('DOLI_DB_NAME')!=='norway_test') { exit(1); }
require '/var/www/html/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/norway/class/NorwayVatRegistration.php';
function vrAssert($ok,$message) { if (!$ok) { throw new RuntimeException($message); } }
$oldEntity=$conf->entity; $oldSoc=$mysoc; $entity=987661; $conf->entity=$entity;
$mysoc=(object)array('idprof1'=>'123456785','country_code'=>'NO'); $p=$db->prefix();
$user=new User($db); vrAssert($user->fetch('','testadmin')>0,'Synthetic admin missing'); $user->admin=1;
$db->begin();
try {
 foreach (array('MAIN_INFO_SIREN'=>'123456785','MAIN_INFO_SOCIETE_COUNTRY'=>'173:NO:Norway') as $key=>$value) {
  vrAssert((bool)$db->query("INSERT INTO ".$p."const (name,value,type,entity) VALUES ('".$key."','".$value."','chaine',".$entity.")"),'Synthetic registration fixture failed');
 }
 vrAssert(NorwayVatRegistration::read($db,$entity,$mysoc->idprof1)['status']==='Missing','Missing record hidden');
 try { NorwayVatRegistration::preview($db,$entity,'2023-02-29'); throw new LogicException('Invalid date accepted'); } catch (InvalidArgumentException $expected) {}
 $preview=NorwayVatRegistration::preview($db,$entity,'2024-02-29'); vrAssert($preview['allowed'],'Valid first record blocked');
 $reader=clone $user; $reader->admin=0;
 try { NorwayVatRegistration::apply($db,$entity,'2024-02-29',NorwayVatRegistration::fingerprint($preview),$reader); throw new LogicException('Nonadmin accepted'); } catch (InvalidArgumentException $expected) {}
 try { NorwayVatRegistration::apply($db,$entity+1,'2024-02-29',NorwayVatRegistration::fingerprint($preview),$user); throw new LogicException('Foreign entity accepted'); } catch (InvalidArgumentException $expected) {}
 $changed=$preview; $changed['date']='2024-03-01';
 try { NorwayVatRegistration::apply($db,$entity,'2024-02-29',NorwayVatRegistration::fingerprint($changed),$user); throw new LogicException('Changed preview accepted'); } catch (InvalidArgumentException $expected) {}
 vrAssert((bool)$db->query("UPDATE ".$p."const SET value='974760673' WHERE entity=".$entity." AND name='MAIN_INFO_SIREN'"),'Synthetic identity change failed');
 try { NorwayVatRegistration::apply($db,$entity,'2024-02-29',NorwayVatRegistration::fingerprint($preview),$user); throw new LogicException('Changed stored identity accepted'); } catch (InvalidArgumentException $expected) {}
 vrAssert((bool)$db->query("UPDATE ".$p."const SET value='123456785' WHERE entity=".$entity." AND name='MAIN_INFO_SIREN'"),'Synthetic identity restore failed');
 NorwayVatRegistration::apply($db,$entity,'2024-02-29',NorwayVatRegistration::fingerprint($preview),$user);
 $record=NorwayVatRegistration::read($db,$entity,$mysoc->idprof1);
 vrAssert($record['status']==='Recorded' && $record['start']==='2024-02-29','Record not stored');
 foreach (array('2024-02-28'=>'Before','2024-02-29'=>'After','2024-03-01'=>'After') as $date=>$expected) { vrAssert(NorwayVatRegistration::compare($record,$date)===$expected,'Date boundary mismatch'); }
 vrAssert(!NorwayVatRegistration::preview($db,$entity,'2024-03-01')['allowed'],'Existing date replaceable');
 try { vrAssert((bool)$db->query("UPDATE ".$p."const SET value='974760673' WHERE entity=".$entity." AND name='MAIN_INFO_SIREN'"),'Synthetic identity change failed');
 try { NorwayVatRegistration::apply($db,$entity,'2024-02-29',NorwayVatRegistration::fingerprint($preview),$user); throw new LogicException('Changed stored identity accepted'); } catch (InvalidArgumentException $expected) {}
 vrAssert((bool)$db->query("UPDATE ".$p."const SET value='123456785' WHERE entity=".$entity." AND name='MAIN_INFO_SIREN'"),'Synthetic identity restore failed');
 NorwayVatRegistration::apply($db,$entity,'2024-02-29',NorwayVatRegistration::fingerprint($preview),$user); throw new LogicException('Record overwritten'); } catch (InvalidArgumentException $expected) {}
 vrAssert(NorwayVatRegistration::read($db,$entity,'974760673')['status']==='Identity','Changed identity accepted');
 vrAssert((bool)$db->query("UPDATE ".$p."const SET value='invalid' WHERE entity=".$entity." AND name='".NorwayVatRegistration::SETTING."'"),'Synthetic invalid record failed');
 vrAssert(NorwayVatRegistration::read($db,$entity,$mysoc->idprof1)['status']==='Invalid','Invalid record trusted');
 $r=$db->query("SELECT COUNT(*) AS n FROM ".$p."const WHERE entity=".$entity." AND name IN ('FACTURE_TVAOPTION','MAIN_INFO_TVAINTRA')");
 vrAssert($r && (int)$db->fetch_object($r)->n===0,'VAT status/number altered');
} finally { $db->rollback(); $conf->entity=$oldEntity; $mysoc=$oldSoc; unset($conf->global->{NorwayVatRegistration::SETTING}); }
print "PASS explicit VAT registration date, identity/entity/admin scope, immutable record, invalid data and comparison boundaries\n";
