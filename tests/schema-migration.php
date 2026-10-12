<?php
// SPDX-License-Identifier: GPL-3.0-or-later
if (PHP_SAPI!=='cli' || getenv('NORWAY_SYNTHETIC_TEST')!=='1' || getenv('DOLI_DB_NAME')!=='norway_test') { exit(1); }
require '/var/www/html/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/norway/class/NorwaySchema.php';
function smAssert($ok,$message) { if (!$ok) { throw new RuntimeException($message); } }
function smBlocked($call,$message) { $blocked=false; try { $call(); } catch (RuntimeException $error) { $blocked=true; } smAssert($blocked,$message); }
$test=new class($db) {
 private $inner; public $fail=false;
 public function __construct($db) { $this->inner=$db; }
 public function prefix() { return 'llx_synmigrate_'; }
 public function __call($name,$args) {
  if ($this->fail && $name==='query' && strpos($args[0],'INSERT INTO llx_synmigrate_norway_schema')===0) { return false; }
  return $this->inner->$name(...$args);
 }
};
$p=$test->prefix();
function smDrop($db,$p) { foreach (array('norway_tax_evidence','norway_schema') as $name) { smAssert((bool)$db->query('DROP TABLE IF EXISTS '.$p.$name),'Synthetic migration cleanup failed'); } }
smDrop($db,$p);
try {
 smAssert(NorwaySchema::migrate($test) && NorwaySchema::verify($test),'Fresh migration failed');
 smAssert(NorwaySchema::migrate($test),'Repeated migration failed');
 $db->begin();
 try { smBlocked(fn()=>NorwaySchema::migrate($test),'DDL implicitly committed an enclosing transaction'); } finally { $db->rollback(); }
 $r=$db->query('SELECT COUNT(*) AS n FROM '.$p.'norway_schema'); smAssert((int)$db->fetch_object($r)->n===1,'Repeated migration duplicated history');
 $payload='{"synthetic":true}'; $hash=hash('sha256',$payload);
 $insert="INSERT INTO ".$p."norway_tax_evidence (entity,document_type,fk_document,revision,captured_at,fk_user,mapping_revision,payload,payload_sha256) VALUES (1,'customer_invoice',999999,1,UTC_TIMESTAMP(),1,1,'".$payload."','".$hash."')";
 smAssert((bool)$db->query($insert),'Synthetic evidence fixture failed');
 smAssert(!$db->query($insert),'Duplicate document revision accepted');
 smAssert((bool)$db->query(str_replace("VALUES (1,","VALUES (2,",$insert)), 'Entity-specific revision rejected');
 smAssert(NorwaySchema::migrate($test),'Existing payload blocked repeated migration');
 $r=$db->query('SELECT COUNT(*) AS n FROM '.$p.'norway_tax_evidence'); smAssert((int)$db->fetch_object($r)->n===2,'Migration changed existing payloads');
 smAssert((bool)$db->query('DELETE FROM '.$p.'norway_schema'),'Lost metadata fixture failed');
 smBlocked(fn()=>NorwaySchema::migrate($test),'Untracked evidence silently adopted');
 smDrop($db,$p); $test->fail=true;
 smBlocked(fn()=>NorwaySchema::migrate($test),'Failed migration marker accepted'); $test->fail=false;
 smBlocked(fn()=>NorwaySchema::verify($test),'Partial migration reported complete');
 smAssert(NorwaySchema::migrate($test),'Interrupted empty migration could not resume');
 smAssert((bool)$db->query("UPDATE ".$p."norway_schema SET checksum=REPEAT('0',64)"),'Checksum fixture failed');
 smBlocked(fn()=>NorwaySchema::migrate($test),'Changed migration checksum accepted');
 smAssert((bool)$db->query("UPDATE ".$p."norway_schema SET checksum='".NorwaySchema::CHECKSUM."',version=2"),'Future version fixture failed');
 smBlocked(fn()=>NorwaySchema::migrate($test),'Future schema silently downgraded');
 smAssert((bool)$db->query('UPDATE '.$p.'norway_schema SET version=1'),'Version restore failed');
 smAssert((bool)$db->query('DROP TABLE '.$p.'norway_tax_evidence'),'Lost table fixture failed');
 smBlocked(fn()=>NorwaySchema::migrate($test),'Lost evidence table silently recreated');
 $r=$db->query("SHOW TABLE STATUS WHERE Name='".$p."norway_tax_evidence'"); smAssert($r && !$db->fetch_object($r),'Missing evidence table was recreated');
 smDrop($db,$p); NorwaySchema::migrate($test);
 smAssert((bool)$db->query('ALTER TABLE '.$p.'norway_tax_evidence MODIFY payload VARCHAR(64) NOT NULL'),'Incompatible shape fixture failed');
 smBlocked(fn()=>NorwaySchema::migrate($test),'Incompatible existing schema accepted');
} finally { smDrop($db,$p); }
print "PASS fresh/repeated/interrupted migration, checksum/version/shape guards, unique document revisions, entity separation, retained payloads and lost-table protection\n";
