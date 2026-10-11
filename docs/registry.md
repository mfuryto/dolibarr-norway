# Manuell registerkontroll

Norway 0.5.0 har et administratoroppslag fra modulens oppsettside. Bare foretakets eget gyldige norske organisasjonsnummer sendes, først etter et manuelt knappetrykk med CSRF-beskyttelse. Det brukes fast HTTPS-endepunkt, eksplisitt v2-format, sertifikatkontroll, ingen omdirigeringer, tidsgrenser og størrelsesgrense. Ingen API-nøkkel kreves.

MVA-status sammenlignes med foretaksinnstillingen. Ingen innstillinger, fakturaer eller registreringshistorikk endres. Resultatet vises bare i denne forespørselen og lagres ikke. Manglende eller ugyldig status, slettet foretak, 404, 410 og nettverksfeil gir ukjent status, aldri en antakelse om at foretaket ikke er MVA-registrert. Et samsvar er ingen godkjenning av avgiftsbehandlingen eller produksjonsregnskapet.

Registeret viser dagens status. Kontroller registreringens virkningsdato og relevant periode separat. Oppslaget blokkerer aldri fakturering ved nettverksfeil. Eksisterende lokale fakturakontroller gjelder fortsatt.

Kilde: [Brønnøysundregistrenes API-dokumentasjon](https://data.brreg.no/enhetsregisteret/api/dokumentasjon/no/index.html). Syntetiske parser- og HTTP-tester kjører uten oppslag mot det offentlige registeret.

## Aksept for leveransen

Støttet omfang er administratorstyrt sammenligning av ett norsk foretaks nåværende MVA-innstilling mot registerets boolske status. Parseren prøves med syntetiske registrerte og uregistrerte foretak, feil typer, feil organisasjonsnummer, sletting og tjenestefeil. HTTP-testen kontrollerer autentisert skjema, ugyldig foretaksoppsett før nettverkskall og avvisning av ugyldig CSRF-token. Ingen registertjeneste simuleres som en vellykket produksjonskontroll i grensesnittet. Nettverkstilgjengelighet i den enkelte installasjonen er ikke verifisert av disse testene.
