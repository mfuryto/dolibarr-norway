# Arbeidsplan for norsk lokalisering

Oppdatert 2026-10-13. Planen gjelder en generell norsk modul for varer og
tjenester. Status er kontrollert mot kode og tester til og med 0.42.0.
[Leveransehistorikken](delivery-history.md) holdes separat fra videre arbeid.

## Omfang og arbeidsregler

Ett foretak per installasjon; norsk kontoplan, NOK-dokumenter og norsk/engelsk
på tilbud og faktura. EUR kan vises som veiledende pris. Norske bedriftskunder
og offentlige kunder er første testprofiler. ENK uten ansatte er første
foretaksprofil; AS og andre former krever egne scenarioer. Modulen skal ikke
hardkode en bransje, installasjon eller bruker. Ingen sjekkfunksjoner.

Kjernen gjenbrukes via ekstern modul. Bare main og staging brukes; stabil
leveranse krever PR og bestått CI på aktuell revisjon. Drift beholder data,
konfigurasjon og instansnøkkel. Spørsmål fra brukeren endrer ikke automatisk
prioriteringen. Nye innstillinger skal løse et dokumentert gap i planen.

## Kravgrunnlag og faktisk status

| Område / kilde | Levert | Gjenstående tilpasning og ferdigkriterium |
| --- | --- | --- |
| Faktura/kreditnota – bokføringsreglene, Altinn S7 | Maskinelle F/K/T-serier, kontroller, norske/engelske dokumenter, kildefaktura | Kvalifiser PDF-felter, leveringsdato/-sted, differensierte satser, foretaksform og originalreferanse med fullstendige dokumenteksempler. Startnummer er migreringsoppsett, ikke fri nummerering av utstedte dokumenter. |
| Kontoplan – NS 4102:2023 S14 | Låst lisensiert profil med 745 kontoer; generelle standardkontoforslag og lukkingskontoer. Prosjektaksept dokumentert 2026-10-13 etter sammenligning med SAF-T-listen. | Kontoplanens samsvar er avklart som prosjektbeslutning og blokkerer ikke videre arbeid. Ingen uavhengig NS-sertifisering påstås. Årsoppstilling, SAF-T-klassifisering og avgiftsmapping må fortsatt kvalifiseres separat. Se [kontoplanens aksept](norwegian-chart.md#aksept). |
| MVA – Skatteetaten S8/S17 | Satsdiagnostikk, én registreringsdato, satskontoer og eksplisitte avgiftskodekoblinger | Kjernen har 0/8/14/25; manglende 15/12 og aktive gamle satser må håndteres i nytt oppsett. Deretter gyldighetsperioder, registreringshistorikk, fritak/unntak, fradrag, NOK-grunnlag og korreksjoner. En sats/konto bekrefter ikke avgiftsbehandling. |
| Bokført avgiftsgrunnlag – NBS 2 S15, SAF-T S5/S6 | Linjeopplysninger, koblinger til faktisk hovedbok og versjonerte kontrollkopier; bruttokontroll | Kontrollkopi av dagens data er ikke opprinnelig bokføringsøyeblikksbilde. Knytt grunnlag/avgift til faktiske posteringer for både salg og kjøp, inkludert manuelle bilag og korreksjoner. Avstem avgift og grunnlag hver for seg. |
| Betaling/reskontro/balanse – NBS 5/7 S15 | Bankkoblinger, bilagsbalanse og syntetiske betalingsforløp | Avstem kunder/leverandører mot kontrollkontoene og bank: inngående saldo + bevegelser = utgående saldo. Prøv delbetaling, kreditnota, refusjon, gebyr og åpne poster. |
| Utenlandshandel – Skatteetaten S17 | Separate kontoforslag for varer/tjenester og eksport/import | Kvalifiser konkrete land/kundetyper og leveranser. Innførsel av varer, fjernleverbare tjenester og omvendt avgiftsplikt er ulike scenarioer. Utenlandsk kunde alene gir ikke fritak. NOK-faktura fjerner ikke utenlandske avgiftsplikter eller bankens vekslingsgebyrer. |
| EHF – DFØ S18, NBS 4 S15 | Norske/engelske PDF-er; ingen godkjent EHF-leveranse | Kvalifiser EHF faktura/kreditnota 3.0, kjøper-/ordrereferanser, tilgangspunkt og mottakertest. PDF er ikke EHF. Tilgangspunkt velges før faktisk sending. |
| SAF-T Financial – Skatteetaten S5/S6/S12 | Fast offisiell avgiftskodereferanse og eksternt lagringsgrunnlag | Implementer kontomapping til gjeldende næringsspesifikasjon, fullstendig eksport og avstemming. Mål 1.40, pliktig fra 2027 og tilgjengelig nå. XSD-validering alene er utilstrekkelig. Elektronisk regnskap gir ikke automatisk småforetaksfritak. |
| Sikring, kontrollspor, oppbevaring – NBS 1/2/3 S15, Altinn S16 | Vedvarende drift, backup og testet restore; begrenset endringshistorikk | Dokumentert risikovurdering, rollevern, låste perioder, retting med spor, lesbarhet og oppbevaring. Primærdokumentasjon normalt 5 år; sekundær normalt 3,5 år; vurder særregler. Backup alene er ikke arkiv eller fullstendig kontrollspor. |
| Årsavslutning og næringsspesifikasjon | Kontoplan, lukkingskontoforslag og forklaring av skatteordboken | Årsavslutning og rapportgrunnlag for næringsspesifikasjonen er vedtatt videre omfang, men ikke ferdigstilt. Kvalifiser avstemming, periodisering, avskrivninger, resultatdisponering, inngående saldoer og låsing; prøv årsversjon og foretaksform for rapporten. |
| Skatt/ansatte | Ingen automatisk norsk skatteberegning eller lønn | ENK-skatt er personlig; AS trenger eget årsoppgjørs-/innsendingsløp. Skattekontoer er ikke et utviklingskrav for fakturering. Ansatte/lønn er separat omfang. |

S1–S18 henviser til [kilderegisteret](sources.md). Lov-/forskriftsgrunnlag og
NBS brukes som kravgrunnlag; dette dokumentet er ingen sertifisering.
Prosjekteier har akseptert kontoplanen på grunnlag av sammenligningen med
SAF-T-standardkontolisten; dette er prosjektaksept, ikke uavhengig
NS-verifikasjon. Referanselisten skal ikke kopieres inn som kontoplan uten
riktig bruksrett.

## Prioritert leveranserekkefølge

| Prioritet | Konkret leveranse | Aksept og avhengighet |
| --- | --- | --- |
| A1 – levert i 0.41.0 | Fullfør ordinært MVA-satsoppsett for nye installasjoner | Forhåndsvis manglende 25/15/12 og deaktivering av ordinære 8/14. Bevar nullsats, spesialrader, kontovalg og dokumenter. Historikk, eksisterende bruk og tvetydighet sperrer endring. Test gjentakelse, atomisk rollback og foreldet bekreftelse. Ingen sats velges for fakturalinjer. |
| A2 | Kvalifiser faktura, kreditnota og konto-/avgiftsfordeling | Salg/kjøp, 25/15/12, nullsats med eksplisitt behandling, retting, fradrag og registreringsperioder. Knyt avgift og grunnlag til faktisk hovedbok; manglende metadata må vises som avvik. |
| B1 | MVA-perioderapport fra kvalifisert bokført grunnlag | Spor begge veier mellom rapport og postering; prøv manuelle bilag, korreksjoner, delvis fradrag og periodegrenser. Ingen rapport fra bare dagens fakturasatser. |
| B2 | Direkte innrapportering av MVA-melding | Bygger på B1. Kvalifiser Skatteetatens gjeldende grensesnitt, autorisasjon, validering, eksplisitt bekreftet innsending, kvittering/status og endringsmeldinger. Test avbrudd og gjentakelse uten utilsiktet dobbeltinnsending før produksjonsbruk. |
| C | Reskontro, bank og balanseavstemming | Komplette saldoer og bevegelser, ingen dobbelttelling; inngående saldoer fra tidligere system behandles som egen migrering. Nytt regnskap trenger ingen konstruerte inngående poster. |
| C2 | Årsavslutning før skattemelding og næringsspesifikasjon | Bygger på kvalifisert bokføring og C. Kontroller saldoer, bank, reskontro og MVA, og håndter dokumenterte periodiseringer, avskrivninger og resultatdisponering. Test overføring av inngående saldoer, gjentakelse uten dobbeltføring, endelig låsing og sporbar retting/etterpostering. |
| D | Utenlandsscenarioer for varer og tjenester | Konkrete land-/kundetyper og leveringsmodeller; eksportdokumentasjon, importgrunnlag og omvendt avgiftsplikt. Bygger på A2/B1. |
| E – spesifikasjon kan avklares tidlig | EHF/Peppol | Skjema og forretningsregler, kjøperreferanser, testmottaker og tilgangspunkt. Ingen produksjonssending før konkret avtale og aksept. |
| F – kontomapping kan utvikles parallelt | SAF-T 1.40 | Låst skjema/kodelisteversjon, datoavgrenset kontomapping, konto-/reskontrosaldoer, sporbar avgift og komplette totaler. Krever A2/B1/C før endelig eksportaksept. |
| F2 | Rapportgrunnlag for næringsspesifikasjonen | Bygger på avstemt årsgrunnlag fra C2. Knytt regnskapstall til riktig årsversjon og foretaksprofil, med sporbarhet til kontoer/posteringer og eksplisitt behandling av skattemessige forskjeller. Kontroller fullstendighet og totaler før tallene brukes ved levering. |
| G – vern utvikles gjennom alle trinn | Perioder, retting, arkiv, drift og produksjonsaksept | Rolle-/låsetester, dokumentert gjenoppretting, oppbevaring og en signert scenario-/kravmatrise for konkret støttet omfang. |

Kontoplanens prosjektaksept er gjennomført. Kontomapping under F gjenstår
og er ikke grunnlag for å bytte allerede brukt kontoplan automatisk.
Kjerne-/sikkerhetsoppdateringer vurderes løpende på staging med låste images
og regresjonstester. Støttet kjerne er fortsatt 24.0.1 til en ny versjon er
kvalifisert; planen lover ingen automatisk versjonsoppgradering.

## Vedtatt videre omfang – 2026-10-13

MVA-perioderapport med direkte innrapportering, årsavslutning og rapport til
næringsspesifikasjonen inngår i videre utvikling. Rapportproduksjon og
innsending kvalifiseres som separate leveranser. Dette utvider tidligere
avgrensning der direkte MVA-innsending var utenfor første leveranse; det
endrer ikke status for hva som allerede er implementert.

Rapporten til næringsspesifikasjonen skal gi et kontrollert årsgrunnlag for
skattemeldingen. Begrepene skattemelding og næringsspesifikasjon brukes
fremfor de tidligere betegnelsene selvangivelse og næringsoppgave.
Årsversjoner, foretaksform og skattemessige justeringer må håndteres
uttrykkelig; en SAF-T-kobling alene bekrefter ikke en ferdig skatterapport.
Se [Skatteetatens struktur for skattemelding og næringsspesifikasjon](https://www.skatteetaten.no/bedrift-og-organisasjon/skatt/skattemelding-naringsdrivende/innhold-og-struktur-i-skattemeldingen/)
og [MVA-meldingen](https://www.skatteetaten.no/bedrift-og-organisasjon/avgifter/mva/mva-melding).

Integrasjon av modulens daglige funksjoner i Dolibarrs ordinære menyer
utsettes til funksjonene er på plass. Neste funksjonelle arbeid forblir A2;
disse beslutningene skal ikke avbryte arbeidet med bokført avgiftsgrunnlag.

## Neste arbeid etter A1

A1 er implementert som [bekreftet satsoppsett](vat-rates.md). Denne
leveransen endrer ingen fakturalinjer, bokføringsmetadata eller MVA-rapport.

Neste funksjonelle leveranse er A2: en avgrenset kontroll av bokført
konto-/avgiftsfordeling, før den brukes som grunnlag for MVA-rapportering.
Start med ordinære innenlandske NOK-fakturaer/kreditnotaer og utvid eksplisitt
til kjøp, manuelle bilag og særbehandling. Det er ikke tilstrekkelig at hele
bilaget balanserer dersom avgift eller grunnlag er ført på feil konto.

Veiledertekster justeres ved behov i samme leveranse. Nye kontrollsider og
informasjonstekster skal ikke erstatte arbeid med bokført grunnlag,
avstemming og rapportering.

## Installasjonsspesifikke avklaringer

MVA-registerstatus og faktiske virkningsperioder må dokumenteres per foretak.
Vare-/tjenestetyper, land, fradrag og offentlige mottakerkrav bestemmer hvilke
scenarioer som kan godkjennes. Uavklarte scenarioer sperrer deres egen aksept,
men stopper ikke uavhengig utvikling med syntetiske data.

## Fast ferdigkriterium

Relevant lokal test, dokumentert omfang/begrensninger og bestått CI på aktuell
revisjon før PR fra staging leveres til main. Skjemaendringer krever versjonert
migrering, backup og gjenopprettingstest. Bare eksplisitt støttede scenarioer
kan gis produksjonsaksept; teknisk stabilitet og veiviserens arbeidsstatus er
ikke norsk regnskapsmessig etterlevelse.

## A2 – første del levert i 0.42.0

[Kunde-/salgs-/MVA-fordeling](invoice-allocation.md) kan sammenlignes med en
uttrykkelig valgt ordinær innenlandsk profil for 25/15/12 %. Faktiske
posteringer og forventede beløp vises separat per rolle, også ved kreditnota.
Feil avgiftsfordeling kan avdekkes selv om bilaget balanserer. Konsistent
lesing og kontroll av bilagsfullstendighet beskytter resultatet.

Dette fullfører ikke A2. Neste del er koblingen mellom fakturalinjer,
konkrete kontoer og historisk avgiftsbehandling, deretter kjøp, manuelle
bilag og korreksjoner. MVA-perioderapporten i B1 venter på kvalifisert bokført
avgiftsgrunnlag. Ingen kontrollstatus gir avgiftsmessig produksjonsaksept.
