# Fradrag i ENK – registrering og årsgrunnlag

Åpne **Regnskap → Saldobalanse → Årsgrunnlag for ENK**, velg inntektsår og
**Vis årsgrunnlag**. Fradragsoversikten og CSV-filen samler relevante
bokførte kostnader, rapportposter, konti og bilag. Manglende koblinger vises.
Beløpene er allerede med i resultatet: ikke legg dem til som nye fradrag.

Dette bruker vanlige kjøpsfakturaer og kjernens bilag. Det er ikke et nytt
fradragsregister. **Registrer manglende årsjustering** åpner kjernens bilag.
Bruk faktisk dato/inntektsår, konto etter kostnadens art og underlag. En
privatbetalt næringskostnad føres mot innehaverens mellomregning/innskudd,
ikke som en oppdiktet betaling fra foretaksbanken. Registrer samme hendelse
én gang; allerede registrert kjøp skal ikke også føres som nytt årsfradrag.

## Telefon og internett

Registrer dokumenterte næringskostnader som vanlig kjøp, eksempelvis
6900/6903/6907/6909 etter den medfølgende kontoens betydning. Utstyr må
vurderes som direkte kostnad eller driftsmiddel separat. Privat bruk gir
nødvendig tilbakeføring i næringsspesifikasjonens **6998**. For 2025/2026
er hovedgrensen 4 392 kroner, begrenset av kostnaden; samordning med eventuell
arbeidsgiverfordel og dokumentert fravær av privat bruk må vurderes.
[Skatteetatens veiledning](https://www.skatteetaten.no/bedrift-og-organisasjon/skatt/skattemelding-naringsdrivende/fradrag/eiendeler-utstyr-eiendom/telefon-og-internett/).

Ved vanlig dokumentert årsjustering debet 2068/kredit aktuell telefonkonto
presenterer årsrapporten tilbakeføringen i 6998 og gjenoppretter bruttokostnaden
i 6995. Dette endrer bare presentasjonen, ikke bokføringen eller resultatet.
Eksempel: bokført telefon 12 000 og privat tilbakeføring 4 392 gir
fradragsvirkning 7 608. Rapporten legger ikke tilbake 4 392 én gang til.
Bilaget følger med i CSV. En blandet/ukjent kontering må avstemmes særskilt.
MVA-fradrag følger egen vurdering og er ikke lik denne skattemessige sjablongen.

## Eiendeler og avskrivning

Rapporten viser direkte kostnadsført utstyr og bokført avskrivning, sammen med
driftsmidlenes inngående verdi, økninger, reduksjoner og utgående verdi.
[Skatteetatens eiendelsregler](https://www.skatteetaten.no/bedrift-og-organisasjon/skatt/skattemelding-naringsdrivende/fradrag/eiendeler-utstyr-eiendom/bedriftens-eiendeler/kjope-eiendeler-til-bedriften/)
avgjør aktivering og skattemessig avskrivning. Avstem tidligere skattesaldo,
saldogruppe, tilganger/salg og valgt sats. Hele en aktivert anskaffelse skal
ikke fradragsføres i tillegg til avskrivningen. Se [årsavslutning](year-end.md).

## Pensjon, sykepenger, forsikring og kontingenter

Oversikten finner relevante kostnader og personlige premier betalt fra
foretaksbank. Disse er ulike grunnlag. Frivillig sykepengeforsikring og enkelte
andre personlige premier skal vurderes i den personlige skattemeldingen;
de skal ikke automatisk redusere næringsresultatet. Betaling mot 2077/2078
beholdes i oversikten også etter at privatkontoene er nullstilt. Egen
pensjonsordning, fradragsgrenser og eventuelt forhåndsutfylte beløp må avstemmes
mot tilbyderens oppgave og [Skatteetatens regler](https://www.skatteetaten.no/bedrift-og-organisasjon/skatt/skattemelding-naringsdrivende/fradrag/utgifter-til-pensjon-sykepenger-og-forsikring/).

## Egen bolig og hjemmekontor

Rapportens lokalkostnader kan også gjelde eksterne lokaler; de beviser ikke
bruk av egen bolig. Avklar et rom som utelukkende brukes i næring og vilkårene
for standardsats eller dokumenterte faktiske kostnader. Du skal ikke beregne
leie til deg selv i ENK. Bruk én korrekt metode for samme kostnad, og bokfør
nødvendig næringsandel med underlag uten å føre private totalutgifter som
næringskostnad. [Skatteetatens veiledning og satslenke](https://www.skatteetaten.no/bedrift-og-organisasjon/skatt/skattemelding-naringsdrivende/fradrag/eiendeler-utstyr-eiendom/bruke-egen-bolig-i-naring/)
beskriver vilkår og eventuell betydning ved senere salg av boligen.

## Oppstartskostnader

Bruk kostnadskonto etter art; oppstart er ikke én generell konto. Dokumenter
år og formål. Tidligere oppstartsår kan kreve endring av de aktuelle årenes
skattemelding. Ikke flytt alle gamle kvitteringer til inneværende års rapport.
[Skatteetatens oppstartsveiledning](https://www.skatteetaten.no/bedrift-og-organisasjon/starte-og-drive/ny-som-naringsdrivende/oppstartsperiode/)
beskriver inntil fem foregående oppstartsår og dokumentasjon. Gjenopptatt
virksomhet er ikke automatisk en ny oppstartsperiode.

## Hva rapporten kan og ikke kan fastslå

Rapporten bruker bokføringen og dokumenterte rapportkoblinger. Den kan ikke
vite om et rom brukes privat, finne en uregistrert privat kvittering, velge
rett saldogruppe for en ukjent eiendel eller avgjøre personlig pensjonsfradrag.
Dette vises som nødvendige tilleggsopplysninger, med kilde ved hvert tema.
Null/«—» betyr ikke at du mangler fradragsrett. En omdøpt/egendefinert konto
kan kreve kobling eller egen avstemming; beløpene beholdes i kontospesifikasjonen.

Kvalifisert med syntetiske data i `tests/deductions-cycle.php`: telefon og
6998 uten dobbeltføring, kostnadsført utstyr, privatbetalt dokumentert boligandel,
personlig NAV-premie uten reduksjon av næringsoverskudd, bevaring etter
privatkontoavslutning, null aktivitet og uavklarte kontonavn. Testen bekrefter
bokføring/rapportering, ikke fradragsrett for en virkelig virksomhet.
