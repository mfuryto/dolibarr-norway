# MVA-kontoer for salg og innkjøp

Fra 0.40.0 finnes «Fyll MVA-kontoer for salg og innkjøp» i veiviserens steg 4,
fra standardkontosiden og fra kontrollen av MVA-kontokoblinger.

Dolibarr lagrer to separate innstillinger:

- Standardkontoer for inngående og utgående MVA er reservekoblinger.
- Hver avgiftssats har egne felt for kontokode for salg og innkjøp i
  «MVA-kontoer» under Regnskap (avgiftssatsordboken).

Å fylle standardkontoene fyller ikke satsradens egne felt. En generell
reservekonto for høy sats erstatter heller ikke særskilte kontoer for lavere
satser. Den nye siden viser forslag før lagring:

| Ordinær innenlandsk sats | Salg: utgående MVA | Innkjøp: inngående MVA |
| --- | --- | --- |
| 25 % | 2700 | 2710 |
| 15 % | 2701 | 2711 |
| 12 % | 2703 | 2713 |

Numrene gjelder den medfølgende kontoplanen. Satsene er kontrollert mot
[Skatteetatens satser](https://www.skatteetaten.no/satser/merverdiavgift/).
Kontonavnene følger den låste kontoplanen fra
[Odoo l10n_no](https://github.com/odoo/odoo/blob/2ff84495182bc2d6e87b616731f865677a12b213/addons/l10n_no/data/template/account.account-no.csv).
Kilder kontrollert 2026-10-13.

## Bruk

1. Kontroller de aktive norske avgiftssatsene. Fra 0.41.0 finnes et separat
   [bekreftet satsoppsett for nye regnskap](vat-rates.md). Eksisterende
   regnskap og særvalg må fortsatt gjennomgås manuelt.
2. Åpne utfyllingssiden og gjennomgå hver rad. Bare tomme felt på ordinære,
   aktive norske satser uten særinnstillinger foreslås fylt. Fra 0.67.0
   gjenkjennes også kodene 3/31/33 for salg og 1/11/13 for kjøp, når sats
   og bruk samsvarer. Andre kodede behandlinger bevares for manuell vurdering.
3. Bekreft at radene gjelder ordinære innenlandske avgiftspliktige salg/kjøp,
   og at de viste kontoene er riktige. Lagre med bekreftelsesknappen.
4. Åpne «MVA-kontoer» under Regnskap og kontroller salg- og kjøpskolonnene.

Eksisterende kontokoder bevares, også ukjente eller ugyldige koder som må
rettes manuelt. Satsens bruk (salg, kjøp eller begge) respekteres.
Forhåndsvisning og lagring er bundet til administrator, foretak og gjeldende
innstillinger; samtidige endringer krever ny forhåndsvisning. Delvise feil
rulles tilbake i én transaksjon.

## Avgrensning

Hjelperen krever den medfølgende aktive norske kontoplanen og et nytt
bokføringsoppsett uten validerte kunde-/leverandørfakturaer eller hovedbok.
Eksisterende regnskap må gjennomgå kontokoblingene manuelt i Dolibarr.
Manglende, inaktive eller endrede målkontoer sperrer utfyllingen.

Ingen satsrader opprettes, aktiveres eller endres. 0 %, historiske satser
som 8 og 14 %, kodede rader, lokale avgifter, avdelingsregler og andre
særinnstillinger fylles ikke. Import og omvendt avgiftsplikt trenger egne
vurderinger og koblinger. Nullsats avgjør ikke om omsetningen er fritatt,
unntatt eller eksport.

Kontokoder avgjør ikke MVA-registrering, satsvalg på fakturalinjer,
fradragsrett eller MVA-rapportering. Innkjøpskontoene gjelder inngående MVA
med relevant fradragsrett; ingen automatisk vurdering av fradrag gjøres.
Denne leveransen er teknisk kontooppsett, ikke norsk regnskapsmessig
produksjonsaksept.
