<?php
// SPDX-License-Identifier: GPL-3.0-or-later
/** Native account classes for the bundled chart; never changes amounts or custom groups. */
class NorwayChartGroups
{
    public const SETTINGS = array(
        'ACCOUNTING_CLOSURE_ACCOUNTING_GROUPS_USED_FOR_BALANCE_SHEET_ACCOUNT' => '1,2',
        'ACCOUNTING_CLOSURE_ACCOUNTING_GROUPS_USED_FOR_INCOME_STATEMENT' => '3,4,5,6,7,8',
    );

    public static function accounts()
    {
        require_once __DIR__.'/NorwayChartProfile.php';
        $accounts = array();
        foreach (NorwayChartPreview::parse(NorwayChartProfile::csv()) as $row) {
            $class = substr($row['number'], 0, 1);
            if (!in_array($class, array('1','2','3','4','5','6','7','8'), true)) { throw new RuntimeException('Account class'); }
            $accounts[$row['number']] = $class;
        }
        return $accounts;
    }

    private static function rows($db, $sql)
    {
        $result = $db->query($sql);
        if (!$result) { throw new RuntimeException('Account groups: '.$db->lasterror()); }
        $rows = array();
        while ($row = $db->fetch_object($result)) { $rows[] = $row; }
        return $rows;
    }

    /** Idempotent 0.77 data repair, also used by the chart installer. No schema change. */
    public static function complete($db, $entity)
    {
        global $conf;
        if ((int) $entity <= 0 || (int) $entity !== (int) $conf->entity) { throw new InvalidArgumentException('Entity'); }
        require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
        require_once __DIR__.'/NorwayChartProfile.php';
        $runtime = array();
        foreach (self::SETTINGS as $name => $value) { $runtime[$name] = array(property_exists($conf->global,$name),$conf->global->$name ?? null); }
        if (!$db->begin()) { throw new RuntimeException('Account groups'); }
        try {
            $selected = self::rows($db, "SELECT value FROM ".$db->prefix()."const WHERE entity=".(int)$entity." AND name='CHARTOFACCOUNTS' FOR UPDATE");
            $chart = $selected ? self::rows($db, "SELECT s.pcg_version FROM ".$db->prefix()."accounting_system s INNER JOIN ".$db->prefix()."c_country c ON c.rowid=s.fk_country WHERE s.rowid=".(int)$selected[0]->value." AND s.active=1 AND c.code='NO'") : array();
            if (count($selected) !== 1 || count($chart) !== 1 || $chart[0]->pcg_version !== NorwayChartProfile::VERSION) {
                if (!$db->commit()) { throw new RuntimeException('Account groups'); }
                return 0;
            }
            $accounts = self::accounts();
            $rows = self::rows($db, "SELECT rowid,account_number,pcg_type FROM ".$db->prefix()."accounting_account WHERE entity=".(int)$entity." AND fk_pcg_version='".NorwayChartProfile::VERSION."' FOR UPDATE");
            $changed = 0;
            foreach ($rows as $row) {
                if (!isset($accounts[$row->account_number]) || !in_array(trim((string)$row->pcg_type), array('','XXXXXX'), true)) { continue; }
                if (!$db->query("UPDATE ".$db->prefix()."accounting_account SET pcg_type='".$accounts[$row->account_number]."' WHERE rowid=".(int)$row->rowid." AND entity=".(int)$entity)) { throw new RuntimeException('Account groups'); }
                $changed++;
            }
            foreach (self::SETTINGS as $name => $value) {
                $settings = self::rows($db, "SELECT value FROM ".$db->prefix()."const WHERE name='".$name."' AND entity IN (0,".(int)$entity.") FOR UPDATE");
                $existing = trim(getDolGlobalString($name)) !== '';
                foreach ($settings as $setting) { $existing = $existing || trim((string)$setting->value) !== ''; }
                if (!$existing && dolibarr_set_const($db,$name,$value,'chaine',0,'Norwegian account classes',(int)$entity) <= 0) { throw new RuntimeException('Account groups'); }
            }
            if (!$db->commit()) { throw new RuntimeException('Account groups'); }
            return $changed;
        } catch (Throwable $error) {
            $db->rollback();
            foreach ($runtime as $name => [$exists,$value]) { if ($exists) { $conf->global->$name=$value; } else { unset($conf->global->$name); } }
            throw $error;
        }
    }

    /** Read-only status for the existing setup overview, including locally added accounts. */
    public static function ready($db, $entity)
    {
        $rows = self::rows($db, "SELECT a.account_number,a.pcg_type FROM ".$db->prefix()."accounting_account a INNER JOIN ".$db->prefix()."accounting_system s ON s.pcg_version=a.fk_pcg_version INNER JOIN ".$db->prefix()."const c ON c.name='CHARTOFACCOUNTS' AND c.value=s.rowid AND c.entity=a.entity WHERE a.entity=".(int)$entity." AND s.pcg_version='NORWAY-NO-1'");
        if (!$rows) { return false; }
        $groups = array();
        foreach (self::SETTINGS as $name => $value) { $groups[$name] = array_map('trim',explode(',',getDolGlobalString($name))); }
        $balance = $groups['ACCOUNTING_CLOSURE_ACCOUNTING_GROUPS_USED_FOR_BALANCE_SHEET_ACCOUNT'];
        $income = $groups['ACCOUNTING_CLOSURE_ACCOUNTING_GROUPS_USED_FOR_INCOME_STATEMENT'];
        if (array_intersect($balance,$income)) { return false; }
        $seen=array();
        foreach ($rows as $row) {
            if (isset($seen[$row->account_number])) { return false; }
            $seen[$row->account_number]=true;
            $class = substr($row->account_number,0,1);
            if (!in_array($class,array('1','2','3','4','5','6','7','8'),true)
                || !in_array(trim((string)$row->pcg_type),in_array($class,array('1','2'),true) ? $balance : $income,true)) { return false; }
        }
        return true;
    }
}
