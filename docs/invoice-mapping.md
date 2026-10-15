> Historisk dokumentasjon til og med 0.64.0. Funksjonen er fjernet i 0.65.0.
> Se [gjeldende plan](delivery-plan.md). Eldre tilleggshistorikk beholdes i arkivet.

# Fakturalinjer mot valgt oppsettsversjon

Fra 0.31.0 har Norsk fakturakontroll en valgfri seksjon **Sammenlign med en
lagret oppsettsversjon**. Åpne seksjonen, velg et versjonsnummer med tidspunkt
og trykk **Sammenlign linjer**. Ingen versjon velges automatisk fra fakturadato.
Hvis ingen koblinger er lagret, vises dette uten å foreslå en kode.

Den valgte versjonen rekonstrueres fra [oppsettshistorikken](tax-mappings.md).
Senere rettinger og avslutninger tas ikke med. Hele den lagrede historikken
valideres likevel først; en ugyldig senere hendelse kan ikke skjules ved å velge
en tidligere versjon. Foretaksidentiteten og fakturaens foretak må stemme.

Sammenligningen bruker dagens lagrede fakturalinjer og fakturadato. Den prøver
kun salgskoblinger som var gjeldende i valgt oppsettsversjon, med eksakt lokal
kode og inklusivt datointervall. Kjøpskoblinger og avsluttede koblinger brukes
ikke. Manglende linjekode, ukjent dato og særmarkeringer vises særskilt. Et
mulig treff viser målkode, koblings-ID og den siste endringsversjonen for den
koblingen innenfor valgt oppsettsversjon. Beløp og fortegn endres ikke.

Dette er en kandidatsammenligning, ikke en beslutning om riktig avgiftsbehandling.
Den åpne fakturaen kan være et utkast eller en kreditnota. Valgt oppsettsversjon
sier ikke hvilken mapping som faktisk ble brukt ved opprinnelig bokføring.
Dagens fakturalinjer er heller ikke uforanderlige historiske avgiftsmetadata.
Ingen data, valg eller rapportresultater lagres, og ingen beløpssummer beregnes.

Hovedboken har dokument-/linjekoblinger, men ikke egne norske TaxCode/TaxBase-felt.
Sammenligningen oppretter derfor ingen hovedboksmetadata og bekrefter ikke
hovedboksavstemming. Bokført avgiftsgrunnlag, historiske rapportversjoner,
MVA-melding og SAF-T-eksport gjenstår. Ingen skjemaendring innføres.

Teknisk aksept: eksplisitt versjonsvalg, rekonstruksjon før/etter retting og
avslutning, kun salg, intervallets grenser, markeringer og manglende opplysninger,
ugyldig senere historikk, foretaksavgrensning og uendrede inngangsdata testes
syntetisk. Norsk/engelsk visning og bredde kontrolleres separat.
