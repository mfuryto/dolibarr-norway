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
