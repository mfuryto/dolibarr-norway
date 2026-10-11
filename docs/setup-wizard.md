# Norsk oppsettsveiviser

Fra modul 0.12.0: Åpne Norway-moduloppsettet og velg **Norsk oppsettsveiviser**. Administrator med aktiv Norway-modul kreves. Siden er skrivebeskyttet og bruker aktivt foretak. Den gjør ingen eksterne oppslag ved åpning og bruker ikke produksjonsdata som testdata.

Elleve steg følger [samlet norsk oppsettveiledning](norwegian-setup-guide.md): virksomhet/avgrensning; foretak/tilgang; MVA; kontoplan/journaler; bank; motparter/programvare; salgsdokumenter; offentlig/utland; saldoer/perioder; rapportering/oppbevaring; gjennomgang før bruk. Alle tekster finnes på norsk bokmål og engelsk. Oversikten lar administratoren hoppe mellom stegene, og forrige/neste endrer bare siden som vises.

Fra 0.23.0 har hvert steg en kort innledning, nummererte oppgaver og «Før du går videre». Oppgaver forklarer hvor du skal gjøre endringen, hva du skal fylle inn og når oppgaven gjelder. Innstillingsknapper åpner en ny fane; lagring skjer der, og veiviseren beholder plassen i den opprinnelige fanen. Manuelle steg sier uttrykkelig at de avklares utenfor systemet.

Lagrede innstillinger vises under «Vis dagens innstillinger (kun lesing)», ikke som utfyllingsfelt. Tilleggskontroller og avanserte innstillinger er samlet i en egen, lukket seksjon med forklaring per lenke. Begrensninger har en egen seksjon. Teknisk navngitte standardkontoer har forståelige etiketter i veiviseren. Ingen innstillinger eller regnskapsdata endres ved åpning eller navigasjon. Foretakssteget leser eksisterende navn-/adresse-/nummer-/land-/valutakontroller. MVA-steget viser nåværende innstilling uten å behandle den som historisk registreringsbevis. Konto-/journalsteget bruker den eksisterende oppsettskontrollen. Banksteget bruker samme foretak-/planavgrensede bankkontroll som oppsettssiden. Fakturasteget viser NOK og dokumentenes språkinnstilling uten å godkjenne nummerering eller PDF-innhold.

Det finnes ingen ferdigknapp eller samlet produksjonsklar-status. Manuelle valg, ikke relevant, ansvarlig, begrunnelse og kontrollbevis må foreløpig dokumenteres separat. Veiviseren lagrer ikke fremdrift, standardkontoer eller andre innstillinger. Den viser lenker til oppsettsider der administratoren selv kan gjøre endringer. Fra 0.13.0 lenker kontoplansteget til [bekreftet utfylling av kunde-/leverandørkonto](default-accounts.md). Lagringen skjer på en separat bekreftelsesside; veivisernavigasjonen lagrer fortsatt ikke valg eller fremdrift. Fra 0.14.0 lenker samme steg også til [bekreftet oppretting av manglende journaltyper](journal-setup.md). Bankkobling velges fortsatt separat. Andre kontoforslag og fremdriftslagring gjenstår; uimplementerte MVA-/EHF-/SAF-T-funksjoner kan ikke kvitteres bort.

## Verifikasjon

Autentisert HTTP-test åpner alle elleve steg på nb_NO og en_US, kontrollerer navigasjon, lesefeil og manglende oversetting samt avgrensing av ugyldige stegnumre. Uautentisert bruker får ikke veiviserinnhold. Eksisterende databasetester dekker kontrollklassene og isolering mellom foretak/planer. Nettleserprøven inkluderer alle elleve steg med lukkede og åpne detaljseksjoner ved fire skjermbredder; den er ingen regnskapsfaglig aksept. Det er ingen nye tabeller eller migreringer.

Fra 0.15.0 forklarer steg 2 forskjellen på organisasjonsnummer, MVA-nummer og organisasjonsform. Foretaksoppsettet har tydelige norske feltnavn og norske organisasjonsformer, uten automatisk valg. Se [foretaksoppsett](company-setup.md).

Fra 0.17.0 lenker steg 5 til separat [bekreftet bankkobling](bank-defaults.md) for ett avgrenset nytt NOK-oppsett. Veiviseren i seg selv er fortsatt skrivebeskyttet.

Fra 0.18.0 lenker steg 7 til [kontroll av dokumentoppsett](document-setup.md). Ingen numre reserveres og ingen dokumenter endres.

Fra 0.19.0 lenker steg 9 til [kontroll av regnskapsperioder](period-setup.md). Ingen perioder åpnes/lukkes.

Fra 0.20.0 lenker steg 3 til [kontroll av MVA-kontokoblinger](vat-setup.md). Ingen avgiftsoppsett eller bilag endres.

Fra 0.21.0 lenker steg 3 til [bekreftet første MVA-virkningsdato](vat-registration.md). Veiviseren i seg selv lagrer fortsatt ingen valg.

Fra 0.22.0 viser fakturakontrollen i steg 7 også [lagrede MVA-beløp](invoice-amounts.md), linjesummer og eventuelle avvik mot fakturatotalene. Dette er lesekontroll, uten godkjenning av avgiftsbehandling.
