<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require_once __DIR__.'/NorwayInvoiceCheck.php';
require_once __DIR__.'/NorwayVatRegistration.php';
/** Minimum field validation only; does not approve VAT treatment or legal compliance. */
class NorwayInvoiceValidation
{
    public static function missing($invoice, $seller, $customer, $source = null)
    {
        $checks = NorwayInvoiceCheck::evaluate($invoice, $seller, $customer, $source);
        $missing = array_keys(array_filter($checks, static function ($status) { return $status === 'Missing'; }));
        // Historical credit notes may reverse tax from a previously registered period.
        // Their source is required; detailed tax correspondence remains a separate check.
        if ((int) $invoice->type !== 2 && (int) ($seller->tva_assuj ?? 0) !== 1) {
            $tax = self::hasVat($invoice);
            if ($tax) { $missing[] = 'UnregisteredVAT'; }
        }
        return $missing;
    }

    public static function hasVat($invoice)
    {
        $tax=(float)($invoice->total_tva ?? 0)!==0.0;
        foreach ($invoice->lines ?? array() as $line) { $tax=$tax || (float)($line->tva_tx ?? 0)!==0.0 || (float)($line->total_tva ?? 0)!==0.0; }
        return $tax;
    }

    /** Advisory in the native validation workflow, never an inferred permission to charge VAT. */
    public static function registrationWarning($db,$invoice,$seller,$entity)
    {
        if (!self::hasVat($invoice)) { return ''; }
        if ((int)$invoice->type===2) { return 'Credit'; }
        try {
            $record=NorwayVatRegistration::read($db,$entity,$seller->idprof1 ?? '');
            $status=NorwayVatRegistration::compare($record,dol_print_date($invoice->date,'dayrfc'));
            return $status==='After' ? '' : $status;
        } catch (Throwable $error) { return 'Unavailable'; }
    }

    public static function loadAndCheck($db, $invoice, $seller, $entity)
    {
        if ((int) ($invoice->entity ?? 0) !== (int) $entity) { return array('Entity'); }
        require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
        require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
        $customer = new Societe($db);
        if ($customer->fetch($invoice->socid) <= 0) { return array('Customer'); }
        $source = null;
        if ((int) $invoice->type === 2 && $invoice->fk_facture_source > 0) {
            $res = $db->query('SELECT rowid FROM '.MAIN_DB_PREFIX.'facture WHERE rowid='.(int) $invoice->fk_facture_source.' AND entity='.(int) $entity);
            if (!$res) { return array('ReadError'); }
            if ($db->num_rows($res)) {
                $candidate = new Facture($db);
                if ($candidate->fetch($invoice->fk_facture_source) > 0) { $source = $candidate; }
            }
        }
        return self::missing($invoice, $seller, $customer, $source);
    }
}
