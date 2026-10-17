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
MVA-melding. Manuelle bilag har fortsatt ikke egne norske avgiftsfelt i kjernen.
Rapporteringen må bruke faktisk bokført fradrag og kunne behandle disse bilagene
før fullstendighet eller innsending kan godkjennes.

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
nullsatsbilag, kjøp uten MVA og utenlandskjøp/-salg er fortsatt ikke kvalifisert.
Import/eksport skal ikke presses inn under de innenlandske kodene.
