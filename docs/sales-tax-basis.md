> Historisk dokumentasjon til og med 0.64.0. Funksjonen er fjernet i 0.65.0.
> Bruk [gjeldende plan](delivery-plan.md) og kjernens arbeidsflyt. Eldre data beholdes i arkivet.

# Dokumentasjonsgrunnlag på salgsfaktura

Fra 0.63.0 ligger **Avgiftsgrunnlag** som egen fane på kundens faktura,
ved siden av fakturakontroll og kontrollkopier. Daglig dokumentasjon er ikke
lagt i oppstartsveilederen eller i regnskapsinnstillinger.

## Bruk

1. Kontroller eksplisitte, daterte salgskoblinger under regnskapsoppsettet.
2. Åpne fakturaens Avgiftsgrunnlag. For hver relevant linje, skriv hvorfor
   avgiftsbehandlingen gjelder og oppgi referanse til underlaget som oppbevares,
   for eksempel avtale eller dokumentnummer. Ikke legg hemmeligheter i feltene.
3. Forhåndsvis, les begrunnelsene og bekreft. Lagring oppretter en ny revisjon.
4. Ved endret faktura eller oppsett, vurder og bekreft et nytt grunnlag.
   Tidligere revisjoner bevares med bruker, UTC-tid, linjer og rågrunnlag.

Referansen er tekst, ikke automatisk dokumentopplasting, lenkeåpning eller
kontroll av at dokumentet finnes. Den som fører regnskapet må sikre at
referansen er forståelig og at underlaget oppbevares og kan fremlegges.

## Avgrensning og vern

Første profil gjelder ordinær type 0, NOK med kurs 1 og norsk kunde/selger,
i kladd, validert eller betalt tilstand. Innenlandske nullsatslinjer må ha
konsistent og eksplisitt kobling til SAF-T-kode 5, 6 eller 7. Profilen velger
ikke kode ut fra 0 % og skiller kategoriene. Positive satslinjer kan inngå
på samme faktura, men denne funksjonen dokumenterer bare nullsatslinjene.
Kreditnota, eksport, omvendt avgiftsplikt og spesialavgifter er utenfor profilen.

Begrunnelse og referanse kreves for samtlige aktuelle linjer. Manglende,
duplikate eller fremmede linjer avvises. Bare intern administrator med faktura-
og hovedboklesing kan lese; fakturaopprettingsrett kreves i tillegg ved skriving.
Forhåndsvisningen utløper etter ti minutter og må bekreftes med engangsnonce
og CSRF-token. Lagring leser grunnlaget på nytt i en serialiserbar transaksjon;
endringer eller mellomliggende revisjoner avviser bekreftelsen.

Fakturalinjer, dato, beløp, kundeidentitet og relevant konfigurasjon inngår i
rågrunnlaget. Betalingsstatus og fakturareferanse inngår ikke; vanlig betaling
eller tildeling av fakturanummer gjør ikke begrunnelsen foreldet. Andre
endringer, også en ny koblingsrevisjon, krever ny vurdering. Historikken er
begrenset til 100 revisjoner, 500 kB per revisjon og 5 MB totalt; den avkortes
ikke automatisk. Hash- og metadatakontroller avviser skadet historikk.

Payload versjon 1 bruker eksisterende evidenstabell med egen dokumenttype
`sales_tax_basis`. Ingen DDL eller endring av eldre bokføringsobservasjoner.
Standard databasebackup omfatter tabellen. Hashene oppdager utilsiktet skade;
de er ikke digital signatur eller vern mot en administrator som endrer databasen.

## Faglig grunnlag og videre arbeid

Skatteetaten skiller fritatt omsetning fra omsetning unntatt MVA-loven;
lik sats betyr ikke lik behandling. Se [fritak og unntak](https://www.skatteetaten.no/bedrift-og-organisasjon/avgifter/mva/slik-fungerer-mva/forskjellen-pa-fritak-og-unntak-fra-merverdiavgift/).
Håndbokens [§ 15-10 annet ledd om dokumentasjon](https://www.skatteetaten.no/en/rettskilder/type/handboker/merverdiavgiftshandboken/gjeldende/M-15/M-15-10/M-15-10.3/)
beskriver dokumentasjonskrav som avhenger av salget og grunnlaget for avgiftsfrihet.
En intern tekst og referanse erstatter ikke nødvendige salgsdokumenter eller
særskilt dokumentasjon. Kildene er lest 2026-10-15; profilen er prosjektets
begrensede dokumentasjonsfunksjon, ikke en påstand om full kravoppfyllelse.

## Kobling til faktisk bokføring fra 0.64.0

Ved en ny native bokføringshendelse for kundefaktura bevares siste tilgjengelige
begrunnelsesrevisjon i observasjonen når slik historikk finnes. Payload 9
bevarer begrunnelsen og, innenfor profilen, et eget låst dokumentgrunnlag.
Utenfor profilen bevares kildegrunnlaget som utilgjengelig og status som avvik. Samsvar krever
uendret begrunnelsesgrunnlag og samsvar med de felles faktura-, linje-, kunde-
og oppsettsfeltene i bokføringsobservasjonen. Mengde, pris og beskrivelse
bevares i det separate dokumentgrunnlaget; de er ikke nye felter i eldre
observasjoners rågrunnlag. Endret eller ikke støttet grunnlag vises som avvik.

Fakturafanen Avgiftsgrunnlag viser hver hendelse, tidspunkt, bevart revisjon,
begrunnelse og dokumentreferanse. Fakturakontrollen viser en oppsummering med
lenke hit. Hendelsens historiske samsvar og dagens endringsstatus er separate:
en begrunnelse som samsvarte da, kan fortsatt være historisk korrekt bevart
selv om dagens faktura eller oppsett er endret.

Uten lagret begrunnelse brukes fortsatt eksisterende payload 3/8; visningen
sier at ingen begrunnelsesrevisjon er bevart ved hendelsen. Det er ikke bevis
på at underlag ikke fantes utenfor systemet. Eldre payloads 1–8 tolkes som før,
og senere begrunnelser fylles aldri inn i dem. Registreringskontekst 1/2
beholdes også inne i payload 9, avhengig av oppsettet ved hendelsen.

Native transaksjon eier både postering og observasjon. Ingen separat commit
foretas av dokumentasjonskoblingen. Skadet begrunnelseshistorikk eller
overskredet kapasitet gir feil og rollback, ikke et stille bortfall av bevis.
Eksisterende grense på 1 MB per observasjon og 5 MB per fakturarapport gjelder
også de nye kopiene; store underlag kan kreve at omfanget reduseres eller at
kapasitetsmodellen videreutvikles før bokføring. Ingen automatisk avkorting.

Oppdaterings-/gjenopprettingstesten bevarer to begrunnelsesrevisjoner og to
native observasjoner som fortsatt peker på revisjon 1 etter revisjon 2.
Ingen DDL, endring av faktura/PDF, avgiftsberegning eller konteringsvalg.
Koblingen gjelder dokumentgrunnlag ved hver enkelt hendelse, ikke godkjenning
av komplett bilag, dokumentets rettslige tilstrekkelighet eller MVA-melding.
Videre A2 gjelder myndighetsgodkjenning/etterfakturering, manuelle bilag og
flere rettingsscenarioer. Den gir ingen godkjenning av avgiftsbehandlingen.
