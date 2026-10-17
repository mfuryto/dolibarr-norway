# Norske MVA-satser og koder

Fra 0.67.0 bruker oppsetthjelpen kjernens eksisterende satsregister til
separate norske koder for ordinært innenlandsk salg og kjøp.
[Skatteetatens meldingsmodell og kodeliste](https://skatteetaten.github.io/mva-meldingen/mvameldingen/informasjonsmodell/)
er kodegrunnlaget. En sats alene klassifiserer ikke avgiftsbehandlingen.

| Sats | Salgskode / bruk | Kjøpskode / bruk | Utgående / inngående konto i medfølgende plan |
| --- | --- | --- | --- |
| 25 % | 3 / salg | 1 / kjøp | 2700 / 2710 |
| 15 % | 31 / salg | 11 / kjøp | 2701 / 2711 |
| 12 % | 33 / salg | 13 / kjøp | 2703 / 2713 |

Kjøpskodene gjelder ordinært innenlandsk fradragsberettiget inngående MVA.
Fradragsrett og andel må vurderes særskilt. Denne tabellen er ikke et komplett
oppsett for nullsats, fritak, unntak, utenlandshandel eller omvendt avgiftsplikt.
Ingen kode velges automatisk bare fordi en linje har 0 prosent.

## Nytt, ubrukt oppsett

1. Velg den medfølgende norske kontoplanen.
2. Åpne Norsk regnskapsoppsett → Norske MVA-satser og koder.
3. Forhåndsvis de seks radene med sats, salg/kjøp og kode. Bekreft én gang
   dersom dette er et ubrukt oppsett og radene passer virksomheten.
4. Fortsett til MVA-kontoene. Den eksisterende kontohjelpen kjenner igjen
   de seks kodede radene og viser bare feltet for aktuell salgs-/kjøpsretning.

Koder lagres i `c_tva.code`, med `type_vat=1` for salg og `2` for kjøp.
Kjernen bevarer valgt kode i fakturalinjens `vat_src_code`. Det opprettes
ingen separat kodekobling, historikkmodell eller kontrollkopi.

Ubrukte, ukodede standardsatser på 25/15/12 og gamle 8/14 deaktiveres uten
sletting. 0 prosent og øvrige kodede særvalg beholdes. Eksisterende kontoer,
standardmarkeringer, konflikt, inaktiv standardkode og særregler overstyres ikke.
Kodeoppsettet velger ingen ny standardmarkering og fyller ikke kontoene selv.

## Eksisterende regnskap eller produkter

Automatisk omlegging er sperret når foretaket allerede har produkter,
fakturaer, tilbud, ordre eller hovedbok. Oppdatering av modulen gjør ingen
omlegging av disse dataene. Ikke slett dokumenter for å omgå vernet.

Bruk kjernens MVA-register fra lenken på samme side. Ved manuell gjennomgang
kan egne rader opprettes med sats, kode og bruk som i tabellen. Vurder når
ukodede rader skal deaktiveres for nye valg, og angi riktige MVA-kontoer.
Ikke skriv om gamle fakturalinjer eller gjenbruk en historisk kode til en
annen behandling. Eksisterende produkter og leverandørpriser må gjennomgås
for fremtidige dokumenter; gamle dokumenter beholder sine lagrede opplysninger.

## Standardvalg i skjermbildene

Kjernens generelle standardoppslag i 24.0.1 tar ikke hensyn til salg/kjøp.
Modulen bruker derfor en avgrenset hook rundt kjernens egen MVA-valgliste:

- Tomt ordinært norsk salgs-/kjøpsfelt får kodet standard for riktig retning,
  når en entydig, aktiv og ordinær kode allerede finnes i satsregisteret.
- Nye produktkort og salgsprisfelt viser salgsretningen. Et lagret eller
  eksplisitt innsendt kjøpskodevalg på et eksisterende produkt endres ikke
  automatisk; det krever manuell gjennomgang.
- Eksplisitte fakturalinjevalg, nullsats, NPR, særkode og utenlandske
  motparter blir ikke automatisk omklassifisert.

Dette er standardvalg i de prøvde native skjermbildene. Direkte kall til
kjernens generelle `get_default_tva()` uten skjermbildets hook kan fortsatt
gi en annen kode. API-/importklienter må angi riktig kode uttrykkelig.
Ingen kjernefil endres.

## Begrensning

Dette er nødvendige kildeopplysninger for norsk rapportering, ikke en ferdig
MVA-melding. Kjernen mangler egne norske avgiftsfelt for manuelle bilag; modulen har
[avgrenset støtte på det vanlige bilagskortet](manual-vat.md). Rapporteringen
må bruke faktisk bokført fradrag; fullstendighet og innsending gjenstår.

## Innenlands salg uten beregnet MVA fra 0.70.0

Aktivering og oppdatering gjør manglende **5/6/7** tilgjengelige som separate
salgsrader med teknisk sats 0 i kjernens register. Dette er en append-only
endring: eksisterende rader, deaktiveringer, kontovalg, standardvalg og
historiske dokumenter beholdes. En eksisterende konflikt overskrives ikke.
Gjenta aktivering oppretter ikke flere rader. Ingen kode velges automatisk
på en faktura eller et produkt, og ingen MVA-konto fylles på nullradene.

| Valg på fakturalinjen | Betydning | I det støttede uttrekket |
| --- | --- | --- |
| `0 (5)` | Innenlands fritak med nullsats | Grunnlag for kode 5, ingen avgift |
| `0 (6)` | Omsetning utenfor MVA-loven | Grunnlag for kode 6, ingen avgift; ikke kalt nullsats |
| `0 (7)` | Inntekt som ikke er MVA-omsetning | Vises separat; ikke meldingsgrunnlag |

Velg riktig behandling i den vanlige MVA-listen på fakturalinjen. En
ukodet `0` blir ikke tolket som 5, 6 eller 7. Behold riktig inntektskonto
på varen/tjenesten eller i det native konto-oppsettet; MVA-koden er ikke
en inntektskonto. Beskriv behandlingen i vanlig linjetekst/merknad og
oppbevar relevant dokumentasjon ved dokumentet. Ingen ny begrunnelsesside
eller bekreftelsesrunde innføres.

Dette gjelder innenlandske fakturaer/kreditnotaer i NOK i det prøvde,
MVA-registrerte oppsettet. Vilkårene for fritak/unntak og fradragsrett avgjøres
ikke av kodevalget. Rene unntatte foretak, registreringsovergang, manuelle
nullsatsbilag og utenlandskjøp er fortsatt ikke kvalifisert. Eksport med kode 52 er
beskrevet nedenfor.
Import/eksport skal ikke presses inn under de innenlandske kodene.

## Kjøp uten MVA-behandling i 0.71.0

På en innenlandsk leverandørfaktura i NOK velges **`0 (NO0)`** når kjøpet
ikke har MVA-behandling. Kodevalget settes på den vanlige fakturalinjen;
kostnadskonto velges som ellers for varen eller tjenesten. Kode 0 gir ingen
fradragsført MVA og rapporteres ikke i MVA-meldingen. Uttrekket viser beløpet
separat. Kreditnota bruker kjernens vanlige kreditnotaflyt.

NO0 er et fast lokalt navn for norsk kode 0, uten eget koblingsregister.
Dolibarr 24.0.1 behandler teksten «0» som tom ved visning og lagring; NO0
bevares av den native satsvelgeren, linjelagringen og redigeringen.
Uttrekket oversetter bare dette eksplisitte valget til 0. Et eksisterende
ukodet nullvalg eller en bokstavelig kode 0 konverteres ikke automatisk.
Manglende NO0 opprettes ved oppdatering som aktiv kjøpsrad, uten MVA-konto
eller standardvalg. Eksisterende NO0-rader, også deaktiverte eller feilaktige,
beholdes og må eventuelt rettes i kjernens satsregister.

Bruk ikke NO0 til utenlandskjøp, import, omvendt avgiftsplikt eller som en
snarvei for kjøp med MVA der fradragsretten er begrenset/mangler. For sistnevnte
brukes [den prøvde fradragsrettingen](norwegian-setup-guide.md). Ingen nullklassifisering
velges automatisk. Manuelt førte nullbilag er fortsatt utenfor uttrekkets omfang.

## Eksportkode 52 i 0.72.0

Aktivering/oppdatering legger til en manglende norsk salgsrad for **`0 (52)`**,
uten MVA-konto eller standardvalg. Eksisterende rader, deaktiveringer,
kontovalg og fakturaer beholdes. Ingen omklassifisering skjer ved oppdatering.

Bruk kjernens vanlige kundefaktura eller kreditnota i NOK. Registrer riktig
mottaker og utenlandsk adresse, velg varen/tjenesten og dens inntektskonto,
og velg `0 (52)` på de linjene som oppfyller vilkårene for eksportfritak.
For handelsvarer peker den medfølgende kontoplanens eksportforslag på 3105,
og for tjenester på 3125; andre varer/behandlinger kan kreve andre kontoer.
Koden setter ikke inntektskontoen. Bruk vanlig linjetekst/offentlig merknad
for relevant fritaksgrunnlag, og oppbevar dokumentasjonen som vanlige vedlegg.
Dokumentene kan fortsatt lages på norsk eller engelsk. Det innføres ingen
egen begrunnelsesside eller godkjenningsrunde.

**Varer:** § 6-21 gjelder omsetning ut av merverdiavgiftsområdet.
Skatteetaten beskriver krav til salgsdokument, deklarasjon og attest for
utførsel, med egne regler for varer unntatt fra deklareringsplikt. En
utenlandsk fakturaadresse alene dokumenterer ikke at varen er utført.

**Tjenester:** For fjernleverbare tjenester etter § 6-22 annet ledd må
mottakeren være hjemmehørende utenfor merverdiavgiftsområdet. Tjenestens
art og faktisk mottaker må vurderes. Dette er ikke et generelt fritak for
alle tjenester til en utenlandsk kunde. Beskriv behandlingen konkret,
for eksempel henvisning til § 6-22 annet ledd når denne gjelder.

Det støttede uttrekket omfatter eksplisitt kode 52 på fakturaer/kreditnotaer
til utenlandske kunder, både varer og tjenester. SE og US er syntetisk prøvd.
Beløpet avstemmes mot bokføring/reskontro og inngår som kode 52-grunnlag
med null avgift. Ukodet nullsats blir ikke eksport. Det samme gjelder 5/6/7.
Norske mottakere med særskilte eksportforhold, blanding av 52 og andre
avgiftsbehandlinger på en utenlandsfaktura, utenlandsk MVA, omvendt avgiftsplikt,
utenlandskjøp og manuelle eksportbilag er fortsatt utenfor prøvd omfang.
Koden bekrefter ikke at fritaksvilkår eller dokumentasjonskrav er oppfylt.

Kilder, lest 2026-10-17:
- [Skatteetatens SAF-T-koder, kode 52](https://github.com/Skatteetaten/saf-t/blob/05179521e435d82feb0b2d6c89a92a32a4f2d02f/Standard%20Tax%20Codes/CSV/Standard_Tax_Codes.csv).
- [Utførsel av varer og dokumentasjon](https://www.skatteetaten.no/rettskilder/type/handboker/merverdiavgiftshandboken/gjeldende/M-6/M-6-21/M-6-21.2/).
- [Fjernleverbare tjenester, § 6-22 annet ledd](https://www.skatteetaten.no/rettskilder/type/handboker/merverdiavgiftshandboken/gjeldende/M-6/M-6-22/M-6-22.4/).
