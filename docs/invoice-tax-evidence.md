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
