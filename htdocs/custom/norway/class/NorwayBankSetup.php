<?php
// SPDX-License-Identifier: GPL-3.0-or-later
/** Read-only checks of open bank/cash accounts, scoped to the active entity and chart. */
class NorwayBankSetup
{
    private $db;
    private $entity;

    public function __construct($db, $entity)
    {
        if ((int) $entity <= 0) { throw new InvalidArgumentException('Invalid entity'); }
        $this->db = $db;
        $this->entity = (int) $entity;
    }

    private function rows($sql)
    {
        $result = $this->db->query($sql);
        if (!$result) { throw new RuntimeException('Bank setup query failed'); }
        $rows = array();
        while ($row = $this->db->fetch_object($result)) { $rows[] = $row; }
        return $rows;
    }

    public function report($chartId)
    {
        $prefix = $this->db->prefix();
        $charts = $this->rows("SELECT pcg_version,active FROM ".$prefix."accounting_system WHERE rowid=".(int) $chartId);
        $chart = $charts ? $charts[0] : null;
        // Never select IBAN, real account number, balances, owner or credentials.
        $banks = $this->rows("SELECT rowid,ref,label,account_number,fk_accountancy_journal,currency_code,courant"
            ." FROM ".$prefix."bank_account WHERE entity=".$this->entity." AND clos=0 ORDER BY rowid");
        $usage = array();
        foreach ($banks as $bank) {
            $number = trim((string) $bank->account_number);
            if ($number !== '' && $number !== '-1') { $usage[$number] = ($usage[$number] ?? 0) + 1; }
        }
        $report = array();
        foreach ($banks as $bank) {
            $number = trim((string) $bank->account_number);
            $accountStatus = 'Unset';
            if ($number !== '' && $number !== '-1') {
                $accounts = $chart ? $this->rows("SELECT active FROM ".$prefix."accounting_account"
                    ." WHERE entity=".$this->entity." AND fk_pcg_version='".$this->db->escape($chart->pcg_version)."'"
                    ." AND account_number='".$this->db->escape($number)."'".$this->db->plimit(2)) : array();
                $accountStatus = !$accounts ? 'Missing' : (count($accounts) > 1 ? 'Ambiguous'
                    : ((int) $chart->active !== 1 || (int) $accounts[0]->active !== 1 ? 'Inactive' : 'Available'));
            }
            $journalStatus = 'Unset';
            $journalCode = '';
            if ((int) $bank->fk_accountancy_journal > 0) {
                $journals = $this->rows("SELECT code,nature,active FROM ".$prefix."accounting_journal"
                    ." WHERE entity=".$this->entity." AND rowid=".(int) $bank->fk_accountancy_journal);
                $journal = $journals ? $journals[0] : null;
                $journalCode = $journal ? (string) $journal->code : '';
                $journalStatus = !$journal ? 'Missing' : ((int) $journal->active !== 1 ? 'Inactive'
                    : ((int) $journal->nature !== 4 || trim($journalCode) === '' ? 'Review' : 'Available'));
            }
            $report[] = array('id' => (int) $bank->rowid, 'ref' => $bank->ref, 'label' => $bank->label,
                'number' => $number, 'account_status' => $accountStatus, 'journal' => $journalCode,
                'journal_status' => $journalStatus, 'currency' => $bank->currency_code,
                'currency_status' => $bank->currency_code === 'NOK' ? 'Available' : 'Review',
                'shared_status' => ($usage[$number] ?? 0) > 1 ? 'Review' : 'Available',
                'cash' => (int) $bank->courant === 2);
        }
        return $report;
    }
}
