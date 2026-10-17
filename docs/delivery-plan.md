# Samlet leveranseplan for norsk lokalisering

Oppdatert 2026-10-17. Gjeldende modulversjon: **0.80.0**. Planen følger
[gjennomgangen av 0.75.0](review-0.75.0.md), med funn R01–R17. Dette er én
samlet mangelliste og prioritering. [Leveransehistorikken](delivery-history.md)
beskriver fortiden; den utvider ikke bestillingen.

## Mål og avgrensning

En sammenhengende modul for ett norsk foretak med NOK, varer og tjenester,
norsk/engelsk tilbud og faktura, norske regnskaps-/avgiftsopplysninger og
kvalifisert rapportering. Kjernens dokumenter, kjøpsjournal, bank, reskontro,
hovedbok og perioder skal brukes. Foretaksform og faktisk bruksområde avgjør
hvilke norske regler som gjelder; støtte loves først for kvalifisert omfang.
Valgfrie prefikser/startnummer og sperring av sjekker beholdes som bestilt.
Papirløs bilagsflyt tas sist. Eventuell ekstern lisensadapter er valgfri og
skal ikke være en avhengighet fra regnskapskjernen.

Få kodelinjer er ikke en forenkling hvis brukeren får flere manuelle steg.
Før ny eller vesentlig endret regnskapsflyt implementeres, sammenlignes relevant
leverandørdokumentasjon fra etablerte norske programmer. Deretter prøves
kjernen/eksisterende tillegg. Brukerflyt er inspirasjon; norske krav avgjøres
med primære regelkilder. Ingen leverandørs interne kontonumre eller kodemodell
kopieres ukritisk. Referanser finnes i [dekningsanalysen](coverage-analysis.md#referanser-for-normal-arbeidsflyt).

## Hva som er levert nå

- Native dokument- og bokføringsgrunnlag, kontoliste, norske kodevalg,
  foretaksopplysninger, nummerering, språk/NOK og delvis MVA-uttrekk finnes.
- 0.76.0 erstatter elleve veivisersteg med [én oppstartsoversikt](setup-wizard.md):
  oppgave, aktuell status, nødvendig forklaring og lenke. Den skriver ikke
  regnskapsdata og lagrer ingen fullførings-/godkjenningsstatus.
- R01 rettes med eksplisitt fjerning av utgåtte eide PHP-filer og kontroll av
  uventet kjørbar kode ved oppdatering. Historikk, andre moduler og data beholdes.
  Oppgraderingstesten prøver filrestene og bevaring. Dette løser ikke alle
  øvrige drifts- eller regnskapsfunn.
- 0.77.0 ferdigstiller grunnoppsettet i etappe 2 (R02, R07–R10): native
  kontoklasser og prøvd resultat/saldoovergang, ett native oppsett for kontoer,
  journaler og flere bankkonti, supplering av manglende MVA-kodevalg i brukt
  oppsett og uavhengig låsing av hver nummerserie. Gamle poster/numre bevares.
  Full årsavslutning og skatt tilhører fortsatt etappe 6.
- 0.78.0 leverer dokumentdelen av etappe 3: ENK/AS/ASA på norsk/engelsk,
  «Foretaksregisteret» for AS/ASA og kreditnota for original i tidligere system
  via kjernens referansefelt og standalone-funksjon. Leveringsperiode/-sted og
  kundereferanse bruker eksisterende felt. Native bokføring, MVA-fortegn og
  refusjonsregistrering er prøvd.
- 0.66.0/0.75.0 sine separate fradragsrettinger og 0.74.0 sin manuelle 87-flyt
  er historiske reserveveier. Ny normalflyt erstatter dem fra 0.79.0; gamle poster bevares.
- 0.79.0 lukker R03/R04 i etappe 3 for innenlandske 25/15/12-kjøp og NOK-
  tjenestekjøp fra utlandet med 25 prosent beregnet avgift: fradrag på vanlig
  linje, fritekst, ett native bilag, kreditnota, betaling/refusjon og avstemt
  faktisk fradrag. Se [omfang, eksempler og prøvebevis](purchase-vat.md).

Hele modulen har **ikke** samlet regnskapsfaglig produksjonsaksept. Status i
veilederen, README og denne planen skal vise de samme kjente begrensningene.

## Fast rekkefølge og ferdigkriterier

| Etappe | Arbeid og funn | Ferdig når |
| --- | --- | --- |
| 1. Ryddig grunnlag | R01, R11, R12, R17: filutrulling, riktig testgrunnlag, enkel veileder og konsistent dokumentasjon/versjon. | Gammel installasjon oppgraderes uten gamle skriveflater eller datatap. Aktuelle brukerlenker virker. Mangler vises presist; det er ingen ekstra godkjenningsflyt. 0.76.0 dekker filutrulling og veileder; bred dokumentasjonsopprydding gjenstår. |
| 2. Norsk grunnoppsett | **Levert i 0.77.0 for grunnoppsettet.** R02, R07–R10: native kontoklasser, kontokoblinger, journaler, bank og uavhengige nummerserier. | Et nytt og et allerede brukt regnskap kan settes opp med forståelige native verktøy. Balanse-/resultatgrupper virker i kjernen. Egne kontonavn og flere bankkonti håndteres. Utstedte numre og gamle posteringer bevares. |
| 3. Vanlig dokument-/kjøpsflyt | **Levert i 0.78.0/0.79.0 for dokumentert omfang.** R03, R04, R06: dokumentinnhold, innenlands/utland, ingen/delvis/full fradragsrett, kreditnota og betalingsoppfølging. | Behandling velges ved vanlig registrering, og riktig kostnad/MVA/gjeld bokføres. Kjøp, fritekst der relevant, kreditnota og betaling henger sammen. Et separat rettingsbilag er ikke hovedvei for hvert kjøp. Norske/engelske PDF-er er prøvd for støttet omfang. |
| 4. Bank, perioder og MVA | **Levert i 0.80.0 for ordinær tomånedlig portalvei og støttede koder.** R05, bank/reskontro, registreringsovergang, oppgjør, retting og periodelåsing. | Samlet syntetisk periode avstemmer dokumenter, betalinger, hovedbok og bank. Senere oppsett endrer ikke historiske beløp. Brukeren valgte Skatteetatens portal som første leveringsvei: bruker logger inn, kontrollerer og leverer, og oppbevarer faktisk kvittering på kjernens MVA-oppgjør. Native vedlegg/opphenting er prøvd; ingen faktisk myndighetsinnsending eller direkte integrasjon er utført. |
| 5. EHF og SAF-T | Eksisterende EHF-/Peppol-tillegg kvalifiseres; norsk SAF-T leveres med riktig mapping. EHF-undersøkelse kan gå parallelt fra etappe 2. | EHF-faktura/kreditnota er validert og sendt med mottakerreferanser og sporbar status gjennom valgt løsning. SAF-T er skjemavalid og avstemt mot komplett regnskap, med kontoer, avgifter, motparter og bilag. 1.40 er målformat for 2027. Ingen avtale, kjøp eller mottakerregistrering gjøres uten uttrykkelig bestilling. |
| 6. Årsavslutning og skatt | Kjernens avslutning, kontogrupper og resultatdisponering; næringsspesifikasjon og skattemelding som egen leveranse. | Inngående saldoer og årsresultat avstemmes. Foretaksform/inntektsår, skattemessige forskjeller, avskrivninger og tilleggsopplysninger er håndtert. En konkret leveringsvei er kvalifisert. SAF-T erstatter ikke skattemeldingen. |
| 7. Samlet aksept og drift | R13–R16 og rester: vedlikeholdsvindu, moduloverganger, skjermbredde, valgfrie hjelpemidler, tilgang og bevaring. | Hele akseptmatrisen under består i fersk og oppgradert installasjon. Feil, låsing, retting, bevaring og gjenoppretting er prøvd. Støttet bruk og alle eksterne avhengigheter er dokumentert. |
| 8. Papirløs bilagsflyt | Kjernens eksisterende mottak/vedlegg og aktuelle tillegg brukes først. | Registrering og matching er prøvd uten en parallell bokføringsmotor. Tas sist som avtalt. |

Etappe 4 gjelder én NOK-enhet, ordinære tomånedlige terminer og kodene
3/31/33, 1/11/13, 5/6/52 og 86/87; 0/7 vises utenfor meldingen. Oppgjøret
registreres én gang per termin som vanlig bilag i kjernen, ut fra rapportens
forslag og faktisk portalkvittering. Vareimport, særordninger, andre
terminordninger og spesifikasjoner som uttak/kompensasjon er ikke kvalifisert.
De er betingede gap, ikke skjulte deler av ferdigmeldingen. Direkte innsending
inngår ikke i den valgte portalveien. [Arbeidsflyt og prøvebevis](vat-period-close.md).
Neste etappe er EHF/SAF-T; papirløs bilagsflyt ligger fortsatt sist.

## Felles akseptmatrise — hele arbeidsflyter

En etappe avsluttes med samlet bevis for relevant rad, ikke bare test av siste
kodeendring. Bruk én liten syntetisk regnskapsperiode med uavhengig forventet
fasit og native dokument-/betalingsflyt.

| Arbeidsflyt | Må vises før den regnes som ferdig |
| --- | --- |
| Oppstart og systembytte | Foretak/MVA, kontoplan og kontotyper, kontoer, journaler, bank, nummerstart, inngående saldoer og åpne poster. Historikk bevares. |
| Innenlandsk salg/kjøp | Varer/tjenester, relevante satser, riktig dokument, kontering, full/ingen/delvis fradragsrett der aktuelt, kreditnota og betaling/refusjon. |
| Utenlandske transaksjoner | Relevante land/leveranser, dokumentert behandling, salg og kjøp, bokført beregnet avgift og faktisk fradrag. Bevar opprinnelig kjøpsdokument og nødvendig omregningsgrunnlag selv om salget er i NOK. |
| Perioder og MVA | Grensedatoer, registreringsovergang, manuelle bilag, retting, oppgjør, avstemming og historisk lesing etter endring av oppsett. Innsendingsfeil og korrigert melding inngår når innsending leveres. |
| EHF og SAF-T | Faktura og kreditnota, validering, transportstatus/kvittering for EHF; fullstendighet og avstemming av norsk SAF-T. |
| Årsskifte og skatt | Resultat/balanse, periodisering og saldoovergang uten dobbeltføring, foretaksform/årsversjon, skattemessige forskjeller og faktisk leveringsvei. |
| Drift og rettigheter | Fersk installasjon og oppgradering fra faktisk gammel fil-/datamodell, feil under oppdatering, ikke-admin/ekstern bruker, slettede skriveflater, backup og isolert gjenoppretting. |
| Brukerflyt | Oppgavene finnes der de hører hjemme, uten dobbeltregistrering eller overflødige bekreftelser. Norsk/engelsk og relevante PC-/mobilbredder prøves. |

Samme datagrunnlag skal avstemme dokument → bokføring → kreditnota/betaling →
reskontro/bank → MVA/eksport → årsavslutning der relevant. Tester som forventer
bare at to summer er like, er ikke tilstrekkelig bevis for riktig behandling.

## Behandling av hvert funn

R01–R17 beholdes som stabile referanser. Nye funn føyes til med konkret bevis;
vi oppretter ikke stadig nye parallelle planer. Et funn er lukket når:

1. Det konkrete gapet og nødvendig brukerresultat er beskrevet.
2. Kjerne/tillegg og relevant normalflyt er undersøkt før eventuell ny kode.
3. Endringen og bevaring av eksisterende data er prøvd i riktig arbeidsflyt.
4. Dokumentasjon, veilederstatus og testbevis stemmer med faktisk støtte.

Dette er utviklingskriterier, ikke nye bekreftelsesoppgaver for brukeren.
En reservevei kan dokumenteres, men regnes ikke som ferdig normalflyt.
Et lukket område åpnes igjen ved en konkret feil eller endret krav, ikke bare
fordi en ny produktidé dukker opp. Kjente uløste funn flyttes ikke bort ved
å øke versjonsnummeret.

## Leveranser og avhengigheter

- Utvikling skjer på staging; main krever PR og beståtte påkrevde tester på
  aktuell revisjon. Bare main og staging beholdes, uten force-push.
- En teknisk delversjon kan leveres med tydelig avgrensning. Hele modulen
  erklæres ikke ferdig før samlet aksept for avtalt omfang er dokumentert.
- Eksisterende regnskapsdata, dokumenter, numre, volumer, passord og instansnøkkel
  bevares. Skjemaendringer krever versjonert migrering, backup og restore-prøve.
- Det innføres ikke nye kontrollsider, generelle historikkmodeller eller en
  særskilt kjøpsmotor for å lukke listen. Nødvendige kontroller hører til ved
  oppgaven i kjernen; oppsett hører til innstillingene.
- Kjøp/avtale med EHF-leverandør, myndighetsautorisasjon og virksomhetens
  faglige valg kan kreve avklaring når en konkret løsning foreligger. Dette
  hindrer ikke kildearbeid, kompatibilitetsprøver og øvrige uavhengige oppgaver.

Kravgrunnlaget er [kravregisteret](norwegian-requirements.md) og
[kilderegisteret](sources.md). Ulike bransjer, ansatte, kontantsalg og
forbrukersalg gir betingede krav; de skal ikke automatisk bli nye produktspor.
