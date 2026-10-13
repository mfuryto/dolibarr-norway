# Norsk kravregister og produksjonsaksept

Kontrollert 2026-10-13. Registeret supplerer [roadmapen](delivery-plan.md)
med krav som skal vurderes før et norsk bruksområde erklæres støttet.
Det registrerer også betingede krav som ikke gjelder alle installasjoner.
En oppføring her betyr at kravet skal håndteres, ikke at funksjonen er levert.

Lov og forskrift, god bokføringsskikk og produktvalg skilles ved kvalifisering.
Et påbud om et resultat innebærer ikke nødvendigvis krav om en bestemt knapp,
automatisk funksjon eller integrasjon. Manuelle rutiner eller kvalifiserte
eksterne systemer kan inngå når dette er dokumentert og regelverket tillater det.

## Felles grunnlag

| ID | Kravområde | Tilpasning / bevis før aksept | Roadmap / kilder |
| --- | --- | --- | --- |
| KR01 | Foretaksidentitet og plikter | Kontroller selger/kjøper, organisasjonsnummer, registrering, adresser og opplysninger som avhenger av foretaksform. Avklar bokføringsplikt og eventuell årsregnskapsplikt per installasjon. | A2/F2; S7/S20/S23 |
| KR02 | Utstedelse og fakturanummer | Kvalifiser maskinell nummerering, nummerhull, samtidighet, dokumentdato og utstedelsestidspunkt. Avklar løpende leveranser, abonnement og eventuell forskuddsfakturering. Nummerserieoppsett gir ikke adgang til fritt å endre utstedte numre. | A2; S7/S20 |
| KR03 | Fakturainnhold og kreditnota | Leveringsdato/-sted, beskrivelse, mottaker, vederlag, betalingsfrist, NOK-MVA og spesifikasjon av ulike avgiftsbehandlinger. Kreditnota må ha nødvendig originalreferanse. Bevar utstedt dokument og historiske opplysninger. | A2/E; S7/S15 |
| KR04 | Inngående bilag og utlegg | Bevar faktura/kvittering og vedlegg, korrekt kjøper, formål og dokumentasjon for fradrag. Kontroller dubletter og kobling til betaling/kontering. Utlegg og reisekostnader trenger eget grunnlag der det er relevant. | A2/C/G; S20/S22 |
| KR05 | Fullstendig bokføring og kontrollspor | Entydig bilagsreferanse, dokument-/bokførings-/avgiftsdato, motpost og spor begge veier. Dekk salg, kjøp, bank, manuelle bilag og importer. Retting skjer med dokumentert spor og korrekt motføring. | A2/B1/C/G; S15/S20 |
| KR06 | Pliktige spesifikasjoner | Kartlegg bokførings-, konto-, kunde-/leverandør- og avgiftsspesifikasjoner, samt eier-/uttaks- og lønnsopplysninger når relevante. Prøv fullstendighet, periodeuttrekk og avstemming mot hovedboken; saldobalanse alene dekker ikke alle behov. | B1/C/F/G; S15/S20 |
| KR07 | Ajourhold og periodegrenser | Definer rapporteringsperioder og ajourholdsfrister. Test dokument- og bokføringsdato, etterposteringer, sperrede perioder og sporbar gjenåpning/retting. Én generell kalenderfrist passer ikke alle foretak. | B1/C2/G; S20 |
| KR08 | MVA-status over tid | Dokumenter registrering, avregistrering og eventuelle nye perioder. Avklar tilbakegående avgiftsoppgjør og behandling ved registreringsgrensen. Dagens registeroppslag eller én dato kvalifiserer ikke historikken. | A2/B1; S8/S17/S22 |
| KR09 | Avgiftsbehandling og fradrag | Skill ordinær sats, fritak, unntak og uregistrert omsetning. Prøv blandet virksomhet, delvis/ingen fradragsrett, privat bruk og kostnadstyper med begrensninger. 25/15/12 er ordinære satser, ikke komplett støtte for alle særregler. | A2/B1; S8/S17/S22 |
| KR10 | Uttak, tap og justering | Kvalifiser avgiftsmessige uttak, tap på fordringer, senere innbetaling og kapitalvarejustering når relevante. Forfalt faktura gir ikke alene grunnlag for tilbakeføring av MVA. Historiske grunnlag må bevares. | A2/B1/C2; S22 |
| KR11 | Betaling og oppgjør | Dokumenter delbetalinger, refusjon, rabatter, gebyr og nettooppgjør fra kort/formidler. Vurder bankbetaling som fradragsvilkår. Avstem brutto krav, betaling og kostnader hver for seg. KID/bankintegrasjon er produktvalg, ikke generelle lovkrav. | C; S15/S22 |
| KR12 | Balanse og årsavslutning | Dokumenter vesentlige balanseposter, lager/varetelling der aktuelt, anleggsmidler, periodisering, regnskapsmessige/skattemessige avskrivninger og resultatdisponering. Kontroller inngående saldoer og unngå dobbelt årsavslutningsbilag. | C2/F2; S15/S21/S23 |
| KR13 | MVA-melding og retting | Avstemt grunnlag per rapporteringsperiode, sporbar rapportversjon, autorisert og bekreftet innsending, kvittering/status og håndtering av korrigert melding. API-avbrudd må ikke gi ukjent eller dobbelt innsendt resultat. | B1/B2; S8/S22 og roadmapens innsendingskilder |
| KR14 | Næringsspesifikasjon og årsregnskap | Kontroller årsversjon, foretaksform, skattemessige forskjeller og totalsummer. Årsregnskap med nødvendige oppstillinger/noter og eventuell revisjon/årsberetning er en separat plikt for regnskapspliktige; næringsspesifikasjonen erstatter det ikke. | C2/F2; S23 |
| KR15 | SAF-T Financial | Bevar kontonumre og originaldata; kvalifiser klassifisering, avgiftskoder, motparter, saldoer og fullstendig eksport. Mål 1.40 for 2027. Prosjektaksept av kontoplanen erstatter ikke eksporttest. | F; S5/S6/S12 |
| KR16 | Elektronisk fakturering | Kvalifiser utgående faktura/kreditnota til offentlige og øvrige norske virksomheter. Lovendringen har frist 2027 for sending; mottaksplikt og digital bokføring følger 2030-reglene. Format, unntak og mottakerforutsetninger må avklares. | E; S18/S19 |
| KR17 | Dokumentbevaring og tilgjengelighet | Klassifiser dokumenter, behold originaler og nødvendige vedlegg, sikre lesbarhet og elektronisk tilgjengelighet gjennom oppbevaringstiden. Vurder lengre særfrister. Sletting av konto eller abonnement må ikke fjerne pliktig materiale. | G; S15/S16 |
| KR18 | Lagringssted og driftsavvikling | Avklar land for database, dokumenter og backup, eventuell melding/tillatelse og myndighetstilgang. Test eksport/lesetilgang ved bytte av leverandør, driftsstans og avslutning. Selvhosting alene bekrefter ikke riktig lagringssted. | G; S16 |
| KR19 | Sikring og tilgang | Dokumenter risikovurdering, rollegrenser, endringsspor, backup og gjenoppretting av komplett regnskap. Prøv uautorisert endring og sletting, samt bevaring ved modul-/kjerneoppdatering. | G; S15 |
| KR20 | Personvern | Behandlingsgrunnlag, dataminimering, tilgang, innsyn, leverandøransvar og dokumenterte lagrings-/sletterutiner. Samordne personvern med lovpålagt oppbevaring; kundesletting må ikke ukritisk slette bilag. | G; S16/S24 |

## Betingede bruksområder

| ID | Når det gjelder | Avklaring / kvalifisering | Roadmap / kilder |
| --- | --- | --- | --- |
| KR21 | Utenlandske kjøp/salg og oppgjør | Skille vareimport med tolldokumentasjon fra tjenesteimport og omvendt avgiftsplikt. Avklar eksportbevis, kundetype/land, NOK-grunnlag og eventuelle utenlandske plikter. Utenlandske kjøpsbilag og bankoppgjør kan kreve dokumentert omregning, kursdifferanse og gebyr selv om salgsfakturaen er i NOK. Automatisk livekurs er et produktvalg. | D/A2/B1/C; S17/S22 |
| KR22 | Kontantsalg, butikk og nettbetaling | Avklar om salget omfattes av krav til produkterklært kassasystem, oppgjør og kontantdokumentasjon eller andre regler. Kort/Vipps betyr ikke automatisk fritak. Vanlig Dolibarr-POS eller en bankintegrasjon skal ikke fremstilles som norsk kvalifisert kassasystem. | Eget betinget omfang; S26 |
| KR23 | Ansatte, lønn og oppgavepliktige ytelser | Avklar lønnsdokumentasjon, a-melding, arbeidsgiveravgift, trekk, feriepenger og andre arbeidsgiverplikter. Kvalifiser reglene fra 2026; skattetrekkskonto skal ikke anbefales som standard ny løsning. Integrasjon med kvalifisert lønnssystem er mulig. | Eget betinget omfang; S25/S20 |
| KR24 | Foretaksform, eiere og særbransjer | Skill ENK-uttak fra AS-lønn/utbytte/lån. Avklar eventuell aksjonær-/annen særskilt rapportering. Bransjer kan kreve prosjektregnskap, timeregistrering, personallister, særavgifter eller særlige MVA-regler. Disse scenarioene får egne krav og kilder før støtte loves. | C2/F2 og eget betinget omfang; S20/S23/S26 |
| KR25 | Purring, renter og inkasso | Hvis funksjonen brukes: kvalifiser frister, kundetype, datert rente/gebyrgrunnlag, omtvistede krav og dokumentert utsending. Ikke hardkod en sats for alle år eller bland tillegg inn i MVA-grunnlaget uten hjemmel. Automatisert purring er et produktvalg. | C og eget betinget omfang; S27 |
| KR26 | Privatkunder og netthandel | Før et forbrukerscenario støttes, avklar prisopplysninger, avtale-/bestillingsbekreftelse, angrerett, reklamasjon, oppsigelse av abonnement og refusjon. Digitale ytelser og vareleveranser kan ha ulike vilkår. Kreditering og betaling må bevare dokumentasjon ved retur/angring. | Eget betinget omfang; S28 |

## Frister og prioritering

- **2027:** SAF-T 1.40 og de nye kravene til utgående e-faktura må ha egne
  kvalifiserte leveranser. E-fakturering begrenses ikke til offentlige kunder.
- **2030:** planlegg mottak/behandling av e-faktura og digital bokføring etter
  overgangsreglene. Løsning og forskriftskrav kontrolleres før implementering.
- **Løpende:** skattemeldings-/næringsspesifikasjonsversjoner, avgiftsregler,
  satser, renter og eksterne API-er må gjennomgås ved relevante endringer.

E-fakturaformat er ikke automatisk det samme som en bestemt transportavtale.
Valgt format og tilgangspunkt må møte de gjeldende kravene. Planens E-spor
avklares parallelt med A2; det venter ikke på at alle regnskapsrapporter er ferdige.

## Hvordan registeret brukes

For hvert støttet scenario skal akseptmatrisen angi relevante KR-ID-er,
aktuell rettskilde/versjon, hva kjernen dekker, norsk tilpasning eller ekstern
rutine, testbevis og gjenværende avvik. «Ikke relevant» krever begrunnelse.
«Krever ekstern løsning» må ha en konkret arbeidsflyt som kan gjennomføres.
Betingede områder blir ikke automatisk implementert eller godkjent fordi de
står i registeret.

Kilder S1–S28 står i [kilderegisteret](sources.md). Registeret er en strukturert
kravoversikt, ikke dokumentasjon på uttømmende juridisk gjennomgang av alle
norske bransjer. Lovdata var ikke direkte tilgjengelig ved kildekontrollen;
paragrafer og detaljvilkår må verifiseres før den aktuelle leveransen godkjennes.
