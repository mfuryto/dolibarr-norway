<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require_once __DIR__.'/NorwaySaftLedger.php';
require_once __DIR__.'/NorwaySaftMapping.php';
require_once __DIR__.'/NorwayChartProfile.php';
/** Read-only ENK annual basis. Reuse the complete ledger snapshot and existing account mapping. */
final class NorwayAnnualReport
{
    public static function fields($year)
    {
        if(!in_array((int)$year,array(2025,2026),true)){throw new RuntimeException('NorwayAnnualYear');}
        $root=__DIR__.'/../data/tax/';$manifest=json_decode(file_get_contents($root.'manifest.json'),true,32,JSON_THROW_ON_ERROR);$name='fields-'.(int)$year.'.csv';
        if(!isset($manifest['files'][$name]) || hash_file('sha256',$root.$name)!==$manifest['files'][$name]['sha256']){throw new RuntimeException('NorwayAnnualReference');}
        $stream=fopen($root.$name,'rb');$out=array();try{fgetcsv($stream,0,';','"','');while(($r=fgetcsv($stream,0,';','"',''))!==false){if(count($r)!==4 || isset($out[$r[0].'/'.$r[1]])){throw new RuntimeException('NorwayAnnualReference');}$out[$r[0].'/'.$r[1]]=array('category'=>$r[0],'code'=>$r[1],'label'=>$r[2],'polarity'=>$r[3]);}}finally{fclose($stream);}return $out;
    }
    private static function cent($amount){if($amount%1000000!==0){throw new RuntimeException('NorwaySaftPrecision');}return NorwayVatReport::cents($amount);}
    public static function build($db,$entity,$year,$user)
    {
        global $conf;
        $fields=self::fields($year);$start=(int)$year.'-01-01';$end=(int)$year.'-12-31';$p=$db->prefix();
        if(!$db->begin()){throw new RuntimeException('NorwaySaftDatabase');}
        try{
            $ledger=NorwaySaftLedger::read($db,$entity,$start,$end,$user,true);
            $mapping=NorwaySaftMapping::load($db,$entity,$year,array_keys($ledger['accounts']),true);
            $details=array();foreach(NorwaySaftMapping::rows($db,"SELECT a.account_number,a.label,a.fk_pcg_version,a.pcg_type FROM {$p}accounting_account a JOIN {$p}accounting_system s ON s.pcg_version=a.fk_pcg_version WHERE a.entity=".(int)$entity.' AND s.rowid='.(int)getDolGlobalInt('CHARTOFACCOUNTS')) as $a){$details[$a->account_number]=$a;}
            $original=array();foreach(NorwayChartPreview::parse(NorwayChartProfile::csv()) as $row){$original[$row['number']]=$row['label'];}
            $report=array('year'=>(int)$year,'fields'=>array(),'accounts'=>array(),'payments'=>array(),'assets'=>array(),'personal_premiums'=>array(),'issues'=>array(),'phone_private'=>0,'phone_projection'=>array(),'profit'=>0,'disposal'=>0,'known_addback'=>0,'known_deduction'=>0,'depreciation'=>0,'advance_tax'=>0,'additional_tax'=>0,'owner_cash'=>0,'opening_equity'=>0,'closing_equity'=>0,'unallocated_result'=>0);
            $income=array('salgsinntekt','annenDriftsinntekt','finansinntekt');$cost=array('varekostnad','loennskostnad','annenDriftskostnad','finanskostnad','skattekostnad');$balance=array('balanseverdiForAnleggsmiddel','balanseverdiForOmloepsmiddel','langsiktigGjeld','kortsiktigGjeld','egenkapital');
            $plGroups=array_filter(array_map('trim',explode(',',getDolGlobalString('ACCOUNTING_CLOSURE_ACCOUNTING_GROUPS_USED_FOR_INCOME_STATEMENT'))));
            $balanceGroups=array_filter(array_map('trim',explode(',',getDolGlobalString('ACCOUNTING_CLOSURE_ACCOUNTING_GROUPS_USED_FOR_BALANCE_SHEET_ACCOUNT'))));
            if(!$plGroups || !$balanceGroups){throw new RuntimeException('NorwayAnnualGroups');}
            $overrideFile=__DIR__.'/../data/tax/account-overrides.csv';$meta=json_decode(file_get_contents(__DIR__.'/../data/tax/manifest.json'),true,32,JSON_THROW_ON_ERROR);
            if(hash_file('sha256',$overrideFile)!==$meta['files']['account-overrides.csv']['sha256']){throw new RuntimeException('NorwayAnnualReference');}
            $stream=fopen($overrideFile,'rb');fgetcsv($stream,0,';','"','');$overrides=array();while(($r=fgetcsv($stream,0,';','"',''))!==false){$overrides[$r[0]]=array('source'=>$r[1].'/'.$r[2],'category'=>$r[3],'code'=>$r[4]);}fclose($stream);
            foreach($mapping as $n=>&$m){$d=$details[$n]??null;if($d && $d->fk_pcg_version===NorwayChartProfile::VERSION && ($original[$n]??null)===$d->label && isset($overrides[$n]) && $m['category'].'/'.$m['code']===$overrides[$n]['source']){$m=array('category'=>$overrides[$n]['category'],'code'=>$overrides[$n]['code']);}}unset($m);
            $known=array();$bankAccounts=array();
            foreach($ledger['accounts'] as $number=>$a){
                $d=$details[$number]??null;if(!$d){throw new RuntimeException('NorwaySaftAccountMapping:'.$number);}
                $known[$number]=$d->fk_pcg_version===NorwayChartProfile::VERSION && ($original[$number]??null)===$d->label;
                $opening=self::cent($a['opening']);$movement=self::cent($a['movement']);$closing=self::cent($a['closing']);
                $special=$known[$number] && in_array((string)$number,array('8800','8960','8968','8969','8990'),true);
                $private=$known[$number] && preg_match('/^20[67][0-9]$/D',(string)$number)===1;
                if(in_array($d->pcg_type,$plGroups,true)){if($special){$report['disposal']+=$movement;}else{$report['profit']-=$movement;}}
                if($known[$number] && in_array((string)$number,array('7799','7835'),true)){$report['known_addback']+=$movement;}
                if($known[$number] && (string)$number==='7825'){$report['known_deduction']-=$movement;}
                $m=$mapping[$number]??null;$key=$m?$m['category'].'/'.$m['code']:null;
                $report['accounts'][$number]=array('number'=>(string)$number,'label'=>$d->label,'opening'=>$opening,'movement'=>$movement,'closing'=>$closing,'field'=>$key,'special'=>$special,'private'=>$private,'shipped_meaning'=>$known[$number]);
                if($special){if(!in_array($d->pcg_type,$plGroups,true)){$report['issues'][]=array('key'=>'NorwayAnnualAccountGroup','detail'=>(string)$number);}continue;}
                if($private){if($closing!==0){$report['issues'][]=array('key'=>'NorwayAnnualPrivateOpen','detail'=>(string)$number);}continue;}
                if(!$key || !isset($fields[$key])){$report['issues'][]=array('key'=>'NorwayAnnualMapping','detail'=>(string)$number);continue;}
                $category=$m['category'];$isBalance=in_array($category,$balance,true);$sign=in_array($category,$income,true)||in_array($category,array('egenkapital','langsiktigGjeld','kortsiktigGjeld'),true)?-1:1;if($fields[$key]['polarity']==='negativ'){$sign=-$sign;}
                if(!$isBalance && !in_array($category,array_merge($income,$cost),true)){$report['issues'][]=array('key'=>'NorwayAnnualMapping','detail'=>(string)$number);continue;}
                if(!in_array($d->pcg_type,$isBalance?$balanceGroups:$plGroups,true)){$report['issues'][]=array('key'=>'NorwayAnnualAccountGroup','detail'=>(string)$number);}
                if(!isset($report['fields'][$key])){$report['fields'][$key]=$fields[$key]+array('opening'=>0,'value'=>0,'adjustment'=>0,'accounts'=>array(),'balance'=>$isBalance);}
                $report['fields'][$key]['opening']+=$sign*$opening;$report['fields'][$key]['value']+=$sign*($isBalance?$closing:$movement);$report['fields'][$key]['accounts'][]=(string)$number;
                if($known[$number] && in_array((string)$number,array('7799','7835'),true)){$report['fields'][$key]['adjustment']-=$movement;}
                if($known[$number] && (string)$number==='7825'){$report['fields'][$key]['adjustment']+=$movement;}
                if($category==='egenkapital'){$report['opening_equity']-=$opening;$report['closing_equity']-=$closing;}
                if($category==='balanseverdiForAnleggsmiddel'){$report['assets'][$number]=array('number'=>(string)$number,'label'=>$d->label,'opening'=>$opening,'increases'=>0,'reductions'=>0,'depreciation'=>0,'closing'=>$closing);}
                if($category==='balanseverdiForOmloepsmiddel' && $m['code']==='1920'){$bankAccounts[(string)$number]=true;}
                if($category==='annenDriftskostnad' && in_array($m['code'],array('6000','6004','6008'),true)){$report['depreciation']+=$movement;}
                if($category==='skattekostnad'){$report['issues'][]=array('key'=>'NorwayAnnualTaxExpense','detail'=>(string)$number);}
            }
            foreach(NorwaySaftMapping::rows($db,"SELECT account_number FROM {$p}bank_account WHERE entity=".(int)$entity) as $bank){$bankAccounts[(string)$bank->account_number]=true;}
            // Ledger lines are grouped by native voucher; do not treat owner clearing as payment.
            foreach($ledger['vouchers'] as $v){$rows=array_map(static fn($id)=>$ledger['lines'][$id],$v['lines']);$hasBank=false;$dep=0;$assetCredits=0;$assetDebits=0;
                foreach($rows as $line){$n=(string)$line['numero_compte'];if(isset($report['assets'][$n])){$delta=self::cent(NorwayVatReport::amount($line['debit'])-NorwayVatReport::amount($line['credit']));$assetCredits+=max(0,-$delta);$assetDebits+=max(0,$delta);}if(isset($bankAccounts[$n])){$hasBank=true;}$m=$mapping[$n]??null;if($m && $m['category']==='annenDriftskostnad' && in_array($m['code'],array('6000','6004','6008'),true)){$dep+=self::cent(NorwayVatReport::amount($line['debit'])-NorwayVatReport::amount($line['credit']));}}
                foreach($rows as $line){$n=(string)$line['numero_compte'];$amount=self::cent(NorwayVatReport::amount($line['debit'])-NorwayVatReport::amount($line['credit']));
                    if(isset($report['assets'][$n])){$report['assets'][$n]['increases']+=max(0,$amount);$report['assets'][$n]['reductions']+=max(0,-$amount);if($dep>0 && $amount<0){if($dep===$assetCredits && $assetDebits===0){if($report['assets'][$n]['depreciation']!==null){$report['assets'][$n]['depreciation']+=-$amount;}}else{$report['assets'][$n]['depreciation']=null;$report['issues'][]=array('key'=>'NorwayAnnualAssetAllocation','detail'=>(string)$v['piece']);}}}
                    if($hasBank && ($report['accounts'][$n]['private']??false)){
                        $report['owner_cash']+=$amount;
                        if(in_array($n,array('2077','2078'),true)){$report['personal_premiums'][]=array('date'=>$v['date'],'account'=>$n,'amount'=>$amount,'piece'=>$v['piece'],'reference'=>(string)$line['doc_ref']);}
                        if(in_array($n,array('2070','2072'),true)){$key=$n==='2070'?'advance_tax':'additional_tax';$report[$key]+=$amount;$report['payments'][]=array('date'=>$v['date'],'account'=>$n,'amount'=>$amount,'piece'=>$v['piece'],'reference'=>(string)$line['doc_ref']);}
                    }
                }
            }
            // Native yearly 2068 / phone-cost journal is already in profit. Present gross
            // communication cost and field 6998 without adding a second tax adjustment.
            foreach($ledger['vouchers'] as $v){$private=0;$phone=0;$onlyPhone=true;$ownerClearing=true;$privateBank=true;
                foreach($v['lines'] as $id){$line=$ledger['lines'][$id];$n=(string)$line['numero_compte'];$delta=self::cent(NorwayVatReport::amount($line['debit'])-NorwayVatReport::amount($line['credit']));
                    if(empty($known[$n])){$onlyPhone=false;}
                    if($n!=='2050' && empty($report['accounts'][$n]['private']) && $delta!==0){$ownerClearing=false;}
                    if($n!=='2068' && !isset($bankAccounts[$n]) && $delta!==0){$privateBank=false;}
                    if($n==='2068' && !empty($known[$n])){$private+=$delta;}elseif(!empty($known[$n]) && in_array($n,array('6900','6903','6907','6909'),true)){$phone+=$delta;}elseif($delta!==0){$onlyPhone=false;}
                }
                if(!$onlyPhone || $private===0 || $phone!==-$private){if($private!==0 && !$ownerClearing && !$privateBank){$report['issues'][]=array('key'=>'NorwayAnnualPhoneUnresolved','detail'=>(string)$v['piece']);}continue;}
                $costKey='annenDriftskostnad/6995';$benefitKey='annenDriftskostnad/6998';
                if(!isset($report['fields'][$costKey]) || !isset($fields[$benefitKey])){$report['issues'][]=array('key'=>'NorwayAnnualPhoneUnresolved','detail'=>(string)$v['piece']);continue;}
                if(!isset($report['fields'][$benefitKey])){$report['fields'][$benefitKey]=$fields[$benefitKey]+array('opening'=>0,'value'=>0,'adjustment'=>0,'accounts'=>array('2068'),'balance'=>false);}
                $report['fields'][$costKey]['adjustment']+=$private;$report['fields'][$benefitKey]['adjustment']+=$private;
                $report['phone_private']+=$private;$report['phone_projection'][]=array('piece'=>$v['piece'],'amount'=>$private);
            }
            if(isset($report['accounts']['7530']) && $report['accounts']['7530']['shipped_meaning'] && $report['accounts']['7530']['movement']!==0){$report['issues'][]=array('key'=>'NorwayDeductionPersonal','detail'=>'7530');}
            // Display missing source transfers instead of presenting an incomplete year as ready.
            foreach(array('customer_invoice'=>'facture','supplier_invoice'=>'facture_fourn') as $type=>$table){$r=NorwaySaftMapping::rows($db,"SELECT COUNT(*) n FROM {$p}{$table} f WHERE f.entity=".(int)$entity." AND f.fk_statut>0 AND f.datef>='$start' AND f.datef<='$end 23:59:59' AND NOT EXISTS (SELECT b.rowid FROM {$p}accounting_bookkeeping b WHERE b.entity=f.entity AND b.doc_type='$type' AND b.fk_doc=f.rowid)");if((int)$r[0]->n>0){$report['issues'][]=array('key'=>'NorwayAnnualUnposted','detail'=>$type.': '.(int)$r[0]->n);}}
            $r=NorwaySaftMapping::rows($db,"SELECT COUNT(*) n FROM {$p}bank b JOIN {$p}bank_account a ON a.rowid=b.fk_account WHERE a.entity=".(int)$entity." AND b.amount<>0 AND b.dateo>='$start' AND b.dateo<='$end 23:59:59' AND NOT EXISTS (SELECT g.rowid FROM {$p}accounting_bookkeeping g WHERE g.entity=a.entity AND g.doc_type='bank' AND g.fk_doc=b.rowid)");if((int)$r[0]->n>0){$report['issues'][]=array('key'=>'NorwayAnnualUnposted','detail'=>'bank: '.(int)$r[0]->n);}
            $draft=NorwaySaftMapping::rows($db,"SELECT COUNT(*) n FROM {$p}accounting_bookkeeping_tmp WHERE entity=".(int)$entity." AND doc_date>='$start' AND doc_date<='$end 23:59:59' AND (debit<>0 OR credit<>0)");if((int)$draft[0]->n>0){$report['issues'][]=array('key'=>'NorwayAnnualUnposted','detail'=>'bilagskladd: '.(int)$draft[0]->n);}
            $report['unallocated_result']=$report['profit']-$report['disposal'];
            $report['other_equity_movements']=$report['closing_equity']-$report['opening_equity']-$report['disposal']+$report['owner_cash'];
            // Native closing can dispose of profit only in next year's opening. Present
            // the same result in the annual equity basis without posting or double disposal.
            if($report['unallocated_result']!==0){
                $receiver=getDolGlobalString($report['unallocated_result']>0?'ACCOUNTING_RESULT_PROFIT':'ACCOUNTING_RESULT_LOSS');
                if($receiver==='' && isset($details['2050']) && $details['2050']->fk_pcg_version===NorwayChartProfile::VERSION && ($original['2050']??null)===$details['2050']->label){$receiver='2050';}
                $choice=$receiver!=='' && isset($details[$receiver])?NorwaySaftMapping::load($db,$entity,$year,array($receiver),true):array();$m=$choice[$receiver]??null;$key=$m?$m['category'].'/'.$m['code']:'';
                if(!$m || $m['category']!=='egenkapital' || !isset($fields[$key])){$report['issues'][]=array('key'=>'NorwayAnnualResultReceiver','detail'=>$receiver);}
                else{
                    if(!isset($report['fields'][$key])){$report['fields'][$key]=$fields[$key]+array('opening'=>0,'value'=>0,'adjustment'=>0,'accounts'=>array((string)$receiver),'balance'=>true);}
                    $report['fields'][$key]['adjustment']+=$report['unallocated_result']*($fields[$key]['polarity']==='negativ'?-1:1);
                }
            }

            $report['tax_basis_before_other_adjustments']=$report['profit']+$report['known_addback']-$report['known_deduction'];
            foreach($report['fields'] as &$field){$field['basis']=$field['value']+$field['adjustment'];}unset($field);
            uasort($report['fields'],static function($a,$b){return ($a['balance']<=>$b['balance'])?:strnatcmp($a['code'],$b['code']);});if(!$db->commit()){throw new RuntimeException('NorwaySaftDatabase');}return $report;
        }catch(Throwable $error){$db->rollback();throw $error;}
    }
}
