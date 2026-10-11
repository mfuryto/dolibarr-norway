<?php
// SPDX-License-Identifier: GPL-3.0-or-later
if (PHP_SAPI !== 'cli' || getenv('NORWAY_SYNTHETIC_TEST') !== '1'
    || getenv('DOLI_DB_NAME') !== 'norway_test') { exit(1); }
require '/var/www/html/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/norway/class/NorwayLedgerAudit.php';
require_once DOL_DOCUMENT_ROOT.'/accountancy/class/bookkeeping.class.php';
function ensure($condition, $message)
{
    if (!$condition) { throw new RuntimeException($message); }
}
foreach (array(array('2026-02-30','2026-03-01'), array('2026-10-02','2026-10-01'),
    array('2025-01-01','2026-12-31'), array("2026-01-01'",'2026-01-02')) as $dates) {
    try { NorwayLedgerAudit::period($dates[0], $dates[1]); throw new RuntimeException('Invalid period accepted'); }
    catch (InvalidArgumentException $expected) { }
}
ensure(NorwayLedgerAudit::balanced('0.00000000') && !NorwayLedgerAudit::balanced('0.00000001'), 'Precision lost');
$audit = new NorwayLedgerAudit($db, $conf->entity);
$report = $audit->vouchers('2026-01-01', '2026-12-31', 1000);
$invoices = array_filter($report['rows'], function ($row) { return strpos($row->code_journal, 'SYN-') === 0; });
ensure(count($invoices) >= 4, 'Real invoice workflow fixtures missing');
foreach ($invoices as $row) { ensure($row->status === 'OK', 'Balanced invoice flagged'); }
ensure($audit->vouchers('2026-01-01', '2026-12-31', 1)['truncated'], 'Truncated report was not flagged');
ensure((new NorwayLedgerAudit($db, 999999))->vouchers('2026-01-01', '2026-12-31')['rows'] === array(), 'Entity leakage');
ensure($user->fetch('', 'testadmin') > 0, 'Test admin missing');
$conf->global->ACCOUNTANCY_FISCAL_PERIOD_MODE = 'blockedonclosed';
$record = new BookKeeping($db);
$record->doc_date = dol_mktime(12, 0, 0, 10, 1, 2026);
$record->doc_type = 'customer_invoice';
$record->doc_ref = 'SYN-MISSING-INVOICE';
$record->fk_doc = 999999999;
$record->fk_docdet = 0;
$record->numero_compte = '1500';
$record->label_compte = $record->label_operation = 'Synthetic audit failure';
$record->code_journal = 'SYN-AUDIT-FAIL';
$record->journal_label = 'Synthetic audit failure';
$record->debit = $record->montant = 1;
$record->credit = 0;
$record->fk_user_author = $user->id;
ensure($record->create($user) >= 0 && $record->id > 0, 'Could not create negative fixture: '.$record->error.' '.implode('; ', $record->errors));
try {
    $report = $audit->vouchers('2026-10-01', '2026-10-01', 1000);
    $bad = array_values(array_filter($report['rows'], function ($row) { return $row->code_journal === 'SYN-AUDIT-FAIL'; }));
    ensure(count($bad) === 1 && $bad[0]->status === 'Error' && !$bad[0]->balanced
        && (int) $bad[0]->missing_sources === 1, 'Unbalanced orphan posting missed');
} finally {
    ensure((bool) $db->query('DELETE FROM '.$db->prefix().'accounting_bookkeeping WHERE rowid='.(int) $record->id.' AND entity='.(int) $conf->entity), 'Negative fixture cleanup failed');
}
print "PASS voucher balance precision, source trace, entity isolation, dates and truncation\n";
$db->close();
