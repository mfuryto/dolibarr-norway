<?php
// SPDX-License-Identifier: GPL-3.0-or-later
/** Read-only product/default candidates, not a simulation of invoice binding. */
class NorwayProductAccounts
{
    private static function rows($db,$sql)
    {
        $r=$db->query($sql);if (!$r) { throw new RuntimeException('Product accounts'); }
        $rows=array();while ($row=$db->fetch_object($r)) { $rows[]=(array)$row; }return $rows;
    }
    public static function report($db,$entity,$chartId,$defaults,$scope='domestic',$productId=null)
    {
        if (!is_int($entity) || $entity<=0 || $entity!==(int)$GLOBALS['conf']->entity || !in_array($scope,array('domestic','export','intra'),true) || ($productId!==null && (!is_int($productId) || $productId<=0))) { throw new InvalidArgumentException('Product account scope'); }
        $p=$db->prefix();$suffix=$scope==='domestic' ? '' : '_'.$scope;
        $charts=self::rows($db,'SELECT pcg_version,active FROM '.$p.'accounting_system WHERE rowid='.(int)$chartId);
        $chart=$charts ? $charts[0] : null;
        $products=self::rows($db,'SELECT rowid,ref,label,fk_product_type,tva_tx,tosell,tobuy,accountancy_code_sell'.$suffix.' AS sell,accountancy_code_buy'.$suffix.' AS buy FROM '.$p.'product WHERE entity='.$entity.($productId===null ? '' : ' AND rowid='.$productId).' AND fk_product_type IN (0,1) AND (tosell=1 OR tobuy=1) ORDER BY ref,rowid'.$db->plimit(201));
        $truncated=count($products)>200;$products=array_slice($products,0,200);$rows=array();$cache=array();
        foreach ($products as $product) {
            $type=(int)$product['fk_product_type']===1 ? 'SERVICE' : 'PRODUCT';
            foreach (array('sell'=>'SOLD','buy'=>'BUY') as $direction=>$verb) {
                if ((int)$product[$direction==='sell' ? 'tosell' : 'tobuy']!==1) { continue; }
                $explicit=(string)($product[$direction] ?? '');
                // Native product binding tests PHP empty(); a nonempty invalid code must not silently fall back.
                $hasExplicit=!empty($product[$direction]);
                $key='ACCOUNTING_'.$type.'_'.$verb.($suffix!=='' ? strtoupper($suffix) : '').'_ACCOUNT';
                $fallback=(string)($defaults[$key] ?? '');$number=$hasExplicit ? $explicit : $fallback;
                $status='Unset';$label='';
                if (!in_array($number,array('','0','-1','NotDefined'),true)) {
                    if (!array_key_exists($number,$cache)) {
                        $cache[$number]=$chart ? self::rows($db,"SELECT label,active FROM ".$p."accounting_account WHERE entity=".$entity." AND fk_pcg_version='".$db->escape($chart['pcg_version'])."' AND account_number='".$db->escape($number)."'".$db->plimit(2)) : array();
                    }
                    $accounts=$cache[$number];
                    $status=!$accounts ? 'Missing' : (count($accounts)>1 ? 'Ambiguous' : ((int)$chart['active']!==1 || (int)$accounts[0]['active']!==1 ? 'Inactive' : 'Available'));
                    if (count($accounts)===1) { $label=$accounts[0]['label']; }
                }
                $rows[]=array('id'=>(int)$product['rowid'],'ref'=>$product['ref'],'label'=>$product['label'],'type'=>$type,'rate'=>$product['tva_tx'],
                    'direction'=>$direction,'explicit'=>$explicit,'fallback'=>$fallback,'number'=>$number,'account_label'=>$label,'source'=>$hasExplicit ? 'Explicit' : 'Fallback','status'=>$status);
            }
        }
        return array('rows'=>$rows,'products'=>count($products),'truncated'=>$truncated,'scope'=>$scope);
    }
}
