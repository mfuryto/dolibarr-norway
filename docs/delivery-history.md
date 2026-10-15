# Leveransehistorikk til og med 0.40.1

Historisk dokument, ikke gjeldende prioritering. Se [arbeidsplanen](delivery-plan.md).

Fra 0.16.0 fylles et tomt MVA-nummer fra gyldig norsk organisasjonsnummer ved ordinær lagring når «MVA brukes» er valgt. Eksisterende MVA-nummer og registreringsstatus endres ikke. Se [foretaksoppsett](company-setup.md).

Bankkoblingens avgrensning og vern er beskrevet i [bekreftet bankkobling](bank-defaults.md). Bank- og reskontroavstemming gjenstår.

Fra 0.18.0 samler [dokumentoppsettkontrollen](document-setup.md) valgt nummerering, nummermasker, effektive PDF-modeller og språk. Den er skrivebeskyttet; konkret dokumenttest og gjennomgang av etablerte nummerserier gjenstår per installasjon.

Fra 0.19.0 viser [periodediagnostikken](period-setup.md) perioder, datodekning, overlapp, standardvalg og kjernens periodeinnstilling uten endringer. Full kvalifisering av periodelåsing og retting gjenstår.

Fra 0.20.0 viser [MVA-kontokontrollen](vat-setup.md) aktive norske satsrader, eksplisitte kontoer, reservekontoer og strukturell kontostatus. Avgiftsbehandling, bokført metadata, rapporteringsmapping og MVA-melding gjenstår.

Fra 0.21.0 kan én [MVA-virkningsdato lagres eksplisitt](vat-registration.md), knyttet til foretaksidentitet og bekreftelsestid. Datovarsel i fakturakontrollen endrer ingen bilag eller MVA-status. Rettbar historikk, flere registreringsperioder, avgiftsbehandling og MVA-melding gjenstår.

Fra 0.22.0 viser [fakturaens MVA-beløp](invoice-amounts.md) lagrede linjebeløp og sammenligner netto/MVA med fakturatotalene. Null MVA krever begrunnelsesgjennomgang. Kontroll av avgiftsbehandling, hovedbok og MVA-melding gjenstår.

Fra 0.23.0 har veiviseren en fast oppgaverekkefølge, forklarte knapper som åpner ny fane, og separate seksjoner for lagrede innstillinger, tilleggskontroller og avgrensninger. Navigasjonen lagrer fortsatt ikke fremdrift eller innstillinger.

Fra 0.24.0 kan administrator lagre arbeidsstatus per veivisersteg. Oversikten viser status for alle steg, avgrenset til aktivt foretak. Endret organisasjonsnummer krever ny gjennomgang. Markeringene endrer ikke oppsett, dokumenter eller kontrollresultater og gir ingen produksjonsaksept.

Fra 0.24.1 bruker veiviserens steg 6 «Varer og tjenester» og generelle leveransebeskrivelser på norsk og engelsk. Dette er en tekstpresisering, uten endret funksjonelt eller regnskapsfaglig støttet omfang.

Fra 0.24.2 omtaler veiviseren leveranser generelt, og fakturakontrollen bruker varer/tjenester fremfor programvare. Organisasjonsformer omtales som valg, uten å kvalifisere særregler. Dette endrer ikke dokumentert støttet regnskapsomfang eller uferdige funksjoner.

Fra 0.25.0 foreslår veiviseren hvor arbeidet kan fortsette ut fra eksisterende arbeidsstatus. Direkte valg av steg beholdes, og navigasjon endrer ingen markeringer eller innstillinger.

Fra 0.26.0 samler [fakturakontrollen for periode](invoice-period.md) dokumentstatus, type og beløpsavvik for et datointervall i aktivt foretak. Ingen summer på tvers av dokumenter eller MVA-melding beregnes; avgiftsmetadata, rapportering og hovedboksavstemming gjenstår.

Fra 0.27.0 viser [fakturalinjenes lagrede avgiftsopplysninger](invoice-tax-evidence.md) kildekode og særmarkering uten oppslag i dagens satsregister. Periodeoversikten teller tomme koder og markeringer. Uforanderlig avgiftshistorikk, norsk rapporteringsmapping og MVA-melding gjenstår.

Fra 0.28.0 viser fakturakontrollen eksakt medlemskap i en fast offisiell SAF-T-kodereferanse. Lik skrivemåte bekrefter ikke mapping eller avgiftsbehandling. Neste faglige grunnlag er eksplisitt, versjonert mapping med historisk metadata; MVA-melding og SAF-T-eksport gjenstår. Se [avgiftsopplysninger](invoice-tax-evidence.md).

Fra 0.29.0 kan administrator [prøve én datert avgiftsmapping](tax-mapping-preview.md) mot det åpne dokumentets lagrede linjekoder. Valget lagres ikke, og særmarkeringer må avklares. Versjonert mapping med rettbar historikk, bokførte avgiftsmetadata og rapportering gjenstår.

Fra 0.30.0 kan administrator [lagre daterte avgiftskodekoblinger](tax-mappings.md) per foretak og salg/kjøp, med bekreftet retting, avslutning og bevart oppsettshistorikk. Overlapp og foreldede forhåndsvisninger sperres. Koblingene brukes ennå ikke ved bokføring eller rapportering. Kobling til bokførte avgiftsmetadata og historisk rapportversjon gjenstår.

Fra 0.31.0 kan [fakturalinjer sammenlignes mot en uttrykkelig valgt oppsettsversjon](invoice-mapping.md). Senere rettinger/avslutninger tas ikke med i kandidatsammenligningen. Dette oppretter ikke bokført avgiftsgrunnlag eller historisk rapportering.

Fra 0.32.0 viser [hovedboksreferanser for fakturaen](invoice-ledger.md) faktisk lagrede posteringer og referanseavvik. Samlede posteringer skilles fra bestemte fakturalinjer. Dette er ikke beløps-/MVA-avstemming eller bokførte avgiftsmetadata.

Fra 0.33.0 finnes et [versjonert lagringsgrunnlag for avgiftsopplysninger](tax-evidence-storage.md), med migreringshistorikk, strukturelt vern og testet backup/gjenoppretting. Ingen avgiftsøyeblikksbilder opprettes i ordinær drift ennå. Innholdsskjema, bekreftet lagring/retting og kobling til faktiske posteringer må kvalifiseres før rapportering.

Fra 0.34.0 kan administrator forhåndsvise og bekrefte [kontrollkopier av dagens fakturagrunnlag](invoice-evidence.md), hovedboksreferanser og valgt oppsettsversjon. Ny kopi bevarer tidligere revisjoner, og endringer etter forhåndsvisning sperrer lagring. Dette dokumenterer ikke opprinnelig bokføringstidspunkt og godkjenner ikke MVA-behandling. Neste del er en lesbar sammenligning av lagret kopi mot dagens grunnlag, før videre beløpsavstemming og kvalifisering av rapportering.

Fra 0.35.0 kan [enkle nummerserier](numbering.md) for F/K/T og kunde-/leverandørnumre velges med startnummer, forhåndsvisning og bekreftelse. Eksisterende numre beholdes. Dette er kvalifisert nummereringsfunksjonalitet innen dokumentert omfang, ikke full norsk regnskapsmessig produksjonsaksept.

Fra 0.36.0 kan en lagret kontrollkopi sammenlignes med dagens fakturafelt, linjer og hovedboksposter mot samme oppsettsversjon. Endrede verdier og nye/fjernede rader vises uten lagring. Dette kvalifiserer ikke beløps-/MVA-avstemming eller rapportering, som fortsatt gjenstår.

Fra 0.37.0 finnes en samlet, kildebegrunnet oversikt over standardkontoer og
lukkingskontoer, med eksplisitt valg og utfylling av tomme felt i nytt oppsett.
Regnskapskoder for varer/tjenester omfattes som standarder; individuelle kort
beholdes. Bruttosammenligning mellom faktura og kildekoblet hovedbok er lesende.
Full konto-/MVA-avstemming, årsavslutning og rapportering gjenstår. Se
[standardkontoer](standard-accounts.md) for faggrunnlag og støttet omfang.

Fra 0.38.0 viser veiviserens steg 6 en samlet, lesende gjennomgang av egne
regnskapskoder og standardkontoer på varer og tjenester. Kontokandidater,
handelsfelter, inaktive/manglende kontoer og avkorting synliggjøres. Dette
endrer ingen kort og simulerer ikke faktisk fakturakontering.

Fra 0.39.0 er alle åtte standardfelt for innenlandske varer/tjenester og
eksport/import gitt kildebegrunnede, formålsavgrensede alternativer. Eksport
har egne kontoer fra kontoplanprofilen, og tjenestekjøp dekker flere generelle
kostnadstyper. Valgene krever fortsatt forhåndsvisning/bekreftelse og opphever
ikke vern for eksisterende valg eller historikk. MVA-beregning og rapportering
er ikke utvidet. Se [faggrunnlag og forslag](trade-account-suggestions.md).

Fra 0.40.0 leveres bekreftet utfylling av tomme kontokoder på ordinære norske
MVA-satser (25/15/12 %) i et nytt bokføringsoppsett. Eksisterende valg bevares;
nullsats, historiske og spesielle rader, import og omvendt avgiftsplikt holdes
utenfor. Ingen satser eller bilag endres. Se [MVA-kontoer](vat-accounts.md).
Teknisk aksept omfatter lagring, transaksjonsvern og kontooppsett; norsk
MVA-beregning, fradragsvurdering og rapportering er fortsatt ikke godkjent.

Fra 0.40.1 forklares «Skattekontoer» på standardkontosiden og som valgfri hjelp
fra veiviserens steg 4. Veiledningen skiller skatte-/avgiftstyper fra kontoplan,
MVA-satskontoer og lokal skatt 1/2. ENK-eksempelet er avgrenset fra andre
foretaksformer. Ingen typer, koblinger, bilag eller beregninger endres.
Se [skattekontoer](tax-accounts.md).


## Implementasjonsnotater fra 0.42.0 til 0.64.0

Flyttet fra arbeidsplanen 2026-10-15. Avsnittene nedenfor beskriver tidligere
leveranser og tidligere prioriteringer. De bestemmer ikke videre utvikling;
se den gjeldende [arbeidsplanen](delivery-plan.md). Implementert betyr ikke
at funksjonen er nødvendig etter norske krav.

## A2 – første del levert i 0.42.0

[Kunde-/salgs-/MVA-fordeling](invoice-allocation.md) kan sammenlignes med en
uttrykkelig valgt ordinær innenlandsk profil for 25/15/12 %. Faktiske
posteringer og forventede beløp vises separat per rolle, også ved kreditnota.
Feil avgiftsfordeling kan avdekkes selv om bilaget balanserer. Konsistent
lesing og kontroll av bilagsfullstendighet beskytter resultatet.

Dette fullfører ikke A2. Neste del er koblingen mellom fakturalinjer,
konkrete kontoer og historisk avgiftsbehandling, deretter kjøp, manuelle
bilag og korreksjoner. MVA-perioderapporten i B1 venter på kvalifisert bokført
avgiftsgrunnlag. Ingen kontrollstatus gir avgiftsmessig produksjonsaksept.

## A2 – lagrede linjekontoer i 0.43.0

Fakturalinjens egne lagrede kontokoblinger sammenlignes med bokført netto
per konkret salgskonto. Direkte linjereferanser og aggregerte kontosummer
skilles uttrykkelig. Dagens produkt- og standardkontoer brukes ikke som
reserve, og manglende/fremmede koblinger kan ikke gi samlet samsvar.

Denne delen fullfører linjekontodiagnostikken innenfor de valgte profilene.
Historisk avgiftsgrunnlag, kontovalgenes opprinnelige gyldighet, kjøp,
manuelle bilag og rapportering gjenstår. Neste arbeid forblir A2, før B1/B2.

## A2 – kildeobservasjoner ved nye salgsposteringer i 0.44.0

[Hendelseslagring](posting-observations.md) bevarer rå faktura-, linje-,
kontokoblings-, motparts- og oppsettsopplysninger ved støttede nye
BOOKKEEPING_CREATE-hendelser. Hendelsen lagres i kjernens transaksjon, uten
etterfylling av eldre bilag. Dagens kilder kan sammenlignes med observasjonen;
endret eller fjernet hovedbokspost overskriver ikke lagringen.

Dette er et teknisk lagrings-/sporingsgrunnlag under KR05/KR08/KR09, ikke
ferdig historisk MVA-kvalifisering. Kjøp, manuelle bilag, øvrige import- og
korreksjonsveier, fradragsregler og rapportering gjenstår under A2.

## A2 – bevart oppsettskobling for avgiftskoder i 0.45.0

Nye kildeobservasjoner bevarer automatisk den eksakte lokale linjekodens
kobling til det daterte salgskodeoppsettet ved bokføring. Oppsettets og regelens
versjoner følger resultatet. Manglende, motstridende eller ufullstendig
kildegrunnlag forblir uavklart; sats eller kontonummer brukes ikke til å gjette.
Eldre hendelser etterfylles ikke, og senere oppsettsrettinger overskriver ikke
bevarte koblinger. Kjernens salgsjournal og eksisterende hendelseslagring gjenbrukes.

Dette er en teknisk kobling til eksplisitt oppsett, ikke ferdig kvalifisert
avgiftsbehandling. Neste A2-arbeid må knytte registreringsperioder, konkrete
avgiftsscenarioer og hovedboksfordeling til dette grunnlaget. Kjøp, manuelle
bilag, korreksjoner og rapportaksept gjenstår før B1/B2.

## A2 – registreringssammenligning i arbeidsflyten i 0.46.0

Kjernens fakturavalidering gir handlingsrettede varsler ved MVA og manglende,
ugyldig eller avvikende registreringsgrunnlag, dato før virkningsdato og
kreditnota. Én virkningsdato blir ikke en generell sperre eller avgiftsgodkjenning.
Den vanlige arbeidsflyten gjenbrukes; ingen ny kontrollside innføres.

Nye salgshendelser bevarer datosammenligning og bekreftelsesopplysninger
sammen med råkilder og kodekobling. Eldre payloadversjoner leses uten
etterfylling. Full registreringshistorikk med avregistrering/ny registrering,
retting, kildefakturaens opprinnelige status, konkret avgiftsbehandling og
hovedboksfordeling gjenstår under A2, før rapporteringsaksept i B1/B2.

## A2 – fordeling mot bevart grunnlag i 0.47.0

Den eksisterende profilkontrollen sammenligner nå også bevarte fakturalinjer
og kontokoblinger med faktisk hovedbok i ett konsistent lesebilde. Felles
beløpsregning, profiler og bilagsfullstendighet gjenbrukes. Manglende,
fjernede, endrede eller sprikende hendelser/poster gir ikke samsvar; endring
i dagens fakturakilder erstatter ikke det bevarte grunnlaget. Summerte
kontoposteringer skilles fra enkeltlinjefordeling.

Dette kvalifiserer den tekniske sammenligningen innen uttrykkelig valgte
25/15/12-profiler, ikke historisk avgiftsbehandling eller rapportgrunnlaget
som helhet. Neste A2-arbeid er konkrete avgiftsscenarioer, kilde-/rettingsløp,
kjøp og manuelle bilag med samme krav til sporbarhet. Full registreringshistorikk,
fradrag, nullsats og B1/B2-aksept gjenstår.

## A2 – kildebevaring for kjøp i 0.48.0

Kjernens kjøpsjournal bruker samme BOOKKEEPING_CREATE-hendelse som salg.
Den eksisterende hendelseslagringen utvides til leverandørfaktura og
leverandørkreditnota, med rå linje-/avgifts-/kontofelt, leverandørreferanse,
valutafelt og motpart. Kjøp og salg skilles på dokumenttype også når numeriske
faktura-ID-er er like. Lagring følger kjernens transaksjon og feil stopper
posteringen uten å etterlate en post uten kildeobservasjon.

Dette er teknisk bevaring, ikke fradragsvurdering eller kvalifisert
kjøpsfordeling. Salgsprofiler og selgerens registreringsdato brukes ikke
som avgiftsvurdering av kjøp. Neste A2-arbeid er datert kjøpskodekobling,
kjøpsfordeling, fradragsomfang og korreksjonsløp. Manuelle bilag, nullsats og
rapportaksept gjenstår. Papirløs opplasting og matching tas til slutt; krav
til dokumentasjon og arkiv gjelder fortsatt under de øvrige leveransene.

## A2 – bevart datert kjøpskodekobling i 0.49.0

Nye kjøpshendelser bevarer automatisk eksakt linjekodes daterte kjøpsregel,
mål og oppsettsversjon. Salgsregler er ingen reserve. Manglende eller
ufullstendig grunnlag og særmarkører forblir uavklart. Senere retting endrer
ikke den bevarte koblingen, og eldre rå kjøpshendelser etterfylles ikke.
Kjernens kjøpsjournal, samme lagring og eksisterende oppsett gjenbrukes.

Dette kvalifiserer teknisk oppsettskobling, ikke fradragsrett eller MVA.
Neste A2-arbeid er kjøpsfordeling mot hovedbok, uttrykkelig fradragsomfang og
korreksjonsløp. Manuelle bilag, nullsats, registreringshistorikk og B1/B2-aksept
gjenstår. Papirløs opplasting og matching er fortsatt prioritert sist.

## A2 – kjøpsfordeling i kjernens leverandørfakturabilde i 0.50.0

En valgfri, lesende [kjøpskontroll](purchase-allocation.md) vises i Dolibarrs
leverandørfakturabilde. Valgte innenlandske 25/15/12-profiler for fullt fradrag
sammenligner bevart netto, MVA, brutto og konkrete linjekontoer med hovedbok.
Kreditnota, summerte poster, endrede kilder og bilagsfullstendighet håndteres
uten en ny bilagsflyt. Fradragsrett velges eller godkjennes ikke automatisk.

Dette kvalifiserer teknisk sammenligning innen valgt omfang. Neste A2-arbeid
er uttrykkelig delvis/ingen fradragsbehandling og dokumenterte korreksjonsløp.
Blandede satser, utenlandskjøp, nullsats, manuelle bilag, registreringshistorikk
og B1/B2-aksept gjenstår. Papirløs opplasting og matching ligger fortsatt sist.

## A2 – uttrykkelig delvis/ingen fradragssammenligning i 0.51.0

Den eksisterende kjøpskontrollen sammenligner nå også delvis og ingen
fradragsføring for valgte enkeltsatser. Delvis krever uttrykkelig prosent;
ørebundet linjeavrunding og kreditnotafortegn kontrolleres. Resterende MVA
fordeles på bevarte kjøpskontoer i sammenligningen, uten endring i hovedboken.
Kjernens globale bokføring med avgift på kjøpskonto er undersøkt og testet;
NPR er uttrykkelig avgrenset og brukes ikke som norsk fradragsmodell.

Dette er teknisk sammenligning av et valgt grunnlag, ikke lagret historisk
fradragsvalg eller implementert delvis fradragsbokføring. Neste A2-arbeid er
begrunnede, versjonerte fradragsvalg, linjevis sporbarhet og kvalifisert
bokførings-/korreksjonsintegrasjon. Blandede satser, utenlandskjøp, nullsats,
manuelle bilag, registreringshistorikk og B1/B2-aksept gjenstår.
Papirløs bilagsflyt og matching er fortsatt prioritert sist.

## Valgfrie kunde-/leverandørprefikser i 0.52.0

Nummerserieoppsettet tilbyr valgfrie prefikser foran femsifrede kunde- og
leverandørkoder, eksempelvis K00001 og L00001. Prefiksbytte fortsetter hver
rolles teller uten å endre gamle koder. Brukte startnumre forblir låst.
Kjernens eksisterende allokering, bekreftelsesflyt og eksterne partskodemodul
gjenbrukes. Tidligere oppsett leses med tomme prefikser; valg endres bare ved
uttrykkelig bekreftelse. Ingen skjemaendring.

Dette er en avgrenset oppsettsforbedring etter prosjekteiers valg. Videre
A2-prioritet er uendret: begrunnede historiske fradragsvalg, linjevis sporbarhet
og bokførings-/korreksjonsintegrasjon. Papirløs bilagsflyt ligger fortsatt sist.


## A2 – begrunnede historiske fradragsvalg i 0.53.0

Leverandørfakturabildet tilbyr uttrykkelig forhåndsvisning og bekreftet lagring
av fullt/delvis/ingen fradrag med begrunnelse. Valget og beregnede linjebeløp
bevares sammen med fakturagrunnlag, bruker, tidspunkt og stigende revisjon.
Samme andel gjelder alle linjer; innenlandske NOK-kjøp/kreditnotaer med én sats
25/15/12 og ørepresisjon støttes. Endret grunnlag gir varsel; gamle revisjoner
overskrives ikke. Uendret lagret valg brukes i den eksisterende sammenligningen
når brukeren ikke har valgt en annen profil. Ingen automatisk fradragsvurdering,
bokføringsendring eller påstand om et historisk bokføringstidspunkt.

Eksisterende versjonert kontrollarkiv gjenbrukes med egen dokumenttype og
payloadversjon; ingen skjemaendring eller tilbakefylling. Tester kvalifiserer
lagring/historikk, linjebeløp, endret grunnlag, replay, tilgang og HTTP-bekreftelse.
Videre A2-arbeid er kobling av valgt revisjon til nye bokføringshendelser,
kvalifisert fradragsbokføring og korreksjonsløp. Ulike andeler per linje,
blandede satser og særbehandling gjenstår. Papirløs bilagsflyt ligger sist.


## A2 – bevart vurderingsrevisjon ved bokføring i 0.54.0

Nye kjøpsposteringer bevarer siste lagrede fradragsvurdering med kildedata,
linjebeløp, revisjon og integritetsavtrykk i kjernens egen transaksjon.
Koblingen skiller mellom samsvarende grunnlag, endret/ikke støttet grunnlag
og manglende vurdering. Historikken vises i leverandørfakturabildet. Nyere
vurderinger endrer ikke gamle posteringers kobling. Tidligere payloadversjoner
leses uten tilbakefylling; ny kjøpspayload har versjon 6.

Dette kvalifiserer teknisk sporbarhet ved nye bokføringshendelser, ikke at
valgt fradrag er bokført. Kjernens kontofordeling er uendret, og den eksisterende
sammenligningen viser fortsatt avvik ved eksempelvis valgt delvis fradrag og
full MVA-postering. Tester dekker kjøpsjournalen, kreditnota, revisjonsbytte,
kildeendring, manglende valg, semantisk integritet, ikke-administrativ operatør,
rollback og bevaring av eldre payload. Ingen skjemaendring.

Neste A2-arbeid er kvalifisert fradragsbokføring og korreksjonsløp med bevart
vurderingsrevisjon. Blandede satser, ulike linjeandeler og øvrig særbehandling
gjenstår. Papirløs bilagsflyt er fortsatt sist.


## A2 – bekreftet førstegangsbokføring i 0.55.0

Leverandørfakturabildet kan forhåndsvise og bokføre et nytt kostnadsbilag med
siste lagrede, uendrede fradragsvurdering. Fullt, delvis og intet fradrag ved
25/15/12 støttes for innenlandske NOK-fakturaer og kreditnotaer, med samme andel
på alle linjer. Beløp avrundes per linje og posteres via kjernens API med
konkret linjekobling, hovedbokskonto, leverandørreskontro og vurderingsrevisjon.

Omfanget krever aktive kostnadskontoer i klasse 4–7 i valgt kontoplan, standard
leverandørkonto 2400, støttet inngående MVA-konto, leverandørreskontrokode,
åpen entydig regnskapsperiode og nøyaktig ørebalanse. Gamle eller fjernede
posteringer i kontrollarkivet sperrer ny førstegangsbokføring. Bekreftelse
verner kilde/oppsett/historikk; hele bilaget og arkivet rulles tilbake ved feil.
En egen versjonert bokføringsregistrering bevarer bekreftet grunnlag og
posterings-ID-er. Ingen DDL eller endring av tidligere posteringer.

Vanlig kjøpsjournal sperres for nye posteringer med lagret delvis/intet fradrag,
siden den ikke anvender valget. Bekreftet flyt kan ikke brukes til å erstatte
allerede bokførte bilag. Tekniske tester kvalifiserer kontofordeling og
sporbarhet innen omfanget, ikke fradragsrett eller norsk produksjonsetterlevelse.
Neste arbeid er et uttrykkelig korreksjonsløp med bevarte originalposter og
vurderingsrevisjoner. Blandede satser, ulike linjeandeler, eiendeler, nullsats,
utland og øvrig særbehandling gjenstår. Papirløs bilagsflyt ligger sist.


## A2 – sporbart fradragskorreksjonsløp i 0.56.0

Bilag fra bekreftet førstegangsbokføring kan få endret fradragsandel gjennom
full reversering av forrige effektive bokføring og et nytt erstatningsbilag.
Originalene og alle vurderingsrevisjoner bevares; flere korreksjoner kan kjedes.
Forhåndsvisning viser begge nye bilag, dato, begrunnelse og vurderingskobling.
Lagring skjer atomisk via kjernens hovedboks-API, uten skjemaendring.

Første omfang krever uendret fakturagrunnlag og bevarte kildeobservasjoner
(inkludert fakturastatus), ny fradragsandel, samme
kalendermåned, åpen regnskapsperiode og poster som ikke er eksportert eller
endelig validert. Brukeren må uttrykkelig bekrefte at grunnlaget ikke er brukt
i en innsendt MVA-melding; dette verifiseres ikke mot en innsendingshistorikk.
Retting av innrapportert grunnlag, andre perioder, beløp eller kontokoblinger
er ikke kvalifisert. Kjernens returfunksjon er undersøkt; den bevarer ikke alle
referanser ved opprettingstidspunktet, så kjernens createStd-API gjenbrukes med
modulens bevarte koblinger og transaksjonskontroll.

Kontrollen validerer hele kjeden og eksakt reversering før erstatningsbilaget
brukes som effektivt grunnlag. Endrede/fjernede/ekstra poster, korrupte metadata,
foreldet bekreftelse og gjentatt innsending sperres. Tekniske tester dekker
konto-/beløpsfordeling, originalbevaring, kreditnota, satser, kjeding, rollback,
periode-/eksportvern og native HTTP-flyt. Dette er ingen bekreftelse av
fradragsrett eller norsk regnskapsmessig produksjonsaksept.

Neste A2-arbeid er ulike fradragsandeler per linje og blandede satser, samt
kvalifisering av videre rettings-/periode- og avgiftsscenarioer. B1/B2 krever
fortsatt avstemt grunnlag, manuelle bilag, nullsats og registreringshistorikk.
Papirløs bilagsflyt ligger fortsatt sist.


## A2 – linjevurdering og blandede satser i 0.57.0

Innenlandske NOK-kostnadskjøp og kreditnotaer kan ha egne uttrykkelige
fradragsvalg og begrunnelser per linje, med blandede satser 25/15/12.
Dekningen må være komplett og entydig; manglende, dupliserte, fremmede eller
satsstridige linjevalg avvises. Vurderingspayload 2 og ny kjøpsobservasjon 7
bevarer linjegrunnlaget; eldre vurderinger og observasjoner leses uendret.
Ingen skjemaendring eller automatisk fradragsvurdering.

Bekreftet bokføring grupperer inngående MVA per satskonto. Kontrollen avstemmer
hver MVA-konto og linjens kostnad, slik at riktig samlet MVA ikke skjuler feil
kontofordeling. Øreavrunding gjøres per linje. Det eksisterende, avgrensede
korreksjonsløpet håndterer også nye linjeandeler og overgang fra eldre felles
vurdering, med bevart original, full reversering og erstatning. Bare endret
fradragsfordeling, uendret grunnlag og ikke innrapportert grunnlag i samme måned
støttes fortsatt; øvrige korreksjonsbegrensninger fra 0.56.0 gjelder.

Tekniske tester kvalifiserer linjedekning, eksakte kontobeløp, feilfordeling med
riktig MVA-total, kreditnota, korreksjon, eldre historikk, HTTP og native vern.
Dette bekrefter ikke fradragsrett, avgiftskode-/rapporteringsmapping eller samlet
norsk regnskapsmessig produksjonsetterlevelse. Neste arbeid er M0, med
meny-/arbeidsflytintegrasjon av eksisterende funksjoner. Deretter fortsetter
A2 med uttrykkelig nullsats/fritak/unntak, registreringshistorikk og manuelle
bilag samt videre periode-/rettingsscenarioer før B1/B2. Papirløs bilagsflyt
ligger sist.


## M0 – første menyintegrasjon i 0.58.0

Bilags- og periodekontroll er tilgjengelig fra Regnskap for interne brukere
med hovedboklesing. Kundefakturaen får en kontrollfane for eksisterende
administratorprofil. Fakturaperiodekontroll og relevante oppsettssider får
ordinære innganger under fakturering, regnskapsoppsett og bank. Daglige
kontrollinnganger fjernes fra oppstartsveilederen og samles ved oppgaven.

Se [menykartlegging, tilgang og videre arbeid](menu-integration.md). M0 er
ikke fullført: øvrige oppsettssider, produkt-/prisarbeid, enklere oppfølging
av fakturaavvik og kvalifisering av ordinære brukerroller gjenstår.
Menyplassering skal følge hver videre funksjonsleveranse. Dette endrer ikke
avgrensningene i A2 eller status for norsk regnskapsmessig produksjonsaksept.


## M0 – avgiftsoppsett og dokument-/produktfaner i 0.59.0

Avgiftsoppsettet nås fra Regnskap, med samme eksisterende innstillinger for
satser, kontoer, registerhistorikk og avgiftskodekoblinger. Regnskapskoder får
en kataloginngang og en fane på varen/tjenesten, avgrenset til valgt element.
Kundefakturaen får direkte inngang til sine kontrollkopier. Veiledende
EUR-prising flyttes til tilbudsmenyen og tas ut av veilederen/moduloppsettet.
Ingen nye regnskapsvurderinger, kurshentinger eller bokføringer skjer ved navigasjon.

Eksisterende administrator-/lagringsvern beholdes. Videre M0 gjelder
forenkling av avviksoppfølging og kvalifisering av operatørroller ved den
aktuelle funksjonen. Neste regnskapsfunksjonelle arbeid fortsetter A2:
nullsats/fritak/unntak, registreringshistorikk, manuelle bilag og videre
rettingsscenarioer. Ingen endring i faglig produksjonsaksept.


## M0 – rettet oppsettsnivå i 0.60.0

Kontoplan-/bokføringsoppsett, MVA-registerhistorikk og avgiftskodekoblinger
samles under Regnskap → Innstillinger → Norsk regnskapsoppsett. Underpunktene vises
ved åpnet oppsettsgruppe, ikke under daglig hovedbokarbeid. Den separate
bankkoblingslenken fjernes fra Bank; funksjonen beholdes fra regnskapsoppsettet.
Aksept gjelder menynivå, synlighet og bevarte innganger/tilganger. Ingen
endringer i bokføringsgrunnlag, faglig avgiftsomfang eller produksjonsaksept.


## A2 – eksplisitte salgskategorier uten beregnet MVA i 0.61.0

Fakturaens eksisterende kontrollfane skiller eksplisitte salgskategorier med
nullsats fra omsetning utenfor loven og inntekt som ikke er MVA-omsetning.
Sats-/beløpskonflikter, manglende datert kodekobling og særmarkeringer får
konkret oppfølging. Utførsel og omvendt avgiftsplikt vises som egne scenarioer,
ikke som ordinært innenlands fritak. Kontrollen bruker valgt oppsettsversjon
for dagens faktura og bare bevart oppsett for bokføringsobservasjoner.
Se [omfang, kilder og videre arbeid](sales-zero-vat.md).

Dette er en skrivebeskyttet konsistenskontroll, ikke et fullført grunnlag for
faglig avgiftsbehandling eller rapportering. A2 fortsetter med dokumentert
behandlingsgrunnlag for leveransen, registreringshistorikk, manuelle bilag
og videre rettingsscenarioer. Historiske payloads og avgiftskontekster er
uendret. Ingen endring av produksjonsaksept.


## A2 – registreringshistorikk i 0.62.0

Oppsettet støtter bekreftet import av første dato, retting, avslutning, nye
perioder og historikk ved feilregistrering. Overlapp avvises. Native varsler
og nye salgsobservasjoner bruker perioder/hull uten å behandle datodekning
som avgiftsgodkjenning. Payload 8/kontekst 2 brukes bare når historikk finnes;
eldre payloads og opprinnelig første dato bevares. Backup/gjenoppretting prøver
rettet og ny periode. Se [omfang og vern](vat-registration-history.md).

Videre A2 gjelder dokumentasjonsgrunnlaget for konkrete avgiftsbehandlinger,
myndighetsgodkjenning/etterfakturering, manuelle bilag og flere støttede
rettingsscenarioer. Periodemodellen er ikke full faglig kvalifisering av
registreringsforhold eller MVA-rapportering.

## A2 – dokumentasjonsgrunnlag i 0.63.0

Egen fakturafane lagrer begrunnelse og dokumentreferanse per innenlandsk
nullsatslinje med eksplisitt kode 5/6/7. Revisjoner bevares; endret grunnlag,
foreldet bekreftelse og skadet historikk avvises/synliggjøres. Se
[omfang og kilder](sales-tax-basis.md). Ingen endring av bokføring eller PDF.

Neste del knytter den bevarte begrunnelsesrevisjonen til faktisk bokføring.
Deretter gjenstår myndighetsgodkjenning/etterfakturering, manuelle bilag og
videre rettingsscenarioer før A2 er kvalifisert. Papirløs bilagsflyt er fortsatt sist.

## M0 – menyopprydding i 0.63.1

Nummerserier og dokumentoppsett brukes fra veilederens steg 7. Regnskapskode-
oversikten ligger under Norsk regnskapsoppsett; individuell katalogfane beholdes.
Daglige bilags-/periodekontroller følger åpnet Regnskap-gruppe. Denne rettingen
endrer ingen regnskapsdata. Neste A2-del er fortsatt begrunnelsesrevisjon ved bokføring.

## A2 – begrunnelsesrevisjon ved bokføring i 0.64.0

Native salgsobservasjoner bevarer siste tilgjengelige begrunnelsesrevisjon
og et låst grunnlag. Endret kilde vises som avvik; eldre observasjoner
etterutfylles ikke. Fakturafanen og fakturakontrollen viser hendelsens
begrunnelse ved oppgaven. Backup/gjenoppretting prøver begge revisjoner og
den opprinnelige koblingen. Ingen ny meny eller oppstartsoppgave.

Neste A2-del avklarer dokumentert myndighetsgodkjenning og etterfakturering;
deretter manuelle bilag og videre rettingsscenarioer. Full faglig avgifts-
og rapporteringsaksept gjenstår. Papirløs bilagsflyt er fortsatt sist.
