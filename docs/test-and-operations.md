# Testmiljø, installasjon og drift

Kontrollert 2026-10-11. Kun syntetiske testdata. Dette dokumentet beskriver også
planlagte produksjonsrutiner; det er ikke bevis på ferdig produksjonsdrift.

## Lokalt testmiljø

`compose.test.yaml` låser Dolibarr 24.0.1 og MariaDB 10.11 til konkrete image-digests.
Testet PHP-versjon: **8.2.34**. Databasen har ingen publisert port og ligger på
internt nettverk. Web har egen nettverkstilkobling og er bundet til
`127.0.0.1:8087`. Ingen cron, lisensportal eller eksterne integrasjoner aktiveres.
Miljøet er isolert fra produksjonsdata, men webcontainerens frontend-nettverk
kan nå internett. Dette er ikke et herdet produksjonsoppsett.

```sh
python3 scripts/init-test-env.py
python3 -m unittest discover -s tests -v
docker compose -f compose.test.yaml config --quiet
docker compose -f compose.test.yaml up -d
docker compose -f compose.test.yaml logs --tail 20 web
# Etter at førstegangsinstallasjonen er ferdig:
docker compose -f compose.test.yaml exec -T web php /opt/norway-tests/integration.php
docker compose -f compose.test.yaml exec -T web php /opt/norway-tests/http-smoke.php
```

Miljøfilen `.env` opprettes med tilfeldige testpassord og filmodus 0600.
Skriptet nekter å overskrive en eksisterende fil. Filen er ignorert av Git.
Åpne http://localhost:8087; brukernavn `testadmin`, passord fra
`TEST_ADMIN_PASSWORD` i den lokale `.env`-filen. Ikke del eller sjekk inn filen.
Installasjonen og dokumentene ligger i navngitte Docker-volumer. Stoppe uten
å slette data: `docker compose -f compose.test.yaml stop`. Starte igjen: `docker compose -f compose.test.yaml start`.

Integrasjonstesten skriver syntetiske importbilag via Dolibarrs
`BookKeeping::create()`, aktiverer/deaktiverer/reaktiverer modulen, avstemmer
inngående/utgående saldo og hver bilagsbalanse, og kontrollerer at alle importerte
linjer kan spores til testdokumentet. Den nekter kjøring uten egne testmarkører,
DB-navnet `norway_test` og syntetisk foretaksnavn. Testbilag har `SYN-`-referanser.
Gjenta på en ny testdatabase; samme seed avvises for å unngå dobbel bokføring.
For bevisst nullstilling av **bare dette syntetiske miljøet** kan
`docker compose -f compose.test.yaml down --volumes` brukes; dette sletter testdata og er ikke en
backup-/oppgraderingsrutine. Start på nytt og kjør testene etter nullstilling.

## Hva testene beviser

- Modulens livssyklus fungerer mot den faktiske Dolibarr-databasen.
- Webtesten kontrollerer at statusen krever innlogging og vises etter innlogging.
- Python-testene avstemmer dokumentbeløp, debet/kredit, åpnings-/sluttsaldo,
  25 % avgiftsgrunnlag, delkreditnota, inn-/utbetaling og bilagsreferanser.
  Uregistrert profil kostnadsfører innkjøpet brutto. Mutasjoner kontrollerer
  at ubalanse, feil avgift, manglende kreditreferanse, feil betaling, feil
  saldo og urettmessig fradrag faktisk avvises.
- Samme syntetiske regnskap er importert i standard hovedbok og kontosaldoene
  stemmer med uavhengig oppgitte forventninger. Import er bevisst brukt for
  å få et lite regnskap uten å implementere en parallell fakturamotor.

**Begrensninger:** Testdokumentene er JSON-fixtures, ikke fakturaer opprettet
og validert av standard fakturamodul. Ingen faktisk faktura→hovedbok-, PDF-,
EUR-, EHF- eller SAF-T-eksporttest er gjennomført. MVA-grunnlag er kontrollert
mot fixture-beløp og hovedbokens avgiftssaldo, ikke en ferdig norsk rapport.
Importene er testposter, ikke låste produksjonsbilag. Full feltmatrise,
reskontro med ekte syntetiske motparter, delbetaling, fakturavalidering,
årsskifte og negativ tilgangstest for innlogget ikke-admin gjenstår.

## Installerbar modul

```sh
python3 scripts/package.py
```

Bygger `dist/module_norway-0.1.0.zip`, kun modul og lisens, uten testdata og
lokale hemmeligheter. Kopier alternativt `htdocs/custom/norway` til
Dolibarr-installasjonens `htdocs/custom/norway`. Aktiver norsk lokalisering i
Innstillinger → Moduler. Advanced Accounting aktiveres som avhengighet.
Modulens innstillingsside er begrenset til administrator.

Modul-ID 510173 er lokal/provisorisk; kontroller kollisjon og reserver ID før
distribusjon. Ingen kjernetabeller eller satser endres av modulen. Det følger
ikke med kontoplan, MVA-regler eller SAF-T-eksport i versjon 0.1.0.

## Backup, gjenoppretting og oppbevaring før produksjon

Ansvarlig og lagringssted må fastsettes før produksjon. Ta daglig konsistent
backup og før oppgraderinger: database (inkludert modulens tabeller), alle
bilags-/dokumentfiler, nødvendig konfigurasjon og eksakte program-/modulversjoner.
Oppbevar hemmeligheter separat kryptert. Bruk vedlikeholdsvindu eller dokumentert
snapshotmetode så DB og dokumenter representerer samme tidspunkt.

Oppbevar krypterte kopier utenfor driftsmaskinen med begrenset tilgang,
versjonshistorikk og overvåket resultat. Dokumenter oppbevaringskategorier og
frister etter gjeldende regler for faktisk materiale; fem års hovedregel er
ikke en universell slettefrist for alle dokumenttyper. Ingen backup skal i Git.
Avtal gjenopprettingstid og maksimalt datatap ut fra faktisk drift.

Minst kvartalsvis og før produksjonsaksept: gjenopprett DB, dokumenter og
konfigurasjon til separat isolert instans med samme programversjoner. Verifiser
antall bilag, vedleggshasher, inngående/utgående saldo, reskontro, MVA-rapport og
SAF-T-totalsummer. Dokumenter tidspunkt, ansvarlig, avvik og målt tid.
Ikke kall en backup godkjent før restore er prøvd. **Full restore-prøve er ikke
gjennomført i denne leveransen.**

Ved oppgradering: lag backup, prøv ny versjon på kopi, kjør funksjons-/eksport-
og migreringstester og dokumenter reverseringsplan før endring av driftsinstans.
Modulens nøyaktige versjonskontroll utvides først etter bestått regresjonstest.

## Kontrollside for foretaksoppsett

`tests/readiness.php` tester kontrollsiffer (inkludert 0 og ugyldig 10),
feil lengde/innhold, tomt foretaksoppsett, satser bare for salg, inaktive
kjøpssatser, separate kjøps-/salgssatser og aktive/inaktive historiske satser.
Numeriske test-ID-er er kunstige og representerer ikke bekreftede foretak.
HTTP-testen kontrollerer at administrator får kontrolltabellen uten SQL-feil,
og at uferdig SAF-T/EHF fortsatt vises. CI kjører disse i den isolerte
Dolibarr-installasjonen. Ingen regnskapsdata endres av kontrollsiden.

## Vedvarende drift og oppdatering

Standard Compose gjelder drift; utviklingstestene krever `compose.test.yaml`.
CI sammenligner fullt oppløst standardoppsett med `deploy/compose.yaml` uten å
skrive hemmeligheter til logg. Serveroppdateringen testes fra en ren main-checkout,
med privat backup og helse-/versjonskontroll. Backupens kontrollsummer kontrolleres;
konfigurasjonstabellen fjernes og gjenopprettes fra SQL-dumpen, og en slettet
dokumentmarkør gjenopprettes sammen med conf før innlogging/instansnøkkel prøves.
Det konkrete kjørende web-imaget arkiveres også. En backup merkes COMPLETE først
når arkiver og kontrollsummer er skrevet. Første backup kan ha ukjent kildecommit
for eldre installasjoner; image-ID og selve imaget bevares likevel.

Negative tester bekrefter at manglende miljøfil, staging-gren, skitten checkout
og parallell oppdatering avvises før Docker kjøres. Dette er teknisk driftsprøve,
ikke økonomisk produksjonsaksept eller full restore-prøve av faktisk server.

## Migrering til prosjektlokale datamapper

Installasjonstesten starter med navngitte volumer og oppdaterer til bind-monterte
`data/database`, `data/documents` og `data/config`. En syntetisk DB-verdi og et
syntetisk vedlegg må bevares både ved migrering og ved senere restore. Testen
kontrollerer monteringsstedene og at ny migreringskjøring er idempotent.
Blandet lagring og utviklingsdatabase avvises før stopp eller kopiering.
Originalvolumer beholdes i drift; CI fjerner bare sitt eget isolerte testprosjekt.
Datamappene og standard backupmappe er ekskludert fra Git og Docker-byggekontekst.


## Faktura og tilbud gjennom standard arbeidsflyt

`invoice-workflow.php` oppretter og validerer fakturaer med Dolibarrs klasser og
overfører dem gjennom den faktiske HTTP-salgsjournalen med innlogging/CSRF.
Det kontrollerer 25 prosent, nullsats, delkreditnota, NOK i
hovedboken og at ny overføring ikke gir dubletter. Nullsats er ikke i seg selv
en test av MVA-registerstatus. Fakturaer bruker påtvungne syntetiske referanser;
produksjonens fakturanummerserie er ikke kvalifisert her. Tilbud bruker kjernens
nummermodell. Egne syntetiske kontoer representerer ikke en ferdig norsk kontoplan.

Tilbud og faktura genereres på begge språk i NOK. Testen kontrollerer
oversatt tittel og valutaetikett i PDF-innholdsstrømmene, ikke bare metadata,
samt kildebeløp. Full PDF-layout, NOK-avgiftsvisning og alle norske dokumentkrav
krever videre kontroll. Ingen produksjonsdokumenter regenereres i disse testene.

`ledger-audit.php` prøver balanse med åtte desimaler, fakturakobling, negativt
ubalansert bilag med manglende kilde, foretaksskille, datovalidering og varsling
ved avkortet liste. HTTP-testen prøver siden og feil på ugyldig periode.

NOK-begrensning, avvisning/rollback av sjekkbetaling og fortsatt fungerende
bankoverføring testes gjennom kjernens triggere. HTTP-testen prøver skjult
sjekkmeny og 403 på direkte sjekkinnskuddssider. EUR-kalkulatoren testes med
syntetiske API-svar for kursretning, helg, dato, enhet og ugyldige/gamle svar.
CI er ikke avhengig av Norges Banks tilgjengelighet.

Valutamigrering prøves i tomt syntetisk grunnoppsett med den tidligere
informasjonsinnstillingen NOK og faktisk EUR i kjernen. Testen krever at
`MAIN_MONNAIE` blir NOK. Et utfylt regnskap med annen basisvaluta skal avvises,
og prøves separat gjennom fakturaarbeidsflyten.

## Feil i sluttkontrollen ved oppdatering

Sluttkontrollen kjøres via standard input til PHP. Ved feil brukes
`php://stderr`, og kontrollen skriver navn på alle punkter som feiler, uten
konfigurasjonsverdier eller hemmeligheter. Oppdateringen avsluttes med feil og
markeres ikke som ferdig. En tidligere `Undefined constant "STDERR"` maskerte
den egentlige feilen; den betyr ikke at kontrollene kan hoppes over.

`FAIL instance_key_match` betyr at den lagrede instansnøkkelen og containerens
forventede nøkkel ikke stemmer, eller at forventet nøkkel mangler. Bevar backup
og undersøk samsvaret mellom privat miljøfil og eksisterende konfigurasjon.
Ikke regenerer nøkler eller slett konfigurasjonen som en automatisk løsning.
Andre `FAIL`-navn identifiserer modul, basisvaluta, språkvalg eller installatørlås.
CI fremprovoserer en nøkkelfeil i separat syntetisk prosess med samme PHP-stdin-
kjøremåte og krever exitkode 1, konkret kontrollnavn og ingen hemmelige verdier.

Forsidens Bankkonto-gruppe testes med en faktisk syntetisk bankkonto som kan
avstemmes. Testen krever at bankgruppen finnes, og avviser både sjekkinnskudds-
tekst og lenker. En egen hook-test krever at sjekkraden fjernes mens bankkonto,
direktebelastning og bankoverføring beholdes. Moduloppgradering prøves fra den
eldre hook-registreringen med bare `main` til `main` og `index`.
