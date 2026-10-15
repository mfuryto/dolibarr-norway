> Historisk dokumentasjon til og med 0.64.0. Funksjonen er fjernet i 0.65.0.
> Se [gjeldende plan](delivery-plan.md). Eldre tilleggshistorikk beholdes i arkivet.

# Kontroll av faktura- og tilbudsoppsett

Fra modul 0.18.0 finnes en skrivebeskyttet side via **Norsk oppsettsveiviser → steg 7 → Kontroll av faktura- og tilbudsoppsett** og modulens oppsettsside. Den samler valgene som ellers ligger i separate innstillinger for faktura og tilbud.

Siden viser:

- Valgt nummereringsmodul for faktura og tilbud. Kjente kjernemoduler gjenkjennes uten å kjøre dem. Andre moduler vises for manuell gjennomgang; de er ikke automatisk feil.
- Faktura-/kreditnotamasker når Mercure er valgt, og tilbudsmaske ved Saphir. Tom maske vises som manglende; en utfylt maske må fortsatt prøves med syntetiske dokumenter.
- Standard PDF-modell og effektive fakturamodeller for ordinær faktura, erstatningsfaktura, kreditnota og forskuddsfaktura. Situasjonsfaktura vises når funksjonen er aktivert. Tomt valg per type faller tilbake til standardmodellen, slik kjernens innstillingsside gjør.
- Om den effektive modellen er aktivert for riktig dokumenttype i aktivt foretak. Manglende aktivering vises som manglende, selv om modellnavnet er utfylt. Sponge for faktura og azur for tilbud er prøvd i prosjektets syntetiske dokumentflyt; øvrige modeller må gjennomgås manuelt.
- Flerspråklige dokumenter, standard språk og basisvaluta. Kundens språk og dokumentets faktiske språk må fortsatt kontrolleres separat.

**Konfigurert** bekrefter bare den avgrensede innstillingskontrollen. Det er ingen godkjenning av nummerseriens egnethet, PDF-innhold, MVA-behandling, norsk etterlevelse eller EHF. Siden kaller ikke nummergenerator, reserverer ingen numre og oppretter eller endrer ingen dokumenter, innstillinger eller aktiverte modeller. Ingen dokumentopplysninger eller kundedata leses.

## Gjennomgang før fakturering

Bruk lenkene til faktura-/tilbudsinnstillingene ved mangler. Prøv tilbud, faktura og kreditnota på norsk og engelsk i et separat testmiljø. Kontroller faktiske nummer, identitet, leveranse, avgift, NOK-beløp og betalingsopplysninger på PDF. Ved overgang fra tidligere system må tidligere nummerserier og dokumenter avklares før første utstedelse. Ikke endre en etablert nummerserie bare for å få en annen status på kontrollsiden.

Teknisk grunnlag er kjernens `admin/invoice.php`, `admin/propal.php` og `document_model` i støttet Dolibarr 24.0.1. Tester dekker effektiv PDF-overstyring, modellaktivering per foretak/type, alternative modeller, nummermasker, språk, valuta, databasefeil og innlogget webtilgang på norsk/engelsk. Siden kontrolleres også ved fersk installasjon og gjenoppretting.

Fra 0.35.0 kan [nummerserier og startnummer](numbering.md) forhåndsvises og bekreftes på en egen oppsettsside. Dokumentoppsettkontrollen er fortsatt skrivebeskyttet.
