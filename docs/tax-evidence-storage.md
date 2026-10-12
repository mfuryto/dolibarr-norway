# Versjonert lagringsgrunnlag for avgiftsopplysninger

Modul 0.33.0 innfører skjema v1 for fremtidige avgiftsøyeblikksbilder. Dette er
lagrings- og migreringsgrunnlag. Fra modul 0.34.0 brukes tabellen også til
[bekreftede kontrollkopier](invoice-evidence.md). Ingen fakturaer klassifiseres
automatisk og ingen historiske avgiftsopplysninger fylles inn. MVA-melding og
SAF-T-eksport gjenstår.

To eksterne modultabeller opprettes med installasjonens tabellprefiks:

- `norway_schema`: migreringsversjon, SHA-256 for migreringsfilen og UTC-tidspunkt.
- `norway_tax_evidence`: foretak, dokumenttype/ID, revisjon, tidspunkt, bruker,
  mappingversjon, JSON-plass for fremtidig innhold og innholdets SHA-256.

Dokumentrevisjon er unik innen foretak/dokumenttype/dokument-ID. Primærnøkkel
og unik indeks kontrolleres. Tabellen bruker InnoDB. Ingen kjernetabeller endres.
Tabellenes eksistens bekrefter ikke bokført avgiftsgrunnlag eller sporbarhet.
Kontrollkopiens innholdsskjema, skriveflyt, låsing og kobling til posteringer
er avgrenset og dokumentert i veiledningen for kontrollkopier. Dette er ikke
dokumentasjon av opprinnelig avgiftsbehandling på bokføringstidspunktet.
SHA-256 er heller ikke et manipulasjonssikkert revisjonsspor alene.

## Migrering og drift

Migrering `001-tax-evidence.sql` er låst med SHA-256 i `NorwaySchema`. Den kjøres
ved modulaktivering og gjennom den ordinære oppdateringsflyten, etter backup.
Ved manuell oppgradering uten oppdateringsskriptet må administrator ta full
backup før modulaktivering; modulaktivering lager ikke backup selv.
Migreringshistorikken deles av foretakene i samme database/tabellprefiks.
En navngitt databaselås serialiserer migrering. Lagringsstrukturen og historikken
kontrolleres på nytt ved gjentatt kjøring. Ordinær installasjonsverifisering
kontrollerer skjemaet skrivebeskyttet og rapporterer `FAIL norway_schema_version`
ved avvik.

DDL i MariaDB er ikke én tilbakeførbar transaksjon. Migrering avviser derfor en
åpen transaksjon før den utfører DDL. Ved avbrudd etter oppretting av tomme,
korrekte tabeller, men før migreringsmarkøren, kan ny kjøring fullføre.
Migreringsversjonen registreres først etter at strukturen er kontrollert.

Ukjent/fremtidig versjon, endret kontrollsum, inkompatible kolonner/indekser eller
ikke-transaksjonell tabellmotor sperrer migrering. Eksisterende avgiftsdata uten
migreringshistorikk adopteres ikke automatisk. Hvis registrert migrering finnes
men datatabellen mangler, sperres kjøring i stedet for å opprette en tom tabell
og skjule mulig datatap. Ingen automatisk nedgradering, beskjæring eller sletting
av eksisterende avgiftsdata inngår. Deaktivering av modulen beholder tabellene.

Ta vare på backup og feilmelding ved stopp. Rett årsaken eller gjennomfør
kontrollert gjenoppretting av database og tilhørende kode/konfigurasjon. Ved
retur til en backup fra før skjema v1 må databasen gjenopprettes som en komplett
database; innlesing av en eldre dump over eksisterende tabeller fjerner ikke
nødvendigvis modultabeller opprettet etter backup. Ikke slett tabeller som en
rutinemessig feilretting. Følg [driftsveiledningen](vps-installation.md).

## Teknisk aksept

Isolerte syntetiske prøver dekker ny, gjentatt og avbrutt migrering, kontrollsum,
ukjent versjon, transaksjonsgrense, feil struktur, manglende tabell, unik
revisjon, foretaksavgrensning og bevaring av lagret innhold. Installasjonsprøven
lagrer en uttrykkelig syntetisk post, oppdaterer installasjonen, ødelegger
testtabellen og gjenoppretter den fra backup. Deretter kontrolleres både
skjemaversjon og nøyaktig innhold/kontrollsum. Testdata installeres aldri i
ordinær drift. Dette er teknisk lagringsaksept, ikke regnskapsfaglig aksept.
