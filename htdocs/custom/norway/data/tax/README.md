# Årsbestemte ENK-felt

`fields-2025.csv` og `fields-2026.csv` er avledet fra Skatteetatens offisielle
`resultatregnskapOgBalanse`-kodelister, med filter `enkeltpersonforetak` og
`ingenRegnskapsplikt`. Kilde, revisjon, originalhash og avledet filhash står i
`manifest.json`. Postnavn og fortegn er hentet fra kodelisten. Begge år har
131 aktuelle poster. En årsmodell betyr ikke at årets portal er åpen ennå.

SAF-T-gruppelisten er ikke identisk med skattemeldingens ENK-felt. Rapporten
kontrollerer kandidatkoblinger fra eksisterende kontoplanoppsett mot årets
ENK-liste. De få eksplisitte forskjellene i `account-overrides.csv` gjelder
bare uendrede betydninger i medfølgende kontoplan. Ingen SAF-T-kobling eller
hovedbokspost skrives om. Ukjente/ikke relevante poster vises som uavklarte.

Avskrivning annet på 6030 går til ENK-post 6000; 6420 leie av datasystemer til
6400; 6551 direkte kostnadsført datautstyr til 6500; 6552 kostnadsført
programvare til 7600. Valg av kostnadsføring/aktivering må allerede være
riktig i regnskapet. Dette er ingen regel om at alle slike anskaffelser kan
kostnadsføres direkte. Immaterielle eiendeler med andre egenskaper og
særtilfeller må avklares særskilt.
