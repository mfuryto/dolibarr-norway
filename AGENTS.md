# Arbeidsregler for dolibarr-norway

- `main` er stabil kanal. Ikke push direkte til `main`, force-push eller slett
  `main`/`staging`. Ikke flytt utviklingskode til main bare for å rydde grener.
- `staging` er integrasjonsgren for utvikling. Opprett korte arbeidsgrener fra
  staging og rett utviklings-PR-er mot staging. Oppdater staging etter relevante
  tester; hovedoppgaven må fortsatt være innenfor brukerens bestilling.
- Leveranse til main skjer med PR fra staging, først etter beståtte CI-sjekker
  på aktuell revisjon og dokumentert aksept for leveransens støttede omfang.
  «Stabilt modulgrunnlag» er ikke det samme som norsk regnskapsmessig etterlevelse.
- Gjenbruk Fylkrens prinsipper: tester og dokumentasjon følger endringen,
  syntetiske testdata, låste avhengigheter/images og minst mulige CI-rettigheter.
  Ikke kopier Fylkrens produktspesifikke kode eller lisensvilkår.
- Kjør `git config core.hooksPath .githooks` i en faktisk Git-checkout.
  Lokal pre-push-hook sperrer push til main. GitHub-planen blokkerte serverstyrt
  grenvern ved kontroll 2026-10-11; ikke omtale CI/hook som full grenbeskyttelse.
- Ingen virkelige bilag, personopplysninger, passord eller eksportfiler i Git.
- Bevar ekstern modularkitektur under htdocs/custom. Ikke endre Dolibarr-kjernen.
- Les docs/branch-workflow.md for grenflyt og kontroll av serververn.
- Bruk nøytrale prosjektnavn i grener, commit-metadata og dokumentasjon.
