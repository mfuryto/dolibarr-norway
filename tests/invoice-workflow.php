<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// Exercise the real core invoice and sales-journal workflow in a disposable DB.
if (PHP_SAPI !== 'cli' || getenv('NORWAY_SYNTHETIC_TEST') !== '1'
    || getenv('DOLI_DB_NAME') !== 'norway_test'
    || getenv('DOLI_COMPANY_NAME') !== 'SYNTHETIC NORWAY TEST ONLY') {
    exit(1);
}
require '/var/www/html/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/accountancy/class/accountingaccount.class.php';
require_once DOL_DOCUMENT_ROOT.'/comm/propal/class/propal.class.php';
function insist($condition, $message)
{
    if (!$condition) { throw new RuntimeException($message); }
}
function sql($statement)
{
    global $db;
    $result = $db->query($statement);
    insist((bool) $result, $db->lasterror());
    return $result;
}
function setting($name, $value)
{
    global $db, $conf;
    insist(dolibarr_set_const($db, $name, $value, 'chaine', 0, '', $conf->entity) > 0, 'Setting failed');
    $conf->global->$name = $value;
}
insist($user->fetch('', 'testadmin') > 0 && $user->admin, 'Synthetic administrator missing');
foreach (array('modSociete', 'modFacture', 'modPropale', 'modBanque') as $module) {
    $result = activateModule($module);
    insist(empty($result['errors']), 'Module activation failed');
}
$conf->setValues($db);
$user->loadRights('', 1);
$run = bin2hex(random_bytes(4));
// Populate the bank dashboard so cheque assertions cannot pass on an empty box.
require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';
$bank = new Account($db);
$bank->ref = 'SYN-'.$run;
$bank->label = 'SYNTHETIC BANK ONLY '.$run;
$bank->country_id = 173;
$bank->currency_code = 'NOK';
$bank->courant = 1;
$bank->rappro = 1;
$bank->date_solde = dol_mktime(12, 0, 0, 10, 1, 2026);
$bank->balance = 0;
insist($bank->create($user) > 0, 'Synthetic bank account creation failed: '.$bank->error);
insist($bank->countAccountToReconcile() > 0, 'Bank dashboard fixture missing');

setting('MAIN_INFO_SOCIETE_COUNTRY', '173:NO:Norway');
setting('MAIN_MONNAIE', 'NOK');
$conf->currency = 'NOK';
setting('MAIN_MULTILANGS', '1');
setting('PROPALE_ADDON', 'mod_propale_marbre');
setting('ACCOUNTANCY_FISCAL_PERIOD_MODE', 'blockedonclosed');
$mysoc->country_id = 173;
$mysoc->country_code = 'NO';
$mysoc->tva_assuj = 1;
$mysoc->idprof1 = '123456785';
$mysoc->address = 'Synthetic address';
$mysoc->zip = '0000';
$mysoc->town = 'Synthetic';
sql("INSERT INTO ".$db->prefix()."accounting_system (fk_country,pcg_version,label,active) VALUES (173,'SYN-".$run."','Synthetic test chart, not a production chart',1)");
$chart = $db->last_insert_id($db->prefix().'accounting_system');
setting('CHARTOFACCOUNTS', $chart);
$accounts = array();
foreach (array('1500', '3000', '2700') as $number) {
    $account = new AccountingAccount($db);
    $account->fk_pcg_version = 'SYN-'.$run;
    $account->account_number = $number;
    $account->label = 'Synthetic '.$number;
    $account->active = 1;
    insist($account->create($user) > 0, 'Synthetic account creation failed: '.$account->error);
    $accounts[$number] = $account->id;
}
setting('ACCOUNTING_ACCOUNT_CUSTOMER', '1500');
setting('ACCOUNTING_SERVICE_SOLD_ACCOUNT', '3000');
setting('ACCOUNTING_PRODUCT_SOLD_ACCOUNT', '3000');
setting('ACCOUNTING_VAT_SOLD_ACCOUNT', '2700');
sql("INSERT INTO ".$db->prefix()."accounting_journal (entity,code,label,nature,active) VALUES (".(int) $conf->entity.",'SYN-".$run."','Synthetic sales',2,1)");
$journal = $db->last_insert_id($db->prefix().'accounting_journal');
$customer = new Societe($db);
$customer->name = 'SYNTHETIC CUSTOMER ONLY';
$customer->client = 1;
$customer->idprof1 = '123456785';
$customer->code_client = 'SYN-'.$run;
$customer->code_compta = 'SYN-RESK';
$customer->country_id = 173;
$customer->tva_assuj = 1;
insist($customer->create($user) > 0, 'Customer creation failed: '.$customer->error);
$invoices = array();
foreach (array(array('SYN-SALE-25', 1000, 25, 0), array('SYN-SALE-0', 600, 0, 0), array('SYN-CREDIT-25', 200, 25, 2), array('SYN-SALE-NOK', 1000, 25, 0)) as $fixture) {
    $invoice = new Facture($db);
    $invoice->socid = $customer->id;
    $invoice->date = dol_mktime(12, 0, 0, 10, 1, 2026);
    $invoice->type = $fixture[3];
    $invoice->multicurrency_code = 'NOK';
    $invoice->multicurrency_tx = 1;
    if ($invoice->type === 2) { $invoice->fk_facture_source = $invoices[0]->id; }
    insist($invoice->create($user) > 0, 'Invoice creation failed: '.$invoice->error);
    insist($invoice->addline('Synthetic software licence', $fixture[1], 1, $fixture[2], 0, 0, 0, 0, '', '', $accounts['3000'], 0, 0, 'HT', 0, 1) > 0, 'Invoice line failed: '.$invoice->error);
    if (!$invoices) {
        $expectRejected = static function ($expectedKey) use ($invoice, $user, $db, $langs) {
            $before = $db->fetch_object(sql('SELECT ref,fk_statut FROM '.MAIN_DB_PREFIX.'facture WHERE rowid='.(int) $invoice->id));
            insist($invoice->validate($user) < 0, 'Invalid invoice validation succeeded: '.$expectedKey);
            insist(strpos(html_entity_decode($invoice->error, ENT_QUOTES, 'UTF-8'), html_entity_decode($langs->trans('NorwayInvoice'.$expectedKey), ENT_QUOTES, 'UTF-8')) !== false, 'Wrong validation rejection reason: '.$expectedKey);
            $after = $db->fetch_object(sql('SELECT ref,fk_statut FROM '.MAIN_DB_PREFIX.'facture WHERE rowid='.(int) $invoice->id));
            insist((int) $after->fk_statut === 0 && $after->ref === $before->ref, 'Rejected invoice changed status/reference');
            insist($invoice->fetch($invoice->id) > 0, 'Rejected invoice could not reload');
        };
        $mysoc->idprof1 = '123456786';
        $expectRejected('SellerOrganisation');
        $mysoc->idprof1 = '123456785';
        $mysoc->tva_assuj = 0;
        $expectRejected('UnregisteredVAT');
        $mysoc->tva_assuj = 1;
        sql("UPDATE ".MAIN_DB_PREFIX."societe SET siren='',address='' WHERE rowid=".(int) $customer->id);
        $expectRejected('Customer');
        sql("UPDATE ".MAIN_DB_PREFIX."societe SET siren='123456785' WHERE rowid=".(int) $customer->id);
        sql("UPDATE ".MAIN_DB_PREFIX."facturedet SET description='',label='' WHERE fk_facture=".(int) $invoice->id);
        $expectRejected('Descriptions');
        sql("UPDATE ".MAIN_DB_PREFIX."facturedet SET description='Synthetic software licence' WHERE fk_facture=".(int) $invoice->id);
        insist($invoice->fetch($invoice->id) > 0, 'Invoice reload after corrections failed');
        print "PASS rejected invoice validation rolls back status/reference and corrected draft can proceed\n";
    }
    $registrationBefore=null;
    if (!$invoices) {
        $registrationBefore=$db->fetch_object(sql("SELECT value FROM ".MAIN_DB_PREFIX."const WHERE entity=".(int)$conf->entity." AND name='NORWAY_VAT_REGISTRATION_START'"));
        setting('NORWAY_VAT_REGISTRATION_START',json_encode(array('version'=>1,'entity'=>(int)$conf->entity,'org'=>'123456785','start'=>'2027-01-01','confirmed_by'=>(int)$user->id,'confirmed_at'=>gmdate('c'))));
    }
    unset($_SESSION['dol_events']['warnings']);
    try {
    insist($invoice->validate($user) > 0, 'Invoice validation failed: '.$invoice->error);
        if (!$invoices || (int)$invoice->type===2) {
            $warning=$langs->trans('NorwayRegistrationWarning'.((int)$invoice->type===2 ? 'Credit' : 'Before'));
            insist(in_array($warning,$_SESSION['dol_events']['warnings'] ?? array(),true),'Native invoice validation omitted actionable registration warning');
        }
    } finally {
        if (!$invoices) {
            if ($registrationBefore) { setting('NORWAY_VAT_REGISTRATION_START',$registrationBefore->value); }
            else { sql("DELETE FROM ".MAIN_DB_PREFIX."const WHERE entity=".(int)$conf->entity." AND name='NORWAY_VAT_REGISTRATION_START'");unset($conf->global->NORWAY_VAT_REGISTRATION_START); }
        }
    }

    insist($invoice->fetch($invoice->id) > 0, 'Invoice reload failed');
    $invoices[] = $invoice;
}
require_once DOL_DOCUMENT_ROOT.'/custom/norway/class/NorwayInvoiceAmounts.php';
foreach ($invoices as $storedInvoice) {
    insist(NorwayInvoiceAmounts::report($storedInvoice)['status'] === 'Match', 'Stored core invoice/credit totals differ from lines');
}
print "PASS stored core sale, zero-VAT invoice and credit note amount comparison\n";
$evidenceLine=(int)$invoices[0]->lines[0]->id;
$oldEvidence=$db->fetch_object(sql('SELECT vat_src_code,info_bits FROM '.MAIN_DB_PREFIX.'facturedet WHERE rowid='.$evidenceLine));
sql("UPDATE ".MAIN_DB_PREFIX."facturedet SET vat_src_code='SYN<25>',info_bits=8 WHERE rowid=".$evidenceLine);
insist($invoices[0]->fetch($invoices[0]->id)>0,'Evidence invoice reload failed');
$storedEvidence=NorwayInvoiceAmounts::report($invoices[0])['rows'][0]['evidence'];
insist($storedEvidence['code']==='SYN<25>' && $storedEvidence['flags']===8,'Core stored source fields not preserved');
sql("UPDATE ".MAIN_DB_PREFIX."facturedet SET vat_src_code='".$db->escape((string)$oldEvidence->vat_src_code)."',info_bits=".(int)$oldEvidence->info_bits.' WHERE rowid='.$evidenceLine);
insist($invoices[0]->fetch($invoices[0]->id)>0,'Evidence fixture restore failed');
print "PASS core invoice line persisted tax source and marker read without dictionary inference\n";
// Exercise automatic core numbering rather than forced test references.
insist(preg_match('/^(.*?)([0-9]+)$/D', $invoices[0]->ref, $first) === 1
    && preg_match('/^(.*?)([0-9]+)$/D', $invoices[1]->ref, $second) === 1,
    'Automatic references must end with a sequence');
insist($first[1] === $second[1] && (int) $second[2] === (int) $first[2] + 1,
    'Consecutive sales invoices did not receive consecutive automatic numbers');
insist(count(array_unique(array_map(static function ($invoice) { return $invoice->ref; }, $invoices))) === count($invoices),
    'Duplicate invoice or credit note reference');
require_once DOL_DOCUMENT_ROOT.'/custom/norway/class/NorwayInvoiceCheck.php';
insist(NorwayInvoiceCheck::evaluate($invoices[2], $mysoc, $customer, $invoices[0])['CreditSource'] === 'Present',
    'Real core credit note source not recognised');
print "PASS automatic consecutive invoice numbering and unique credit note reference\n";
// A forged cheque payment must roll back; an ordinary transfer must still work.
require_once DOL_DOCUMENT_ROOT.'/compta/paiement/class/paiement.class.php';
$cheque = $db->fetch_object(sql("SELECT id,active FROM ".$db->prefix()."c_paiement WHERE code='CHQ' AND entity=".(int) $conf->entity));
insist($cheque && (int) $cheque->active === 0, 'Cheque option still active');
$transfer = $db->fetch_object(sql("SELECT id FROM ".$db->prefix()."c_paiement WHERE code='VIR' AND active=1 AND entity=".(int) $conf->entity));
insist((bool) $transfer, 'Bank transfer unavailable');
foreach (array((int) $cheque->id, (int) $transfer->id) as $method) {
    $payment = new Paiement($db);
    $payment->datepaye = $invoices[0]->date;
    $payment->paiementid = $method;
    $payment->amounts = array($invoices[0]->id => 10);
    $before = (int) $db->fetch_object(sql('SELECT COUNT(*) AS n FROM '.$db->prefix().'paiement'))->n;
    $result = $payment->create($user);
    if ($method === (int) $cheque->id) {
        insist($result < 0, 'Forged cheque payment accepted');
        insist((int) $db->fetch_object(sql('SELECT COUNT(*) AS n FROM '.$db->prefix().'paiement'))->n === $before, 'Rejected cheque payment persisted');
    } else { insist($result > 0, 'Ordinary transfer rejected: '.$payment->error); }
}
// Core trigger dispatch must reject an attempted foreign-currency document.
$invoices[0]->multicurrency_code = 'EUR';
insist($invoices[0]->call_trigger('BILL_VALIDATE', $user) < 0, 'EUR invoice accepted');
$invoices[0]->multicurrency_code = 'NOK';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/paiementfourn.class.php';
$supplierPayment = new PaiementFourn($db);
$supplierPayment->paiementid = (int) $cheque->id;
insist($supplierPayment->call_trigger('PAYMENT_SUPPLIER_CREATE', $user) < 0, 'Supplier cheque payment accepted');
require_once DOL_DOCUMENT_ROOT.'/custom/norway/class/NorwayPolicy.php';
setting('MAIN_INFO_SOCIETE_DEVISE', 'NOK');
$conf->currency = 'EUR';
try { NorwayPolicy::apply($db, $conf); throw new LogicException('Populated EUR accounting silently converted'); }
catch (RuntimeException $expected) {
    insist(strpos($expected->getMessage(), 'Existing financial activity') !== false, 'Wrong currency migration failure');
}
$conf->currency = 'NOK';
insist(getDolGlobalString('MAIN_MONNAIE') === 'NOK', 'Rejected currency migration changed core setting');
print "PASS cheque payment rollback, ordinary bank transfer and NOK-only sales guard\n";
$cookie = tempnam(sys_get_temp_dir(), 'norway-workflow-');
$curl = curl_init();
curl_setopt_array($curl, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEFILE => $cookie,
    CURLOPT_COOKIEJAR => $cookie, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 30));
function http($path, $post = null)
{
    global $curl;
    curl_setopt($curl, CURLOPT_URL, 'http://localhost'.$path);
    curl_setopt($curl, CURLOPT_POST, $post !== null);
    if ($post !== null) { curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $body = curl_exec($curl);
    insist($body !== false && curl_getinfo($curl, CURLINFO_HTTP_CODE) < 400, 'Workflow HTTP request failed');
    return $body;
}
function token($body)
{
    insist(preg_match('/name="token"[^>]*value="([^"]+)"/', $body, $match) === 1, 'CSRF token missing');
    return html_entity_decode($match[1]);
}
function assertPdfLanguage($path, $title, $expected = true)
{
    insist(is_file($path), 'PDF missing');
    $pdf = file_get_contents($path);
    insist(strpos($pdf, '%PDF-') === 0, 'Not a PDF');
    $parts = array();
    // Read the declared byte length. Treating a trailing compressed CR byte as
    // part of the newline delimiter can corrupt zlib data nondeterministically.
    preg_match_all('/\/Length\s+([0-9]+)\s*>>\s*stream(?:\r\n|\n|\r)/', $pdf, $streams, PREG_OFFSET_CAPTURE);
    foreach ($streams[0] as $i => $header) {
        $stream = substr($pdf, $header[1] + strlen($header[0]), (int) $streams[1][$i][0]);
        $decoded = @gzuncompress($stream);
        $parts[] = $decoded !== false ? $decoded : $stream;
    }
    $unicode = mb_convert_encoding($title, 'UTF-16BE', 'UTF-8');
    foreach ($parts as $part) {
        if (strpos($part, $title) !== false || strpos($part, $unicode) !== false
            || stripos($part, bin2hex($unicode)) !== false) {
            insist($expected, 'Unexpected PDF text: '.$title);
            return;
        }
    }
    if ($expected) { throw new RuntimeException('Expected PDF text missing: '.$title); }
}
// Deterministic regression: a valid zlib stream whose final checksum byte is CR.
$fixture = tempnam(sys_get_temp_dir(), 'norway-pdf-stream-');
try {
    $compressed = '';
    for ($i = 0; $i < 256; $i++) {
        $compressed = gzcompress('Norske kroner '.str_repeat('a', $i));
        if (substr($compressed, -1) === "\r") { break; }
    }
    insist(substr($compressed, -1) === "\r", 'Compressed stream regression fixture not produced');
    file_put_contents($fixture, "%PDF-1.7\n<</Filter /FlateDecode /Length ".strlen($compressed).">> stream\n".$compressed."\nendstream\n");
    assertPdfLanguage($fixture, 'Norske kroner');
} finally { unlink($fixture); }
try {
    $body = http('/index.php');
    http('/index.php', array('token' => token($body), 'actionlogin' => 'login', 'loginfunction' => 'loginfunction',
        'username' => 'testadmin', 'password' => getenv('DOLI_ADMIN_PASSWORD')));
    foreach (array('nb_NO', 'en_US') as $language) {
        $body = http('/custom/norway/admin/invoice.php?id='.$invoices[0]->id.'&lang='.$language);
        insist(strpos($body, '/compta/facture/card.php?facid='.$invoices[0]->id) !== false,
            'Invoice check page could not load a real invoice');
        $text = html_entity_decode($body, ENT_QUOTES, 'UTF-8');
        insist(strpos($text, 'Må gjennomgås') !== false || strpos($text, 'Review required') !== false,
            'Invoice check omitted mandatory manual review');
    }
    $body = http('/custom/norway/admin/invoice.php');
    insist(strpos($body, '/norway/admin/invoice.php?id='.$invoices[0]->id) !== false,
        'Recent invoice selector missing');
    // Prove entity isolation through HTTP; restore this synthetic invoice afterwards.
    sql('UPDATE '.MAIN_DB_PREFIX.'facture SET entity=99999 WHERE rowid='.(int) $invoices[0]->id);
    try {
        $body = http('/custom/norway/admin/invoice.php?id='.$invoices[0]->id);
        insist(strpos($body, '/compta/facture/card.php?facid='.$invoices[0]->id) === false,
            'Invoice check exposed an invoice belonging to another entity');
        insist(strpos($body, 'Fakturaen kunne ikke leses i aktivt foretak.') !== false
            || strpos($body, 'Invoice could not be read in the active entity.') !== false,
            'Wrong-entity invoice was not rejected');
    } finally {
        sql('UPDATE '.MAIN_DB_PREFIX.'facture SET entity='.(int) $conf->entity.' WHERE rowid='.(int) $invoices[0]->id);
    }
    print "PASS authenticated bilingual invoice checks and entity isolation\n";
    $params = array('id_journal' => $journal, 'date_startday' => 1, 'date_startmonth' => 1, 'date_startyear' => 2026,
        'date_endday' => 31, 'date_endmonth' => 12, 'date_endyear' => 2026, 'in_bookkeeping' => 'notyet');
    $path = '/accountancy/journal/sellsjournal.php?'.http_build_query($params);
    $body = http($path);
    http($path, $params + array('token' => token($body), 'action' => 'writebookkeeping'));
    $expected = array(array('1500' => 125000, '3000' => -100000, '2700' => -25000),
        array('1500' => 60000, '3000' => -60000), array('1500' => -25000, '3000' => 20000, '2700' => 5000),
        array('1500' => 125000, '3000' => -100000, '2700' => -25000));
    foreach ($invoices as $i => $invoice) {
        $rows = sql("SELECT numero_compte,debit,credit,piece_num,doc_ref,fk_docdet FROM ".$db->prefix()."accounting_bookkeeping WHERE entity=".(int) $conf->entity." AND doc_type='customer_invoice' AND fk_doc=".(int) $invoice->id);
        $actual = array();
        while ($row = $db->fetch_object($rows)) {
            insist($row->doc_ref === $invoice->ref && (int) $row->piece_num > 0, 'Document reference missing');
            $actual[$row->numero_compte] = ($actual[$row->numero_compte] ?? 0) + (int) round(((float) $row->debit - (float) $row->credit) * 100);
        }
        ksort($actual); ksort($expected[$i]);
        insist($actual === $expected[$i], 'Invoice ledger mismatch: '.$invoice->ref.' '.json_encode($actual));
        insist(array_sum($actual) === 0, 'Invoice voucher does not balance');
        $events = sql("SELECT e.payload,e.payload_sha256 FROM ".$db->prefix()."norway_tax_evidence e JOIN ".$db->prefix()."accounting_bookkeeping b ON b.rowid=e.fk_document AND b.entity=e.entity WHERE e.document_type='bookkeeping_event' AND b.doc_type='customer_invoice' AND b.entity=".(int) $conf->entity." AND b.fk_doc=".(int) $invoice->id);
        $eventCount = 0;
        while ($event = $db->fetch_object($events)) {
            $payload = json_decode($event->payload, true);
            insist(hash('sha256', $event->payload) === $event->payload_sha256
                && ($payload['basis']['posting']['fk_doc'] ?? 0) == $invoice->id
                && ($payload['basis']['source_complete'] ?? false) === true,
                'Native sales journal did not preserve a complete source observation');
            $eventCount++;
        }
        insist($eventCount === $db->num_rows($rows), 'Native sales journal omitted posting source observations');
    }
    $count = (int) $db->fetch_object(sql("SELECT COUNT(*) AS n FROM ".$db->prefix()."accounting_bookkeeping WHERE doc_type='customer_invoice' AND entity=".(int) $conf->entity))->n;
    $body = http($path);
    http($path, $params + array('token' => token($body), 'action' => 'writebookkeeping'));
    insist((int) $db->fetch_object(sql("SELECT COUNT(*) AS n FROM ".$db->prefix()."accounting_bookkeeping WHERE doc_type='customer_invoice' AND entity=".(int) $conf->entity))->n === $count, 'Repeated transfer duplicated postings');
    $mysoc->idprof1 = '123456785'; // Artificial checksum fixture, no real business.
    $mysoc->tva_assuj = 1;
    $identityBefore = array($mysoc->idprof1, $mysoc->tva_assuj, $mysoc->tva_intra);
    foreach (array('nb_NO', 'en_US') as $language) {
        $outputlangs = new Translate('', $conf);
        $outputlangs->setDefaultLang($language);
        $outputlangs->loadLangs(array('main', 'bills', 'propal'));
        foreach (array($invoices[0], $invoices[2], $invoices[3]) as $invoice) {
            insist($invoice->generateDocument('sponge', $outputlangs) > 0, 'Invoice PDF failed: '.$invoice->error);
            $pdf = $conf->facture->dir_output.'/'.dol_sanitizeFileName($invoice->ref).'/'.dol_sanitizeFileName($invoice->ref).'.pdf';
            assertPdfLanguage($pdf, $outputlangs->transnoentities((int) $invoice->type === 2 ? 'InvoiceAvoir' : 'PdfInvoiceTitle'));
            assertPdfLanguage($pdf, $outputlangs->transnoentitiesnoconv('Currency'.$invoice->multicurrency_code));
            assertPdfLanguage($pdf, '123 456 785 MVA');
        }
        foreach (array('NOK') as $currency) {
            $proposal = new Propal($db);
            $proposal->socid = $customer->id;
            $proposal->date = dol_mktime(12, 0, 0, 10, 1, 2026);
            $proposal->duree_validite = 30;
            $proposal->multicurrency_code = $currency;
            $proposal->multicurrency_tx = 1;
            insist($proposal->create($user) > 0, 'Proposal creation failed: '.$proposal->error);
            insist($proposal->addline('Synthetic software licence', 1000, 1, 25, 0, 0, 0, 0, 'HT', 0, 0, 1) > 0, 'Proposal line failed');
            insist($proposal->valid($user) > 0, 'Proposal validation failed: '.$proposal->error);
            insist($proposal->fetch($proposal->id) > 0, 'Proposal reload failed');
            insist(abs((float) $proposal->total_ht - 1000) < 0.001, 'Proposal base amount changed');
            insist($proposal->generateDocument('azur', $outputlangs) > 0, 'Proposal PDF failed');
            $pdf = $conf->propal->dir_output.'/'.dol_sanitizeFileName($proposal->ref).'/'.dol_sanitizeFileName($proposal->ref).'.pdf';
            assertPdfLanguage($pdf, $outputlangs->transnoentities('PdfCommercialProposalTitle'));
            assertPdfLanguage($pdf, $outputlangs->transnoentitiesnoconv('Currency'.$currency));
            assertPdfLanguage($pdf, '123 456 785 MVA');
            $mysoc->tva_assuj = 0;
            // Document rendering must not invent registration from charged tax.
            foreach (array(array($invoices[1], 'sponge', $conf->facture->dir_output), array($proposal, 'azur', $conf->propal->dir_output)) as $unregistered) {
                $document = $unregistered[0];
                insist($document->generateDocument($unregistered[1], $outputlangs) > 0, 'Unregistered PDF generation failed');
                $unregisteredPdf = $unregistered[2].'/'.dol_sanitizeFileName($document->ref).'/'.dol_sanitizeFileName($document->ref).'.pdf';
                assertPdfLanguage($unregisteredPdf, '123 456 785');
                assertPdfLanguage($unregisteredPdf, '123 456 785 MVA', false);
            }
            $mysoc->tva_assuj = 1;

        }
    }
    insist(array($mysoc->idprof1, $mysoc->tva_assuj, $mysoc->tva_intra) === $identityBefore, 'PDF rendering changed company identity');
    print "PASS Norwegian/English invoice, credit note and proposal PDF organisation/MVA identity\n";
    print "PASS Norwegian/English invoice and proposal PDFs in NOK\n";
    print "PASS core invoice creation, validation, sales-journal transfer, credit note, NOK ledger and repeated transfer\n";
} finally {
    curl_close($curl);
    unlink($cookie);
    $db->close();
}
