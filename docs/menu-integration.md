# Menyer og arbeidsflyt

Oppdatert 2026-10-15 for 0.59.0. Dette viderefører M0, ikke ferdig
regnskapsfaglig produksjonsaksept. Ingen nye regnskapsrapporter innføres.
Menynavn følger språk og aktiverte kjernefunksjoner.

## Tilgjengelige innganger

| Oppgave / eksisterende side | Ordinær inngang | Tilgang og videre arbeid |
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
| `vat.php`, `vataccounts.php`, `vatrates.php`, `registration.php`, `taxmappings.php` | Fra 0.59.0: Regnskap → Oppsett → kontroll av MVA-kontokoblinger, med innganger til satser, salg-/kjøpskontoer, registerhistorikk og avgiftskodekoblinger. Dette er oppsett, ikke en MVA-melding. |
| `productaccounts.php` | Fra 0.59.0: katalogmeny og egen fane på varen/tjenesten. Fanen viser bare valgt element og bevarer valget ved innenlands-/eksport-/EU-visning. Eksisterende kontokoder og standardkontoer beholdes. Administrator med rett til kontoplanoppsett; ordinær operatørtilgang gjenstår. |
| `pricing.php` | Fra 0.59.0: tilbudsmenyen, fjernet fra veilederen og moduloppsettet. Administrator med tilbudslesing. Kun veiledende EUR-beløp; bokføring og fakturering fortsatt i NOK. Henting krever fortsatt brukerens beregningshandling. |
| `evidence.php` | Fra 0.59.0: egen fane ved aktuell kundefaktura og retur til denne. Lagring av kontrollkopi er fortsatt administratorstyrt; ingen utvidelse av skriverettigheter. |
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


Fra 0.59.0 oppdateres også eksisterende fanedefinisjoner atomisk ved
modulaktivering. Oppdateringskontrollen verifiserer alle tre faner. Produktets
ID valideres og filtreres i databasespørringen, med samme foretaksavgrensning
som oversikten. En ugyldig ID utvider aldri rapporten til hele katalogen.
Testene prøver valgt vare/tjeneste i tre kontekster, fremmed foretak og ugyldig
ID, gamle fanedefinisjoner og native menyer/faner på norsk og engelsk.
