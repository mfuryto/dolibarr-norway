# Gjennomgang av dolibarr-norway 0.75.0

Dato: 2026-10-17. Gjennomgått grunnlag: main/staging ved
`96b20e9209527087081cd391fbf085ab42a0eadb`, modul 0.75.0 og låst Dolibarr 24.0.1.
Ingen program-, database-, konto- eller driftsendringer er utført i gjennomgangen.

Gjennomgangen omfatter modulens funksjonsområder: 44 PHP-filer, derav 24
klassefiler og 14 adminsider, nummermodeller, triggere, CSS, kontoplandata,
migreringer, installasjons-/oppdateringsskript, Compose, repositoryvern,
48 dokumentasjonsfiler og testopplegget. Det er en gjennomgang av prosjektets
løsning og arbeidsflyt, ikke en bekreftelse på tilstanden på en bestemt server
eller en garanti for at all kjernens kode er feilfri.

## Resultat og prioritering

Det er både bekreftede tekniske feil, unødvendig kompliserte arbeidsflyter og
avtalte funksjoner som ennå ikke er levert. Disse må behandles forskjellig.
En nødvendig retting gir ikke mandat til å bygge en ny bokføringsmotor,
kontrollmodell eller flere godkjenningssider.

### R01 — Høy: Utgått kode kan bli liggende etter oppdatering

**Bekreftet ved isolert reproduksjon.** `deploy/start-web.sh:9` kopierer ny
modul over gammel med `cp -a`, uten å fjerne filer som er slettet fra pakken.
`scripts/verify-installation.php:12` kontrollerer bare at leverte filer finnes
med riktig hash. Ekstra PHP-filer blir ikke oppdaget.

Prøve: Hent modulfilene fra revisjonen før opprydding `a898f32`, kopier dagens
modul over med samme overlappende kopisemantikk. Resultat: **42 utgåtte filer
beholdes**, samtidig som alle nye filers hash stemmer. Blant filene er
`admin/purchasededuction.php`, `admin/registration.php`, `admin/taxmappings.php`
og de tilhørende gamle skriveklassene. Dette er ikke bevis på at en bestemt
gammel handling lykkes på dagens database, men viser at kode som skulle vært
fjernet fortsatt kan være til stede. Ingen gammel skrivehandling ble kjørt.

**Endring:** Bruk kontrollert distribusjon av modulens eide filer, med fjerning
av utgåtte kjørbare filer. Bevar andre moduler, regnskapsdata og historikk.
Kontroller både manglende/endrede og uventede eide filer. Test oppgradering fra
en faktisk gammel filtremodell, og at gamle skriveadresser ikke kan brukes.
Å skjule menyer alene er utilstrekkelig.

### R02 — Høy: Kontoplanen mangler kjernens kontotyper

**Bekreftet i importkoden og kjernen.** `NorwayChartImport.php:48–54` fyller
nummer, navn, plan og aktivstatus. `pcg_type` og øvrig rapportklassifisering
settes ikke. Kjernens `AccountingAccount::create()` erstatter tom `pcg_type`
med `XXXXXX`. Årsavslutningens saldoutvalg bruker `pcg_type`.

Odoo-kilden har `account_type` og avstemmingsinformasjon, men den leverte
nummer-/navnfilen og importen overfører ikke disse egenskapene. Dette betyr
ikke at alle kontonumrene er feil. Det betyr at 745 importerte kontoer ikke
utgjør en ferdig rapporterings- og årsavslutningsprofil.

**Endring:** Behold kontolisten og kilde/lisens. Kvalifiser mapping til kjernens
balanse-/resultatgrupper og relevante kontoegenskaper. Bruk native felt.
For eksisterende regnskap kreves en avgrenset, kontrollert overgang som ikke
flytter posteringer eller overskriver egne klassifiseringer. Prøv resultat,
balanse og saldoovergang på et lite, komplett regnskap før dette kalles ferdig.
NS 4102-sertifisering og SAF-T-mapping følger ikke automatisk av arbeidet.

### R03 — Høy: Ingen/delvis fradrag er gjort til manuell etterbehandling

**Bekreftet arbeidsflyt.** 0.66.0 beskriver innenlandske kjøp med fullt fradrag
først og separat ompostering. 0.74.0 fører hele utenlandskjøpet manuelt under
87 og mister fakturamodulens betalingsoppfølging. 0.75.0 beholder fakturaen,
men krever separat 86-retting og motsatt retting ved kreditnota.

**Endring:** Fradragsbehandling skal inngå i vanlig kjøps-/bilagsregistrering
og gi riktig kostnad, MVA og leverandørgjeld ved bokføring. Kreditnota skal
følge tilsvarende behandling. Undersøk kjernen og eksisterende tillegg før
minste nødvendige tilpasning velges. Det er ikke besluttet en ny prosentmodell
eller et nytt koderegister. Behold rapportlesing av tidligere manuelle bilag;
reserveveiene skal ikke brukes som ferdigkriterium for normalflyten.

### R04 — Høy: Også den native utenlandsflyten er for snever

**Bekreftet avgrensning.** `actions_norway.class.php:14` og
`NorwayVatReport.php:122` støtter den prøvde 86-flyten. Den forutsetter
katalogtjeneste, sats på produktkort, fakturaflagg og flere oppsettsvalg.
Fritekstkjøp er utenfor prøvd omfang. Kjøpsjournalen leser produktets gjeldende
sats; endring før overføring kan endre beregningen.

**Endring:** Kvalifiser vanlige tjenestekjøp, også uten produktkatalog.
Avgiftsbehandlingen må være forståelig ved registrering og beregningsgrunnlaget
må følge dokumentet fram til bokføring. Samle nødvendige engangsinnstillinger
på relevant oppsettsside. Ikke anta at utenlandsk land alene avgjør behandlingen.

### R05 — Høy: Eldre MVA-uttrekk avhenger av dagens oppsett

**Bekreftet i rapporten og dens negative tester.** `NorwayVatReport.php:73`
henter dagens aktive kodevalg og kontokoblinger. Endring/deaktivering av en kode
kan gjøre et gammelt uttrekk uavklart, selv om gamle posteringer ikke er endret.
Rapporten bruker også dagens foretaksstatus. Avvikssperren er forsiktig, men
historisk lesing er ikke ferdig løst.

**Endring:** Skill kildemangel fra senere oppsettsendring. Gjenbruk lagrede
fakturakoder, dokumentbeløp og bokføring slik at en legitim oppsettsendring ikke
i seg selv ødelegger lesing av eldre perioder. Avklar minste nødvendige
historiske opplysninger før eventuell ekstra lagring. Ikke gjeninnfør det
utgåtte generelle kontrollarkivet.

### R06 — Høy: Fakturakrav er ikke ferdig kvalifisert

**Bekreftet dekningsgap, ikke bevis på at alle PDF-er er feil.**
`NorwayInvoiceCheck.php:41–45` lar levering, MVA og PDF stå til gjennomgang.
Validering dekker bare utvalgte felt. PDF-prøvene dekker blant annet språk,
valuta og organisasjonsnummer, men ikke en komplett norsk dokumentmatrise.
Intern `fk_facture_source` kreves for kreditnota, uten en kvalifisert overgang
for kreditering av faktura fra tidligere system.

**Endring:** Prøv faktiske dokumenter for avtalt omfang, inkludert leverings-
opplysninger, avgiftsbehandling og foretaksform. Bruk standardfelt og maler
før tillegg. Undersøk kreditnota ved systembytte før regelen eventuelt endres;
bevar kravet om dokumentert originalreferanse. Ikke lag en ny kontrollside.

### R07 — Middels: Oppsettet er fragmentert og har overlapp

**Bekreftet.** `admin/accounting.php:8` viser egne sider for kontoplan,
kunde-/leverandørstandard, øvrige standardkontoer, satser, satskontoer,
journaler og bankkobling, ved siden av kjernens innstillinger.
Kunde-/leverandørfeltene finnes også i katalogen på standardkontosiden.
Flere sider har egen forhåndsvisning, midlertidig bekreftelse og lagring.

**Endring:** Bruk ett forståelig norsk oppsett der et særskilt norsk behov
finnes; ellers lenk til kjernens relevante innstilling. Fjern overlappende
kunde-/leverandørside når samme oppgave er dekket. Vurder sats-/kontoutfylling
samlet slik at brukeren ikke sitter igjen med et halvferdig oppsett mellom
flere sider. Reduser overflødige brukerbekreftelser; behold CSRF-vern,
rettigheter, transaksjoner og beskyttelse mot utilsiktet overskriving.

### R08 — Middels: Bankkoblingen dupliserer vanlig bankoppsett

**Bekreftet.** `NorwayBankDefaults.php:24–50` forutsetter én ordinær åpen
NOK-bankkonto, én egnet journal og fravær av regnskapshistorikk. To ordinære
bankkonti fører til at hjelperen ikke kan brukes. Dette er modulens
hjelperbegrensning, ikke en generell grense i kjernen.

**Endring:** Bruk bankkortets konto-/journalfelt som normalvei. Fjern den
separate hjelperen og dens veiviseroppgave hvis den ikke gir en nødvendig
forenkling der. Bevar eksisterende koblinger og støtte for flere bankkonti.
Ingen ny bankintegrasjon er bestilt med denne oppryddingen.

### R09 — Middels: Oppsettshjelperne har for brede sperrer

**Bekreftet.** `NorwayDefaultAccounts.php:56` og tilsvarende kode i
bank-/MVA-hjelperne krever bestemte opprinnelige kontonavn. Historikk sperrer
store deler av utfyllingen. Satsoppsettet sperres også av produkter eller
utkast. Flere lagringer låser alle faktura-/hovedboksrader i foretaket.

**Endring:** Ikke bruk ordrett kontonavn som identitet for en regnskapskonto.
Skill trygg nyutfylling og framtidig oppsett fra endringer som påvirker
historiske dokumenter. Bruk konkrete avhengigheter fremfor generell sperre
ved enhver historikk. Dette må prøves før sperrer løsnes: native bokføring av
eldre, ennå ikke overførte fakturaer kan faktisk bruke dagens reservekontoer.
Begrens låsing til nødvendige rader/områder når den nye flyten er bestemt.

### R10 — Middels: Nummerseriene er unødvendig koblet sammen

**Bekreftet i oppsettslogikken.** `NorwayNumbering.php:143–167` lager ett felles
utstedelsesgrunnlag for faktura/kreditnota/tilbud. Bruk av én serie kan fryse
endring av en annen, ubrukt serie. Kunde-/leverandørstart låses tilsvarende
mot ett felles partsgrunnlag. Fingeravtrykket inkluderer alle partsrader.

**Endring:** Behold bestilt F/K/T med seks sifre og valgbare K/L-prefikser med
fem sifre. Avgrens vern etter serie og faktisk bruk. Forenkle oppsettet uten
å gi adgang til omnummerering eller gjenbruk av utstedte numre. Undersøk om
native masker kan dekke mer av implementasjonen; migrer ikke modell eller
teller bare for å få færre filer. Legg til relevant samtidig nummereringstest.

### R11 — Middels: Nettlesertesten og deler av akseptgrunnlaget er foreldet

**Bekreftet.** `tests/browser-layout.cjs:16` starter på en slettet fakturaside
og bruker videre slettede kontrollsider og gammel bevisregistrering. Den er
ikke del av påkrevd CI. De siste manuelle nettleserprøvene for MVA var separate
midlertidige skript og dekker ikke hele den oppgitte gamle sidematrisen.
CI simulerer gammel modulversjon, men ikke et helt gammelt filtre; derfor ble
R01 ikke oppdaget.

**Endring:** Oppdater testene til dagens brukeroppgaver og negative tester for
fjernede skriveadresser. Test faktisk oppgradering, normal kjøpsflyt og
kreditnota, historisk rapport etter oppsettsendring, klassifisert kontoplan og
uavhengige nummerserier. Bruk disse som aksept før støtte loves. Behold
relevante eksisterende tester; ikke skriv tester som bare gjentar nye detaljer.

### R12 — Middels: Dokumentasjonen gir motstridende instruksjoner

**Bekreftet.** `docs/default-accounts.md`, `docs/bank-defaults.md` og deler av
`docs/standard-accounts.md` beskriver utgåtte kontrollsider. `docs/menu-integration.md`
sier én meny selv om det nå er to. `docs/test-and-operations.md` inneholder blant
annet gammel 0.1.0-pakke og utdaterte beskrivelser av tester som ikke var kjørt.
Historiske beskrivelser er blandet med gjeldende bruk.

**Endring:** Én oppdatert bruksveiledning og tydelig adskilt leveransehistorikk.
Rett lenker, versjoner og beskrivelse av hva som er levert. Beskriv koder med
forståelige behandlinger. Skill lovkrav, brukerønske, teknisk begrensning og
reservevei. Et vedlikeholdsfunn skal ikke utløse en ny brukerbekreftelse.

### R13 — Middels: Backup og oppdatering har et mellomrom med nye skriv

**Bekreftet rekkefølge; konkret datatap er ikke observert.**
`backup-installation.sh:25–31` stopper web under backup, men starter den igjen
ved retur. `update-installation.sh:14–17` bygger deretter og erstatter
containeren. Nye posteringer kan derfor komme etter backupen. Ved senere
restore av denne backupen må slike endringer håndteres særskilt.

**Endring:** Definer et kort, sammenhengende vedlikeholdsvindu fra siste
konsistente backup til ferdig oppdatering, eller en annen prøvd metode som
bevarer skriv i mellomrommet. Prøv feil etter oppstart/migrering og dokumenter
trygg gjenoppretting. Ikke innfør automatisk rollback som kan slette nye bilag.
Behold backup av database, dokumenter, konfigurasjon, moduler og kjørende image.

### R14 — Middels: Deaktivering med aktiv Norway-nummerering må kvalifiseres

**Kildebasert risiko som må reproduksjonsprøves.** Nummermodellene krever aktiv
Norway-modul. `modNorway::remove()` kaller bare native `_remove()`, uten en
uttrykkelig overgang av valgte Norway-nummermodeller. Livssyklustesten kjøres
før nummerseriene konfigureres, og beviser derfor ikke denne overgangen.

**Endring:** Prøv deaktivering med faktisk valgt og brukt nummermodell. Gi en
kontrollert overgang eller tydelig sperre når det ellers ville stoppe
fakturering. Ikke bytt automatisk til en modell som nullstiller eller gir feil
serie. Dette er overgangsvern, ikke bestilling av en ny nummerseriemotor.

### R15 — Lavere: Layoutrettingen er bredere enn dokumentert behov

**Vedlikeholdsrisiko, ikke bekreftet feil på alle sider.**
`css/layout-0.5.0.css:3` endrer kjernens hovedkolonner globalt og har regler
for kontrollflater som er fjernet. Den prøvde layouten bygger på Eldy.

**Endring:** Fjern ubrukte regler og avgrens rettinger til påviste problemer.
Prøv relevante bank-, dokument-, produkt- og oppsettssider på PC/mobil.
Behold intern horisontal rulling der tabeller trenger den. Ikke bytt tema eller
redesign hele Dolibarr som del av dette.

### R16 — Lavere: Valgfrie verktøy har uklar plass og leveransegrad

**Bekreftet.** `admin/pricing.php` er en separat administratorkalkulator,
ikke automatisk visning av veiledende EUR-pris på tilbud eller faktura.
`admin/registry.php` gjør et nyttig lesende oppslag, men de to verktøyene har
ikke en tydelig inngang i dagens forenklede veiviser/oppsettside.

**Endring:** Avklar og beskriv faktisk omfang. Eventuelt Brreg-oppslag hører
ved foretaksopplysninger; det er ikke en godkjenning av historisk MVA-status.
EUR-visning er et valgfritt brukerønske og skal ikke forsinke nødvendig
bokføring. Gjenbruk relevant pris-/dokumentflate hvis visningen gjennomføres;
ikke bygg en ny valutamotor eller legg til flere hovedmenyer.

### R17 — Vedlikehold: Kjerneversjon må kommuniseres og vurderes presist

**Bekreftet prosjektstatus.** Dockerfile og modulaktivering er låst til
24.0.1. Oppdatering til modul 0.75.0 er ikke en kjerneoppgradering til 24.0.2.
24.0.2 ble tidligere omtalt, men prosjektets dokumenterte støtte er fortsatt
24.0.1. Det er ikke kontrollert hvilken versjon en bestemt server kjører.

**Endring:** Samordne faktisk kjerneversjon, README, installasjonsveiledning og
oppdateringsmelding. Vurder tilgjengelig vedlikeholdsversjon og offisielt
image med kompatibilitetstest før oppgradering. Behold låste images; ikke
fjern versjonskontrollen eller bruk `latest` for å omgå testarbeidet.

## Behold og gjenbruk

- Ekstern modularkitektur; ingen kjernemodifikasjoner.
- Native faktura, tilbud, leverandørfaktura, bank, reskontro, journaler og perioder.
- NOK-salg og norsk/engelsk dokumenter som bestilt. NOK-salg opphever ikke
  behovet for å bevare valuta og omregningsgrunnlag på utenlandske kjøpsbilag.
- Organisasjonsnummer, organisasjonsformer, MVA-merking og forståelige norske
  betegnelser. Brreg-kontroll skal være et hjelpemiddel, ikke en ekstra port.
- Bestilte nummerserier, valgbare prefikser og startnummer; bevar eksisterende numre.
- Norske avgiftskoder og native sats-/kontofelt. Den lokale NO0-verdien løser
  et påvist tap av bokstavelig 0 i kjernen; ikke fjern den uten prøvd erstatning.
- Felter for kode/grunnlag på reelt manuelle MVA-bilag: norske rapporter må også
  kunne lese slike bilag. Vurder integrering i vanlig registrering; ikke slett
  metadata fordi fradragsnormalflyten forenkles.
- Ett MVA-uttrekk ved rapportoppgaven med lenker til kilder. Videreutvikle
  rapportgrunnlaget uten å gjeninnføre generelle kontrollkopier.
- Sperring av sjekkbetalinger/-innskudd som uttrykkelig bestilt.
- Lesearkiv og vern mot dobbeltføring av tidligere særposterte kjøp.
- Migreringskontrollsummer, bevaringsprøver, syntetiske data og relevante rettigheter.
- Vedvarende lagring, private sikkerhetskopier, låste avhengigheter og main/staging-vern.

## Avtalte mangler som ikke skal forveksles med opprydding

| Område | Fortsatt nødvendig leveranse |
| --- | --- |
| MVA | Normal fradragsflyt, registreringsovergang, relevante sær-/importposteringer, avstemt fullstendig grunnlag og kvalifisert innsending. |
| EHF/Peppol | Eksisterende tillegg/tilgangspunkt må kvalifiseres for norsk faktura/kreditnota, mottakerreferanse og transportstatus. Ingen egen Peppol-motor er valgt. |
| SAF-T | Norsk eksport med konto-/avgiftsmapping, motparter, saldoer og bilag; validering og avstemming. Kontoplan er ikke en SAF-T-eksport. |
| Bank og årsavslutning | Full kjede med delbetaling, kreditnota/refusjon, gebyr, avstemming, inngående saldoer, kontotyper, låsing og retting må kvalifiseres gjennom kjernen. |
| Næringsspesifikasjon/skattemelding | Egen leveranse med årsversjon, foretaksform, skattemessige forskjeller og konkret leveringsvei. SAF-T erstatter ikke dette. |
| Papirløse bilag | Beholdes sist som avtalt. Ingen ny OCR-/matchingmotor foreslås i denne gjennomgangen. |

## Praktisk rekkefølge

1. Rett filutskiftingen og oppgraderingstesten (R01/R11). Bevar historikk.
2. Samordne bruker-/utviklingsdokumentasjonen (R12/R17), og kvalifiser
   kontotyper og faktisk fakturainnhold (R02/R06).
3. Velg en prøvd normal kjøps-/fradragsflyt og stabil historisk rapportlesing
   (R03–R05). Dette må løses før flere importtilfeller erklæres ferdige.
4. Forenkle oppsett/bank og unødvendige sperrer, samt uavhengig nummerserieoppsett
   (R07–R10). Gjenbruk native flater og bevar historiske dokumenter.
5. Fullfør drifts-/livssyklusoverganger og rydd layout/verktøy (R13–R16).
6. Fortsett med de avtalte rapporteringsleveransene. EHF-undersøkelsen kan
   fortsette parallelt; papirløse bilag tas fortsatt sist.

Dette er endringsbehov, ikke en bestilling av 17 nye funksjoner eller sider.
Konkrete implementasjoner og nødvendig migrering må bygge på funnene, ikke
på at et annet produkt har en bestemt intern modell.

## Sammenligningsgrunnlag og primærkilder

Lest 2026-10-17. Leverandørdokumentasjon viser brukerflyt; offentlige kilder
brukes for regnskaps-/rapporteringskrav. Et annet produkts kontonumre eller
kodeverdier er ikke automatisk riktige i denne kontoplanen.

- [Tripletex: forholdsmessig MVA](https://hjelp.tripletex.no/hc/no/articles/18168752046225-Slik-f%C3%B8rer-du-forholdsmessig-mva): andel velges gjennom MVA-kode i vanlig bilagsføring.
- [DNB Regnskap: forholdsmessig MVA](https://hjelp.dnbregnskap.dnb.no/no/article/forholdsmessig-mva-16zoi3c/): fradragsgruppe eller prosent i vanlig bilagsføring, med mulighet for kontokobling.
- [Tripletex: bankinnstillinger](https://hjelp.tripletex.no/hc/no/articles/6667414676625-Innstillinger-for-Bankintegrasjon): bankens regnskapskobling ligger ved bankoppgaven.
- [Altinn: faktura](https://info.altinn.no/starte-og-drive/regnskap-og-revisjon/regnskap/faktura-salgsdokumentasjon/): dokumentinnhold, blant annet leveringsopplysninger og kreditnota.
- [Skatteetaten: nummerering](https://www.skatteetaten.no/rettskilder/type/uttalelser/prinsipputtalelser/nummerering-av-salgsdokumenter/): maskinell nummerering og etterprøvbarhet; brukerønsket F/K/T-format er et produktvalg.
- [Skatteetaten: SAF-T](https://www.skatteetaten.no/bedrift-og-organisasjon/starte-og-drive/rutiner-regnskap-og-kassasystem/saf-t-regnskap/dokumentasjon/): 1.40 er pliktig fra 2027, og er en egen eksportleveranse.
- [Stortinget: lovsaken om digital bokføring og e-faktura](https://www.stortinget.no/no/Saker-og-publikasjoner/Saker/Sak/?p=200136): vedtatte lovendringer. Detaljert ikrafttredelse, format og unntak må kontrolleres ved den konkrete EHF-leveransen.
- [Dolibarrs gjennomgåtte kildeversjon](https://github.com/Dolibarr/dolibarr/tree/b7958385f00a92219f76a4dfea67dae93df97a25): native felt, nummermodeller, regnskapskontoer og årsavslutning.

## Verifikasjon utført i gjennomgangen

- Kildegjennomgang og sammenhold av aktive funksjonsområder, tidligere opprydding,
  dokumentasjon og testopplegg. Eksisterende integrasjon/restore-CI på PR 91 er
  bestått; det ble ikke kjørt en ny full Docker-suite for denne lesende gjennomgangen.
- Alle 22 Python-tester ble kjørt på nytt og bestod. Modulpakken ble bygget.
- Isolert filprøve med faktisk gammel revisjon bekreftet R01: 42 ekstra filer
  beholdes, selv om alle dagens leverte filer har riktig hash.
- Ingen handlinger mot produksjonsserveren. Ingen regnskapsdata eller innstillinger
  ble endret. R14 og den fulle konsekvensmatrisen for øvrige funn krever målrettede
  reproduksjons-/akseptprøver før implementasjon.

## Oppfølging etter gjennomgangen

0.76.0 tar inn brukerens bestilling av én enkel oppstartsoversikt med statustabell,
lenker og nødvendig forklaring. Den erstatter stegnavigasjonen, bruker kjernens
verktøy og viser uferdig støtte. R01 inngår som nødvendig retting av filutrulling;
øvrige funn følges i den samlede leveranseplanen. Selve gjennomgangen over
beskriver grunnlaget ved 0.75.0; etterfølgende tiltak er ikke bevis for at hele
modulen er ferdig. Se [gjeldende plan](delivery-plan.md).
