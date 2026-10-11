# Stabil main og utvikling på staging

Opprettet 2026-10-11. Gjenbruker Fylkrens CI-prinsipper: tester på begge
langlivede grener, CODEOWNERS, SHA-låst checkout, ingen lagrede checkout-credentials
og CI med lesetilgang. Prosjektets teknologistakk og GPL-lisens beholdes.

## Grenflyt

`arbeidsgren → PR til staging → tester og aksept → PR fra staging til main`

- `main` er stabil kanal og fortsatt standardgren. Den oppdateres gjennom PR
  fra staging. Ingen direkte push, force-push eller sletting.
- `staging` er utviklings-/integrasjonsgren. Nye arbeidsgrener starter her.
  Utviklings-PR-er rettes hit. Den første modulgrunnlags-PR-en er flyttet hit.
- En PR til main må komme fra staging i samme repo; CI avviser andre kilder.
- Begge CI-jobber skal bestå på aktuell revisjon før stabilisering. Kontroller
  også leveransens relevante manuelle akseptansekriterier og dokumenter
  begrensninger. Grønn CI gjør ikke modulen klar for norsk produksjonsregnskap.
- Bruk merge-commit ved promotering staging → main for å bevare slektskapet.
  Synkroniser staging med main etterpå. Ikke slett staging etter merge.
- Ingen automatisk produksjonsutrulling eller automatisk promotering til main.

## CI

`Quality` kjører ved push til main/staging, PR til disse grenene og manuell start.
Sjekkene heter `Norway source and policy` og `Norway Dolibarr integration`.
Den første tester grenpolicy, regnskaps-fixtures og modulpakken. Den andre
installerer en fersk Dolibarr 24.0.1 med låste containere og kjører PHP-syntaks,
modullivssyklus, hovedbokavstemming og HTTP-innlogging/statusside.

Integrasjonsskriptet bruker eget midlertidig område og unikt Compose-prosjekt,
og rydder bare egne containere/volumer. Det gjenbruker ikke utviklingsdatabasen
eller dens passord. CI har ingen skrivetilgang til repoet og ingen egne secrets.

## Faktisk vern og begrensninger

GitHub svarte HTTP 403 ved kontroll av regler/grenvern 2026-10-11:
«Upgrade to GitHub Pro or make this repository public to enable this feature.»
Repoet skal forbli privat. Serverstyrt grenvern kunne derfor ikke aktiveres på
nåværende abonnement. Dette er en GitHub-planbegrensning, ikke en CI-feil.
[GitHubs dokumentasjon](https://docs.github.com/en/repositories/configuring-branches-and-merges-in-your-repository/managing-protected-branches/about-protected-branches)
beskriver hvilke abonnementer som støtter vern av private repoer.

I mellomtiden gjelder arbeidsreglene i `AGENTS.md`. Installer lokal sperre i
hver faktisk Git-checkout:

```sh
git config core.hooksPath .githooks
```

Hooken avviser enhver lokal push til main og sletting av staging. Den kan omgås
og beskytter ikke endringer via GitHub, andre checkouter eller API. CODEOWNERS og
CI stopper heller ikke merge uten serverregler. Main er derfor **ikke fullt
serverbeskyttet** før abonnementsbegrensningen er løst og oppsettet verifisert.

Ønsket oppsett ligger i `.github/main-protection.json`: PR kreves, alle samtaler
må være løst, begge oppdaterte tester må bestå, administratorer omfattes, og
force-push/sletting er forbudt. Ingen ekstra godkjenner kreves fordi repoet har
én identifisert eier; CODEOWNERS synliggjør eierskap. Krav om en uavhengig
reviewer kan legges til når en slik person finnes.

Etter at privat grenvern er tilgjengelig, kjør fra repoets rot:

```sh
gh api --method PUT repos/mfuryto/dolibarr-norway/branches/main/protection --input .github/main-protection.json
gh api repos/mfuryto/dolibarr-norway/branches/main/protection
```

Kontroller faktisk svar. Ikke anta at et lagret JSON-oppsett er aktivt vern.
