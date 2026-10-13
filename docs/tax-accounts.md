# Skattekontoer under Regnskap

«Skattekontoer» i Dolibarr er en liste over skatte- og avgiftstyper med
kontokoblinger for skatt-/avgiftsmodulen. Dette er en egen ordbok, ikke selve
kontoplanen. Den norske modulen oppretter ingen typer eller koblinger her.

Fra 0.40.1 finnes veiledningen på standardkontosiden og som valgfri hjelp i
veiviserens steg 4. Dette er informasjon; oppsett og bilag endres ikke.

| Sted | Hva det brukes til |
| --- | --- |
| Kontoplan | Kontoene som regnskapet kan bokføres på |
| Standardkontoer | Reservekoblinger for kunder, leverandører, varer, tjenester og avgifter |
| MVA-kontoer | MVA-satsradens egne salg- og kjøpskontoer |
| Skattekontoer | Kontokoblinger for typer i Dolibarrs skatt-/avgiftsmodul |
| Lokal skatt 1/2 | Kjernens egne lokale avgiftsmekanismer; ingen generell norsk MVA-kobling |

## Hva skal administrator gjøre?

Hvis skatt-/avgiftsmodulens registrering av skattekrav ikke brukes, kan
«Skattekontoer» stå urørt. En tom liste hindrer ikke vanlig fakturering og
betyr ikke at kontoplanen mangler kontoer for skatt.

Hvis funksjonen skal brukes, vurder først den konkrete skatte-/avgiftstypen,
foretaksformen, eventuell fradragsrett og riktig motkonto. Kontroller
bokføringen funksjonen faktisk gir før ordinær bruk. Ikke opprett tilfeldige
typer eller bruk én konto for alle skatter. Eksisterende typer og koblinger
skal gjennomgås før endringer; de slettes ikke av den norske modulen.

MVA settes opp separat via «MVA-kontoer» og [MVA-veiledningen](vat-accounts.md).

## Enkeltpersonforetak uten ansatte

Vanlig fakturering krever ingen utfylling av «Skattekontoer».
Forskuddsskatt i ENK gjelder innehaveren personlig. Når den betales fra
foretakets bankkonto, brukes normalt **2070 Forskuddsskatt** i den
medfølgende kontoplanen, mot den faktiske bankkontoen. Betalingen reduserer
ikke virksomhetens overskudd. Det krever ikke at en egen skatte-/avgiftstype
opprettes i Dolibarrs liste.

Konto **2072 Tilleggsforskudd/restskatt** finnes også i kontoplanprofilen;
klassifiser betalingen etter faktisk grunnlag. Ingen automatisk bokføring
eller skatteberegning følger av at kontoene finnes.

## Andre foretaksformer og ansatte

ENK-eksempelet er ikke en generell skattekobling for AS eller andre
foretaksformer. Selskapsskatt, arbeidsgiveravgift, forskuddstrekk og andre
forpliktelser må vurderes separat. Denne leveransen omfatter ikke norsk
lønn, skatteberegning eller innrapportering, og gir ingen produksjonsaksept
for disse funksjonene.

## Kilder og teknisk grunnlag

Kontrollert 2026-10-13:

- [Skatteetaten: Forskuddsskatt for enkeltpersonforetak](https://www.skatteetaten.no/bedrift-og-organisasjon/skatt/forskuddsskatt/forskuddsskatt-for-enkeltpersonforetak)
  beskriver personlig forskuddsskatt og felles skattekort.
- [Conta: Konto 2070](https://conta.no/kontohjelp/2070-forskuddsskatt)
  forklarer bruk når skatten betales fra foretakets bankkonto og at den ikke
  påvirker virksomhetens kostnader.
- Kontonumre og navn kontrolleres mot den låste norske
  [kontoplanprofilen](standard-accounts.md).
- Dolibarr 24.0.1: `htdocs/core/menus/standard/eldy.lib.php` peker
  `MenuTaxAccounts` til ordbok 7, mens `MenuVatAccounts` peker til ordbok 10.
  `htdocs/admin/dict.php` definerer disse som `c_chargesociales` og `c_tva`.
  Dette er teknisk skille mellom sidene, ikke godkjenning av norsk
  skattebehandling i kjernen.
