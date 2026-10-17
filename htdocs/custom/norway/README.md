# Norway 0.75.0

**Versjon 0.75.0 · Under utvikling**

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

## Norske MVA-koder

Det eksisterende MVA-oppsettet kan opprette separate ordinære salgskoder
3/31/33 og kjøpskoder 1/11/13 i kjernens satsregister for nye, ubrukte oppsett.
MVA-kontohjelpen støtter disse radene. Native fakturalinjer bevarer kodevalget.
Standardvalget i de prøvde skjermbildene er avgrenset til riktig salgs-/kjøpsretning.
Eksisterende regnskap og produkter legges ikke om automatisk. Ingen ny meny,
kontrollside, kodemodell eller lagringstabell innføres.

MVA-meldingen er fortsatt uferdig. Manuelle nullsatsbilag, utlandstilfeller og komplett
avstemming/innsending gjenstår.

Fra 0.70.0 finnes et lesende MVA-uttrekk under regnskapsrapportene for
ordinære norske koder, med avstemming av fakturaer, kreditnotaer og manuelle
fradragsrettinger mot bokføringen. Uavklarte poster sperrer netto og CSV.
Full MVA-melding, særtilfeller og innsending gjenstår.

0.70.0 skiller innenlands fritak (5), omsetning utenfor MVA-loven (6) og
inntekt som ikke er MVA-omsetning (7) i samme uttrekk. Manglende kodevalg
gjøres tilgjengelige i det native satsregisteret; eksisterende dokumenter
omklassifiseres ikke. Kode 7 holdes utenfor meldingsgrunnlaget i CSV.

0.71.0 støtter innenlandske kjøp uten MVA-behandling i samme uttrekk.
Velg `0 (NO0)` på leverandørfakturalinjen; NO0 er et lokalt navn for norsk
kode 0 fordi kjernen mister den bokstavelige verdien «0». Uttrekket viser
kode 0 separat, uten fradrag eller meldingsgrunnlag. Vanlig kjøpsjournal,
kreditnotaer og varer/tjenester gjenbrukes. Kjøp med MVA uten fradragsrett
krever fortsatt den ordinære fradragsrettingen; NO0 erstatter ikke dette.

0.72.0 gjør eksportkode 52 tilgjengelig i satsregisteret og det samme
MVA-uttrekket. Eksplisitt kodede NOK-fakturaer/kreditnotaer til utenlandske
kunder bruker kjernens salgsjournal, for både varer og tjenester.
Eksportgrunnlaget vises med null norsk avgift; landet setter ikke koden
automatisk. Se kodeveiledningen i prosjektets dokumentasjon for eksportflyten.
Full MVA-melding og innsending gjenstår. Tjenestekjøp med kode 86 er beskrevet nedenfor.

0.73.0 gjenbruker kjernens omvendte avgiftsplikt for NOK-kjøp av tjenester
med 25 % og full fradragsrett (kode 86). Leverandørgjelden har ingen norsk
MVA; kjøpsjournalen fører beregnet MVA på 2704 og fradraget på 2714.
Uttrekket avstemmer og viser begge retninger under 86. Kjernens funksjon
må settes opp før bruk; eksisterende kontovalg og fakturaer endres ikke.
Se kodeveiledningen i prosjektets dokumentasjon før bruk.
Ingen/delvis fradragsrett, andre satser og vareimport gjenstår.

0.74.0 støtter kode 87 på manuelt bokførte NOK-tjenestekjøp uten fradragsrett,
med 25 % beregnet MVA på 2704 og grunnlag på det vanlige bilagskortet.
Uttrekket viser bare utgående avgift for 87. Denne avgrensede flyten oppretter
ikke leverandørfaktura eller betalingsoppfølging i fakturamodulen; den må
ikke brukes i tillegg til en allerede bokført faktura for samme kjøp.
Se veiledningen for manuelle MVA-bilag i prosjektets dokumentasjon.
Automatisk leverandørfakturaflyt for 87 gjenstår. Delvis fradrag kan fra
0.75.0 håndteres med separat, vanlig rettingsbilag med kode 86 på 2714.
Beregnet MVA og grunnlag beholdes; faktisk inngående fradrag summeres i
uttrekket. Prosenten velges ikke automatisk, og kreditnota krever motsatt
retting. Ingen ny side, tabell eller bokføringsmotor.
Se [føring av delvis fradrag](../../../docs/manual-vat.md#delvis-fradrag-på-tjenestekjøp-kode-86-i-0750).

De manuelle fradragsrutinene og kode 87-flyten over er **reserveveier**.
En sammenhengende normalflyt for ingen/delvis fradragsrett gjenstår:
behandling ved registrering, bokføring, kreditnota og rapportering. Separate
rettingsbilag skal ikke regnes som ferdig standardflyt. Dette prioriteres
før flere importtilfeller. Modulversjonen er fortsatt 0.75.0; denne
presiseringen endrer bare dokumentasjon og utviklingsprioritering.
