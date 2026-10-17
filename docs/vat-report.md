# MVA-uttrekk for ordinære koder

Fra modul 0.69.0 ligger **MVA-uttrekk (ordinære koder)** under
**Regnskap → Regnskap → Rapporter** i den vanlige menyen. Velg fra- og
med / til- og med-dato i samme kalenderår. Standardvalget er inneværende
måned. Uttrekket leser bokføring og eksisterende kilder uten å endre dem.
Det oppretter ingen kontrollkopier, historikk eller bekreftelsesoppgaver.

## Hva som vises

Ordinære norske koder 3/31/33 og 1/11/13 dekker henholdsvis utgående og
inngående MVA ved 25/15/12 prosent. Varer, tjenester og kreditnotaer inngår.
Rapporten viser grunnlag og sats for utgående MVA. Inngående MVA vises som
positivt fradrag; tilbakeføring reduserer fradraget. Netto for de ordinære
kodene er utgående minus inngående MVA. Negativ netto betyr til gode for
disse kodene, ikke nødvendigvis for hele MVA-meldingen.

Fakturaenes eksisterende `vat_src_code`, sats og beløp brukes sammen med
bokføringen. Fakturaer tas med først når de er overført til hovedboken.
Validerte fakturaer i perioden som mangler slik overføring vises som
uavklarte. Dato er bokføringsdato; ulik fakturadato, delvis overføring eller
posteringer fordelt på flere perioder/bilag må avklares. Betaling flytter
ikke avgiften i dette uttrekket.

MVA-koder og tilhørende kontoer må finnes entydig i det native satsregisteret.
Rapporten bruker dagens fakturakilde og kontokoblinger; den utgir dem ikke
for å være et arkiv av det opprinnelige oppsettet. Endrede kontokoblinger kan
gi avvik mot eldre bokføring. Historiske dokumenter omklassifiseres ikke.

[Manuelle MVA-opplysninger](manual-vat.md) brukes på egne manuelle bilag,
inkludert separate fradragsrettinger. Avgiften kommer fra den faktiske
hovedbokslinjen. Manuelt utgående grunnlag kommer fra det lagrede feltet.
Utløpt kildefingeravtrykk, manglende kode eller feil beløpsforhold gjør linjen
uavklart. Låste bilag leses; rapporten åpner eller endrer dem ikke.

Under **Bilag og fakturaer bak beløpene** kan hver kilde åpnes i Dolibarrs
vanlige kort. CSV inneholder én rad per dokument/kode eller manuell
MVA-linje, med periode, kildetype, dokument-/linje-ID, bilagsnummer, kode,
retning og NOK-beløp. Summeringen i rapporten kan dermed etterprøves.
Referanser er interne ID-er; CSV inneholder ikke kunder, fritekst eller
regnearkformler fra bilagene.

## Avvik og avgrensning

Kildenes grunnlag/sats må samsvare med avgiften, og fakturaens netto/brutto
må stemme med bokført reskontrobeløp. Kildenes avgift per konto må stemme med bokførte MVA-beløp i NOK, også
når flere koder deler konto. Avrunding summeres over åtte lagrede desimaler
med heltall før omregning til øre. Ubalanse i et bilag innenfor perioden,
manglende kode, ukjent kilde, feil dato, NPR/lokalavgifter og behandling
utenfor de ordinære kodene vises ved kilden. Delsummer kan leses, men netto
og CSV er utilgjengelig så lenge slike poster finnes. CSV-kallet håndhever
samme regel og beregner rapporten på nytt ved nedlasting.

Kontoer fra satsregisteret og MVA-standardene, samt norske 270x/271x-kontoer,
brukes til å finne mulige manuelle MVA-poster som må klassifiseres. Dette er
ikke en automatisk avgiftsklassifisering. Flytting til oppgjørskonto og
andre manuelle bevegelser på MVA-kontoer blir heller ikke gjettet bort;
slike uklassifiserte bevegelser gir uavklart uttrekk inntil deres behandling
er kvalifisert. Egendefinerte MVA-kontoer må være registrert i oppsettet.

**Dette er ikke en fullstendig norsk MVA-melding eller innsending.**
Nullsats, fritak/unntak, utenlandshandel, registreringsovergang,
avgiftsoppgjør/åpningsposter og andre særtilfeller er ikke kvalifisert.
Øvrige manuelle hovedbokslinjer uten MVA-klassifisering telles separat;
manuell omsetning uten MVA blir ikke automatisk med. En avstemt ordinær
oversikt bekrefter derfor ikke at alle forhold i meldingen er dekket.
CSV er et avstemmingsuttrekk, ikke en XML-fil for import eller innsending.

## Tilgang og drift

Intern bruker trenger lesetilgang til både bokføring og regnskapsrapporter.
Aktivt foretak brukes; forespørsler kan ikke velge et annet foretak. Konto,
kilde og hovedbok leses i én repeatable-read-transaksjon i den låste MariaDB-
konfigurasjonen. Rapporten avbryter hvis denne isolasjonen ikke er tilgjengelig,
eller beløp ligger utenfor heltallsberegningens støttede område. Lesing eller
CSV gir ingen bokførings-/periodestatus og krever ingen ekstra godkjenning.
Ingen skjemaendring inngår i 0.69.0.

## Konkret gap og prøver

Kjernens `tax_by_rate` utelater manuelle hovedboksbilag. Kjernen har heller
ikke et ferdig norsk kodeuttrekk som avstemmer fakturaer og manuelle
fradragsrettinger mot bokføringen. Dette gapet er dokumentert i
[dekningsanalysen](coverage-analysis.md). Den native kjøps-/salgsbokføringen
og de eksisterende kodefeltene gjenbrukes; ingen ny bokføringsmotor innføres.

`tests/vat-report.php` lager syntetiske salgs-/leverandørfakturaer gjennom
native objekter, validerer dem og overfører gjennom de virkelige journalene.
Varer/tjenester, blandede satser, øreavrunding, kreditnotaer og separat
fradragsretting inngår. Feil kode, kildeendring, periode-/beløpsavvik, utenlandsk
motpart, foretaksskille og tilgang prøves. Ufullstendig CSV avvises også via
HTTP; fakturaer, bokføring og metadata sammenlignes før og etter lesing.
Menyplassering, oppgradering og backup/gjenoppretting inngår i øvrig CI.

[Skatteetatens informasjonsmodell](https://skatteetaten.github.io/mva-meldingen/mvameldingen/informasjonsmodell/)
skiller kode og avgift fra grunnlag/sats, som bare fylles ut for utgående MVA.
Modellen har også behandlinger som dette avgrensede uttrekket ikke dekker.
Kilden ble lest 2026-10-17. Teknisk avstemming er ikke godkjenning av selve
avgiftsbehandlingen eller full regnskapsmessig produksjonsaksept.
