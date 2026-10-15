<?php
// SPDX-License-Identifier: GPL-3.0-or-later
if (PHP_SAPI !== 'cli' || getenv('DOLI_DB_NAME') !== 'accounting'
    || getenv('DOLI_COMPANY_NAME') !== 'SYNTHETIC INSTALLATION ONLY'
    || getenv('NORWAY_EXPECT_EMPTY') !== '1') {
    exit(1);
}
require '/var/www/html/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/norway/class/NorwayChartImport.php';
require_once DOL_DOCUMENT_ROOT.'/custom/norway/class/NorwayVatRegistration.php';
require_once DOL_DOCUMENT_ROOT.'/custom/norway/class/NorwayVatHistory.php';
require_once DOL_DOCUMENT_ROOT.'/custom/norway/class/NorwayWizardProgress.php';
require_once DOL_DOCUMENT_ROOT.'/custom/norway/class/NorwayTaxMappings.php';
require_once DOL_DOCUMENT_ROOT.'/custom/norway/class/NorwaySchema.php';
NorwaySchema::verify($db);
require_once DOL_DOCUMENT_ROOT.'/custom/norway/class/NorwayNumbering.php';
$mappingRows=$db->query("SELECT value FROM ".$db->prefix()."const WHERE entity=".(int)$conf->entity." AND name='".NorwayTaxMappings::SETTING."'");
$mappingRow=$mappingRows ? $db->fetch_object($mappingRows) : null;
$mapping=$mappingRow ? json_decode($mappingRow->value,true) : null;
if (!$mapping) { throw new RuntimeException('Mapping history missing after installation/update/recovery'); }
$mappingState=NorwayTaxMappings::validate($mapping,$conf->entity,'123456785');
if (count($mapping['events'])!==2 || $mapping['events'][0]['target']!=='3' || $mapping['events'][1]['target']!=='31'
    || $mappingState['active'][1]['source']!=='SYNMAP' || $mappingState['active'][1]['target']!=='31') { throw new RuntimeException('Mapping correction history lost'); }
$registration=NorwayVatRegistration::read($db,$conf->entity,'123456785');
if ($registration['status']!=='Recorded' || $registration['start']!=='2024-02-29') { throw new RuntimeException('Confirmed registration date lost'); }
foreach (array('ACCOUNTING_ACCOUNT_CUSTOMER'=>'1500','ACCOUNTING_ACCOUNT_SUPPLIER'=>'2400') as $key=>$value) {
    if (getDolGlobalString($key)!==$value) { throw new RuntimeException('Confirmed default lost: '.$key); }
}
foreach (array('SALG'=>2,'KJOP'=>3,'BANK'=>4,'DIV'=>1) as $code=>$nature) {
    $journal=$db->query("SELECT nature,active FROM ".$db->prefix()."accounting_journal WHERE entity=".(int)$conf->entity." AND code='".$code."'");
    $journal=$journal ? $db->fetch_object($journal) : null;
    if (!$journal || (int)$journal->nature!==$nature || (int)$journal->active!==1) { throw new RuntimeException('Confirmed journal lost: '.$code); }
}
if (getenv('NORWAY_CREATE_SENTINEL') === '1') {
    $previousChart = getDolGlobalString('CHARTOFACCOUNTS');
    if ($user->fetch('', getenv('DOLI_ADMIN_LOGIN')) <= 0 || !$user->admin
        || !$db->query("INSERT INTO ".$db->prefix()."accounting_system (fk_country,pcg_version,label,active) VALUES (173,'SYN-BACKUP','Synthetic backup chart',1)")) {
        throw new RuntimeException('Synthetic chart backup setup failed');
    }
    $chart = $db->last_insert_id($db->prefix().'accounting_system');
    if (dolibarr_set_const($db, 'CHARTOFACCOUNTS', $chart, 'chaine', 0, '', $conf->entity) <= 0) { exit(1); }
    $csv = "account_number;label\n8891;Synthetic imported backup account";
    $report = NorwayChartPreview::report($db, $conf->entity, $chart, $csv);
    if (NorwayChartImport::apply($db, $conf->entity, $chart, $csv,
        NorwayChartImport::fingerprint($conf->entity, $chart, $report), $user) !== 1) { exit(1); }
    if (dolibarr_set_const($db, 'CHARTOFACCOUNTS', $previousChart, 'chaine', 0, '', $conf->entity) <= 0) { exit(1); }
    if (dolibarr_set_const($db, 'NORWAY_MIGRATION_SENTINEL', 'synthetic-only', 'chaine', 0, '', $conf->entity) <= 0
        || file_put_contents('/var/www/documents/norway-backup-marker', "synthetic backup marker\n") === false) {
        exit(1);
    }
} elseif (getDolGlobalString('NORWAY_MIGRATION_SENTINEL') !== 'synthetic-only'
    || file_get_contents('/var/www/documents/norway-backup-marker') !== "synthetic backup marker\n") {
    file_put_contents('php://stderr', "Synthetic database/document content was not preserved.\n");
    exit(1);
}
$numberingInput=NorwayNumbering::input('F',1201,'K',501,'T',301,1,1,'K','L');
if (getenv('NORWAY_CREATE_SENTINEL') === '1') {
    $preview=NorwayNumbering::preview($db,(int)$conf->entity,$numberingInput,$user);
    NorwayNumbering::save($db,(int)$conf->entity,$numberingInput,$preview['fingerprint'],$user);
}
if (NorwayNumbering::record($db,(int)$conf->entity)['input']!==$numberingInput
    || NorwayNumbering::record($db,(int)$conf->entity,'proposal')['input']!==$numberingInput
    || NorwayNumbering::next($db,(int)$conf->entity,$numberingInput,'invoice')!=='F001201'
    || NorwayNumbering::next($db,(int)$conf->entity,$numberingInput,'credit')!=='K000501'
    || NorwayNumbering::next($db,(int)$conf->entity,$numberingInput,'proposal')!=='T000301'
    || NorwayNumbering::next($db,(int)$conf->entity,$numberingInput,'customer')!=='K00001'
    || NorwayNumbering::next($db,(int)$conf->entity,$numberingInput,'supplier')!=='L00001') { throw new RuntimeException('Numbering/start offsets lost through update/restore'); }
if (getenv('NORWAY_CREATE_SENTINEL') === '1') {
    foreach (array(NorwayVatHistory::input(1,'2024-03-01','2025-12-31','Synthetic documented date correction',false),
        NorwayVatHistory::input(0,'2026-02-01','','Synthetic new documented registration',false)) as $historyInput) {
        $historyPreview=NorwayVatHistory::preview($db,(int)$conf->entity,'123456785',$historyInput);
        NorwayVatHistory::save($db,(int)$conf->entity,'123456785',$historyInput,NorwayVatHistory::fingerprint($historyPreview),$user);
    }
}
$history=NorwayVatHistory::read($db,(int)$conf->entity,'123456785');
if ($history['status']!=='History' || $history['revision']!==3 || $history['record']['events'][0]['start']!=='2024-02-29'
    || $history['active'][1]['start']!=='2024-03-01' || $history['active'][1]['end']!=='2025-12-31'
    || $history['active'][2]['start']!=='2026-02-01'
    || NorwayVatHistory::compare($history,'2026-01-31')['status']!=='HistoryGap') {
    throw new RuntimeException('Registration history lost through update/restore');
}
$syntheticPayload='{"synthetic":true,"purpose":"backup-restore-only","version":1}';
$syntheticHash=hash('sha256',$syntheticPayload);
if (getenv('NORWAY_CREATE_SENTINEL') === '1') {
    $sql="INSERT INTO ".$db->prefix()."norway_tax_evidence (entity,document_type,fk_document,revision,captured_at,fk_user,mapping_revision,payload,payload_sha256) VALUES (".(int)$conf->entity.",'synthetic_backup',987665,1,UTC_TIMESTAMP(),".(int)$user->id.",2,'".$db->escape($syntheticPayload)."','".$syntheticHash."')";
    if (!$db->query($sql)) { throw new RuntimeException('Synthetic evidence backup fixture failed'); }
}
$r=$db->query("SELECT payload,payload_sha256 FROM ".$db->prefix()."norway_tax_evidence WHERE entity=".(int)$conf->entity." AND document_type='synthetic_backup' AND fk_document=987665 AND revision=1");
$evidence=$r ? $db->fetch_object($r) : null;
if (!$evidence || $evidence->payload!==$syntheticPayload || $evidence->payload_sha256!==$syntheticHash) { throw new RuntimeException('Synthetic evidence payload lost through update/restore'); }
$work = NorwayWizardProgress::read($db, $conf->entity, 1, '123456785');
if (getenv('NORWAY_CREATE_SENTINEL') === '1') {
    NorwayWizardProgress::save($db, $conf->entity, 1, 'Waiting', '123456785', $work['fingerprint'], $user);
    $work = NorwayWizardProgress::read($db, $conf->entity, 1, '123456785');
}
if ($work['state'] !== 'Waiting' || $work['stale']) { throw new RuntimeException('Wizard work status lost'); }
$result = $db->query("SELECT label,active FROM ".$db->prefix()."accounting_account WHERE entity=".(int) $conf->entity." AND fk_pcg_version='SYN-BACKUP' AND account_number='8891'");
$account = $result ? $db->fetch_object($result) : false;
if (!$account || $account->label !== 'Synthetic imported backup account' || (int) $account->active !== 1) {
    throw new RuntimeException('Imported chart account was not preserved by backup/recovery');
}
$result = $db->query("SELECT COUNT(*) AS total FROM ".$db->prefix()."accounting_account WHERE entity=".(int) $conf->entity." AND fk_pcg_version='NORWAY-NO-1' AND active=1");
if (!$result || (int) $db->fetch_object($result)->total !== 745) { throw new RuntimeException('Norwegian chart was not preserved'); }
print "PASS synthetic persistent content, Norwegian chart and imported chart account\n";
require_once DOL_DOCUMENT_ROOT.'/custom/norway/class/NorwayPostingEvidence.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/accountancy/class/bookkeeping.class.php';
function salesSentinelQuery($db,$sql){$r=$db->query($sql);if(!$r){throw new RuntimeException('Synthetic documentary restore query: '.$db->lasterror());}return $r;}
function salesSentinel($db,$entity,$user,$create)
{
    $p=$db->prefix();$sql=static fn($q)=>salesSentinelQuery($db,$q);
    if($create){
        $saved=$db->fetch_object($sql("SELECT value FROM ".$p."const WHERE entity=".$entity." AND name='NORWAY_TAX_MAPPINGS_V1'"))->value;
        $rule=array('revision'=>1,'user'=>(int)$user->id,'at'=>'2026-01-01T00:00:00+00:00','source'=>'SYNRETAIN','target'=>'5','start'=>'2026-01-01','end'=>'2026-12-31','direction'=>'sale','id'=>1,'retire'=>false);
        $record=array('version'=>1,'entity'=>$entity,'org'=>'123456785','reference'=>NorwayTaxReference::SOURCE,'events'=>array($rule));
        try{
            $sql("INSERT INTO ".$p."accounting_fiscalyear (label,date_start,date_end,statut,entity,datec) VALUES ('Synthetic documentary period','2026-01-01','2026-12-31',0,".$entity.",NOW())");
            $period=(int)$db->last_insert_id($p.'accounting_fiscalyear');
            if(dolibarr_set_const($db,'ACCOUNTANCY_FISCALYEAR_DEFAULT',$period,'chaine',0,'',$entity)<=0){throw new RuntimeException('Synthetic documentary active period');}
            $sql("UPDATE ".$p."const SET value='".$db->escape(json_encode($record))."' WHERE entity=".$entity." AND name='NORWAY_TAX_MAPPINGS_V1'");
            $country=(int)$db->fetch_object($sql("SELECT rowid FROM ".$p."c_country WHERE code='NO'"))->rowid;
            $sql("INSERT INTO ".$p."societe (nom,entity,fk_pays) VALUES ('SYNTHETIC DOCUMENTARY RESTORE ONLY',".$entity.','.$country.')');$party=(int)$db->last_insert_id($p.'societe');
            $sql("INSERT INTO ".$p."facture (ref,entity,type,fk_statut,fk_soc,datef,multicurrency_code,multicurrency_tx,total_ht,total_tva,total_ttc) VALUES ('SYN-DOCUMENTARY-BACKUP',".$entity.",0,1,".$party.",'2026-01-01','NOK',1,100,0,100)");$id=(int)$db->last_insert_id($p.'facture');
            $sql("INSERT INTO ".$p."facturedet (fk_facture,description,product_type,qty,subprice,tva_tx,vat_src_code,info_bits,localtax1_tx,localtax2_tx,total_ht,total_tva,total_ttc,total_localtax1,total_localtax2) VALUES (".$id.",'SYNTHETIC DOCUMENTARY BACKUP ONLY',0,1,100,0,'SYNRETAIN',0,0,0,100,0,100,0,0)");$line=(int)$db->last_insert_id($p.'facturedet');
            $input=array(array('line'=>$line,'target'=>'5','reason'=>'Synthetic retained reason before posting','reference'=>'SYN-DOCUMENT-001'));
            $preview=NorwaySalesTaxBasis::preview($db,$entity,$id,$user,$input);NorwaySalesTaxBasis::save($db,$entity,$id,$user,$input,$preview['fingerprint']);
            $db->begin();foreach(array('1500'=>array(100,0),'3100'=>array(0,100)) as $account=>$amount){
                $o=new BookKeeping($db);$o->entity=$entity;$o->doc_date=strtotime('2026-01-01');$o->doc_type='customer_invoice';$o->doc_ref='SYN-DOCUMENTARY-BACKUP';$o->fk_doc=$id;$o->fk_docdet=0;$o->numero_compte=(string)$account;$o->label_compte='Synthetic retention';$o->label_operation='Synthetic';$o->debit=$amount[0];$o->credit=$amount[1];$o->montant=$amount[0]-$amount[1];$o->sens=$amount[0]?'D':'C';$o->code_journal='SYNRETAIN';$o->journal_label='Synthetic';$o->piece_num=987790;$o->ref='SYNRETAIN';
                if($o->createStd($user)<=0){throw new RuntimeException('Synthetic native documentary backup capture: '.$o->errorsToString());}
            }$db->commit();
            $input[0]['reason']='Synthetic later reason retained independently';$input[0]['reference']='SYN-DOCUMENT-002';$preview=NorwaySalesTaxBasis::preview($db,$entity,$id,$user,$input);NorwaySalesTaxBasis::save($db,$entity,$id,$user,$input,$preview['fingerprint']);
        }finally{
            $db->rollback();$sql("UPDATE ".$p."const SET value='".$db->escape($saved)."' WHERE entity=".$entity." AND name='NORWAY_TAX_MAPPINGS_V1'");
        }
    }
    $invoice=$db->fetch_object($sql("SELECT rowid FROM ".$p."facture WHERE entity=".$entity." AND ref='SYN-DOCUMENTARY-BACKUP'"));if(!$invoice){throw new RuntimeException('Documentary invoice lost');}$id=(int)$invoice->rowid;
    $history=NorwaySalesTaxBasis::history($db,$entity,$id,$user);
    if(count($history)!==2 || $history[0]['data']['choices'][0]['reference']!=='SYN-DOCUMENT-001' || $history[1]['data']['choices'][0]['reference']!=='SYN-DOCUMENT-002'){throw new RuntimeException('Documentary revisions lost');}
    $observations=NorwayPostingEvidence::report($db,$entity,$id,$user);if(count($observations)!==2){throw new RuntimeException('Documentary observations lost');}
    foreach($observations as $entry){$context=$entry['data']['sales_basis_context'] ?? null;
        if($entry['data']['version']!==9 || !$context || $context['status']!=='Linked' || $context['assessment']['data']['revision']!==1 || $context['assessment']['data']!==$history[0]['data']){throw new RuntimeException('Documentary posting link lost or backfilled');}
    }
    print "PASS persistent documentary revisions and native v9 links survive update/restore without backfill\n";
}

if ($user->fetch('',getenv('DOLI_ADMIN_LOGIN'))<=0) { throw new RuntimeException('Synthetic retention reader'); }$user->loadRights('',1);
salesSentinel($db,(int)$conf->entity,$user,getenv('NORWAY_CREATE_SENTINEL')==='1');
$db->close();
