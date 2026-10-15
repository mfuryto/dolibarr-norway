# Kontroll av salg uten beregnet MVA

Fra 0.61.0 finnes en skrivebeskyttet konsistenskontroll i fakturaens eksisterende
kontrollfane. Den gjelder ordinære NOK-kundefakturaer og kreditnotaer med
komplett, entydig linjegrunnlag (1–200 linjer). Ingen ekstra meny eller
oppstartsoppgave innføres. Ingen avgiftskode, fradragsrett, sats eller
bokføring velges eller endres av kontrollen.

## Kilder og kategorier

[Skatteetaten forklarer skillet mellom fritak og unntak](https://www.skatteetaten.no/bedrift-og-organisasjon/avgifter/mva/slik-fungerer-mva/forskjellen-pa-fritak-og-unntak-fra-merverdiavgift/):
fritak har nullsats innenfor avgiftssystemet, mens unntatt omsetning står
utenfor. Bare et nullbeløp eller en nullsats avgjør ikke hvilken behandling
som gjelder. Registreringsforhold og vilkårene for den konkrete leveransen
må vurderes og dokumenteres separat.

Kontrollversjon 1 bruker modulens
[låste offisielle SAF-T-kodereferanse](https://github.com/Skatteetaten/saf-t/blob/05179521e435d82feb0b2d6c89a92a32a4f2d02f/Standard%20Tax%20Codes/CSV/Standard_Tax_Codes.csv).
Dette er kodekategorier, ikke automatiske vedtak om avgiftsbehandling:

| Eksplisitt koblet salgskode | Vist kategori | Avgrensning |
| --- | --- | --- |
| 5 | Innenlands fritak med nullsats | Faglig begrunnelse gjenstår. |
| 6 | Omsetning utenfor MVA-loven | Grunnlaget for unntak/annen behandling må dokumenteres. |
| 7 | Inntekt som ikke er MVA-omsetning | Må ikke forveksles med nullsats eller unntatt omsetning. |
| 51 | Innenlands omvendt avgiftsplikt | Separat scenario, ikke kvalifisert her. |
| 52 | Utførsel | Separat scenario, ikke kvalifisert her. |

## Bruk og oppfølging

Ved dagens fakturalinjer velger brukeren uttrykkelig en lagret
kodekoblingsversjon. Kontrollen bygger på samme daterte salgskobling som den
øvrige fakturakontrollen. Ved bevarte bokføringsobservasjoner brukes bare
linjene, identiteten og oppsettet som ligger i det verifiserte grunnlaget.
Dagens oppsett kan derfor ikke erstatte manglende historisk kobling.
Visningen er en ny kontroll på bevarte data, ikke en tilbakeført attest
på at denne kontrollen ble utført da bilaget ble bokført.

Hver relevant linje viser oppgitt kategori, kontrollresultat og neste handling:

- Manglende kode, datert salgskobling eller gyldig identitet gir uavklart behandling.
- Positiv salgskode sammen med nullsats, nullsats med ikke-null MVA-beløp eller
  nullsatskode med positiv sats gir konflikt. Små MVA-beløp rundes ikke bort.
- Et MVA-beløp på null ved positiv sats utleder ikke nullsats; dette kan skyldes
  beløpsgrunnlaget eller avrunding og hører til øvrig beløpskontroll.
- Manglende avgiftsopplysninger, lokalavgifter og særmarkeringer krever egen vurdering.
- Oppgitt kategori med konsistente tall krever fortsatt dokumentert faglig grunnlag.

Salg og kjøp holdes atskilt. Kjøpskoder og kode 0 godtas ikke som salgskategori.
Kreditnotaens negative grunnlag tillates; kontrollen bekrefter ikke dermed
rettingsløpet eller vilkårene for den opprinnelige leveransen.

## Historikk, tester og videre arbeid

Ingen payload, tax-context-versjon, lagringsformat eller skjema endres.
Eksisterende observasjoner leses gjennom sitt uendrede integritetsvern.
`NorwayPostingTaxContext` beholder sin opprinnelige betydning; den nye
kontrollen viser numeriske konflikter separat. Ingen tilbakefylling utføres.

`tests/sales-zero-vat.php` dekker kategoriene, motstridende beløp/koder,
nullbeløp ved positiv sats, kreditnotaer, dato/retning/identitet, manglende
opplysninger, linjedekning og uendret kilde/klassifisering.
`tests/sales-zero-vat-http.php` prøver balansert syntetisk native bokføring,
norsk/engelsk visning, autentisering, endret dagens kobling og uendrede
observasjonshasher.

Dette fullfører ikke A2. Begrunnelse og dokumentasjon for den konkrete
leveransen, registreringshistorikk, kjøp/manuelle bilag, støttede rettinger,
rapporteringsmapping og MVA-perioderapport må kvalifiseres videre. En
konsistent kategori er ikke faglig produksjonsaksept eller leveringsklart
MVA-grunnlag.
