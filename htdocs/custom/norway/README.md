# Norway 0.70.0

**Versjon 0.70.0 · Under utvikling**

Ekstern modul for Dolibarr 24.0.1 og PHP 8.2 eller nyere. Kjernen endres ikke.
Ett foretak og NOK som basis- og dokumentvaluta støttes.

Modulen gir norsk kontoplan og standardkontoer, MVA-kontooppsett,
foretaksopplysninger, norske/engelske dokumenter og valgbare nummerserier.
Sjekkbetalinger og sjekkinnskudd er deaktivert. Veiledende EUR-prisvisning er valgfri.

Bruk kjernens fakturaer, tilbud, kjøpsjournal, hovedbok, perioder, bank og
produktkontoer. Oppsettsveilederen viser lenker og forklaringer; den lagrer
ingen godkjenning. Norsk regnskapsoppsett ligger under Regnskap → Innstillinger.

Fra 0.65.0 er egne kontrollsider, kontrollfaner, begrunnelsesregistrering,
kontrollkopiering og særskilt kjøpsbokføring fjernet. Tidligere lagret
historikk beholdes uendret i et skrivebeskyttet arkiv for interne administratorer
med lesetilgang til regnskap. Arkivet finnes i modulinnstillingene når data finnes.
Tidligere særposterte kjøp sperres mot ny overføring av samme faktura eller
innblanding i samme bilag. Separate rettingsbilag i kjernen er mulig.

MVA-rapportering, SAF-T og EHF/Peppol er ikke ferdigstilt. Fullt, delvis eller
manglende MVA-fradrag må håndteres faglig i kjernens oppsett og bokføring;
modulen avgjør ikke fradragsrett. Ingen versjon er godkjent som en komplett
løsning for norsk produksjonsregnskap.

Se prosjektets README, docs/norwegian-setup-guide.md og docs/delivery-plan.md.
GPL-3.0-or-later; kontoplanens egne kilde- og lisensopplysninger følger dataene.

0.66.0 retter NPR-begrepet på norsk/engelsk og prøver native kjøp/kreditnotaer
med blandede 25/15/12-satser samt separate 50/0-prosent-omposteringer.
Fradragsandel avgjøres faglig og bokføres manuelt i kjernen; NPR er en fransk
særordning og endrer prisberegningen. Ingen egen kjøpsflyt eller fradragshistorikk
innføres. Fakturauttrekket er fortsatt ikke en komplett norsk MVA-melding.

## Norske MVA-koder i 0.70.0

Det eksisterende MVA-oppsettet kan opprette separate ordinære salgskoder
3/31/33 og kjøpskoder 1/11/13 i kjernens satsregister for nye, ubrukte oppsett.
MVA-kontohjelpen støtter disse radene. Native fakturalinjer bevarer kodevalget.
Standardvalget i de prøvde skjermbildene er avgrenset til riktig salgs-/kjøpsretning.
Eksisterende regnskap og produkter legges ikke om automatisk. Ingen ny meny,
kontrollside, kodemodell eller lagringstabell innføres.

MVA-meldingen er fortsatt uferdig. Manuelle bilags norske avgiftsopplysninger,
nullsats-/utlandstilfeller og komplett avstemming/innsending gjenstår.

Fra 0.70.0 finnes et lesende MVA-uttrekk under regnskapsrapportene for
ordinære norske koder, med avstemming av fakturaer, kreditnotaer og manuelle
fradragsrettinger mot bokføringen. Uavklarte poster sperrer netto og CSV.
Full MVA-melding, særtilfeller og innsending gjenstår.

0.70.0 skiller innenlands fritak (5), omsetning utenfor MVA-loven (6) og
inntekt som ikke er MVA-omsetning (7) i samme uttrekk. Manglende kodevalg
gjøres tilgjengelige i det native satsregisteret; eksisterende dokumenter
omklassifiseres ikke. Kode 7 holdes utenfor meldingsgrunnlaget i CSV.
