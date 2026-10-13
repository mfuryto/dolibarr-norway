# Kjøpsfordeling i leverandørfakturabildet

Fra 0.50.0, utvidet i 0.51.0 og 0.53.0, videre arbeid under A2. Dolibarrs leverandørfakturaer må være
aktivert for å bruke dette bildet. Åpne en leverandørfaktura i Dolibarr og
utvid **Norsk kontroll av kjøpsfordeling**. Administrator med leserett for
leverandørfaktura og hovedbok kan velge et sammenligningsgrunnlag. Ingen profil
er valgt uten et tidligere uttrykkelig lagret valg med uendret grunnlag.
Sammenligningen endrer ikke fradrag/bokføring.
Vanlig bokføring krever ingen ekstra manuell kontrollrunde.

## Gjenbruk og avgrenset gap

Kjernen har leverandørfakturabildet, kjøpsjournalen og hovedboken. Modulen
bevarer allerede kilder ved bokføring. Gapet er sammenligning av dette
bevarte grunnlaget med kjøpsposteringene i samme arbeidsflyt. Det løses med
kjernens `invoicesuppliercard`-/`tabContentViewSupplierInvoice`-hook, felles
kilde-/driftkontroll, beløpsregning og bilagsfullstendighet. Kjernens innhold
og handlinger beholdes. Ingen ny bilags- eller diagnoseside opprettes.

## Uttrykkelig valgt fradragsgrunnlag

Omfanget er innenlandske norske kjøp i NOK med én ordinær sats,
uten særmarkører, lokale avgifter, stempelavgift eller omvendt avgiftsplikt.
Profilen representerer et uttrykkelig valgt sammenligningsgrunnlag, ikke et
vedtak om at virksomheten eller kjøpet har fradragsrett. Fullt fradrag er
100 prosent i sammenligningen; delvis og ingen er beskrevet nedenfor.

| Valgt profil | Leverandørgjeld | Inngående MVA | Netto kjøp |
| --- | --- | --- | --- |
| 25 % – fullt fradrag | 2400 | 2710 | Bevarte linjekontoer |
| 15 % – fullt fradrag | 2400 | 2711 | Bevarte linjekontoer |
| 12 % – fullt fradrag | 2400 | 2713 | Bevarte linjekontoer |

Numrene gjelder den medfølgende kontoplanen. Kjøpskontoen kommer fra hver
linjes bevarte, foretakstilknyttede kontokobling, ikke dagens produkt- eller
standardkonto. Netto og MVA uten fradrag forventes på linjens kjøpskonto i debet,
fradragsført MVA på MVA-kontoen i debet og brutto på leverandørgjeld i kredit. Kreditnota har motsatte fortegn. De faktiske
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
Bokføringsintegrasjon for delvis/ingen fradragsføring, blandede satser,
utenlandskjøp, nullsats, manuelle bilag, korreksjonsløp og rapporteringsaksept gjenstår. Dette er teknisk
sammenligning, ikke norsk regnskapsmessig produksjonsaksept.

## Delvis eller ingen fradragsføring fra 0.51.0

Det samme leverandørfakturabildet tilbyr nå fullt, delvis eller ingen
fradragsføring som uttrykkelig sammenligningsgrunnlag for hver av satsene
25/15/12. Valget er lesende og gjelder denne visningen; det lagrer ingen
fradragsbeslutning eller postering. Ingen profil eller prosent utledes fra
kode, konto, omsetning, registrering eller NPR-markør.

Ved delvis fradrag må en vurdert andel mellom 0,01 og 99,99 prosent oppgis,
med høyst to desimaler. Manglende/ugyldig andel gir forklaring uten beregning.
Fullt fradrag betyr 100 prosent i sammenligningen, ingen fradragsføring betyr
0 prosent; prosentfeltet brukes bare for delvis fradrag. Samme valgte andel
gjelder alle linjer innenfor den valgte enkeltsatsprofilen.

For hver linje beregnes fradragsført MVA fra bevart MVA og valgt andel, med
avrunding til nærmeste øre. Et halvt øre avrundes bort fra null, symmetrisk
for kreditnota. Resterende MVA legges til linjens bevarte kjøpskonto i
sammenligningsgrunnlaget. Brutto leverandørgjeld endres ikke. Delvis/ingen
fradragsføring krever MVA-linjebeløp med ørepresisjon; finere lagret presisjon
gir uavklart resultat fremfor skjult avrunding. Beløpsregningen bruker heltall.

Eksempel på et valgt 50-prosentgrunnlag: netto 1000, MVA 250 og brutto 1250
gir forventet kjøpskonto 1125, inngående MVA 125 og leverandørgjeld 1250.
Ved valgt ingen fradragsføring forventes kjøpskonto 1250 og inngående MVA 0.
Dette vurderer ikke om kjøpet har rett til disse andelene. Se de tidligere
lenkene til Skatteetatens fradragsregler; bruk og vilkår må dokumenteres selvstendig.

## Kjernens dekning og hva som fortsatt mangler

Kjøpsjournalen i støttet Dolibarr-versjon har en global innstilling som fører
alle kjøpsavgifter sammen med linjebeløpet. Den er kvalifisert i et isolert
syntetisk forsøk med faktura og kreditnota, og sammenlignes korrekt med
ingen fradragsføring. Modulen aktiverer ikke denne globale innstillingen,
og den er ikke en norsk per-bilag/per-linje-modell for delvis fradrag.

NPR (`info_bits & 1`) er heller ikke en slik modell: kjernen endrer brutto-
beregningen og oppretter en særskilt motpost. Den beholdes derfor utenfor
kontrollens støttede omfang. Dette er undersøkt i
[kjøpsjournalen](https://github.com/Dolibarr/dolibarr/blob/24.0.1/htdocs/accountancy/journal/purchasesjournal.php)
og [beløpsberegningen](https://github.com/Dolibarr/dolibarr/blob/24.0.1/htdocs/core/lib/price.lib.php).

Leveransen utvider sammenligningen, ikke den native bokføringen. En endring
av profil/prosent flytter ikke MVA fra hovedboken til kostnad eller anlegg.
Godkjent bokføringsintegrasjon og korreksjonsløp gjenstår. Et manuelt valgt
sammenligningsgrunnlag lagres ikke; lagring gjøres uttrykkelig som beskrevet nedenfor. Et avvik forklarer forskjellen mot valgt sammenligning;
det er ingen automatisk retting eller produksjonsaksept.

Tester dekker delvis/ingen/fullt fradrag, gyldige/ugyldige andeler, øregrenser,
store beløp, kreditnotaer, native postering uten separat MVA-konto, manglende
andel i HTTP og bevaring av hovedbok ved sidevisning. Kjernens globale
innstilling gjenopprettes etter forsøket. Ingen skjema- eller payloadendring.


## Lagret fradragsvurdering fra 0.53.0

I samme leverandørfakturabilde velges sats og fradragsomfang. Delvis krever
prosent mellom 0,01 og 99,99. Skriv begrunnelse og grunnlag, forhåndsvis
linjebeløpene og bekreft. Administrator må ha leserett til leverandørfaktura
og hovedbok samt opprettingsrett til leverandørfaktura for å lagre.
Samme fradragsandel brukes på alle linjer; kjøpet må være validert,
innenlandsk i NOK, ha én støttet sats og MVA-beløp med ørepresisjon.

Lagringen bevarer kildelinjene, beløp, valgt prosent og begrunnelse med bruker,
UTC-tidspunkt og stigende revisjon. Valget har en egen dokumenttype
`supplier_deduction` i eksisterende kontrollarkiv,
payloadversjon 1. Det er ingen skjemaendring, tilbakefylling eller endring i
hovedboken. Endring av valg krever ny forhåndsvisning/bekreftelse og oppretter
en ny revisjon. Bruk maksimalt 100 revisjoner per faktura; kapasitetsbrudd
stopper lagring. Gamle revisjoner beholdes ved nye vurderinger.

Endrede faktura-/linjeopplysninger, leverandørland eller relevante foretaksdata
gir varsel. Betalingsstatus inngår ikke i fradragsgrunnlaget. Ved uendret
kilde brukes siste lagrede valg i sammenligningen hvis ingen profil er valgt
manuelt. Sammenligningen bruker fortsatt bevarte bokføringskilder; endringer
siden bokføring vises separat. Lagret vurdering og likt kontrollresultat
bekrefter ikke fradragsrett eller at valget ble brukt ved bokføring.

Transaksjon, fakturalås, fingeravtrykk av kilde/valg/historikk og engangsbekreftelse
beskytter mot endret grunnlag og gjentatt lagring. Kontroller av integritet og
foretakstilgang gjelder også historikk. Dette er teknisk kvalifisert bevaring,
ikke aksept for norsk regnskapsproduksjon. Neste arbeid knytter valgt revisjon
til bokføringshendelser og kvalifiserer fradragsføring og korreksjoner.


## Kobling til nye posteringer fra 0.54.0

Under **Fradragsvurdering ved bokføring** vises hva som forelå da hver ny
postering ble opprettet: vurderingsrevisjon, tidspunkt og om grunnlaget samsvarte.
Valget må lagres før bokføringen for å inngå i denne historiske koblingen.
Lagrer du et nytt valg senere, beholder tidligere posteringer sin opprinnelige
kobling. Valg som lagres etter en eldre postering blir ikke tilbakeført som om
de var kjent ved bokføringen. Eldre observasjoner vises med at kobling ikke er
bevart, mens nye observasjoner uten lagret valg viser manglende vurdering.

Ny kjøpspayloadversjon 6 inneholder et eget `deduction_context` med vurdering,
bevart integritetsavtrykk og kildegrunnlag. Samsvar krever uendret vurderingskilde
og overensstemmelse med posteringens observerte faktura/linjer/foretak/leverandør.
Et endret eller ikke støttet grunnlag bevares som avvik uten å brukes som
bekreftet kobling. Integriteten valideres fra arkiverte data, uavhengig av dagens
vurdering. Versjon 4/5 leses fortsatt uten nye fradragsopplysninger.

Kjernens transaksjon eier lagringen og rollback. Fakturalås og låst lesing av
vurderingshistorikk serialiserer lagring mot nye bokføringshendelser. Korrupte
vurderinger eller svikt i arkivering stopper den aktuelle bokføringshandlingen;
det opprettes ikke en postering uten den påkrevde observasjonen. Operatøren kan
bruke kjernens eksisterende bokføringsrettigheter; administrator kreves fortsatt
for å lagre selve fradragsvurderingen.

Koblingen endrer ikke kontofordeling eller fradragsbeløp i hovedboken. For eksempel
vil en bevart 50-prosentvurdering fremdeles gi avvik i sammenligningen når kjernen
har bokført full inngående MVA. Kvalifisert fradragsføring og korreksjoner er neste
arbeid; denne leveransen er ikke norsk regnskapsmessig produksjonsaksept.
