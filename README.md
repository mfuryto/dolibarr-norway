# Dolibarr Norway

**Versjon 0.61.0 · Under utvikling**

**Norsk modul for Dolibarr**, med norsk kontoplan, foretaksoppsett,
fakturering og hjelp til å konfigurere regnskapet for norske forhold.

Modulen utvider Dolibarrs funksjoner for kunder, leverandører, varer,
tjenester og regnskap. Den installeres som en ekstern modul og krever ingen
endringer i Dolibarr-kjernen. Den passer for ulike bransjer og bruker ett
foretak per installasjon.

## Hva modulen gir

- **Norsk kontoplan med 745 kontoer**, med forhåndsvisning og bekreftet
  installasjon. Forslag til standardkontoer dekker kunder, leverandører,
  varer, tjenester, eksport, import og lukkingskontoer.
- **Foretaksoppsett for Norge**, med organisasjonsnummer, norske
  organisasjonsformer, MVA-identitet og manuelt oppslag i registeret.
- **Tilbud og faktura på norsk og engelsk**, med NOK som dokumentvaluta.
  En separat kalkulator kan vise veiledende EUR-priser.
- **Enkle, stigende nummerserier** for faktura, kreditnota og tilbud,
  samt kunde- og leverandørnumre med valgfrie prefikser, for eksempel
  K00001 og L00001. Startnummer kan velges ved overgang
  fra et annet system.
- **Hjelp til MVA-oppsett**, med kontroll av norske satser, salg- og
  kjøpskontoer, registreringens virkningsdato og avgiftskodekoblinger.
- **Kontroller av regnskapsgrunnlaget**, blant annet fakturabeløp,
  hovedboksreferanser, bilagsbalanse og endringer i lagrede kontrollkopier.
  Nye salgs- og kjøpsposteringer bevarer kildeopplysninger og separate
  daterte kodekoblinger. Salg bevarer også registreringssammenligning. Valgte fordelingsprofiler kan sammenlignes
  med bevart grunnlag. En valgfri kjøpskontroll finnes i leverandørfakturabildet
  for uttrykkelig valgte profiler med fullt, delvis eller ingen fradragsføring.
  Begrunnede fradragsvurderinger kan lagres med linjebeløp og revisjonshistorikk;
  dette endrer ikke bokføringen. Nye kjøpsposteringer bevarer også hvilken
  vurderingsrevisjon som forelå, og om grunnlaget samsvarte ved posteringen.
  En bekreftet førstegangsbokføring kan anvende lagret fullt, delvis eller intet
  fradrag på støttede kostnadskjøp og kreditnotaer. Endret fradragsandel kan
  korrigeres med full reversering og erstatningsbilag innen støttet omfang.
  Fradragsandel og begrunnelse kan velges per linje, også med blandede
  satser på 25, 15 og 12 %, med kontroll av hver MVA-konto.
- **En norsk oppsettsveiviser** som leder gjennom foretaksopplysninger,
  kontoplan, journaler, bank, varer og tjenester og dokumentoppsett.

Sjekkbetalinger og sjekkinnskudd er deaktivert. Oppsetthjelperne bruker
forhåndsvisning og bekreftelse, og har vern for eksisterende valg og historikk.

## Installere i eksisterende Dolibarr

Støttet versjon er **Dolibarr 24.0.1**, med **PHP 8.2 eller nyere**.
Installasjonen må bruke **NOK som basisvaluta** og ha modulen
**Advanced Accounting** aktivert.

1. Ta sikkerhetskopi av den eksisterende installasjonen.
2. Hent prosjektet fra `main` og kopier mappen `htdocs/custom/norway`
   til `htdocs/custom/norway` i Dolibarr-installasjonen.
3. Aktiver modulen **Norway** under **Innstillinger → Moduler**.
4. Åpne modulens innstillinger og følg **Norsk oppsettsveiviser**.

Kontoplan, kontokoblinger og nummerserier velges og bekreftes i veiviseren.
Aktivering alene installerer ikke kontoplanen eller endrer eksisterende
fakturanumre.

## Installere Dolibarr og modulen med Docker

Prosjektet har et samlet oppsett for Dolibarr, MariaDB og den norske modulen.
Det krever Linux, Docker med Compose, Git og Python 3.

Kjør følgende i katalogen der du vil hente prosjektet. Oppgi foretakets navn
og den offentlige HTTPS-adressen når du blir spurt:

```bash
git clone --branch main https://github.com/mfuryto/dolibarr-norway.git
cd dolibarr-norway

read -r -p 'Registrert foretaksnavn: ' norway_company_name
read -r -p 'Offentlig HTTPS-adresse: ' norway_public_url
python3 scripts/init-install-env.py \
  --company-name "$norway_company_name" \
  --url "$norway_public_url"

docker compose --env-file deploy/.env -f deploy/compose.yaml up -d --build
docker compose --env-file deploy/.env -f deploy/compose.yaml ps
```

Oppsettet lager tilfeldige passord i den private filen `deploy/.env`.
Administratorens brukernavn er `admin`; passordet står i `ADMIN_PASSWORD`.
Modulen aktiveres automatisk. Ingen eksempelbilag eller testbrukere legges inn.

Sett opp HTTPS og en reverse proxy mot `127.0.0.1:8088` på samme server før
innlogging. Åpne deretter den valgte nettadressen og følg modulens
oppsettsveiviser. Database, dokumenter og konfigurasjon lagres vedvarende
under prosjektets `data/`-mappe. Bevar `deploy/.env` og instansnøkkelen ved
oppdatering og gjenoppretting.

Se [installasjonsveiledningen](docs/vps-installation.md) for proxyoppsett,
oppdatering og sikkerhetskopiering.

## Regnskapsmessig omfang

Modulen gir norsk oppsett og kontroller, men full norsk MVA-rapportering,
SAF-T-eksport og EHF/Peppol er ikke ferdigstilt. Den er foreløpig ikke en
komplett løsning for norsk produksjonsregnskap. Kontoplan og kontokoblinger
alene bekrefter ikke riktig avgiftsbehandling eller fradragsrett.

## Lisens

GPL-3.0-or-later. Se [LICENSE](LICENSE). Den medfølgende norske kontoplanen
fra Odoo er lisensiert under LGPL-3.0; kilde- og lisensopplysninger følger
modulen.

Menyer og kontroller plasseres ved den aktuelle oppgaven. Fra 0.58.0 finnes
bilags- og periodekontroll under Regnskap, fakturakontroll ved kundefakturaen
og relevante oppsett ved de aktuelle innstillingene. Oppstartsveilederen
beholder førstegangsoppsettet. Se [menyer og tilgang](docs/menu-integration.md).

Fra 0.59.0 får varer/tjenester en fane for sine regnskapskoder, kundefakturaen
får direkte inngang til kontrollkopier, og avgiftsoppsettet nås fra Regnskap.
Veiledende EUR-prising finnes i tilbudsmenyen og er tatt ut av veilederen.

Fra 0.60.0 samles norsk regnskapsoppsett under Regnskap → Innstillinger.
MVA-underpunktene vises når gruppen åpnes. Bankkobling nås fra dette oppsettet;
den separate menylenken under Bank er fjernet.

Fra 0.61.0 viser fakturakontrollen eksplisitte salgskategorier uten beregnet
MVA og konflikter mellom kode, sats og beløp. Dagens og bevart grunnlag holdes
atskilt. Kontrollen godkjenner ikke fritak eller unntak. Se
[nullsatskontrollen](docs/sales-zero-vat.md).
