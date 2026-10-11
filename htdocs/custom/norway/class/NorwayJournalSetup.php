<?php
// SPDX-License-Identifier: GPL-3.0-or-later
/** Create missing journal types after confirmation; never change existing journals. */
class NorwayJournalSetup
{
    private static function rows($db, $sql)
    {
        $result = $db->query($sql);
        if (!$result) { throw new RuntimeException('Journals'); }
        $rows = array();
        while ($row = $db->fetch_object($result)) { $rows[] = (array) $row; }
        return $rows;
    }

    public static function preview($db, $entity)
    {
        if ((int) $entity <= 0 || (int) $GLOBALS['conf']->entity !== (int) $entity) { throw new InvalidArgumentException('Entity'); }
        $existing = self::rows($db, 'SELECT rowid,code,label,nature,active FROM '.$db->prefix().'accounting_journal WHERE entity='.(int) $entity.' ORDER BY rowid');
        $rows = array();
        foreach (array(2 => array('SALG', 'Salg'), 3 => array('KJOP', 'Kjøp'),
            4 => array('BANK', 'Bank'), 1 => array('DIV', 'Diverse')) as $nature => $proposal) {
            $matches = array(); $collision = false;
            foreach ($existing as $journal) {
                if ((int) $journal['nature'] === $nature && (int) $journal['active'] === 1 && trim($journal['code']) !== '') {
                    $matches[] = $journal['code'].' — '.$journal['label'];
                }
                $collision = $collision || strtoupper(trim($journal['code'])) === $proposal[0];
            }
            $rows[] = array('nature' => $nature, 'code' => $proposal[0], 'label' => $proposal[1], 'existing' => $matches,
                'action' => $matches ? 'Keep' : ($collision ? 'Blocked' : 'Create'));
        }
        return array('entity' => (int) $entity, 'existing' => $existing, 'rows' => $rows);
    }

    public static function fingerprint($preview)
    {
        return hash('sha256', json_encode($preview, JSON_THROW_ON_ERROR));
    }

    public static function apply($db, $entity, $fingerprint, $user)
    {
        if ((int) $entity <= 0 || (int) $GLOBALS['conf']->entity !== (int) $entity || empty($user->admin) || (int) $user->id <= 0) {
            throw new InvalidArgumentException('Confirmation');
        }
        require_once DOL_DOCUMENT_ROOT.'/accountancy/class/accountingjournal.class.php';
        if ($db->begin() <= 0) { throw new RuntimeException('Journals'); }
        try {
            // Lock the full entity range, including the empty range, before re-reading.
            self::rows($db, 'SELECT rowid FROM '.$db->prefix().'accounting_journal WHERE entity='.(int) $entity.' FOR UPDATE');
            $preview = self::preview($db, $entity);
            if (!hash_equals(self::fingerprint($preview), (string) $fingerprint)) { throw new InvalidArgumentException('Changed'); }
            $created = 0;
            foreach ($preview['rows'] as $row) {
                if ($row['action'] === 'Blocked') { throw new InvalidArgumentException('Blocked'); }
                if ($row['action'] !== 'Create') { continue; }
                $journal = new AccountingJournal($db);
                $journal->entity = (int) $entity; $journal->code = $row['code']; $journal->label = $row['label'];
                $journal->nature = $row['nature']; $journal->active = 1;
                if ($journal->create($user) <= 0) { throw new RuntimeException('Journals'); }
                $created++;
            }
            if ($db->commit() <= 0) { throw new RuntimeException('Journals'); }
            return $created;
        } catch (Throwable $error) { $db->rollback(); throw $error; }
    }
}
