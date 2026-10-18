# Norsk oppstartsoversikt

Fra 0.76.0 viser oppstartsveilederen én tabell med **oppgave, status og hva du
gjør**, med lenke til riktig verktøy. De elleve stegene og fram/tilbake-knappene
er fjernet. Gamle steglenker åpner samme oversikt.

Status leses fra dagens innstillinger der det er mulig. **Oppsett funnet**
betyr at de aktuelle feltene er fylt ut, ikke at alle norske krav er oppfylt.
**Må settes opp** gjelder manglende grunninnstillinger; **Vurderes manuelt** gjelder
oppgaver som krever brukerens vurdering. **Delvis støtte** og **Ikke levert**
viser konkrete funksjonsmangler. Feil ved lesing vises som **Kan ikke leses**.
Det finnes ingen egen fullføringsstatus, godkjenning, bekreftelse eller historikk.

Oppdater oversikten etter at du har lagret på siden lenken åpner. Foretak,
brukere, standardkontoer, journaler, MVA-kontoer, bank, dokumentmodeller,
regnskapsperioder og avslutningsoppsett bruker kjernens sider. Norsk tillegg
brukes ved kontoplaninstallering, norske kodevalg, bestilte nummerserier og
MVA-uttrekket. Den separate bankkoblingshjelperen og overlappende kontooppgaver
inngår ikke i veilederen.

Tabellen dekker foretak/tilgang, valuta/språk, MVA-registrering, kontoplan,
standardkontoer/journaler, satser/kontoer, bank, kunder/varer/tjenester,
nummerering, dokumenter, perioder/overgang, utenlandshandel/fradrag,
MVA-grunnlag/melding, EHF, SAF-T, årsavslutning, næringsspesifikasjon/skattemelding
og bevaring/drift. Betingede behov må vurderes for faktisk virksomhet.

Kontoklasser og teknisk saldoovergang er kvalifisert i 0.77.0. Normal kjøpsflyt med
fradragsandel er kvalifisert i 0.79.0 for [støttet omfang](purchase-vat.md).
Full årsavslutning og flere rapporteringsleveranser gjenstår. Oversikten skjuler ikke disse manglene eller
skriver regnskapsdata. Den er en veiledning til oppgavene; løpende arbeid skjer
fortsatt i kjernens vanlige menyer.

Fra 0.80.0 er ordinær bank-/MVA-periode prøvd gjennom kjernen, med
portalinnlevering som valgt leveringsvei. Se [bank, MVA og periodeavslutning](vat-period-close.md)
for betaling, kvittering, oppgjør, retting og avgrensning.

Fra 0.83.0 lenker SAF-T-raden til kjernens regnskapseksport og kontoplanoppsett.
Status «Vurderes manuelt» gjelder periode, koblinger og støttet omfang, ikke en
ny godkjenningsrunde. [SAF-T-arbeidsflyten](saft-export.md) dekker 2025–2026 og
NOK; andre år/behandlinger har uttrykkelige begrensninger.
