# Arbeidsregler for dolibarr-norway

- Repoet skal bare ha grenene `main` og `staging`, også ved fremtidig arbeid.
  Ikke opprett feature-, arbeids- eller automatiske oppdateringsgrener.
- `main` er stabil kanal. Ingen direkte push, force-push eller sletting.
- Utvikling skjer direkte på `staging`. Test relevante endringer før push.
  CI kjører på hver push; staging kan ha arbeid som ennå ikke er stabilt.
- Leveranse til main skjer med PR fra staging, etter beståtte CI-sjekker på
  aktuell revisjon og dokumentert aksept for leveransens støttede omfang.
  «Stabilt modulgrunnlag» er ikke det samme som norsk regnskapsmessig etterlevelse.
- Etter promotering synkroniseres staging med main uten force-push.
- Serververn på main krever PR og oppdaterte tester, også for administratorer.
  Staging tillater vanlig push, men force-push og sletting er sperret.
- Bruk `git config core.hooksPath .githooks` i en faktisk Git-checkout.
  Lokal hook sperrer main-push og oppretting av andre grener på remote.
- Følg prosjektets utviklingsprinsipper: relevante tester/dokumentasjon, syntetiske
  data, låste avhengigheter/images og minst mulige CI-rettigheter.
- Ingen reelle bilag, personopplysninger, passord eller eksportfiler i Git.
- Bevar ekstern modularkitektur under htdocs/custom; ikke endre kjernen.
- Bruk nøytrale prosjektnavn i grener, commit-metadata og dokumentasjon.
- Les docs/branch-workflow.md og SECURITY.md. Ikke omgå serververnet.
- Hold all dokumentasjon og alle eksempler generelle og gjenbrukbare.
  Ikke lagre brukeres domener, servernavn, installasjonsstier eller selskapsnavn.
- Lever sluttmeldinger om oppdateringer som én kjørbar oppdateringsblokk.
- Standard Compose gjelder vedvarende drift. Tester bruker compose.test.yaml.
  Bevar prosjektnavn, volumer, passord og instansnøkkel ved videre utvikling.
- Skill teknisk driftsstabilitet fra regnskapsfaglig produksjonsaksept.
  Skjemaendringer krever versjonert migrering, backup og gjenopprettingstest.

- Eventuelle lisensintegrasjoner skal være valgfrie, generelle adaptere uten
  leverandørnavn, personlige systemreferanser eller avhengighet fra regnskapskjernen.
- Bruk «kontroll» for validering av regnskapet; ikke omtale slike kontroller som sjekker.
