# Dolibarr Norway

**Versjon 0.78.0 · Under utvikling**

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

## Videre utvikling

Videre arbeid prioriterer forenkling og gjenbruk av Dolibarrs funksjoner.
Overflødige kontroll- og historikktillegg er fjernet i 0.65.0.
Tilleggskode skal dekke et dokumentert norsk gap eller et uttrykkelig bestilt
behov. Implementerte ekstrakontroller skal ikke forstås som lovpålagte trinn.
Se den [oppdaterte arbeidsplanen](docs/delivery-plan.md).

## Regnskapsmessig omfang

Modulen gir norsk oppsett og kontroller, men full norsk MVA-rapportering,
SAF-T-eksport og EHF/Peppol er ikke ferdigstilt. Den er foreløpig ikke en
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

## MVA og fradrag i 0.66.0

Blandede kjøpssatser på 25, 15 og 12 prosent og kreditnotaer er prøvd gjennom
kjernens kjøpsjournal med separate MVA-kontoer. Delvis og intet fradrag er
prøvd som separate omposteringsbilag i kjernen, med bevaring av faktura og
opprinnelig postering. Dette er en dokumentert manuell arbeidsflyt, ikke
ny automatisk fradragsberegning.

Den misvisende NPR-teksten er rettet til «Fransk NPR-ordning» på norsk og
«French NPR scheme» på engelsk. NPR er ikke et valg for norsk fradragsandel.
Se [oppsettsveiledningen](docs/norwegian-setup-guide.md) for et regneeksempel.
Norsk MVA-melding gjenstår; kjernens fakturauttrekk tar ikke automatisk med
separate fradragsomposteringer.

## Norske MVA-koder

Det eksisterende MVA-oppsettet kan opprette separate ordinære salgskoder
3/31/33 og kjøpskoder 1/11/13 i kjernens satsregister. Fra 0.77.0 kan manglende kodevalg også legges til i
brukte oppsett, med bevaring av eksisterende satser.
Kontokodene velges direkte på satsradene i kjernens MVA-register. Native fakturalinjer bevarer kodevalget.
Standardvalget i de prøvde skjermbildene er avgrenset til riktig salgs-/kjøpsretning.
Eksisterende regnskap og produkter legges ikke om automatisk. Ingen ny meny,
kontrollside, kodemodell eller lagringstabell innføres.

MVA-meldingen er fortsatt uferdig. Manuelle nullsatsbilag, utlandstilfeller og komplett
avstemming/innsending gjenstår.

Fra 0.70.0 finnes et lesende MVA-uttrekk under regnskapsrapportene for
ordinære norske koder, med avstemming av fakturaer, kreditnotaer og manuelle
fradragsrettinger mot bokføringen. Uavklarte poster sperrer netto og CSV.
Full MVA-melding, særtilfeller og innsending gjenstår.
Se [bruk og avgrensning](docs/vat-report.md).

0.70.0 skiller innenlands fritak (5), omsetning utenfor MVA-loven (6) og
inntekt som ikke er MVA-omsetning (7) i samme uttrekk. Manglende kodevalg
gjøres tilgjengelige i det native satsregisteret; eksisterende dokumenter
omklassifiseres ikke. Kode 7 holdes utenfor meldingsgrunnlaget i CSV.
Se [kodevalg og avgrensning](docs/vat-rates.md).

0.71.0 støtter innenlandske kjøp uten MVA-behandling i samme uttrekk.
Velg `0 (NO0)` på leverandørfakturalinjen; NO0 er et lokalt navn for norsk
kode 0 fordi kjernen mister den bokstavelige verdien «0». Uttrekket viser
kode 0 separat, uten fradrag eller meldingsgrunnlag. Vanlig kjøpsjournal,
kreditnotaer og varer/tjenester gjenbrukes. Kjøp med MVA uten fradragsrett
krever fortsatt den ordinære fradragsrettingen; NO0 erstatter ikke dette.

0.72.0 gjør eksportkode 52 tilgjengelig i satsregisteret og det samme
MVA-uttrekket. Eksplisitt kodede NOK-fakturaer/kreditnotaer til utenlandske
kunder bruker kjernens salgsjournal, for både varer og tjenester.
Eksportgrunnlaget vises med null norsk avgift; landet setter ikke koden
automatisk. Se [eksportflyt og avgrensning](docs/vat-rates.md#eksportkode-52-i-0720).
Full MVA-melding og innsending gjenstår. Tjenestekjøp med kode 86 er beskrevet nedenfor.

0.73.0 gjenbruker kjernens omvendte avgiftsplikt for NOK-kjøp av tjenester
med 25 % og full fradragsrett (kode 86). Leverandørgjelden har ingen norsk
MVA; kjøpsjournalen fører beregnet MVA på 2704 og fradraget på 2714.
Uttrekket avstemmer og viser begge retninger under 86. Kjernens funksjon
må settes opp før bruk; eksisterende kontovalg og fakturaer endres ikke.
Se [oppsett og avgrensning](docs/vat-rates.md#tjenestekjøp-med-omvendt-avgiftsplikt-i-0730).
Ingen/delvis fradragsrett, andre satser og vareimport gjenstår.

0.74.0 støtter kode 87 på manuelt bokførte NOK-tjenestekjøp uten fradragsrett,
med 25 % beregnet MVA på 2704 og grunnlag på det vanlige bilagskortet.
Uttrekket viser bare utgående avgift for 87. Denne avgrensede flyten oppretter
ikke leverandørfaktura eller betalingsoppfølging i fakturamodulen; den må
ikke brukes i tillegg til en allerede bokført faktura for samme kjøp.
Se [manuell kode 87 og avgrensning](docs/manual-vat.md#tjenestekjøp-uten-fradragsrett-kode-87-i-0740).
Automatisk leverandørfakturaflyt for 87 gjenstår. Delvis fradrag kan fra
0.75.0 håndteres med separat, vanlig rettingsbilag med kode 86 på 2714.
Beregnet MVA og grunnlag beholdes; faktisk inngående fradrag summeres i
uttrekket. Prosenten velges ikke automatisk, og kreditnota krever motsatt
retting. Ingen ny side, tabell eller bokføringsmotor.
Se [føring av delvis fradrag](docs/manual-vat.md#delvis-fradrag-på-tjenestekjøp-kode-86-i-0750).

De manuelle fradragsrutinene og kode 87-flyten over er **reserveveier**.
En sammenhengende normalflyt for ingen/delvis fradragsrett gjenstår:
behandling ved registrering, bokføring, kreditnota og rapportering. Separate
rettingsbilag skal ikke regnes som ferdig standardflyt. Dette prioriteres
før flere importtilfeller. Fra 0.76.0 gir oppstartsveilederen én statustabell med forklaring og lenker
til kjernens relevante verktøy. Uferdig støtte vises uttrykkelig, uten
lagrede fullføringsmerker eller ekstra godkjenninger.

Full [gjennomgang av tidligere tilpasninger](docs/review-0.75.0.md)
og [samlet leveranseplan med ferdigkriterier](docs/delivery-plan.md)
er grunnlaget for videre arbeid. Hele modulen er fortsatt under utvikling.

Fra **0.77.0** er norsk grunnoppsett forenklet til kjernens standardkontoer,
journaler, MVA-register og bankkort. Separate utfyllingssider er fjernet.
Kontoplanens manglende native balanse-/resultatgrupper fylles uten å endre egne
valg eller posteringer; resultat og teknisk saldoovergang er prøvd. Hver
nummerserie låses uavhengig etter bruk. Se [leveranseplanen](https://github.com/mfuryto/dolibarr-norway/blob/main/docs/delivery-plan.md)
for gjenstående dokument-/fradragsflyt, rapportering og årsavslutning/skatt.

Fra **0.78.0** støtter dokumentflyten kreditnota for originalfaktura fra tidligere
system med kjernens referansefelt. Norsk AS/ASA får «Foretaksregisteret» på
faktura/tilbud. Norske/engelske standard-PDF-er, native leveringsopplysninger,
bokføring, MVA-fortegn og refusjonsregistrering er prøvd. Kjøpsflyten med
ingen/delvis fradrag er fortsatt under arbeid; etappe 3 er ikke ferdigstilt.
Se [salgsdokumenter](https://github.com/mfuryto/dolibarr-norway/blob/main/docs/sales-documents.md).
