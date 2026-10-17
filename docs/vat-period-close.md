# MVA, bank og periodeavslutning

Fra 0.80.0 er første leveringsvei manuell innlevering i Skatteetatens portal,
som valgt for denne leveransen. Modulen gir grunnlaget; den logger ikke inn,
signerer eller sender. Det er den faktiske portalkvitteringen som dokumenterer
levering. Ingen ny innsendingsstatus eller bekreftelsesrunde lagres i modulen.

## Vanlig arbeidsflyt

1. Registrer fakturaer, kreditnotaer og kjøp på vanlig måte. Velg eventuell
   fradragsandel på kjøpslinjen. Registrer betaling/refusjon mot dokumentet
   og riktig bankkonto; ikke registrer samme bevegelse en gang til som løst bilag.
2. Sammenlign bankens kontoutskrift med **Bank → aktuell konto → Avstemming**.
   Match også gebyrer, refusjoner og andre bevegelser. Et vanlig bankgebyr kan
   registreres som en diverse utbetaling mot 7770; riktig konto og eventuell
   avgift følger hva gebyret faktisk gjelder. Bankkontoens bokføringskonto
   (vanligvis 1920) og bankjournal må være angitt på bankkortet.
3. Overfør salgs-, kjøps- og bankjournalene til hovedboken med kjernens
   vanlige verktøy. Avstem 1500/2400 mot åpne kunde-/leverandørposter, og
   bokført bank mot kontoutskriften. Avstemming alene bokfører ikke journalen.
4. Åpne **Regnskap → Regnskap → Rapporter → MVA-uttrekk (støttede koder)**.
   Velg år og ordinær termin 1–6. Åpne eventuelle avvik ved kilden og rett
   dem før levering. Rapporten viser ørebeløp og bilag bak hver kode, samt
   en egen tabell med hele kroner til portalen. Kodene 0/7 er utenfor meldingen.
5. Åpne lenken til Skatteetaten, logg inn med nødvendig tilgang og velg
   riktig foretak, meldingstype, år og termin. Fyll inn de støttede kodene,
   grunnlag for utgående avgift og faktiske fradrag. Vurder eventuelle
   merknader eller andre forhold som ikke dekkes av denne modulen. Kontroller
   portalens valideringer og total, lever og last ned kvittering/betalingsinformasjon.
   Et avvist utkast eller en lagret kladd er ikke en levert melding.
6. Bruk rapportens **Opprett oppgjørsbilag**. Dette åpner kjernens bilagsskjema;
   fyll dato som siste dag i terminen, velg ordinær journal for diverse bilag
   og registrer de foreslåtte debet-/kreditlinjene. Fullfør det balanserte
   utkastet med kjernens godkjenningsknapp. Beløp på 2740 må stemme med faktisk
   kvittering; en avrundingsdifferanse føres på 7740. Forslaget skriver ingen
   hovedboksposter og tar hensyn til allerede bokførte oppgjør for samme termin.
7. Åpne **MVA-betalinger** fra rapporten. Opprett ett native MVA-oppgjør for
   terminen med kvitteringsbeløp og faktisk betalingsfrist. Under **Dokumenter**
   lastes kvitteringen og eventuelt CSV-grunnlaget fra rapporten opp. Dette
   bruker kjernens vanlige dokumentlagring og rettigheter. Navngi vedleggene
   med år, termin og versjon; behold også tidligere kvittering ved retting.
8. Registrer faktisk MVA-betaling mot oppgjøret og banken. Native **konto for
   MVA-betaling** i regnskapsinnstillingene skal være 2740, ikke ny kostnad
   eller ny inngående MVA. Avstem bankposten og overfør bankjournalen.
   Ved tilbakebetaling registreres den faktiske innbetalingen mot 2740 i
   kjernens bank-/bilagsflyt; ikke opprett en negativ leverandørfaktura.
9. Avstem MVA-kontoene mot oppgjøret og 2740 mot restbetaling/tilgodebeløp.
   Lukk den aktuelle bokføringsperioden i kjernen når grunnlaget er ferdig.
   Oppstartsoversikten lenker til native regnskapsperioder; den er ingen ny
   månedlig kontrollside. Bruk den native innstillingen som blokkerer lukkede
   perioder, ikke et oppsett uten periodesperre.

## Retting og registreringsovergang

Ved retting beholdes originaldokument og tidligere kvittering. Bruk vanlig
kreditnota eller nødvendig dokumentert korrigeringsbilag. En lukket periode
må eventuelt gjenåpnes uttrykkelig i kjernen av en bruker med rettighet før
rettingen bokføres; rapporten åpner den aldri selv. Velg den opprinnelige
terminen og lever en ny fullstendig melding i portalen. Rapporten viser hele
periodens oppdaterte tall; oppgjørsforslaget viser bare gjenstående differanse.
Avstem tidligere betaling mot det nye oppgjøret før en eventuell tilleggsbetaling.

Ved MVA-registrering bruker du kjernens foretaksinnstilling når registreringen
er bekreftet. En allerede utstedt faktura uten MVA som skal erstattes,
krediteres på vanlig måte og erstattes med ny faktura med riktig MVA. Ikke
overskriv det utstedte dokumentet. Vurder dokument-/termindato ut fra den
konkrete registreringen. Tilbakegående avgiftsoppgjør krever selvstendig
fradragsgrunnlag; et eventuelt manuelt avgiftsbilag kan klassifiseres med de
[eksisterende manuelle MVA-feltene](manual-vat.md). Et registeroppslag avgjør
ikke historisk fradragsrett.

## Dekning og bevis

Omfanget er ett foretak, NOK og ordinær tomånedlig melding: 3/31/33, 1/11/13,
5/6/52 og utenlandstjenester 86/87. Det er ikke kvalifisert vareimport,
primærnæring, årlig melding, kompensasjon, uttak eller andre særspesifikasjoner.
En rapport uten avvik beviser heller ikke at bilag utenfor systemet er registrert
eller at den enkelte leveransens avgiftsbehandling er juridisk riktig.

`tests/period-cycle.php` prøver en syntetisk periode med salg/kreditnota,
innenlandsk delvis fradrag, utenlandstjeneste med delvis beregnet fradrag,
native betaling/refusjon, gebyr og bankjournal. Uavhengig fasit er bank 515
før MVA, bokført netto MVA 167,50, portalforslag 168 og avrunding 0,50.
Native oppgjørsbilag, betaling 168 og ny bankavstemming gir bank 347,
oppgjørskonto og reskontro null. Egen avgiftskonto brukes for å prøve at
rapporten følger bokføringen. En korreksjon på 10 gir ny full melding 158
og oppgjørsdifferanse 10. En native tilbakebetaling på 10 gir bank 357 og
2740 null. Native kvitteringsopplasting/nedlasting prøves med
en tydelig syntetisk PDF og bytekontroll. Ingen reell myndighetsinnsending er gjort.

Prøven dekker også skuddårsgrense, lukket periodes bevaring av native utkast,
sperre mot ny bokføring, uttrykkelig gjenåpning og registreringsovergang med
uendret original. `tests/vat-report.php` prøver historisk lesing etter slettede/
endrede satser, kontokoblinger, inaktiv kontoplan og endret foretaksstatus.
Korte kode-/kontoroller i native `extraparams` er del av vanlig databasebackup;
ingen nye tabeller eller skjemamigrering inngår i 0.80.0. Gamle poster uten
slike roller leses bare når opprinnelig behandling kan belegges av lagret
kode, bokføringstekst og konto; uklar gammel spesialkontering må avklares.

## Kilder og valg av normalflyt

- [Skatteetaten: Se, endre og levere MVA-melding](https://www.skatteetaten.no/bedrift-og-organisasjon/avgifter/mva/mva-melding/)
  er inngangen til brukerens faktiske portalinnlevering og retting.
- [Skatteetaten: informasjonsmodell og kodelister](https://skatteetaten.github.io/mva-meldingen/mvameldingen/informasjonsmodell/)
  beskriver kode, grunnlag, fradrag, beløp og koder som ikke skal rapporteres.
- [Skatteetaten: ny som næringsdrivende](https://www.skatteetaten.no/bedrift-og-organisasjon/starte-og-drive/ny-som-naringsdrivende/oppstart/?pageid=482534)
  beskriver registrering og kreditering/ny faktura ved overgang til MVA.
- [PowerOffice: MVA-innstillinger](https://hjelpesenter.poweroffice.no/mva-innstillinger)
  viser ordinær tomånedlig/manuell levering og konto 2740/7740 som arbeidsflytreferanse.
- [Finago: opprett MVA-melding](https://finago.no/support/opprett-ny-mva-melding)
  beskriver hele kroner per meldingslinje. Forslaget fjerner øredelen etter
  summering per kode/retning; originalbilag og øregrunnlaget endres ikke.
  Faktisk fastsatt beløp fra portalen må alltid avstemmes før oppgjøret føres.
- [DNB Regnskap: levere MVA-melding](https://hjelp.dnbregnskap.dnb.no/no/article/levere-mva-melding-1lzxfb2/)
  viser normal rekkefølge og full ny melding ved retting. Leverandørreferansene
  er inspirasjon til arbeidsflyt, ikke selvstendige norske regelkilder.
