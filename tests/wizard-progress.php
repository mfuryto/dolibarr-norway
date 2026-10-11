<?php
// SPDX-License-Identifier: GPL-3.0-or-later
if (PHP_SAPI !== 'cli' || getenv('NORWAY_SYNTHETIC_TEST') !== '1' || getenv('DOLI_DB_NAME') !== 'norway_test') { exit(1); }
require '/var/www/html/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/norway/class/NorwayWizardProgress.php';
function progressCheck($ok, $message) { if (!$ok) { throw new RuntimeException($message); } }
function rejected($callback) { try { $callback(); } catch (Throwable $error) { return; } throw new RuntimeException('Unsafe progress write accepted'); }
$oldEntity=$conf->entity; $entity=987662; $conf->entity=$entity;
progressCheck($user->fetch('', 'testadmin')>0 && $user->admin, 'Test admin missing');
$db->begin();
try {
    progressCheck($db->query('DELETE FROM '.MAIN_DB_PREFIX.'const WHERE entity='.$entity)!==false, 'Fixture failed');
    $before=$db->fetch_object($db->query('SELECT COUNT(*) AS n FROM '.MAIN_DB_PREFIX.'facture'))->n;
    $r=NorwayWizardProgress::read($db,$entity,1,'123456785');
    progressCheck($r['state']==='NotStarted' && !$r['stale'], 'Default progress wrong');
    $nonadmin=clone $user; $nonadmin->admin=0;
    rejected(fn()=>NorwayWizardProgress::save($db,$entity,1,'Reviewed','123456785',$r['fingerprint'],$nonadmin));
    rejected(fn()=>NorwayWizardProgress::save($db,$entity,1,'Approved','123456785',$r['fingerprint'],$user));
    rejected(fn()=>NorwayWizardProgress::read($db,$entity+1,1,'123456785'));
    rejected(fn()=>NorwayWizardProgress::read($db,$entity,12,'123456785'));
    foreach (NorwayWizardProgress::states() as $state) {
        $r=NorwayWizardProgress::read($db,$entity,1,'123456785');
        NorwayWizardProgress::save($db,$entity,1,$state,'123456785',$r['fingerprint'],$user);
        progressCheck(NorwayWizardProgress::read($db,$entity,1,'123456785')['state']===$state, 'State not persisted');
    }
    progressCheck(NorwayWizardProgress::read($db,$entity,2,'123456785')['state']==='NotStarted','Cross-step leak');
    progressCheck(NorwayWizardProgress::read($db,$entity,1,'000000000')['stale'],'Changed organisation not flagged');
    $r=NorwayWizardProgress::read($db,$entity,1,'123456785');
    NorwayWizardProgress::save($db,$entity,1,'Waiting','123456785',$r['fingerprint'],$user);
    rejected(fn()=>NorwayWizardProgress::save($db,$entity,1,'Reviewed','123456785',$r['fingerprint'],$user));
    progressCheck(NorwayWizardProgress::read($db,$entity,1,'123456785')['state']==='Waiting','Stale save overwrote status');
    $conf->entity=$entity+1;
    progressCheck(NorwayWizardProgress::read($db,$entity+1,1,'123456785')['state']==='NotStarted','Cross-entity progress leak');
    $conf->entity=$entity;
    progressCheck($db->query("UPDATE ".MAIN_DB_PREFIX."const SET value='invalid' WHERE entity=".$entity." AND name='NORWAY_WIZARD_STEP_1'")!==false,'Corrupt fixture failed');
    rejected(fn()=>NorwayWizardProgress::read($db,$entity,1,'123456785'));
    progressCheck($db->fetch_object($db->query('SELECT COUNT(*) AS n FROM '.MAIN_DB_PREFIX.'facture'))->n===$before,'Invoice data changed');
    $r=$db->query('SELECT name FROM '.MAIN_DB_PREFIX.'const WHERE entity='.$entity);
    while ($row=$db->fetch_object($r)) { progressCheck($row->name==='NORWAY_WIZARD_STEP_1','Company setting changed'); }
} finally { $db->rollback(); $conf->entity=$oldEntity; unset($conf->global->NORWAY_WIZARD_STEP_1); }
print "PASS wizard work markers, admin/entity/step isolation, changed identity, invalid data and stale-write protection\n";
