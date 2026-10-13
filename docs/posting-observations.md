# Kildeobservasjoner ved nye salgsposteringer

Fra 0.44.0, videre arbeid under A2/KR05/KR08/KR09. Dette er bevaring av rå
kildeopplysninger ved en støttet bokføringshendelse, ikke kvalifisert
historisk MVA-behandling eller en ferdig perioderapport.

## Bruk av kjernen og ansvar

Dolibarr har allerede salgsjournal, hovedbok og transaksjonell opprettelseshendelse.
Disse gjenbrukes. Det konkrete gapet er at hovedboksposten ikke selv bevarer
fakturalinjenes kildeopplysninger ved bokføring; modulen tilføyer bare denne
observasjonen i eksisterende lagring. Ingen alternativ bokføringsflyt innføres.

Den som bokfører, bruker vanlig overføring fra salgsjournal til hovedbok.
Lagringen skjer automatisk og krever ikke besøk på modulens kontrollside.
Hvis lagringen feiler, stopper bokføringen med beskjed om at administrator må
kontrollere lagringen og rette årsaken før nytt forsøk. Administrator kan bruke
fakturakontrollen til feilsøking. Denne valgfrie visningen er ikke en nødvendig
manuell rutine ved hver postering eller en godkjenning av avgiftsbehandlingen.

## Hendelsen og lagringen

Dolibarr 24.0.1 utløser `BOOKKEEPING_CREATE` fra `BookKeeping::create()` og
`createStd()` før kjernens transaksjon avsluttes. Den norske modulen observerer
nye hovedboksposter med `doc_type=customer_invoice` i aktivt foretak.
Den leser den faktisk lagrede hovedboksposten og krever samsvar med
hendelsens dokumentreferanse, dokument-ID og kontonummer før lagring.
Midlertidige eller ubeviste opprettelsesveier kvalifiseres ikke som generell
støtte for hovedbokshistorikk.

Observasjonen inneholder postens dato, referanser, konto, reskontro, beløp,
opprettelsesopplysninger, fakturahode, lagrede linje-/avgifts-/kontofelt,
foretakets lagrede identitets- og registreringsopplysninger, rå lagret
avgiftsmapping og tilgjengelige motpartsopplysninger. Opplysningene gjelder
hendelsestidspunktet; de beviser ikke at dokumentet ble utstedt med samme
opplysninger tidligere. Effektive miljøoverstyringer er ikke rekonstruert
fra de lagrede innstillingene. Mappingen er rå oppsettsdata, ikke godkjent
avgiftsbehandling eller en automatisk valgt rapporteringskode.

Lagring skjer i kjernens eksisterende transaksjon. Modulen starter eller
avslutter ikke denne. Feil i hendelseslagringen returneres som triggerfeil,
slik at kjernen ruller posteringen tilbake. Det er derfor en bevisst
atferdsendring: støttede nye salgsposteringer skal ikke fullføres uten at
observasjonen kan bevares. Hele bilagets transaksjonsgrense eies fortsatt
av den aktuelle kjernearbeidsflyten.

Det eksisterende versjonerte lagringsgrunnlaget brukes uten DDL-endring:
`norway_tax_evidence`, `document_type=bookkeeping_event`, `fk_document` lik
hovedboks-ID, `revision=1`, `mapping_revision=0`. Payloaden har eget `kind`
og `version=1`. Dette formatet er separat fra manuelle kontrollkopier med
`document_type=customer_invoice`; ingen eldre payload omskrives.

SHA-256, identitetskontroller og en unik hendelsesnøkkel beskytter mot
utilsiktet skade og duplikater. Modulen overskriver ikke en observasjon.
Dette er ikke digital signatur, databasevern mot administrator eller et
fullstendig append-only system på tvers av alle kjerne-/importveier.

## Visning og sammenligning

Fakturakontroll → hovedboksposter viser status per observert post. Det kreves
administrator og faktura-/regnskapsleserettighet. Aktive kildeposter og
bevarte observasjoner for fakturaen leses fra én skrivebeskyttet konsistent
transaksjon. En allerede aktiv transaksjon avvises.

- **Ingen hendelse:** ingen støttet opprettelsesobservasjon finnes. Eldre
  bilag etterfylles ikke; SQL-import eller kall uten triggere kan også mangle.
- **Uendret:** de observerte kildefeltene samsvarer med dagens kilder.
- **Endret:** minst ett observert kildefelt er endret. Også legitime senere
  endringer i motpart, konto eller oppsett kan gi denne statusen.
- **Fjernet:** posten finnes ikke lenger i dagens kildetilknyttede hovedbok;
  den lagrede observasjonen beholdes.
- **Ufullstendig:** faktura, motpart eller linjegrunnlag manglet eller
  oversteg capture-grensen. Dette er ikke fullstendig historisk grunnlag.

Bevarte linjebeløp, koder og kontonumre kan åpnes separat fra dagens data.
Readeren krever fortsatt at den aktuelle fakturaen finnes. Lagring av et
arkiv er ikke i seg selv en komplett visning/eksport av slettede dokumenter.

## Grenser og neste arbeid

Maksimalt 200 linjer bevares per observasjon. Større grunnlag merkes
ufullstendig; prosentsatser, manglende opplysninger og fradrag blir ikke
utledet eller fylt inn. Payloadgrensen er 1 MB. Rapporten er avgrenset til
200 aktuelle/bevarte hovedboks-ID-er og 5 MB payload; avkorting eller skadet
lagring gir ikke godkjent resultat. Oppbevaring av finansielle kilder er
regnskapsdata i driftsdatabasen, aldri materiale som skal legges i Git.

Hendelsene dekker ikke kjøp, manuelle bilag, alle importveier, korreksjons-
hendelser eller opprinnelig dokumentutstedelse. Endringer/sletting oppdages
ved sammenligning, men tidspunkt og aktør for hver senere endring er ikke
registrert av denne leveransen. Kontoplan-/avgiftsmapping, registrerings-
perioder, fradragsrett, komplette avgiftssummer og rapportaksept gjenstår.

Neste del under A2 må kvalifisere sammenhengen mellom disse hendelsene,
faktiske posteringer og avgiftsbehandling før data brukes i B1/B2.
Kontrollspor vurderes mot
[NBS 2](https://www.regnskapsstiftelsen.no/bokforing/bokforingsstandarder/nbs-2-kontrollsporet/);
denne leveransen oppfyller ikke alene hele standarden.
