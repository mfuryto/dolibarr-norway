# Skattemelding og næringsspesifikasjon for ENK

**Leveringsvei:** Skatteetatens portal, med grunnlag fra kjernens regnskap.
Dette følger prosjektets valg om manuell levering der reglene tillater det.
Ingen modulavgift, API-nøkkel eller avtale med en årsoppgjørsleverandør kreves
for denne veien. Modulen sender ikke skattemelding på dine vegne.

Veien gjelder **ENK uten regnskaps- eller revisjonsplikt**, ett foretak og
ordinær virksomhet med varer/tjenester. Regnskaps-/revisjonspliktige ENK og
selskap som AS trenger et egnet innsendingssystem. Se
[Skatteetaten om ENK og leveringsvalg](https://www.skatteetaten.no/bedrift-og-organisasjon/skatt/skattemelding-naringsdrivende/enk/).

## Bruk årsrapporten i programmet

Åpne **Regnskap → Kontobalanse → Årsgrunnlag for ENK**. Velg inntektsår og
**Vis årsgrunnlag** eller **Last ned årsgrunnlag (CSV)**. Rapporten bruker hele
kalenderåret uavhengig av søk, sideinndeling og datoene på den øvrige
saldobalansesiden. Den viser:

- postkode og norsk navn fra årets offisielle ENK-liste, bokført beløp, kjent
  korreksjon og grunnlag etter korreksjonen, med lenker til aktuelle konti;
- resultat før disponering, kjente ikke-fradragsberettigede kostnader og
  avstemming mot egenkapital, uten at disponering nuller ut årsresultatet;
- [fradragsoversikt](deductions.md), driftsmiddelbevegelser og personlige
  skattebetalinger/premier med sporbarhet til bilag;
- uavklarte kontokoblinger, ikke bokførte dokumenter/bankbevegelser og
  nødvendige tilleggsopplysninger.

CSV inkluderer også full kontospesifikasjon med IB, årets bevegelse og UB.
Udisponert resultat presenteres i valgt egenkapitalpost også når kjernen
fører resultatet først i neste års inngående balanse. Beløpet tas med én gang;
et allerede disponert resultat legges ikke til igjen. Ingen postering opprettes.
«Grunnlag etter kjent korreksjon» er ikke et løfte om endelig fradragsrett:
skatteverdier, personinntekt og ukjente/private forhold må avklares. Rapporten
skjuler ikke manglende kontokoblinger som null. Ingen økonomiske data eller
kontokoblinger endres av lesing/nedlasting.

Første støttede år er 2025 og 2026, med låste postnavn/fortegn for ENK **uten
regnskapsplikt**. SAF-T-grupper og næringsposter er ikke identiske; egne
ENK-koder kontrolleres separat. Endelige felt må fortsatt kontrolleres i
portalen ved levering. Null bokført aktivitet vises som nullgrunnlag, ikke
som automatisk fritak fra levering.

Omsetning over 50 000 kroner utløser ikke alene krav om systeminnsending av
skattemeldingen. Systemkravet følger regnskaps-/revisjonsplikten. Grensen for
MVA-registrering gjelder omsetning og uttak som omfattes av merverdiavgiftsloven over en rullerende
12-månedersperiode, og er en annen vurdering enn forenklet næringsspesifikasjon.
Ikke bruk beløpsgrensen som et generelt fritak fra bokføring eller levering.
Fristen i mai må også leses med gjeldende helge-/helligdagsregler og eventuell
innvilget utsettelse. Bruk Skatteetatens varsel for det aktuelle året.

## 1. Hent årets grunnlag

Gjør [årsavslutningens forberedelser](year-end.md) ferdig før endelig innlevering.
Årsrapporten samler tallene automatisk. Bruk **Regnskap → Kontobalanse og Hovedbok** for 1. januar–31. desember
(`/accountancy/bookkeeping/balance.php`, `/accountancy/bookkeeping/list.php`).
Ta med alle årets bokførte poster og inngående balanse, også tidligere
rapporterte/eksporterte poster. Avstem mot bank, åpne fakturaer, MVA og
årsunderlag. Bevar rapportene slik de så ut ved levering.

| Opplysning til næringsspesifikasjonen | Finnes hvor / hva må vurderes |
| --- | --- |
| Salgsinntekt | Inntektskontoene, fordelt etter faktisk inntektsart. Bruk netto etter kreditnotaer, normalt uten MVA. Salg uten MVA er også inntekt. |
| Varekostnad og lager | Varekjøp, beholdningsendring og dokumentert utgående lager. |
| Andre kostnader | Faktiske kostnadskontoer etter periodisering. Skill fradragsberettiget kostnad fra ikke fradragsberettiget del. |
| Driftsmidler | Anskaffelsesunderlag, tidligere skattemessig saldo, tilganger, salg/uttak, saldogruppe og årets avskrivning. En sluttsaldo alene gir ikke disse opplysningene. |
| Bank, fordringer og annen formue/gjeld | Avstemte balansesaldoer; sjekk også skattemessig verdsetting og fordeling mellom personlig skattemelding og næring. |
| Egenkapital | Inngående egenkapital + resultat + innskudd − privatuttak/personlig skatt = utgående egenkapital. |

Kontonumre er hjelp til å finne grunnlaget. De er ikke en generell automatisk
kobling til et felt i skattemeldingen. Ved annen kontoplan brukes kontoenes
faktiske innhold. Portalen spør etter opplysninger ut fra virksomhetens svar.

## 2. Avstem regnskapsresultat mot skattepliktig næringsinntekt

Start med resultatet **før** disponering til egenkapital. En saldobalanse der
8800/8960 allerede har disponert overskuddet, kan vise null i resultatklassene;
det betyr ikke null næringsinntekt. Bruk drifts-/finanspostene og ta bort selve
disponeringen fra resultatberegningen.

| Avstemming i det syntetiske eksemplet | NOK |
| --- | ---: |
| Regnskapsresultat før disponering | 42 250 |
| Legg tilbake en bokført, ikke fradragsberettiget kostnad | 750 |
| Regnskapsført avskrivning som allerede tilsvarer årets skattefradrag | 15 000 |
| Tilbakeført regnskapsavskrivning minus skattemessig avskrivning | 0 |
| Skattepliktig næringsinntekt før eventuelt fremførbart underskudd | **43 000** |

Skatteavskrivning er allerede med én gang i eksemplet. Dersom portalen legger
avskrivningen inn separat, oppgis de øvrige kostnadene uten samme avskrivning;
avstem til 43 000. Ikke trekk 15 000 fra en gang til. På samme måte skal en
kostnad som allerede er utelatt fra fradragskostnadene ikke legges tilbake
enda en gang. Oppbevar avstemmingen mellom innlagte kategorier og hovedboken.

Personlige skattebetalinger 17 000 og privatuttak 3 000 i eksemplet har ingen
resultat-/fradragseffekt. Regnskapsmessig egenkapital blir
200 000 + 42 250 − 20 000 = **222 250**.

[Skatteetatens inntektsveiledning](https://www.skatteetaten.no/bedrift-og-organisasjon/skatt/skattemelding-naringsdrivende/fradrag/inntekt-formue-gjeld/inntekt-i-enkeltpersonforetak/)
er grunnlag for tidfesting og næringsinntekt. Oppgi også relevante forhold som
privat bruk av telefon/bil, ikke-fradragsberettiget representasjon, tap,
fremførte underskudd og skattemessige forskjeller. Et kontonummer kan ikke
avgjøre disse forholdene alene. Bruk portalens årsspesifikke forklaring og
[fradragsveilederen](https://www.skatteetaten.no/bedrift-og-organisasjon/skatt/skattemelding-naringsdrivende/fradrag/).

## 3. Fyll ut saldogrupper og personinntekt

For driftsmidler kontrolleres saldoens inngående verdi, årets bevegelser og
valgt sats mot riktig inntektsår. Eksemplet gjelder én kontormaskin i gruppe a,
50 000 i grunnlag og valgt avskrivning 30 prosent = 15 000, rest 35 000.
Ingen salg, privat bruk eller tidligere saldo inngår i eksemplet.
[Skatteetatens eiendelsveiledning](https://www.skatteetaten.no/bedrift-og-organisasjon/skatt/skattemelding-naringsdrivende/fradrag/eiendeler-utstyr-eiendom/bedriftens-eiendeler/kjope-eiendeler-til-bedriften/)
og [årets satser](https://www.skatteetaten.no/satser/avskrivningssatser/) avgjør
behandlingen. Kontoer eller kjernens lineære anleggsregister beregner ikke
automatisk norske skattemessige saldogrupper.

Fyll også ut **beregnet personinntekt**. Netto næringsinntekt er utgangspunktet,
men kapitalinntekter/-kostnader, skjermingsgrunnlag og tidligere negativ
personinntekt kan gi en annen verdi. Hent inngående/utgående verdier og
foretaksrelatert gjeld fra avstemte underlag; bankbeholdningen alene er ikke
skjermingsgrunnlaget. Bruk gjeldende skjermingsrente og veiledningen i portalen.
[Skatteetaten om personinntekt](https://www.skatteetaten.no/bedrift-og-organisasjon/skatt/skattemelding-naringsdrivende/fradrag/inntekt-formue-gjeld/personinntekt-i-enkeltpersonforetak/)
forklarer justeringene. Modulen beregner ikke personlig skatt, trygdeavgift eller
trinnskatt fra ett prosentanslag.

## 4. Lever og oppbevar kvitteringen

Logg inn på Skatteetatens skattemelding for riktig person og inntektsår med
egen elektronisk ID eller nødvendige representasjonsrettigheter. Åpne
næringsspesifikasjonen og følg spørsmålene. Mangler den, søk «næring» og velg
«Inntekter, kostnader, eiendeler og gjeld i næring».

Kontroller at private og næringsrelaterte opplysninger ikke ligger dobbelt.
Ferdigstill både næringsspesifikasjonen og den personlige skattemeldingen,
kontroller portalens meldinger og send inn. **En lagret kladd er ikke levert.**
Last ned innsendte opplysninger og faktisk mottakskvittering fra portalen/Altinn;
bevar dem privat med årsgrunnlaget. Se
[Skatteetatens beskrivelse av innsending og kvittering](https://www.skatteetaten.no/bedrift-og-organisasjon/skatt/skattemelding-naringsdrivende/hjelp-til-skattemelding/innsending/).

Fristen er normalt 31. mai året etter inntektsåret, med gjeldende helgeregel.
Ved retting av innsendte opplysninger leveres en korrigert samlet melding og
ny kvittering beholdes sammen med den tidligere. Vurder også nødvendig
bokføringsretting. Ingen ekte innlogging, innsending eller mottakskvittering
inngår i prosjektets syntetiske tester.

## Årsversjon, bevis og avgrensning

Kontrollert 2026-10-18 mot Skatteetatens portalveiledning og publiserte satser.
Det gjennomregnede eksemplet bruker **inntektsåret 2025**; saldoovergang til
2026 og videre er prøvd. For **2026** brukes denne arbeidsrekkefølgen som
forberedelse. Årets endelige portal, satser og felt må kontrolleres ved faktisk
levering i 2027. En publisert modell er ikke bevis på at portalen allerede er
åpen. Ingen XML-/API-innsending eller universell kontomapping loves for noen år.
[Årsvis innhold og struktur](https://www.skatteetaten.no/bedrift-og-organisasjon/skatt/skattemelding-naringsdrivende/innhold-og-struktur-i-skattemeldingen/)
er referanse ved utfylling.

`tests/year-end-cycle.php` avstemmer de bokførte tallene, årets avskrivning,
resultat før/etter disponering, egenkapital og inngående balanse mot uavhengig
fasit. Den beviser regnskapsgrunnlaget, ikke at en privat skattemelding er
komplett eller mottatt av Skatteetaten. Portalutfylling er første leveringsvei;
direkte innsending og et automatisk norsk årsoppgjørssystem er ikke levert.

Biler, hjemmekontor, ansatte, ektefellefordeling, flere næringer, finansielle
instrumenter, kompliserte driftsmiddelsalg og særnæringer krever ytterligere
opplysninger og vurderinger; de er ikke kvalifisert av eksemplet. For slike
forhold brukes portalens veiledning/faglig bistand eller et egnet årsoppgjørssystem.
SAF-T er en egen regnskapseksport og erstatter verken utfylling eller levering.
