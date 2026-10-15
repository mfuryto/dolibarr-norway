> Historisk dokumentasjon til og med 0.64.0. Funksjonen er fjernet i 0.65.0.
> Se [gjeldende plan](delivery-plan.md). Eldre tilleggshistorikk beholdes i arkivet.

# Hovedboksreferanser for en faktura

Fra 0.32.0 har Norsk fakturakontroll en valgfri seksjon **Hovedboksreferanser
for fakturaen**. Den krever også aktiv regnskapsmodul og leserettighet til
hovedboken. Utvalget er hovedbokslinjer i aktivt foretak med dokumenttype
`customer_invoice` og dokument-ID lik den åpne fakturaen.

Visningen viser hovedboks-ID, journal, bilagsnummer, dokumentdato i hovedboken,
fakturalinje-ID, konto og lagret debet/kredit. Referansekontrollen skiller mellom:

- En linjereferanse som finnes på akkurat denne fakturaen.
- Samlet postering med linje-ID `0`, uten en bestemt fakturalinje.
- Manglende eller feil linjereferanse.
- Avvik mellom hovedbokens dokumentreferanse og fakturaens nåværende referanse.
- Manglende journal, positivt bilagsnummer eller kontonummer.

Et treff bekrefter bare referansen. Ingen balansekontroll, beløpsavstemming,
MVA-fordeling eller kontroll av fullstendig bokføring foretas. Andre dokumenter
på samme bilag inngår ikke i dette utvalget. Flere avvik på én rad vises med
første relevante referansefeil; det er ikke en uttømmende feilrapport.

Ingen funn betyr ikke automatisk at en faktura skulle vært bokført eller aldri
har vært bokført på en annen måte. Rapporten viser maksimalt de første 200
linjene, sortert på hovedboks-ID. Flere treff gir uttrykkelig avkortingsvarsel.
Lesefeil skilles fra et vellykket, tomt utvalg. Ingen totalsummer beregnes.

Ingen data endres og ingen skjemaendring innføres. Nåværende kildekoblinger er
ikke uforanderlige historiske avgiftsmetadata. Kobling mellom avgiftsgrunnlag,
valgt mappingversjon og bokført beløp krever videre arbeid før MVA-melding eller
SAF-T-eksport kan leveres.

Teknisk aksept: faktisk fakturaoverføring til hovedbok, linje-/samlekobling,
foretaks-/dokumenttypeavgrensning, avvik, manglende nøkkel/konto, avkorting,
lesefeil og uendrede data testes syntetisk. Norsk/engelsk visning og bredde
kontrolleres separat.
