<?php
// SPDX-License-Identifier: GPL-3.0-or-later
/** Explain booked deduction evidence; entitlement and personal facts are never inferred. */
final class NorwayDeductions
{
    public static function topics($report)
    {
        $base='https://www.skatteetaten.no/';
        $topics=array(
            'Phone'=>array('numbers'=>array('6900','6903','6907','6909'),'url'=>$base.'bedrift-og-organisasjon/skatt/skattemelding-naringsdrivende/fradrag/eiendeler-utstyr-eiendom/telefon-og-internett/'),
            'Equipment'=>array('numbers'=>array('6500','6510','6520','6530','6540','6551','6552'),'url'=>$base.'bedrift-og-organisasjon/skatt/skattemelding-naringsdrivende/fradrag/eiendeler-utstyr-eiendom/bedriftens-eiendeler/kjope-eiendeler-til-bedriften/'),
            'Premia'=>array('numbers'=>array('7400','7410','7500','7530'),'url'=>$base.'bedrift-og-organisasjon/skatt/skattemelding-naringsdrivende/fradrag/utgifter-til-pensjon-sykepenger-og-forsikring/'),
            'Home'=>array('numbers'=>array('6300','6320','6340','6345','6360','6399'),'url'=>$base.'bedrift-og-organisasjon/skatt/skattemelding-naringsdrivende/fradrag/eiendeler-utstyr-eiendom/bruke-egen-bolig-i-naring/'),
            'Startup'=>array('numbers'=>array(),'url'=>$base.'bedrift-og-organisasjon/starte-og-drive/ny-som-naringsdrivende/oppstartsperiode/'),
        );
        $names=array();foreach(NorwayChartPreview::parse(NorwayChartProfile::csv()) as $a){$names[$a['number']]=$a['label'];}
        foreach($topics as $key=>&$topic){$topic['amount']=$key==='Startup'?null:0;$topic['accounts']=array();
            foreach($topic['numbers'] as $number){$a=$report['accounts'][$number]??null;if(!$a){continue;}
                // Renamed/custom accounts remain visible as unresolved, never shown as zero evidence.
                if(empty($a['shipped_meaning']) || ($names[$number]??null)!==$a['label']){$topic['amount']=null;}
                elseif($topic['amount']!==null){$topic['amount']+=$a['movement'];}$topic['accounts'][]=$number;
            }
            if($key==='Equipment' && $topic['amount']!==null){$topic['amount']+=$report['depreciation'];}
        }unset($topic);return $topics;
    }
}
