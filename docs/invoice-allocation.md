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
