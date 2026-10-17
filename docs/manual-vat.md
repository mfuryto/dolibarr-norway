# MVA på manuelle bilag

Fra modul 0.68.0 kan avgiftskode og grunnlag registreres nederst på det
vanlige bilagskortet i Regnskap. Feltene vises på manuelle MVA-linjer når
satsregisteret har en entydig, aktiv norsk kode og tilhørende MVA-konto.
Det opprettes ingen egen meny, bokføringsmotor eller kontrollkopi.

## Bruk

1. Opprett og balanser bilaget med kjernens vanlige funksjon. Åpne det
   ferdige bilaget før perioden låses eller bilaget eksporteres.
2. Under **MVA på manuelt bilag** velger du kode på selve MVA-kontolinjen.
   Kontofeltet for riktig retning i satsregisteret må være utfylt.
3. Ved utgående MVA oppgir du grunnlaget i NOK med inntil to desimaler.
   Vanlig salg har positivt grunnlag; tilbakeføring har negativt grunnlag.
   Grunnlaget må samsvare med avgiften som allerede er bokført.
4. Ved inngående MVA lar du grunnlaget stå tomt. Avgiften er beløpet som
   allerede er bokført: debet øker fradraget, kredit reduserer fradraget.
   Dette kan brukes på et separat fradragsrettingsbilag som beskrevet i
   [oppsettsveiledningen](norwegian-setup-guide.md). Feltene beregner eller bokfører
   ikke fradragsandeler.
5. Lagre opplysningene ved linjen. Velg «Ingen» og lagre for å fjerne en
   feilregistrering mens bilaget fortsatt er redigerbart.

Støttede koder er 3/31/33 for utgående og 1/11/13 for inngående MVA,
ved henholdsvis 25/15/12 prosent. Salg og kjøp av både varer og tjenester
omfattes. Satser, kontoer og retning gjenbrukes fra kjernens satsregister;
modulen gjetter ikke avgiftsbehandling fra kontonummeret alene.

Dette er opplysninger for rapportgrunnlaget. **Et samlet norsk MVA-uttrekk
og innsending er ikke levert ennå.** Fritak, unntak, import/eksport og andre
særtilfeller skal ikke registreres med en ordinær kode for å få dem med.
Kjernens eksisterende fakturabaserte MVA-rapport inkluderer fortsatt ikke
disse manuelle bilagene.

## Tilgang, låsing og endring

Lesing krever intern bruker med tilgang til bokføring. Lagring krever i
tillegg opprettings-/endringsrett. Bilag utenfor en tillatt regnskapsperiode,
med låste eller eksporterte linjer kan ikke endres her. Feltene på låste
bilag kan fortsatt leses. Midlertidige bilag og fakturaoverførte linjer
endres ikke av funksjonen.

Endres den underliggende bilagslinjen, markeres tidligere MVA-opplysninger
som endret grunnlag. De skal ikke brukes som gyldige rapportopplysninger.
Åpne det redigerbare bilaget på nytt og registrer riktige verdier. En
foreldet nettleserfane kan ikke overskrive en nyere lagring uten omlasting.
Kopiering av et bilag kopierer ikke MVA-feltene automatisk.

## Lagring og oppgradering

Skjemaversjon 2 legger til `norway_manual_vat`, med én oppføring per
foretak/hovedbokslinje: kode, grunnlag for utgående MVA, kildefingeravtrykk,
bruker og tidspunkt. Regnskapsbeløp, fakturaer og gamle kontrollkopier
kopieres eller omskrives ikke. Ingen nye revisjonsarkiver opprettes.
Grunnlagskolonnen er teknisk 0 for inngående MVA og skal ikke tolkes som
anskaffelsens grunnlag i en senere eksport.

Bruk oppdateringsskriptet som tar backup før modulaktivering. Overgangen
fra skjema 1 til 2 er additiv, med lås, kontrollsummer og strukturkontroll.
Tapt tabell etter registrert migrering blir ikke automatisk opprettet tom.
Migreringsprøven tester overgang, avbrudd og tapte tabeller; driftsprøven
ødelegger og gjenoppretter de syntetiske lagringsmarkørene fra backup.

## Konkret gap og kilde

Prøvd Dolibarr 24.0.1 har ikke avgiftskode eller grunnlag på manuelle
hovedbokslinjer. `tax_by_rate` henter fakturaer/betalinger og utelater
manuelle MVA-bilag, dokumentert i `tests/core-vat-report.php`.
Fakturalinjenes eksisterende `vat_src_code` beholdes for fakturaer.

[Skatteetatens informasjonsmodell for MVA-meldingen](https://skatteetaten.github.io/mva-meldingen/mvameldingen/informasjonsmodell/)
beskriver kode og avgiftsbeløp, og grunnlag/sats for utgående MVA. Grunnlag
og sats fylles ikke ut for inngående MVA i meldingen. Lest 2026-10-17.
Dette begrunner de manglende feltene, ikke nye brukerbekreftelser eller
full norsk regnskapsmessig produksjonsaksept.
