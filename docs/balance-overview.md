# Balanseoversikt

Fra **0.86.0**: **Regnskap → Balanseoversikt**, ved siden av kjernens
kontospesifikasjoner. Velg **Per dato** og oppdater. Rapporten viser bokførte
NOK-beløp for ett foretak med kalenderår og norsk firesifret kontoplan.

## Hva oversikten viser

- **Eiendeler:** anleggsmidler etter bokførte avskrivninger, varelager,
  kundefordringer, andre fordringer/forskuddsbetalte kostnader, kortsiktige
  investeringer og bank/kontanter.
- **Egenkapital og gjeld:** bokført egenkapital/eierkontoer, udisponert
  resultat, langsiktig gjeld/avsetninger, kortsiktige lån, leverandørgjeld,
  MVA/skatt/offentlige avgifter og annen kortsiktig gjeld.
- Summer for eiendeler, egenkapital og gjeld og en synlig differanse.

Grupper uten kontoer med saldo skjules. Klikk på en konto for å åpne kjernens
hovedbok med samme konto og hele datoomfanget som ligger bak saldoen.
Kontonavn fra valgt kontoplan beholdes, også når de er endret lokalt.
Saldoer med motsatt fortegn vises negativt i kontogruppen; rapporten gjør
ikke en automatisk ompostering eller skattemessig omklassifisering.

**Kontobalanse** er fortsatt kjernens saldobalanse med kontoer, debet og
kredit. **Balanseoversikt** gir den grupperte presentasjonen av balansen.
Ingen av disse henter en uregistrert nettbanksaldo automatisk.

## Resultat og årsskifte

Gjenværende saldo på resultatkontoene vises én gang som **Udisponert resultat**
under egenkapital. Det omfatter årets og eventuelt tidligere åpne års resultat.
Et resultatdisponeringsbilag reduserer denne raden og øker bokført egenkapital;
rapporten fører ikke en ekstra disponering. Et tap reduserer egenkapitalen.

Etter kjernens saldooverføring brukes siste inngående balanse og senere
posteringer. Tidligere års bevegelser legges ikke oppå de overførte saldoene.
En historisk dato før overføringen viser fortsatt grunnlaget fra den gangen.
Kjernen beholder alle posteringer; oversikten lagrer ingen rapportkopi.

## Fullstendighet og tilgang

Tallene bygger på hovedboken. Validerte fakturaer, bankbevegelser og kladder
som ennå ikke er overført, telles opp med lenker til kjernens arbeidsflater.
Beløpene deres tas ikke inn som om de var bokført. En manglende faktura eller
banktransaksjon som aldri er registrert, kan rapporten ikke oppdage.

En balansert sum betyr derfor ikke at regnskapet er fullstendig eller avstemt.
Ved ubalanse vises den faktiske differansen. Ved ukjent kontoplan-/gruppevalg,
ugyldig dato eller utydelig saldooverføring vises en forklaring, uten oppdiktede
nullsummer eller en skjult balanseringspost. Ukjent kontotype må rettes med
kjernens kontoplan-/avslutningsoppsett. Det endres aldri automatisk ved lesing.

Interne brukere trenger kjernens **leserett til hovedbok**. Skrive- og
regnskapseksportrett kreves ikke for å lese rapporten. Eksterne brukere og
andre foretak får ikke tilgang. Siden er skrivebeskyttet og leveres uten cache.
Ingen ny tabell, migrering, kontomapping eller oppsettbekreftelse er innført.
Dette er en løpende oversikt, ikke en ferdig innsending av lovpliktig årsregnskap.

## Kjerneundersøkelse, kilder og prøvebevis

Undersøkt før implementasjon 2026-10-18: Dolibarr 24.0.1 har **Kontobalanse**,
men ingen tilgjengelig gruppert norsk balanserapport som dekker den bestilte
visningen. Kjernens `BookKeeping::fetchAllBalance()` summerer de bokførte
kontobeløpene. Den gjenbrukes med datoavgrensning og uten side-, journal- eller
eksportfilter. Summering, klassifisering og kildeoversikt leses i samme
repeatable-read-transaksjon. Posteringer og bankregistreringer endres ikke.

Fikens offentlige dokumentasjon beskriver en egen balanserapport under
Oversikt, mens bankoversikten viser et annet utvalg. Tripletex presenterer
resultat og balanse som egne rapporter med kontodetaljer. Dette er inspirasjon
for plassering og lesbarhet; leverandørspesifikke kontomodeller kopieres ikke.
Altinn beskriver dokumentasjon og avstemming av saldoene som en egen oppgave.

- [Dolibarr 24.0.1: native kontosummering](https://github.com/Dolibarr/dolibarr/blob/24.0.1/htdocs/accountancy/class/bookkeeping.class.php)
- [Dolibarr 24.0.1: Kontobalanse](https://github.com/Dolibarr/dolibarr/blob/24.0.1/htdocs/accountancy/bookkeeping/balance.php)
- [Fiken: resultat- og balanserapport](https://hjelp.fiken.no/tips-til-revisorer-hvordan-revidere-effektivt-i-fiken)
- [Fiken: bankkontooversikt](https://hjelp.fiken.no/oversikt-over-bankkontoer-i-fiken)
- [Tripletex: resultat- og balanserapport](https://www.tripletex.no/produktnyheter/ny-og-raskere-resultat-og-balanserapport/)
- [Altinn: dokumentasjon av balansen](https://info.altinn.no/starte-og-drive/regnskap-og-revisjon/regnskap/dokumentasjon-av-balansen-avstemminger/)

`tests/balance-overview.php` bruker native posteringer med uavhengig fasit:
bank 8 900, kunder 2 500, utstyr etter avskrivning 1 400, lager 500 og forskudd
900 gir eiendeler **14 200**. Leverandører 500, avgifter 400 og lån 3 000 gir
gjeld **3 900**. Bokført egenkapital/eierkontoer 9 300 og udisponert overskudd
1 000 gir egenkapital **10 300**. Datogrense, åpent foregående år, disponering,
native årsskifte, senere tap 100 og uendret historisk rapport er prøvd. En
ensidig post på 1 gir faktisk differanse 1. Ukjent kontotype avvises.

`tests/balance-http.php` prøver faktisk meny, norsk/engelsk, datoer, uoverført
native leverandørfaktura og en ikke-administrator med kun leserett. Lesing
krever ikke eksportrett. `tests/navigation.php` prøver riktig nivå under
Regnskap uten flere oppsett-/kontrollmenyer. PC/nettbrett/mobil prøves med
1440/1024/768/390 på norsk og engelsk. Versjonen følger vanlig CI og PR-flyt.
