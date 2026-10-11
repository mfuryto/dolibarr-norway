# To grener: main og staging

Oppdatert 2026-10-11 etter eierens valg. Repoet er offentlig og skal kun ha
`main` og `staging`. Ingen feature-grener eller automatiske oppdateringsgrener. En aktiv GitHub-ruleset
sperrer oppretting av alle andre grennavn, uten administratorunntak.

## Flyt

`utvikling på staging → CI og aksept → PR fra staging til main`

Utvikling committes og pushes direkte til staging etter relevante lokale tester.
CI kjører på hver push. Før promotering skal begge kvalitetssjekkene bestå på
aktuell revisjon, og leveransens støttede omfang og begrensninger dokumenteres.
Staging kan inneholde uferdig arbeid; grønn CI alene er ikke norsk etterlevelse.

Main er fortsatt standardgren og stabil kanal. Bruk merge-commit ved promotering
for å bevare slektskapet. Synkroniser staging med main etterpå uten force-push.
Ingen automatisk promotering eller produksjonsutrulling. Ikke slett staging.

## Håndhevet serververn

| Kontroll | main | staging |
|---|---|---|
| PR kreves | Ja, fra staging; CI kontrollerer kilden | Nei, vanlig utviklingspush tillates |
| Oppdaterte CI-resultater kreves før merge/push | Begge sjekkene må bestå før merge | CI kjører etter push, men sperrer ikke selve pushen |
| Force-push og sletting | Sperret | Sperret |
| Administratorer omfattes | Ja | Ja |

De påkrevde main-sjekkene er `Norway source and policy` og
`Norway Dolibarr integration`, knyttet til GitHub Actions-appen (15368).
Samtaler må være løst. Ingen ekstra reviewer kreves fordi prosjektet har én
identifisert eier; CODEOWNERS angir eierskapet uten å love uavhengig review.
Oppsettene er lagret i `.github/main-protection.json` og
`.github/staging-protection.json`.

Installer lokalt tilleggvern:

```sh
git config core.hooksPath .githooks
```

Hooken avviser lokal push til main, sletting av staging og push til andre
remote-grener. Den kan omgås; GitHubs main-/staging-vern er serverhåndhevet.

## CI og sikkerhet

CI kjører ved push til main/staging, relevante PR-er og manuell start.
Kildejobben kontrollerer grenpolicy, hemmeligheter i hentet Git-historikk,
regnskaps-fixtures og modulpakken. Integrasjonsjobben tester en fersk Dolibarr
24.0.1 med låste containere, PHP-syntaks, modullivssyklus, hovedbok og webinnlogging.
Testmiljøene har egne midlertidige data og gjenbruker ikke utviklingsdatabasen.

Hemmelighetsskanning med push-beskyttelse, sårbarhetsvarsler og privat
sårbarhetsrapportering er aktivert. Actions har lesetilgang som standard,
kan ikke godkjenne PR-er og tillater kun GitHub-eide actions med SHA-låsing.
CI kjører ikke `pull_request_target` med kode fra bidragsytere.

Gitleaks 8.30.1, med kontrollert arkivhash, fant ingen hemmeligheter i undersøkt
historikk før offentliggjøring, inkludert erstattet PR. Samme kontroll kjøres i
CI. Skanning er ikke garanti for at all sensitiv informasjon blir oppdaget.

Automatiske oppdaterings-PR-er er deaktivert fordi de oppretter ekstra grener.
Sikkerhetsvarsler er fortsatt aktive. Oppdateringer vurderes og implementeres
manuelt på staging med kompatibilitetstesting; tags og digests endres bevisst.

Main er beholdt uendret ved herding og grenopprydding. Se SECURITY.md for
rapportering og skillet mellom repositorysikkerhet og sikker produksjonsdrift.

## Drift og videre utvikling

Installasjonskatalogen velges av administratoren. Standard Compose er
vedvarende drift; utviklingstester velges med `compose.test.yaml`. Oppdateringer
leveres som én blokk med kontrollert revisjon, privat backup, helsesjekk og
bevaring av data. Fremtidige skjemaendringer krever migrering og restore-prøve.
Ingen syntetiske bilag skal inn i driftsinstallasjonen. Stabil driftsinfrastruktur
kan promoteres uavhengig av uferdig norsk regnskapsfunksjonalitet, med tydelig
beskrivelse av støttet omfang og gjenværende begrensninger.
