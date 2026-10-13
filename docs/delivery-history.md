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
