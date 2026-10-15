> Historisk dokumentasjon til og med 0.64.0. Funksjonen er fjernet i 0.65.0.
> Se [gjeldende plan](delivery-plan.md). Eldre tilleggshistorikk beholdes i arkivet.

# Kontroll av bokført kunde-, salgs- og MVA-fordeling

Fra 0.42.0, første avgrensede del av A2 i [arbeidsplanen](delivery-plan.md).
Åpne fakturakontrollen, velg fakturaen, utvid hovedbokspostene og velg en
kontrollprofil. Dette er en lesende sammenligning, ingen bokføring.

| Valgt ordinær innenlandsk profil | Kunde | Salgskontoer | Utgående MVA |
| --- | --- | --- | --- |
| 25 % | 1500 | 3000, 3010, 3020 | 2700 |
| 15 % | 1500 | 3030, 3040 | 2701 |
| 12 % | 1500 | 3050 | 2703 |

Numrene kommer fra den medfølgende kontoplanprofilen. Bruk av profilen er
uttrykkelig; den velges ikke ut fra kundens land, dagens satskontoer eller
standardkontovalg. Andre legitime kontoplaner, råfisksatsen, rabattkontoer,
uttak, viderefakturering og alternative kunde-/salgskontoer krever separat
kontroll. En post utenfor profilen er ikke automatisk en bokføringsfeil.

## Hva sammenlignes?

Fakturaens lagrede brutto forventes på kundefordringen. Netto og MVA fra
linjene sammenlignes separat med posteringer på profilens salgs- og
MVA-kontoer. Alle posteringene vises med faktisk hovedboks-ID, konto, rolle
og signert beløp: debet minus kredit. Kreditnotaer har motsatte fortegn.

Kontrollen oppdager eksempelvis at 250 i MVA er ført som salgsinntekt, selv
om et bilag med brutto 1250 fortsatt balanserer. Ukjente kontoroller kan ikke
skjules ved at posteringene summerer til null. Posteringer under samme
bilagsnøkkel som ikke inngår i fakturauttrekket sperrer resultatet.

Lagrede linjebeløp kontrolleres mot fakturahodet og valgt prosentsats.
Beløp behandles med åtte desimaler; faktura-/fordelingsavvik har ett øres
toleranse, mens debet og kredit i hele bilaget må balansere nøyaktig.
Avkorting, lesefeil og manglende kildedata gir ikke samsvar.

Data leses i én konsistent, skrivebeskyttet databasetransaksjon. Rapporten
avviser en allerede aktiv transaksjon og avslutter bare sin egen. Den
skriver ingen innstillinger, dokumenter eller kontrollkopier.

## Støttet omfang og begrensninger

Ordinære utstedte norske NOK-kundefakturaer og kreditnotaer med én valgt
sats, ett bilag og norske selger-/kjøperopplysninger. Linjer med lokale
avgifter, særmarkeringer, andre satser eller motgående fortegn avvises.
Maksimalt 200 linjer og 200 kildetilknyttede posteringer; beløpsgrensene fra
[bruttokontrollen](invoice-ledger.md) gjelder.

Samsvar betyr kun at dagens lagrede beløp fordeles som den valgte profilen
forventer. Det bekrefter ikke riktig avgiftsplikt, registreringsperiode,
produktklassifisering, opprinnelig kontovalg eller uforanderlig historikk.
Kontrollen grupperer salgsbeløpet; den kvalifiserer ikke korrekt salgskonto
for hvert produkt eller hver tjeneste. Dagens kunde-/foretaksland er heller
ikke en historisk dokumentasjon av partenes land.

Blandet avgiftsbehandling, nullsats, innkjøp, manuelle bilag, import,
omvendt avgiftsplikt, delvis fradrag, perioderapportering og låst historisk
avgiftsgrunnlag gjenstår. A2 er derfor ikke ferdig, og en MVA-perioderapport
kan ikke bygges ved bare å summere disse kontrollresultatene.

## Kravgrunnlag

[Skatteetatens generelle satser](https://www.skatteetaten.no/satser/merverdiavgift/)
og den låste kontoplanprofilen bestemmer de avgrensede kontrollprofilene.
[NBS 2 Kontrollsporet](https://www.regnskapsstiftelsen.no/bokforing/bokforingsstandarder/nbs-2-kontrollsporet/)
brukes som kravgrunnlag for sporbarhet. Kontrollen alene oppfyller ikke hele
standarden. Kildene ble kontrollert 2026-10-13.

## Lagrede linjekontoer fra 0.43.0

Kontrollen leser `facturedet.fk_code_ventilation` og slår opp akkurat denne
konto-ID-en i aktivt foretak. Dagens produktkort, standardkontoer og valgt
kontoplan brukes ikke til å fylle tomme koblinger eller forklare historikk.
Manglende eller fremmede kontokoblinger gir gjennomgang, og fremmede
kontonavn vises ikke. Nå inaktive kontoer beholdes i sammenligningen;
inaktivitet alene beviser ikke at en eldre postering er feil.

Netto summeres per konkret lagret salgskonto og sammenlignes med faktiske
salgsposter. Dette kan avdekke at 600 på konto 3000 og 400 på 3020 feilaktig
ble bokført som 400 og 600, selv om samlet salg og MVA er riktig.
Direkte hovedboksreferanser kontrolleres mot riktig fakturalinje, konto og
beløp når hele salget er postert med linjereferanser.

Hvis noen salgsposter er aggregert (`fk_docdet=0`), vises **samsvar per
kontosum**, uten bekreftelse av hver linjes beløp. Kjente feil i direkte
linjereferanser skjules ikke av andre aggregerte poster. Den samlede
kontrollstatusen kan ikke vise ordinært samsvar ved manglende koblinger
eller avvik i linjekontoene.

Dette er fortsatt en sammenligning av dagens lagrede kilder. Konto-ID,
kontonummer, kontonavn og linjekobling er ikke et uforanderlig bevis på hva
som gjaldt ved opprinnelig bokføring. Den nye delen erstatter ikke historisk
avgiftsmetadata eller faglig vurdering av riktig salgskonto for en leveranse.
Neste arbeid under A2 er historisk avgiftsgrunnlag og videre kjøps-/bilagsdekning.

## Sammenligning mot bevart grunnlag fra 0.47.0

Den eksisterende profilvelgeren viser nå også **Fordeling mot bevart
fakturagrunnlag**. Det gjenbruker samme 25/15/12-profiler, beløpsregning og
kontofordeling, men forventede beløp og linjekontoer kommer fra opplysningene
som ble bevart ved bokføring. Dagens produkt-/standardkontoer, linjer og
oppsett erstatter ikke dette grunnlaget. Ingen ny side eller bokføringsflyt
innføres; dette er en valgfri diagnose for den som utreder rapportgrunnlaget.
Vanlig bokføring krever ingen ekstra manuell kontrollrunde.

Hendelser og dagens hovedbok leses i én skrivebeskyttet, konsistent
transaksjon. Alle aktuelle poster må ha intakte, fullstendige observasjoner
med samme faktura-, linje-, motparts- og oppsettsgrunnlag. Manglende,
fjernede eller sprikende observasjoner, avkorting og endrede postbeløp eller
referanser kan ikke gi samsvar. Hele bilaget kontrolleres også for fremmede
posteringer i samme journal/bilagsnummer. Opplysningene omskrives aldri.

Dagens kildeopplysninger kan være endret uten at den bevarte sammenligningen
forsvinner; dette varsles uttrykkelig. Manglende spor til enkeltlinjer blir
ikke skjult av riktige summer. **Summerte kontoer** betyr bare samsvar på
kontosummer. Det bekrefter ikke fordeling på hver linje eller historisk
avgiftsbehandling. Samme profil brukes som uttrykkelig sammenligningsgrunnlag,
aldri som automatisk tolkning av SAF-T-kode eller lovlig MVA-behandling.

Ved uavklart resultat undersøkes kildeobservasjonene og hovedboken før
rapportgrunnlaget brukes. Det er ingen rapporterings- eller produksjonsaksept.
Eldre posteringer uten hendelsesgrunnlag etterfylles ikke; kjøp, manuelle
bilag, nullsats, korreksjonsflyt og kvalifisert avgiftsrapportering gjenstår.
Testene prøver reell overføring fra salgsjournalen, kreditnota, kildeendringer,
hovedboksendringer, manglende/sprikende grunnlag og et balansert bilag med
feil MVA-fordeling. Den eksisterende lagringen og payloadversjonene beholdes,
uten skjemaendring.
