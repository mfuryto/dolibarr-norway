> Historisk dokumentasjon til og med 0.64.0. Funksjonen er fjernet i 0.65.0.
> Bruk [gjeldende plan](delivery-plan.md) og kjernens arbeidsflyt. Eldre data beholdes i arkivet.

# MVA-registreringshistorikk

Fra 0.62.0: Regnskap → Innstillinger → Norsk regnskapsoppsett →
MVA-registrering. Historikk aktiveres bare ved en uttrykkelig forhåndsvisning
og bekreftelse. Installasjon og oppdatering legger ikke inn datoer eller
endrer «MVA brukes», MVA-nummer, fakturaer eller posteringer.

## Første periode, retting og ny registrering

1. Registrer den første dokumenterte virkningsdatoen hvis den ikke allerede
   finnes. Den tidligere første-dato-funksjonen beholdes.
2. Under registreringshistorikken kontrollerer du periode 1. Oppgi første
   registrerte dag, eventuell siste registrerte dag og begrunnelse/referanse
   til grunnlaget. Ved første bekreftelse kopieres den opprinnelige datoen
   med sin bekreftelsesinformasjon som første historikkversjon. Endringen
   blir neste versjon; den opprinnelige konstanten overskrives ikke.
3. Velg **Rett eller avslutt periode** for å korrigere datoer eller angi siste
   registrerte dag. Hver bekreftelse legger til en ny versjon. Tidligere
   versjoner finnes under **Alle historiske versjoner**.
4. Velg **Ny registreringsperiode** ved dokumentert ny registrering. Perioder
   kan ikke overlappe. Siste dag er inkludert, så en ny periode må begynne
   senere enn forrige periodes siste dag. En periode uten sluttdato må
   avsluttes før en senere periode kan legges til.
5. Bare hvis en periode var feilregistrert, kan den tas ut av nye
   sammenligninger. Dette bevarer historikken og kan ikke angres ved å
   gjenåpne samme periode-ID. En senere reell periode får en ny ID.
   Bruk sluttdato når en reell registrering opphørte.

Tom sluttdato betyr at ingen avslutning er oppgitt, ikke en bekreftelse på
fortsatt registrering. Oppgi **siste registrerte dag**, ikke første dag uten
registrering, når sluttdato dokumenteres. Datoer hentes ikke automatisk fra
dagens registeroppslag eller første faktura.

[Skatteetatens registreringsveiledning](https://www.skatteetaten.no/bedrift-og-organisasjon/avgifter/mva/registrere-endre-slette/)
skiller mellom registrering, godkjenning og fakturering. Oppgitte perioder
beviser derfor ikke alene at MVA kunne anføres på en bestemt faktura.
Brukerens bekreftelsestid i historikken er ikke myndighetenes vedtaksdato.
Etterfakturering og kreditnota krever vurdering av originalleveransen og
dokumentert registreringsgrunnlag; se også
[prinsipputtalelsen om fakturering ved registrering](https://www.skatteetaten.no/rettskilder/type/uttalelser/prinsipputtalelser/registrering-i-merverdiavgiftsregisteret-og-fakturering-av-merverdiavgift--skattedirektoratets-fellesskriv-7.2.12/).

## Bruk i fakturaflyten

Når historikk finnes, bruker nye datosammenligninger denne. En dato innenfor
en oppgitt periode er fortsatt ingen avgiftsgodkjenning. Et hull, en dato
før/etter oppgitte perioder eller ugyldig historikk gir nødvendig oppfølging.
Manglende dekning beviser ikke at foretaket faktisk var uregistrert.
Ugyldig historikk faller ikke tilbake til den opprinnelige første datoen.

Vanlig fakturavalidering viser varsel ved manglende dekning eller ugyldig
grunnlag når fakturaen har MVA. Kreditnota beholder egen påminnelse om
originaldokumentet. Eksisterende minimumskrav og valideringssperrer beholdes;
ingen ny generell datosperre eller automatisk avgiftsrett innføres.

Nye støttede salgsposteringer med historikk bevarer payload 8 og
registreringskontekst 2: historikkversjon, periode-ID/datoer, bekreftelse og
kreditnotamarkering. Dette bruker bare bevarte kilder. Uten historikk
fortsetter nye salgsobservasjoner å bruke payload 3 og kontekst 1.
Eldre payloads tolkes fortsatt etter sine opprinnelige regler og får ingen
ny periodetolkning eller tilbakefylling. Innkjøpsobservasjoner endres ikke.

## Lagring og vern

Historikken ligger som JSON-format 1 i `NORWAY_VAT_REGISTRATION_HISTORY_V1`
i kjernens konstantsystem. Dette er ny versjonert konfigurasjon, ingen
DDL-endring. Den binder foretak og organisasjonsnummer, original første-dato-
JSON og alle revisjoner med periode-ID, datoer, begrunnelse, bruker-ID og UTC.
Den er logisk versjonshistorikk, ikke et manipulasjonssikkert register;
en databaseadministrator kan endre databasen utenfor applikasjonen.

Administrator og aktiv norsk modul/regnskap kreves for lagring. CSRF og en
engangsbekreftelse på ti minutter binder forhåndsvisningen til bruker og
foretak. Identitet/oppsett låses, og ny låst lesing og fingeravtrykk avviser
foreldede endringer også inne i en eksisterende transaksjon. Savepoint
beskytter tidligere data ved feil etter skriving. Globalt foretak 0 er ikke
reserve for historikken. Endret originalregistrering, identitet, duplikate
oppsettsrader, ugyldige data eller overlapp sperrer endringen.

Grensene er 100 historikkversjoner og 60 000 JSON-byte. Kapasitetsgrense
sperrer nye endringer; tidligere historikk beskjæres aldri automatisk.
Oppdatering og backup/gjenoppretting skal bevare både første-dato-konstanten
og historikken. Ikke erstatt disse manuelt for å skjule avvik.

## Teknisk aksept og videre arbeid

Tester dekker import, retting/avslutning/ny periode, hull og inkluderte
sluttdatoer, feilregistrering, avvist gjenåpning, kapasitet, identitet,
rettigheter, foreldede/gjentatte bekreftelser, CSRF og feil etter skriving
inne i ytre transaksjon. Native bokføringshendelser prøver payload 8 og
bevaring av payload 3. Fakturavarsler og norsk/engelsk HTTP inngår. Den
vedvarende installasjonstesten prøver rettet og ny periode gjennom
oppdatering og full backup/gjenoppretting.

Dette er dokumentert konfigurasjon og datospor, ikke komplett A2 eller
faglig produksjonsaksept. Dokumentasjonsgrunnlag for hver avgiftsbehandling,
myndighetsgodkjenning kontra virkning, originalfaktura/etterfakturering,
manuelle bilag og øvrige rettings-/rapporteringsscenarioer må kvalifiseres videre.
