<?php
// SPDX-License-Identifier: GPL-3.0-or-later
/** Read-only structural checks. Account existence never certifies tax treatment. */
class NorwayAccountingSetup
{
    private $db;
    private $entity;

    public function __construct($db, $entity)
    {
        if ((int) $entity <= 0) { throw new InvalidArgumentException('Invalid entity'); }
        $this->db = $db;
        $this->entity = (int) $entity;
    }

    public static function defaults()
    {
        return array('ACCOUNTING_ACCOUNT_CUSTOMER', 'ACCOUNTING_ACCOUNT_SUPPLIER',
            'ACCOUNTING_PRODUCT_SOLD_ACCOUNT', 'ACCOUNTING_SERVICE_SOLD_ACCOUNT',
            'ACCOUNTING_PRODUCT_BUY_ACCOUNT', 'ACCOUNTING_SERVICE_BUY_ACCOUNT',
            'ACCOUNTING_VAT_SOLD_ACCOUNT', 'ACCOUNTING_VAT_BUY_ACCOUNT');
    }

    private function rows($sql)
    {
        $result = $this->db->query($sql);
        if (!$result) { throw new RuntimeException('Accounting setup query failed'); }
        $rows = array();
        while ($row = $this->db->fetch_object($result)) { $rows[] = $row; }
        return $rows;
    }

    public function report($chartId, $settings)
    {
        $prefix = $this->db->prefix();
        $chart = $this->rows("SELECT s.pcg_version,s.label,s.active,c.code AS country FROM ".$prefix."accounting_system s"
            ." LEFT JOIN ".$prefix."c_country c ON c.rowid=s.fk_country WHERE s.rowid=".(int) $chartId);
        $chart = $chart ? $chart[0] : null;
        $checks = array(array('key' => 'Chart', 'value' => $chart ? $chart->pcg_version.' — '.$chart->label : '',
            'status' => !$chart ? 'Missing' : ((int) $chart->active !== 1 ? 'Inactive' : ($chart->country !== 'NO' ? 'Review' : 'Available'))));
        $version = $chart ? $chart->pcg_version : '';
        $where = " entity=".$this->entity." AND fk_pcg_version='".$this->db->escape($version)."'";
        $counts = $this->rows("SELECT COUNT(*) AS total,COALESCE(SUM(CASE WHEN active=1 THEN 1 ELSE 0 END),0) AS enabled FROM ".$prefix."accounting_account WHERE".$where)[0];
        $checks[] = array('key' => 'Accounts', 'value' => $counts->enabled.' / '.$counts->total,
            'status' => !$chart || (int) $counts->enabled === 0 ? 'Missing' : 'Available');
        foreach (self::defaults() as $key) {
            $value = trim((string) ($settings[$key] ?? ''));
            $status = 'Unset';
            if ($value !== '' && $value !== '-1') {
                $matches = $this->rows("SELECT active FROM ".$prefix."accounting_account WHERE".$where
                    ." AND account_number='".$this->db->escape($value)."'".$this->db->plimit(2));
                $status = !$chart || !$matches ? 'Missing' : (count($matches) > 1 ? 'Ambiguous'
                    : ((int) $matches[0]->active !== 1 ? 'Inactive' : 'Available'));
            }
            $checks[] = array('key' => $key, 'value' => $value, 'status' => $status);
        }
        foreach (array(2 => 'SalesJournal', 3 => 'PurchaseJournal', 4 => 'BankJournal', 1 => 'GeneralJournal') as $nature => $key) {
            $rows = $this->rows("SELECT COUNT(*) AS total FROM ".$prefix."accounting_journal WHERE entity=".$this->entity
                ." AND nature=".$nature." AND active=1 AND TRIM(code)<>''");
            $checks[] = array('key' => $key, 'value' => $rows[0]->total,
                'status' => (int) $rows[0]->total > 0 ? 'Available' : 'Missing');
        }
        return $checks;
    }
}
