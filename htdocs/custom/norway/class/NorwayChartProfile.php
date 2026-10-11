<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require_once __DIR__.'/NorwayChartImport.php';
/** Pinned Norwegian chart from LGPL-3 Odoo l10n_no; see data/THIRD-PARTY-NOTICES.md. */
class NorwayChartProfile
{
    public const VERSION = 'NORWAY-NO-1';
    public const LABEL = 'Norsk kontoplan – Odoo l10n_no v1';

    public static function csv()
    {
        $csv = file_get_contents(__DIR__.'/../data/norwegian-chart-v1.csv');
        $source = json_decode(file_get_contents(__DIR__.'/../data/norwegian-chart-source.json'), true);
        if (!is_string($csv) || !is_array($source) || !hash_equals($source['sha256']['norwegian-chart-v1.csv'], hash('sha256', $csv))) {
            throw new RuntimeException('Profile');
        }
        return $csv;
    }

    private static function rows($db, $sql)
    {
        $result = $db->query($sql);
        if (!$result) { throw new RuntimeException('Profile'); }
        $rows = array();
        while ($row = $db->fetch_object($result)) { $rows[] = $row; }
        return $rows;
    }

    public static function preview($db, $entity)
    {
        if ((int) $entity <= 0) { throw new InvalidArgumentException('Profile'); }
        $prefix = $db->prefix();
        $settings = self::rows($db, "SELECT value FROM ".$prefix."const WHERE name='CHARTOFACCOUNTS' AND entity=".(int) $entity);
        $selected = $settings ? (int) $settings[0]->value : 0;
        $plans = self::rows($db, "SELECT s.rowid,s.label,s.active,c.code AS country FROM ".$prefix."accounting_system s"
            ." LEFT JOIN ".$prefix."c_country c ON c.rowid=s.fk_country WHERE s.pcg_version='".self::VERSION."'");
        $plan = $plans ? $plans[0] : null;
        if ($plan && ((int) $plan->active !== 1 || $plan->country !== 'NO' || $plan->label !== self::LABEL)) {
            throw new InvalidArgumentException('Profile');
        }
        // Core Accounting selects a stock chart by default even before any accounts exist.
        // A different selection may be replaced only in a completely empty entity.
        if (!$plan || $selected !== (int) $plan->rowid) {
            foreach (array('accounting_account', 'accounting_bookkeeping') as $table) {
                $counts = self::rows($db, "SELECT COUNT(*) AS total FROM ".$prefix.$table." WHERE entity=".(int) $entity);
                if ((int) $counts[0]->total > 0) { throw new InvalidArgumentException('Profile'); }
            }
        }
        if ($plan) {
            $report = NorwayChartPreview::report($db, $entity, $plan->rowid, self::csv());
        } else {
            $rows = NorwayChartPreview::parse(self::csv());
            foreach ($rows as &$row) {
                $row += array('existing_id' => 0, 'existing_label' => '', 'usage' => 0, 'status' => 'New');
            }
            unset($row);
            $report = array('rows' => $rows, 'chart' => self::VERSION, 'format' => NorwayChartPreview::FORMAT);
        }
        if (!NorwayChartImport::eligible($report)) { throw new InvalidArgumentException('Profile'); }
        return array('selected' => $selected, 'target' => $plan ? (int) $plan->rowid : 0, 'report' => $report);
    }

    public static function fingerprint($entity, $preview)
    {
        return hash('sha256', json_encode(array((int) $entity, $preview), JSON_THROW_ON_ERROR));
    }

    public static function install($db, $entity, $fingerprint, $user)
    {
        global $conf;
        if ((int) $entity !== (int) $conf->entity || empty($user->admin) || (int) $user->id <= 0) {
            throw new InvalidArgumentException('Profile');
        }
        require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
        if ($db->begin() <= 0) { throw new RuntimeException('Profile'); }
        try {
            // Lock entity selection before rechecking the installation fingerprint.
            if (!$db->query("SELECT rowid FROM ".$db->prefix()."const WHERE entity=".(int) $entity." AND name='CHARTOFACCOUNTS' FOR UPDATE")) {
                throw new RuntimeException('Profile');
            }
            foreach (array('accounting_account', 'accounting_bookkeeping') as $table) {
                if (!$db->query("SELECT rowid FROM ".$db->prefix().$table." WHERE entity=".(int) $entity." FOR UPDATE")) { throw new RuntimeException('Profile'); }
            }
            $preview = self::preview($db, $entity);
            if (!hash_equals(self::fingerprint($entity, $preview), (string) $fingerprint)) { throw new InvalidArgumentException('Profile'); }
            $chart = $preview['target'];
            if (!$chart) {
                $country = self::rows($db, "SELECT rowid FROM ".$db->prefix()."c_country WHERE code='NO' AND active=1");
                if (count($country) !== 1 || !$db->query("INSERT INTO ".$db->prefix()."accounting_system (fk_country,pcg_version,label,active,date_creation,fk_user_author) VALUES ("
                    .(int) $country[0]->rowid.",'".self::VERSION."','".$db->escape(self::LABEL)."',1,'".$db->idate(dol_now())."',".(int) $user->id.")")) {
                    throw new RuntimeException('Profile');
                }
                $chart = $db->last_insert_id($db->prefix().'accounting_system');
            }
            if ($preview['selected'] !== (int) $chart && dolibarr_set_const($db, 'CHARTOFACCOUNTS', $chart, 'chaine', 0, '', $entity) <= 0) {
                throw new RuntimeException('Profile');
            }
            $report = NorwayChartPreview::report($db, $entity, $chart, self::csv());
            $created = NorwayChartImport::apply($db, $entity, $chart, self::csv(), NorwayChartImport::fingerprint($entity, $chart, $report), $user);
            if ($db->commit() <= 0) { throw new RuntimeException('Profile'); }
            return $created;
        } catch (Throwable $error) {
            $db->rollback(); throw $error;
        }
    }
}
