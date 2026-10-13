# Arbeidsplan for norsk lokalisering

Oppdatert 2026-10-13. Planen gjelder en generell norsk modul for varer og
tjenester. Status er kontrollert mot kode og tester til og med 0.55.0.
[Leveransehistorikken](delivery-history.md) holdes separat fra videre arbeid.

[Norsk kravregister](norwegian-requirements.md) konkretiserer felles og
betingede krav med KR01–KR26. Relevante krav skal knyttes til testbevis og
aksept før et bruksområde erklæres støttet.

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

## Gjenbruk før ny funksjonalitet

Modulen skal være en norsk tilpasning av Dolibarrs eksisterende system.
Den skal ikke bli en parallell regnskapsløsning med egne kopier av fakturaer,
bilag, betalinger, bankavstemming eller årsavslutningsprosesser.

Før hver videre funksjonell leveranse kartlegges og prøves relevant
funksjonalitet i støttet Dolibarr-versjon. Beslutningen dokumenteres kort:

| Kjernens dekning | Tiltak |
| --- | --- |
| Funksjonen finnes og dekker behovet | Gjenbruk den, prøv norsk bruksområde og dokumenter normal arbeidsflyt. |
| Funksjonen trenger oppsett eller norsk tekst | Konfigurer/tilpass eksisterende funksjon uten å lage en ny parallell side. |
| Et konkret norsk krav mangler | Dokumenter gapet og bygg minste nødvendige utvidelse via støttede modulpunkter. |
| Området er ikke undersøkt | Undersøk og test kjernen før ny implementasjon besluttes. |

Kartleggingen gjelder også eksisterende norske kontrollsider. Vurder hvilke
som skal integreres i kjernens arbeidsflyt, forenkles eller samles, og hvilke
som bare er intern diagnose. Bevar nødvendig norsk validering og historikk;
ingen kontroller eller data fjernes automatisk som del av kartleggingen.

Faktura, leverandørbilag, dokumenter, posteringer og betalinger beholder
Dolibarr som hovedkilde. Egne tabeller brukes bare for dokumenterte norske
metadata-, sporbarhets- eller rapporteringsgap. En kontrollkopi skal ikke
bli en ny original eller et konkurrerende regnskap.

Dette er et krav til videre A2/A3/B/C/E/F/G-arbeid og skal avklares før nye
funksjoner bygges. Kartlegging og integrasjon er del av hver leveranse,
ikke et eget sluttprosjekt etter at parallelle løsninger er laget.

## Kravgrunnlag og faktisk status

| Område / kilde | Levert | Gjenstående tilpasning og ferdigkriterium |
| --- | --- | --- |
| Faktura/kreditnota – bokføringsreglene, Altinn S7 | Maskinelle F/K/T-serier, kontroller, norske/engelske dokumenter, kildefaktura | Kvalifiser PDF-felter, leveringsdato/-sted, differensierte satser, foretaksform og originalreferanse med fullstendige dokumenteksempler. Startnummer er migreringsoppsett, ikke fri nummerering av utstedte dokumenter. |
| Kontoplan – NS 4102:2023 S14 | Låst lisensiert profil med 745 kontoer; generelle standardkontoforslag og lukkingskontoer. Prosjektaksept dokumentert 2026-10-13 etter sammenligning med SAF-T-listen. | Kontoplanens samsvar er avklart som prosjektbeslutning og blokkerer ikke videre arbeid. Ingen uavhengig NS-sertifisering påstås. Årsoppstilling, SAF-T-klassifisering og avgiftsmapping må fortsatt kvalifiseres separat. Se [kontoplanens aksept](norwegian-chart.md#aksept). |
| MVA – Skatteetaten S8/S17 | Satsdiagnostikk, én registreringsdato, satskontoer og eksplisitte avgiftskodekoblinger | Kjernen har 0/8/14/25; manglende 15/12 og aktive gamle satser må håndteres i nytt oppsett. Deretter gyldighetsperioder, registreringshistorikk, fritak/unntak, fradrag, NOK-grunnlag og korreksjoner. En sats/konto bekrefter ikke avgiftsbehandling. |
| Bokført avgiftsgrunnlag – NBS 2 S15, SAF-T S5/S6 | Linjeopplysninger, koblinger til faktisk hovedbok og versjonerte kontrollkopier; bruttokontroll | Kontrollkopi av dagens data er ikke opprinnelig bokføringsøyeblikksbilde. Knytt grunnlag/avgift til faktiske posteringer for både salg og kjøp, inkludert manuelle bilag og korreksjoner. Avstem avgift og grunnlag hver for seg. |
| Betaling/reskontro/balanse – NBS 5/7 S15 | Bankkoblinger, bilagsbalanse og syntetiske betalingsforløp | Avstem kunder/leverandører mot kontrollkontoene og bank: inngående saldo + bevegelser = utgående saldo. Prøv delbetaling, kreditnota, refusjon, gebyr og åpne poster. |
| Utenlandshandel – Skatteetaten S17 | Separate kontoforslag for varer/tjenester og eksport/import | Kvalifiser konkrete land/kundetyper og leveranser. Innførsel av varer, fjernleverbare tjenester og omvendt avgiftsplikt er ulike scenarioer. Utenlandsk kunde alene gir ikke fritak. NOK-faktura fjerner ikke utenlandske avgiftsplikter eller bankens vekslingsgebyrer. |
| E-faktura – DFØ S18, Finansdepartementet S19, NBS 4 S15 | Norske/engelske PDF-er; ingen godkjent EHF-leveranse | Kvalifiser offentlig EHF og utgående e-faktura mellom bokføringspliktige virksomheter etter 2027-reglene. Mottaksplikt og digital bokføring følger 2030-reglene. Kontroller formatforskrift, unntak, referanser, tilgangspunkt, mottak og kvitteringer. PDF er ikke e-faktura. |
| SAF-T Financial – Skatteetaten S5/S6/S12 | Fast offisiell avgiftskodereferanse og eksternt lagringsgrunnlag | Implementer kontomapping til gjeldende næringsspesifikasjon, fullstendig eksport og avstemming. Mål 1.40, pliktig fra 2027 og tilgjengelig nå. XSD-validering alene er utilstrekkelig. Elektronisk regnskap gir ikke automatisk småforetaksfritak. |
| Sikring, kontrollspor, oppbevaring – NBS 1/2/3 S15, Altinn S16 | Vedvarende drift, backup og testet restore; begrenset endringshistorikk | Dokumentert risikovurdering, rollevern, låste perioder, retting med spor, lesbarhet og oppbevaring. Primærdokumentasjon normalt 5 år; sekundær normalt 3,5 år; vurder særregler. Backup alene er ikke arkiv eller fullstendig kontrollspor. |
| Årsavslutning og næringsspesifikasjon | Kontoplan, lukkingskontoforslag og forklaring av skatteordboken | Årsavslutning og rapportgrunnlag for næringsspesifikasjonen er vedtatt videre omfang, men ikke ferdigstilt. Kvalifiser avstemming, periodisering, avskrivninger, resultatdisponering, inngående saldoer og låsing; prøv årsversjon og foretaksform for rapporten. |
| Skatt/ansatte | Ingen automatisk norsk skatteberegning eller lønn | ENK-skatt er personlig; AS trenger eget årsoppgjørs-/innsendingsløp. Skattekontoer er ikke et utviklingskrav for fakturering. Ansatte/lønn er separat omfang. |
| Bilag, spesifikasjoner og ajourhold – S15/S20/S22 | Begrensede faktura-/bilagskontroller | Kvalifiser kjøpsbilag, utlegg, manuelle posteringer, pliktige spesifikasjoner, frister og dokumentert retting. Se KR04–KR07; komplette rapporter kan ikke erstattes av en saldobalanse. |
| Papirløs bilagsflyt | Kjernen har dokumentvedlegg på leverandørfakturaer og utlegg; samlet norsk arbeidsflyt er ikke kvalifisert | Enkel bilagsoversikt, opplasting fra PC, dokumentvisning, duplikat-/mangelvarsler og kobling til bokføring/betaling. Gjenbruk kjernen; mobilopplasting og dokumenttolking inngår ikke. Se [papirløst regnskap](paperless-accounting.md). |
| Balanse, årsregnskap og særtilfeller – S21/S23/S25/S26 | Begrenset kontooppsett | Avklar varetelling, anleggsmidler, eiertransaksjoner og årsregnskapsplikt. Kontantsalg, lønn, privatkunder og særbransjer får begrunnede egne omfang. Se KR12/KR14/KR21–KR26. |
| Lagringssted og personvern – S16/S24 | Privat driftsoppsett og backupvern | Avklar land, myndighetstilgang, elektronisk tilgjengelighet, leverandøransvar, innsyn og lagring/sletting uten tap av pliktig materiale. Se KR17–KR20. |

S1–S28 henviser til [kilderegisteret](sources.md). Lov-/forskriftsgrunnlag og
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
| E – friststyrt; avklares parallelt med A2 | E-faktura/EHF/Peppol for offentlige kunder og næringslivet | Kvalifiser sending etter 2027-reglene, format og unntak. Planlegg mottak og digital behandling etter 2030-reglene. Skjema/forretningsregler, referanser, arkiv, tilgangspunkt og mottakertest inngår. Sporet skal ikke vente på full MVA-/årsrapport. |
| F – kontomapping kan utvikles parallelt | SAF-T 1.40 | Låst skjema/kodelisteversjon, datoavgrenset kontomapping, konto-/reskontrosaldoer, sporbar avgift og komplette totaler. Krever A2/B1/C før endelig eksportaksept. |
| F2 | Rapportgrunnlag for næringsspesifikasjonen | Bygger på avstemt årsgrunnlag fra C2. Knytt regnskapstall til riktig årsversjon og foretaksprofil, med sporbarhet til kontoer/posteringer og eksplisitt behandling av skattemessige forskjeller. Kontroller fullstendighet og totaler før tallene brukes ved levering. |
| G – vern utvikles gjennom alle trinn | Perioder, retting, arkiv, drift og produksjonsaksept | Rolle-/låsetester, dokumentert gjenoppretting, oppbevaring og en signert scenario-/kravmatrise for konkret støttet omfang. |
| A3 – til slutt | Papirløs bilagsflyt på Dolibarrs dokumentgrunnlag | Etter øvrige planlagte funksjonsleveranser: kvalifiser enkel bilagsoversikt, PDF/bilde fra PC, dokument ved registreringen, enkel status og duplikat-/mangelvarsler. Originalen kobles til kjøp/utlegg/manuelt bilag og faktisk postering. Bygg videre på kjernen og bevar dokumenter i backup/restore. |
| C1 – til slutt, etter A3 | Enkel bilagsmatching i nettleseren | Bygger på A3/C. Brukeren kobler tilgjengelig banktransaksjon, dokument og regnskap med bekreftelse. Gjenbruk eksisterende funksjoner. Mobilapp/-opplasting, e-postmottak, OCR og avansert automatisering inngår ikke i bilagsleveransen. |

Kontoplanens prosjektaksept er gjennomført. Kontomapping under F gjenstår
og er ikke grunnlag for å bytte allerede brukt kontoplan automatisk.
Kjerne-/sikkerhetsoppdateringer vurderes løpende på staging med låste images
og regresjonstester. Støttet kjerne er fortsatt 24.0.1 til en ny versjon er
kvalifisert; planen lover ingen automatisk versjonsoppgradering.

## Vedtatt videre omfang – 2026-10-13

Papirløs bilagsflyt er vedtatt som A3/C1 og flyttet til slutt etter
øvrige planlagte funksjonsleveranser, etter prosjekteiers prioritering. Omfanget er primært for vanlig nettleserbruk på
PC med manuell opplasting og registrering. Mobilopplasting og ekstra
automatisering er tatt ut av bilagsomfanget. Løsningen får selvstendig
utforming og implementasjon. Offentlige produktbeskrivelser kan belyse brukerbehov;
tredjeparts kode, tekster, skjermbilder, grafikk eller annet materiale
gjenbrukes bare med dokumentert rett. Biblioteker, modeller og tjenester
må gjennomgås for lisenskompatibilitet, bruks-/distribusjonsvilkår og
eventuelle andre rettigheter før innføring. Se
[omfang og aksept for papirløst regnskap](paperless-accounting.md).

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

## Kontroller som del av arbeidsflyten

Vedtatt produktkrav: kontrollene skal ha naturlig eierskap og være integrert
i oppgavene de beskytter. Brukeren skal møte en forståelig arbeidsflyt med
avvik og neste handling. Separate diagnosesider kan beholdes for fordypning,
men skal ikke være den normale måten å gjennomføre regnskapsarbeidet på.

| Arbeidsflyt | Ansvarlig funksjon / rolle | Kontrolltidspunkt og oppfølging |
| --- | --- | --- |
| Førstegangsoppsett og endringer | Oppsettsveiviser / administrator | Kontroller foretaksdata, kontoer, satser, journaler og dokumentoppsett ved relevant lagring. Vis hva som gjenstår og lenk direkte til innstillingen. Ferdig gjennomgått oppsett skal ikke kreve samme manuelle runde ved hvert bilag. |
| Faktura og kreditnota | Fakturabildet / fakturaansvarlig | Kontroller dokumentopplysninger ved klargjøring/validering. Vis avvik ved feltet eller dokumentet, med forklaring og riktig rettingshandling. |
| Kjøp, kvitteringer og manuelle bilag | Bilagsregistrering / den som bokfører | Kontroller dokumentasjon, dubletter, beløp og kontokoblinger mens bilaget behandles og ved bokføring. Vis mangler i bilagsoversikten og på aktuelt bilag. |
| Bank og betaling | Avstemmingsbildet / den som avstemmer | Vis manglende koblinger og differanser sammen med transaksjonene. Brukeren følger opp delbetaling, gebyr eller feil kobling der avstemmingen skjer. |
| MVA-periode og innsending | MVA-arbeidsflyten / rapporteringsansvarlig | Samle avvik i rapportgrunnlaget og gi spor til bilag/postering. Krev nødvendig oppfølging før bekreftet innsending, og vis status/kvittering samme sted. |
| Årsavslutning og næringsspesifikasjon | Årsavslutningsløpet / årsoppgjørsansvarlig | Samle balanseavstemminger, justeringer, rapportgrunnlag og åpne punkter før avslutning. Vis hvilke forhold som hindrer neste trinn og hvem som skal følge dem opp. |

Rollene beskriver ansvar, ikke krav om flere personer eller en komplisert
godkjenningskjede. Én person kan ha alle rollene i et enkelt foretak.

Hvert avvik skal forklare hva som er undersøkt, hva som mangler/avviker,
hvorfor det har betydning og hva brukeren konkret kan gjøre. Skill mellom
bekreftet feil, nødvendig faglig vurdering og scenarioer som kontrollen
ikke dekker. Et manglende datagrunnlag skal ikke fremstå som bestått kontroll.
Sperring brukes når handlingens dokumenterte krav faktisk ikke er oppfylt;
et avvik fra en begrenset kontrollprofil er ikke alene bevis på ulovlig føring.

Kontroller utføres ved relevante hendelser og i samlede periode-/årsoppgaver.
En «kjør kontroll»-knapp er støtte ved behov, ikke en ekstra plikt for hvert
bilag. Kritiske vilkår vurderes på nytt ved lagring/bokføring/innsending;
tidligere kontrollresultater skal ikke brukes som fersk godkjenning etter
at grunnlaget er endret. Gjenbruk kjernens arbeidsflyter og norsk kontrollkode
slik at ulike innganger gir samme resultat.

Arbeidsflyt, ansvar og feilhåndtering beskrives og testes sammen med hver
funksjonell leveranse. Flytting til ordinære menyer skjer fortsatt senere;
plassering og integrasjon planlegges nå. Eksisterende diagnosesider er
teknisk grunnlag, og er ikke i seg selv en ferdig daglig arbeidsflyt.

## Installasjonsspesifikke avklaringer

MVA-registerstatus og faktiske virkningsperioder må dokumenteres per foretak.
Vare-/tjenestetyper, land, fradrag og offentlige mottakerkrav bestemmer hvilke
scenarioer som kan godkjennes. Uavklarte scenarioer sperrer deres egen aksept,
men stopper ikke uavhengig utvikling med syntetiske data.

## Fast ferdigkriterium

Relevant kjernefunksjon skal være undersøkt og gjenbrukt, og eventuell ny
norsk kode skal ha et konkret dokumentert gap. Leveransen skal vise én
sammenhengende arbeidsflyt med samme dokumenter, posteringer og rettigheter
som i Dolibarr, uten unødvendig dobbeltregistrering eller parallelle rutiner.

En kontrollfunksjon skal ha angitt arbeidsflyt, ansvarlig rolle, riktig
utløsningstidspunkt og konkret oppfølging av avvik før den regnes som ferdig
integrert. Normal bruk skal testes uten at brukeren må kjenne interne
kontrollsider eller manuelt gjenta samme kontroll på flere steder.

Hvert støttet scenario skal gjennomgå relevante KR01–KR26 fra
[kravregisteret](norwegian-requirements.md), med kilder, testbevis, eventuelle
eksterne rutiner og begrunnede avgrensninger. Betingede krav som gjelder
virksomheten må være håndtert før akkurat dette omfanget godkjennes.

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

## A2 – lagrede linjekontoer i 0.43.0

Fakturalinjens egne lagrede kontokoblinger sammenlignes med bokført netto
per konkret salgskonto. Direkte linjereferanser og aggregerte kontosummer
skilles uttrykkelig. Dagens produkt- og standardkontoer brukes ikke som
reserve, og manglende/fremmede koblinger kan ikke gi samlet samsvar.

Denne delen fullfører linjekontodiagnostikken innenfor de valgte profilene.
Historisk avgiftsgrunnlag, kontovalgenes opprinnelige gyldighet, kjøp,
manuelle bilag og rapportering gjenstår. Neste arbeid forblir A2, før B1/B2.

## A2 – kildeobservasjoner ved nye salgsposteringer i 0.44.0

[Hendelseslagring](posting-observations.md) bevarer rå faktura-, linje-,
kontokoblings-, motparts- og oppsettsopplysninger ved støttede nye
BOOKKEEPING_CREATE-hendelser. Hendelsen lagres i kjernens transaksjon, uten
etterfylling av eldre bilag. Dagens kilder kan sammenlignes med observasjonen;
endret eller fjernet hovedbokspost overskriver ikke lagringen.

Dette er et teknisk lagrings-/sporingsgrunnlag under KR05/KR08/KR09, ikke
ferdig historisk MVA-kvalifisering. Kjøp, manuelle bilag, øvrige import- og
korreksjonsveier, fradragsregler og rapportering gjenstår under A2.

## A2 – bevart oppsettskobling for avgiftskoder i 0.45.0

Nye kildeobservasjoner bevarer automatisk den eksakte lokale linjekodens
kobling til det daterte salgskodeoppsettet ved bokføring. Oppsettets og regelens
versjoner følger resultatet. Manglende, motstridende eller ufullstendig
kildegrunnlag forblir uavklart; sats eller kontonummer brukes ikke til å gjette.
Eldre hendelser etterfylles ikke, og senere oppsettsrettinger overskriver ikke
bevarte koblinger. Kjernens salgsjournal og eksisterende hendelseslagring gjenbrukes.

Dette er en teknisk kobling til eksplisitt oppsett, ikke ferdig kvalifisert
avgiftsbehandling. Neste A2-arbeid må knytte registreringsperioder, konkrete
avgiftsscenarioer og hovedboksfordeling til dette grunnlaget. Kjøp, manuelle
bilag, korreksjoner og rapportaksept gjenstår før B1/B2.

## A2 – registreringssammenligning i arbeidsflyten i 0.46.0

Kjernens fakturavalidering gir handlingsrettede varsler ved MVA og manglende,
ugyldig eller avvikende registreringsgrunnlag, dato før virkningsdato og
kreditnota. Én virkningsdato blir ikke en generell sperre eller avgiftsgodkjenning.
Den vanlige arbeidsflyten gjenbrukes; ingen ny kontrollside innføres.

Nye salgshendelser bevarer datosammenligning og bekreftelsesopplysninger
sammen med råkilder og kodekobling. Eldre payloadversjoner leses uten
etterfylling. Full registreringshistorikk med avregistrering/ny registrering,
retting, kildefakturaens opprinnelige status, konkret avgiftsbehandling og
hovedboksfordeling gjenstår under A2, før rapporteringsaksept i B1/B2.

## A2 – fordeling mot bevart grunnlag i 0.47.0

Den eksisterende profilkontrollen sammenligner nå også bevarte fakturalinjer
og kontokoblinger med faktisk hovedbok i ett konsistent lesebilde. Felles
beløpsregning, profiler og bilagsfullstendighet gjenbrukes. Manglende,
fjernede, endrede eller sprikende hendelser/poster gir ikke samsvar; endring
i dagens fakturakilder erstatter ikke det bevarte grunnlaget. Summerte
kontoposteringer skilles fra enkeltlinjefordeling.

Dette kvalifiserer den tekniske sammenligningen innen uttrykkelig valgte
25/15/12-profiler, ikke historisk avgiftsbehandling eller rapportgrunnlaget
som helhet. Neste A2-arbeid er konkrete avgiftsscenarioer, kilde-/rettingsløp,
kjøp og manuelle bilag med samme krav til sporbarhet. Full registreringshistorikk,
fradrag, nullsats og B1/B2-aksept gjenstår.

## A2 – kildebevaring for kjøp i 0.48.0

Kjernens kjøpsjournal bruker samme BOOKKEEPING_CREATE-hendelse som salg.
Den eksisterende hendelseslagringen utvides til leverandørfaktura og
leverandørkreditnota, med rå linje-/avgifts-/kontofelt, leverandørreferanse,
valutafelt og motpart. Kjøp og salg skilles på dokumenttype også når numeriske
faktura-ID-er er like. Lagring følger kjernens transaksjon og feil stopper
posteringen uten å etterlate en post uten kildeobservasjon.

Dette er teknisk bevaring, ikke fradragsvurdering eller kvalifisert
kjøpsfordeling. Salgsprofiler og selgerens registreringsdato brukes ikke
som avgiftsvurdering av kjøp. Neste A2-arbeid er datert kjøpskodekobling,
kjøpsfordeling, fradragsomfang og korreksjonsløp. Manuelle bilag, nullsats og
rapportaksept gjenstår. Papirløs opplasting og matching tas til slutt; krav
til dokumentasjon og arkiv gjelder fortsatt under de øvrige leveransene.

## A2 – bevart datert kjøpskodekobling i 0.49.0

Nye kjøpshendelser bevarer automatisk eksakt linjekodes daterte kjøpsregel,
mål og oppsettsversjon. Salgsregler er ingen reserve. Manglende eller
ufullstendig grunnlag og særmarkører forblir uavklart. Senere retting endrer
ikke den bevarte koblingen, og eldre rå kjøpshendelser etterfylles ikke.
Kjernens kjøpsjournal, samme lagring og eksisterende oppsett gjenbrukes.

Dette kvalifiserer teknisk oppsettskobling, ikke fradragsrett eller MVA.
Neste A2-arbeid er kjøpsfordeling mot hovedbok, uttrykkelig fradragsomfang og
korreksjonsløp. Manuelle bilag, nullsats, registreringshistorikk og B1/B2-aksept
gjenstår. Papirløs opplasting og matching er fortsatt prioritert sist.

## A2 – kjøpsfordeling i kjernens leverandørfakturabilde i 0.50.0

En valgfri, lesende [kjøpskontroll](purchase-allocation.md) vises i Dolibarrs
leverandørfakturabilde. Valgte innenlandske 25/15/12-profiler for fullt fradrag
sammenligner bevart netto, MVA, brutto og konkrete linjekontoer med hovedbok.
Kreditnota, summerte poster, endrede kilder og bilagsfullstendighet håndteres
uten en ny bilagsflyt. Fradragsrett velges eller godkjennes ikke automatisk.

Dette kvalifiserer teknisk sammenligning innen valgt omfang. Neste A2-arbeid
er uttrykkelig delvis/ingen fradragsbehandling og dokumenterte korreksjonsløp.
Blandede satser, utenlandskjøp, nullsats, manuelle bilag, registreringshistorikk
og B1/B2-aksept gjenstår. Papirløs opplasting og matching ligger fortsatt sist.

## A2 – uttrykkelig delvis/ingen fradragssammenligning i 0.51.0

Den eksisterende kjøpskontrollen sammenligner nå også delvis og ingen
fradragsføring for valgte enkeltsatser. Delvis krever uttrykkelig prosent;
ørebundet linjeavrunding og kreditnotafortegn kontrolleres. Resterende MVA
fordeles på bevarte kjøpskontoer i sammenligningen, uten endring i hovedboken.
Kjernens globale bokføring med avgift på kjøpskonto er undersøkt og testet;
NPR er uttrykkelig avgrenset og brukes ikke som norsk fradragsmodell.

Dette er teknisk sammenligning av et valgt grunnlag, ikke lagret historisk
fradragsvalg eller implementert delvis fradragsbokføring. Neste A2-arbeid er
begrunnede, versjonerte fradragsvalg, linjevis sporbarhet og kvalifisert
bokførings-/korreksjonsintegrasjon. Blandede satser, utenlandskjøp, nullsats,
manuelle bilag, registreringshistorikk og B1/B2-aksept gjenstår.
Papirløs bilagsflyt og matching er fortsatt prioritert sist.

## Valgfrie kunde-/leverandørprefikser i 0.52.0

Nummerserieoppsettet tilbyr valgfrie prefikser foran femsifrede kunde- og
leverandørkoder, eksempelvis K00001 og L00001. Prefiksbytte fortsetter hver
rolles teller uten å endre gamle koder. Brukte startnumre forblir låst.
Kjernens eksisterende allokering, bekreftelsesflyt og eksterne partskodemodul
gjenbrukes. Tidligere oppsett leses med tomme prefikser; valg endres bare ved
uttrykkelig bekreftelse. Ingen skjemaendring.

Dette er en avgrenset oppsettsforbedring etter prosjekteiers valg. Videre
A2-prioritet er uendret: begrunnede historiske fradragsvalg, linjevis sporbarhet
og bokførings-/korreksjonsintegrasjon. Papirløs bilagsflyt ligger fortsatt sist.


## A2 – begrunnede historiske fradragsvalg i 0.53.0

Leverandørfakturabildet tilbyr uttrykkelig forhåndsvisning og bekreftet lagring
av fullt/delvis/ingen fradrag med begrunnelse. Valget og beregnede linjebeløp
bevares sammen med fakturagrunnlag, bruker, tidspunkt og stigende revisjon.
Samme andel gjelder alle linjer; innenlandske NOK-kjøp/kreditnotaer med én sats
25/15/12 og ørepresisjon støttes. Endret grunnlag gir varsel; gamle revisjoner
overskrives ikke. Uendret lagret valg brukes i den eksisterende sammenligningen
når brukeren ikke har valgt en annen profil. Ingen automatisk fradragsvurdering,
bokføringsendring eller påstand om et historisk bokføringstidspunkt.

Eksisterende versjonert kontrollarkiv gjenbrukes med egen dokumenttype og
payloadversjon; ingen skjemaendring eller tilbakefylling. Tester kvalifiserer
lagring/historikk, linjebeløp, endret grunnlag, replay, tilgang og HTTP-bekreftelse.
Videre A2-arbeid er kobling av valgt revisjon til nye bokføringshendelser,
kvalifisert fradragsbokføring og korreksjonsløp. Ulike andeler per linje,
blandede satser og særbehandling gjenstår. Papirløs bilagsflyt ligger sist.


## A2 – bevart vurderingsrevisjon ved bokføring i 0.54.0

Nye kjøpsposteringer bevarer siste lagrede fradragsvurdering med kildedata,
linjebeløp, revisjon og integritetsavtrykk i kjernens egen transaksjon.
Koblingen skiller mellom samsvarende grunnlag, endret/ikke støttet grunnlag
og manglende vurdering. Historikken vises i leverandørfakturabildet. Nyere
vurderinger endrer ikke gamle posteringers kobling. Tidligere payloadversjoner
leses uten tilbakefylling; ny kjøpspayload har versjon 6.

Dette kvalifiserer teknisk sporbarhet ved nye bokføringshendelser, ikke at
valgt fradrag er bokført. Kjernens kontofordeling er uendret, og den eksisterende
sammenligningen viser fortsatt avvik ved eksempelvis valgt delvis fradrag og
full MVA-postering. Tester dekker kjøpsjournalen, kreditnota, revisjonsbytte,
kildeendring, manglende valg, semantisk integritet, ikke-administrativ operatør,
rollback og bevaring av eldre payload. Ingen skjemaendring.

Neste A2-arbeid er kvalifisert fradragsbokføring og korreksjonsløp med bevart
vurderingsrevisjon. Blandede satser, ulike linjeandeler og øvrig særbehandling
gjenstår. Papirløs bilagsflyt er fortsatt sist.


## A2 – bekreftet førstegangsbokføring i 0.55.0

Leverandørfakturabildet kan forhåndsvise og bokføre et nytt kostnadsbilag med
siste lagrede, uendrede fradragsvurdering. Fullt, delvis og intet fradrag ved
25/15/12 støttes for innenlandske NOK-fakturaer og kreditnotaer, med samme andel
på alle linjer. Beløp avrundes per linje og posteres via kjernens API med
konkret linjekobling, hovedbokskonto, leverandørreskontro og vurderingsrevisjon.

Omfanget krever aktive kostnadskontoer i klasse 4–7 i valgt kontoplan, standard
leverandørkonto 2400, støttet inngående MVA-konto, leverandørreskontrokode,
åpen entydig regnskapsperiode og nøyaktig ørebalanse. Gamle eller fjernede
posteringer i kontrollarkivet sperrer ny førstegangsbokføring. Bekreftelse
verner kilde/oppsett/historikk; hele bilaget og arkivet rulles tilbake ved feil.
En egen versjonert bokføringsregistrering bevarer bekreftet grunnlag og
posterings-ID-er. Ingen DDL eller endring av tidligere posteringer.

Vanlig kjøpsjournal sperres for nye posteringer med lagret delvis/intet fradrag,
siden den ikke anvender valget. Bekreftet flyt kan ikke brukes til å erstatte
allerede bokførte bilag. Tekniske tester kvalifiserer kontofordeling og
sporbarhet innen omfanget, ikke fradragsrett eller norsk produksjonsetterlevelse.
Neste arbeid er et uttrykkelig korreksjonsløp med bevarte originalposter og
vurderingsrevisjoner. Blandede satser, ulike linjeandeler, eiendeler, nullsats,
utland og øvrig særbehandling gjenstår. Papirløs bilagsflyt ligger sist.
