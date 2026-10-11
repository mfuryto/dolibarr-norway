<?php
// SPDX-License-Identifier: GPL-3.0-or-later
if (PHP_SAPI!=='cli' || getenv('NORWAY_SYNTHETIC_TEST')!=='1' || getenv('DOLI_DB_NAME')!=='norway_test') { exit(1); }
require '/var/www/html/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/norway/class/NorwayPeriodSetup.php';
function psAssert($ok,$message) { if (!$ok) { throw new RuntimeException($message); } }
$periods=array(array('rowid'=>1,'label'=>'Synthetic closed','date_start'=>'2023-01-01','date_end'=>'2023-12-31','statut'=>1),array('rowid'=>2,'label'=>'Synthetic open','date_start'=>'2024-01-01','date_end'=>'2024-12-31','statut'=>0));
$before=$periods;
foreach (array('2024-01-01'=>'Open','2024-02-29'=>'Open','2024-12-31'=>'Open','2023-12-31'=>'Closed','2025-01-01'=>'Missing') as $date=>$status) {
 $r=NorwayPeriodSetup::evaluate($periods,$date,2,'blockedonclosed'); psAssert($r['coverage']===$status && $r['default']==='Open','Coverage/default boundary mismatch');
}
psAssert($periods===$before,'Period evaluation mutated source');
foreach (array('2023-02-29','2024-13-01','2024-01-32','2024-1-1',"2024-01-01'",'') as $date) {
 try { NorwayPeriodSetup::evaluate($periods,$date,2,''); throw new LogicException('Invalid date accepted'); } catch (InvalidArgumentException $expected) {}
}
$periods[]=array('rowid'=>3,'label'=>'Synthetic overlap','date_start'=>'2024-12-31','date_end'=>'2025-12-31','statut'=>1);
$r=NorwayPeriodSetup::evaluate($periods,'2024-12-31',2,''); psAssert($r['coverage']==='Overlap' && $r['default']==='Overlap','Inclusive overlap missed');
$periods[2]['date_start']='2025-01-01';
$r=NorwayPeriodSetup::evaluate($periods,'2024-12-31',99,''); psAssert($r['coverage']==='Open' && $r['default']==='Missing','Adjacent periods overlap or foreign default accepted');
$periods[2]['date_end']=null;
$r=NorwayPeriodSetup::evaluate($periods,'2025-12-31',3,''); psAssert($r['coverage']==='Dates' && $r['default']==='Dates','Open-ended period silently treated as missing');
$periods[2]['date_end']='2025-12-31'; $periods[2]['statut']=7;
psAssert(NorwayPeriodSetup::evaluate($periods,'2025-01-01',3,'')['coverage']==='Unknown','Unknown status accepted');
$old=$conf->entity; $conf->entity=987657; $db->begin();
try {
 foreach (array(987657,987658) as $entity) {
  psAssert((bool)$db->query("INSERT INTO ".$db->prefix()."accounting_fiscalyear (label,date_start,date_end,statut,entity,datec) VALUES ('Synthetic period','2024-01-01','2024-12-31',0,".$entity.",NOW())"),'Synthetic period fixture failed');
 }
 $r=NorwayPeriodSetup::report($db,987657,'2024-06-01',0,''); psAssert(count($r['rows'])===1 && $r['coverage']==='Open','Foreign periods leaked');
 try { NorwayPeriodSetup::report($db,987658,'2024-06-01',0,''); throw new LogicException('Foreign entity accepted'); } catch (InvalidArgumentException $expected) {}
} finally { $db->rollback(); $conf->entity=$old; }
$failed=new class { public function prefix(){return 'synthetic_';} public function query($sql){return false;} };
try { NorwayPeriodSetup::report($failed,$conf->entity,'2024-06-01',0,''); throw new LogicException('Database error hidden'); } catch (RuntimeException $expected) {}
print "PASS fiscal-period dates, leap day, inclusive boundaries, overlaps, open-ended/unknown periods, entity scope and query failure\n";
