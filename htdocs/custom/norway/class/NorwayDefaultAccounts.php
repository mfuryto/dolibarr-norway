<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require_once __DIR__.'/NorwayChartProfile.php';
/** Confirmed, fill-only customer/supplier defaults for an unused accounting setup. */
class NorwayDefaultAccounts
{
    public static function mapping()
    {
        return array('ACCOUNTING_ACCOUNT_CUSTOMER' => '1500', 'ACCOUNTING_ACCOUNT_SUPPLIER' => '2400');
    }

    private static function rows($db, $sql)
    {
        $result = $db->query($sql);
        if (!$result) { throw new RuntimeException('Defaults'); }
        $rows = array();
        while ($row = $db->fetch_object($result)) { $rows[] = (array) $row; }
        return $rows;
    }

    public static function preview($db, $entity, $mapping = null)
    {
        if ((int) $entity <= 0 || (int) $GLOBALS['conf']->entity !== (int) $entity) { throw new InvalidArgumentException('Entity'); }
        if ($mapping !== null) { require_once __DIR__.'/NorwayStandardAccounts.php'; $mapping = NorwayStandardAccounts::validate($mapping); }
        else { $mapping = self::mapping(); }
        $names = "'CHARTOFACCOUNTS','".implode("','",array_keys($mapping))."'";
        $entity = (int) $entity; $prefix = $db->prefix();
        $constants = self::rows($db, "SELECT name,value,entity FROM ".$prefix."const WHERE entity IN (0,".$entity.")"
            ." AND name IN (".$names.") ORDER BY entity,name");
        $settings = array();
        foreach ($constants as $constant) { $settings[$constant['name']] = $constant['value']; }
        $chartId = (int) ($settings['CHARTOFACCOUNTS'] ?? 0);
        $charts = self::rows($db, "SELECT s.rowid,s.pcg_version,s.active,c.code AS country FROM ".$prefix."accounting_system s"
            ." LEFT JOIN ".$prefix."c_country c ON c.rowid=s.fk_country WHERE s.rowid=".$chartId);
        $chart = $charts ? $charts[0] : null;
        $runtimeChart = getDolGlobalInt('CHARTOFACCOUNTS');
        $validChart = $runtimeChart === $chartId && $chart && $chart['pcg_version'] === NorwayChartProfile::VERSION && $chart['country'] === 'NO' && (int) $chart['active'] === 1;
        $history = false;
        foreach (array('facture' => ' AND fk_statut<>0', 'facture_fourn' => ' AND fk_statut<>0', 'accounting_bookkeeping' => '') as $table => $where) {
            $history = (bool) self::rows($db, "SELECT rowid FROM ".$prefix.$table." WHERE entity=".$entity.$where.$db->plimit(1)) || $history;
        }
        $labels = array_column(NorwayChartPreview::parse(NorwayChartProfile::csv()), 'label', 'number');
        $rows = array(); $evidence = array();
        foreach ($mapping as $key => $number) {
            $accounts = self::rows($db, "SELECT rowid,label,active FROM ".$prefix."accounting_account WHERE entity=".$entity
                ." AND fk_pcg_version='".$db->escape(NorwayChartProfile::VERSION)."' AND account_number='".$number."' ORDER BY rowid");
            $evidence[$key] = $accounts;
            $current = (string) ($settings[$key] ?? '');
            $runtime = getDolGlobalString($key);
            $existing = false;
            // Preserve inherited choices as well, even when an empty local row masks them.
            foreach ($constants as $constant) {
                if ($constant['name'] === $key && !in_array(trim($constant['value']), array('', '-1'), true)) { $existing = true; }
            }
            $existing = $existing || !in_array(trim($runtime), array('', '-1'), true);
            $validAccount = count($accounts) === 1 && (int) $accounts[0]['active'] === 1 && $accounts[0]['label'] === $labels[$number];
            $rows[] = array('key' => $key, 'current' => $current, 'runtime' => $runtime, 'number' => $number, 'label' => $labels[$number],
                'action' => $existing ? 'Keep' : (!$validChart || !$validAccount || $history ? 'Blocked' : 'Fill'));
        }
        return array('entity' => $entity, 'chart' => $chart, 'runtime_chart' => $runtimeChart, 'constants' => $constants, 'history' => $history,
            'accounts' => $evidence, 'rows' => $rows);
    }

    public static function fingerprint($preview)
    {
        return hash('sha256', json_encode($preview, JSON_THROW_ON_ERROR));
    }

    public static function apply($db, $entity, $fingerprint, $user, $mapping = null)
    {
        if ((int) $entity <= 0 || (int) $GLOBALS['conf']->entity !== (int) $entity || empty($user->admin) || (int) $user->id <= 0) {
            throw new InvalidArgumentException('Confirmation');
        }
        require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
        $custom = $mapping !== null;
        if ($custom) {
            require_once __DIR__.'/NorwayStandardAccounts.php'; $mapping = NorwayStandardAccounts::validate($mapping);
            $active = self::rows($db,'SELECT @@in_transaction AS active');
            if (count($active)!==1 || (int)$active[0]['active']!==0 || !$db->query('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE')) { throw new RuntimeException('Defaults transaction'); }
        } else { $mapping = self::mapping(); }
        $names = "'CHARTOFACCOUNTS','".implode("','",array_keys($mapping))."'";
        $numbers = "'".implode("','",array_values($mapping))."'";
        $runtimeBefore = array();
        foreach ($mapping as $key => $number) {
            $runtimeBefore[$key] = array(property_exists($GLOBALS['conf']->global, $key), $GLOBALS['conf']->global->$key ?? null);
        }
        if ($db->begin() <= 0) { throw new RuntimeException('Defaults'); }
        try {
            $prefix = $db->prefix(); $entity = (int) $entity;
            // Lock chart, settings, accounts and source/history ranges before re-reading the preview.
            foreach (array(
                "SELECT rowid FROM ".$prefix."accounting_system WHERE pcg_version='".$db->escape(NorwayChartProfile::VERSION)."' FOR UPDATE",
                "SELECT rowid FROM ".$prefix."const WHERE entity IN (0,".$entity.") AND name IN (".$names.") FOR UPDATE",
                "SELECT rowid FROM ".$prefix."accounting_account WHERE entity=".$entity." AND account_number IN (".$numbers.") FOR UPDATE",
                "SELECT rowid FROM ".$prefix."facture WHERE entity=".$entity." FOR UPDATE",
                "SELECT rowid FROM ".$prefix."facture_fourn WHERE entity=".$entity." FOR UPDATE",
                "SELECT rowid FROM ".$prefix."accounting_bookkeeping WHERE entity=".$entity." FOR UPDATE"
            ) as $sql) { self::rows($db, $sql); }
            $preview = self::preview($db, $entity, $custom ? $mapping : null);
            if (!hash_equals(self::fingerprint($preview), (string) $fingerprint)) { throw new InvalidArgumentException('Changed'); }
            $saved = 0;
            foreach ($preview['rows'] as $row) {
                if ($row['action'] === 'Blocked') { throw new InvalidArgumentException('Blocked'); }
                if ($row['action'] !== 'Fill') { continue; }
                if (dolibarr_set_const($db, $row['key'], $row['number'], 'chaine', 0, 'Confirmed Norwegian setup', $entity) <= 0) {
                    throw new RuntimeException('Defaults');
                }
                $stored = self::rows($db, "SELECT value FROM ".$prefix."const WHERE entity=".$entity." AND name='".$db->escape($row['key'])."'");
                if (count($stored) !== 1 || $stored[0]['value'] !== $row['number']) { throw new RuntimeException('Defaults'); }
                $saved++;
            }
            if ($db->commit() <= 0) { throw new RuntimeException('Defaults'); }
            return $saved;
        } catch (Throwable $error) {
            $db->rollback();
            foreach ($runtimeBefore as $key => $old) {
                if ($old[0]) { $GLOBALS['conf']->global->$key = $old[1]; } else { unset($GLOBALS['conf']->global->$key); }
            }
            throw $error;
        }
    }
}
