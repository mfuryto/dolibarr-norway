> Historisk dokumentasjon til og med 0.64.0. Funksjonen er fjernet i 0.65.0.
> Se [gjeldende plan](delivery-plan.md). Eldre tilleggshistorikk beholdes i arkivet.

# Lagrede avgiftsopplysninger på fakturalinjer

Fra 0.27.0 viser Norsk fakturakontroll lagret avgiftskildekode og særmarkering på
hver linje sammen med sats, netto og MVA. Åpne fakturaen via veiviserens steg 7
eller [fakturakontrollen for periode](invoice-period.md).

Koden leses direkte fra kjernens `vat_src_code`. Den vises som lagret, uten oppslag
i dagens satsregister og uten omtolking til en norsk rapporteringskode. Også kode
`0` bevares. Tom/null/blank kode vises som «Ingen kode lagret». Et utilgjengelig
eller ugyldig kildefelt skilles fra en kjent, tom kode.

Særmarkeringen leses fra kjernens `info_bits`. Null betyr at ingen særmarkering er
lagret. Et positivt heltall vises som en kjernemarkering som må vurderes, med den
lagrede verdien. Manglende/ugyldige verdier vises som utilgjengelige, ikke som null.
Markeringen tolkes ikke som en norsk avgiftskode eller et norsk fritak.

Periodeoversikten teller linjer med tom kode, særmarkering og utilgjengelige
avgiftsopplysninger per dokument. Åpne dokumentlenken for å se de faktiske
linjene. Antallene gjelder bare linjene i dokumentet og dokumentene i det viste
utvalget. Avkorting ved over 200 dokumenter gjelder fortsatt.

En tom kode er ikke i seg selv bevis på feil avgiftsbehandling. En lagret kode er
heller ikke bevis på riktig behandling eller historisk registrering. Koden og
markeringen er dagens lagrede fakturalinjefelt; de er ikke et uforanderlig
revisjonsspor, en versjonert norsk mapping eller et komplett historisk avgiftsgrunnlag.

Ingen koder eller markeringer fylles inn eller endres. Fakturavalidering og
beløpssammenligning beholder tidligere virkemåte. Regnskapsfaglig klassifisering,
rapportkode-/SAF-T-mapping, fradrag, hovedboksavstemming og MVA-melding gjenstår.
Ingen tabeller eller migreringer innføres.

Syntetiske tester dekker lagret kode/markering, kode `0`, tomme og utilgjengelige
felt, ugyldige markeringer, uendrede inngangsdata og kjernens fakturalesing etter
lagring. Periodeprøven kontrollerer antall per dokument. Norsk/engelsk visning og
bredden på faktura- og periodesidene prøves separat.

## Referansekontroll fra 0.28.0

Fakturakontrollen viser i tillegg om den eksakt lagrede koden finnes blant de
30 numeriske identifikatorene i Skatteetatens
[Standard Tax Codes CSV, fast revisjon](https://github.com/Skatteetaten/saf-t/blob/05179521e435d82feb0b2d6c89a92a32a4f2d02f/Standard%20Tax%20Codes/CSV/Standard_Tax_Codes.csv).
Bare identifikatorene inngår i modulen. Beskrivelser, satser og behandlingsregler
kopieres ikke. Ingen nettverksoppslag gjøres ved fakturakontroll; oppdatering av
referansen krever en ny, testet modulversjon.

Tre resultater vises: kode finnes i referanselisten, kode finnes ikke, eller
ingen tilgjengelig kode å sammenligne. `0` sammenlignes som en faktisk kode.
`03`, `3 ` og lokale navn normaliseres ikke til `3`. En lokal kode som ikke
finnes i listen er ikke automatisk feil. Identisk skrivemåte betyr heller ikke
at Dolibarr-koden har samme betydning som den offisielle koden.

Dette er et hjelpemiddel før eksplisitt mapping, ikke en implementasjon av
mapping. Ingen kode tildeles fra sats, kontonummer, kunde, land eller beløp.
Periodeoversikten endres ikke og beregner ingen rapport etter referansekoder.
Referanselisten er ikke MVA-meldingens rapporteringsformat og bekrefter ikke
hvilket SAF-T-skjema som gjelder for en rapporteringsperiode.

Akseptert teknisk omfang: skrivebeskyttet, eksakt referansesammenligning på den
eksisterende fakturakontrollen i norsk/engelsk visning. Tester dekker kode `0`,
lokale aliaser, mellomrom, manglende kildefelt og uendrede data. Historisk,
versjonert mapping og avgiftsbehandling er fortsatt utenfor støttet omfang.
