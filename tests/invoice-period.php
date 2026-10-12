<?php
// SPDX-License-Identifier: GPL-3.0-or-later
if (PHP_SAPI !== 'cli' || getenv('NORWAY_SYNTHETIC_TEST') !== '1' || getenv('DOLI_DB_NAME') !== 'norway_test') { exit(1); }
require '/var/www/html/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/norway/class/NorwayInvoicePeriod.php';
function periodCheck($ok,$message) { if (!$ok) { throw new RuntimeException($message); } }
function periodSql($sql) { global $db; $r=$db->query($sql); periodCheck((bool)$r,$db->lasterror()); return $r; }
$failing=new class {
    public $rolled=false;
    public function begin() { return 1; }
    public function prefix() { return MAIN_DB_PREFIX; }
    public function escape($s) { return $s; }
    public function plimit($n) { return ' LIMIT '.$n; }
    public function query($sql) { return false; }
    public function rollback() { $this->rolled=true; }
};
$failed=false;
try { NorwayInvoicePeriod::report($failing,$conf->entity,'2026-01-01','2026-01-31'); } catch (RuntimeException $error) { $failed=true; }
periodCheck($failed && $failing->rolled,'Read failure must abort, not return an empty period');
$oldEntity=$conf->entity; $conf->entity=987663; $prefix=MAIN_DB_PREFIX; $run=bin2hex(random_bytes(3));
$soc=$db->fetch_object(periodSql('SELECT rowid FROM '.$prefix.'societe ORDER BY rowid LIMIT 1'));
periodCheck((bool)$soc,'Run core invoice workflow first');
$add=static function($date,$entity,$type,$status,$net,$vat,$line=true) use ($db,$prefix,$run,$soc) {
    static $counter=0; $counter++;
    periodSql("INSERT INTO ".$prefix."facture (ref,entity,fk_soc,datef,type,fk_statut,total_ht,total_tva,multicurrency_code) VALUES ('SYN-P-".$run.'-'.$counter."',".$entity.','.(int)$soc->rowid.','.($date===null ? 'NULL' : "'".$date."'").','.$type.','.$status.','.$net.','.$vat.",'NOK')");
    $id=(int)$db->last_insert_id($prefix.'facture');
    if ($line) { periodSql('INSERT INTO '.$prefix.'facturedet (fk_facture,description,total_ht,total_tva,tva_tx,qty,subprice) VALUES ('.$id.",'SYNTHETIC PERIOD ONLY',".$net.','.($type===2 ? -25 : 25).',25,1,'.$net.')'); }
    return $id;
};
$db->begin();
try {
    $first=$add('2099-02-01',987663,0,0,100,25);
    $credit=$add('2099-02-28',987663,2,2,-100,-25);
    $different=$add('2099-02-15',987663,4,1,100,26);
    $missing=$add('2099-02-20',987663,0,3,100,25,false);
    $add('2099-01-31',987663,0,1,100,25); $add('2099-03-01',987663,0,1,100,25);
    $add('2099-02-10',987664,0,1,100,25); $add(null,987663,0,0,100,25);
    $r=NorwayInvoicePeriod::report($db,987663,'2099-02-01','2099-02-28');
    periodCheck(count($r['rows'])===4 && !$r['truncated'],'Date boundaries/entity/all statuses wrong');
    $rows=array_column($r['rows'],null,'id');
    periodCheck($rows[$first]['missing_code']===1 && $rows[$first]['marked']===0, 'Empty stored code not counted');
    periodSql("UPDATE ".$prefix."facturedet SET vat_src_code='SYN25',info_bits=8 WHERE fk_facture=".$first);
    $evidenceRows=array_column(NorwayInvoicePeriod::report($db,987663,'2099-02-01','2099-02-28')['rows'],null,'id');
    periodCheck($evidenceRows[$first]['missing_code']===0 && $evidenceRows[$first]['marked']===1, 'Stored marker/code counts wrong');
    periodCheck($rows[$first]['amount_status']==='Match' && $rows[$first]['status']===0 && $rows[$first]['date']==='2099-02-01','Draft/date missing');
    periodCheck($rows[$credit]['vat']===-25.0 && $rows[$credit]['amount_status']==='Match' && $rows[$credit]['date']==='2099-02-28','Credit signs/date changed');
    periodCheck($rows[$different]['amount_status']==='Different' && $rows[$different]['type']===4,'Proforma/mismatch missing');
    periodCheck($rows[$missing]['amount_status']==='Missing' && $rows[$missing]['status']===3,'Abandoned/empty document missing');
    foreach (array(array('2099-02-30','2099-03-01'),array('2099-03-01','2099-02-01')) as $dates) {
        $rejected=false; try { NorwayInvoicePeriod::report($db,987663,$dates[0],$dates[1]); } catch (InvalidArgumentException $error) { $rejected=true; }
        periodCheck($rejected,'Invalid dates accepted');
    }
    $rejected=false; try { NorwayInvoicePeriod::report($db,987664,'2099-02-01','2099-02-28'); } catch (InvalidArgumentException $error) { $rejected=true; }
    periodCheck($rejected,'Foreign entity accepted');
    periodCheck(NorwayInvoicePeriod::report($db,987663,'2098-01-01','2098-01-01')['rows']===array(),'Empty period misreported');
    for ($i=0;$i<201;$i++) { $add('2098-12-01',987663,0,0,100,25,false); }
    $r=NorwayInvoicePeriod::report($db,987663,'2098-12-01','2098-12-01');
    periodCheck(count($r['rows'])===200 && $r['truncated'],'Truncation not explicit');
    periodCheck((float)$db->fetch_object(periodSql('SELECT total_tva FROM '.$prefix.'facture WHERE rowid='.$different))->total_tva===26.0,'Read-only report changed data');
} finally { $db->rollback(); $conf->entity=$oldEntity; }
print "PASS invoice period boundaries, all statuses/types, credit signs, mismatches, empty/missing data, entity isolation and truncation\n";
