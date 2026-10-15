# Menyer og arbeidsflyt

Oppdatert 2026-10-15 for 0.58.0. Dette er første del av M0, ikke ferdig
regnskapsfaglig produksjonsaksept. Ingen nye regnskapsrapporter innføres.
Menynavn følger språk og aktiverte kjernefunksjoner.

## Tilgjengelige innganger

| Oppgave / eksisterende side | Ordinær inngang fra 0.58.0 | Tilgang og videre arbeid |
| --- | --- | --- |
| Bilagskontroll (`audit.php`) | Regnskap → regnskap/hovedbok → Bilagskontroll | Intern bruker med leserett til hovedbok. Skrivebeskyttet, avgrenset kontroll av balanse og fakturakobling; ikke komplett avstemming. |
| Periodekontroll (`periods.php`) | Regnskap → regnskap/hovedbok → Kontroll av regnskapsperioder | Samme leserett. Endring av perioder krever kjernens egen skriverett; oppsettslenker skjules uten relevant rettighet. |
| Fakturakontroll (`invoice.php`) | Egen fane på kundefakturaen, med retur til fakturaen | Foreløpig administrator med faktura- og hovedboklesing. Detaljdiagnostikk; videre arbeid må forenkle avvik og rettingshandlinger og kvalifisere eventuell ordinær brukertilgang. |
| Fakturakontroll for periode (`invoiceperiod.php`) | Fakturering → kundefakturaer | Foreløpig administrator med fakturalesing. Dette er ikke MVA-perioderapport. |
| Kontoplan og bokføringsoppsett (`accounting.php`) | Regnskap → Oppsett | Administrator. Eksisterende innganger herfra til `chart.php`, `profile.php`, `defaults.php`, `journals.php` og `standardaccounts.php` beholdes. |
| MVA-registerhistorikk (`registration.php`) | Regnskap → Oppsett | Administrator; endrer oppsett, ikke fakturalinjer. |
| Avgiftskodekoblinger (`taxmappings.php`) | Regnskap → Oppsett | Administrator. Lagrede koblinger med eksisterende forhåndsvisning og bekreftelse. |
| Nummerserier (`numbering.php`) og dokumentoppsett (`documents.php`) | Fakturering → kundefakturaer | Administrator med fakturalesing. Dokumentoppsettet omfatter også tilbud. |
| Bankkontokoblinger (`bankdefaults.php`) | Bank | Administrator med banklesing; oppsettets eksisterende lagringsvern beholdes. |
| Fradragsvurdering, bokføring og korreksjon (`purchasededuction.php`) | Leverandørfakturaens eksisterende arbeidsbilde | Beholdes. Krever fortsatt de dokumenterte administrator-/faktura-/hovedbokrettighetene og skriverett for handlingen; egen operatørtilgang gjenstår. |

Daglige innganger til faktura-, periode- og bilagskontroll er fjernet fra
oppstartsveilederen. Moduloppsettet samler heller ikke lenger faktura- og
bilagskontroll. Veilederen beholder førstegangsoppsett og lenker til relevante
innstillinger; funksjoner implementeres ikke en gang til der.

## Kartlagt videre integrasjon

| Eksisterende side / funksjon | Videre plassering og avgrensning |
| --- | --- |
| `setup.php`, `wizard.php`, `registry.php` | Modul-/foretaksoppsett og oppstart. Registeroppslag er oppsett, ikke en regnskapskontroll som skal kjøres for hvert bilag. |
| `vat.php`, `vataccounts.php`, `vatrates.php`, `taxmappings.php` | Regnskap → relevant avgiftsoppsett. `vat.php` er en kontroll av kontokoblinger, ikke en MVA-melding. Egen direkte plassering av øvrige oppsettssider gjenstår. |
| `productaccounts.php` | Varer og tjenester / relevant regnskapsoppsett. Eksisterende kontokoder beholdes; direkte integrasjon i produktarbeidsflyten gjenstår. |
| `pricing.php` | Relevant pris-/tilbudsarbeid. Kun veiledende EUR-beløp; bokføring og fakturering fortsatt i NOK. Direkte integrasjon gjenstår. |
| `evidence.php` | Ved aktuell kundefaktura som fordypning. Lagring av kontrollkopi er fortsatt administratorstyrt; ingen utvidelse av skriverettigheter i denne leveransen. |
| Bankavstemming, MVA-rapport/innsending, årsavslutning og SAF-T | Integreres ved sine respektive oppgaver når funksjonene kvalifiseres. Ingen tomme menypunkter eller rapporter som fremstår som ferdige. |

## Teknisk aksept

Ekstern modul registrerer venstremenyer og fakturafane uten kjerneendringer.
Gjentatt aktivering erstatter modulens egne menyrader i kjernens transaksjon;
deaktivering fjerner inngangene uten å slette regnskapsdata. Ingen nye
rettigheter gis automatisk. Lesetilgang til bilags-/periodekontroll krever
aktiv norsk modul, aktivt regnskap og eksisterende hovedbokleserett.
Eksterne brukere er avvist både i menyen og ved direkte tilgang.

`tests/navigation.php` prøver gjentatt aktivering, deaktivering, norsk/engelsk
HTTP, bruker uten rettigheter, bruker med bare hovedboklesing, avviste
administrasjonssider, skjulte skrivehandlinger, faktisk menyplassering,
fakturafane og fravær av daglige kontrollinnganger i veilederen.
