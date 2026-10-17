# Arbeidsplan for norsk lokalisering

Oppdatert 2026-10-17 etter opprydding og presisering av næringsspesifikasjonen.
Gjeldende programversjon er 0.75.0. Oppryddingen er gjennomført;
videre arbeid skal følge prioriteringen nedenfor. [Leveransehistorikken](delivery-history.md)
beskriver hva som allerede er bygget; den er ikke en bestilling av mer kode.

## Styrende beslutning

Dolibarrs eksisterende funksjoner skal brukes. Modulen skal dekke konkrete
norske mangler og uttrykkelig bestilte behov med minst mulig tilleggskode.
Et dokumentasjons- eller sporbarhetskrav betyr ikke automatisk at det trengs
en ny kontrollside, tabell, historikkmodell eller bekreftelsesrunde.

Egne kontroll- og historikkfunksjoner er fjernet fra vanlig arbeidsflyt.
Generelle «fortsett»-beskjeder utvider ikke oppgaven med nye produktideer.

## Gjenstående leveranser

| Rekkefølge | Oppgave | Ferdig når |
| --- | --- | --- |
| 1 | Avklare kjernedekning og gjenbruk | Første kildegjennomgang er dokumentert i [dekningsanalysen](coverage-analysis.md). Aktuelle tillegg er kandidater, ikke installert eller godkjent støtte. Kompatibilitet og faktisk norsk dekning prøves før valg. |
| 2 | Norsk MVA-behandling og dokumenter | Ordinært salg/kjøp, kreditnota, fritak/unntak, registreringsovergang, fradrag og relevante utenlandstransaksjoner er prøvd mot kjernen. Bare påviste mangler får nødvendig tilpasning. Varer og tjenester inngår. |
| 3 | MVA-grunnlag og melding | Grunnlag og avgift kan avstemmes mot bokføringen per periode og norsk avgiftsbehandling, inkludert manuelle bilag og retting. Direkte innsending er et eget bestilt behov med autorisasjon, status og kvittering; rapportgrunnlag må være riktig først. |
| 4 | EHF faktura og kreditnota | Eksisterende tillegg og tilgangspunkt er kvalifisert med norske identifikatorer, mottakerreferanser, validering, sending og status. Undersøkelse starter parallelt med punkt 2. Ingen avtale eller kjøp er gjort. |
| 5 | SAF-T Financial | Norsk eksport med kontoklassifisering, avgiftskoder, motparter, saldoer og bilag er validert mot aktuelt skjema og avstemt mot komplett regnskap. Eksisterende eksportutvidelser undersøkes før egen kode. |
| 6 | Avstemming og årsavslutning | Kjernens reskontro, bank, saldoovergang, periodisering, låsing og retting er prøvd for støttet omfang. Rutiner og rapporter er dokumentert; egne kontrollflater er ikke standardløsningen. |
| 7 | Næringsspesifikasjon og skattemelding | Regnskapskontoer og saldoer er koblet til relevante opplysninger for riktig inntektsår og foretaksform. Skattemessige forskjeller, avskrivninger og tilleggsopplysninger håndteres. Grunnlaget avstemmes mot årsavslutningen, og en konkret leveringsvei er kvalifisert: egnet årsoppgjørssystem, overføring/eksport eller integrasjon. SAF-T alene dekker ikke leveransen. |
| 8 | Samlet produksjonsaksept og drift | Hele kjeden dokument–bokføring–betaling/kreditnota–avstemming–rapport/eksport er prøvd. Tilgang, retting, bevaring og gjenoppretting inngår. Støttet bruk og gjenværende avvik beskrives eksplisitt. |
| 9 | Papirløs bilagsflyt og matching | Eksisterende mottak og vedlegg er brukt først; nødvendig forenkling av registrering og matching er prøvd. Tas til slutt som avtalt. |

## Neste konkrete arbeid

Første MVA-prøve er gjennomført i `tests/core-vat-report.php` og dokumentert
i [dekningsanalysen](coverage-analysis.md). Fakturabaserte varer/tjenester,
kreditnotaer, lokale kodegrupper og betalinger er prøvd. Manuelle MVA-bilag
og separate omposteringer påvirker hovedboken uten å inngå i fakturauttrekket.
Dette er et konkret rapporteringsgap, ikke en begrunnelse for nye kontrollflater.

Blandede 25/15/12-satser, kreditnotaer og separate bilag for 50/0-prosent
fradrag er nå prøvd gjennom kjernen i 0.66.0. Norsk/engelsk NPR-tekst er
presisert fordi valget er en fransk særordning som påvirker prisberegningen.
En konkret manuell fradragsflyt står i oppsettsveiledningen. Ingen egen
kjøpsmotor eller fradragshistorikk skal gjeninnføres for dette behovet.

0.67.0 gjenbruker kjernens satsregister og fakturalinjefelt til ordinære norske
koder: salg 3/31/33 og kjøp 1/11/13. MVA-kontohjelpen og de prøvde native
standardvalgene støtter skillet. Det er ingen ny kodemodell eller meny.
Eksisterende regnskap og produkter konverteres ikke automatisk.

0.68.0 legger [avgiftskode og grunnlag på manuelle MVA-linjer](manual-vat.md)
ved kjernens vanlige bilagskort. Ordinære koder og kontoer kommer fra satsregisteret;
regnskapsbeløp og fakturaer endres ikke. Skjema 2 er en additiv metadatautvidelse,
med migrerings- og gjenopprettingsprøve. Låste/eksporterte bilag kan bare leses,
og endret kilde blir ikke stille godtatt som gjeldende avgiftsgrunnlag.

0.69.0 gir et [lesende MVA-uttrekk for ordinære koder](vat-report.md) under
Regnskap → Regnskap → Rapporter. Native fakturaer/kreditnotaer, manuelle
MVA-linjer og separate fradragsrettinger kan summeres mot faktisk bokføring.
Manglende kode, kilde-/beløpsavvik, ulik dato og manglende overføring vises
ved kilden. Delsummer har ingen netto/CSV før avvik er avklart. Dette er én
rapport ved den vanlige rapportoppgaven; ingen nye kontrollkopier eller databasetabeller.

0.70.0 gjenbruker satsregister/fakturalinjer for innenlandske 5/6/7, med
append-only oppretting av manglende kodevalg ved aktivering/oppdatering.
Native fakturaer/kreditnotaer, rene og blandede nullsatslinjer og varer/tjenester
er prøvd. 5/6 vises som meldingsgrunnlag, 7 separat uten slikt grunnlag i CSV.
En ukodet nullsats blir ikke gjettet til en behandling. Ingen ny meny, database-
modell eller begrunnelsesside innføres. Faglig vilkårsvurdering er fortsatt
brukerens oppgave i den vanlige dokumentflyten.

0.71.0 bruker kjernens kjøpsjournal og linjekode NO0 for innenlandske kjøp
uten MVA-behandling. Det lokale navnet unngår kjernens tap av bokstavelig
kode 0 i velger/lagring. Uttrekket viser norsk kode 0 med separat beløp uten
fradrag/meldingsgrunnlag. Native rene/blandede fakturaer og kreditnotaer,
varer/tjenester og bevaring gjennom oppdatering/gjenoppretting er prøvd.
Ingen nye tabeller, menyer eller lagringsinngrep innføres.

0.72.0 gjenbruker satsregister, kundefakturalinjer og salgsjournal for
uttrykkelig valgt eksportkode 52. NOK-salg/kreditnotaer til utenlandske
kunder, varer/tjenester og nullavgift er prøvd med SE/US. Uttrekket tar med
eksportgrunnlaget uten avgift; innenlandske kodevalg blir ikke brukt som
utenlandsregel. Dokumentasjon beholdes i den vanlige dokumentflyten.
Ingen nye menyer, tabeller, kontrollsider eller bokføringsmotor innføres.

0.73.0 bruker kjernens omvendte avgiftsplikt på NOK-tjenestekjøp med 25 %
og full fradragsrett. Eksplisitt kode 86, katalogtjeneste, native avgiftsvalg,
kjøpsjournal, kreditnotaer og kontoene 2704/2714/2400 er prøvd med SE/US.
Uttrekket avstemmer faktisk avgift i begge retninger og viser inngående
fradrag uten nytt grunnlag. Ingen egen kjøpsmotor, tabell eller kontrollside.
Kjernens konto-/driftsinnstillinger må settes opp; de overskrives ikke.

0.74.0 støtter kode 87 på hele, manuelt bokførte NOK-tjenestekjøp uten
fradragsrett ved 25 %. Kjernens bilag, eksisterende MVA-felt og konto 2704
gjenbrukes. Kostnaden inkluderer avgiften; uttrekket viser bare utgående
MVA og grunnlag. Ingen ny satsrad, side, tabell eller betalingsflyt innføres.
Denne flyten oppretter ikke leverandørfakturaer eller betalingsoppfølging.

0.75.0 støtter delvis fradrag på den prøvde native 86-fakturaflyten ved et
separat rettingsbilag med kode 86 på 2714. Kjernens bilag og eksisterende
MVA-felt gjenbrukes. Full beregnet avgift/grunnlag beholdes; faktisk inngående
fradrag summeres uten nytt grunnlag. Kjøp/kreditnota og begge fortegn er
prøvd med 50 % fradrag. Prosent og kobling til faktura automatiseres ikke.
Ingen nye tabeller, menyer eller kontrollsider.

Neste arbeid er eventuell videre gjenbruk for leverandørfakturaflyt uten
fradrag og andre aktuelle importtilfeller, deretter
registreringsovergang og særposteringer som MVA-oppgjør. Manuelle nullsatsbilag,
rene unntatte/uregistrerte foretak og andre behandlinger må kvalifiseres.
Støttede koder alene utgjør ikke en fullstendig norsk melding. Innsending og
samlet faglig produksjonsaksept gjenstår. Kjernens journaler brukes fortsatt.

EHF-kandidater vurderes videre på dokumentasjon og tilgjengelig kilde før
avtale eller installasjon. Norsk dokumentvalidering og faktisk transport
prøves hver for seg. Ingen mottakerregistrering eller kontakt med leverandører
gjøres uten uttrykkelig bestilling.

## Utført forenkling i 0.65.0

| Beholdt | Fjernet | Overgang |
| --- | --- | --- |
| Norsk kontoplan, standardkontoer, MVA-kontoer, foretaksopplysninger, nummermodeller, NOK og språk, valgfri EUR-visning og sperring av sjekker | Egne faktura-, bilags-, periode- og fordelingskontroller, begrunnelsesregistrering, nye kontrollkopier og særskilt kjøpsbokføring | Regnskap og dokumenter beholdes. Gammel tilleggshistorikk kan leses og lastes ned uendret. Tidligere særposterte kjøp sperres mot dobbeltføring; separat rettingsbilag i kjernen er mulig. |

Oppsettsveilederen gir forklaringer og lenker uten egen godkjenningsstatus.
Daglig arbeid bruker kjernens dokumenter, journaler, hovedbok, perioder,
bank og produktkontoer. Norsk regnskapsoppsett har én menyinngang under
Regnskap → Innstillinger. De tidligere løsningene var tillegg, ikke
lovpålagte brukeroppgaver. Det gamle lagringsskjemaet beholdes for lesing
og vern av allerede særposterte kjøp; nye historikkposter opprettes ikke.

Oppryddingen er prøvd med ordinær salgs- og kjøpsbokføring, gammel historikk,
oppgradering, backup og gjenoppretting. Dette dokumenterer teknisk overgang,
ikke full norsk regnskapsmessig etterlevelse.

Neste arbeid er å avklare faktisk dekning i kjernen og eksisterende tillegg
for MVA-rapportering, SAF-T og EHF. Ingen ny rapportmotor eller kontrollflate
skal bygges før det konkrete norske gapet er dokumentert. Papirløs flyt tas sist.

## Videre omfang

De avtalte områdene beholdes, men er ikke forhåndsbeslutninger om egen kode:

- A1/A2: norsk avgiftsoppsett, faktura, kreditnota og nødvendige kontokoblinger.
- B1/B2: norsk MVA-grunnlag og rapportering, med direkte innsending som bestilt funksjon.
- C/C2: reskontro, bank, avstemming og årsavslutning på kjernens grunnlag.
- D: relevante utenlandsscenarioer for varer og tjenester.
- E: EHF/Peppol for offentlige kunder; eksisterende løsninger undersøkes tidlig.
- F: SAF-T Financial.
- F2: næringsspesifikasjon og skattemelding som egen leveranse etter årsavslutning.
- G: nødvendig retting, tilgang, oppbevaring og drift.
- A3/C1: papirløs bilagsflyt og enkel matching tas fortsatt til slutt.

Kravgrunnlaget ligger i [kravregisteret](norwegian-requirements.md) og
[kilderegisteret](sources.md). Lovkrav, uttrykkelige brukerønsker og intern
teknisk diagnostikk skal skilles. Tekniske tester bekrefter ikke at et tillegg
er nødvendig eller at norsk regnskapsmessig etterlevelse er oppnådd.

## Kontroller som del av arbeidsflyten

Nødvendige kontroller brukes ved den ordinære oppgaven. Gjenbruk kjernens
kontroller først. Oppsett hører til i relevante innstillinger eller
oppsettsveilederen; daglige oppgaver hører til ved dokument, bank eller regnskap.
Vanlig bokføring skal ikke få ekstra manuelle kontrollrunder uten et konkret
behov. Interne diagnoser skal ikke presenteres som lovpålagte brukeroppgaver.

## Arbeidsregler og ferdigkriterium

Ett foretak, NOK som dokumentvaluta, norsk/engelsk på tilbud og faktura og
valgfri veiledende EUR-prisvisning videreføres. Modulen skal være generell
for varer og tjenester. Bare main og staging brukes, og kjernen endres ikke.

Før ny tilleggskode skal den korte begrunnelsen vise:

1. Kravet eller det uttrykkelig bestilte behovet.
2. Hva kjernen eller et eksisterende tillegg faktisk dekker i prøvd versjon.
3. Det konkrete gapet og den minste endringen som løser det.

Relevant test og dokumentasjon følger endringen. Main krever fortsatt PR,
bestått CI på aktuell revisjon og tydelig akseptert omfang. Eksisterende
regnskap, dokumenter, passord, volumer og instansnøkkel bevares. Endring av
skjema eller historikk krever versjonert overgang og prøvd gjenoppretting.
