# MVA på manuelle bilag

Nye kjøp i støttet omfang registreres fra 0.79.0 som [vanlig leverandørfaktura
med fradragsandel](purchase-vat.md). Rutinene for separat fradragsretting og
hele manuelle 87-kjøp nedenfor beholdes for eldre bilag og reservebruk.

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

**Manuell reservevei:** Normal leverandørfakturaflyt uten fradrag gjenstår.
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

## Delvis fradrag på tjenestekjøp: kode 86 i 0.75.0

**Manuell reservevei:** Dette lukker ikke gapet for delvis fradrag i vanlig
registrering og bokføring. Kreditnota krever en separat, motsatt retting.

Kjernens automatiske kjøpsjournal fører fullt fradrag. Fra 0.75.0 kan et
separat, vanlig rettingsbilag redusere dette fradraget med kode **86** på
**2714**. Leverandørfakturaen, beregnet MVA og betalingsoppfølgingen beholdes.
Ingen ny bokføringsmotor, side, tabell eller prosentberegner innføres.

Dette gjelder den [prøvde fakturaflyten med kode 86](vat-rates.md#tjenestekjøp-med-omvendt-avgiftsplikt-i-0730):
MVA-registrert norsk virksomhet, NOK, avgiftspliktige fjernleverbare tjenester
fra utlandet og 25 %. Det er ikke automatisk delvis fradrag på fakturaen.
Faglig vurdering og beregning av fradragsandelen gjøres før bokføring.

1. Registrer og bokfør leverandørfakturaen med kode 86 og omvendt avgiftsplikt
   som beskrevet i fakturaflyten. Kontroller at kjernen har ført hele beregnet
   avgift kredit 2704 og fullt fradrag debet 2714. Ikke bokfør kjøpet på nytt.
2. Før den delen som **ikke** kan fradragsføres fra 2714 til riktig kostnads-/
   eiendelskonto i et separat bilag. Ved nettokjøp **1 000 NOK**, beregnet MVA
   **250 NOK** og **50 % fradragsrett** er rettingen:

   | Konto | Debet | Kredit |
   | --- | ---: | ---: |
   | Riktig kostnads-/eiendelskonto | 125 | |
   | 2714, reduksjon av inngående MVA | | 125 |

   Ikke før noe på 2704 eller leverandørgjelden i dette rettingsbilaget.
   Oppgi faktura-/bilagsreferanse og forklar fradragsfordelingen i de vanlige
   bilagsfeltene. Bevar beregningen sammen med regnskapsdokumentasjonen.
3. På rettingsbilagets 2714-linje velger du **86** under **MVA på manuelt bilag**.
   La grunnlaget stå **tomt**. Lagre. Kontoen må være entydig og aktiv i valgt,
   aktiv kontoplan; foretaket må være norsk, bruke NOK og ha «MVA brukes» valgt.
4. Før rettingen i riktig MVA-periode sammen med kjøpet. Uttrekket skal vise
   fullt utgående grunnlag **1 000**, beregnet avgift **250**, inngående fradrag
   **125** og dermed **125 NOK** netto MVA for eksempelet. Kjøpet viser fullt
   fradrag inntil rettingsbilaget er bokført og klassifisert.
5. En kreditnota krever også motsatt retting av den opprinnelige fradrags-
   reduksjonen. Ved tilbakeføring av **200 NOK** med samme 50 % fradragsandel
   fører kjernen avgift **−50** i begge retninger. Rett deretter debet 2714
   **25** og kredit kostnad **25**, med kode 86 og tomt grunnlag på 2714.

Uttrekket summerer fakturaens inngående avgift og rettingsbilagets faktiske
beløp; det velger eller kontrollerer ikke fradragsprosenten. Det oppretter
heller ingen kobling som automatisk retter ved senere kreditnota. Referanser,
periode og avstemming må derfor ivaretas i vanlig bokføring. Endret kilde,
manglende kode og inaktiv konto gir uavklart uttrekk uten netto/CSV.

Manuell kode 86 her gjelder bare inngående fradragsretting på 2714. Hele
manuelle kjøp med 86, andre satser, varer og valutafakturaer er ikke kvalifisert.
Kjøp helt uten fradragsrett følger fortsatt den beskrevne kode 87-flyten.

[Merverdiavgiftshåndboken § 8-2](https://www.skatteetaten.no/rettskilder/type/handboker/merverdiavgiftshandboken/gjeldende/M-8/M-8-2/)
beskriver fradrag for anskaffelser delvis til bruk i registrert virksomhet.
[Skatteetatens kodebibliotek](https://github.com/Skatteetaten/mva-meldingen/blob/master/docs/informasjonsmodell_filer/kodelister/mvaKodeSAFT.xml)
angir både inngående og utgående MVA for kode 86. Sammen med
[informasjonsmodellen](https://skatteetaten.github.io/mva-meldingen/mvameldingen/informasjonsmodell/)
er dette grunnlaget for å rapportere faktisk inngående fradrag uten å gjenta
utgående grunnlag. Lest 2026-10-17. Føringsmåten over er prosjektets avgrensede
anvendelse av kjernens rettingsbilag, ikke en særskilt prosedyre pålagt av kildene.
