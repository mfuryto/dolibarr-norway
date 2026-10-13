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
og opprinnelig `version=1` (versjon 2 fra 0.45.0, beskrevet nedenfor). Dette formatet er separat fra manuelle kontrollkopier med
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

Den opprinnelige salgsleveransen dekker ikke kjøp (rå kjøpshendelser tilføyes
fra 0.48.0, se nedenfor), manuelle bilag, alle importveier, korreksjons-
hendelser eller opprinnelig dokumentutstedelse. Endringer/sletting oppdages
ved sammenligning, men tidspunkt og aktør for hver senere endring er ikke
registrert av denne leveransen. Kontoplan-/avgiftsmapping, registrerings-
perioder, fradragsrett, komplette avgiftssummer og rapportaksept gjenstår.

Neste del under A2 må kvalifisere sammenhengen mellom disse hendelsene,
faktiske posteringer og avgiftsbehandling før data brukes i B1/B2.
Kontrollspor vurderes mot
[NBS 2](https://www.regnskapsstiftelsen.no/bokforing/bokforingsstandarder/nbs-2-kontrollsporet/);
denne leveransen oppfyller ikke alene hele standarden.

## Bevart kodekobling fra 0.45.0

Nye observasjoner bruker payloadversjon 2 med `tax_context.version=1`.
Kjernen bruker allerede `vat_src_code` i salgsjournalens avgiftsgrunnlag, men
bevarer ikke modulens daterte kodeoppsett på hovedbokshendelsen. Utvidelsen
løser dette avgrensede gapet i samme automatiske hendelse. Ingen ny
arbeidsflyt, oppsettskrav eller manuell godkjenning innføres.

Koblingen beregnes bare fra kildeopplysningene som faktisk bevares i
hendelsen: fakturadato, eksakt lokal linjekode og aktivt foretaks daterte
salgskoblinger. Referanse, organisasjonsnummer, land, hele oppsettshistorikken
og entydig datointervall kontrolleres. Foretak 0 brukes ikke som reserve.
Sats, konto, beløp og land på motparten velger aldri en SAF-T-kode. Heller
ikke en lokal kode som allerede ser ut som en SAF-T-kode godkjennes uten
uttrykkelig kobling. Særmarkører/lokale avgifter forblir uavklart.

Resultatet bevarer mål, koblings-ID, koblingsversjon og oppsettets samlede
versjon per linje. Ufullstendig grunnlag, ukjent dato/identitet, manglende kode,
korrupt oppsett eller manglende entydig salgskobling gir uavklart resultat.
Dette stopper ikke bokføringen: rådata bevares, og avgiftsmessig rapportering
må håndtere avviket før godkjenning. Lagringsfeil stopper fortsatt posteringen.

Fakturakontrollen viser den bevarte koblingen sammen med kildeopplysningene.
Administrator eller rapporteringsansvarlig bruker dette ved utredning av
uavklart rapportgrunnlag; vanlig bokføring krever ingen ekstra kontrollrunde.
En senere retting av dagens kodeoppsett endrer ikke den bevarte koblingen.
Eldre versjon 1 leses fortsatt, med uttrykkelig beskjed om manglende bevart
kobling; dagens oppsett etterfylles ikke. Ingen eldre payload omskrives, og
ingen skjemaendring gjennomføres.

`tax_context.version=1` angir bare en teknisk oppsettskobling, ikke juridisk
avgiftsklassifisering eller rapportkode for MVA-meldingen. Den bekrefter ikke
riktig sats, registreringsperiode, fradrag, fritak, korrekt hovedboksfordeling
eller komplett kontrollspor. Fremtidig endring av tolkningsreglene må få ny
formatversjon og bevare lesing av denne versjonen. Kodegrunnlaget er den
[låste offisielle SAF-T-referansen](https://github.com/Skatteetaten/saf-t/blob/05179521e435d82feb0b2d6c89a92a32a4f2d02f/Standard%20Tax%20Codes/CSV/Standard_Tax_Codes.csv).
Skatteetaten publiserer [dokumentasjon for mapping](https://www.skatteetaten.no/bedrift-og-organisasjon/starte-og-drive/rutiner-regnskap-og-kassasystem/saf-t-regnskap/dokumentasjon/);
SAF-T-kobling alene er ikke aksept for MVA-rapportering.

## Bevart registreringssammenligning fra 0.46.0

Nye payloads bruker versjon 3 og `registration_context.version=1` ved siden
av den uendrede avgiftskodekoblingen. Fakturadato sammenlignes med bevart,
identitetsbundet virkningsdato for aktivt foretak. Dato, bekreftelsestid og
bekreftende bruker følger sammenligningen. Kreditnota markeres særskilt;
kildedokumentets historiske registreringsstatus utledes ikke fra dette.

Manglende/ugyldig grunnlag, annen identitet, duplikate oppsettsrader eller
ufullstendige kilder gir ikke en positiv datosammenligning. Foretak 0 er
ingen reserve. På/etter-datoen er bare en sammenligning, ikke en godkjenning
av MVA eller bevis for fortsatt registrering. Avregistrering og flere perioder
krever egen historikk som ennå ikke støttes. Sammenligningen stopper ikke
bokføring; lagringsfeil håndteres fortsatt atomisk.

Payloadversjon 1 og 2 leses uten at dagens registreringsoppsett etterfylles.
Visningen merker eldre observasjoner uten bevart sammenligning. Det brukes
samme tabell og samme transaksjon; ingen skjemaendring gjennomføres.
Kjernens fakturavalidering gjenbrukes til [handlingsrettede varsler](vat-registration.md),
og den valgfrie kontrollsiden er fortsatt diagnose, ikke et ekstra steg for
hver faktura. Nye tolkningsregler må versjoneres uten å endre denne historikken.

## Kildebevaring for kjøp fra 0.48.0

Dolibarr 24.0.1 har allerede kjøpsjournal og transaksjonell
BOOKKEEPING_CREATE for leverandørfakturaer. Det konkrete gapet er at denne
hendelsen tidligere ikke ble bevart av modulen. Samme trigger, lagring,
integritetskontroll og transaksjon gjenbrukes nå for `supplier_invoice`.
Den som bokfører, bruker vanlig overføring fra kjøpsjournal til hovedbok.
Ingen ekstra opplasting eller manuell kontrollrunde innføres.

Payloadversjon 4 gjelder bare kjøp. Den bevarer fakturahode,
leverandørreferanse, kreditnotakilde-ID, reverseringsmarkør, valuta- og
beløpsfelt, motpart, linjer, avgiftsfelt og lagrede kjøpskontokoblinger.
Kjernens kjøpslinjefelt `tva` bevares som `total_tva` i det felles formatet;
`fk_facture_fourn` beholdes også ved siden av felles `fk_facture`.
Ufullstendig grunnlag og de eksisterende kapasitetsgrensene merkes som før.
Valutafelt er rå lagrede opplysninger, ingen kursintegrasjon eller beregning.

`tax_context` og `registration_context` er uttrykkelig null for kjøp.
Salgsoppsett og selgerens virkningsdato kan ikke godkjenne fradrag eller
avgiftsbehandling av en leverandørfaktura. Datert kjøpskodekobling,
fradragsomfang, hovedboksfordeling, utenlandskjøp og korreksjonsregler må
kvalifiseres videre før rapportering. Rådata om slike forhold er ikke støtte
for korrekt norsk avgiftsbehandling i disse scenarioene.

Diagnostikkleseren kan hente kjøpsobservasjoner med eksplisitt dokumenttype
og krever administrator, leserett for leverandørfaktura og hovedbok i aktivt
foretak. Denne leveransen oppretter ingen ny diagnose-/bilagsside. Kjøp og
salg filtreres separat både i hovedbok og arkiv, også ved like numeriske
faktura-ID-er. Fjernede hovedboksposter beholder observasjonen. Skadet
lagring avvises; administratorendringer er ikke kryptografisk forhindret.
Salg beholder payloadversjon 3; versjon 1–3 leses som før uten omskriving.
Ingen tabell- eller skjemaendring innføres.

Lagringsfeil stopper også støttede kjøpsposteringer med kjernens
handlingsrettede feilmelding. Administrator undersøker lagringen og retter
årsaken før nytt forsøk. Tester dekker reell kjøpsjournal, leverandørkreditnota,
gjentatt overføring, rollback, lagringsfeil, kildeendring, slettet post,
korrupsjon, dokumenttype- og foretaksisolasjon. Papirløs bilagsflyt og
matching er flyttet til slutt og inngår ikke i denne leveransen.
