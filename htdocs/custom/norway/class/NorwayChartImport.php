<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require_once __DIR__.'/NorwayChartPreview.php';
/** Insert missing accounts only; never overwrite, activate or remap existing accounts. */
class NorwayChartImport
{
    public static function eligible($report)
    {
        foreach ($report['rows'] as $row) {
            if (!in_array($row['status'], array('New', 'Keep'), true)) { return false; }
        }
        return true;
    }

    public static function fingerprint($entity, $chartId, $report)
    {
        return hash('sha256', json_encode(array((int) $entity, (int) $chartId, $report), JSON_THROW_ON_ERROR));
    }

    public static function apply($db, $entity, $chartId, $csv, $fingerprint, $user, $preserveExisting = false)
    {
        global $conf;
        if ((int) $entity <= 0 || (int) $conf->entity !== (int) $entity || empty($user->admin) || (int) $user->id <= 0) {
            throw new InvalidArgumentException('Confirmation');
        }
        require_once DOL_DOCUMENT_ROOT.'/accountancy/class/accountingaccount.class.php';
        if ($db->begin() <= 0) { throw new RuntimeException('Import'); }
        try {
            // Serialize our imports and protect the selected chart from concurrent changes.
            $result = $db->query("SELECT rowid FROM ".$db->prefix()."accounting_system WHERE rowid=".(int) $chartId." FOR UPDATE");
            if (!$result || !$db->fetch_object($result)) { throw new RuntimeException('Import'); }
            $result = $db->query("SELECT value FROM ".$db->prefix()."const WHERE name='CHARTOFACCOUNTS' AND entity=".(int) $entity." FOR UPDATE");
            $setting = $result ? $db->fetch_object($result) : false;
            if (!$setting || (int) $setting->value !== (int) $chartId) { throw new InvalidArgumentException('Changed'); }
            $numbers = array();
            foreach (NorwayChartPreview::parse($csv) as $row) { $numbers[] = "'".$db->escape($row['number'])."'"; }
            $in = implode(',', $numbers);
            foreach (array("SELECT rowid FROM ".$db->prefix()."accounting_account WHERE entity=".(int) $entity." AND account_number IN (".$in.") FOR UPDATE",
                "SELECT rowid FROM ".$db->prefix()."accounting_bookkeeping WHERE entity=".(int) $entity." AND numero_compte IN (".$in.") FOR UPDATE") as $sql) {
                if (!$db->query($sql)) { throw new RuntimeException('Import'); }
            }
            $report = NorwayChartPreview::report($db, $entity, $chartId, $csv, $preserveExisting);
            if (!hash_equals(self::fingerprint($entity, $chartId, $report), (string) $fingerprint)
                || !self::eligible($report)) { throw new InvalidArgumentException('Changed'); }
            $created = 0;
            foreach ($report['rows'] as $row) {
                if ($row['status'] !== 'New') { continue; }
                $account = new AccountingAccount($db);
                $account->fk_pcg_version = $report['chart'];
                $account->account_number = $row['number'];
                $account->label = $row['label'];
                $account->active = 1;
                // No inferred tax/reporting category, parent, reconciliation or default mapping.
                if ($account->create($user) <= 0) { throw new RuntimeException('Import'); }
                $created++;
            }
            if ($db->commit() <= 0) { throw new RuntimeException('Import'); }
            return $created;
        } catch (Throwable $error) {
            $db->rollback();
            throw $error;
        }
    }
}
