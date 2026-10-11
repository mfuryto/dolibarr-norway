# Testmiljø, installasjon og drift

Kontrollert 2026-10-11. Kun syntetiske testdata. Dette dokumentet beskriver også
planlagte produksjonsrutiner; det er ikke bevis på ferdig produksjonsdrift.

## Lokalt testmiljø

`compose.yaml` låser Dolibarr 24.0.1 og MariaDB 10.11 til konkrete image-digests.
Testet PHP-versjon: **8.2.34**. Databasen har ingen publisert port og ligger på
internt nettverk. Web har egen nettverkstilkobling og er bundet til
`127.0.0.1:8087`. Ingen cron, lisensportal eller eksterne integrasjoner aktiveres.
Miljøet er isolert fra produksjonsdata, men webcontainerens frontend-nettverk
kan nå internett. Dette er ikke et herdet produksjonsoppsett.

```sh
python3 scripts/init-test-env.py
python3 -m unittest discover -s tests -v
docker compose config --quiet
docker compose up -d
docker compose logs --tail 20 web
# Etter at førstegangsinstallasjonen er ferdig:
docker compose exec -T web php /opt/norway-tests/integration.php
docker compose exec -T web php /opt/norway-tests/http-smoke.php
```

Miljøfilen `.env` opprettes med tilfeldige testpassord og filmodus 0600.
Skriptet nekter å overskrive en eksisterende fil. Filen er ignorert av Git.
Åpne http://localhost:8087; brukernavn `testadmin`, passord fra
`TEST_ADMIN_PASSWORD` i den lokale `.env`-filen. Ikke del eller sjekk inn filen.
Installasjonen og dokumentene ligger i navngitte Docker-volumer. Stoppe uten
å slette data: `docker compose stop`. Starte igjen: `docker compose start`.

Integrasjonstesten skriver syntetiske importbilag via Dolibarrs
`BookKeeping::create()`, aktiverer/deaktiverer/reaktiverer modulen, avstemmer
inngående/utgående saldo og hver bilagsbalanse, og kontrollerer at alle importerte
linjer kan spores til testdokumentet. Den nekter kjøring uten egne testmarkører,
DB-navnet `norway_test` og syntetisk foretaksnavn. Testbilag har `SYN-`-referanser.
Gjenta på en ny testdatabase; samme seed avvises for å unngå dobbel bokføring.
For bevisst nullstilling av **bare dette syntetiske miljøet** kan
`docker compose down --volumes` brukes; dette sletter testdata og er ikke en
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
