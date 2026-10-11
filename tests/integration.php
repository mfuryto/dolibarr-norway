<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// CLI-only synthetic import test, never an invoice-to-ledger workflow test.
if (PHP_SAPI !== 'cli' || getenv('NORWAY_SYNTHETIC_TEST') !== '1'
    || getenv('DOLI_DB_NAME') !== 'norway_test'
    || getenv('DOLI_COMPANY_NAME') !== 'SYNTHETIC NORWAY TEST ONLY') {
    fwrite(STDERR, "Refusing to run outside the dedicated synthetic test environment.\n");
    exit(1);
}
require '/var/www/html/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/accountancy/class/bookkeeping.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/norway/core/modules/modNorway.class.php';

function check($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
function query($sql)
{
    global $db;
    $result = $db->query($sql);
    check((bool) $result, $db->lasterror());
    return $result;
}
check(DOL_VERSION === '24.0.1', 'Unexpected Dolibarr version');
check($user->fetch('', 'testadmin') > 0 && $user->admin, 'Test admin missing');
$result = activateModule('modNorway');
check(empty($result['errors']), json_encode($result['errors']));
$module = new modNorway($db);
check($module->init() > 0, $module->error);
check($module->remove() > 0, 'Module deactivation failed');
check($module->init() > 0, 'Module reactivation failed');

// Date policy is test-local; production requires explicit fiscal periods.
$conf->global->ACCOUNTANCY_FISCAL_PERIOD_MODE = 'blockedonclosed';
foreach (glob(__DIR__.'/fixtures/*.json') as $file) {
    $data = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
    check($data['synthetic'] === true, 'Non-synthetic fixture');
    $prefix = 'SYN-'.$data['profile'].'-';
    $escaped = $db->escape($prefix);
    $db->begin();
    try {
        $existing = $db->fetch_object(query("SELECT COUNT(*) AS n FROM ".$db->prefix()."accounting_bookkeeping WHERE doc_ref LIKE '".$escaped."%' AND entity=".(int) $conf->entity));
        check((int) $existing->n === 0, 'Seed already exists; use a fresh test database');
        foreach ($data['documents'] as $i => $document) {
            foreach ($document['lines'] as $j => $line) {
                $record = new BookKeeping($db);
                $record->doc_date = strtotime($document['date'].' 12:00:00 UTC');
                $record->doc_type = 'import';
                $record->doc_ref = $prefix.$document['id'];
                $record->fk_doc = ($data['profile'] === 'registered' ? 810000 : 820000) + $i;
                $record->fk_docdet = $j + 1;
                $record->numero_compte = $line['account'];
                $record->label_compte = 'Synthetic '.$line['account'];
                $record->label_operation = $document['id'];
                $record->code_journal = 'SYN';
                $record->journal_label = 'Synthetic test';
                $record->debit = $line['debit'] / 100;
                $record->credit = $line['credit'] / 100;
                $record->montant = $record->debit - $record->credit;
                $record->fk_user_author = $user->id;
                check($record->create($user) >= 0, implode('; ', $record->errors));
            }
        }
        $opening = $closing = array();
        $seen = array();
        $rows = query("SELECT doc_ref,doc_date,numero_compte,debit,credit,piece_num FROM ".$db->prefix()."accounting_bookkeeping WHERE doc_ref LIKE '".$escaped."%' AND entity=".(int) $conf->entity);
        while ($row = $db->fetch_object($rows)) {
            $account = $row->numero_compte;
            $amount = (int) round(((float) $row->debit - (float) $row->credit) * 100);
            $closing[$account] = ($closing[$account] ?? 0) + $amount;
            if (substr($row->doc_date, 0, 10) < $data['period'][0]) {
                $opening[$account] = ($opening[$account] ?? 0) + $amount;
            }
            check((int) $row->piece_num > 0, 'Missing voucher number');
            $seen[$row->doc_ref][] = $row;
        }
        ksort($opening); ksort($closing);
        $expectedOpen = $data['expected']['opening'];
        $expectedClose = $data['expected']['closing'];
        ksort($expectedOpen); ksort($expectedClose);
        check($opening === $expectedOpen, 'Opening balance mismatch');
        check($closing === $expectedClose, 'Closing balance mismatch');
        foreach ($data['documents'] as $document) {
            $rows = $seen[$prefix.$document['id']] ?? array();
            check(count($rows) === count($document['lines']), 'Missing source trace');
            $balance = 0;
            foreach ($rows as $row) {
                $balance += (int) round(((float) $row->debit - (float) $row->credit) * 100);
            }
            check($balance === 0, 'Unbalanced imported voucher');
        }
        $db->commit();
        print 'PASS core ledger import: '.$data['profile']."\n";
    } catch (Throwable $error) {
        $db->rollback();
        throw $error;
    }
}
print "PASS module activation, reactivation, ledger balances and fixture trace\n";
$db->close();
