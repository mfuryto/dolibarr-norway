# Første omfang og leveranseplan

Oppdatert 2026-10-11 etter brukerens svar. Estimatene er arbeidsdager for én
utvikler, med kvalifisert regnskapsfaglig gjennomgang tilgjengelig. De er
planleggingsintervaller, ikke tilbud eller løfte om juridisk etterlevelse.

## Avgrenset første leveranse

Første leveranse er **teknisk pilot**: ett syntetisk ENK, NOK som hovedbokvaluta,
norske testtransaksjoner før/etter MVA-registrering, faktura, del-/helkredit,
betaling og innenlandske innkjøp. Den avgrenser risiko og bygger testgrunnlag;
den erstatter ikke brukerens internasjonale produksjonsbehov.

Brukerens første produksjonsomfang må også omfatte kvalifisert EHF for aktuelle
offentlige kunder, EUR-fakturering og -oppgjør, relevante utenlandskjøp og salg,
samt norsk MVA-behandling for hvert faktisk scenario. Brukeren har avklart at salget foreløpig bare gjelder programvare.
Leveringsmodell (lisens/nedlasting/SaaS), leveringssted og land er fortsatt åpne. Oppstart må
vente på at de aktuelle arbeidsflytene har bestått.

## Milepæler

| Trinn | Leveranse og ferdigkriterium | Foreløpig innsats |
|---|---|---|
| 0 | Dekningsanalyse, modulgrunnlag, Docker og syntetiske avstemminger (denne leveransen) | Grunnlag levert; videre analyse under følger |
| 1 | Avklare status/land/varer-tjenester, saldoer og feltmatrise mot 1.40; demonstrere faktura→hovedbok inkl. EUR | 3–6 dager |
| 2 | Avgrenset kontoplan, næringsmapping, norske koder/gyldighet og registreringsprofiler; test repeterbar import | 5–9 dager |
| 3 | Bokført avgiftsmetadata, sporbar MVA-rapport, korreksjon, periodekontroll og norske PDF-krav | 8–15 dager |
| 4 | SAF-T 1.40, komplette saldoer/reskontro, validering og negative tester | 10–18 dager |
| 5 | Utenlandskjøp/-salg, EUR-kurs, agio/disagio og alle avklarte avgiftsbehandlinger | 8–16 dager etter scenarioavklaring |
| 6 | EHF/Peppol-kvalifisering, mottakeraksept og driftsprøve | 4–8 dager pluss leverandør-/tilgangspunktventing |
| 7 | Regnskapsfaglig kontroll, restore-prøve og begrenset produksjonsaksept | 4–7 dager |

Trinn 1–7: foreløpig 42–79 arbeidsdager, uten full Akaunting-migrering,
ny bankintegrasjon, automatisk innsending eller lisensportal. Reestimer etter
trinn 1; særlig utenlandsomfang og EHF-avtale kan endre både tid og kostnader.

## Akseptansetester før produksjon

- Kontoplan kan installeres om igjen uten duplikater eller endring av brukte
  kontoer. Alle eksporterte kontoer har riktig, versjonert næringsmapping.
- Før registrering: salg uten oppkrevd MVA; innkjøp bokføres med riktig
  brutto kostnad når fradrag ikke foreligger. Overgangsdato og eventuell
  tilbakegående behandling må være regnskapsfaglig avklart og egne tester.
- Etter registrering: norske fakturaer med korrekte satser/koder, rabatter,
  blandede avgiftsbehandlinger, avrunding, full/delvis kreditering og refusjon.
- Hver journalpost balanserer. Inngående saldo + bevegelser = utgående saldo
  per konto og motpart, også ved delperiode og årsskifte. Reskontro =
  kontrollkontoer; betaling = bank, med særskilte gebyr-/kursdifferanser.
- MVA-rapport viser grunnlag/avgift per behandling og lenke til bilag/linje;
  summer avstemmes mot hovedbok. Manglende bilag, mapping eller ubalanse
  stopper eksport med forklarbar feil; ingen lydløs utelatelse.
- EUR-salg og -innkjøp har kjent kurskilde/dato og NOK-MVA. EUR-betaling til
  annen kurs gir korrekt agio/disagio. Relevant omvendt avgiftsplikt,
  innførsel og eksport testes etter bekreftet land-/produktmatrise.
- PDF viser relevante norske fakturaopplysninger, maskinell nummerserie,
  leveringssted/-tid og kildefaktura på kreditnota. Historiske dokumenter
  endres ikke ved nytt oppsett. EHF aksepteres av valgt offentlig mottaker.
- SAF-T 1.40 validerer mot fast offisielt skjema og innholdskontroller:
  linjer, konto-/avgiftsmapping, motparter, perioder, totalsummer, saldoer,
  sporbarhet og dublett-/entity-kontroller. Tom periode og årsskifte testes.
- Låste perioder og validerte bilag kan ikke endres av vanlige brukere.
  Retting skjer med sporbare nye poster; kontroller også adminrutiner,
  import og gjenåpning. Hvilke bokføringsspesifikasjoner som dekkes av
  hvilke rapporter dokumenteres eksplisitt.
- Backup av database, dokumenter og konfigurasjon gjenopprettes på separat
  instans og gir samme saldoer/rapporter/dokumenthash. Oppbevaring og
  tilgang må være dokumentert. Sletting av testvolumer er ingen driftsrutine.

Modulen kan ikke omtales som ferdig for norsk regnskap før støttede scenarier,
begrensninger og bevis er publisert og godkjent for faktisk produksjonsomfang.
