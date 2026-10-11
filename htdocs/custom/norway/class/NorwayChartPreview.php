<?php
// SPDX-License-Identifier: GPL-3.0-or-later
/** Bounded CSV preflight. Never imports or changes accounts. */
class NorwayChartPreview
{
    public const FORMAT = 'norway-chart-csv/1';

    public static function parse($csv)
    {
        if (!is_string($csv) || strlen($csv) > 262144 || !preg_match('//u', $csv)) {
            throw new InvalidArgumentException('CSV');
        }
        $csv = preg_replace('/^\xEF\xBB\xBF/', '', $csv);
        $lines = preg_split('/\r\n|\n|\r/', $csv);
        if (array_shift($lines) !== 'account_number;label') { throw new InvalidArgumentException('CSV'); }
        $rows = array(); $seen = array();
        foreach ($lines as $line) {
            if ($line === '') { continue; }
            if (count($rows) >= 500 || !preg_match('/^[1-9][0-9]{3,7};(?:"(?:[^"\r\n]|"")*"|[^;"\r\n]*)$/D', $line)) {
                throw new InvalidArgumentException('CSV');
            }
            $fields = str_getcsv($line, ';', '"', '');
            $number = $fields[0]; $label = trim($fields[1]);
            if ($label === '' || strlen($label) > 255 || preg_match('/[\x00-\x1f\x7f]/', $label)
                || isset($seen[$number])) { throw new InvalidArgumentException('CSV'); }
            $seen[$number] = true;
            $rows[] = array('number' => $number, 'label' => $label);
        }
        if (!$rows) { throw new InvalidArgumentException('CSV'); }
        return $rows;
    }

    public static function report($db, $entity, $chartId, $csv)
    {
        if ((int) $entity <= 0) { throw new InvalidArgumentException('Chart'); }
        $rows = self::parse($csv);
        $query = static function ($sql) use ($db) {
            $result = $db->query($sql);
            if (!$result) { throw new RuntimeException('Chart preview query failed'); }
            $values = array();
            while ($row = $db->fetch_object($result)) { $values[] = $row; }
            return $values;
        };
        $prefix = $db->prefix();
        $chart = $query("SELECT s.pcg_version FROM ".$prefix."accounting_system s INNER JOIN ".$prefix."c_country c ON c.rowid=s.fk_country"
            ." WHERE s.rowid=".(int) $chartId." AND s.active=1 AND c.code='NO'");
        if (count($chart) !== 1) { throw new InvalidArgumentException('Chart'); }
        $numbers = array();
        foreach ($rows as $row) { $numbers[] = "'".$db->escape($row['number'])."'"; }
        $in = implode(',', $numbers);
        $existing = $query("SELECT rowid,account_number,label,active FROM ".$prefix."accounting_account WHERE entity=".(int) $entity
            ." AND fk_pcg_version='".$db->escape($chart[0]->pcg_version)."' AND account_number IN (".$in.")");
        $accounts = array();
        foreach ($existing as $account) { $accounts[$account->account_number][] = $account; }
        // Core ledger has no chart identifier: usage is historical, per entity and number.
        $usage = $query("SELECT numero_compte,COUNT(*) AS total FROM ".$prefix."accounting_bookkeeping WHERE entity=".(int) $entity
            ." AND numero_compte IN (".$in.") GROUP BY numero_compte");
        $used = array();
        foreach ($usage as $entry) { $used[$entry->numero_compte] = (int) $entry->total; }
        foreach ($rows as &$row) {
            $matches = $accounts[$row['number']] ?? array();
            $row['existing_id'] = count($matches) === 1 ? (int) $matches[0]->rowid : 0;
            $row['existing_label'] = count($matches) === 1 ? $matches[0]->label : '';
            $row['usage'] = $used[$row['number']] ?? 0;
            $row['status'] = !$matches ? ($row['usage'] > 0 ? 'Historical' : 'New')
                : (count($matches) > 1 ? 'Conflict' : ((int) $matches[0]->active !== 1 ? 'Inactive'
                    : ((string) $matches[0]->label !== $row['label'] ? 'Conflict' : 'Keep')));
        }
        unset($row);
        return array('rows' => $rows, 'chart' => $chart[0]->pcg_version, 'format' => self::FORMAT);
    }
}
