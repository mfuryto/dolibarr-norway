# Norsk SAF-T 1.40

Oppdatert 2026-10-18. **0.83.0 leverer SAF-T for omfanget nedenfor** på staging. EHF avventer fortsatt faktisk testnettverkslevering.

## Vanlig arbeidsflyt

1. Fullfør vanlig faktura-, kjøps- og bankjournalføring for perioden i kjernen.
   Avstem bank, kunde-/leverandørposter og hovedbok.
2. Åpne **Regnskap → Oppsett → Kontoplan** ved behov for kontokoblinger.
   Standardvalg finnes for 357 vanlige kontoer med opprinnelig betydning i
   medfølgende kontoplan. Egne, omdøpte og tvetydige kontoer må ha eksplisitt
   `GroupingCategory` og `GroupingCode` for eksportåret. Valget endrer ikke
   kontonumre eller bokførte beløp. Manglende kobling stopper eksport med
   kontonummer; man trenger ikke klassifisere ubrukte kontoer.
3. Åpne kjernens **Regnskap → Hovedbok → Eksport**, velg fra-/til-dato for
   SAF-T og **Last ned SAF-T**. Knappen bruker hele perioden, også tidligere
   eksporterte bilag, uavhengig av kjernens listefiltre.
4. Oppbevar filen privat og lever den gjennom Skatteetatens aktuelle tjeneste
   ved forespørsel. Eksporten sender ikke filer og erstatter ikke MVA-melding,
   skattemelding eller næringsspesifikasjon.

Bare regnskapets lese-/eksportrettigheter kreves for nedlasting. Kontokoblinger
krever kjernens kontoplanrettighet. Eksterne brukere får ikke tilgang.
Eksporterende bruker må ha fornavn og etternavn på brukerkortet; dette blir
kontaktpersonen i filen. Foretaksnavn, organisasjonsnummer og land hentes fra
kjernens foretaksoppsett. Ingen egne kontakt- eller foretaksregistre opprettes.

## Støttet omfang og avgrensning

- Ett norsk foretak, NOK og perioder innen kalenderårene **2025–2026**.
  Formatet er 1.40. Gruppelistene for 2027 må kvalifiseres når de foreligger;
  programmet antar ikke at de er uendret.
- Bokførte vanlige kunde- og leverandørfakturaer og kreditnotaer, innenlandske
  25/15/12-prosentkoder, null-/fritaks-/utenfor-koder og tjenestekjøp fra
  utlandet med 25 prosent beregnet avgift. Fullt, delvis og intet fradrag
  følger registrerte linjevalg og faktisk bokføring.
- Bankbetalinger/refusjoner, enkle manuelle bilag, MVA-oppgjør og saldoer.
  En manuell MVA-kobling kan eksporteres når den er uendret og har én entydig
  grunnlagslinje. Historiske bilag med flere mulige grunnlag eller manuell
  omvendt avgiftsplikt er ikke kvalifisert. Normal utenlandsk kjøpsflyt fra
  0.79.0 bruker fakturalinjer og krever ikke slike rettingsbilag.
- Validerte, ikke journalførte fakturaer og ikke journalførte bankbevegelser
  stopper eksporten. Ubalanse, delte bilag ved datogrensene, tvetydige
  reskontrokoblinger, endret kildegrunnlag, manglende mapping og beløp med
  brøkdeler av øre stopper også filen. Ingen avrundingsdifferanse skjules.
- Vareimport, lønn, særordninger, andre valutaer, fakturatyper som depositum/
  situasjonsfaktura og avvikende regnskapsår inngår ikke i kvalifisert omfang.
  Eksporten skal stoppe på ukjent behandling fremfor å hoppe over postene.

## Hvordan avstemmingen fungerer

Én konsistent, skrivebeskyttet databasevisning gir Header, MasterFiles og
GeneralLedgerEntries. Hele bilag, opprinnelige bilags-/rad-ID-er, journal,
kildereferanser, datoer, betalingskoblinger og kontoer brukes. Det opprettes
ingen parallell hovedbok. Eksportdatoer, kildebilag og regnskap endres ikke.

Kjernens årlige `closure`-poster behandles som åpning, ikke ny omsetning.
En overført åpningssaldo erstatter eldre års summer; første dag skal ikke
både få åpningssaldo og samme post som bevegelse. Tidligere år uten korrekt
saldooverføring må først håndteres i kjernen. Dette kvalifiserer SAF-T-lesing
av teknisk saldoovergang, ikke den kommende norske årsavslutningsleveransen.

Kunder og leverandører får stabile separate ID-er fra native motparts-ID-er.
Samme foretak kan ha begge roller. Tidligere reskontrokoder beholdes på
linjene; faktura- og betalingskoblinger brukes ved navne-/kodeendringer.
Åpnings-/sluttsaldoer per motpart og hovedbokskonto beregnes fra samme native
poster. Tvetydig betalingsfordeling blir ikke gjettet fra kode eller beløp.

MVA-informasjon står på grunnlagslinjen. Den gjentas ikke på MVA-kontolinjen.
Kildelinjenes kontoer og beløp må avstemme med hele fakturabilaget, inkludert
fradragsandel og beregnet avgift. Delvis fradrag bruker grunnlag inklusive
ikke-fradratt avgift og faktisk fradratt MVA, med brukte `BaseRate`-verdier i
TaxTable. Omvendt avgiftsplikt viser netto avgift når begge retninger følger
samme grunnlagslinje; fullt fradrag gir null netto avgift.

Bankreferanser som er for lange for formatets 35-tegnsfelt erstattes der med
full stabil native kilde-ID. Den komplette lesbare referansen beholdes i
bilagets beskrivelse. Referanser kappes ikke slik at sporbarhet går tapt.

## Kilder og formatvalg

[Skatteetaten](https://www.skatteetaten.no/bedrift-og-organisasjon/starte-og-drive/rutiner-regnskap-og-kassasystem/saf-t-regnskap/dokumentasjon/)
tillater 1.40 nå og krever formatet fra 2027. XSD og kodelister er hentet fra
[offisielt repository](https://github.com/Skatteetaten/saf-t/tree/10c8fd82a6ff47e53385bc33c7638c5fde239a4f).
Revisjon og SHA-256 for hver originalfil står i modulens
`data/saft/manifest.json`. Originalfilene endres ikke. Kodelisten for
næringsspesifikasjon gjelder 2025–2026; 2027-gruppering blir ikke antatt lik.

SAF-T fra 2025 krever **GroupingCategory + GroupingCode**. To-/firesifret
standardkonto er ikke et alternativ i 1.30/1.40. Et likt nummer er ikke i seg
selv grunnlag for å koble kontoene. Eksempel: lokale MVA-kontoer 2700 og 2710
kan knyttes til `kortsiktigGjeld/2740`; 2700 er ikke en gyldig gruppekode her.
Dette er eksportklassifisering. Kontoplanen og bokførte kontonumre beholdes.

Arbeidsflyten sammenlignes med
[Fiken: eksport av regnskap for valgt periode](https://hjelp.fiken.no/tips-til-revisorer-hvordan-revidere-effektivt-i-fiken),
[Tripletex: SAF-T-eksport](https://hjelp.tripletex.no/hc/no/sections/21672753915025-Eksport)
og [Microsoft: norsk SAF-T og mapping](https://learn.microsoft.com/en-us/dynamics365/finance/localizations/norway/emea-nor-satndard-audit-file-for-tax).
Leverandørdokumentasjon er arbeidsflytinspirasjon, ikke rettslig grunnlag.

## Gjenbruk og konkret gap

Dolibarr 24.0.1 har hovedbok, journalføring, bank, reskontro, kontoplan og
regnskapseksportside, men ingen kvalifisert norsk SAF-T-generator ble funnet i
kjernen eller undersøkte åpne tillegg. Modulens generator fyller dette gapet.
Hooks legger periodevalget på native eksportside og gruppevalget i native
kontoplanoppsett. Ingen ny meny, kontrollside, bokføringsmotor eller
bekreftelsesrunde innføres.

Kjernens vanlige eksport kan utelate tidligere eksporterte rader, filtrere
kontoer og markere eksportdato. Disse mekanismene brukes ikke av SAF-T.
Å hente en komplett valgt periode er eksportens hovedvei.

## Aksept og prøvebevis

Akseptprøvene i denne revisjonen:

- `tests/saft-foundation.php`: låste originalreferanser, ugyldig XML/DTD,
  rettigheter, hele bilag ved datogrenser og uendret hovedbok.
- `tests/saft-export.php`: native saldooverføring, uavhengige åpnings-/slutt-
  saldoer, gjentatt eksport, omdøpt konto med eksplisitt mapping og årsgrenser.
- `tests/saft-cycle.php`: ordinær faktura og kreditnota, kjøp med delvis
  fradrag innenlands og utland, native betaling/refusjon og bankjournal,
  samme motpart i begge roller, reskontrosaldoer og norsk/engelsk nedlasting
  fra native side. XML valideres med låst offisielt XSD; beløpsfasiten kommer
  fra syntetiske dokumenter og bankutskrift, ikke bare fra generatorens summer.

Regnskapsprøvene er kun for isolerte syntetiske miljøer. Ferdigkriteriet er
format **og** kilde-/saldoavstemming. Samlet norsk regnskapsmessig
produksjonsaksept for hele modulen følger ikke av at SAF-T-etappen består.
