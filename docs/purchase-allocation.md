> Historisk dokumentasjon til og med 0.64.0. Funksjonen er fjernet i 0.65.0.
> Se [gjeldende plan](delivery-plan.md). Eldre tilleggshistorikk beholdes i arkivet.

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


## Bekreftet førstegangsbokføring fra 0.55.0

For et nytt kostnadsbilag: lagre først fradragsvurderingen. Under **Bokfør med
lagret fradragsvurdering** velges kjøpsjournal. Forhåndsvis kontoer og NOK-beløp,
og bekreft at posteringene skal opprettes. Handlingen krever administrator,
leverandørfaktura-/hovedboksleserett og kjernens `accounting.bind.write`-rett.
Den bruker kjernens hovedboks-API, bilagsnummerering og periodekontroll.

Siste vurdering må ha uendret kilde. Alle linjer må være knyttet til aktive
kostnadskontoer i klasse 4–7 i valgt aktiv kontoplan; eiendelskjøp inngår ikke
ennå. Leverandørkontoen må være 2400, og leverandøren må ha reskontrokode.
MVA-kontoene er 2710/2711/2713 ved sats 25/15/12. Fakturadatoen må tilhøre én
åpen, avgrenset periode uten overlapp. Erstattede/særskilt lukkede fakturaer og
kreditnotaer med ukjent/annen leverandør som kilde avvises. Bare ørepresise bilag
som balanserer nøyaktig støttes; ingen skjult avrundingskonto eller avrunding av
fakturagrunnlaget. Samme fradragsandel gjelder alle linjer.

Eksempel: netto 100 og MVA 25 med lagret 50 % fradrag gir kostnad 112,50 i
debet, inngående MVA 12,50 i debet og leverandørgjeld 125 i kredit. Kreditnota
har motsatt fortegn. Fradragsført MVA avrundes per linje, ikke som samlet
prosent av fakturaens MVA. Beløpene er en anvendelse av brukerens dokumenterte
valg, ikke automatisk avgjørelse av fradragsrett. Se Skatteetatens
[dokumentasjonskrav](https://www.skatteetaten.no/rettskilder/type/handboker/merverdiavgiftshandboken/gjeldende/M-15/M-15-10/M-15-10.2/).

Første bokføring bruker en egen `supplier_posting`-registrering med
payloadversjon 1 i eksisterende kontrollarkiv. Den bevarer forhåndsvisning,
vurderingsrevisjon, posteringer og bilagsnummer. Alle hovedbokslinjer og deres
kildeobservasjoner opprettes i én transaksjon. Fordelingen kontrolleres før
commit. Ved feil rulles hele den nye bokføringen tilbake. Ny innsending,
endret kilde/oppsett og allerede eksisterende eller bevart bokføring avvises.
En bekreftet registrering sperrer utrygg ny kjernetransfer og sammenblanding
med et annet bilag på samme bilagsnummer. Ingen skjemaendring.

Vanlig kjøpsjournal kan fortsatt brukes uten lagret særskilt fradragsvalg
eller med fullt fradrag. Med lagret delvis eller intet fradrag må den bekreftede
flyten brukes, slik at kjernen ikke posterer fullt fradrag ved en feil.
Dette påvirker nye bokføringshandlinger; tidligere poster omskrives ikke.

Korreksjon er foreløpig ikke støttet av denne handlingen. En ny vurdering
endrer ikke eksisterende hovedbok, og sletting/ny overføring er ikke et
kvalifisert korreksjonsløp. Videre arbeid skal bevare originalpostene og knytte
rettingen til en ny vurderingsrevisjon. Teknisk verifisert bokføring innen dette
avgrensede omfanget er ikke norsk regnskapsmessig produksjonsaksept.


## Korreksjon av fradragsandel fra 0.56.0

For bilag fra **Bokfør med lagret fradragsvurdering**: lagre først en ny,
begrunnet vurdering med endret fradragsandel. Velg **Korriger fradragsbokføring**
på leverandørfakturaen, oppgi kjøpsjournal, dato og rettingsbegrunnelse.
Forhåndsvis full reversering og ny bokføring. Bekreft både posteringene og at
grunnlaget ikke er brukt i en innsendt MVA-melding. Originalene blir stående.
Dette følger prinsippet om ny dokumentert postering og full reversering i
[bokføringsloven § 9](https://lovdata.no/nav/lov/2004-11-19-73).

Omfanget er endret fradragsandel på uendret fakturagrunnlag, samme kalendermåned
som fakturaen og en dato som ikke er tidligere enn siste effektive bokføring.
De bevarte kildeobservasjonene må fortsatt samsvare, også fakturastatus;
statusendringer etter bokføring kvalifiseres i et senere trinn.
Begge relevante datoer må ligge i åpne, entydige perioder. Eksporterte eller
endelig validerte poster sperres. Brukerens bekreftelse om innrapportering
bevares som en uttrykkelig erklæring; den kontrolleres ikke automatisk mot
Skatteetatens innsendingshistorikk. Endring av fakturabeløp, linjer eller
kontokoblinger, retting i andre måneder og endring av innrapportert grunnlag
gjenstår. Tidligere kjernetransfer uten modulens førstegangsregistrering kan
ikke adopteres som bekreftet korreksjonsgrunnlag.

En `supplier_correction`-registrering, payloadversjon 1 og stigende revisjon,
bevarer forrige registreringsavtrykk, reverserte poster, erstatningsposter,
dato, begrunnelse, vurdering og brukerens erklæring. Det er ingen DDL eller
oppdatering av originalposter. Hele reverseringen, erstatningen og kildearkivet
rulles tilbake hvis noe svikter. Maksimalt 20 korreksjoner og 200 samlede
hovedboksposter per faktura støttes; eksisterende størrelsesgrenser gjelder.

Før kontrollen bruker erstatningsbilaget, valideres hele kjeden: alle poster
skal være med, tidligere effektiv bokføring skal være nøyaktig opphevet,
og ingen poster skal være endret, fjernet eller blandet med andre dokumenter.
Historiske hovedbokslinjer og observasjoner er fortsatt tilgjengelige. Eksport-
eller valideringsstatus kan sperre ny retting uten å skjule en gyldig lesbar
korreksjonshistorikk. Vanlig kjernetransfer kan ikke erstatte dette løpet.

Kjernens `newReturnAccount` er gjennomgått i støttet versjon. Den setter enkelte
faktura-/bilagsreferanser etter opprettingen og dekker ikke modulens krav til
atomisk kildebevaring. Utvidelsen bruker derfor kjernens `createStd` til nye
poster, eksisterende nummerering og periodelogikk, med bevart referanse allerede
ved opprettelsen. Den endrer ikke kjernen og oppretter ikke en ny fakturaoriginal.
Se [kjernens hovedboks-API](https://github.com/Dolibarr/dolibarr/blob/24.0.1/htdocs/accountancy/class/bookkeeping.class.php).

Dette kvalifiserer teknisk sporbar retting innen det avgrensede omfanget.
Fradragsrett, MVA-rapport/endringsmelding og samlet norsk produksjonsetterlevelse
må fortsatt kvalifiseres separat.


## Ulike linjeandeler og blandede satser fra 0.57.0

I leverandørfakturaen velges **Fradragsvurdering per linje**. Hver linje viser
beskrivelse, lagret MVA-sats og MVA-beløp. Velg fullt, delvis eller intet
fradrag, og skriv begrunnelse på hver linje. Ved delvis fylles prosent inn;
fullt/ingen bruker henholdsvis 100/0 uten å velge dette automatisk. Skriv en
samlet begrunnelse, forhåndsvis linjebeløpene og bekreft. Manglende eller
uvedkommende linjevalg avvises. Nye linjer eller endret grunnlag krever ny
forhåndsvisning og vurdering. Satser velges fortsatt på fakturalinjen i kjernen.

Satser 25/15/12 kan blandes på samme innenlandske NOK-bilag. Nullsats, særmarkører,
eiendeler og utenlandshandel inngår fortsatt ikke. Fradragsretten vurderes av
brukeren ut fra anskaffelsen, som omtalt i Skatteetatens
[veiledning om fradrag](https://oppslag.rettskilder.skatteetaten.no/rettskilder2/type/handboker/merverdiavgiftshandboken/gjeldende/MVA2025_M-8).
Beregningen anvender et uttrykkelig valg, uten å bekrefte vilkårene for fradrag.

Linjevurderinger lagres som vurderingspayload 2; felles vurdering beholder
payload 1. Nye kjøpsobservasjoner har payload 7 og bruker fradragskontekst 2
ved linjevurdering, ellers 1. Observasjoner 4/5/6 og vurderinger 1 leses uten
omskriving eller etterfylling. Linjedekning, satssamsvar, andel, grunnlag og
beregnede linjebeløp kontrolleres også ved historikklesing. Ingen DDL.

Bekreftet bokføring bruker linjens andel og fordeler fradragsført MVA på
2710/2711/2713 for 25/15/12 %. MVA uten fradrag går til linjens kostnadskonto.
Kontrollen viser hver MVA-konto separat og krever eksakt fordeling for
linjevurderinger. En balansert flytting mellom MVA-kontoer blir avvik selv om
samlet MVA og bilagsbalanse er riktig. Kjernens vanlige kjøpsjournal kan ikke
omgå en lagret linjevurdering, heller ikke om alle linjer har fullt fradrag;
bruk den bekreftede bokføringsflyten.

Eksempel med tre linjer à netto 100: 25 % MVA med 50 % fradrag, 15 % med fullt
fradrag og 12 % uten fradrag gir kostnad 324,50, inngående MVA 12,50 på 2710 og
15 på 2711, og leverandørgjeld 352. Kreditnota har motsatt fortegn. Avrunding
skjer per linje før beløp grupperes på hovedbokskonto.

Korreksjonsløpet kan endre linjeandelene og gå fra en eldre felles vurdering
til linjevurdering. Endret begrunnelse uten endret fradragsfordeling oppretter
ikke unødvendige korreksjonsposter. Originalene bevares, og hele kjeden
verifiseres før erstatningsbilaget brukes i kontrollen. Begrensningene i
0.56.0 gjelder fortsatt, inkludert uendrede kildeobservasjoner, samme måned,
åpen periode og brukerens erklæring om at grunnlaget ikke er innrapportert.
