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

## Forskuddsskatt gjennom året

Status 2026-10-18: kontoene finnes og arbeidsbeskrivelsen er dokumentert.
Den konkrete bank-/bilagsflyten skal fortsatt prøves samlet i etappe 7 av
[leveranseplanen](delivery-plan.md), før årsavslutning. Dette er ikke en
ferdigmelding av regnskapsstøtten.

1. Oppgi forventet årlig overskudd under «Næring» i ditt personlige
   skattekort hos Skatteetaten. Oppdater anslaget ved endringer; regnskapets
   resultat er grunnlag for vurderingen, ikke en full personlig skatteberegning.
2. Betal etter Skatteetatens krav og godkjente endringer. Ordinære frister er
   15. mars, 15. juni, 15. september og 15. desember, med justering for
   helg/helligdag. Bruk betalingsopplysningene på det aktuelle kravet.
   En innsendt endring i skattekortet er ikke i seg selv godkjent reduksjon.
3. Betalt fra foretakets bank: bokfør betalingen én gang som debet **2070**
   og kredit bankens faktiske hovedbokskonto, uten MVA. Eksempel: 10 000 kroner
   betalt fra en bank koblet til 1920 gir debet 2070 og kredit 1920 med
   10 000 kroner. Bruk kjernens bank-/bilagsføring; ikke registrer en vanlig
   leverandørkostnad i tillegg. Oppbevar krav og betalingsunderlag med bilaget
   og avstem mot bank og hovedbok.
4. Betalt privat: personlig skatt trenger ikke registreres i foretaksregnskapet.
   Ikke før uttak fra foretaksbanken når ingen slik betaling har skjedd.

En eventuell reserve på en annen av foretakets bankkonti er bare en intern
bankoverføring, ikke betalt skatt. Konto 2070 brukes først ved skattebetalingen.
En fast reserveprosent erstatter ikke Skatteetatens personlige beregning.
Forskuddsskatt er separat fra MVA-melding og MVA-oppgjør.

Tilleggsforskudd og restskatt må skilles fra ordinære terminer og fra eventuelle
renter. Konto 2072 finnes til skattebeløpet; ikke bruk den ukritisk på hele
kravet. Skatteetaten beskriver tilleggsforskudd innen 31. mai året etter som
mulighet for å unngå renter på restskatt. Årsavslutningen skal senere avstemme
personlige skattebetalinger som egenkapitalbevegelser, ikke driftskostnader.

## Andre foretaksformer og ansatte

ENK-eksempelet er ikke en generell skattekobling for AS eller andre
foretaksformer. Selskapsskatt, arbeidsgiveravgift, forskuddstrekk og andre
forpliktelser må vurderes separat. Denne leveransen omfatter ikke norsk
lønn, skatteberegning eller innrapportering, og gir ingen produksjonsaksept
for disse funksjonene.

## Kilder og teknisk grunnlag

Kontrollert 2026-10-18:

- [Skatteetaten: Forskuddsskatt for enkeltpersonforetak](https://www.skatteetaten.no/bedrift-og-organisasjon/skatt/forskuddsskatt/forskuddsskatt-for-enkeltpersonforetak)
  beskriver personlig forskuddsskatt og felles skattekort.
- [Conta: Konto 2070](https://conta.no/kontohjelp/2070-forskuddsskatt)
  forklarer bruk når skatten betales fra foretakets bankkonto og at den ikke
  påvirker virksomhetens kostnader.
- [Conta: Bokføre skatteoppgjør](https://hjelp.conta.no/regnskap/bilag/bokfore-skatteoppgjor/)
  viser ordinær bilagsføring mot 2070 og bank.
- [Fiken: Skatt for enkeltpersonforetak](https://hjelp.fiken.no/skatt-for-enkeltpersonforetak)
  skiller personlig skatt fra foretakets kostnader og registrering ved privat betaling.
  Disse brukes som arbeidsflytinspirasjon; Skatteetaten er regelkilden.
- Kontonumre og navn kontrolleres mot den låste norske
  [kontoplanprofilen](standard-accounts.md).
- Dolibarr 24.0.1: `htdocs/core/menus/standard/eldy.lib.php` peker
  `MenuTaxAccounts` til ordbok 7, mens `MenuVatAccounts` peker til ordbok 10.
  `htdocs/admin/dict.php` definerer disse som `c_chargesociales` og `c_tva`.
  Dette er teknisk skille mellom sidene, ikke godkjenning av norsk
  skattebehandling i kjernen.
