# Norsk grunnoppsett

Åpne **Oppstartsoversikten**, eller **Regnskap → Innstillinger → Norsk regnskapsoppsett**.
Fra 0.77.0 leder oppsettet til kjernens vanlige verktøy:

| Oppgave | Hvor | Hva du velger |
| --- | --- | --- |
| Kontoplan | Installer norsk kontoplan / Kontoplan | Den medfølgende planen ved ny oppstart. Egne navn og grupper beholdes ved komplettering av samme plan. |
| Standardkontoer | Regnskap → Innstillinger → Standardkontoer | Kunde 1500, leverandør 2400 og passende vare-/tjeneste-/avgiftskontoer. Velg bare relevante felt. |
| Journaler | Regnskap → Innstillinger → Journaler | Salg, kjøp, bank og diverse. Kode og navn kan velges fritt. |
| MVA | Norske avgiftskodevalg, deretter MVA-kontoer | Aktuelle kodevalg og kontokoder på satsradene. Se [MVA-kontoer](vat-accounts.md). |
| Bank | Bankkontolisten → aktuell konto | Betalingsopplysninger, NOK, bokføringskonto og bankjournal for hver faktisk bankkonto. |
| Varer/tjenester | Regnskapskoder på produktkortet og kjernens produktkontooppsett | Konto etter leveransetype og avgiftsbehandling. |
| Nummerserier | Oppstartsoversikten → Nummerserier | Eget prefiks/startnummer for hver serie. Utstedte numre beholdes. |
| Perioder og inngående saldoer | Kjernens regnskapsperioder og hovedbok | Riktig periode og dokumenterte åpningssaldoer/åpne poster ved systembytte. |
| Avslutningsoppsett | Kjernens avslutningsoppsett | Kontogrupper, journal for inngående saldo og resultatkonto etter faktisk foretak. |

Separate utfyllingssider for standardkontoer, journaler, MVA-kontoer og bank er
fjernet. Det er ingen ekstra bekreftelse i modulen når du bruker kjernens oppsett.
Egne kontonavn og flere bankkonti er tillatt. En endret standardkonto flytter
ikke bokførte poster, men kan påvirke dokumenter som ennå ikke er overført.
Vurder derfor dokumentkøen før endring i et brukt regnskap.

Kontoklasse 1–2 brukes som balanse og 3–8 som resultat i den medfølgende planen.
Oppdateringen fyller bare manglende grupper på kjente kontonumre, og tomme
avslutningsgrupper. Egne valg bevares. Det opprettes ingen bilag og ingen periode
avsluttes ved oppdatering. Se [kontoplanen og testomfanget](norwegian-chart.md).

Grunnoppsett er ikke samlet regnskapsfaglig produksjonsaksept. Normalflyt for
fradrag, full MVA-melding, EHF, SAF-T og skatt følger [leveranseplanen](delivery-plan.md).
