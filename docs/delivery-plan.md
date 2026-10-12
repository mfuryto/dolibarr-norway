# Videre utviklingsplan

Oppdatert 2026-10-11. Dette er arbeidsrekkefølgen, ikke en påstand om at norsk produksjonsregnskap er ferdig. Hver leveranse utvikles på staging, testes isolert og promoteres gjennom PR til main. Drift oppdateres med backup og kontrollert revisjon. Bare main og staging brukes.

## Avklart omfang

Ett foretak, ENK uten ansatte i første omfang. Programvare er første salgsområde, med offentlige kunder og mulig utenlandskjøp/-salg. Salgsdokumenter og krav føres i NOK. EUR er bare veiledende prisvisning. Norsk og engelsk støttes på tilbud og faktura. Sjekker og sjekkinnskudd skal ikke brukes. Lisensintegrasjon kommer senere som en valgfri generell adapter.

Faktisk MVA-registerstatus og virkningsdato må bekreftes per installasjon. Programvarens leveringsmodell, land og kundetype må avklares før utenlandske avgiftsscenarioer kvalifiseres. Ingen automatisk antakelse om registrering ved første faktura.

## Det som allerede er levert

- Vedvarende Docker-drift, oppdatering med privat backup, installasjonskontroll og testet gjenoppretting. Standard drift og syntetiske testmiljøer er adskilt.
- Ekstern modul uten endring av Dolibarr-kjernen; støttet og låst testversjon er 24.0.1. Ny kjerneversjon krever egen kompatibilitetstest og tilgjengelig offisielt image.
- NOK-begrensning, norsk/engelsk PDF, organisasjonsnummer/MVA-identitet og fjerning av sjekkfunksjoner.
- Grunnleggende fakturakontroll ved validering, kildefaktura for kreditnota og syntetiske tester av nummerering, betaling, hovedbok og PDF.
- Skrivebeskyttet bilagskontroll av balanse og kundedokumentkoblinger. Denne avstemmer ikke reskontro eller MVA.
- Manuelt registeroppslag for dagens MVA-status. Ingen automatiske innstillingsendringer eller historisk registreringsbekreftelse.
- CSS-tilpasninger prøvd på Eldy-tema og representative sider/skjermbredder.

## Samlet oppsettveiledning og veiviser

[Veiledningen for norsk oppsett](norwegian-setup-guide.md) samler rekkefølge, innstillinger, betingede norske forhold og leveransegap. Fra 0.12.0 finnes en skrivebeskyttet veiviser med elleve steg, aktuelle kontroller og lenker til innstillinger. Fra 0.13.0 finnes separat bekreftet utfylling av tom kunde-/leverandørstandard i nytt bokføringsoppsett. Fra 0.14.0 kan manglende journaltyper opprettes etter egen forhåndsvisning og bekreftelse. Fra 0.15.0 er norske foretaksfelt og et avgrenset register av organisasjonsformer levert uten endring av foretaksvalgene. Fra 0.17.0 kan manglende bankkoblinger fylles etter bekreftelse i et avgrenset nytt oppsett med én ordinær NOK-bankkonto. Øvrige foretaksvalg gjenstår; manuell arbeidsstatus er levert i 0.24.0. Veiviseren skal vise eksisterende valg, forklare kontoforslag og kreve forhåndsvisning/bekreftelse før lagring. Uklarhet, ikke relevant og uimplementert må vises hver for seg. Den skal ikke gi samlet produksjonsaksept.

## Prioritert rekkefølge

| Trinn | Arbeid | Når deltrinnet er ferdig |
|---|---|---|
| 1a – levert i 0.6.0 | Skrivebeskyttet kontroll av valgt kontoplan, aktive kontoer, standardkontokoblinger og journaler | Viser mangler/inaktive kontoer uten å endre oppsett; tester foretak- og kontoplanavgrensning, databasefeil og webtilgang |
| 1b – import og norsk kontoplan levert i 0.9.0; mapping gjenstår | Avgrenset norsk kontoplan med versjonert mapping og forhåndsvisning av import | Repeterbar import uten dubletter, endring av brukte kontoer eller historikk; konfliktkontroll, backup og gjenopprettingsprøve |
| 2 | Registreringsperioder og norske avgiftsbehandlinger; bokført avgiftsmetadata og sporbar MVA-rapport | Grunnlag og avgift avstemmes mot hovedbok; før/etter registrering, korreksjoner og fradrag er testet; manglende mapping vises som feil |
| 3 | Fordringer, leverandørgjeld og bankavstemming | Inngående saldo + bevegelser = utgående saldo; reskontro stemmer med kontrollkontoer; delbetaling, kreditering, refusjon og gebyrer er prøvd |
| 4 | Programvare og avklarte utenlandsscenarioer i NOK | Land, kundetype og leveringsmodell er dokumentert; relevante kjøp/salg og eventuell omvendt avgiftsplikt er faglig vurdert og testet |
| 5a | EHF/Peppol for offentlige kunder | Løsning og tilgangspunkt er kvalifisert for norsk profil; referanser og kreditnota er validert og akseptert av testmottaker |
| 5b | Norsk SAF-T-eksport mot fast offisielt skjema og kodelister | Kontoer, avgift, motparter, saldoer og totalsummer er komplette og avstemt; XSD og egne innholdskontroller består |
| 6 | Periodelåsing, endringsspor, oppbevaring og produksjonsaksept | Rettigheter og retting er prøvd; backup gir samme saldoer og dokumenter på separat instans; støttede scenarioer er regnskapsfaglig gjennomgått |

EHF-avklaring kan gjøres parallelt med tidligere trinn, fordi tilgangspunkt og mottakertest kan ta tid. Implementering av SAF-T bygger på kontoplan, avgiftsmetadata og komplette saldoer. EHF og SAF-T er separate leveranser og kan ikke erstatte hverandre.

## Første del gjennomført; neste leveranse

Kontrollsiden i trinn 1a viser valgt kontoplan, aktive/alle kontoer, ti standardkontoer for kunde, leverandør, produkt/tjeneste kjøp/salg og inngående/utgående MVA, samt aktive salgs-, kjøps-, bank- og diversejournaler. Fra 0.10.0 vises avgrensede kontoforslag, og fra 0.11.0 kontrolleres den faktiske bank-/kassekoblingen til konto, journal og valuta. Begge deler er skrivebeskyttet. Den skiller manglende, inaktiv, tvetydig og ikke angitt kobling.

Standardkontoer er reservekoblinger. Fravær beviser ikke at all bokføring er umulig, fordi produkt-/kunde-/linjekoblinger kan finnes. En tilgjengelig konto bekrefter heller ikke riktig norsk kontonummer eller avgiftsbehandling. Ingen samlet grønn «produksjonsklar»-status innføres. Trinn 1b har nå [CSV-forhåndsvisning og bekreftet import](chart-preview.md) med konfliktkontroll, engangsbekreftelse, ny kontroll ved import og atomisk tilbakeføring. [Norsk kontoplan med 745 kontoer](norwegian-chart.md) fra låst, lisensiert Odoo-kilde kan nå installeres og velges eksplisitt. Virksomhetens kontobruk og versjonert SAF-T-/næringsmapping gjenstår.

## Avklaringer som gjenstår

- MVA-registreringens faktiske virkningsdato og eventuell behandling før registrering.
- Om programvaren leveres som nedlasting, lisens, abonnement/SaaS eller andre tjenester, og hvilke land/kundetyper som brukes.
- Eventuelle inngående saldoer og åpne poster fra tidligere regnskapssystem; migrering er et eget avstemmingsarbeid.
- Offentlige mottakeres EHF-/ordrereferanser og valg av tilgangspunkt.
- Regnskapsfaglig gjennomgang av kontoplan, mapping, avgiftsbehandling og konkret produksjonsomfang.

Disse avklaringene stopper ikke skrivebeskyttede kontroller eller utvikling med syntetiske data. De må foreligge før avhengige scenarioer godkjennes for bruk.

## Fast ferdigkriterium per leveranse

Relevant kode- og integrasjonstest, dokumentert støttet omfang og kjente begrensninger, ingen reelle bilag eller hemmeligheter i Git, godkjente CI-kontroller og PR fra staging til main. Skjemaendringer krever versjonert migrering, backup og restore-prøve. Nye funksjoner skal bevare eksisterende bilag, passord, lagring og instansnøkkel.

Se [dekningsanalysen](coverage-analysis.md), [kilderegisteret](sources.md) og [test og drift](test-and-operations.md). Ingen tidsestimat fastsettes før de større avgifts- og integrasjonsavklaringene er gjort.

Fra 0.16.0 fylles et tomt MVA-nummer fra gyldig norsk organisasjonsnummer ved ordinær lagring når «MVA brukes» er valgt. Eksisterende MVA-nummer og registreringsstatus endres ikke. Se [foretaksoppsett](company-setup.md).

Bankkoblingens avgrensning og vern er beskrevet i [bekreftet bankkobling](bank-defaults.md). Bank- og reskontroavstemming gjenstår.

Fra 0.18.0 samler [dokumentoppsettkontrollen](document-setup.md) valgt nummerering, nummermasker, effektive PDF-modeller og språk. Den er skrivebeskyttet; konkret dokumenttest og gjennomgang av etablerte nummerserier gjenstår per installasjon.

Fra 0.19.0 viser [periodediagnostikken](period-setup.md) perioder, datodekning, overlapp, standardvalg og kjernens periodeinnstilling uten endringer. Full kvalifisering av periodelåsing og retting gjenstår.

Fra 0.20.0 viser [MVA-kontokontrollen](vat-setup.md) aktive norske satsrader, eksplisitte kontoer, reservekontoer og strukturell kontostatus. Avgiftsbehandling, bokført metadata, rapporteringsmapping og MVA-melding gjenstår.

Fra 0.21.0 kan én [MVA-virkningsdato lagres eksplisitt](vat-registration.md), knyttet til foretaksidentitet og bekreftelsestid. Datovarsel i fakturakontrollen endrer ingen bilag eller MVA-status. Rettbar historikk, flere registreringsperioder, avgiftsbehandling og MVA-melding gjenstår.

Fra 0.22.0 viser [fakturaens MVA-beløp](invoice-amounts.md) lagrede linjebeløp og sammenligner netto/MVA med fakturatotalene. Null MVA krever begrunnelsesgjennomgang. Kontroll av avgiftsbehandling, hovedbok og MVA-melding gjenstår.

Fra 0.23.0 har veiviseren en fast oppgaverekkefølge, forklarte knapper som åpner ny fane, og separate seksjoner for lagrede innstillinger, tilleggskontroller og avgrensninger. Navigasjonen lagrer fortsatt ikke fremdrift eller innstillinger.

Fra 0.24.0 kan administrator lagre arbeidsstatus per veivisersteg. Oversikten viser status for alle steg, avgrenset til aktivt foretak. Endret organisasjonsnummer krever ny gjennomgang. Markeringene endrer ikke oppsett, dokumenter eller kontrollresultater og gir ingen produksjonsaksept.

Fra 0.24.1 bruker veiviserens steg 6 «Varer og tjenester» og generelle leveransebeskrivelser på norsk og engelsk. Dette er en tekstpresisering, uten endret funksjonelt eller regnskapsfaglig støttet omfang.

Fra 0.24.2 omtaler veiviseren leveranser generelt, og fakturakontrollen bruker varer/tjenester fremfor programvare. Organisasjonsformer omtales som valg, uten å kvalifisere særregler. Dette endrer ikke dokumentert støttet regnskapsomfang eller uferdige funksjoner.

Fra 0.25.0 foreslår veiviseren hvor arbeidet kan fortsette ut fra eksisterende arbeidsstatus. Direkte valg av steg beholdes, og navigasjon endrer ingen markeringer eller innstillinger.

Fra 0.26.0 samler [fakturakontrollen for periode](invoice-period.md) dokumentstatus, type og beløpsavvik for et datointervall i aktivt foretak. Ingen summer på tvers av dokumenter eller MVA-melding beregnes; avgiftsmetadata, rapportering og hovedboksavstemming gjenstår.

Fra 0.27.0 viser [fakturalinjenes lagrede avgiftsopplysninger](invoice-tax-evidence.md) kildekode og særmarkering uten oppslag i dagens satsregister. Periodeoversikten teller tomme koder og markeringer. Uforanderlig avgiftshistorikk, norsk rapporteringsmapping og MVA-melding gjenstår.

Fra 0.28.0 viser fakturakontrollen eksakt medlemskap i en fast offisiell SAF-T-kodereferanse. Lik skrivemåte bekrefter ikke mapping eller avgiftsbehandling. Neste faglige grunnlag er eksplisitt, versjonert mapping med historisk metadata; MVA-melding og SAF-T-eksport gjenstår. Se [avgiftsopplysninger](invoice-tax-evidence.md).

Fra 0.29.0 kan administrator [prøve én datert avgiftsmapping](tax-mapping-preview.md) mot det åpne dokumentets lagrede linjekoder. Valget lagres ikke, og særmarkeringer må avklares. Versjonert mapping med rettbar historikk, bokførte avgiftsmetadata og rapportering gjenstår.

Fra 0.30.0 kan administrator [lagre daterte avgiftskodekoblinger](tax-mappings.md) per foretak og salg/kjøp, med bekreftet retting, avslutning og bevart oppsettshistorikk. Overlapp og foreldede forhåndsvisninger sperres. Koblingene brukes ennå ikke ved bokføring eller rapportering. Kobling til bokførte avgiftsmetadata og historisk rapportversjon gjenstår.

Fra 0.31.0 kan [fakturalinjer sammenlignes mot en uttrykkelig valgt oppsettsversjon](invoice-mapping.md). Senere rettinger/avslutninger tas ikke med i kandidatsammenligningen. Dette oppretter ikke bokført avgiftsgrunnlag eller historisk rapportering.

Fra 0.32.0 viser [hovedboksreferanser for fakturaen](invoice-ledger.md) faktisk lagrede posteringer og referanseavvik. Samlede posteringer skilles fra bestemte fakturalinjer. Dette er ikke beløps-/MVA-avstemming eller bokførte avgiftsmetadata.

Fra 0.33.0 finnes et [versjonert lagringsgrunnlag for avgiftsopplysninger](tax-evidence-storage.md), med migreringshistorikk, strukturelt vern og testet backup/gjenoppretting. Ingen avgiftsøyeblikksbilder opprettes i ordinær drift ennå. Innholdsskjema, bekreftet lagring/retting og kobling til faktiske posteringer må kvalifiseres før rapportering.

Fra 0.34.0 kan administrator forhåndsvise og bekrefte [kontrollkopier av dagens fakturagrunnlag](invoice-evidence.md), hovedboksreferanser og valgt oppsettsversjon. Ny kopi bevarer tidligere revisjoner, og endringer etter forhåndsvisning sperrer lagring. Dette dokumenterer ikke opprinnelig bokføringstidspunkt og godkjenner ikke MVA-behandling. Neste del er en lesbar sammenligning av lagret kopi mot dagens grunnlag, før videre beløpsavstemming og kvalifisering av rapportering.

Fra 0.35.0 kan [enkle nummerserier](numbering.md) for F/K/T og kunde-/leverandørnumre velges med startnummer, forhåndsvisning og bekreftelse. Eksisterende numre beholdes. Dette er kvalifisert nummereringsfunksjonalitet innen dokumentert omfang, ikke full norsk regnskapsmessig produksjonsaksept.
