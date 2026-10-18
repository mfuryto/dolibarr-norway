# Papirløs bilagsflyt

Levert i **0.85.0** for ett foretak, NOK og manuell bruk i nettleser på PC.
Dette er den bestilte A3/C1-flyten: originalbilag → vanlig registrering →
hovedbok → betaling → bankavstemming → gjenfinning og oppbevaring.
EHF avventer fortsatt ekstern kvalifisering. Ingen OCR, e-postmottak,
mobilapp, banktilkobling eller ny godkjenningsmodell inngår.

## Registrere og finne igjen et kjøp

1. Åpne **Fakturering → Leverandørfakturaer → Ny faktura**. Velg leverandør,
   dato og referansen fra originalen. Lagre utkastet på vanlig måte.
2. Under **Bilagsdokumentasjon** på fakturakortet laster du opp PDF, PNG
   eller JPEG. Åpne forhåndsvisningen og registrer linjer med riktig konto,
   MVA og fradragsrett. På svært bred skjerm ligger originalen ved siden av kortet;
   ellers ligger den under. «Åpne original i eget vindu» gjør at originalen
   kan holdes synlig ved siden av registreringen uten å klemme sammen feltene. Kontroller at hele originalen er lesbar.
3. Valider når registreringen er riktig. Overfør med kjernens kjøpsjournal.
   Opplasting eller validering alene betyr ikke at kjøpet er i hovedboken.
4. Registrer faktisk betaling på fakturaen og avstem bankkontoen.
   Et kjøp som allerede er betalt, skal ha én betalingsregistrering.

Den vanlige fakturalisten har dato-, leverandør-, referanse- og statusfiltre.
Kolonnen **Bilagsdokumentasjon** viser manglende vedlegg, ikke overført eller
faktisk overført til hovedboken. Betalingsstatusen er fortsatt kjernens status;
en fil gjør aldri en faktura betalt. Dokumentasjon kan suppleres senere.
Kreditnota registreres som vanlig kreditnota med sin egen original og
kobling til kjøpet. Et generert fakturadokument teller ikke som originalvedlegg.

Kjernens unike kombinasjon av leverandør, leverandørreferanse og foretak
sperrer dobbelt registrering av samme referanse. Den nye opplastingen
avviser eksisterende filnavn og identisk filinnhold på samme bilag, også ved
nytt filnavn. Samtidige opplastinger til samme bilag serialiseres. Like beløp
alene regnes aldri som duplikatbevis. Dokumenter på forskjellige bilag uten
samme leverandørreferanse krever fortsatt brukerens vurdering.

## Private utlegg og andre manuelle bilag

Et ENK-utlegg betalt privat gir ingen betaling fra foretaksbanken. Bruk
kjernens **Regnskap → Hovedbok → Nytt bilag** og før relevant kostnad,
eventuelt dokumentert fradragsberettiget MVA og motpost på aktuell eierkonto
(eksempelvis 2062 for privat innskudd). Bruk den vanlige MVA-funksjonen på
bilaget der den er relevant; et vedlegg velger ikke MVA-behandling.

Etter at bilaget er lagret i hovedboken, finnes **Bilagsdokumentasjon** på
samme bilagskort. Last opp underlaget der. Midlertidige kladder må først
lagres/overføres til et fast bilagsnummer. Dokumenter fra kjøpsjournalen
beholdes på leverandørfakturaen og får ikke en ekstra vedleggsplass i hovedboken.
Ansattutlegg, lønn og reiseregninger er ikke kvalifisert av denne ENK-prøven.

## Betaling og brukerbekreftet bankavstemming

Bruk kjernens betaling på fakturaen og **Bank → Kontoutskrifter/Avstemming**.
Velg faktisk bankkonto, dato, beløp og betalingsreferanse. Ved delbetaling
registreres hver betaling med faktisk beløp; resten står åpent. Refusjon
knyttes til kreditnotaen. Et separat bankgebyr er en egen utgift, ikke en
reduksjon av fakturaens opprinnelige kostnad/MVA.

Koble de allerede registrerte bankbevegelsene til den faktiske kontoutskriften.
Når betalingen finnes, skal den avstemmes, ikke opprettes på nytt. Gjentatt
avstemming oppretter ingen betaling eller ny journalpost. Automatiske forslag
fra bankfeed eller import av vilkårlige bankformater inngår ikke.

## Rettigheter, retting og bevaring

Lesing/opplasting bruker kjernens lese-/opprettingsrettigheter for
leverandørfaktura eller hovedbok og avviser eksterne brukere. Leverandørens
vanlige tilgangsbegrensninger og aktivt foretak gjelder også direkte filhenting.
Originalfiler leveres privat uten cache. Filtype og faktisk innhold valideres
på den nye opplastingen, maksimalt 20 MB; PHP/proxy kan ha en lavere grense.
Kjernens opplasting og eventuell konfigurert virusskanning gjenbrukes.

På validerte eller bokførte leverandørfakturaer sperres sletting, overskriving
og navneendring av originalvedlegg. Genererte dokumenter kan fortsatt
regenereres. Tilbakeføring til utkast med originalvedlegg sperres slik at
bevaringen ikke kan omgås. Retting skjer med kreditnota eller dokumentert
rettingsbilag; suppler underlaget med en ny fil. På ubehandlede utkast kan
feilvedlegg håndteres under kjernens Dokumenter-fane før validering.

Vedlegg på manuelle hovedboksbilag bevares, og sletting av det tilknyttede
bilaget sperres. Vedlegg knyttes også til kjernens rad-ID, slik at eventuell
senere gjenbruk av et bilagsnummer ikke viser et annet bilags dokumenter. Korriger med dokumentert nytt bilag. Ingen vedlegg eller
hovedboksposter flyttes eller omskrives ved oppdatering. Vernet gjelder
applikasjonens kvalifiserte handlinger; det erstatter ikke tilgangsvern på
serveren, oppbevaringsrutine og sikkerhetskopiering.

## Nedlasting, eksport og backup

Enkeltfiler lastes ned fra bilaget. Under **Regnskap → Eksport** finnes
**Last ned bilagsdokumentasjon (ZIP)**. Bruk fra-/til-datoene ved SAF-T på
samme side; dokumentpakken er en separat eksport og krever ikke en SAF-T-mapping.
Den tar med originale PDF-/bildevedlegg på leverandørfakturaer med dato i
perioden (også utkast/ikke bokførte), kilder til periodens kjøpsposteringer og
periodens manuelle bilag. `manifest.json` kobler filer, referanser, kilde-ID-er,
SHA-256 og periodens hovedboksposter; manglende dokumentasjon vises uttrykkelig.
Ingen fil regnes som bokført bare fordi den er med i pakken.

Eksporten krever leserett til kjøp og hovedbok samt regnskapseksportrett.
Den avbryter fremfor å levere en ufullstendig pakke hvis en kilde ikke kan
leses. Maksimal dokumentmengde er 200 MB per pakke; velg kortere perioder ved
behov. Dette er en dokumentpakke for kjøp/manuelle bilag, ikke SAF-T, en kopi
av alle salgsdokumenter eller en full gjenopprettingsbackup.

Full backup gjøres med `bash scripts/backup-installation.sh` fra valgt
installasjonskatalog. Den stopper webskriving mens database, dokumenter,
konfigurasjon og modul tas samlet. Behold backup privat; den inneholder
innloggingshemmeligheter. Oppdatering tar backup automatisk, men dette er
ikke en periodisk backupordning. Administrator må etablere en regelmessig
kopi også utenfor serveren og prøve gjenoppretting. Se [driftsveiledningen](test-and-operations.md).

[Altinns oppbevaringsveiledning](https://info.altinn.no/starte-og-drive/regnskap-og-revisjon/regnskap/oppbevaring-av-regnskapsmateriale/)
beskriver hovedregelen om fem års oppbevaring av primærdokumentasjon, lengre
særkrav, lesbarhet og elektronisk backup. Behold skannet papirmateriale til
backup er tatt. Dokumenter hva som kopieres, hyppighet og lagringssteder i
virksomhetens private driftsrutine. Reelle dokumenter og opplysninger skal aldri i Git.

## Prøvebevis og ferdigkriterium

`tests/paperless-cycle.php` bruker faktiske native HTTP-skjermbilder, native
opplasting/ECM-indeks, syntetisk PDF/PNG og ordinær kjøpsjournal. Prøven dekker
manglende dokument, forhåndsvisning, bytebevaring, ny nummerering ved validering,
korrekt hovedbokstatus, referanseduplikat, identisk vedlegg, samtidighet,
ugyldig fil, CSRF, tilgang/foretak, sletting/overskriving og privat utlegg med kjernens normale
tomme dokumenttype. En faktisk ikke-administrator prøver lesing, skriving,
eksportrett og begrensning til tildelte leverandører.
Delbetaling 40 + 60, kreditnota/refusjon 20 og gebyr 2 gir uavhengig forventet
bankbevegelse −82; gjentatt brukerbekreftet avstemming skaper ingen dobbeltpost.
ZIP-eksporten avstemmes mot alle tre originalfiler og kilder i prøven.

`tests/storage-sentinel.php` og `scripts/ci-installation.sh` prøver oppgradering
og isolert restore: original PDF/PNG fjernes etter backup, gjenopprettes fra
arkivet og sammenlignes bytevis med hash og den gjenopprettede native ECM-indeksen.
Skjermbredder prøves ved 1440/1024/768/390 med norsk/engelsk grensesnitt.
Ingen ny tabell eller skjemamigrering er innført. EHF og samlet produksjonsaksept
lukkes ikke av denne leveransen.

## Grunnlag undersøkt før implementasjon

Kontrollert 2026-10-18. Fikens offentlige flyt bruker vedlegg på vanlig kjøp,
etterregistrering av dokumentasjon og separat faktisk betaling. Dette mønsteret
brukes som inspirasjon; tekster, kode og leverandørspesifikke kontovalg kopieres ikke.
Dolibarrs eksisterende fakturaliste, dokumentfane, betalingskoblinger og bankavstemming
gjenbrukes. Det konkrete gapet var vedlegg på manuelle hovedboksbilag,
tilgjengelig original mens kjøpet registreres, tydelig dokument-/bokføringsstatus
og originalbevaring. En ekstra innboks eller hovedbok er derfor ikke bygget.

- [Fiken: registrere kjøp og utgifter](https://hjelp.fiken.no/registrere-kjoep-og-utgifter)
- [Fiken: dokumentasjon](https://hjelp.fiken.no/dokumentasjon-i-et-regnskap)
- [Fiken: bankavstemming](https://hjelp.fiken.no/hva-er-og-hvordan-fungerer-bankavstemming)
- [Dolibarr: leverandørfakturaer](https://wiki.dolibarr.org/index.php?title=Module_Suppliers_Invoices)
- [Kjerne 24.0.1: vedlegg](https://github.com/Dolibarr/dolibarr/blob/24.0.1/htdocs/core/actions_linkedfiles.inc.php)
- [Altinn: oppbevaring](https://info.altinn.no/starte-og-drive/regnskap-og-revisjon/regnskap/oppbevaring-av-regnskapsmateriale/)
