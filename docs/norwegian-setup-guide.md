# Samlet veiledning for norsk oppsett

Kontrollert 2026-10-11 mot modul 0.25.0 og støttet Dolibarr 24.0.1. Start her etter [teknisk installasjon](vps-installation.md). Åpne Norway-moduloppsettet → Norsk oppsettsveiviser for elleve veiledningssteg inne i Dolibarr. Veiviseren viser én oppgaverekkefølge per steg med forklarte knapper til innstillingssidene. Knapper åpner ny fane; lagring skjer på siden som åpnes. Dagens innstillinger og tilleggskontroller har egne sammenfoldede seksjoner. Foretaksvalg lagres på innstillingssidene. Fra 0.24.0 kan administrator lagre egen arbeidsstatus per steg; den er ikke en regnskapsfaglig godkjenning. Se [veiviserens omfang](setup-wizard.md).

Du skal ikke måtte kjenne Dolibarrs interne konstanter eller velge alle de 745 kontoene. Følg rekkefølgen nedenfor. «Automatisk» betyr utført av installasjons-/oppdateringsskriptet; «manuelt» betyr at administratoren må lagre eller kontrollere valget. «Gjenstår» betyr at dokumentasjonen ikke kan erstattes med en innstilling i dagens modul.

Dette dokumentet samler norske oppsettforhold, men erklærer ikke komplett lovdekning. Omfanget er ett norsk foretak, NOK-salgsdokumenter og norsk/engelsk. ENK og programvaresalg er første utviklingsprofil. Andre foretaksformer, ansatte, kontantsalg og særregler må vurderes særskilt. Full norsk MVA-rapport, SAF-T, kvalifisert EHF og produksjonsaksept gjenstår.

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

Åpne Norway → foretaks-/moduloppsett og gjennomgå kontrollresultatene. Registeroppslaget sammenligner dagens opplysninger med Brønnøysundregistrene, men endrer ikke MVA-innstillinger. Se [registerkontroll](registry.md).

Opprett personlige brukere og avgrens rettigheter for administrasjon, fakturavalidering, bank, bokføring og eksport. Bruk HTTPS og privat oppbevaring av passord og sikkerhetskopier. Regnskapsfører kan gis nødvendige rettigheter uten deling av administratorkonto.

**Automatisk:** driftsoppsettet aktiverer grunnmoduler og setter støttet NOK-/språkomfang. Det registrerer ikke foretaket hos myndighetene og fyller ikke inn foretakets identitet.

## 3. MVA-status og avgiftsbehandling

**Manuelt:** Bekreft faktisk registrering og virkningsdato fra vedtak/registreringsgrunnlag før fakturering med MVA. Den alminnelige registreringsgrensen er omsetning omfattet av reglene over 50 000 kroner i en tolvmånedersperiode; forhåndsregistrering har egne vilkår. Oppstart av fakturering betyr ikke automatisk registrering. Se [Skatteetatens registreringsveiledning](https://www.skatteetaten.no/bedrift-og-organisasjon/avgifter/mva/registrere-endre-slette/?pageid=310763).

Velg behandling for hver aktuell type salg og kjøp: norsk avgiftspliktig omsetning, fritak, unntak, salg før registrering, utenlandssalg og kjøp med omvendt avgiftsplikt. Disse kan ikke slås sammen til én generell «0 %»-regel. Fradragsrett og eventuell delvis fradragsrett må vurderes separat fra satsen. Se [avgiftssatser](https://www.skatteetaten.no/satser/merverdiavgift) og [fjernleverbare tjenester kjøpt fra utlandet](https://www.skatteetaten.no/rettskilder/type/handboker/merverdiavgiftshandboken/gjeldende/M-3/M-3-30/M-3-30.3/).

**Delvis levert:** modulen kontrollerer satsdekning og hindrer enkelte feil ved fakturavalidering. En kontroll som etterlyser 15 eller 12 prosent betyr ikke at programvaresalg skal bruke disse satsene. Kjernens gamle norske satser må ikke brukes ukritisk.

Fra 0.20.0 viser [MVA-kontokontrollen](vat-setup.md) aktive norske satsrader, egne satskontoer og standardkontoer som reserve. Den kontrollerer kontoenes tilgjengelighet, ikke avgiftsbehandling eller rapporteringskoder.

Fra 0.21.0 kan [én MVA-virkningsdato lagres etter eksplisitt bekreftelse](vat-registration.md). Den endrer ingen MVA-status eller bilag. Flere perioder og retting av registreringsgrunnlaget er ikke implementert.

**Gjenstår:** historiske registreringsperioder, komplett avgiftskode-/kontomapping, fradragsbehandling og sporbar norsk MVA-rapport. Ikke endre gamle bilag ved å omskrive dagens satser. En grønn satskontroll er ikke aksept av MVA-oppsettet.

## 4. Kontoplan, standardkontoer og journaler

**Eksplisitt installering:** Norway → Installer norsk kontoplan oppretter og velger den medfølgende planen etter forhåndsvisning og bekreftelse. Oppsett med andre kontoer/historikk erstattes ikke. Planen har 745 kontoer fra en dokumentert, lisensiert Odoo-kilde; dette er ikke en sertifisert kopi av en norsk standardpublikasjon. Se [kontoplaninstallering](norwegian-chart.md).

**Manuelt:** Åpne Norway → Kontroll av kontoplan og bokføringsoppsett, deretter standardkontoene via lenken. Vurder forslagene samlet:

| Kobling | Forslag fra medfølgende plan | Avgrensning |
|---|---|---|
| Kunde | 1500 Kundefordringer | Kunde-/linjekobling kan overstyre |
| Leverandør | 2400 Leverandørgjeld | Leverandør-/linjekobling kan overstyre |
| Tjenestesalg | 3020 | Innenlandsk tjenestesalg med høy sats for MVA-registrert foretak |
| Utgående/inngående MVA | 2700 / 2710 | Høy sats; ikke generell mapping for alle avgiftsbehandlinger |
| Bank | 1920 Bankinnskudd | Velges på faktisk bankkonto; flere bankkonti kan kreve andre kontoer |
| Kjøp, feilkonto og overføring | Ingen automatisk anbefaling | Avhenger av kostnad og transaksjon |

Fra 0.14.0 kan [manglende journaltyper opprettes etter bekreftelse](journal-setup.md), med bevaring av eksisterende journaler og bilag. Kontroller aktive journaler med kode og riktig type: salg (2), kjøp (3), bank/kasse (4), diverse (1). Kodene kan beholdes hvis oppsettet er egnet; de er ikke myndighetenes avgiftskoder. Kontoens eksistens bekrefter ikke riktig klassifisering. Kontokategorier og SAF-T-/næringsmapping settes ikke av kontoplanimporten.

**Levert:** kontroll og kontoforslag, samt separat [bekreftet utfylling](default-accounts.md) av tom kunde-/leverandørstandard i et nytt bokføringsoppsett. Eksisterende valg og historikk bevares. **Gjenstår:** lagring av øvrige grunnkoblinger og komplett rapporteringsmapping. Oppdatering fyller ingen kontoforslag automatisk. Se [detaljert kontooppsett](accounting-setup.md).

## 5. Bank og betaling

**Manuelt:** Åpne bank-/kasselisten, opprett eller rediger faktisk bankkonto, fyll inn betalingsopplysningene og velg NOK, bokføringskonto og aktiv bankjournal. Kontroller opplysningene på en forhåndsvisning av fakturaen. KID og bankintegrasjon må vurderes og konfigureres særskilt; de følger ikke automatisk med kontoplanen.

Fra 0.17.0 finnes [bekreftet utfylling av manglende bankkoblinger](bank-defaults.md) for én ny ordinær NOK-bankkonto uten historikk. Eksisterende koblinger beholdes; øvrige oppsett gjennomgås manuelt.

Bankdelen i Norway-kontrollen viser åpne konti og konto-/journal-/valutakoblinger. Feil journaltype, manglende kobling og delte bokføringskontoer fremgår. Lukkede konti og historiske bevegelser undersøkes ikke.

**Automatisk:** sjekkfunksjoner deaktiveres i støttet norsk oppsett. **Gjenstår:** full bank- og reskontroavstemming. Bankgebyr, kort-/formidleroppgjør, delbetaling, refusjon og underbetaling må behandles særskilt. Ikke marker en faktura fullt betalt bare fordi et nettobeløp er mottatt.

## 6. Kunder, leverandører og produkter

**Manuelt:** Registrer korrekt navn, adresse, organisasjons-/avgiftsidentitet der relevant, land, kunde-/leverandørrolle og språk. Et prospekt er en mulig kunde; samme motpart kan være både kunde og leverandør. Velg norsk eller engelsk per kunde og kontroller dokumentets språk ved generering.

Opprett programvaren som den leveransetypen som faktisk brukes. Angi tydelig beskrivelse, NOK-pris, relevant avgiftsbehandling og salgskonto. Kontroller produkt- og motpartskoblinger som kan overstyre standardkontoene. For abonnement: dokumenter avtale-/leveringsperiode og faktureringsrytme; faktureringsdato er ikke alene grunnlag for periodisering.

**Automatisk:** nye salgsdokumenter begrenses til NOK. EUR-kalkulatoren gir bare veiledende pris. Utenlandske leverandørbilag og bankens veksling er ikke dermed løst regnskapsmessig. Se [salgsdokumenter](sales-documents.md).

## 7. Faktura, tilbud og kreditnota

**Manuelt:** Velg og prøv maskinell fakturanummerering før første faktura. Ved overgang må nummerserier og tidligere dokumenter avklares; bruk ikke manuell gjenbruk av numre. Velg betalingsvilkår, forfallsberegning, bankopplysninger og dokumentmodell. Prøv norsk og engelsk PDF med syntetiske data i separat testmiljø.

Kontroller på det faktiske dokumentet: dato og nummer, selgeridentitet og korrekt MVA-merking, kjøperidentitet, konkret leveransebeskrivelse, leveringstid/-sted der relevant, grunnlag/avgift og totalsum i NOK, betalingsfrist og kreditnotareferanse. Ulike avgiftsbehandlinger må kunne skilles. Foretaksform kan kreve ytterligere registertekst. Se [Altinns fakturakrav](https://info.altinn.no/starte-og-drive/regnskap-og-revisjon/regnskap/faktura-salgsdokumentasjon/).

Fra 0.18.0 samler [kontroll av dokumentoppsett](document-setup.md) gjeldende nummereringsmoduler, nummermasker, effektive PDF-valg og språk uten å reservere numre eller endre dokumenter.

**Delvis levert:** fakturatrigger og PDF-tilpasninger dekker deler av dette. De garanterer ikke alle opplysninger på alle dokumentmodeller. Prøvde modeller er azur for tilbud og sponge for faktura. Fritekst oversettes ikke automatisk. Rett utstedte feil gjennom dokumentert kreditnota-/rettingsflyt, ikke sletting av historikk.

## 8. Offentlige kunder og utenlandshandel

**Offentlige kunder:** Avklar EHF-mottaker, bestiller-/ordrereferanse, kontraktskrav, vedlegg og tilgangspunkt før sending. PDF på e-post er ikke EHF. Kvalifisert norsk EHF/Peppol-integrasjon er ikke levert; ekstern løsning må vurderes med sporbarhet til fakturaen. Se [DFØs EHF-veiledning](https://www.anskaffelser.no/verktoy/veiledere/ehf-fakturering).

**Utland:** Avklar leveringsmodell, mottakerland, kundetype og dokumentasjon for både salg og kjøp før avgiftsregel velges. Programvare er ikke én universell avgiftskategori. Ikke bruk norsk innenlandsk standardkonto eller 0 prosent som eneste beslutningsregel. Utenlandske avgiftsplikter, kjøp med omvendt avgiftsplikt, utenlandsk valuta og dokumenterte bankoppgjør krever særskilt kvalifisering. Støttede NOK-salgsdokumenter utvider ikke automatisk modulens avgiftsstøtte.

## 9. Inngående saldoer, perioder og løpende rutiner

**Manuelt:** Ved overgang avstemmes bank, åpne kundefordringer, leverandørgjeld, MVA, eiendeler, gjeld og egenkapital mot gammelt system på samme dato. Bevar kildedokumenter og revisjonsspor. Import av kontoer er ikke import av saldoer eller åpne poster. Oppsettveiledningen skal ikke foreslå syntetiske startbilag i drift.

Fra 0.19.0 gir [kontroll av regnskapsperioder](period-setup.md) oversikt over datodekning, overlapp og eksisterende periodestatus. Den åpner/lukker ingen perioder.

Avklar regnskapsår, bilagsdatoer, periodisering, godkjenning, periodelåsing og retting. Dokumenter rutiner for faktura, kjøp, betaling, gebyr, kreditnota, manuelle bilag og månedlig avstemming. For ENK skilles private bevegelser fra virksomhetens kostnader og eiertransaksjoner vurderes særskilt. Andre foretaksformer krever egne vurderinger.

**Gjenstår:** kvalifisering av hele avslutnings-/låseflyten og alle pliktige norske spesifikasjoner. Norway-bilagskontrollen kontrollerer debet/kredit og enkelte dokumentkoblinger; den erstatter ikke reskontro-, bank- eller MVA-avstemming.

## 10. Rapportering, oppbevaring og sikker drift

Avklar hvem som gjør MVA-melding, skattemelding/næringsspesifikasjon, eventuell årsregnskapsinnsending og andre rapporteringer som følger av virksomheten. Direkte innsending er ikke levert. SAF-T er en separat eksportplikt og ingen erstatning for disse meldingene. Norsk SAF-T-eksport er ikke implementert; lav omsetning alene fritar ikke elektronisk regnskap. Se [Skatteetatens SAF-T-veiledning](https://www.skatteetaten.no/bedrift-og-organisasjon/starte-og-drive/rutiner-regnskap-og-kassasystem/saf-t-regnskap/).

Dokumenter oppbevaringsansvar, lagringsland, tilgang, oppbevaringstid og gjenoppretting. Primærdokumentasjon har normalt fem års oppbevaring, med særregler; avtaler kan være del av dokumentasjonsgrunnlaget. Vurder også elektronisk tilgjengelighet og reglene ved utenlandsk lagring. Se [Altinns oppbevaringsveiledning](https://info.altinn.no/starte-og-drive/regnskap-og-revisjon/regnskap/oppbevaring-av-regnskapsmateriale).

**Levert:** vedvarende lagring, backup ved oppdatering og testet restore i syntetisk miljø. **Manuelt:** planlagt løpende backup, separat sikret kopi, tilgangsstyring, overvåking og egen restore-prøve. En oppdateringsbackup på samme server er ikke en full oppbevaringsstrategi. Personvern og eventuelle databehandleravtaler vurderes for valgte tjenester.

## 11. Avslutt gjennomgangen uten å skjule mangler

Registrer for hvert punkt: valg/ikke relevant/må avklares, begrunnelse, ansvarlig, dato og kontrollbevis. Oppsett som er «lagret» må skilles fra oppsett som er «testet». Kontoplan på plass, grønne helsekontroller og fungerende faktura-PDF betyr ikke at norsk regnskapsføring er ferdig kvalifisert.

Før et konkret scenario tas i bruk må hele kjeden prøves med syntetiske data utenfor drift: dokument → bokføring → betaling/kreditnota → avstemming → relevant rapport/eksport. Uavklarte MVA-, EHF-, SAF-T- og historikkbehov er reelle leveransegap, ikke valg som brukeren kan løse ved å krysse av «ferdig».

Videre utvikling av veiviseren skal følge denne rekkefølgen, vise gjeldende verdier, forklare forslag, lenke til relevante innstillinger og bevare eksisterende valg. Den må vise manglende funksjoner og støtte «ikke relevant» med begrunnelse. Automatiske endringer krever forhåndsvisning og bekreftelse; ingen automatisk grønn produksjonsstatus innføres. Videre utvikling følger [leveranseplanen](delivery-plan.md).
