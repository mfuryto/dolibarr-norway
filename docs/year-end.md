# Årsavslutning for ENK

Arbeidsflyten er prøvd i Dolibarr 24.0.1 med NOK, kalenderår og medfølgende
norsk kontoplan. Den gjelder ENK uten regnskaps-/revisjonsplikt. Regnskapspliktige
foretak trenger også vurdering etter regnskapsloven og eventuell innsending av
årsregnskap. Se [Altinn om regnskapsplikt](https://info.altinn.no/starte-og-drive/regnskap-og-revisjon/regnskap/regnskapsplikt/).

## 1. Gjør årets registrering ferdig

Registrer fakturaer, kreditnotaer, kjøp og betalinger på vanlig måte. Overfør
salgs-, kjøps- og bankjournalene til hovedboken. Bruk hele inntektsåret, også
poster som tidligere er eksportert. Avstem bank mot kontoutskrift, kunde- og
leverandørsaldoer mot åpne dokumenter, og MVA mot leverte meldinger/oppgjør.
MVA for siste termin kan være ubetalt ved årsskiftet og skal da stå som gjeld.

**Verktøy:** Regnskap → Journaler, Hovedbok og Kontobalanse
(`/accountancy/bookkeeping/list.php`, `/accountancy/bookkeeping/balance.php`),
Bank → Kontoutskrifter, Fakturering → Kunde-/leverandørfakturaer.

[Altinn om dokumentasjon av balansen](https://info.altinn.no/starte-og-drive/regnskap-og-revisjon/regnskap/dokumentasjon-av-balansen-avstemminger/)
beskriver avstemmingsgrunnlag og oppbevaring. Ta vare på årsrapportene og
underlagene privat sammen med regnskapsmaterialet.

## 2. Bokfør de årsjusteringene som faktisk gjelder

Bruk kjernens **Nytt bilag** (`/accountancy/bookkeeping/card.php?action=create`),
datert 31. desember, med underlag og en forståelig beskrivelse. Dette er
ordinære årsoppgjørsposteringer, ikke omklassifisering av hver faktura.

| Oppgave | Eksempel fra den medfølgende kontoplanen |
| --- | --- |
| Betalt leie som gjelder neste år | Debet 1700 Forskuddsbetalt leiekostnad, kredit 6300 Leie lokale. Bare neste års del flyttes. Reverser/oppløs når kostnaden påløper neste år. |
| Varelager | Varetell og verdsett etter gjeldende skatteregler; endring mot 1460/4390. Registrer bare endringen fra allerede bokført verdi. |
| Driftsmidler | Avstem anskaffelser, salg/uttak, saldo og årets avskrivning. Kontormaskin på 1280 kan ha avskrivning mot 6030. Konto og sats velges etter eiendelen, ikke fordi eksemplet bruker dem. |
| Privatuttak, innskudd og personlig skatt | Avstem aktuelle 206x-/207x-kontoer og overfør årets netto bevegelser til 2050. Debetsaldo på 2070 nullstilles med kredit 2070/debet 2050. Kreditsaldo føres motsatt. Dette endrer ikke resultatet. |

[Krav til inntektens og kostnadens år](https://www.skatteetaten.no/bedrift-og-organisasjon/skatt/skattemelding-naringsdrivende/fradrag/inntekt-formue-gjeld/inntekt-i-enkeltpersonforetak/)
og [Skatteetatens avskrivningsveiledning](https://www.skatteetaten.no/bedrift-og-organisasjon/skatt/skattemelding-naringsdrivende/fradrag/eiendeler-utstyr-eiendom/bedriftens-eiendeler/kjope-eiendeler-til-bedriften/)
er grunnlag for vurderingene. Saldoavskrivning skjer per relevant saldo;
kjernens ordinære anleggsregister er ikke kvalifisert som norsk skattemessig
saldoregister. Bruk årets saldoopplysninger i næringsspesifikasjonen og bokfør
den avstemte årsavskrivningen én gang. Ikke erstatt dette med automatisk lineær
avskrivning. Tidsbegrensede rettigheter og programvareanskaffelser må vurderes
etter sin faktiske art. [Skatteetatens satser](https://www.skatteetaten.no/satser/avskrivningssatser/) velges for riktig inntektsår.

## 3. Avstem skatt og resultat før disponering

Følg [skattemelding og næringsspesifikasjon](enk-tax-return.md) før du låser
året. Ta vare på resultatet **før resultatdisponering**. Personlig
forskuddsskatt er verken fradragsberettiget kostnad eller selskapsskatt.

For ENK brukes normalt **2050** som mottaker for både overskudd og underskudd
i Regnskap → Innstillinger → Avslutningsoppsett
(`/accountancy/admin/closure.php`). Velg journal for inngående balanse og
kontoklasser 1–2 som balanse, 3–8 som resultat.

Vil du disponere resultatet i det avsluttede året, føres overskudd som debet
8800/kredit 2050 og underskudd motsatt. Bruk resultatet etter alle årsjusteringer.
Deretter er netto i resultatklassene null, og kjernen overfører bare balansen.
**Ikke før en ekstra resultatpost i neste år.** Kjernen kan alternativt
føre et udisponert resultat til valgt egenkapitalkonto i inngående balanse;
de to metodene skal gi samme egenkapital, ikke dobbel disponering.
[Standardkontoer](standard-accounts.md) forklarer kjernens felt.

## 4. Valider, avslutt og kontroller inngående balanse

Ta backup. Opprett neste regnskapsår i kjernens periodeoppsett. Åpne
**Regnskap → Årsavslutning** (`/accountancy/closure/index.php`):

1. Velg året og valider ferdige bevegelser i kjernens dialog.
2. Avslutt mot det påfølgende året med generering av inngående balanse og
   **separate hjelpekontoer/reskontro** valgt.
3. Kontroller at inngående saldo per konto og kunde/leverandør stemmer med
   utgående saldo, med disponert resultat i egenkapital. Resultatkontoene
   skal begynne på null. Avskrivningssaldo og åpne dokumenter beholdes.

Fra 0.84.0 sperrer et lite vern på den native handlingen gjentatt/samtidig
avslutning, feil periodeskifte, ukjente kontogrupper, ubalanse og generering
uten reskontro. Det oppretter ingen egen avslutningsmodell eller meny.
Oppdateringen avslutter aldri et år automatisk.

## Retting

Før validering og saldooverføring brukes kjernens ordinære rettingsfunksjoner.
Etter låsing korrigeres med dokumentert nytt bilag i tillatt åpen periode;
gamle validerte poster beholdes. Vurder om tidligere skattemelding/MVA-melding
må endres. En materiell feil kan kreve at rettingen tilordnes et tidligere
inntektsår. Da må bokføringsdato, rapportering og allerede overførte saldoer
avstemmes særskilt; et inneværende års kostnadsbilag løser ikke automatisk
skattemessig tidfesting. Modulen tilbyr ikke automatisk omavslutning eller
ny generering over eksisterende inngående balanse.

## Prøvebevis og begrensninger

`tests/year-end-cycle.php` prøver et helt syntetisk 2025-år: bank 172 250,
periodisert leie 3 000/forhåndsbetalt 9 000, lager 5 000, kontormaskin
50 000/avskrivning 15 000/rest 35 000, privatuttak/skatt 20 000, åpne
kundefordringer 2 500 og leverandørgjeld 1 250. Resultat 42 250,
skattegrunnlag i eksemplet 43 000, disponert egenkapital 222 250. Native
saldooverføring er balansert og bevarer reskontro. I 2026 oppløses forskuddsbetalt leie 9 000, neste saldoavskrivning er
10 500 og et dokumentert rettingsbilag er 100. Underskudd 19 600 overføres
én gang av kjernen, uten separat disponeringsbilag, til egenkapital 202 650. Lukket år sperrer ny postering; historikken
bevares. `tests/year-end-http.php` prøver også faktisk native avslutningsdialog,
konkurrerende kall og gjentatt innsending.

Kjerneundersøkelse: `assetdepreciationoptions.class.php` har degressive valg,
men `Asset::generateDepreciation()` i den låste versjonen fordeler et
avskrivningsgrunnlag over varighet og periodiserer etter dato. Det er ingen
kvalifisert norsk samlesaldo med årets tilganger/salg og beløpsgrense i denne
flyten. Derfor brukes portalens saldoopplysninger og et ordinært årlig bilag,
slik at den eksisterende regnskapsmotoren beholdes. Et automatisk norsk
saldoregister er et faktisk funksjonsgap, ikke noe som erklæres levert av
kontoplanen eller av denne prøven.

Dette kvalifiserer den beskrevne ENK-arbeidsflyten. AS, regnskapspliktig ENK,
årsregnskapsinnsending, kompliserte driftsmiddelsalg, særnæringer og automatisk
skattemessig anleggsregister inngår ikke. Se [portalveiens omfang](enk-tax-return.md).
