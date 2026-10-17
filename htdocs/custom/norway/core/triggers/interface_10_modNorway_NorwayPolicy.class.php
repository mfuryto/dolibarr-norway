<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';
/** Reject unsupported new sales documents and cheque payments through core APIs. */
class InterfaceNorwayPolicy extends DolibarrTriggers
{
    public function __construct($db)
    {
        parent::__construct($db);
        $this->family = 'financial';
        $this->description = 'Norwegian invoice validation and payment restrictions';
        $this->version = '0.74.0';
        $this->picto = 'accountancy';
    }

    public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
    {
        if (!isModEnabled('norway')) { return 0; }
        if ($action==='BOOKKEEPING_CREATE') {
            require_once __DIR__.'/../../class/NorwayLegacyArchive.php';
            try { NorwayLegacyArchive::guardPosting($this->db,(int)$conf->entity,$object); }
            catch (Throwable $error) { $langs->load('norway@norway');$this->error=$langs->trans('NorwayArchiveProtected');$object->error=$this->error;return -1; }
            return 0;
        }
        $reason = '';
        if (in_array($action, array('BILL_CREATE', 'BILL_MODIFY', 'BILL_VALIDATE', 'PROPAL_CREATE', 'PROPAL_MODIFY', 'PROPAL_VALIDATE'), true)) {
            if ($conf->currency !== 'NOK' || (!empty($object->multicurrency_code) && $object->multicurrency_code !== 'NOK')
                || (!empty($object->multicurrency_tx) && abs((float) $object->multicurrency_tx - 1) > 0.00000001)) {
                $reason = 'NorwayNokOnly';
            }
            $paymentId = (int) ($object->mode_reglement_id ?? 0);
        } elseif (in_array($action, array('PAYMENT_CUSTOMER_CREATE', 'PAYMENT_SUPPLIER_CREATE', 'PAYMENT_ADD_TO_BANK'), true)) {
            $paymentId = (int) ($object->paiementid ?? $object->fk_paiement ?? 0);
        } else { return 0; }
        if ($paymentId > 0) {
            $res = $this->db->query('SELECT code FROM '.MAIN_DB_PREFIX.'c_paiement WHERE id='.$paymentId);
            if (!$res) { $this->error = 'Could not verify payment method'; return -1; }
            $payment = $this->db->fetch_object($res);
            if ($payment && $payment->code === 'CHQ') { $reason = 'NorwayNoCheques'; }
        }
        if ($reason === '' && $action === 'BILL_VALIDATE') {
            require_once __DIR__.'/../../class/NorwayInvoiceValidation.php';
            $missing = NorwayInvoiceValidation::loadAndCheck($this->db, $object, $GLOBALS['mysoc'], $conf->entity);
            if ($missing) {
                $langs->load('norway@norway');
                $labels = array_map(static function ($key) use ($langs) { return $langs->trans('NorwayInvoice'.$key); }, $missing);
                $this->error = $langs->trans('NorwayInvoiceValidationFailed', implode(', ', $labels));
                $object->error = $this->error;
                return -1;
            }
        }
        if ($reason !== '') {
            $langs->load('norway@norway');
            $this->error = $langs->trans($reason);
            $object->error = $this->error;
            return -1;
        }
        return 0;
    }
}
