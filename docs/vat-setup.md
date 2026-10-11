# Kontroll av MVA-kontokoblinger

Fra modul 0.20.0: åpne **Norsk oppsettsveiviser → steg 3 → Kontroll av MVA-kontokoblinger**. Siden er skrivebeskyttet og viser bare aktive norske satsrader i aktivt foretak.

Hver rad viser sats, intern kode/rad-ID, salg/kjøp, satsradens konto, standardkonto som reserve og kontoen som undersøkes. En satsrad som gjelder både kjøp og salg vises i begge retninger. Deaktiverte satser og andre lands/foretaks rader utelates. Et tomt resultat gir en manglende-melding.

Satsradens utfylte konto prioriteres fremfor standardkontoen. Tom konto bruker `ACCOUNTING_VAT_SOLD_ACCOUNT` for salg og `ACCOUNTING_VAT_BUY_ACCOUNT` for kjøp som reserve. Kjernens tomverdi `0` behandles som tom. En utfylt, men ugyldig konto skjules ikke av en gyldig standardkonto. Dette er oversikt over konfigurasjonen, ikke en beregning av hvilken satsrad kjernen velger for en faktisk fakturalinje.

Kontostatus skiller ikke angitt, manglende, inaktiv, tvetydig og tilgjengelig. Kontoen må finnes i valgt kontoplan og aktivt foretak; både plan og konto må være aktive. **Tilgjengelig** bekrefter bare dette strukturelle forholdet. Riktig kontoklassifisering, fradrag og norsk avgiftsbehandling bekreftes ikke.

## Hva som fortsatt må vurderes

En sats alene er ingen full avgiftskode. Nullsats skiller ikke alene mellom fritak, unntak, salg før registrering og utenlandssalg. Se [Skatteetatens forklaring av fritak og unntak](https://www.skatteetaten.no/bedrift-og-organisasjon/avgifter/mva/slik-fungerer-mva/forskjellen-pa-fritak-og-unntak-fra-merverdiavgift/). Gjeldende satser og bruksområder må vurderes mot [Skatteetatens satsoversikt](https://www.skatteetaten.no/satser/merverdiavgift).

Registreringsperioder, historiske satser, flere satser med samme prosent/kode, kundens/leverandørens land, særregler, lokale avgifter, fradrag og omvendt avgiftsplikt inngår ikke i denne kontrollen. Den setter ingen kontoforslag og endrer ingen satser, kontoer, registreringsstatus eller bilag. Den leser ingen fakturalinjer eller saldoer. Norsk MVA-melding, rapporteringsmapping og avstemming mot hovedbok gjenstår.

Lenkene åpner kjernens avgiftsregister og standardkontoer. Gjennomgå eksisterende og historisk bruk før endringer; ikke endre gamle satser bare for å få en annen kontostatus.

Teknisk grunnlag: `c_tva`, `accounting_account`, `accounting_system` og reservekontoene i `accountancy/journal/sellsjournal.php` og `purchasesjournal.php` i støttet Dolibarr 24.0.1. Syntetiske tester dekker eksplisitt/reservekobling, tomme valg, aktive/inaktive kontoer og planer, nullsats, foretak/land og databasefeil. Norsk/engelsk webvisning og visning etter fersk installasjon/gjenoppretting inngår i CI.
