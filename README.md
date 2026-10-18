# Dolibarr Norway

**Versjon 0.84.0 · Under utvikling**

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
- **Hjelp til MVA-oppsett**, med norske satser og salg- og kjøpskontoer.
- **En norsk oppsettsveiviser** med veiledning og lenker til innstillingene.
  Den har ingen egen godkjenningsstatus eller obligatoriske kontrollrunder.

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

Veiviseren lenker til oppsett for kontoplan, kontokoblinger og nummerserier.
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

## Videre utvikling

Videre arbeid prioriterer forenkling og gjenbruk av Dolibarrs funksjoner.
Overflødige kontroll- og historikktillegg er fjernet i 0.65.0.
Tilleggskode skal dekke et dokumentert norsk gap eller et uttrykkelig bestilt
behov. Implementerte ekstrakontroller skal ikke forstås som lovpålagte trinn.
Se den [oppdaterte arbeidsplanen](docs/delivery-plan.md).

## Regnskapsmessig omfang

Modulen gir norsk oppsett og kontroller, men full norsk MVA-rapportering,
EHF/Peppol og årsoppgjør/skatt er ikke ferdigstilt. SAF-T har avgrenset støtte. Den er foreløpig ikke en
komplett løsning for norsk produksjonsregnskap. Kontoplan og kontokoblinger
alene bekrefter ikke riktig avgiftsbehandling eller fradragsrett.

## Lisens

GPL-3.0-or-later. Se [LICENSE](LICENSE). Den medfølgende norske kontoplanen
fra Odoo er lisensiert under LGPL-3.0; kilde- og lisensopplysninger følger
modulen.

## Forenkling i 0.65.0

Egne faktura-, bilags-, periode- og fordelingskontroller, begrunnelsesregistrering
og egen kjøpsbokføring er fjernet. Bruk kjernens dokumenter, journaler,
hovedbok, perioder, bank og regnskapskoder på varer og tjenester.
Norsk regnskapsoppsett har én inngang under Regnskap → Innstillinger.

Eksisterende regnskap og dokumenter endres ikke. Tidligere lagret tilleggshistorikk
kan leses og lastes ned uendret fra modulinnstillingene når slik historikk finnes.
Det opprettes ingen nye kontrollkopier. Allerede bokførte kjøp fra den gamle
særflyten beskyttes mot ny overføring; retting skjer som eget bilag i kjernen.
Dette er en teknisk forenkling, ikke en bekreftelse på full norsk etterlevelse.

## Kjøp, dokumenter og MVA

Fra **0.79.0** velges MVA-fradrag på linjen i den vanlige leverandørfakturaen:
0 prosent, valgfri delvis andel eller fullt fradrag. Innenlandske varer og
tjenester med 25/15/12 prosent og fjernleverbare utenlandstjenester med 25
prosent beregnet avgift omfattes. Fritekst og katalogtjenester kan brukes.
Kjernens kjøpsjournal bokfører riktig kostnad, faktisk fradrag og gjeld i ett
bilag per faktura. Native kreditnotaer kopierer valget; betaling og refusjon
bruker kjernen. Originalbeløp og tidligere bokførte rettinger bevares.
Se [vanlig kjøpsflyt og konkrete eksempler](docs/purchase-vat.md).

Norske/engelske standard-PDF-er for tilbud, faktura og kreditnota er prøvd.
AS/ASA får «Foretaksregisteret». Kreditnota for original i tidligere system
bruker kjernens referansefelt. Se [salgsdokumenter](docs/sales-documents.md).

Ordinære MVA-koder, nullsats 5/6/7, lokal NO0 (norsk kode 0), eksport 52 og
tjenestekjøp 86/87 inngår i det [avstembare MVA-uttrekket](docs/vat-report.md).
Fra **0.80.0** velges en ordinær tomånedlig termin med grunnlag for
manuell levering i Skatteetatens portal. Historiske beløp tåler endret
satsregister, kontokobling og registreringsstatus. Rapporten viser et forslag
til oppgjørsbilag; bilag, MVA-betaling, kvitteringsvedlegg, bankavstemming og
periodelåsing bruker kjernen. Se [MVA og periodeavslutning](docs/vat-period-close.md).
Portalinnlogging og levering gjøres av brukeren; ingen innsending skjer fra modulen.
Vareimport, særordninger og faktisk EHF-levering gjenstår. ENK-årsavslutning
og portalveiledning for skatt beskrives nedenfor.
Se [samlet leveranseplan](docs/delivery-plan.md) for støttet omfang og neste etappe.

Fra **0.81.0** finnes en tilpasset åpen EHF-generator og sending begrenset til
separat testnettverk. Native beløp/referanser og offisielle formatregler prøves i
CI. **Faktisk testnettverkslevering gjenstår; produksjonssending er sperret.**
Se [EHF-kvalifisering og privat testoppsett](docs/ehf-qualification.md).

Fra **0.83.0** finnes SAF-T 1.40 på kjernens regnskapseksportside, med
kontokoblinger, MVA, reskontro, avstemte saldoer og komplett periodevalg.
Første omfang er NOK og kalenderårene **2025–2026**; øvrige år og ukjent
behandling avvises. [Arbeidsflyt, prøvebevis og avgrensning](docs/saft-export.md).

Fra **0.84.0** er [ENK-forskuddsskatt](docs/tax-accounts.md),
[årsavslutning](docs/year-end.md) og [portalveien for skattemelding med
næringsspesifikasjon](docs/enk-tax-return.md) dokumentert og årsgrunnlaget prøvd.
Kjernens bank, bilag, rapporter og avslutning brukes. Et vern hindrer gjentatt
årsavslutning og saldooverføring uten reskontro. Omfanget er ENK uten regnskaps-/
revisjonsplikt; faktisk innlevering krever egen portalutfylling. Eksemplet gjelder
2025; endelig 2026-utfylling må kontrolleres ved levering i 2027. Direkte innsending
og et automatisk norsk årsoppgjørssystem er ikke levert.
