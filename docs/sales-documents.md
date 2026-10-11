# Tilbud og faktura i NOK på norsk og engelsk

Driftsoppsettet aktiverer kunder, tilbud, faktura og dokumentvalg for språk
(`MAIN_MULTILANGS`). NOK er eneste støttede valuta for nye salgsdokumenter.
MultiCurrency deaktiveres; eksisterende kurstabeller og historiske dokumenter
slettes eller konverteres ikke. Et foretak med annen basisvaluta og økonomiske registreringer stoppes med
feilmelding, slik at oppdateringen ikke omtolker beløp i bakgrunnen. Eldre
grunnoppsett lagret NOK i informasjonsfeltet `MAIN_INFO_SOCIETE_DEVISE`, mens
kjernen leser `MAIN_MONNAIE`. Dette rettes automatisk kun ved tomme økonomitabeller.
Ingen eksterne lisenssystemer kontaktes eller kreves.

## Norsk og engelsk

Velg norsk bokmål (`nb_NO`) eller engelsk (`en_US`) som kundens standardspråk.
Ved generering av tilbuds- eller faktura-PDF kan dokumentets språk velges
separat. Dette endrer ikke systemets grensesnittspråk. Prøvde PDF-modeller er
**azur** for tilbud og **sponge** for faktura.

Dokumentetiketter oversettes av Dolibarr. Produkttekster, leveringsbeskrivelse
og annen fritekst må skrives på ønsket språk eller ha egne produktoversettelser.
Endring av kundens språk regenererer ikke historiske PDF-er automatisk.

Samlemenyen bruker «Kunder og leverandører». Kundelister heter fortsatt «Kunder»,
og leverandørlister «Leverandører». Tilpasningen bruker kjernens oversettingstabell
for norsk bokmål i aktivt foretak; kjernens filer og engelske etiketter bevares.

## Veiledende EUR-pris

Åpne norsk modul → Veiledende EUR-pris. Oppgi NOK-prisen og velg «Hent kurs og
beregn». Kalkulatoren henter siste publiserte EUR/NOK-referansekurs fra
[Norges Banks åpne API](https://www.norges-bank.no/tema/Statistikk/apne-data/).
Den viser beløp, kurs og kursdato. Helger håndteres med siste publiserte kurs;
kurser eldre enn syv dager avvises. Ingen kunde- eller fakturadata sendes ut.
Kun valuta og datointervall sendes til Norges Bank. Ingen API-nøkkel kreves.

Bruk eksempelvis «Ca. EUR 100 – faktureres i NOK. Kursdato: …» i prisinformasjon.
NOK-prisen er bindende. EUR er kun et anslag; bankens vekslingskurs og gebyrer
kan avvike. Bruk samme MVA-grunnlag i NOK- og EUR-prisvisningen. Kalkulatoren er
et separat hjelpemiddel, ikke en automatisk endring av produktkatalog eller PDF.
Den lagrer ingen kurser i regnskapet. Ved nettverksfeil eller ugyldige kurser
vises en melding uten EUR-beløp; ingen fiktiv eller skjult standardkurs brukes.

Kunden betaler NOK-kravet. Bank/betalingsleverandør håndterer eventuell veksling.
Gebyrer og underbetaling skal avstemmes separat, ikke skjules som betalt faktura.
Valutaendring via standard salgs-API avvises av modulens trigger ved opprettelse,
endring og validering. Historiske dokumenter bevares, men redigering/validering
av gamle salgsdokumenter i annen valuta krever en separat, vurdert migreringsflyt.

## Ingen sjekker

«Ventende sjekkinnskudd» er fysiske banksjekker, ikke utestående fakturaer.
Modulen fjerner sjekkinnskuddsraden fra Bankkonto-gruppen på forsiden via
kjernens dashboard-hook, skjuler sjekkoversikt og menyer, sperrer direkte tilgang til
sjekkinnskuddssidene og deaktiverer CHQ i aktivt foretaks betalingsordbok.
Nye kunde-/leverandørbetalinger og salgsdokumenter med CHQ avvises gjennom
kjernens triggere, også ved direkte API-bruk. Historiske betalingsrader bevares.
Vanlige bankoverføringer, utestående betalinger og fordringer kan fortsatt brukes.

## Bilagskontroll

Åpne norsk modul → Bilagskontroll. Velg en periode på høyst 366 dager.
Kontrollen leser hovedboken for aktivt foretak og viser debet, kredit,
differanse og kobling til kundefaktura-ID/-referanse i samme foretak.
Den skriver ingen posteringer. Manuelle bilag og andre kildetyper merkes for
separat gjennomgang; aggregerte poster vises eksplisitt. Ufullstendige lister
varsles. Dette er ikke avstemming av fordringer, MVA-rapport eller SAF-T.

Fordringer er beløp kundene skylder foretaket. Avstemming av reskontro mot
hovedbok og betalinger er et eget videre arbeidsområde. «Kontroll» betyr her
validering av regnskapsdata. Full norsk kontoplan, fakturakrav, MVA, EHF og SAF-T
må fortsatt kvalifiseres før norsk produksjonsregnskap.

## Fakturakontroll

Norsk modul → Fakturakontroll er tilgjengelig for administrator med lesetilgang
til fakturaer. Velg blant de 20 sist opprettede fakturaene, eller oppgi
faktura-ID fra `facid` i fakturalenken. Kontrollen leser kun
aktivt foretak og viser om selgers navn, adresse og organisasjonsnummer med
korrekt kontrollsiffer, kundeidentitet, fakturadato, betalingsfrist, NOK og
linjebeskrivelser er utfylt. Kontrollsiffer bekrefter ikke registrering i et register.
Kundens navn og adresse eller organisasjonsnummer godtas også for utenlandske kunder.
En kreditnotas kildefaktura må finnes, være validert, tilhøre samme kunde/foretak
og ikke selv være en kreditnota. Manglende betalingsfrist på kreditnota merkes
for gjennomgang.

Kontrollen endrer ingen data, genererer ingen PDF og stopper ikke utsending.
Den leser dagens foretaks-/kundedata, som kan avvike fra historiske PDF-er.
«Utfylt» betyr bare at feltet har innhold; det er ingen fullstendighetsgodkjenning.
Maskinell nummerering og hull i nummerserien, leveringstidspunkt/-sted,
MVA-registrering, riktig avgiftsbehandling og MVA-merking, beløp og de faktiske
norske/engelske PDF-ene må gjennomgås separat. Nåværende nummereringsoppsett
og historiske fakturanumre endres ikke. Standard integrasjonstest bruker nå
kjernens automatiske nummerering uten påtvungne referanser og kontrollerer to
fortløpende salgsfakturaer samt unike faktura-/kreditnotareferanser. Dette er
ikke en full nummerserieanalyse eller en sperre mot manuell nummerering via API.

Kravgrunnlag kontrollert 2026-10-11:
[Altinn: Faktura (salgsdokumentasjon)](https://info.altinn.no/starte-og-drive/regnskap-og-revisjon/regnskap/faktura-salgsdokumentasjon/).
En PDF er fortsatt ikke EHF. EHF/Peppol og full norsk MVA-behandling gjenstår.

## Organisasjonsnummer og MVA i PDF (0.3.1)

Nye tilbud, fakturaer og kreditnotaer får en egen organisasjonsnummerlinje
øverst i avsenderadressen. Med norsk foretak, korrekt kontrollsiffer og MVA
aktivert (`tva_assuj=1`) vises `Org.nr.: 123 456 785 MVA` på norsk og
`Organisation no.: 123 456 785 MVA` på engelsk. Nummeret er et kunstig eksempel.
Uten aktivert MVA vises nummeret uten suffikset i modulens linje. Ugyldig eller
manglende nummer kompletteres ikke med oppdiktet innhold; foretaksoppsettet
viser allerede avviket. Avsenderfeltene i databasen endres ikke.

Kontroller foretaksopplysningene under Dolibarr → Oppsett → Foretak/organisasjon:
navn, adresse, land Norge, organisasjonsnummer og faktisk MVA-status. Norsk
moduloppsett viser nå om MVA er aktivert i Dolibarr. Modulen verken registrerer
foretaket eller slår opp MVA-status i offentlige registre, og aktiverer ikke MVA
ved oppdatering. En intern MVA-innstilling er ikke bevis for registrering.
[Altinn om MVA](https://info.altinn.no/starte-og-drive/skatt-og-avgift/avgift/merverdiavgift)
beskriver når MVA-merking skal brukes.

Tilpasningen bruker standard PDF-hook og bevarer kjernens adresse og bunntekst.
Den er prøvd med `sponge` (faktura/kreditnota) og `azur` (tilbud), på norsk og
engelsk, med MVA aktivert/deaktivert. Kundeadresser og andre foretaks dokumenter
berøres ikke. Eksisterende PDF-er regenereres ikke. Ved manuell regenerering
brukes dagens foretaksstatus; ikke regenerer historiske dokumenter ukritisk.
Andre manuelt utfylte felt, som MVA-nummer eller bunntekst, kan fremdeles vise
MVA og må holdes korrekte av administrator. Avgiftssatser, avgiftsbehandling,
MVA-rapport, registreringsdato og overgangshistorikk er separate arbeidsområder.

## Minstekontroll ved fakturavalidering (0.4.0)

Når norsk modul er aktiv, avviser standard `BILL_VALIDATE`-trigger fakturaer med
manglende/ugyldig selgernavn, norsk land, organisasjonskontrollsiffer eller
adresse, kundeidentitet, fakturadato, betalingsfrist, NOK eller linjebeskrivelse.
Produktets etikett kan brukes når en linje ikke har egen beskrivelse.
Kreditnota trenger en validert kildefaktura i samme foretak og for samme kunde;
betalingsfrist på kreditnota krever separat gjennomgang hvis den mangler.

En ordinær faktura med MVA-beløp eller ikke-null MVA-sats avvises når MVA er
deaktivert i foretaksoppsettet. Dette aktiverer ikke MVA, velger ingen satser
og bekrefter ikke registrering. Kreditnota er unntatt denne statuskontrollen
fordi den kan rette MVA fra en tidligere registrert periode; kobling til
originalfaktura kreves fortsatt. Riktig sats, beløp og grunnlag mot originalen
kontrolleres ikke av unntaket og må gjennomgås separat.

Utkast kan fortsatt opprettes og korrigeres. Ved avvisning viser meldingen hvilke
opplysninger som må kontrolleres, og kjernens transaksjon ruller tilbake status
og referanse. Eksisterende validerte fakturaer, bokførte data og nummerserier
omskrives ikke. Dette er en minimumskontroll ved standard validering, ingen
utsendingssperre for allerede validerte dokumenter eller full norsk
regnskapsgodkjenning. Levering, MVA-behandling, historikk, dokumentlayout,
nummerrekkefølge, EHF og rapporter gjenstår som egne kontrollområder.

## Sidebredde (0.4.0)

Modulen laster en versjonert stilfil som lar hovedkolonnen krympe innenfor
skjermen. Brede lister ruller inne i tabellområdet, mens titler og verktøylinjer
brytes på smale skjermer. Bankkontoskjemaet og forsidens bokser tilpasses den
ledige bredden. Modulens egne skjemaer brytes og fakturakontrollen får samme
rullbare tabellinnpakning som de andre kontrollsidene. Ingen felt skjules av
rettingen. Standard Eldy-tema er undersøkt; andre temaer og tillegg kan trenge
egen gjennomgang.
