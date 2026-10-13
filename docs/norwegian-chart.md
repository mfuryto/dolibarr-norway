# Installerbar norsk kontoplan

Norway 0.9.0 leverer de 745 kontoene fra Odoos offisielle norske regnskapsmodul `l10n_no`, i stedet for et egenlaget lite kontoutvalg. Dette er en etablert norsk programvarekontoplan, ikke en påstand om sertifisering fra Standard Norge eller at hele regnskapsløsningen er godkjent.

## Kilde og gjenbruk

Kilden er Odoo 19.0 ved fast commit `2ff84495182bc2d6e87b616731f865677a12b213`. Modulens manifest oppgir LGPL-3 og krediterer Rolv Råen og Bringsvor Consulting AS. Original CSV, manifest og upstream-lisens følger modulen. Konverteringen beholder alle kontonumre og norske navn uten filtrering. SHA-256-hasher og transformasjonen er dokumentert i modulens `data/norwegian-chart-source.json`. Kontonavn og numre oppdateres ikke automatisk fra nettet.

Primærkilder: [kontoplanfilen](https://github.com/odoo/odoo/blob/2ff84495182bc2d6e87b616731f865677a12b213/addons/l10n_no/data/template/account.account-no.csv), [manifest og lisensangivelse](https://github.com/odoo/odoo/blob/2ff84495182bc2d6e87b616731f865677a12b213/addons/l10n_no/__manifest__.py), [upstream-lisens](https://github.com/odoo/odoo/blob/2ff84495182bc2d6e87b616731f865677a12b213/LICENSE).

Det kopieres ingen proprietær NS 4102-publikasjon eller SAF-T-standardkontoliste med begrenset bruksrett. Det medfølgende datasettet beholder LGPL-3-vilkårene; modulens egen kode er GPL-3.0-or-later. Se `data/THIRD-PARTY-NOTICES.md`.

## Installasjon og vern

Moduloppdatering alene installerer ikke kontoplanen. Administratoren kan bruke **Installer norsk kontoplan** fra moduloppsettet, se alle kontoene og bekrefte innen ti minutter. Alternativt kan serveroperatøren, etter backup, kjøre:

```sh
docker compose --env-file deploy/.env -f deploy/compose.yaml -p dolibarr-norway-accounting exec -T web php < scripts/install-chart-profile.php
```

Installeringsskriptet er en eksplisitt handling og kjøres ikke ved modulaktivering. Det skriver bare kontoplan-ID, kontoantall og generell status, ikke foretaksdata eller passord. Backup må tas før installering.

For et tomt bokføringsoppsett opprettes planen `NORWAY-NO-1`, knyttes til Norge, får alle 745 kontoene og velges for aktivt foretak. Ny installering av samme plan oppretter bare manglende kontoer og bevarer øvrige opplysninger. Hvis kontoene allerede samsvarer, opprettes null kontoer.

Dolibarr velger en standardplan ved aktivering, selv uten installerte kontoer. Et slikt tomt valg kan erstattes ved eksplisitt installering dersom foretaket verken har kontoer eller hovedbokhistorikk. Hvis en annen plan har kontoer, eller foretaket har hovedbokhistorikk, stoppes installeringen. Inaktive/endrede kontoer, historiske kontonumre uten konto i planen, ugyldig profilmetadata eller endret forhåndsvisning blokkerer også installering. Slike situasjoner krever avklart migrering, ikke automatisk bytte.

Oppretting av plan, valg av plan og import skjer i én transaksjon. Bruker/foretak avgrenses og relevant oppsett låses før ny kontroll. Feil før commit rulles tilbake. Ved forbindelsesbrudd under commit kontrolleres resultatet med en ny forhåndsvisning før nytt forsøk. Kjør ikke parallelle kontoplanendringer eller bokføring under installering; låsing kan kortvarig forsinke andre operasjoner.

## Hva kontoplaninstalleringen ikke gjør

Den velger ikke standardkontoer for salg/kjøp, endrer ikke journaler eller bankkontoer og setter ikke MVA-satser eller avgiftskoder. Eksisterende dokumenter og bokførte poster endres ikke. Kontoens navn er ingen avgiftsregel. Odoos kontotyper, avstemmingsflagg og tags beholdes i originalkilden for sporbarhet, men gjettes ikke om til Dolibarrs rapporteringskategorier.

Norsk MVA-behandling, registreringsperioder og SAF-T-/næringsmapping er videre arbeid. Kontoplanen inneholder kontoer for flere virksomhetstyper; at en konto finnes, betyr ikke at et ENK skal bruke den. Bruk bokføringskontrollen etter installering og gjennomgå relevant kontobruk før bokføring.

## Aksept

### Prosjektaksept av kontoplanen – 2026-10-13

Prosjekteier har akseptert den medfølgende profilen som tilstrekkelig norsk
kontoplan og avklart kontoplanens samsvar som prosjektbeslutning. En separat
verifikasjon mot NS 4102:2023 skal derfor ikke blokkere videre utvikling.
Dette er prosjektets aksept, ikke en uavhengig sertifisering fra Standard Norge.

Grunnlaget er en sammenligning av modulens CSV med
[Skatteetatens firesifrede SAF-T-kontoliste ved fast revisjon](https://github.com/Skatteetaten/saf-t/blob/05179521e435d82feb0b2d6c89a92a32a4f2d02f/General%20Ledger%20Standard%20Accounts/CSV/General_Ledger_Standard_Accounts_4_character.csv):
Begge inneholder 745 unike kontonumre, alle sammenfallende, uten manglende eller
ekstra kontoer. 740 norske kontonavn er identiske. Forskjellene på 1195, 1475,
4085 og 7785 er doble mellomrom i referansen; på 2540 er forskjellen en
tankestrek. Ingen forskjell i navnenes meningsinnhold ble identifisert.

Sammenligningen gjelder den distribuerte filen, ikke eventuelle lokale
endringer i en installasjonsdatabase. Aksepten gjelder kontoplanen;
SAF-T-klassifisering, avgiftsmapping, eksport og avstemming må fortsatt
implementeres og testes separat. Eksisterende kontoer eller bokførte poster
endres ikke som følge av denne beslutningen.

### Teknisk verifikasjon

Kildetesten kontrollerer fast revisjon, lisensangivelse, filhasher og tapsfri konvertering av alle 745 kontoer. Syntetiske tester kontrollerer oppretting og valg, gjentakelse uten duplikater, utløpt/endret bekreftelse, navnekonflikter og vern mot annen plan eller eksisterende kontoer. Tidligere kontoimporttester dekker feil under andre kontooppretting og atomisk tilbakeføring. Installasjonstesten prøver CLI-installering to ganger og kontrollerer at alle 745 aktive kontoer og planvalget overlever oppdatering og backup/gjenoppretting. Nettlesertesten består for 17 sider ved fire skjermbredder (68 visninger). Ingen produksjonsdata brukes.
