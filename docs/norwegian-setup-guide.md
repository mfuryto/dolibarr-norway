# Samlet veiledning for norsk oppsett

Oppdatert for modul 0.67.0 og støttet Dolibarr 24.0.1. Start etter
[teknisk installasjon](vps-installation.md). Oppsettsveilederen viser
forklaringer og lenker til innstillingene. Lagring skjer på innstillingssiden;
veilederen har ingen egen status eller godkjenning. Bruk kjernens dokumenter
og regnskap i det daglige. Se [veilederens omfang](setup-wizard.md).

Du skal ikke måtte kjenne Dolibarrs interne konstanter eller velge alle de 745 kontoene. Følg rekkefølgen nedenfor. «Automatisk» betyr utført av installasjons-/oppdateringsskriptet; «manuelt» betyr at administratoren må lagre eller kontrollere valget. «Gjenstår» betyr at dokumentasjonen ikke kan erstattes med en innstilling i dagens modul.

Dette dokumentet samler norske oppsettforhold, men erklærer ikke komplett lovdekning. Omfanget er ett norsk foretak, NOK-salgsdokumenter og norsk/engelsk. Modulen er generell for varer og tjenester. Andre foretaksformer, ansatte, kontantsalg og særregler må vurderes særskilt. Full norsk MVA-rapport, kvalifisert EHF og samlet produksjonsaksept gjenstår. SAF-T har [avgrenset støtte](saft-export.md).


Fra 0.76.0 er oppstartsveilederen én [statustabell](setup-wizard.md), med
oppgave, forklaring og lenke. Overskriftene nedenfor er kapitler i veiledningen,
ikke steg eller ekstra godkjenninger i programmet. Status i oversikten viser
lagrede innstillinger og kjente mangler, uten å godkjenne regnskapet.

## 1. Avklar virksomheten før oppsett

Noter foretaksform, organisasjonsnummer, regnskapsårets start, oppstartsdato i systemet, MVA-status og virkningsdato. Avklar også land, kunde-/leverandørtyper, hva som faktisk leveres, betalingsmåter og om tidligere regnskap skal videreføres.

| Forhold | Hva som må avklares | Hvorfor det påvirker oppsettet |
|---|---|---|
| Ny drift eller overgang | Dato for overgang, tidligere system, åpne poster og saldoer | Hindrer dobbelt bokføring og tap av historikk |
| ENK, AS eller annen form | Registrert foretaksform og relevante registeropplysninger | Fakturatekst, egenkapital, eiertransaksjoner og rapportering varierer |
| MVA | Registrert/ikke registrert, vedtak og virkningsdato | Styrer avgift og fakturatekst; dagens registerstatus alene er ikke historikk |
| Leveranse | Lisens, nedlasting, SaaS/abonnement, konsulenttjeneste eller varer | Kan påvirke avgiftsbehandling, leveringsperiode og periodisering |
| Marked | Norge/utland, bedrift/offentlig/privat og mottakerland | Utenlandsk avgift og dokumentasjon må vurderes per scenario |
| Betaling | Bankoverføring, kort, betalingsformidler eller fysisk kontantsalg | Bankkoblinger, gebyrer, oppgjørsavstemming og eventuelle kassasystemkrav |
| Ansatte | Ingen ansatte eller arbeidsgiver | Lønn, a-melding, forskuddstrekk og øvrige arbeidsgiverplikter er eget område |

Uklarheter merkes «må avklares», ikke automatisk «ikke relevant». Lagre ikke fødselsnummer eller sensitive vedlegg i offentlige prosjektfiler.

## 2. Foretaksopplysninger og tilgang

**Manuelt:** Åpne Dolibarrs oppsett for firma/organisasjon. Lagre Norge som land før landavhengige felt fylles inn. Se [feltforklaring og norske organisasjonsformer](company-setup.md). Fyll inn registrert navn, organisasjonsnummer, adresse, postnummer, sted, land Norge og kontaktopplysninger. Velg NOK som basisvaluta. Kontroller faktiske registreringer; en kontrollsifferkontroll bekrefter bare nummerformat.

Åpne foretaksoppsettet og gjennomgå opplysningene. Registeroppslaget sammenligner dagens opplysninger med Brønnøysundregistrene, men endrer ikke MVA-innstillinger. Se [registerkontroll](registry.md).

Opprett personlige brukere og avgrens rettigheter for administrasjon, fakturavalidering, bank, bokføring og eksport. Bruk HTTPS og privat oppbevaring av passord og sikkerhetskopier. Regnskapsfører kan gis nødvendige rettigheter uten deling av administratorkonto.

**Automatisk:** driftsoppsettet aktiverer grunnmoduler og setter støttet NOK-/språkomfang. Det registrerer ikke foretaket hos myndighetene og fyller ikke inn foretakets identitet.

## 3. MVA-status og avgiftsbehandling

**Manuelt:** Bekreft faktisk registrering og virkningsdato fra vedtak/registreringsgrunnlag før fakturering med MVA. Den alminnelige registreringsgrensen er omsetning omfattet av reglene over 50 000 kroner i en tolvmånedersperiode; forhåndsregistrering har egne vilkår. Oppstart av fakturering betyr ikke automatisk registrering. Se [Skatteetatens registreringsveiledning](https://www.skatteetaten.no/bedrift-og-organisasjon/avgifter/mva/registrere-endre-slette/?pageid=310763).

Velg behandling for hver aktuell type salg og kjøp: norsk avgiftspliktig omsetning, fritak, unntak, salg før registrering, utenlandssalg og kjøp med omvendt avgiftsplikt. Disse kan ikke slås sammen til én generell «0 %»-regel. Fradragsrett og eventuell delvis fradragsrett må vurderes separat fra satsen. Se [avgiftssatser](https://www.skatteetaten.no/satser/merverdiavgift) og [fjernleverbare tjenester kjøpt fra utlandet](https://www.skatteetaten.no/rettskilder/type/handboker/merverdiavgiftshandboken/gjeldende/M-3/M-3-30/M-3-30.3/).

**Delvis levert:** modulen kontrollerer satsdekning og hindrer enkelte feil ved fakturavalidering. Velg bare satsene som gjelder dine varer og tjenester. Kjernens gamle norske satser må ikke brukes ukritisk.

MVA-kontoer velges under Regnskap → Innstillinger. Modulen har oppsetthjelp
for manglende norske satsrader. Det er ingen egen registreringshistorikk
eller avgiftskodekobling å vedlikeholde i modulen. Bevar vedtak og annet
registreringsgrunnlag sammen med regnskapsdokumentasjonen.

**Gjenstår:** komplett norsk MVA-rapportering og samlet faglig kvalifisering
av særtilfeller. En korrekt konto er ikke alene bevis på riktig avgiftsbehandling.

Fra 0.67.0 kan [MVA-oppsettet](vat-rates.md) legge ordinære norske salgskoder
3/31/33 og kjøpskoder 1/11/13 på kjernens satsrader.
Kjernen bevarer kodevalget på fakturalinjen. Fra 0.77.0 kan også brukte oppsett suppleres uten å endre eksisterende rader. Nullsats og utenlandshandel
får ingen automatisk klassifisering. Dette er grunnlag for videre rapportering,
ikke en ferdig MVA-melding.

### Fradrag ved kjøp

Fradragsrett og eventuell andel vurderes ut fra anskaffelsen, bruken og
dokumentasjonen. Se [Skatteetatens håndbok, kapittel 8](https://oppslag.rettskilder.skatteetaten.no/rettskilder2/type/handboker/merverdiavgiftshandboken/gjeldende/MVA2025_M-8).
Modulen beregner ikke en fradragsandel for deg.

For et ordinært kostnadskjøp kan du bruke kjernens kjøpsjournal og, når
nødvendig, et separat manuelt omposteringsbilag. Bevar leverandørfakturaen
med originalbeløp og vedlegg. Om det først er bokført fullt fradrag, flyttes
den ikke-fradragsførbare delen fra inngående MVA til riktig kostnadskonto.
Bilaget skal vise til den opprinnelige fakturaen og ha dokumentert grunnlag.
Før i riktig periode; stengte eller allerede rapporterte perioder krever
vurdering av retting.

Eksempel: en kostnadsfaktura på 100 kroner pluss 25 kroner MVA, totalt 125.
50 prosent er bare en valgt eksempelandel; det er ikke en standardregel.

| Fradrag | Kostnad etter ompostering | Inngående MVA etter ompostering | Separat ompostering etter førstegangsføring med fullt fradrag |
| --- | --- | --- | --- |
| Fullt | 100,00 | 25,00 | Ingen |
| 50 prosent | 112,50 | 12,50 | Debet kostnad 12,50; kredit inngående MVA 12,50 |
| Intet | 125,00 | 0,00 | Debet kostnad 25,00; kredit inngående MVA 25,00 |

Leverandørgjelden er fortsatt 125 i alle tre eksemplene. Velg kostnadskonto
etter anskaffelsen; eksemplet gjelder ikke automatisk eiendeler eller alle
særregler. Ved kreditnota må fortegn og eventuell tidligere fradragsfordeling
følges opp. Arbeidsflyten er prøvd teknisk med blandede 25/15/12-satser,
varer/tjenester og tilhørende kreditnotaer; faglig fradragsrett avgjøres separat.

**NPR er en fransk særordning.** Den skal ikke brukes som et valg for norsk
«ingen fradragsrett». Den påvirker kjernens prisberegning. Teksten er presisert
på norsk og engelsk fra 0.66.0. Ikke slå på den globale innstillingen som
kostnadsfører all kjøps-MVA for å løse fradraget på én faktura.

**Rapportering gjenstår:** kjernens fakturabaserte MVA-uttrekk tar ikke med
separate manuelle omposteringer. Avstem mot hovedboken og bruk en konkret,
faglig avklart rapporteringsrutine; uttrekket er ikke godkjent som komplett
norsk MVA-melding. Se [prøver og begrensninger](coverage-analysis.md).

## 4. Kontoplan, standardkontoer og journaler

I nytt regnskap følger du kontoplanvalg med [ordinært MVA-satsoppsett](vat-rates.md)
og deretter [satskontoene](vat-accounts.md). Brukte oppsett kan suppleres med
manglende kodevalg; eksisterende satser og kontokoblinger bevares.

«Skattekontoer» er en egen liste for skatt-/avgiftsmodulen. Den kan stå urørt
når funksjonen ikke brukes, og er ikke en forutsetning for vanlig fakturering
i ENK uten ansatte. Se [veiledning for skattekontoer](tax-accounts.md).

**Eksplisitt installering:** Norway → Installer norsk kontoplan oppretter og velger den medfølgende planen etter forhåndsvisning og bekreftelse. Oppsett med andre kontoer/historikk erstattes ikke. Planen har 745 kontoer fra en dokumentert, lisensiert Odoo-kilde; dette er ikke en sertifisert kopi av en norsk standardpublikasjon. Se [kontoplaninstallering](norwegian-chart.md).

**Manuelt:** Åpne Regnskap → Innstillinger → Norsk regnskapsoppsett, deretter standardkontoene. Vurder forslagene samlet:

| Kobling | Forslag fra medfølgende plan | Avgrensning |
|---|---|---|
| Kunde | 1500 Kundefordringer | Kunde-/linjekobling kan overstyre |
| Leverandør | 2400 Leverandørgjeld | Leverandør-/linjekobling kan overstyre |
| Tjenestesalg | 3020 | Innenlandsk tjenestesalg med høy sats for MVA-registrert foretak |
| Utgående/inngående MVA | 2700 / 2710 | Høy sats; ikke generell mapping for alle avgiftsbehandlinger |
| Bank | 1920 Bankinnskudd | Velges på faktisk bankkonto; flere bankkonti kan kreve andre kontoer |
| Kjøp, feilkonto og overføring | Ingen automatisk anbefaling | Avhenger av kostnad og transaksjon |

Bruk kjernens [journalregister](journal-setup.md): salg (2), kjøp (3),
bank/kasse (4) og diverse (1). Velg egne koder og navn; de er ikke myndighetskoder.
Kjernen lagrer både standardkontoer og journaler i nytt og brukt regnskap.
Separate utfyllingssider er fjernet i 0.77.0.

Den medfølgende planen får kontoklasse 1–2 for balanse og 3–8 for resultat.
Manglende grupper fylles ved oppdatering; egne valg og historikk bevares.
Kjernens avslutningsoppsett har tilsvarende grupper når feltene var tomme.
Velg passende avslutningsjournal og resultatkonto der før årsavslutning.
Dette gir ikke automatisk resultatdisponering, SAF-T eller skattelevering.
Se [detaljert kontooppsett](accounting-setup.md).

## 5. Bank og betaling

**Manuelt:** Åpne bank-/kasselisten, opprett eller rediger faktisk bankkonto, fyll inn betalingsopplysningene og velg NOK, bokføringskonto og aktiv bankjournal. Kontroller opplysningene på en forhåndsvisning av fakturaen. KID og bankintegrasjon må vurderes og konfigureres særskilt; de følger ikke automatisk med kontoplanen.

Fra 0.77.0 bruker alle [bankkontoens vanlige oppsett](bank-defaults.md).
Flere bankkonti og egne kontonavn er tillatt; det er ingen separat bekreftelsesrunde.

Bruk kjernens bankkontooppsett og hovedbok for konto-/journal-/valutakoblinger og avstemming.

**Automatisk:** sjekkfunksjoner deaktiveres i støttet norsk oppsett. **Gjenstår:** full bank- og reskontroavstemming. Bankgebyr, kort-/formidleroppgjør, delbetaling, refusjon og underbetaling må behandles særskilt. Ikke marker en faktura fullt betalt bare fordi et nettobeløp er mottatt.

## 6. Kunder, leverandører og produkter

**Manuelt:** Registrer korrekt navn, adresse, organisasjons-/avgiftsidentitet der relevant, land, kunde-/leverandørrolle og språk. Et prospekt er en mulig kunde; samme motpart kan være både kunde og leverandør. Velg norsk eller engelsk per kunde og kontroller dokumentets språk ved generering.

Opprett varer og tjenester som den leveransetypen som faktisk brukes. Angi tydelig beskrivelse, NOK-pris, relevant avgiftsbehandling og salgskonto. Kontroller produkt- og motpartskoblinger som kan overstyre standardkontoene. For abonnement: dokumenter avtale-/leveringsperiode og faktureringsrytme; faktureringsdato er ikke alene grunnlag for periodisering.

**Automatisk:** nye salgsdokumenter begrenses til NOK. EUR-kalkulatoren gir bare veiledende pris. Utenlandske leverandørbilag og bankens veksling er ikke dermed løst regnskapsmessig. Se [salgsdokumenter](sales-documents.md).

## 7. Faktura, tilbud og kreditnota

**Nummerserier:** Velg og bekreft F/K/T og kunde-/leverandørnumre med eventuelle startnumre i [oppsettet for nummerserier](numbering.md). Prøv maskinell nummerering før første faktura. Ved overgang må nummerserier og tidligere dokumenter avklares; bruk ikke manuell gjenbruk av numre. Velg betalingsvilkår, forfallsberegning, bankopplysninger og dokumentmodell. Prøv norsk og engelsk PDF med syntetiske data i separat testmiljø.

Kontroller på det faktiske dokumentet: dato og nummer, selgeridentitet og korrekt MVA-merking, kjøperidentitet, konkret leveransebeskrivelse, leveringstid/-sted der relevant, grunnlag/avgift og totalsum i NOK, betalingsfrist og kreditnotareferanse. Ulike avgiftsbehandlinger må kunne skilles. Foretaksform kan kreve ytterligere registertekst. Se [Altinns fakturakrav](https://info.altinn.no/starte-og-drive/regnskap-og-revisjon/regnskap/faktura-salgsdokumentasjon/).

Velg nummerserier i veilederens steg 7 og dokumentmodeller i kjernens faktura- og tilbudsoppsett.

**Delvis levert:** fakturatrigger og PDF-tilpasninger dekker deler av dette. De garanterer ikke alle opplysninger på alle dokumentmodeller. Prøvde modeller er azur for tilbud og sponge for faktura. Fritekst oversettes ikke automatisk. Rett utstedte feil gjennom dokumentert kreditnota-/rettingsflyt, ikke sletting av historikk.

Fra 0.78.0 får norsk AS/ASA registerteksten på standarddokumentene. Ved
systembytte kan kreditnota vise til originalfakturanummer fra tidligere system,
registrert på det vanlige fakturakortet. Bruk intern originalkobling når
fakturaen allerede finnes her. Se [dokumentflyten](sales-documents.md#dokumentflyt-fra-0780).

## 8. Offentlige kunder og utenlandshandel

**E-faktura til næringslivet:** Roadmapen omfatter også de nye kravene til
utgående e-faktura fra 2027 og mottak/digital bokføring fra 2030. Kontroller
gjeldende formatregler og unntak; EHF-behov begrenses ikke til offentlige
kunder. Se KR16 og fristene i [norsk kravregister](norwegian-requirements.md).

**Offentlige kunder:** Avklar EHF-mottaker, bestiller-/ordrereferanse, kontraktskrav, vedlegg og tilgangspunkt før sending. PDF på e-post er ikke EHF. Kvalifisert norsk EHF/Peppol-integrasjon er ikke levert; ekstern løsning må vurderes med sporbarhet til fakturaen. Se [DFØs EHF-veiledning](https://www.anskaffelser.no/verktoy/veiledere/ehf-fakturering).

**Utland:** Avklar leveringsmodell, mottakerland, kundetype og dokumentasjon for både salg og kjøp før avgiftsregel velges. Programvare er ikke én universell avgiftskategori. Ikke bruk norsk innenlandsk standardkonto eller 0 prosent som eneste beslutningsregel. Utenlandske avgiftsplikter, kjøp med omvendt avgiftsplikt, utenlandsk valuta og dokumenterte bankoppgjør krever særskilt kvalifisering. Støttede NOK-salgsdokumenter utvider ikke automatisk modulens avgiftsstøtte.

Fra 0.72.0 støttes eksplisitt eksportkode `0 (52)` på NOK-kundefakturaer og
kreditnotaer til utenlandske kunder, for varer og tjenester. Bruk kjernens
vanlige fakturalinjer, offentlig merknad og vedlegg. Velg eksportkonto etter
varen/tjenesten og vurder fritaksvilkårene før kodevalget. Se [eksportflyten](vat-rates.md#eksportkode-52-i-0720)
og [uttrekkets avgrensning](vat-report.md#eksportkode-52-i-0720).
Fra 0.79.0 velges fullt, delvis eller ingen MVA-fradrag på den vanlige
leverandørfakturalinjen. Det gjelder innenlandske varer/tjenester med 25/15/12
prosent og fjernleverbare utenlandstjenester med 25 prosent beregnet avgift.
Bruk tjenestelinje, omvendt avgiftsplikt og kode 86/87 for sistnevnte.
Fritekst fungerer; produktkortets senere sats brukes ikke. Kreditnota kopierer
valget, og betalingsoppfølgingen beholdes. Se [vanlig kjøpsflyt med eksempler](purchase-vat.md).
Tidligere manuelle 86-/87-rutiner beholdes for gamle bilag. Nye kjøp i dette
omfanget skal ikke kreve et separat fradragsrettingsbilag. Vareimport,
andre beregnede satser og uregistrerte foretaks utenlandskjøp gjenstår.

## 9. Inngående saldoer, perioder og løpende rutiner

**Manuelt:** Ved overgang avstemmes bank, åpne kundefordringer, leverandørgjeld, MVA, eiendeler, gjeld og egenkapital mot gammelt system på samme dato. Bevar kildedokumenter og revisjonsspor. Import av kontoer er ikke import av saldoer eller åpne poster. Oppsettveiledningen skal ikke foreslå syntetiske startbilag i drift.

Bruk kjernens regnskapsperioder for datodekning og periodestatus.

Avklar regnskapsår, bilagsdatoer, periodisering, godkjenning, periodelåsing og retting. Dokumenter rutiner for faktura, kjøp, betaling, gebyr, kreditnota, manuelle bilag og månedlig avstemming. For ENK skilles private bevegelser fra virksomhetens kostnader og eiertransaksjoner vurderes særskilt. Andre foretaksformer krever egne vurderinger.

**Gjenstår:** kvalifisering av hele avslutnings-/låseflyten og alle pliktige norske spesifikasjoner. Bruk kjernens hovedbok og rapporter for avstemming av reskontro, bank og MVA.

## 10. Rapportering, oppbevaring og sikker drift

Avklar hvem som gjør MVA-melding, skattemelding/næringsspesifikasjon, eventuell årsregnskapsinnsending og andre rapporteringer som følger av virksomheten. Direkte innsending er ikke levert. SAF-T er en separat eksportplikt og ingen erstatning for disse meldingene. Fra 0.83.0 brukes [SAF-T-eksport på kjernens eksportside](saft-export.md) for støttet omfang; lav omsetning alene fritar ikke elektronisk regnskap. Se [Skatteetatens SAF-T-veiledning](https://www.skatteetaten.no/bedrift-og-organisasjon/starte-og-drive/rutiner-regnskap-og-kassasystem/saf-t-regnskap/).

Dokumenter oppbevaringsansvar, lagringsland, tilgang, oppbevaringstid og gjenoppretting. Primærdokumentasjon har normalt fem års oppbevaring, med særregler; avtaler kan være del av dokumentasjonsgrunnlaget. Vurder også elektronisk tilgjengelighet og reglene ved utenlandsk lagring. Se [Altinns oppbevaringsveiledning](https://info.altinn.no/starte-og-drive/regnskap-og-revisjon/regnskap/oppbevaring-av-regnskapsmateriale).

**Levert:** vedvarende lagring, backup ved oppdatering og testet restore i syntetisk miljø. **Manuelt:** planlagt løpende backup, separat sikret kopi, tilgangsstyring, overvåking og egen restore-prøve. En oppdateringsbackup på samme server er ikke en full oppbevaringsstrategi. Personvern og eventuelle databehandleravtaler vurderes for valgte tjenester.

## 11. Avslutt gjennomgangen uten å skjule mangler

Registrer for hvert punkt: valg/ikke relevant/må avklares, begrunnelse, ansvarlig, dato og kontrollbevis. Oppsett som er «lagret» må skilles fra oppsett som er «testet». Kontoplan på plass, grønne helsekontroller og fungerende faktura-PDF betyr ikke at norsk regnskapsføring er ferdig kvalifisert.

Før et konkret scenario tas i bruk må hele kjeden prøves med syntetiske data utenfor drift: dokument → bokføring → betaling/kreditnota → avstemming → relevant rapport/eksport. Uavklarte MVA-, EHF-, SAF-T- og historikkbehov er reelle leveransegap, ikke valg som brukeren kan løse ved å krysse av «ferdig».

Videre utvikling av veiviseren skal følge denne rekkefølgen, vise gjeldende verdier, forklare forslag, lenke til relevante innstillinger og bevare eksisterende valg. Den må vise manglende funksjoner og støtte «ikke relevant» med begrunnelse. Automatiske endringer krever forhåndsvisning og bekreftelse; ingen automatisk grønn produksjonsstatus innføres. Videre utvikling følger [leveranseplanen](delivery-plan.md).

Fra 0.80.0 er ordinær bank-/MVA-periode prøvd gjennom kjernen, med
portalinnlevering som valgt leveringsvei. Se [bank, MVA og periodeavslutning](vat-period-close.md)
for betaling, kvittering, oppgjør, retting og avgrensning.
