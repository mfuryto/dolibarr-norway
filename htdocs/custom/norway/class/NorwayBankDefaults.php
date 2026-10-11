<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require_once __DIR__.'/NorwayChartProfile.php';
require_once __DIR__.'/NorwayBankSetup.php';
/** Confirmed fill-only mapping for one unused ordinary NOK bank account. */
class NorwayBankDefaults
{
    private static function rows($db, $sql)
    {
        $r=$db->query($sql); if (!$r) { throw new RuntimeException('Bank defaults'); }
        $rows=array(); while ($row=$db->fetch_object($r)) { $rows[]=(array)$row; } return $rows;
    }

    public static function preview($db, $entity)
    {
        if ((int)$entity<=0 || (int)$GLOBALS['conf']->entity!==(int)$entity) { throw new InvalidArgumentException('Entity'); }
        $entity=(int)$entity; $p=$db->prefix();
        $constants=self::rows($db,"SELECT entity,value FROM ".$p."const WHERE name='CHARTOFACCOUNTS' AND entity IN (0,".$entity.") ORDER BY entity");
        $chartId=$constants ? (int)end($constants)['value'] : 0;
        $charts=self::rows($db,"SELECT s.rowid,s.pcg_version,s.active,c.code AS country FROM ".$p."accounting_system s LEFT JOIN ".$p."c_country c ON c.rowid=s.fk_country WHERE s.rowid=".$chartId);
        $chart=$charts ? $charts[0] : null;
        $validChart=$chart && $chart['pcg_version']===NorwayChartProfile::VERSION && $chart['country']==='NO' && (int)$chart['active']===1 && getDolGlobalInt('CHARTOFACCOUNTS')===$chartId;
        // No actual bank number, IBAN, owner, credentials or balance is selected.
        $banks=self::rows($db,'SELECT rowid,ref,label,clos,courant,currency_code,account_number,fk_accountancy_journal FROM '.$p.'bank_account WHERE entity='.$entity.' ORDER BY rowid');
        $open=array_values(array_filter($banks,static function($b){return (int)$b['clos']===0;}));
        $bank=count($open)===1 ? $open[0] : null;
        $accounts=self::rows($db,"SELECT rowid,label,active FROM ".$p."accounting_account WHERE entity=".$entity." AND fk_pcg_version='".$db->escape(NorwayChartProfile::VERSION)."' AND account_number='1920' ORDER BY rowid");
        $labels=array_column(NorwayChartPreview::parse(NorwayChartProfile::csv()),'label','number');
        $journals=self::rows($db,'SELECT rowid,code,label FROM '.$p.'accounting_journal WHERE entity='.$entity." AND active=1 AND nature=4 AND TRIM(code)<>'' ORDER BY rowid");
        $history=(bool)self::rows($db,'SELECT b.rowid FROM '.$p.'bank b INNER JOIN '.$p.'bank_account a ON a.rowid=b.fk_account WHERE a.entity='.$entity.$db->plimit(1));
        foreach (array('accounting_bookkeeping'=>'','facture'=>' AND fk_statut<>0','facture_fourn'=>' AND fk_statut<>0') as $table=>$where) {
            $history=(bool)self::rows($db,'SELECT rowid FROM '.$p.$table.' WHERE entity='.$entity.$where.$db->plimit(1)) || $history;
        }
        $reason='Ready';
        if (!$bank) { $reason='Single'; }
        elseif ((int)$bank['courant']!==1 || $bank['currency_code']!=='NOK') { $reason='Type'; }
        elseif ($history) { $reason='History'; }
        elseif (!$validChart) { $reason='Chart'; }
        if ($bank && $reason==='Ready') {
            $check=(new NorwayBankSetup($db,$entity))->report($chartId)[0];
            if (!in_array($check['account_status'],array('Unset','Available'),true) || !in_array($check['journal_status'],array('Unset','Available'),true) || $check['shared_status']!=='Available') { $reason='Mappings'; }
        }
        $rows=array();
        foreach (array('account_number'=>'1920','fk_accountancy_journal'=>count($journals)===1 ? (string)$journals[0]['rowid'] : '') as $key=>$value) {
            $current=$bank ? (string)$bank[$key] : '';
            $empty=$key==='account_number' ? in_array(trim($current),array('','-1'),true) : (int)$current<=0;
            $valid=$key==='account_number' ? count($accounts)===1 && (int)$accounts[0]['active']===1 && $accounts[0]['label']===$labels['1920'] : count($journals)===1;
            // Also inspect closed accounts: reusing their ledger account may mix earlier activity.
            if ($key==='account_number') { foreach ($banks as $other) { if ((!$bank || $other['rowid']!==$bank['rowid']) && trim((string)$other['account_number'])==='1920') { $valid=false; } } }
            $rows[]=array('key'=>$key,'current'=>$current,'value'=>$value,'label'=>$key==='account_number' ? '1920 — '.$labels['1920'] : (count($journals)===1 ? $journals[0]['code'].' — '.$journals[0]['label'] : ''),
                'action'=>!$empty ? 'Keep' : ($reason==='Ready' && $valid ? 'Fill' : 'Blocked'));
        }
        return array('entity'=>$entity,'chart'=>$chart,'constants'=>$constants,'runtime_chart'=>getDolGlobalInt('CHARTOFACCOUNTS'),'banks'=>$banks,'bank'=>$bank,'accounts'=>$accounts,'journals'=>$journals,'history'=>$history,'reason'=>$reason,'rows'=>$rows);
    }

    public static function fingerprint($preview) { return hash('sha256',json_encode($preview,JSON_THROW_ON_ERROR)); }

    public static function apply($db,$entity,$fingerprint,$user)
    {
        if (empty($user->admin) || (int)$user->id<=0 || (int)$entity<=0 || (int)$GLOBALS['conf']->entity!==(int)$entity) { throw new InvalidArgumentException('Confirmation'); }
        if ($db->begin()<=0) { throw new RuntimeException('Bank defaults'); }
        try {
            $entity=(int)$entity; $p=$db->prefix();
            foreach (array(
                "SELECT rowid FROM ".$p."const WHERE name='CHARTOFACCOUNTS' AND entity IN (0,".$entity.") FOR UPDATE",
                "SELECT rowid FROM ".$p."accounting_system WHERE pcg_version='".$db->escape(NorwayChartProfile::VERSION)."' FOR UPDATE",
                'SELECT rowid FROM '.$p.'accounting_account WHERE entity='.$entity." AND account_number='1920' FOR UPDATE",
                'SELECT rowid FROM '.$p.'accounting_journal WHERE entity='.$entity.' FOR UPDATE',
                'SELECT rowid FROM '.$p.'bank_account WHERE entity='.$entity.' FOR UPDATE',
                'SELECT b.rowid FROM '.$p.'bank b INNER JOIN '.$p.'bank_account a ON a.rowid=b.fk_account WHERE a.entity='.$entity.' FOR UPDATE',
                'SELECT rowid FROM '.$p.'accounting_bookkeeping WHERE entity='.$entity.' FOR UPDATE',
                'SELECT rowid FROM '.$p.'facture WHERE entity='.$entity.' FOR UPDATE',
                'SELECT rowid FROM '.$p.'facture_fourn WHERE entity='.$entity.' FOR UPDATE'
            ) as $sql) { self::rows($db,$sql); }
            $preview=self::preview($db,$entity);
            if (!hash_equals(self::fingerprint($preview),(string)$fingerprint)) { throw new InvalidArgumentException('Changed'); }
            $changes=array();
            foreach ($preview['rows'] as $row) {
                if ($row['action']==='Blocked') { throw new InvalidArgumentException('Blocked'); }
                if ($row['action']==='Fill') { $changes[$row['key']]=$row['value']; }
            }
            if ($changes) {
                require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';
                $bank=new Account($db);
                if ($bank->fetch((int)$preview['bank']['rowid'])<=0 || (int)$bank->id!==(int)$preview['bank']['rowid']) { throw new RuntimeException('Bank defaults'); }
                $bank->entity=$entity; // The core fetch does not populate entity; the locked preview and UPDATE scope do.
                $bank->oldcopy=clone $bank;
                $assignments=array();
                foreach ($changes as $key=>$value) { $bank->$key=$value; $assignments[]=$key."='".$db->escape($value)."'"; }
                // A narrow update preserves every other bank field, including private payment data.
                if (!$db->query('UPDATE '.$p.'bank_account SET '.implode(',',$assignments).' WHERE rowid='.(int)$bank->id.' AND entity='.$entity)
                    || $bank->call_trigger('BANKACCOUNT_MODIFY',$user)<0) { throw new RuntimeException('Bank defaults'); }
                $stored=self::rows($db,'SELECT account_number,fk_accountancy_journal FROM '.$p.'bank_account WHERE rowid='.(int)$bank->id.' AND entity='.$entity);
                foreach ($changes as $key=>$value) { if (count($stored)!==1 || (string)$stored[0][$key]!==$value) { throw new RuntimeException('Bank defaults'); } }
            }
            if ($db->commit()<=0) { throw new RuntimeException('Bank defaults'); }
            return count($changes);
        } catch (Throwable $error) { $db->rollback(); throw $error; }
    }
}
