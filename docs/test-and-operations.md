# Testmiljø, installasjon og drift

Oppdatert 2026-10-18 for modul 0.82.0. Kjernen er fortsatt **Dolibarr 24.0.1**,
med låst image-digest og MariaDB 10.11. En moduloppdatering oppgraderer ikke
kjernen til 24.0.2. Kjerneoppgraderinger krever egen kompatibilitetsprøve.

## Testene som brukes nå

```sh
python3 -m unittest discover -s tests -v
python3 scripts/package.py
bash scripts/ci-integration.sh
bash scripts/ci-installation.sh
```

De to siste skriptene oppretter egne midlertidige miljøer med syntetiske data,
uten delte driftsvolumer eller publiserte webporter. De rydder bare sine egne
miljøer. Regnskapsprøvene har testmarkører og databasenavn som avviser vanlig
drift. Ingen reelle bilag eller personopplysninger skal brukes.

Kildejobben kjører Python-prøver, grenpolicy, hemmelighetsskanning av hentet
historikk og pakkebygging. Integrasjonsjobben kjører fersk Dolibarr,
PHP-syntaks, modul-/migreringsprøver, norsk oppsett og native dokument-,
bokførings-, betalings- og rapportprøver. Den kjører også installasjonsprøven.
Fra 0.82.0 prøves også låste SAF-T 1.40-referanser og første native
periodeuttrekk. Dette er ikke en prøve av ferdig SAF-T-eksport;
[konto-/avgiftskoblinger, reskontro og XML-generering gjenstår](saft-export.md).
Se skriptene og [leveranseplanens akseptmatrise](delivery-plan.md) for faktisk
omfang. Grønn CI er ikke i seg selv norsk regnskapsmessig produksjonsaksept.

Modulpakken heter `dist/module_norway-0.82.0.zip`. Pakkebyggingen krever at
README-versjonene stemmer med modulversjonen, og tar med modul og lisens.
Testdata og driftshemmeligheter pakkes ikke inn.

## Interaktivt utviklingsmiljø

```sh
python3 scripts/init-test-env.py
docker compose -f compose.test.yaml config --quiet
docker compose -f compose.test.yaml up -d
```

Dette miljøet bruker bare syntetiske data og egen webport på localhost.
Testpassord opprettes tilfeldig i en lokal ignorert miljøfil med modus 0600.
Filen skal ikke overskrives, deles eller lagres i Git. Standard Compose er
vedvarende drift; velg alltid `compose.test.yaml` uttrykkelig for dette miljøet.
Testmiljøet kan stoppes og startes uten å slette volumer.

## Oppstartsoversikt og nettleserprøve

`tests/http-smoke.php` prøver norsk og engelsk oppstartsoversikt med 18 oppgaver,
lenker til standardverktøy, uttrykkelig uferdig støtte og gamle steg-bokmerker.
Status følger faktiske endringer av standardkontoer; testinnstillingene
føres tilbake. Før/etter-fingeravtrykk av fakturaer, bokføring og metadata
kontrollerer at lesing av oversikten og tilknyttede GET-sider ikke endrer
regnskapsdata. Det finnes ingen fullførings- eller godkjenningslagring.

`tests/browser-layout.cjs` bruker Playwright 1.58.2/Chromium i et separat
utviklingsmiljø. Den krever `NORWAY_SYNTHETIC_TEST=1` og testadministratorens
passord i prosessmiljøet, avviser eksterne verter, og skriver ingen regnskapsdata.
Aktuelle sider prøves ved 390/768/1024/1440 piksler på norsk og engelsk.
Prøven kontrollerer bredde, synlige lenker og at oppstartsoversiktens innhold
ikke overlapper neste oppgave. Den bruker ikke de utgåtte kontrollsidene og
registrerer ingen kontrollhistorikk. Nettleserprøven kjøres separat fra den
pålagte CI-en; resultatet skal beskrives ved relevante layoutendringer.

## Oppgradering og utgåtte filer

Det offisielle imaget har et vedvarende custom-volum. Nye filer i imaget
må derfor oppdatere modulfilene i dette volumet. Fra 0.76.0 fjerner oppstarten
bare eksplisitt utgåtte, eide PHP-filer fra `data/retired-files.txt` før nye
modulfiler kopieres inn. Andre moduler og regnskapsdata beholdes.

Sluttkontrollen krever riktig kjerne-/modulversjon, SHA-256-samsvar for leverte
filer, og avviser ekstra PHP-filer i modulområdet. Dermed kan ikke en gammel
skriveflate bli stående bare fordi menylenken er fjernet. Ukjent ekstra kode
slettes ikke automatisk; oppdateringen feiler og krever undersøkelse.

Installasjonsprøven legger alle 42 utgåtte PHP-filer tilbake i et syntetisk
custom-volum før oppdatering og krever at de forsvinner. Den prøver også en
uventet PHP-fil og krever konkret feil i sluttkontrollen. En annen modul og
lagrede bevaringsmarkører skal overleve. Tidligere lagret kontrollhistorikk
beholdes i lesearkivet; oppryddingen sletter ikke denne.

## Backup og gjenoppretting

Oppdateringsskriptet krever main, ren arbeidskopi, eksisterende privat miljøfil
og eksklusiv oppdateringslås. Det tar backup før bygging og containerbytte.
Kopien omfatter database, dokumenter, konfigurasjon, custom-filer, miljøfil,
kilde-/imageidentitet og det faktiske kjørende imaget. Backupmappen er privat;
komplettmerke skrives først etter arkiver og kontrollsummer.

Installasjonsprøven prøver migrering fra navngitte volumer til prosjektlokale
datamapper, oppdatering og gjenoppretting av database, dokumenter, konfigurasjon,
custom og syntetiske markører. Kontoplan, innstillinger, modulmetadata,
instansnøkkel og tidligere arkivinnhold skal bevares. Dette beviser den
syntetiske prøven, ikke at en bestemt driftsservers backup er komplett.

Et kjent driftsgap er at backupskriptet starter web igjen før oppdateringen
bygger og erstatter containeren. Eventuelle skriv i mellomrommet er nyere enn
backupen. [R13](review-0.75.0.md) må håndteres ved kvalifisering av et sammenhengende
vedlikeholdsvindu og gjenoppretting. Ikke gjør automatisk rollback som kan
slette nye regnskapsposter.

Backup ved oppdatering erstatter ikke regelmessig backup. Driften må også ha
en separat sikret kopi, avklart oppbevaring/lagringsland, overvåket resultat og
jevnlig isolert gjenopprettingsprøve. Ingen backup eller hemmelighet skal i Git.

## Ved feil

Sluttkontrollen bruker `php://stderr` og skriver bare konkrete `FAIL`-navn,
ikke konfigurasjonsverdier eller hemmeligheter. Oppdateringen markeres ikke
ferdig ved feil. Behold backup og eksisterende innstillinger; undersøk årsaken.
Ikke regenerer passord/instansnøkkel, slett volumer eller hopp over kontrollene.

`FAIL norway_module_payload` betyr at leverte filer ikke stemmer eller at ekstra
PHP-kode ligger igjen. `FAIL instance_key_match` betyr avvik mellom bevart nøkkel
og forventet miljøkonfigurasjon. Andre navn viser hvilken teknisk forutsetning
som svikter. En bestått sluttkontroll gjelder driftsegenskaper, ikke fullstendig
MVA-behandling, SAF-T, EHF, årsoppgjør eller skattemelding.
