<?php
// SPDX-License-Identifier: GPL-3.0-or-later
/** Read-only voucher balance and customer-document trace; not a VAT report. */
class NorwayLedgerAudit
{
    private $db;
    private $entity;

    public function __construct($db, $entity)
    {
        if ((int) $entity <= 0) { throw new InvalidArgumentException('Invalid entity'); }
        $this->db = $db;
        $this->entity = (int) $entity;
    }

    public static function period($start, $end)
    {
        $dates = array();
        foreach (array($start, $end) as $value) {
            if (!is_string($value) || !preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $value)) {
                throw new InvalidArgumentException('Invalid date');
            }
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
            if (!$date || $date->format('Y-m-d') !== $value) {
                throw new InvalidArgumentException('Invalid date');
            }
            $dates[] = $date;
        }
        if ($dates[1] < $dates[0] || $dates[0]->diff($dates[1])->days > 365) {
            throw new InvalidArgumentException('Period must be ordered and at most 366 days');
        }
        return array($start, $dates[1]->modify('+1 day')->format('Y-m-d'));
    }

    public static function balanced($amount)
    {
        return preg_match('/^-?0(?:\.0+)?$/D', (string) $amount) === 1;
    }

    public function vouchers($start, $end, $limit = 100)
    {
        list($from, $until) = self::period($start, $end);
        if (!is_int($limit) || $limit < 1 || $limit > 1000) {
            throw new InvalidArgumentException('Invalid limit');
        }
        $prefix = $this->db->prefix();
        $sql = "SELECT b.code_journal,b.piece_num,MIN(b.doc_date) AS doc_date,COUNT(*) AS line_count,"
            ."SUM(CAST(b.debit AS DECIMAL(32,8))) AS total_debit,"
            ."SUM(CAST(b.credit AS DECIMAL(32,8))) AS total_credit,"
            ."SUM(CAST(b.debit AS DECIMAL(32,8))-CAST(b.credit AS DECIMAL(32,8))) AS difference,"
            ."SUM(CASE WHEN b.doc_type='customer_invoice' AND (f.rowid IS NULL OR b.doc_ref IS NULL OR b.doc_ref='' OR f.ref<>b.doc_ref) THEN 1 ELSE 0 END) AS missing_sources,"
            ."SUM(CASE WHEN b.doc_type<>'customer_invoice' OR b.doc_type IS NULL THEN 1 ELSE 0 END) AS other_sources,"
            ."SUM(CASE WHEN b.doc_type='customer_invoice' AND b.fk_docdet=0 THEN 1 ELSE 0 END) AS aggregated_lines"
            ." FROM ".$prefix."accounting_bookkeeping b"
            ." LEFT JOIN ".$prefix."facture f ON b.doc_type='customer_invoice' AND f.rowid=b.fk_doc AND f.entity=b.entity"
            ." WHERE b.entity=".$this->entity
            ." AND b.doc_date>='".$this->db->escape($from)."' AND b.doc_date<'".$this->db->escape($until)."'"
            ." GROUP BY b.code_journal,b.piece_num,CASE WHEN b.piece_num>0 THEN 0 ELSE b.rowid END"
            ." ORDER BY MIN(b.doc_date),b.code_journal,b.piece_num"
            .$this->db->plimit($limit + 1);
        $result = $this->db->query($sql);
        if (!$result) { throw new RuntimeException('Ledger audit query failed'); }
        $rows = array();
        while ($row = $this->db->fetch_object($result)) {
            $row->balanced = self::balanced($row->difference);
            $row->has_key = trim((string) $row->code_journal) !== '' && (int) $row->piece_num > 0;
            $row->status = !$row->balanced || !$row->has_key || (int) $row->missing_sources > 0
                ? 'Error' : ((int) $row->other_sources > 0 ? 'Review' : 'OK');
            $rows[] = $row;
        }
        $truncated = count($rows) > $limit;
        if ($truncated) { array_pop($rows); }
        return array('rows' => $rows, 'truncated' => $truncated);
    }
}
