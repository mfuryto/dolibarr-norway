<?php
// SPDX-License-Identifier: GPL-3.0-or-later
/** Actual core HTTP forms in the disposable installation; not a production helper. */
function nativeSetup($request)
{
    global $db,$conf;
    if (getenv('DOLI_COMPANY_NAME')!=='SYNTHETIC INSTALLATION ONLY' || getenv('NORWAY_EXPECT_EMPTY')!=='1') { throw new RuntimeException('Synthetic setup guard'); }
    $check=static function($ok,$why){if(!$ok){throw new RuntimeException($why);}};
    $sql=static function($sql)use($db,$check){$r=$db->query($sql);$check($r!==false,'Synthetic setup SQL: '.$db->lasterror());return $r;};
    $token=static function($body)use($check){$check(preg_match('/name="token"[^>]*value="([^"]+)"/',$body,$m)===1,'Native form token');return html_entity_decode($m[1],ENT_QUOTES,'UTF-8');};
    // Submit the form's actual values, preserving unrelated native settings.
    $values=static function($body,$action)use($check){
        $doc=new DOMDocument();@$doc->loadHTML($body);$xp=new DOMXPath($doc);$forms=$xp->query('//form[.//input[@name="action" and @value="'.$action.'"]]');$check($forms->length===1,'Native form missing: '.$action);$out=array();
        foreach($xp->query('.//input[@name]|.//select[@name]|.//textarea[@name]',$forms->item(0)) as $node){
            $name=$node->getAttribute('name');$type=$node->getAttribute('type');
            if($node->hasAttribute('disabled') || in_array($type,array('submit','button'),true) || (in_array($type,array('checkbox','radio'),true)&&!$node->hasAttribute('checked'))){continue;}
            if($node->nodeName==='select'){$chosen=$xp->query('.//option[@selected]',$node);if(!$chosen->length){$chosen=$xp->query('.//option',$node);}$out[$name]=$chosen->length?$chosen->item(0)->getAttribute('value'):'';}
            else{$out[$name]=$node->nodeName==='textarea'?$node->textContent:$node->getAttribute('value');}
        }return $out;
    };
    $p=$db->prefix();$entity=(int)$conf->entity;$banks=array();
    foreach(array('SALG'=>2,'KJOP'=>3,'BANK'=>4,'DIV'=>1) as $code=>$nature){
        $body=$request('/accountancy/admin/journals_list.php?id=35');
        $request('/accountancy/admin/journals_list.php?id=35',array('token'=>$token($body),'id'=>35,'actionadd'=>'Add','code'=>$code,'label'=>'Synthetic '.$code,'nature'=>$nature));
        $j=$db->fetch_object($sql("SELECT rowid,nature,active FROM ".$p."accounting_journal WHERE entity=".$entity." AND code='".$code."'"));
        $check($j && (int)$j->nature===$nature && (int)$j->active===1,'Native journal creation: '.$code);if($nature===4){$journal=(int)$j->rowid;}
    }
    $body=$request('/accountancy/admin/defaultaccounts.php');$data=$values($body,'update');
    $data['ACCOUNTING_ACCOUNT_CUSTOMER']='1500';$data['ACCOUNTING_ACCOUNT_SUPPLIER']='2400';
    $request('/accountancy/admin/defaultaccounts.php',$data);
    foreach(array('ACCOUNTING_ACCOUNT_CUSTOMER'=>'1500','ACCOUNTING_ACCOUNT_SUPPLIER'=>'2400') as $name=>$expected){$row=$db->fetch_object($sql("SELECT value FROM ".$p."const WHERE entity=".$entity." AND name='".$name."'"));$check($row&&$row->value===$expected,'Native default save');}
    // Renamed accounts and more than one bank are normal core setup, without a parallel helper.
    $old=$db->fetch_object($sql("SELECT rowid,label FROM ".$p."accounting_account WHERE entity=".$entity." AND fk_pcg_version='NORWAY-NO-1' AND account_number='1920'"));
    $check((bool)$old,'Synthetic bank ledger');$sql("UPDATE ".$p."accounting_account SET label='Synthetic renamed ledger' WHERE rowid=".(int)$old->rowid);
    try{
        foreach(array('SYN-NATIVE-A'=>'1920','SYN-NATIVE-B'=>'1960') as $ref=>$account){
            $body=$request('/compta/bank/card.php?action=create');
            $request('/compta/bank/card.php',array('token'=>$token($body),'action'=>'add','ref'=>$ref,'label'=>'Synthetic bank '.$ref,'type'=>1,'clos'=>0,'account_currency_code'=>'NOK','account_number'=>$account,'fk_accountancy_journal'=>$journal,'account_country_id'=>173,'proprio'=>'Synthetic owner','solde'=>0,'remonth'=>1,'reday'=>1,'reyear'=>2026));
            $row=$db->fetch_object($sql("SELECT rowid,account_number,fk_accountancy_journal FROM ".$p."bank_account WHERE entity=".$entity." AND ref='".$ref."'"));
            $check($row && $row->account_number===$account && (int)$row->fk_accountancy_journal===$journal,'Native bank setup: '.$ref);$banks[]=(int)$row->rowid;
        }
        // An already used entity can edit future defaults through the same native form.
        $sql("INSERT INTO ".$p."accounting_bookkeeping (entity,piece_num,doc_date,doc_type,doc_ref,fk_doc,fk_docdet,numero_compte,label_compte,debit,credit,fk_user_author,code_journal) VALUES (".$entity.",1,'2026-01-01','SYNSETUP','SYNSETUP',0,0,'1500','Synthetic',0,0,1,'DIV')");
        $history=(int)$db->last_insert_id($p.'accounting_bookkeeping');$snapshot=$db->fetch_object($sql('SELECT * FROM '.$p.'accounting_bookkeeping WHERE rowid='.$history));
        $body=$request('/accountancy/admin/defaultaccounts.php');$data=$values($body,'update');$data['ACCOUNTING_VAT_PAY_ACCOUNT']='2740';$request('/accountancy/admin/defaultaccounts.php',$data);
        $current=$db->fetch_object($sql("SELECT value FROM ".$p."const WHERE entity=".$entity." AND name='ACCOUNTING_VAT_PAY_ACCOUNT'"));
        $check($current && $current->value==='2740','Used setup blocks native settings');
        $check($snapshot==$db->fetch_object($sql('SELECT * FROM '.$p.'accounting_bookkeeping WHERE rowid='.$history)),'Native setup rewrites history');
        $body=$request('/compta/bank/card.php?id='.$banks[0].'&action=edit');$data=$values($body,'update');$data['label']='Synthetic changed bank label';$request('/compta/bank/card.php?id='.$banks[0],$data);
        $row=$db->fetch_object($sql('SELECT label,account_number,fk_accountancy_journal FROM '.$p.'bank_account WHERE rowid='.$banks[0]));$check($row&&$row->label==='Synthetic changed bank label'&&$row->account_number==='1920'&&(int)$row->fk_accountancy_journal===$journal,'Used native bank update lost mappings');
        $body=$request('/compta/bank/card.php?id='.$banks[0].'&action=edit');$data=$values($body,'update');$data['token']='invalid';$data['label']='Synthetic forbidden write';$request('/compta/bank/card.php?id='.$banks[0],$data);
        $protected=$db->fetch_object($sql('SELECT label FROM '.$p.'bank_account WHERE rowid='.$banks[0]));global $curl;$check($protected&&$protected->label==='Synthetic changed bank label'&&curl_getinfo($curl,CURLINFO_HTTP_CODE)<500,'Native setup CSRF protection');
    }finally{
        if(isset($history)){$sql('DELETE FROM '.$p.'accounting_bookkeeping WHERE rowid='.$history);}
        foreach($banks as $id){$sql('DELETE FROM '.$p.'bank WHERE fk_account='.$id);$sql('DELETE FROM '.$p.'bank_account WHERE rowid='.$id);}
        $sql("UPDATE ".$p."accounting_account SET label='".$db->escape($old->label)."' WHERE rowid=".(int)$old->rowid);
    }
    print "PASS native HTTP defaults, journal creation, renamed ledger, two banks, used setup, historical preservation and CSRF\n";
}
