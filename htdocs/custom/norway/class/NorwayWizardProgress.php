<?php
// SPDX-License-Identifier: GPL-3.0-or-later
/** Administrator's editable work markers, never accounting approval. */
class NorwayWizardProgress
{
    public static function states() { return array('NotStarted', 'InProgress', 'Waiting', 'Reviewed', 'NotApplicable'); }
    private static function key($step)
    {
        if (!is_int($step) || $step < 1 || $step > 11) { throw new InvalidArgumentException('Step'); }
        return 'NORWAY_WIZARD_STEP_'.$step;
    }
    private static function rows($db, $entity, $step, $lock = false)
    {
        if ((int) $entity <= 0 || (int) $GLOBALS['conf']->entity !== (int) $entity) { throw new InvalidArgumentException('Entity'); }
        $result = $db->query("SELECT value FROM ".$db->prefix()."const WHERE entity=".(int) $entity." AND name='".self::key($step)."'".($lock ? ' FOR UPDATE' : ''));
        if (!$result) { throw new RuntimeException('Progress read'); }
        $rows = array(); while ($row = $db->fetch_object($result)) { $rows[] = $row->value; } return $rows;
    }
    public static function fingerprint($rows) { return hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR)); }
    public static function read($db, $entity, $step, $org)
    {
        $rows = self::rows($db, $entity, $step);
        $out = array('state'=>'NotStarted', 'updated'=>'', 'fingerprint'=>self::fingerprint($rows), 'stale'=>false);
        if (!$rows) { return $out; }
        $record = count($rows) === 1 ? json_decode($rows[0], true) : null;
        if (!is_array($record) || ($record['version'] ?? null) !== 1 || ($record['entity'] ?? null) !== (int) $entity
            || ($record['step'] ?? null) !== $step || !in_array($record['state'] ?? null, self::states(), true)
            || !is_string($record['org'] ?? null) || !is_string($record['updated'] ?? null)
            || !is_int($record['user'] ?? null) || $record['user'] <= 0) { throw new RuntimeException('Progress invalid'); }
        $out['stale'] = $record['org'] !== preg_replace('/[ \t]/', '', (string) $org);
        $out['state'] = $record['state']; $out['updated'] = $record['updated']; return $out;
    }
    public static function save($db, $entity, $step, $state, $org, $fingerprint, $user)
    {
        if (empty($user->admin) || (int) $user->id <= 0 || !in_array($state, self::states(), true)) { throw new InvalidArgumentException('Progress input'); }
        $key = self::key($step);
        $org = preg_replace('/[ \t]/', '', (string) $org);
        if (strlen($org) > 64) { throw new InvalidArgumentException('Identity'); }
        require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
        $had = property_exists($GLOBALS['conf']->global, $key); $old = $GLOBALS['conf']->global->$key ?? null;
        if ($db->begin() <= 0) { throw new RuntimeException('Progress save'); }
        try {
            $rows = self::rows($db, $entity, $step, true);
            if (!hash_equals(self::fingerprint($rows), (string) $fingerprint)) { throw new RuntimeException('Progress changed'); }
            $record = json_encode(array('version'=>1, 'entity'=>(int) $entity, 'step'=>$step, 'org'=>$org,
                'state'=>$state, 'user'=>(int) $user->id, 'updated'=>gmdate('c')), JSON_THROW_ON_ERROR);
            if (dolibarr_set_const($db, $key, $record, 'chaine', 0, 'Manual setup work status; not approval', (int) $entity) <= 0
                || self::rows($db, $entity, $step) !== array($record) || $db->commit() <= 0) { throw new RuntimeException('Progress save'); }
        } catch (Throwable $error) {
            $db->rollback(); if ($had) { $GLOBALS['conf']->global->$key = $old; } else { unset($GLOBALS['conf']->global->$key); }
            throw $error;
        }
    }
}
