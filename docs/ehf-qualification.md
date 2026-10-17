# Utgående EHF: kvalifisering av åpen løsning

Utviklingsversjon 0.81.0, kontrollert 2026-10-17. **EHF-steget er åpent.**
Dokumentgenerering og simulert transport er prøvd. Faktisk levering i separat
Peppol-testnettverk, mottak av faktura/kreditnota og transportkvittering gjenstår.
Produksjonssending er sperret i koden. Ingen aksesspunktavtale er opprettet.

## Gap og valg

Dolibarr 24.0.1 har vanlig faktura, kreditnota, kundereferanse, bankkonto,
betaling og bokføring. Disse gjenbrukes. De undersøkte åpne EHF-tilleggene
har feil som enten mister kildebeløp/referanser eller ikke lager gyldig kreditnota.
Brukeren valgte tilpasning av åpen løsning uten modulavgift.

| Undersøkt kilde | Revisjon | Konkrete funn med syntetiske native dokumenter |
|---|---|---|
| [marcrant1/dolibarr-peppol-export](https://github.com/marcrant1/dolibarr-peppol-export) | `c8d965b1cfc4080880e4a9b51439e4f1a39eceff` | Belgiske endepunkter som standard; kreditnota bryter skjema/regler; desimalpris bryter R120. |
| [doomzlight/dolibarr-peppol-export](https://github.com/doomzlight/dolibarr-peppol-export) | `506cc2ac8d785acbab6475c60697ad7feba769b3` | Bedre kreditnota og kvalifiserte endepunkter. Mangler opprinnelig faktura, finner på kjøperreferanse og avrunder enhetspris til to desimaler. Valgt som generatorgrunnlag. |
| [cedric-marcoux/dolibarr_peppol-peppyrus](https://github.com/cedric-marcoux/dolibarr_peppol-peppyrus) | `cede93ae75730d56cbb472e3e2cf6f896b9d54d9` | Dokumenter består formatreglene, men prøven på 0,123456 × 100 får 15,00 i stedet for kjernens 15,43. Kreditnotaen som skal reversere en positiv faktura får feil fortegn. Kjøperreferanse erstattes med plassholder. |

Generatoren er tilpasset inne i den eksterne Norway-modulen. Lisens og
opprinnelig opphavsrett er bevart i `licenses/peppol-export-LICENSE.txt` og
kildehodet. Det innføres ingen ekstra fakturamodell, regnskapsmotor,
kontrollside eller meny. Tilleggenes usikre web-/sendebehandlere er ikke kopiert.

Som arbeidsflytinspirasjon er [Fikens EHF-beskrivelse](https://blogg.fiken.no/hva-er-ehf-og-hvem-kan-sende-og-motta-ehf-faktura/)
og [Tripletex om kundens kontraktreferanse](https://www.tripletex.no/produktnyheter/nytt-felt-for-kontraktreferanse-pa-ehf-faktura/)
undersøkt: Brukeren lager vanlig faktura og velger sending fra fakturaen;
mottakerens referanser følger dokumentet. Dette er ikke kilder til lovkrav.

## Dokumenter og begrensninger

- Validerte ordinære NOK-fakturaer og kreditnotaer, vanlige varer/tjenester,
  bankoverføring, reell kjøperreferanse i kjernens **Kundereferanse**.
  Alternativt brukes kundens faktiske ordrenummer fra én entydig koblet
  native kundeordre. Eget internt ordrenummer brukes aldri som erstatning.
  Kontraktreferanse er ikke koblet ennå.
- Opprinnelig faktura hentes fra kjernens kreditnotakobling. Ved overgang fra
  annet system brukes den eksisterende eksterne originalreferansen (`ref_ext`).
- Pris med opptil åtte desimaler bevares. Linjerabatt og negative linjer
  representeres uten å endre fakturaen. Native linje-/MVA-/totalsummer
  sammenlignes; avvik gir stopp, ikke automatisk reprising.
- Leveringsperiode fra kjernens linjedatoer følger EHF-linjen uten at datoer
  finnes på. Sendeforsøk beskytter dokumentet mot ny utkaststatus, endring og
  sletting, også når tilgangen senere slås av. Betaling og kreditnota beholdes.
- Norske endepunkter bruker `0192` og organisasjonsnummer uten `NO`/`MVA`.
  Avsender må fortsatt være registrert/autorisert hos aksesspunktet, og mottaker
  må støtte dokumenttypen. Dette kan ikke bevises av organisasjonsnummeret alene.
- Norske salgskoder `3/31/33 → S`, `5 → Z`, `6 → E`, `7 → O`, `52 → G`.
  Sats og kode må stemme. O kan ikke blandes med andre kategorier og gir ingen
  VAT-identifikatorer. E/O/G får forklarende fritakstekst. Eksport krever
  utenlandsk kunde; norsk eksportkode er ikke automatisk EUs omvendte avgiftsplikt.
- Generatoren kan få en eksplisitt utenlandsk EAS-identifikator. Den gjetter
  ikke registreringen fra utenlandsk MVA-nummer. Lagret mottakeroppsett og
  faktisk utenlandsk levering er fortsatt åpne kvalifiseringspunkter.
- Native timer, antall, sett og vanlige metriske enheter får tilhørende
  EHF-enhetskode. Ukjente/tvetydige enheter avvises uten konvertering.
- Fakturaens valgte bankkonto brukes, med kjernens standard fakturakonto som
  reserve når ingen er valgt. Ingen vilkårlig første bankkonto velges.
- Delbetaling, betaling og bokføring håndteres av kjernen. EHF-testen endrer
  ingen av disse. Delbetaling er ikke dokumentert som ferdig kvalifisert EHF-flyt.

Regelgrunnlag: [norsk EHF-veiledning, MVA-kategorier](https://anskaffelser.dev/postaward/g3/spec/current/billing-3.0/norway/),
[Peppol BIS Billing](https://docs.peppol.eu/poacc/billing/3.0/bis/),
[gjeldende valideringsregler](https://docs.peppol.eu/poacc/billing/3.0/rules/ubl-peppol/).
Kreditnota med kode 381 reverserer en positiv faktura med positive dokumentbeløp;
negative kreditnotaer har en annen betydning. Det er ikke nok at XML er gyldig.

## Aksesspunkt og pris

[Peppyrus](https://peppyrus.be/en) er valgt som første kandidat fordi de åpne
løsningene allerede bruker API-et og tjenesten har et separat testnettverk.
[Tigron BV står på OpenPeppols liste over sertifiserte tjenesteleverandører](https://peppol.org/members/peppol-certified-service-providers/).
Dette er ingen godkjenning av prosjektets integrasjon.

Leverandørens publiserte Free-plan, med oppgitt virkning fra 1. november 2026,
omfatter 100 utgående meldinger per måned og 200 MiB lagring. Pris/vilkår må
kontrolleres ved avtaleinngåelse; ingen modulavgift er ikke et løfte om gratis
aksesspunktbruk for ethvert volum. Prosjektet er ikke avhengig av et betalt tillegg.

[Testkonto](https://customer.test.peppyrus.be),
[test-API-kontrakt](https://api.test.peppyrus.be/v1/openapi.json).
Testmiljø og produksjon har separate nøkler. Testkoden bruker bare den faste
HTTPS-verten `api.test.peppyrus.be`, uten omdirigering eller produksjonsnøkkel.

## Lokal prøving og private nøkler

Bruk bare prosjektets isolerte `compose.test.yaml` med syntetiske data.
Opprett miljøet som beskrevet i utviklingsdokumentasjonen. Ikke aktiver
EHF-testen i installasjonen som inneholder virkelige regnskapsdata.

Etter opprettelse av testkonto og autorisering av syntetisk testavsender/-mottaker:

```sh
python3 scripts/configure-ehf-test.py
docker compose -f compose.test.yaml up -d --no-deps --force-recreate web
```

Skriptet leser nøkkelen skjult og bevarer de eksisterende testpassordene i
privat `.env` med filmodus 0600. Ingen nøkkel skal sendes i chat, legges i
Git eller vises med `docker compose config`. Fakturasiden viser testvarsel,
**Send EHF i testnettverket** og **Hent leveringsstatus** når dette er konfigurert.
Det opprettes ingen produksjonssending og ingen automatisk sending.

Dokument og sendestatus lagres i den eksisterende dokumentlagringen under
`.norway-ehf-test/<native faktura-ID>`. ID brukes slik at endret fakturareferanse
ikke nullstiller duplikatvernet. Dokumentlagringen inngår i ordinær backup.
Ingen databasetabell eller skjemamigrering innføres.

Forsøk lagres før HTTP-kallet og låses mot samtidige klikk. 401/422 behandles
som avvist forespørsel. Tidsavbrudd, uleselig svar, 5xx eller svar uten meldings-ID
blir ukjent resultat; omstart eller nytt klikk sender ikke på nytt. Avklar slike
forsøk med aksesspunktet. Et HTTP-svar med meldings-ID betyr mottatt hos
aksesspunktet, ikke levert. Status hentes separat. «Sendt» er transportstatus,
ikke kjøpers godkjenning eller betaling. Det innføres ingen offentlig webhook.

## Gjentakbare prøver

`tests/ehf-document.php` bruker native syntetiske dokumenter og prøver beløp,
referanser, avgiftskategorier, presisjon, rabatt, negativ linje, manglende
referanse, feil konto, ukjent kode, utkast og feil foretak. Samme prøve
simulerer mottak, avvisning, uklart svar, tidsavbrudd og samtidige klikk.
Den har ingen nettverkskall.

Integrasjons-CI henter disse syntetiske XML-filene fra engangscontaineren og
kjører `scripts/validate-ehf.py`. Verktøyene er låst med versjon og wheel-hasher;
XSD, Schematron-kompilator og offisielle regler kontrolleres mot manifestets
SHA-256. Regelsettet er Peppol BIS Billing May 2026, obligatorisk 17. august
2026. Endrede publiserte filer krever bevisst oppdatering av manifestet.
Verktøyene installeres ikke i applikasjonsbildet. Eksportfiler committes ikke.

En utvikler kan gjenta formatprøven med en isolert Python-venv og argumentene
`--cache` og `--documents` til `scripts/validate-ehf.py`. `tests/ehf-candidate-probe.php`
er en separat valgfri undersøkelse av de oppgitte tredjepartsrevisjonene uten
modulaktivering/sending; nedlastet kilde ligger utenfor webroten og inngår ikke i CI.

Før steget lukkes gjenstår: reell testnettverkslevering av faktura og kreditnota,
bekreftet mottaker/dokumentstøtte, avvisning og transportkvittering, eventuell utenlandsk mottaker og øvrige avtalte normalflyter samt en dokumentert beslutning
om produksjonsaktivering. SAF-T starter ikke mens dette står åpent.
