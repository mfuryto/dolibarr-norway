# Kjøpsfordeling i leverandørfakturabildet

Fra 0.50.0, videre arbeid under A2. Dolibarrs leverandørfakturaer må være
aktivert for å bruke dette bildet. Åpne en leverandørfaktura i Dolibarr og
utvid **Norsk kontroll av kjøpsfordeling**. Administrator med leserett for
leverandørfaktura og hovedbok kan velge et sammenligningsgrunnlag. Ingen profil
er valgt på forhånd; visningen lagrer ingenting eller endrer fradrag/bokføring.
Vanlig bokføring krever ingen ekstra manuell kontrollrunde.

## Gjenbruk og avgrenset gap

Kjernen har leverandørfakturabildet, kjøpsjournalen og hovedboken. Modulen
bevarer allerede kilder ved bokføring. Gapet er sammenligning av dette
bevarte grunnlaget med kjøpsposteringene i samme arbeidsflyt. Det løses med
kjernens `invoicesuppliercard`-/`tabContentViewSupplierInvoice`-hook, felles
kilde-/driftkontroll, beløpsregning og bilagsfullstendighet. Kjernens innhold
og handlinger beholdes. Ingen ny bilags- eller diagnoseside opprettes.

## Uttrykkelig valgt fullt fradrag

Første omfang er innenlandske norske kjøp i NOK med én ordinær sats,
uten særmarkører, lokale avgifter, stempelavgift eller omvendt avgiftsplikt.
Profilen representerer et uttrykkelig valgt sammenligningsgrunnlag med fullt
fradrag, ikke et vedtak om at virksomheten eller kjøpet har denne retten.

| Valgt profil | Leverandørgjeld | Inngående MVA | Netto kjøp |
| --- | --- | --- | --- |
| 25 % – fullt fradrag | 2400 | 2710 | Bevarte linjekontoer |
| 15 % – fullt fradrag | 2400 | 2711 | Bevarte linjekontoer |
| 12 % – fullt fradrag | 2400 | 2713 | Bevarte linjekontoer |

Numrene gjelder den medfølgende kontoplanen. Kjøpskontoen kommer fra hver
linjes bevarte, foretakstilknyttede kontokobling, ikke dagens produkt- eller
standardkonto. Netto forventes i debet, inngående MVA i debet og brutto på
leverandørgjeld i kredit. Kreditnota har motsatte fortegn. De faktiske
kontosummene og differansene vises separat per rolle; linjekontoene
sammenlignes også per konkret konto, slik at feil kontofordeling ikke skjules
av riktig total. Summerte poster gir ikke bekreftelse på hver enkelt linje.

Fradrag krever en selvstendig vurdering av anskaffelsens bruk og øvrige
vilkår. Delvis bruk kan kreve forholdsmessig fradrag, og enkelte anskaffelser
har særskilte begrensninger. Se Skatteetatens omtale av
[fradragsrett](https://www.skatteetaten.no/rettskilder/type/handboker/merverdiavgiftshandboken/gjeldende/M-8/M-8-1/M-8-1.2/)
og [delvis bruk](https://www.skatteetaten.no/rettskilder/type/handboker/merverdiavgiftshandboken/gjeldende/M-8/M-8-2/).
Kontrollen avgjør ikke dette og lager ingen fradragsandel fra sats, kode,
konto eller registreringsdato. Et avvik mot fullt fradrag er derfor ikke i
seg selv bevis for feil bokføring; valgt grunnlag kan være uegnet for kjøpet.

## Resultat og oppfølging

- **Samsvar på kontosummer:** beløpene stemmer med valgt profil, mens
  enkeltlinjefordeling og fradragsrett fortsatt er uavklart.
- **Samsvar på linjer:** beløp og bevarte kontokoblinger stemmer innenfor
  profilen; avgiftsbehandling/fradrag er fortsatt ikke godkjent.
- **Avvik:** undersøk leverandørgjeld, konkrete kjøpskontoer, MVA og om
  sammenligningsgrunnlaget passer til kjøpet før rapportgrunnlaget brukes.
- **Uavklart/utilgjengelig:** manglende, fremmede, fjernede eller sprikende
  kilder, endrede hovedboksposter, kapasitetsgrense eller ustøttet scenario
  gir ikke godkjent resultat. Undersøk kilder og hovedbok; ikke etterfyll
  historikk med dagens oppsett for å skjule problemet.

Hendelser og hovedbok leses i ett konsistent, skrivebeskyttet lesebilde.
Endring i dagens fakturakilder erstatter ikke bevarte forventninger; dette
varsles separat. Hele journal-/bilagsnøkkelen undersøkes for fremmede poster.
Eksisterende kapasitets- og tilgangsvern beholdes. Ingen skjema- eller
payloadendring innføres. Installasjon/oppdatering registrerer den nye hooken.

Tester dekker faktisk kjøpsjournal og kreditnota, native norsk/engelsk visning,
25/15/12, direkte og summerte linjer, feil mellom flere kjøpskontoer,
balansert feil MVA-fordeling, uegnet delvis fradrag, kilde-/hovedboksendringer,
blandede bilag og rettigheter. Papirløs bilagsflyt ligger fortsatt sist.
Delvis/ingen fradragsrett, blandede satser, utenlandskjøp, nullsats, manuelle
bilag, korreksjonsløp og rapporteringsaksept gjenstår. Dette er teknisk
sammenligning, ikke norsk regnskapsmessig produksjonsaksept.
