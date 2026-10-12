<?php
// SPDX-License-Identifier: GPL-3.0-or-later
/** Stored bookkeeping references only; not reconciliation or tax allocation. */
class NorwayInvoiceLedger
{
    public static function report($db,$entity,$invoiceId)
    {
        if (!is_int($entity) || $entity<=0 || $entity!==(int)$GLOBALS['conf']->entity || !is_int($invoiceId) || $invoiceId<=0) { throw new InvalidArgumentException('Invoice/entity'); }
        $p=$db->prefix();
        $r=$db->query("SELECT b.rowid,b.code_journal,b.piece_num,b.doc_date,b.doc_ref,b.fk_docdet,b.numero_compte,b.debit,b.credit,f.ref AS invoice_ref,d.rowid AS line_exists"
            ." FROM ".$p."accounting_bookkeeping b INNER JOIN ".$p."facture f ON f.rowid=b.fk_doc AND f.entity=b.entity"
            ." LEFT JOIN ".$p."facturedet d ON d.rowid=b.fk_docdet AND d.fk_facture=f.rowid"
            ." WHERE b.entity=".$entity." AND b.fk_doc=".$invoiceId." AND b.doc_type='customer_invoice' ORDER BY b.rowid LIMIT 201");
        if (!$r) { throw new RuntimeException('Invoice ledger read'); }
        $rows=array();
        while ($row=$db->fetch_object($r)) {
            $line=$row->fk_docdet;
            $row->trace=(string)$row->doc_ref==='' || $row->doc_ref!==$row->invoice_ref ? 'Reference' :
                (trim((string)$row->code_journal)==='' || (int)$row->piece_num<=0 ? 'Key' :
                (trim((string)$row->numero_compte)==='' ? 'MissingAccount' :
                ($line!==null && (string)$line==='0' ? 'Aggregated' : (empty($row->line_exists) ? 'MissingLine' : 'Linked'))));
            unset($row->invoice_ref,$row->line_exists);
            $rows[]=$row;
        }
        $truncated=count($rows)>200; if ($truncated) { array_pop($rows); }
        return array('rows'=>$rows,'truncated'=>$truncated);
    }
}
