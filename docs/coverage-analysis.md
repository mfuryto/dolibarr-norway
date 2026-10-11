# Teknisk dekningsanalyse

Vurdert 2026-10-11. Status: første kildekontroll og kjørbart modulgrunnlag, ikke
bekreftelse på norsk etterlevelse. Kildereferanser S1–S12 finnes i
[kilderegisteret](sources.md). Testbevis og begrensninger står i
[test- og driftsdokumentet](test-and-operations.md).

## Versjonsvalg og metode

Velg **Dolibarr 24.0.1**, siste stabile GitHub-utgivelse ved kontrollen, publisert
2026-09-07 (S1). Kildecommit `b7958385f00a92219f76a4dfea67dae93df97a25`.
Ikke bruk den bevegelige grenen `24.0` eller `develop` som støtteavtale.
Modul 0.1.0 avviser aktivering på andre versjoner inntil regresjonstesting er gjort.
Kjernen har PHP 7.2 som historisk minimum; vårt grunnlag krever PHP 8.2 og
containerens faktiske PHP-versjon registreres ved test. MariaDB 10.11 brukes.

Repoet hadde bare README og GPLv3-lisens; ingen tidligere modul eller regnskapsdata.
Kontrollen omfatter SQL-skjema, installasjonsdata, fakturaklasse, regnskapsklasse,
modulmal, eksportkataloger og Skatteetatens XSD 1.40. Standard kjøring og modulens
livssyklus er testet i Docker. Faktura→bokføring, PDF, avslutning, EHF og SAF-T er
ikke ferdig funksjonstestet. Funn fra fraværssøk er avgrenset til undersøkt kode.

## Omfang og installasjonsspesifikke avklaringer

Første testprofil dekker ett syntetisk foretak, NOK som basisvaluta og norske
transaksjoner før og etter MVA-registrering. Programvaresalg, utenlandshandel,
EUR og offentlige kunder er relevante utvidede scenarioer. Støtte må verifiseres
for konkrete leveringsmodeller og transaksjoner før produksjonsbruk.

Hver installasjon må avklare faktisk MVA-registerstatus og virkningsdato,
land, vare-/tjenestetype, leveringsmodell, kundetype, ansatte og EHF-behov.
Registrering og eventuell forhåndsregistrering følger egne vilkår og kan ikke
utledes av første faktura (S8). Før/etter registrering er separate testprofiler.

## Dekningsmatrise

| Område | Standard / nødvendig oppsett | Norsk modul / datagap | Før produksjon |
|---|---|---|---|
| Kunder, leverandører, produkter, priser, tilbud, ordre | Standard moduler; land NO, NOK som basis, norske språk/ID-felt | ID-validering og frys av relevante historiske opplysninger | Org.nr., adresser og kunde-/leverandørreskontro kontrolleres |
| Faktura og kreditnota | Standard faktura, nummermodeller, kildefaktura og PDF | Kontroller ved validering; eventuelt PDF-utvidelse for manglende norske opplysninger | Maskinell nummerering, leveringssted/-tid, MVA-tekst, avgift i NOK, kreditreferanse (S7) |
| Bank og betaling | Standard bank, betaling og fakturakoblinger | Avstemmingsrapport mot reskontro/hovedbok | Delbetaling, refusjon, gebyr og kursdifferanse testes |
| Dobbelt bokholderi | Advanced Accounting, journaler, kontoer, hovedbok og saldo | Ikke ny regnskapsmotor | Kontroller bokføringsdato, godkjenning og bilagsnøkler |
| Norsk kontoplan | Kontoimport og kontokoblinger finnes | Eget avgrenset kontoutvalg, oppsett, versjonert næringsspesifikasjonsmapping | Ikke kopier SAF-T-standardkontolisten som kontoplan (S11) |
| MVA-satser og koder | `c_tva` har sats, kode og kjøp-/salgskonto | Gyldighetsperioder, norsk behandling og historisk registreringsstatus | Sats er ikke avgiftskode; 0 %/fritak/unntak/uregistrert skilles |
| MVA-grunnlag | Fakturalinjer har netto, avgift, kode og dokumentlenke | Sporbar rapport fra bokført grunnlag inkl. manuelle bilag, korreksjoner og fradragsandel | Avstem både grunnlag og avgiftskontoer; betalingsdato brukes ikke ukritisk |
| SAF-T Financial | Generelle regnskapseksporter finnes | Norsk eksport, mapping, metadata, saldokontroller, datavalidering | Mål 1.40; XSD alene er utilstrekkelig (S5–S6) |
| Valuta og utenlandshandel | Multicurrency og utenlandske motparter finnes | Kurskilde/-dato, NOK-MVA og norsk avgiftsbehandling må sikres | Test EUR-oppgjør, agio/disagio og aktuelle import-/eksporttyper |
| Offentlige kunder | Faktura/ordre kan gjenbrukes | EHF/Peppol-tillegg og tilgangspunkt må kvalifiseres | En PDF er ikke EHF; mottakerkrav må avklares (S7, S10) |
| Spesifikasjoner og avslutning | Journal, hovedbok, reskontro, saldo og regnskapsperioder finnes | Norsk rapportdekning og rutiner kontrolleres; avvik utvides | Dokumenter hvilke lovpålagte spesifikasjoner hver rapport dekker |
| Retting og oppbevaring | Validering, låsing og dokumentlagring finnes | Rutiner, sikkerhetskopi og gjenopprettingsbevis | Ingen sletting av historikk som korrigeringsmetode |

## Bekreftede kildefunn i 24.0.1

Alle filbaner i dette avsnittet gjelder S2 med fast commit.

- `htdocs/install/mysql/data/llx_c_tva.sql:264–268`: Norge (land-ID 173) har
  0, 14, 8, 25. Dagens generelle norske kategorier er 25, 15 og 12 prosent
  (S8). Historiske bilag skal ikke omskrives ved satsoppdatering.
- `htdocs/install/mysql/tables/llx_c_tva.sql`: `code`, `type_vat`, `taux`,
  `accountancy_code_sell/buy`, men ingen gyldig-fra/-til. `code` er kun 10 tegn.
- Ingen norsk `llx_accounting_account_no.sql` finnes blant kontoplanfilene.
  Fravær her utelukker ikke eksterne leverandører eller manuell import.
- `htdocs/accountancy/class/bookkeeping.class.php:2181` henter hovedbok for
  generisk eksport. Den forespørselen tar ikke med norsk avgiftsgrunnlag/-kode.
  Ingen norsk SAF-T-eksport ble identifisert i regnskaps-/eksportkatalogene.
- `htdocs/compta/facture/class/facture.class.php` har `BILL_VALIDATE`,
  `BILL_MODIFY`, `BILL_UNVALIDATE` og `invoicedao`-hooks. Hovedbokklassen har
  `BOOKKEEPING_CREATE`. Disse er kandidater for kontroller og metadata, men en
  fakturatrigger alene dekker ikke manuelle posteringer, innkjøp og alle importer.
- `date_validated` i hovedboken brukes som låsemarkør. Regnskapsperioder styres
  også av `ACCOUNTANCY_FISCAL_PERIOD_MODE`. Standardvalg og rettigheter må
  prøves før vi lover at en avsluttet norsk periode ikke kan endres.

## Konkret datakart for SAF-T og MVA

Kilde: Dolibarrs tabellfiler i S2 sammenholdt med XSD 1.40 i S6. Dette er et
prioritert kart, ikke en ferdig felt-for-felt-sertifisering av hele skjemaet.

| Eksportbehov | Tilgjengelig data | Gap og foreslått behandling |
|---|---|---|
| Header, foretak, periode, valuta, programversjon | Foretaksinnstillinger, `conf->entity`, NOK og modul-/kjerneversjon | Eksportprofil må validere org.nr., kontakt, periode og regnskapsår; ingen oppdiktede verdier |
| Konto-ID og navn | `accounting_account.account_number/label` | Kontoplanversjon og `GroupingCategory`/`GroupingCode` lagres eksplisitt per konto/gyldighet |
| Konto- og reskontrosaldo ved periodestart/-slutt | Historiske `accounting_bookkeeping.debit/credit`, `subledger_account`, snapshots | Beregn fra komplett bokført historikk; inngående saldoer og migrering fra Akaunting må avstemmes; unngå dobbelttelling av årsavslutning |
| Kunde/leverandør-ID og organisasjonsdata | `societe` med `siren`, `tva_intra`, adresse og reskontrokoder | Tredjeparts-ID må knyttes entydig til bokført reskontro. Nåværende adresse er ikke automatisk historisk adresse |
| Journal, transaksjon og linje | `code_journal`, `piece_num`, `ref`, `rowid` | Eksportnøkkel inkluderer entity og journal der nødvendig; bevar original bilagsreferanse |
| Datoer og registreringsspor | `doc_date`, `date_creation`, `date_validated`, `tms`, bruker-ID | Skill dokument-, bokførings-, system- og avgiftsdato. `tms` er siste endring, ikke komplett historikk; dokumenter tillatte fallback-regler fra S6 |
| Dokumentkobling | `doc_type`, `doc_ref`, `fk_doc`, `fk_docdet` | `fk_docdet` kan være 0 ved aggregering. Import/manuell postering kan mangle faktisk kildedokument; krev bilagsmetadata før godkjenning |
| Fakturalinjens avgift | `facturedet.vat_src_code`, `tva_tx`, `total_ht`, `total_tva`, tjenesteperiode | Ingen norsk, historisk behandling garanteres av kode/sats. Innkjøpslinjer undersøkes tilsvarende; fradragsandel og NOK-beløp må valideres |
| Avgift på hovedbokslinje | Hovedbok har beløp og dokumentkobling | Ingen egne `TaxCode`/`TaxBase`-felt. Bevar avgiftsmetadata i eksterne tabeller og håndter sammenhengen til flere fakturalinjer eksplisitt |
| Kreditnota og retting | `facture.fk_facture_source`, type, negative linjepriser | Test fortegn og originalreferanse i PDF, hovedbok, MVA og eksport; bytte av nåværende kontokobling skal ikke endre historikk |
| Betaling | Betalings-/fakturakobling og bankposter | Avstem reskontro og bank. `paye`/lukket status alene beviser ikke fullt oppgjør |
| Valuta | Fakturaens multicurrency-beløp/-kurs; hovedbok har `multicurrency_amount/code` | Eksportmetoden ovenfor og `BookKeeping::create()` skriver ikke automatisk alle valutafeltene. Ende-til-ende-verifisering og ekstra metadata kan bli nødvendig |
| Eiere/egenkapital | Egenkapitalkontoer kan konfigureres | ENK-eiermetadata og kontoer i Owners må spesifiseres særskilt |

**Viktig endring fra eldre SAF-T-råd:** I 1.30/1.40 er kontoens
`GroupingCategory` og `GroupingCode` obligatoriske. `StandardAccountID` er ikke
feltet som skal designes som hovedløsning; XSD-kommentaren under AccountType
inneholder fortsatt en eldre referanse til dette feltet. Mapping må følge
næringsspesifikasjonen, ikke bare like kontonumre (S6). Standard MVA-koder er
separate fra lokale satser og krever eksplisitt mapping.

### Foreslått ekstern lagring, ennå ikke implementert

1. Kontomapping med `entity`, konto-ID, regnskaps-/kodelisteversjon,
   `GroupingCategory`, `GroupingCode`, gyldighet og endringsspor.
2. Avgiftsregel med lokal kode, norsk standardkode, retning, sats, gyldighet,
   fradragsandel og behandling; registreringsstatus har egen tidslinje.
3. Uforanderlig bokført avgiftsgrunnlag knyttet til entity, hovedbokslinje og
   kildedokument/-linje, med NOK-grunnlag/-avgift, valuta/kurs og korreksjonslenke.
4. Eksportkjøring med periode, versjoner, totalsummer, hash og avviksrapport.

Bruk Dolibarrs transaksjoner og støttede hooks/triggere. Uttrekk skal filtrere
på eksakt entity og bokført periode, avvise manglende mapping/spor og avstemme
mot hele hovedboken. Ikke konstruer eksport fra dagens fakturasatser alene.
Migreringer må være repeterbare, versjonerte og bevare data ved deaktivering.

## Undersøkt gjenbruk

Søk 2026-10-11: GitHub og DoliStore med kombinasjonene Dolibarr/Norway/Norwegian/
SAF-T samt Peppol. Ingen offentlig vedlikeholdt samlet norsk modul ble funnet.
Dette er ikke dokumentasjon på at ingen finnes. Før egen stor implementering
bør relevante utgivere spørres om norsk støtte, API, lisens og vedlikehold.

- Standard regnskapsmotor, kontoimport, journaler og fakturaklasser gjenbrukes.
  Oppdatering av norske standard satser kan foreslås upstream separat, med
  installasjons-/oppgraderingshensyn og uten å omskrive eksisterende bilag.
- SAF-T PT: produktet er utilgjengelig; oppført versjon 1.0.3, Dolibarr 3.5,
  AGPL (S9). Kilde er ikke undersøkt. Ingen kode gjenbrukes. Et eventuelt
  gjenbruk krever både teknisk vurdering og lisensvurdering.
- CAP-REL Peppol 1.4.0 oppgis for Dolibarr V18–V24 og PHP 7.4–8.3, GPLv3+.
  Tilgangspunktavtale er separat (S10). Kilde, norsk EHF-profil, mottakerstøtte
  og totale kostnader er ikke kvalifisert. Ikke anta at den passer testmiljøets
  PHP-versjon eller gir en løsning uten løpende kostnad.
- Skatteetatens skjema/kodelister brukes som spesifikasjon og ekstern
  valideringsreferanse. Standardkontolisten har særskilt opphavsrett og
  begrensning til SAF-T-mapping (S11); den distribueres ikke som kontoplan.
- Lisensportalen er senere integrasjon. Ingen lesing eller endring av et
  separat integrasjonsrepo er nødvendig for denne leveransen.

## Avgjørelser før produksjon

MVA-registerstatus/dato, historiske saldoer og uoppgjorte poster fra inaktiv
periode, faktisk land-/transaksjonsliste, EUR-kursrutine, fradragsrett,
offentlige mottakeres EHF-/ordrereferansekrav og oppbevarings-/driftsansvar er
åpne. Direkte MVA-/skatteinnsending, bankintegrasjon og lisensutstedelse er
fortsatt utenfor første leveranse. Lavt bilagstall brukes ikke som SAF-T-fritak
når regnskapet føres elektronisk (S12).

## Implementert neste del: kontroll av foretaksoppsett

Modulens innstillingsside har nå en skrivebeskyttet kontroll av foretaksnavn,
organisasjonsnummerets format/kontrollsiffer, land NO, basisvaluta NOK og utfylt
adresse. Nummerkontrollen følger Brønnøysundregistrenes vekter/modulus 11;
den gjør ikke registeroppslag og bekrefter verken at foretaket finnes eller
at det er MVA-registrert.

MVA-kontrollen leser bare norske `c_tva`-rader for aktiv entity. Den kontrollerer
aktive satser 25, 15 og 12 for både kjøp og salg, og varsler aktive gamle satser
8/14. Den endrer ingen satser, kontokoblinger eller historiske bilag. Dette er
en kontroll av oppsettets dekning, ikke krav om at foretaket skal bruke alle
satser eller at satsene er gyldige for et gitt bilag. Gyldighetsperioder,
fradragsrett, avgiftskoder og registreringshistorikk er fortsatt datagap.

Kontoplan/mapping, MVA-registerstatus/-dato, bokført avgiftsgrunnlag/rapport,
SAF-T, EHF og valuta/utenlandshandel vises eksplisitt som uferdig eller
uverifisert. Ingen samlet «produksjonsklar»-status beregnes. Kontrollen
stopper med en synlig feil ved mislykket databaseforespørsel.

Kilder kontrollert 2026-10-11:
[Brønnøysundregistrenes kontrollsiffer](https://www.brreg.no/om-oss/registrene-vare/om-enhetsregisteret/organisasjonsnummeret/)
og [Skatteetatens satser](https://www.skatteetaten.no/satser/merverdiavgift?pageid=1500).
