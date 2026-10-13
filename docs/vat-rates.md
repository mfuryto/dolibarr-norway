# Ordinært MVA-satsoppsett for nye regnskap

Leveranse A1 i [arbeidsplanen](delivery-plan.md), fra 0.41.0.
Kjernen 24.0.1 leverer 0, 8, 14 og 25 % for Norge. Gjeldende generelle
kategorier er 25, 15 og 12 % ifølge
[Skatteetaten](https://www.skatteetaten.no/satser/merverdiavgift/), kontrollert
2026-10-13. Dette er satskategorier, ikke regler for hvert enkelt bilag.

## Arbeidsflyt

1. Velg den medfølgende norske kontoplanen.
2. Veiviser → steg 4 → «Sett opp ordinære norske MVA-satser».
3. Gjennomgå forhåndsvisningen. Manglende ordinære 25/15/12-rader opprettes
   for salg/kjøp. Ordinære ubrukte 8/14-rader deaktiveres, uten sletting.
4. Bekreft at dette er et nytt bokføringsoppsett og at endringene er riktige.
5. Fortsett til [MVA-kontoene](vat-accounts.md). Satsoppsettet fyller ikke
   kontokoder; eksisterende kontokoder og standardvalg beholdes.

Det skjer ingen endring ved åpning eller vanlig moduloppdatering.
Administrator, regnskapsrettighet, gyldig token, tidsbegrenset engangsbekreftelse
og uendret forhåndsvisning kreves. Lagringen er atomisk, og feil rulles tilbake.

## Vern og avgrensning

Bare norske rader i aktivt foretak behandles. Utenlandske rader og 0 %
beholdes. Eksisterende ordinære aktive rader bevares, inkludert kontokoder og
standardvalg. Ingen satser omskrives til andre prosenter. Gamle rader
slettes ikke. Manglende rader får ikke automatisk standardmarkering.

Oppsettet sperres ved enhver kunde-/leverandørfaktura, tilbud, kunde-/
leverandørordre eller hovedbok i foretaket, også utkast. Produkter/tjenester
med 8/14 % sperrer også. Dette vernet er strengere enn en kontroll av bare
bokførte fakturaer. Et nytt regnskap kan allerede ha utkast; administrator må
da gjennomgå oppsettet manuelt uten å slette dokumenter for å omgå vernet.

Inaktive eller spesielle eksisterende rader for de aktuelle satsene krever
manuell gjennomgang. Aktive gamle 8/14-rader med kode, særavgift, kontokode
eller standardmarkering sperrer automatisk oppsett. Ingen særvalg overstyres.
Et deaktivert satsregister endrer ikke lagret sats på eksisterende kort eller
bilag; derfor kreves gjennomgang av eksisterende bruk før endringer.

Hjelperen modellerer ikke historiske gyldighetsperioder og velger ingen sats
for en transaksjon. 0 % kan gjelde ulike behandlinger. Registrering, fritak,
unntak, fradragsrett, import og omvendt avgiftsplikt vurderes separat.

Dette er teknisk satsoppsett. A2 i planen kvalifiserer neste del av
konto-/avgiftsfordelingen før bokført grunnlag og MVA-rapportering utvikles.
