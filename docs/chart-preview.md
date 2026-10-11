# Forhåndsvisning av kontoplanimport

Norway 0.7.0 gir en skrivebeskyttet forhåndsvisning fra modulens oppsettside eller bokføringskontrollen. Administratoren limer inn CSV og trykker «Vis forhåndsvisning». Det finnes ingen importknapp i denne leveransen. Kontoer, koblinger, konstanter og bilag endres ikke.

## Format

`norway-chart-csv/1` er et versjonert inndataformat, ikke en norsk kontoplan eller SAF-T-mapping. CSV bruker UTF-8, valgfri BOM, semikolon og nøyaktig overskrift `account_number;label`. Kontonummer må være 4–8 siffer uten innledende null. Navn trimmes i kantene og må være 1–255 byte. Navn med semikolon må stå i doble anførselstegn; doble anførselstegn inne i navnet skrives to ganger. Linjeskift inne i navn støttes ikke. Tomme linjer ignoreres.

Maksimum er 500 kontoer og 256 KiB. Duplikate kontonumre, feil format, kontrolltegn og overskredne grenser avviser hele forhåndsvisningen. Ingen delvis forhåndsvisning vises ved parser- eller databasefeil.

Eksempel med syntetiske navn, ikke en faglig kontoplan:

```csv
account_number;label
1500;Syntetisk konto A
3000;Syntetisk konto B
```

Velg først en aktiv kontoplan knyttet til Norge i Dolibarrs bokføringsoppsett. Hvis ingen slik plan finnes, må den etableres separat før denne forhåndsvisningen kan brukes. Automatisk oppretting av kontoplan og et faglig gjennomgått norsk kontoutvalg gjenstår.

## Resultater

| Resultat | Betydning |
|---|---|
| Ny konto | Nummeret finnes verken i valgt plan for aktivt foretak eller i foretakets hovedbok. Kontoen er ikke opprettet. |
| Behold eksisterende konto | Aktiv konto med samme navn finnes i valgt plan for aktivt foretak. |
| Konflikt | Navn avviker, eller flere kontotreff finnes. Eksisterende opplysninger bevares. |
| Inaktiv konto | Kontoen finnes, men er deaktivert. Den aktiveres ikke. |
| Historisk kontonummer | Nummeret mangler i valgt plan, men finnes i foretakets hovedbok. Det må undersøkes før en senere import. |

Hovedbokbruk telles per foretak og kontonummer, også fra tidligere kontoplaner, fordi kjernens hovedbok ikke har et kontoplan-ID-felt. Opplysningen sier ikke at alle koblinger til en konto er funnet. Produkter, motparter, fakturalinjer og andre koblinger undersøkes ikke. Tilgjengelig navn/nummer bekrefter heller ikke korrekt avgiftsbehandling eller mapping.

CSV behandles bare i forespørselen og lagres ikke av modulen. Ingen eksterne tjenester kontaktes. Siden krever administrator, aktiv modul og gyldig CSRF-token ved innsending; resultatet sendes med `Cache-Control: no-store`. Kontonavn HTML-escapes ved visning.

## Aksept og neste del

Syntetiske parser- og databasetester dekker formatgrenser, duplikater, sitering, BOM, feil UTF-8, gjentatt forhåndsvisning, kontoer fra annen entity/plan, inaktive kontoer, navnekonflikt, historiske kontonumre og databasefeil. HTTP-testene dekker tilgang før innlogging, skjema, resultat, HTML-escaping, feil CSV og CSRF-avvisning. Nettlesertesten består for 16 sider ved fire skjermbredder (64 visninger), inkludert det nye CSV-feltet.

Selve importen, faglig kontoutvalg og versjonert SAF-T-/næringsmapping er fortsatt neste del av trinn 1b. Importen må senere avvise konflikter, kontrollere at forhåndsvisningen fortsatt gjelder, bruke transaksjon og bevare eksisterende kontoer. Automatisk endring av brukte kontoer skal ikke innføres. Denne leveransen krever ingen databasemigrering.
