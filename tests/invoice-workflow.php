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
    insist($invoice->validate($user) > 0, 'Invoice validation failed: '.$invoice->error);
    insist($invoice->fetch($invoice->id) > 0, 'Invoice reload failed');
    $invoices[] = $invoice;
}
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
function assertPdfLanguage($path, $title)
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
            || stripos($part, bin2hex($unicode)) !== false) { return; }
    }
    throw new RuntimeException('Expected PDF text missing: '.$title);
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
    }
    $count = (int) $db->fetch_object(sql("SELECT COUNT(*) AS n FROM ".$db->prefix()."accounting_bookkeeping WHERE doc_type='customer_invoice' AND entity=".(int) $conf->entity))->n;
    $body = http($path);
    http($path, $params + array('token' => token($body), 'action' => 'writebookkeeping'));
    insist((int) $db->fetch_object(sql("SELECT COUNT(*) AS n FROM ".$db->prefix()."accounting_bookkeeping WHERE doc_type='customer_invoice' AND entity=".(int) $conf->entity))->n === $count, 'Repeated transfer duplicated postings');
    foreach (array('nb_NO', 'en_US') as $language) {
        $outputlangs = new Translate('', $conf);
        $outputlangs->setDefaultLang($language);
        $outputlangs->loadLangs(array('main', 'bills', 'propal'));
        foreach (array($invoices[0], $invoices[3]) as $invoice) {
            insist($invoice->generateDocument('sponge', $outputlangs) > 0, 'Invoice PDF failed: '.$invoice->error);
            $pdf = $conf->facture->dir_output.'/'.dol_sanitizeFileName($invoice->ref).'/'.dol_sanitizeFileName($invoice->ref).'.pdf';
            assertPdfLanguage($pdf, $outputlangs->transnoentities('PdfInvoiceTitle'));
            assertPdfLanguage($pdf, $outputlangs->transnoentitiesnoconv('Currency'.$invoice->multicurrency_code));
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
        }
    }
    print "PASS Norwegian/English invoice and proposal PDFs in NOK\n";
    print "PASS core invoice creation, validation, sales-journal transfer, credit note, NOK ledger and repeated transfer\n";
} finally {
    curl_close($curl);
    unlink($cookie);
    $db->close();
}
