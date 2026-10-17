# MVA-kontoer for salg og innkjøp

Bruk kjernens **MVA-kontoer** under Regnskap (avgiftssatsordboken).
Fra 0.77.0 er modulens separate utfyllingsside fjernet. Lagrede koblinger
beholdes; egne kontonavn og eksisterende regnskap sperrer ikke kjernens oppsett.

Dolibarr lagrer to separate innstillinger:

- Standardkontoer for inngående og utgående MVA er reservekoblinger.
- Hver avgiftssats har egne felt for kontokode for salg og innkjøp i
  «MVA-kontoer» under Regnskap (avgiftssatsordboken).

Å fylle standardkontoene fyller ikke satsradens egne felt. En generell
reservekonto for høy sats erstatter heller ikke særskilte kontoer for lavere
satser. For den medfølgende planen brukes disse kontoene for ordinære avgiftspliktige innenlandske transaksjoner:

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

1. Opprett manglende [norske kodevalg](vat-rates.md) ved behov.
2. Åpne den aktuelle satsraden i kjernens MVA-register.
3. Velg riktig salg-/kjøpskonto og lagre i det vanlige skjemaet.

Kodene 3/31/33 gjelder salg og 1/11/13 kjøp, i henholdsvis høy, middels og lav
sats. Kontofeltene skal samsvare med radens retning og behandling. Nullkoder og
omvendt avgiftsplikt har egne regler; ikke kopier de ordinære kontoene ukritisk.
Eksisterende kontokoder overskrives ikke av moduloppdatering. Endring av et
kontooppsett flytter ikke gamle hovedboksposter, men kan påvirke dokumenter som
ennå ikke er overført. Se [samlet veiledning](norwegian-setup-guide.md).
