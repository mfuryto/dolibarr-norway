# MVA på manuelle bilag

Fra modul 0.68.0 kan avgiftskode og grunnlag registreres nederst på det
vanlige bilagskortet i Regnskap. Feltene vises på manuelle MVA-linjer når
satsregisteret har en entydig, aktiv norsk kode og tilhørende MVA-konto.
Det opprettes ingen egen meny, bokføringsmotor eller kontrollkopi.

## Bruk

1. Opprett og balanser bilaget med kjernens vanlige funksjon. Åpne det
   ferdige bilaget før perioden låses eller bilaget eksporteres.
2. Under **MVA på manuelt bilag** velger du kode på selve MVA-kontolinjen.
   Kontofeltet for riktig retning i satsregisteret må være utfylt.
3. Ved utgående MVA oppgir du grunnlaget i NOK med inntil to desimaler.
   Vanlig salg har positivt grunnlag; tilbakeføring har negativt grunnlag.
   Grunnlaget må samsvare med avgiften som allerede er bokført.
4. Ved inngående MVA lar du grunnlaget stå tomt. Avgiften er beløpet som
   allerede er bokført: debet øker fradraget, kredit reduserer fradraget.
   Dette kan brukes på et separat fradragsrettingsbilag som beskrevet i
   [oppsettsveiledningen](norwegian-setup-guide.md). Feltene beregner eller bokfører
   ikke fradragsandeler.
5. Lagre opplysningene ved linjen. Velg «Ingen» og lagre for å fjerne en
   feilregistrering mens bilaget fortsatt er redigerbart.

Støttede koder er 3/31/33 for utgående og 1/11/13 for inngående MVA,
ved henholdsvis 25/15/12 prosent. Salg og kjøp av både varer og tjenester
omfattes. Satser, kontoer og retning gjenbrukes fra kjernens satsregister;
modulen gjetter ikke avgiftsbehandling fra kontonummeret alene. Manuell kode
87 er en avgrenset utvidelse beskrevet nedenfor.

Dette er opplysninger for rapportgrunnlaget. **Et fullstendig norsk MVA-uttrekk
og innsending er ikke levert ennå.** Fra 0.69.0 inngår de ordinære kodene i
[et avstembart uttrekk](vat-report.md). Fritak, unntak, import/eksport og andre
særtilfeller skal ikke registreres med en ordinær kode for å få dem med.
Kjernens eksisterende fakturabaserte MVA-rapport inkluderer fortsatt ikke
disse manuelle bilagene.

## Tilgang, låsing og endring

Lesing krever intern bruker med tilgang til bokføring. Lagring krever i
tillegg opprettings-/endringsrett. Bilag utenfor en tillatt regnskapsperiode,
med låste eller eksporterte linjer kan ikke endres her. Feltene på låste
bilag kan fortsatt leses. Midlertidige bilag og fakturaoverførte linjer
endres ikke av funksjonen.

Endres den underliggende bilagslinjen, markeres tidligere MVA-opplysninger
som endret grunnlag. De skal ikke brukes som gyldige rapportopplysninger.
Åpne det redigerbare bilaget på nytt og registrer riktige verdier. En
foreldet nettleserfane kan ikke overskrive en nyere lagring uten omlasting.
Kopiering av et bilag kopierer ikke MVA-feltene automatisk.

## Lagring og oppgradering

Skjemaversjon 2 legger til `norway_manual_vat`, med én oppføring per
foretak/hovedbokslinje: kode, grunnlag for utgående MVA, kildefingeravtrykk,
bruker og tidspunkt. Regnskapsbeløp, fakturaer og gamle kontrollkopier
kopieres eller omskrives ikke. Ingen nye revisjonsarkiver opprettes.
Grunnlagskolonnen er teknisk 0 for inngående MVA og skal ikke tolkes som
anskaffelsens grunnlag i en senere eksport.

Bruk oppdateringsskriptet som tar backup før modulaktivering. Overgangen
fra skjema 1 til 2 er additiv, med lås, kontrollsummer og strukturkontroll.
Tapt tabell etter registrert migrering blir ikke automatisk opprettet tom.
Migreringsprøven tester overgang, avbrudd og tapte tabeller; driftsprøven
ødelegger og gjenoppretter de syntetiske lagringsmarkørene fra backup.

## Konkret gap og kilde

Prøvd Dolibarr 24.0.1 har ikke avgiftskode eller grunnlag på manuelle
hovedbokslinjer. `tax_by_rate` henter fakturaer/betalinger og utelater
manuelle MVA-bilag, dokumentert i `tests/core-vat-report.php`.
Fakturalinjenes eksisterende `vat_src_code` beholdes for fakturaer.

[Skatteetatens informasjonsmodell for MVA-meldingen](https://skatteetaten.github.io/mva-meldingen/mvameldingen/informasjonsmodell/)
beskriver kode og avgiftsbeløp, og grunnlag/sats for utgående MVA. Grunnlag
og sats fylles ikke ut for inngående MVA i meldingen. Lest 2026-10-17.
Dette begrunner de manglende feltene, ikke nye brukerbekreftelser eller
full norsk regnskapsmessig produksjonsaksept.

## Tjenestekjøp uten fradragsrett: kode 87 i 0.74.0

Denne støtten gjelder **hele kjøpet ført som manuelt bilag**, ikke automatisk
behandling av en leverandørfaktura. Den gjelder MVA-registrert norsk virksomhet,
NOK-grunnlag, avgiftspliktig fjernleverbar tjeneste fra utlandet, sats 25 % og
**ingen fradragsrett**. Bruk ikke denne flyten i tillegg til en allerede
bokført faktura for samme kjøp. Delvis fradrag, andre satser, vareimport,
utenlandsk valuta og særskilt melding for uregistrerte foretak er ikke dekket.

Kjernen fører ellers både beregnet MVA og fullt fradrag i den automatiske
reverse-charge-grenen. Kode 87 er derfor lagt til i de eksisterende manuelle
MVA-feltene, uten en ny satsrad som kan velges feil på en faktura. Kodevalget
vises på **2704** når kontoen finnes entydig og aktiv i valgt, aktiv kontoplan,
foretaket bruker NOK og Norge, og «MVA brukes» er valgt. Oppdateringen oppretter
ikke bilag, avgiftsbeløp eller en ny tabell.

### Føring og dokumentasjon

1. Registrer hele kjøpet med kjernens vanlige manuelle bilagsfunksjon.
   Bruk riktig kostnads-/eiendelskonto og motkonto. Oppgi leverandørens
   dokumentreferanse, motpart og en forståelig beskrivelse i bilagets vanlige
   felt. Bevar leverandørdokumentet og vurderingsgrunnlaget sammen med
   regnskapsdokumentasjonen.
2. Beregnet norsk MVA uten fradrag inngår i kostnaden/anskaffelsesverdien.
   For et eksempel med nettokjøp **1 000 NOK** og MVA **250 NOK**:

   | Konto | Debet | Kredit |
   | --- | ---: | ---: |
   | Riktig kostnadskonto, inkludert MVA uten fradrag | 1 250 | |
   | Leverandørgjeld, eksempelvis 2400 | | 1 000 |
   | 2704, beregnet MVA på tjenestekjøpet | | 250 |

   Ingen inngående MVA føres på 2714. Konto 6790 er brukt som syntetisk
   kostnadseksempel i prøven, ikke en standard for alle tjenester. Velg også
   eventuell leverandør-/hjelpekonto i kjernen slik regnskapsoppsettet krever.
3. Åpne bilaget og velg **87 – Tjenestekjøp fra utlandet uten fradragsrett**
   på 2704-linjen under **MVA på manuelt bilag**. Oppgi nettogrunnlaget,
   **1 000** i eksempelet. Beløpene må allerede være riktig bokført og bilaget
   må balansere. Feltene klassifiserer; de beregner eller bokfører ikke MVA.
4. Ved kreditnota/tilbakeføring brukes motsatte posteringer og negativt
   grunnlag. For **200 NOK** tilbakeført: kostnad kredit 250, leverandørgjeld
   debet 200 og 2704 debet 50, med grunnlag **−200** på MVA-linjen.
5. Uttrekket viser kode 87 med utgående avgift og grunnlag, uten inngående
   fradrag. Manglende kode, endret kilde eller avvik gir uavklart uttrekk.

### Hva denne flyten gjør

Dette er hovedbok og MVA-grunnlag fra et manuelt bilag. Den oppretter ikke
leverandørfaktura, forfallsdato eller betalingsstatus i Dolibarrs fakturamodul.
Gjeld og eventuell senere betaling må håndteres og avstemmes som manuelle
poster i kjernen. Hvis du trenger leverandørfakturaens vanlige betalingsflyt,
er kode 87 på den flyten fortsatt ikke kvalifisert. Ikke velg 86 for å få
et kjøp uten fradragsrett inn i en automatisk flyt.

Koden vurderer ikke tjenestens art eller fradragsretten for deg. Regnskapets
klassifisering og kildedokumentasjon må underbygge valget, som ved andre
manuelle MVA-bilag. Kildene er [Skatteetatens tjenestekjøpsveiledning](https://www.skatteetaten.no/satser/mva-satser-kjop-av-tjenester-fra-utlandet-svalbard-og-jan-mayen/),
[SAF-T-kode 87](https://github.com/Skatteetaten/saf-t/blob/05179521e435d82feb0b2d6c89a92a32a4f2d02f/Standard%20Tax%20Codes/CSV/Standard_Tax_Codes.csv)
og [meldingskodebiblioteket](https://github.com/Skatteetaten/mva-meldingen/blob/master/docs/informasjonsmodell_filer/kodelister/mvaKodeSAFT.xml),
lest 2026-10-17. Kodebiblioteket angir bare utgående MVA for 87. Dette er
teknisk støtte for det beskrevne manuelle omfanget, ikke full MVA-melding.
