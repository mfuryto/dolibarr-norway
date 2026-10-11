# Kontoplan fra CSV: forhåndsvisning og bekreftet import

Norway 0.8.0 utvider forhåndsvisningen fra 0.7.0 med bekreftet import. Administratoren limer inn CSV fra moduloppsettet eller bokføringskontrollen. Forhåndsvisningen er skrivebeskyttet. Bare en egen bekreftelse kan opprette nye kontoer; eksisterende kontoer, koblinger, konstanter og bilag bevares.

## Format og forutsetninger

`norway-chart-csv/1` er et versjonert inndataformat, ikke en norsk kontoplan eller SAF-T-mapping. CSV bruker UTF-8, valgfri BOM, semikolon og nøyaktig overskrift `account_number;label`. Kontonummer er 4–8 siffer uten innledende null. Navn trimmes i kantene og må være 1–255 byte. Navn med semikolon må stå i doble anførselstegn; doble anførselstegn inne i navnet skrives to ganger. Linjeskift inne i navn støttes ikke. Tomme linjer ignoreres.

Maksimum er 1000 kontoer og 256 KiB. Duplikate numre, feil format, kontrolltegn og overskredne grenser avviser hele forhåndsvisningen. Ingen delvis forhåndsvisning vises ved parser- eller databasefeil.

Velg først en aktiv kontoplan knyttet til Norge. Modul 0.9.0 har separat [installering av norsk kontoplan](norwegian-chart.md). Versjonert avgifts-/rapporteringsmapping gjenstår. Ikke bruk syntetiske eksempler som faglig kontoplan.

## Resultater

| Resultat | Betydning og importvirkning |
|---|---|
| Ny konto | Nummeret finnes verken i valgt plan for aktivt foretak eller i foretakets hovedbok. Kan opprettes etter bekreftelse. |
| Behold eksisterende konto | Aktiv konto med identisk navn finnes i valgt plan for aktivt foretak. Kontoen oppdateres ikke. |
| Konflikt | Navn avviker, eller flere kontotreff finnes. Hele importen sperres. |
| Inaktiv konto | Kontoen finnes, men er deaktivert. Hele importen sperres; kontoen aktiveres ikke. |
| Historisk kontonummer | Nummeret mangler i valgt plan, men finnes i foretakets hovedbok. Hele importen sperres. |

Hovedbokbruk telles per foretak og kontonummer, også fra tidligere kontoplaner, fordi kjernens hovedbok ikke har et kontoplan-ID-felt. Produkter, motparter, fakturalinjer og andre koblinger undersøkes ikke. Kontoens eksistens bekrefter ikke riktig avgiftsbehandling eller mapping.

## Bekreftelse og import

Ta en fersk backup med prosjektets backupskript før import. Gå gjennom hele forhåndsvisningen. Bekreftelsesknappen vises bare når alle rader er nye eller allerede samsvarer. Import er en egen administratorhandling med CSRF-token og en tilfeldig engangsbekreftelse bundet til serverøkten, brukeren, aktivt foretak og valgt plan. Den gjelder i ti minutter. CSV og et fingeravtrykk av forhåndsvisningen lagres midlertidig i serverøkten; de skrives ikke til modulens filer eller egne databasetabeller. Ny forhåndsvisning erstatter den gamle. Utløpt bekreftelse kan ikke brukes; øktdata ryddes ved neste sideforespørsel eller øktens normale utløp.

Ved import låses valgt kontoplan, kontoplanvalget og relevante konto-/hovedboktreff i en databasetransaksjon. Oppsettet leses på nytt og sammenlignes med forhåndsvisningen, inkludert eksisterende konto-ID, navn, status og hovedbokbruk. Endringer krever ny forhåndsvisning. Importene fra modulen serialiseres per valgt plan. Bruk ikke parallelle kontoplanendringer eller importer fra andre verktøy mens importen kjører. Låsingen kan kortvarig forsinke andre bokføringsoperasjoner.

Nye kontoer opprettes gjennom Dolibarrs kontoklasse, med bruker-ID, dato, aktiv status og tilknytning til valgt plan. Kontonummer og navn hentes fra gjennomgått CSV. Ingen foreldre, avstemmingsvalg, avgiftskoder, standardkontoer eller rapporteringskategorier utledes. Kjernens standardverdi for uklassifisert kontotype beholdes. Det settes ingen norsk SAF-T-mapping.

Feil under kontooppretting ruller tilbake hele transaksjonen. Ved forbindelsesbrudd under commit kan resultatet være ukjent; kontroller lagrede kontoer med en ny forhåndsvisning før nytt forsøk. Bekreftelsen forbrukes også ved feil; lag ny forhåndsvisning før nytt forsøk. Etter vellykket import gir samme CSV og ny bekreftelse null nye kontoer. Kontoer slettes ikke som angremetode. Feil kontoutvalg må vurderes før bruk; eventuell full gjenoppretting følger driftsrutinene og må ta hensyn til senere posteringer.

## Aksept og begrensninger

Syntetiske parser-/databasetester dekker formatgrenser, duplikater, sitering, BOM, feil UTF-8, entity-/planavgrensning, konflikter, historiske kontonumre, gjentakelse, endret forhåndsvisning og databasefeil. Importtesten tvinger feil ved andre kontooppretting og kontrollerer at ingen delvis import blir stående. HTTP-testene dekker innlogging, forhåndsvisning, escaping, feil CSV, CSRF, bekreftet oppretting og replay-avvisning.

Backup-/restore-testen oppretter en syntetisk konto gjennom importen, tar backup, endrer navnet med vilje og kontrollerer at opprinnelig navn og aktiv status gjenopprettes. Ingen produksjonsdata brukes. Ingen databasemigrering kreves, siden kjernens eksisterende kontotabeller benyttes.

Dette fullfører importmekanismen, ikke hele trinn 1b. Norsk kontoplan og planprofil er levert separat i 0.9.0. Virksomhetens kontobruk og versjonert SAF-T-/næringsmapping er videre arbeid. Ingen samlet produksjonsklar-status innføres.
