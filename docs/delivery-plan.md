# Arbeidsplan for norsk lokalisering

Oppdatert 2026-10-15 etter prosjekteiers presisering om forenkling.
Gjeldende programversjon er 0.65.0. Oppryddingen er gjennomført;
videre arbeid skal følge prioriteringen nedenfor. [Leveransehistorikken](delivery-history.md)
beskriver hva som allerede er bygget; den er ikke en bestilling av mer kode.

## Styrende beslutning

Dolibarrs eksisterende funksjoner skal brukes. Modulen skal dekke konkrete
norske mangler og uttrykkelig bestilte behov med minst mulig tilleggskode.
Et dokumentasjons- eller sporbarhetskrav betyr ikke automatisk at det trengs
en ny kontrollside, tabell, historikkmodell eller bekreftelsesrunde.

Egne kontroll- og historikkfunksjoner er fjernet fra vanlig arbeidsflyt. Tidligere prioritering om å fortsette direkte med
myndighetsgodkjenning/etterfakturering erstattes av oppryddingen nedenfor.
Generelle «fortsett»-beskjeder utvider ikke oppgaven med nye produktideer.

## Prioritering

| Rekkefølge | Oppgave | Ferdig når |
| --- | --- | --- |
| 1 | Gjennomgå eksisterende tillegg mot kjernen | Hvert tillegg har en kort beslutning: behold, erstatt med kjernefunksjon, forenkle eller ta ut av vanlig arbeidsflyt. Beslutningen viser faktisk kjernedekning og eventuelt konkret krav som ikke dekkes. Ingen ny kontrollfunksjon bygges for å utføre gjennomgangen. |
| 2 | Forenkle arbeidsflyten | Overflødige sider, menypunkter og bekreftelser er fjernet eller samlet der dette er avklart. Vanlig arbeid bruker kjernens dokumenter, registrering og bokføring. Eksisterende data og historikk kan fortsatt leses; nødvendig endring av lagring eller bokføringskoblinger har en prøvd overgang. |
| 3 | Fullføre dokumenterte norske mangler | Bare mangler som står igjen etter kjernetest får egen implementasjon. Prioriter relevante norske dokumentopplysninger, avgiftsbehandling og rapportering. Gjenbruk eksisterende tillegg når de dekker behovet. |

## Utført forenkling i 0.65.0

| Beholdt | Fjernet | Overgang |
| --- | --- | --- |
| Norsk kontoplan, standardkontoer, MVA-kontoer, foretaksopplysninger, nummermodeller, NOK og språk, valgfri EUR-visning og sperring av sjekker | Egne faktura-, bilags-, periode- og fordelingskontroller, begrunnelsesregistrering, nye kontrollkopier og særskilt kjøpsbokføring | Regnskap og dokumenter beholdes. Gammel tilleggshistorikk kan leses og lastes ned uendret. Tidligere særposterte kjøp sperres mot dobbeltføring; separat rettingsbilag i kjernen er mulig. |

Oppsettsveilederen gir forklaringer og lenker uten egen godkjenningsstatus.
Daglig arbeid bruker kjernens dokumenter, journaler, hovedbok, perioder,
bank og produktkontoer. Norsk regnskapsoppsett har én menyinngang under
Regnskap → Innstillinger. De tidligere løsningene var tillegg, ikke
lovpålagte brukeroppgaver. Det gamle lagringsskjemaet beholdes for lesing
og vern av allerede særposterte kjøp; nye historikkposter opprettes ikke.

Leveransen prøves med ordinær salgs- og kjøpsbokføring, gammel historikk,
oppgradering, backup og gjenoppretting. Dette dokumenterer teknisk overgang,
ikke full norsk regnskapsmessig etterlevelse.

Neste arbeid er å avklare faktisk dekning i kjernen og eksisterende tillegg
for MVA-rapportering, SAF-T og EHF. Ingen ny rapportmotor eller kontrollflate
skal bygges før det konkrete norske gapet er dokumentert. Papirløs flyt tas sist.

## Videre omfang

De avtalte områdene beholdes, men er ikke forhåndsbeslutninger om egen kode:

- A1/A2: norsk avgiftsoppsett, faktura, kreditnota og nødvendige kontokoblinger.
- B1/B2: norsk MVA-grunnlag og rapportering, med direkte innsending som bestilt funksjon.
- C/C2: reskontro, bank, avstemming og årsavslutning på kjernens grunnlag.
- D: relevante utenlandsscenarioer for varer og tjenester.
- E: EHF/Peppol for offentlige kunder; eksisterende løsninger undersøkes tidlig.
- F/F2: SAF-T og avtalt rapportgrunnlag for næringsspesifikasjonen.
- G: nødvendig retting, tilgang, oppbevaring og drift.
- A3/C1: papirløs bilagsflyt og enkel matching tas fortsatt til slutt.

Kravgrunnlaget ligger i [kravregisteret](norwegian-requirements.md) og
[kilderegisteret](sources.md). Lovkrav, uttrykkelige brukerønsker og intern
teknisk diagnostikk skal skilles. Tekniske tester bekrefter ikke at et tillegg
er nødvendig eller at norsk regnskapsmessig etterlevelse er oppnådd.

## Kontroller som del av arbeidsflyten

Nødvendige kontroller brukes ved den ordinære oppgaven. Gjenbruk kjernens
kontroller først. Oppsett hører til i relevante innstillinger eller
oppsettsveilederen; daglige oppgaver hører til ved dokument, bank eller regnskap.
Vanlig bokføring skal ikke få ekstra manuelle kontrollrunder uten et konkret
behov. Interne diagnoser skal ikke presenteres som lovpålagte brukeroppgaver.

## Arbeidsregler og ferdigkriterium

Ett foretak, NOK som dokumentvaluta, norsk/engelsk på tilbud og faktura og
valgfri veiledende EUR-prisvisning videreføres. Modulen skal være generell
for varer og tjenester. Bare main og staging brukes, og kjernen endres ikke.

Før ny tilleggskode skal den korte begrunnelsen vise:

1. Kravet eller det uttrykkelig bestilte behovet.
2. Hva kjernen eller et eksisterende tillegg faktisk dekker i prøvd versjon.
3. Det konkrete gapet og den minste endringen som løser det.

Relevant test og dokumentasjon følger endringen. Main krever fortsatt PR,
bestått CI på aktuell revisjon og tydelig akseptert omfang. Eksisterende
regnskap, dokumenter, passord, volumer og instansnøkkel bevares. Endring av
skjema eller historikk krever versjonert overgang og prøvd gjenoppretting.
