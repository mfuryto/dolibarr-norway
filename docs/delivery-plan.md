# Arbeidsplan for norsk lokalisering

Oppdatert 2026-10-15 etter prosjekteiers presisering om forenkling.
Gjeldende programversjon er 0.64.0. Dette er en endring av arbeidsretning,
ikke en ny programleveranse. [Leveransehistorikken](delivery-history.md)
beskriver hva som allerede er bygget; den er ikke en bestilling av mer kode.

## Styrende beslutning

Dolibarrs eksisterende funksjoner skal brukes. Modulen skal dekke konkrete
norske mangler og uttrykkelig bestilte behov med minst mulig tilleggskode.
Et dokumentasjons- eller sporbarhetskrav betyr ikke automatisk at det trengs
en ny kontrollside, tabell, historikkmodell eller bekreftelsesrunde.

Videre utvikling av egne kontroll- og historikkfunksjoner stanses mens
nødvendigheten vurderes. Tidligere prioritering om å fortsette direkte med
myndighetsgodkjenning/etterfakturering erstattes av oppryddingen nedenfor.
Generelle «fortsett»-beskjeder utvider ikke oppgaven med nye produktideer.

## Neste arbeid

| Rekkefølge | Oppgave | Ferdig når |
| --- | --- | --- |
| 1 | Gjennomgå eksisterende tillegg mot kjernen | Hvert tillegg har en kort beslutning: behold, erstatt med kjernefunksjon, forenkle eller ta ut av vanlig arbeidsflyt. Beslutningen viser faktisk kjernedekning og eventuelt konkret krav som ikke dekkes. Ingen ny kontrollfunksjon bygges for å utføre gjennomgangen. |
| 2 | Forenkle arbeidsflyten | Overflødige sider, menypunkter og bekreftelser er fjernet eller samlet der dette er avklart. Vanlig arbeid bruker kjernens dokumenter, registrering og bokføring. Eksisterende data og historikk kan fortsatt leses; nødvendig endring av lagring eller bokføringskoblinger har en prøvd overgang. |
| 3 | Fullføre dokumenterte norske mangler | Bare mangler som står igjen etter kjernetest får egen implementasjon. Prioriter relevante norske dokumentopplysninger, avgiftsbehandling og rapportering. Gjenbruk eksisterende tillegg når de dekker behovet. |

## Første vurdering av eksisterende arbeid

Dette er en prioritering for opprydding, ikke en ferdig avgjørelse om å slette kode.

| Område | Kjernens dekning og vurdering | Videre tiltak |
| --- | --- | --- |
| Faktura, kreditnota, tilbud, nummerering og PDF | Kjernen har dokumenter, nummermodeller og dokumentmaler. | Bruk standardoppsett der det dekker valgte serier, språk og norske dokumentopplysninger. Undersøk egne nummermodeller og kontrollsider for overflødig kode. |
| Kontoplan, standardkontoer, journaler og MVA-kontoer | Kjernen har import/oppsett og kontokoblinger. Norsk profil og norske standardvalg er modulens oppsett. | Behold norsk datagrunnlag og nødvendige standardvalg. Samle oppsetthjelp og fjern dobbeltarbeid mot kjernens innstillinger. |
| Hovedbok, regnskapsperioder, bank og dokumentvedlegg | Kjernen har grunnfunksjonene. | Bruk og prøv disse før egne rapporter, periodekontroller eller bilagsbilder vurderes. |
| Faktura-, bilags- og fordelingskontroller | Egne tillegg sammenligner deler av kjernens data med avgrensede profiler. | Vurder konkret nytte og overlapp. En egen profil er ikke i seg selv et norsk krav eller bevis på feil føring. |
| Kontrollkopier, begrunnelsesrevisjoner og bokføringsobservasjoner | Egen tilleggsarkitektur, senest utvidet i 0.63.0/0.64.0. Nødvendigheten av denne formen er ikke godt nok dokumentert. | Stans videre utvidelse. Undersøk om kjernens dokumenter, låsing, referanser og oppbevaring dekker behovet enklere. Bevar allerede lagret historikk under oppryddingen. |
| Egen kjøpsbokføring og fradragskorreksjon | Bruker hovedboken, men legger egne handlinger og avhengigheter rundt kjernens flyt. | Prioritert gjennomgang før endring, fordi dette påvirker faktiske posteringer. Prøv kjernens alternativer og planlegg en overgang som bevarer bokførte beløp og historikk. |
| MVA-rapport, SAF-T og EHF | Norsk dekning må verifiseres mot kjernen og aktuelle eksisterende tillegg. | Avklar mangler før egen rapportmotor, eksport eller integrasjon velges. Skill bestilt direkte innsending fra det underliggende rapporteringskravet. |

Kjernedekningen for nummermodeller og regnskapsoppsett er beskrevet i
[Dolibarrs fakturaoppsett](https://wiki.dolibarr.org/index.php/Invoice_Configuration)
og [regnskapsmodul](https://wiki.dolibarr.org/index.php/Module_Accounting_Advanced).
Den konkrete vurderingen skal prøves i støttet versjon 24.0.1. Dokumentasjon
for andre versjoner eller tillegg er ikke alene bevis på installert støtte.

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
