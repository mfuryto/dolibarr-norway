# Dekningsanalyse etter forenkling

Kontrollert 2026-10-17. Modul 0.76.0, støttet Dolibarr 24.0.1 ved
[kjernerevisjon b795838](https://github.com/Dolibarr/dolibarr/tree/b7958385f00a92219f76a4dfea67dae93df97a25).
Dette er en kildegjennomgang og beslutning om neste arbeid. Tredjepartstillegg
nedenfor er ikke installert eller funksjonstestet. Ingen norsk produksjonsaksept
følger av gjennomgangen. Nye leveranser følger [arbeidsplanen](delivery-plan.md).
Tidligere forslag om egne kontrollkopier og historikkmodeller videreføres ikke.

## Beslutninger

| Område | Hva som faktisk er funnet | Beslutning og gjenstående gap |
| --- | --- | --- |
| Ordinær regnskapsflyt | Kjernen har faktura, kreditnota, kjøpsjournal, hovedbok, reskontro, bank og perioder. Salgs-/kjøpsflyt, kreditnotaer og vern mot dobbeltføring inngår i 0.65-testene. | Behold kjernens arbeidsflyt. Dette kvalifiserer ikke alle avgiftsscenarioer eller årsavslutningen. |
| Norsk MVA-melding | Kjernen har faktura-/betalingsbaserte MVA-uttrekk, men ingen norsk melding eller innsendingsadapter ble identifisert i undersøkte kataloger. | Prøv uttrekk mot bokføringen før en avgrenset norsk rapporteringsutvidelse velges. En oversikt over MVA-satser eller saldo på MVA-kontoer er ikke komplett meldingsgrunnlag. |
| Norsk SAF-T | Kjernen har hovedbokseksport. Ingen norsk SAF-T-generator ble funnet i regnskaps-, MVA- eller modulkatalogene. | Norsk eksport er fortsatt et gap. Gjenbruk hovedbok og kildekoblinger; velg eksportutvidelse før egen generator. Ingen ny regnskapsmotor. |
| EHF/Peppol | Det finnes eksterne Peppol-moduler med dokumentgenerering og sending. | Prøv eksisterende løsning før egen integrasjon. Norsk profil, aktuelle PHP-/Dolibarr-versjoner, tilgangspunkt, lisens og kostnader må kvalifiseres. |
| Næringsspesifikasjon og skattemelding | Kjernen gir regnskapsdata, men ingen norsk årsoppgjørs-/skattemeldingsleveranse ble identifisert i gjennomgangen. Skatteetaten publiserer modeller og integrasjonsdokumentasjon. | Egen leveranse: årsversjon og foretaksform, kontokoblinger, skattemessige forskjeller, avstemming og konkret leveringsvei. Ikke anta at SAF-T eller en kontoplan utfører skatteberegningen. |

## MVA: konkret kjernedekning

I fast kjerneversjon bruker
[`compta/tva/index.php`](https://github.com/Dolibarr/dolibarr/blob/b7958385f00a92219f76a4dfea67dae93df97a25/htdocs/compta/tva/index.php)
`tax_by_rate()` for salg og kjøp. Funksjonen i
[`core/lib/tax.lib.php`](https://github.com/Dolibarr/dolibarr/blob/b7958385f00a92219f76a4dfea67dae93df97a25/htdocs/core/lib/tax.lib.php#L669)
leser fakturaer og linjer, med separate produkt-/tjenestegrener og valg av
faktura- eller betalingsgrunnlag. Den beholder lokale `vat_src_code` i
satsgrupperingen. Det gir gjenbrukbare kildeopplysninger; det skal ikke
feilaktig beskrives som om kjernen bare kjenner satsen.

Funksjonen leser også reiseregninger, men ikke `accounting_bookkeeping`.
Et manuelt bilag i hovedboken blir derfor ikke i seg selv med i denne
funksjonens MVA-uttrekk. Utslaget på en konkret norsk rapport og riktig
håndtering av datoer og fradrag må prøves med syntetiske data. Det er ikke
gjort en ny ende-til-ende-kvalifisering av MVA-meldingen i denne leveransen.

[Skatteetatens meldingsmodell](https://skatteetaten.github.io/mva-meldingen/mvameldingen/informasjonsmodell/)
bruker norske MVA-koder og egne spesifikasjoner, med intern kode som mulig
referanse. Den skiller hvilke felt som brukes for inngående og utgående
avgift. Lokal kode og sats må derfor kvalifiseres mot aktuell behandling.
[Innsendings-API-et](https://skatteetaten.github.io/mva-meldingen/documentation/api/)
har validering, innsending og tilbakemelding. En XML-fil alene er ikke
bekreftet innsending. Gjeldende tilgang og API-versjon må verifiseres før bruk.

## Resultat fra kjernens MVA-prøve

`tests/core-vat-report.php` kjøres etter ordinær salgs- og kjøpsflyt i den
isolerte integrasjonen. Fem fakturaer/kreditnotaer er først opprettet,
validert og overført gjennom kjernens journaler av `invoice-workflow.php`
og `core-purchases.php`. Testen prøver deretter vare-/tjenestegrenene,
koder og datoer som midlertidige varianter av disse syntetiske kildene.
Den er en karakterisering av kjernen, ikke en ny rapporteringsfunksjon.

| Prøve | Resultat i Dolibarr 24.0.1 |
| --- | --- |
| Fakturagrunnlag, varer og tjenester, uten betaling | Salg netto 1800/MVA 450 og kjøp netto 80/MVA 20, inkludert kreditnotaer, stemmer med de aktuelle hovedbokspostene. |
| Samme sats med to lokale koder | Kodene holdes i separate grupper; faktura og kreditnota med samme kode samles med riktig fortegn. |
| Fakturadato og betaling | Fakturagrunnlaget ligger i februar selv om betaling skjer senere. Betaling endrer ikke denne prøvens fakturabaserte avgift. |
| Betalingsgrunnlag | Ubetalte fakturaer utelates. Native halve betalinger i februar/mars gir 125 i salgsmva og 12,50 i kjøpsmva per måned for den betalte fakturaen. Dette prøver kjernens oppførsel, ikke at betalingsgrunnlag er riktig norsk valg. |
| Manuelt balansert MVA-bilag | +7 på inngående MVA vises i hovedboken, men fakturauttrekket forblir 20. |
| Separat balansert ompostering | -25 på inngående MVA endrer hovedboken ytterligere, men fakturauttrekket forblir 20. Netto inngående MVA i de valgte bilagene er nå 2. En ompostering blir ikke automatisk en oppdatert fradragsvurdering i fakturauttrekket. |
| Bevaring | Betalinger, bilag, kilder og datovarianter rulles tilbake. Antall rader og hash av de opprinnelige faktura-/linje-/hovedboksdataene kontrolleres etterpå. Ingen nye kontrollkopier opprettes. |

Kjernens dashboard beregner betalingsandel fra uttrekkets linjer. Testen
anvender samme formel ved prøven av betalingsgrunnlag; den er ikke en
nettlesertest av dashboardet. Kjerneversjonen gir også et kjent PHP-varsel
om manglende `datep` i tjeneste-/fakturagrenen. Bare dette eksakte varselet
registreres særskilt i prøven; øvrige feil undertrykkes ikke, og kjernen endres ikke.

**Beslutning:** gjenbruk fakturaenes kode, sats, beløp og dokumentkobling,
og kjernens hovedbok som avstemmingsgrunnlag. Kjernens fakturauttrekk kan ikke
alene være norsk MVA-melding når manuelle avgiftsbilag eller omposteringer
inngår. Ingen ny historikkmodell er besluttet av den grunn. Fradragsbehandling og blandede satser er fulgt opp nedenfor. Nullsatskategorier
og aktuelle utenlandstransaksjoner må fortsatt avklares.

Den første prøven ovenfor dekker 25 prosent. Utvidelsen nedenfor dekker
blandede satser og separate fradragsbilag. Manuelle bilags norske
avgiftskoder/grunnlag, registreringsovergang,
periodisering på grensedatoer, import/eksport og komplett norsk melding er
fortsatt uavklart. En regnskapsmessig ompostering er ikke i seg selv en
faglig godkjenning av fradragsrett.

## Blandede satser og fradrag prøvd i 0.66.0

`tests/core-purchases.php` er utvidet med faktura og kreditnota for varer og
tjenester, hver med tre ordinære linjer på 25, 15 og 12 prosent. Kjøpsjournalen
fører 25/15/12 til henholdsvis 2710/2711/2713 ut fra syntetiske, eksplisitte
satskontoer. Kostnad og leverandørgjeld balanserer. Testen velger ikke hvilke
varer/tjenester som faglig skal ha hver sats.

For netto 100 per linje blir kostnad 300, inngående MVA 52 og gjeld 352.
Et separat kjernebilag omposterer den valgte ikke-fradragsførbare andelen:

| Prøvd andel | Kostnad etter ompostering | Inngående MVA | Leverandørgjeld |
| --- | --- | --- | --- |
| Fullt fradrag | 300 | 52 | 352 |
| 50 prosent | 326 | 26 | 352 |
| Intet fradrag | 352 | 0 | 352 |

Kreditnotaene har netto -20 per linje og prøves med tilsvarende motsatt
fortegn. Kildens beløp og opprinnelig hovedbokspostering endres ikke; nye
bilag ligger separat med referanse til fakturaen. Gjentatt overføring lager
ikke duplikater, og ingen nye kontrollkopier skrives. Dette prøver teknisk
kontering av en oppgitt andel, ikke fastsettelse av fradragsrett eller komplett
MVA-rapportering. Arbeidsflyten er manuell, ingen ny automatisk funksjon.

**NPR-funnet:** kjernens `c_tva.recuperableonly` er merket «VAT NPR for France»
i skjemaet. `info_bits & 1` styrer en annen prisberegning i `core/lib/price.lib.php`.
En syntetisk NPR-linje på 100 + 25 prosent beholder MVA-felt 25, men får total
100. Den vanlige oversettelsen «Kan ikke dekkes inn»/«Non-recoverable» kan
villede ved norsk fradragsbehandling. 0.66.0 bruker kjernens eksisterende
oversettelsestabell for «Fransk NPR-ordning»/«French NPR scheme», avgrenset til
aktivt foretak og nb_NO/en_US. Ingen prisberegning, historiske dokumenter eller
kjerneregler endres. NPR-eksemplet beholdes som utkast og journalføres ikke.

**Beslutning:** behold kjernens kjøpsjournal og separate manuelle bilag.
Det trengs ingen egen kjøpsmotor, fradragsprosentmodell eller begrunnelsesside
for den prøvde arbeidsflyten. Norsk rapportering må likevel bruke faktiske
bokførte fradrag og håndtere kode/grunnlag for manuelle bilag. Dette løses
ikke ved å slå på global kostnadsføring av all kjøps-MVA. Nullsatskategorier,
utenlandstransaksjoner og komplett norsk melding er fortsatt egne gap.

## Native norske avgiftskoder i 0.67.0

Kjernen har `c_tva.code`, `type_vat` og salg-/kjøpskontoer. Fakturalinjer har
`vat_src_code`. Disse feltene gjenbrukes til seks ordinære koder i samme
MVA-oppsett: salg 3/31/33 og kjøp 1/11/13 for 25/15/12 prosent. Kontooppsettet
gjenkjenner bare riktig kombinasjon av kode, sats og bruk. Ingen egen
kodekoblingsmodell eller historikk lagres.

Native standardvalg tar ikke hensyn til `type_vat` i
`get_product_vat_for_country()`/`get_default_tva()`. En tom salgsvalgliste
kan derfor få et standardforslag med kjøpskode, som ikke finnes blant
salgsalternativene. Produktsalgsfeltene bruker også valglister uten angitt
retning. En smal `commonobject`/`load_tva`-hook gjenbruker kjernens widget
med riktig ordinær standardretning. Eksplisitte linjevalg, fremmed land,
nullsats, NPR og særkoder omklassifiseres ikke. Et eksplisitt/lagret kjøpskodevalg
på eksisterende produkt bevares for manuell vurdering.

`tests/vat-rates.php` prøver faktisk lagring av alle seks koder gjennom
native salgs-/leverandørfakturalinjer, kontoer og kjernens rendring av
salgs-/kjøpsvalglister og nytt produktkort. Konflikt, feil retning, eksisterende
kontovalg, produkter, dokumenter, samtidige endringer, rettigheter og
transaksjonsfeil sperrer automatisk omlegging. Deaktiverte ukodede rader,
0-prosent-rader og utenlandske rader beholdes.

**Gjenstående gap:** hovedboken har ikke et eget norsk kode-/grunnlagsfelt
på manuelle linjer. Kontonummer og bilagstekst alene er ikke en tilstrekkelig
erstatning. Rapportering og eventuell minimal metadatautvidelse må løse dette
uten kopier av hele dokumenter eller en ny bokføringsflyt. Det er ikke laget
en delvis rapport som fremstilles som komplett MVA-melding. Generelle API-kall
til standardoppslaget går ikke gjennom widget-hooken; integrasjoner skal angi
riktig kode uttrykkelig.

## Manuelle felt og ordinært uttrekk i 0.68.0–0.69.0

Det manuelle feltgapet ovenfor er løst med én liten oppføring per manuell
MVA-linje, ved det native bilaget. Ingen dokumentkopier eller revisjonsarkiv
opprettes. [Manuelle felt](manual-vat.md) beskriver tilgang og overgang.

[Det ordinære uttrekket](vat-report.md) gjenbruker dagens fakturakoder,
linjebeløp og hovedbok, samt de manuelle feltene. Native journalprøver av
varer/tjenester, tre satser, kreditnotaer og separate fradragsrettinger er
avstemt. Betaling påvirker ikke dette bokføringsbaserte periodevalget.
Ufullstendige kilder eller beløpsavvik gir ingen netto/CSV. Ingen kilde skrives
om. Rapporten har én inngang under de native regnskapsrapportene.

Dette dekker det påviste gapet for ordinære koder, ikke full norsk melding.
Nullsats/utland, registreringsovergang og blant annet oppgjør/åpningsposter
må kvalifiseres før et fullstendig uttrekk eller innsending kan godkjennes.

## SAF-T: kjerneeksport og kandidater

[`BookKeeping::export_bookkeeping()`](https://github.com/Dolibarr/dolibarr/blob/b7958385f00a92219f76a4dfea67dae93df97a25/htdocs/accountancy/class/bookkeeping.class.php#L2182)
henter konto, beløp, bilags-/dokumentreferanser, journal og valideringsdato,
men ikke et ferdig norsk avgiftsgrunnlag og norske avgiftskoder.
[Regnskapsmodulens eksportoversikt](https://wiki.dolibarr.org/index.php/Module_Accounting_Advanced)
er et startpunkt for gjenbruk; generell TSV/FEC-eksport er ikke norsk SAF-T.

[Offisiell SAF-T-dokumentasjon](https://www.skatteetaten.no/en/business-and-organisation/start-and-run/rutiner-regnskap-og-kassasystem/saf-t-financial/documentation/)
angir 1.40 som format fra 2027. Det tidligere undersøkte
[1.40-skjemaet ved fast revisjon](https://github.com/Skatteetaten/saf-t/blob/05179521e435d82feb0b2d6c89a92a32a4f2d02f/SAF-T_Financial_1.4/Norwegian_SAF-T_Financial_Schema_v_1.40.xsd)
har kontoenes `GroupingCategory`/`GroupingCode` og avgiftsklassifisering.
Kontokoblinger til dette formatet må holdes atskilt fra en komplett
skattemelding med næringsspesifikasjon.

[Dolibarr SAF-T Portugal](https://github.com/cialinux/dolibarr-saft/tree/91dc9aed1a7783b18ac4d14dd5289e1558befc93)
beskriver portugisisk validering/import via en kommersiell API-tjeneste.
Den er ikke dokumentert som norsk Financial-eksport og velges ikke som løsning.
Det er ikke kopiert kode. Avgrensede søk på GitHub/DoliStore fant ingen
bekreftet norsk eksportmodul; dette beviser ikke at ingen eksisterer.

## EHF: gjenbruk først

[CAP-REL Peppol](https://doc.cap-rel.fr/peppol/) beskriver BIS Billing 3.0,
generering, sending/mottak, status og flere tilgangspunkt. Dokumentasjonen
krever en tilgangspunktavtale. DoliStore-søket viste nyere opplysninger enn
den åpnete produktsiden; eksakt versjon, PHP-støtte, pris og norsk dekning
regnes derfor som uavklart. Tillegget er en kandidat, ikke en valgt avhengighet.

To åpne kandidater er også funnet:

- [marcrant1/dolibarr-peppol-export](https://github.com/marcrant1/dolibarr-peppol-export/tree/c8d965b1cfc4080880e4a9b51439e4f1a39eceff): README beskriver UBL, kreditnota og Peppyrus, testet på eldre Dolibarr-versjoner. Lisensfil og faktisk 24.0.1-kompatibilitet må undersøkes.
- [cedric-marcoux/dolibarr_peppol-peppyrus](https://github.com/cedric-marcoux/dolibarr_peppol-peppyrus/tree/cede93ae75730d56cbb472e3e2cf6f896b9d54d9): GPL-3.0-repo som beskriver XML, sending og status via Peppyrus. Norske identifikatorer, kreditnotaer og støttet versjon er ikke kvalifisert her.

Ikke legg README-påstander om gratis tilgang til grunn for en driftsavtale.
Ingen konto, kjøp, abonnement, mottakerregistrering eller sending er utført.
[DFØs EHF-veiledning](https://www.anskaffelser.no/verktoy/veiledere/ehf-fakturering)
er formatreferansen for norsk faktura/kreditnota. Før valg må XML og norsk
profil valideres separat fra transportprøven med et tilgangspunkt.

## Næringsspesifikasjon og skattemelding

[Skatteetaten om ENK](https://www.skatteetaten.no/bedrift-og-organisasjon/skatt/skattemelding-naringsdrivende/enk/)
skiller levering via nettsiden fra levering gjennom regnskaps-/årsoppgjørssystem;
ENK med regnskaps- eller revisjonsplikt må bruke system. Installasjonen må
avklare hvilken leveringsvei som gjelder. Det er ikke ett generelt krav om
at denne modulen selv skal være innsendingssystem.

[Skatteetatens leverandørrepo](https://github.com/Skatteetaten/skattemeldingen)
har modeller, kodelister, test- og API-dokumentasjon. README har historiske
inntektsårseksempler; riktig inntektsårs modell må velges før implementasjon.
API-tilgang må bestilles og vilkår avklares ved eventuell direkte integrasjon.

Leveransen skal omfatte kontokoblinger, relevante skattemessige forskjeller
og avskrivninger, tilleggsopplysninger og avstemming mot årsavslutningen.
Velg en konkret overføring til egnet årsoppgjørsløsning eller integrasjon.
En summering av regnskapskontoene eller SAF-T-eksport er ikke tilstrekkelig
bevis på at hele næringsspesifikasjonen er ferdig.

## Avgrensning

Ingen ny rapportmotor, kontrollside, databaseendring eller integrasjon er
implementert i denne gjennomgangen. Foretaksformer og bransjer får bare
støtte etter konkrete prøver. Lønn, kassasystem og andre betingede områder
utvider ikke automatisk leveransen. Papirløs flyt tas fortsatt til slutt.

## Kjøpskode 0: konkret lagringsgap

I den låste Dolibarr 24.0.1-revisjonen bruker satsvelgeren sannhetsverdien av
`code` i `core/class/html.form.class.php`, og `fournisseur.facture.ligne.class.php`
bruker `empty(vat_src_code)` i både INSERT og UPDATE. Teksten «0» mister derfor
klassifiseringen selv når den er eksplisitt valgt. Et ordinært satsvalg NO0
bevares av kjernen; 0.71.0 bruker dette som fast lokalt navn for norsk kode 0
og oversetter ved lesing i det eksisterende uttrekket. Ingen kjerneendring,
trigger som skriver om bilag, ny koblingstabell eller egen velger trengs.
Ukodede nullkjøp og utenlandsbehandling blir ikke automatisk klassifisert.

## Eksportkode 52: gjenbruk og rapportgap

Den fastlåste kjernen viser en eksplisitt norsk salgskode 52 også med
utenlandsk kjøper, bevarer koden i kundefakturalinjer og overfører nullavgift
via vanlig salgsjournal. Ingen eksportmotor eller egen satsvelger trengs.
Før 0.72.0 manglet valget i modulens kodeoppsett, og uttrekket avviste alle
utenlandske kunder. Utvidelsen legger til kodevalget og avgrenser rapportens
utenlandske salg til eksplisitt 52. Native NOK-fakturaer/kreditnotaer med
varer/tjenester, SE/US, reskontro, kontoer og CSV er prøvd. Kundeland er ingen
automatisk fritaksvurdering. Utenlandskjøp/omvendt avgiftsplikt må fortsatt
prøves separat; gjeldende grunnlag og avgift kan ikke utledes av nullsats alene.

## Kode 86: native omvendt avgiftsplikt og konkret rapportgap

Kjøpsjournalen har allerede en reverse-charge-gren. Den styres av fakturaens
`vat_reverse_charge`, `ACCOUNTING_FORCE_ENABLE_VAT_REVERSE_CHARGE` og for
leverandører utenfor EU `ACCOUNTING_REVERSE_CHARGE_ALSO_NON_EEC`. Beregningen
bruker katalogproduktets `tva_tx`, ikke leverandørens nullsats. Kredit/debet
hentes fra de native globale kontoene. SQL-skjemaet for `c_tva` har ingen egne
reverse-charge-kontofelt i støttet versjon, selv om journalen har reservekode
for slike verdier. Det innføres derfor ingen ekstra native kolonner.

0.73.0 prøver og bruker denne flyten med 25 %, full fradragsrett og 2704/2714.
Satsvelgeren søker ellers bare i den utenlandske selgerens land. Den eksisterende
hooken gjenbruker den norske kjøperens native liste ved en utenlandsk
leverandørfaktura med omvendt avgiftsplikt, uten å endre motpart eller velge kode
86 automatisk. Det eksisterende uttrekket avstemmer faktisk beregnet og
fradragsført MVA i begge retninger under 86. Ingen ny bokføringsmotor,
lagringsmodell eller kontrollside. Kode 87 og delvis fradrag er neste gap,
ikke noe som utledes fra en nullsats eller fremmed leverandør alene.

## Kode 87 på manuelle kjøpsbilag

Kjernens automatiske reverse-charge-gren fører både kredit- og debetavgift.
Den har ikke en dokumentert, kvalifisert norsk null-fradragsflyt i dette
prosjektet. I 0.74.0 utvides derfor bare de eksisterende manuelle MVA-feltene
med 87 på konto 2704, samt lesing i samme uttrekk. Hele kjøpet må være manuelt
bokført, med avgiften i kostnaden og uten inngående fradrag. Ingen ny
fakturasats, kjøpsmotor, tabell, kontrollside eller betalingsflyt innføres.
Kodevalget er uttrykkelig og endrer ikke hovedboken. Kode 87 på leverandør-
fakturaer og delvis fradrag gjenstår; en vanlig faktura skal ikke bokføres
på nytt som manuelt kjøp for å bruke denne støtten.


## Delvis fradrag på den native 86-flyten i 0.75.0

Kjernen bokfører fullt fradrag, men dekker en separat reduksjon gjennom vanlige
rettingsbilag. Det konkrete rapporteringsgapet var at 2714-rettingen manglet
kodevalg. Eksisterende manuelle MVA-felt støtter nå kode 86 på aktiv 2714;
rapporten summerer faktisk retting med fakturaens inngående avgift. Utgående
grunnlag og beregnet avgift forblir uendret. Ingen ny modell, side eller
bokføringsmotor. Fakturaflyten krever fortsatt en gyldig native 86-sats;
manuell kode kan ikke erstatte en deaktivert fakturasats.

`tests/vat-report.php` prøver SE/US-kjøp og kreditnotaer med faktisk native
journaloverføring og separate 50 %-rettinger, begge fortegn. CSV viser retting
som inngående 86 uten grunnlag/sats. Manglende kode, feil grunnlag, feil konto,
inaktiv konto og endret kilde avvises. Lesing endrer ikke regnskapsdata.
Dette kvalifiserer den dokumenterte manuelle rettingen, ikke automatisk valg
av fradragsandel eller kobling ved kreditnota. Se [rutine og primærkilder](manual-vat.md#delvis-fradrag-på-tjenestekjøp-kode-86-i-0750).


## Referanser for normal arbeidsflyt

Lest 2026-10-17 etter brukerens presisering om enkel, vanlig regnskapsflyt.
Dette er leverandørdokumentasjon om brukeropplevelse, ikke selvstendige
rettskilder eller dokumentasjon på at Dolibarr støtter samme funksjoner.

| Problemstilling | Referanse | Relevant mønster |
| --- | --- | --- |
| Forholdsmessig MVA | [Tripletex: Slik fører du forholdsmessig mva](https://hjelp.tripletex.no/hc/no/articles/18168752046225-Slik-f%C3%B8rer-du-forholdsmessig-mva) | Fradragsprosent settes på en MVA-kode som velges i vanlig bilagsføring. Systemet beregner fradrag og kostnadsført rest. |
| Forholdsmessig MVA | [DNB Regnskap: Forholdsmessig mva](https://hjelp.dnbregnskap.dnb.no/no/article/forholdsmessig-mva-16zoi3c/) | Fradragsgruppe kan knyttes til konto eller velges ved bilagsføring; fradragsprosent kan også angis der. |

Referansene viser alternativer til å bokføre fullt fradrag og deretter kreve
et separat bilag ved hvert kjøp. Artiklene kvalifiserer ikke i seg selv den
konkrete utenlandsflyten, kreditnotaer eller norske konto-/kodemappinger.
Disse delene må undersøkes og prøves særskilt. Det skal ikke utledes et behov
for egne koderegistre, grupper eller prosentskjema fra sammenligningen alene.

0.66.0/0.75.0 sine separate fradragsrettinger og 0.74.0 sin manuelle 87-flyt
klassifiseres nå som **reserveveier**. De tekniske prøvene beholdes, men de
viser ikke ferdig normalflyt. Gjenstående gap er å håndtere riktig fradrag
og kostnad ved vanlig registrering/bokføring, med bevart leverandørfaktura,
betalingsoppfølging og tilsvarende behandling ved kreditnota. Før flere
importtilfeller prioriteres, gjennomgås disse rutinene mot kjernen og aktuelle
tillegg. En eventuell endring skal bevare regnskapsdata og lesing av tidligere
klassifiserte bilag. Ingen runtime-endring eller ny funksjon innføres her.


## Oppfølging i 0.76.0

[Den fullstendige gjennomgangen](review-0.75.0.md) samler R01–R17.
[Leveranseplanen](delivery-plan.md) erstatter fortløpende enkeltplaner med
én rekkefølge og en felles akseptmatrise for hele arbeidsflyter. Oppstarts-
veilederen er nå én lesende statustabell med lenker til kjernens verktøy og
tydelig uferdig støtte. Ingen ny godkjenningsmodell eller kontrollhistorikk.
Filutrullingen fjerner eksplisitt utgåtte eide PHP-filer; installasjonskontrollen
avviser ekstra kjørbar kode. Øvrige funn lukkes først med bevis i relevant flyt.
