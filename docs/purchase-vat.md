# Kjøp og MVA-fradrag fra 0.79.0

Registrer kjøpet som en vanlig leverandørfaktura. På hver linje finnes feltet
**MVA-fradrag (%)**. Velg behandlingen der; bruk deretter kjernens vanlige
kontokobling og kjøpsjournal. Ingen separat fradragsvurdering, godkjenning eller
rettingsbilag er nødvendig for nye kjøp i det støttede omfanget.

## Innenlandske varer og tjenester

Behold leverandørens pris og MVA-sats. Velg kjøpskode **1, 11 eller 13** ved
henholdsvis 25, 15 eller 12 prosent MVA. Skriv **0** ved ingen fradragsrett,
f.eks. **60** ved 60 prosent fradrag, eller **100** ved fullt fradrag.
Tomt felt beholder kjernens fulle fradrag. Andelen kan ha to desimaler.
Et kjøp uten MVA bruker fortsatt **0 (NO0)**; ikke fjern leverandørens MVA
fra en avgiftsbelagt faktura for å uttrykke manglende fradragsrett.

| Leverandørens faktura | Fradrag | Kostnad | Inngående MVA | Leverandørgjeld |
| --- | --- | --- | --- | --- |
| 1 000 + 250 MVA | 100 % | 1 000 | 250 | 1 250 |
| 1 000 + 250 MVA | 60 % | 1 100 | 150 | 1 250 |
| 1 000 + 250 MVA | 0 % | 1 250 | 0 | 1 250 |

Kostnaden går til linjens valgte kjøpskonto. Fradraget går til den native
MVA-kontoen for kodevalget, normalt 2710/2711/2713. Avrunding skjer per linje.
Det lagrede fakturabeløpet, MVA-beløpet og leverandørgjelden endres ikke.
Uttrekket viser faktisk fradrag; et innenlandsk kjøp uten fradrag vises under
kode 0 utenfor MVA-meldingen. Fradragsandelen må bygge på kjøpets bruk og
vilkårene for fradrag, ikke bare på at foretaket er MVA-registrert.

## Fjernleverbare tjenester kjøpt fra utlandet

Dette gjelder MVA-registrert norsk foretak, NOK og 25 prosent beregnet MVA.
Velg **omvendt avgiftsplikt** på leverandørfakturaen. Bruk tjenestelinje,
velg **0 (86)** ved fullt/delvis fradrag og oppgi fradragsandelen som over.
Velg **0 (87)** ved ingen fradragsrett; tomt fradragsfelt gir da 0 prosent.
Også kode 86 med uttrykkelig 0 prosent behandles som kode 87 i uttrekket.

Både fritekst og katalogtjeneste kan brukes. Produktkortets senere sats eller
kode brukes ikke til å beregne avgiften på den registrerte fakturaen.
At leverandøren er utenlandsk, velger ikke behandlingen automatisk.

| Leverandørens faktura | Fradrag | Kostnad | Beregnet MVA, kredit 2704 | Fradrag, debet 2714 | Leverandørgjeld |
| --- | --- | --- | --- | --- | --- |
| 1 000 uten norsk MVA | 100 % | 1 000 | 250 | 250 | 1 000 |
| 1 000 uten norsk MVA | 60 % | 1 100 | 250 | 150 | 1 000 |
| 1 000 uten norsk MVA | 0 % | 1 250 | 250 | 0 | 1 000 |

Manglende native kodevalg legges til ved moduloppgradering. Tom kjøpskonto
på kode 86/87 fylles med 2714; egne utfylte valg overskrives ikke. Konflikter
må rettes i kjernens **MVA-register**, lenket fra norsk regnskapsoppsett.
Ingen skjulte reverse-charge-konstanter må aktiveres for denne flyten.
Vareimport, andre beregnede satser, utenlandsk MVA og kjøp for uregistrerte
foretak er ikke omfattet av denne tjenesteflyten. Originalt utenlandsk bilag og
eventuelt omregningsgrunnlag må oppbevares; automatisk valutaomregning inngår ikke.

## Kreditnota, betaling og tidligere kjøp

Bruk kjernens **kreditnota med linjer** for en registrert original. Den kopierer
fradragsfeltet sammen med linjene. Ved delvis kreditering justeres beløpet på
kreditnotaen; behold relevant andel og kode. Kreditnotaen gir motsatte fortegn
på kostnad, MVA og leverandørgjeld. En selvstendig kreditnota må få riktig
behandling på egne linjer. Betaling og refusjon registreres på vanlig måte.

Fradragsfeltet endres på utkast før validering. Allerede bokførte kjøp og deres
separate omposteringer fra tidligere versjoner endres ikke. Ikke legg en ny
fradragsbehandling oppå en tidligere retting av samme kjøp. Lesestøtte og
MVA-opplysninger for gamle manuelle bilag beholdes som beskrevet i
[manuelle bilag](manual-vat.md). Endret kilde som ikke samsvarer med bokføringen
sperrer netto og CSV i [MVA-uttrekket](vat-report.md).

## Avgrenset teknisk løsning og prøving

Dolibarr 24.0.1 mangler fradragsprosent på kjøpslinjen. Kjernens kjøpsjournal
leser dessuten produktets nåværende sats for omvendt avgift og velger ordinær
eller beregnet MVA for hele fakturaen. Den kan ikke kombinere disse for å
kostnadsføre den delen det ikke gis fradrag for. NPR er en annen ordning.

Skjemamigrering 003 legger ett valgfritt felt i kjernens linjeekstrafelt, med
native visning, redigering og kopiering. Modulens SELECT-/FROM-hooks lager
journalgrunnlaget: kostnad og faktisk fradrag, og et ekstra grunnlag på 2704
for beregnet utgående avgift. SQL-projeksjonen bruker senere kolonnealiaser i
den låste MariaDB-/Dolibarr-versjonen. Kjernen grupperer, forhåndsviser,
eksporterer og bokfører sitt eget samlede bilag. Modulen skriver ingen
hovedboksposter og lager ingen ekstra fakturalinje eller rettingsbilag.
Dette er en versjonsbundet tilpasning som må prøves på nytt ved kjernebytte.

`tests/purchase-deduction.php` prøver native HTTP-registrering/-redigering,
varer/tjenester, blandede 25/15/12-satser, 0/37,5/60/100 prosent, kopierte
kreditnotaer, fritekst i SE/US, kode 86/87, produktendring før bokføring,
øreavrunding, riktig kostnad/MVA/gjeld i ett bilag, fradrag per dokument i
uttrekket, uendret kilde, betaling/refusjon, feilvalg og gjentatt overføring.
Migrerings- og installasjonstestene prøver bevaring og isolert gjenoppretting.
Full MVA-melding/innsending og historisk lesing etter legitim oppsettsendring
(R05) tilhører neste etappe; denne leveransen er ikke samlet produksjonsaksept.

## Kilder og vurdert gjenbruk

- [Skatteetaten: fradrag ved delvis bruk, § 8-2](https://www.skatteetaten.no/rettskilder/type/handboker/merverdiavgiftshandboken/gjeldende/M-8/M-8-2/).
- [Skatteetaten: fjernleverbare tjenester fra utlandet, § 3-30](https://www.skatteetaten.no/rettskilder/type/handboker/merverdiavgiftshandboken/gjeldende/M-3/M-3-30/M-3-30.3/).
- [Skatteetaten: MVA-meldingens informasjonsmodell](https://skatteetaten.github.io/mva-meldingen/mvameldingen/informasjonsmodell/).
- [Tripletex: forholdsmessig MVA](https://hjelp.tripletex.no/hc/no/articles/18168752046225-Slik-f%C3%B8rer-du-forholdsmessig-mva) og [DNB Regnskap: fradrag ved vanlig registrering](https://hjelp.dnbregnskap.dnb.no/no/article/forholdsmessig-mva-16zoi3c/) er sammenlignet som brukerflyt.
- [Dolistore: Non-deductible VAT](https://www.dolistore.com/product.php?id=2790&l=en&title=non-deductible-vat) er undersøkt som eksisterende tillegg. Kildepakken var ikke tilgjengelig uten bestilling; den er ikke kjøpt, installert eller kvalifisert. Produktbeskrivelsens endring av lagret nettobeløp er ikke grunnlag for å endre originalfakturaen her.
