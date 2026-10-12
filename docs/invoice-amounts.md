# Lesekontroll av fakturaens MVA-beløp

Fra 0.22.0 viser Norsk fakturakontroll lagret netto, MVA-sats og MVA-beløp per linje,
linjesummer og lagrede fakturatotaler. Åpne kontrollen fra oppsettsveiviserens steg 7
og velg en faktura. Bare aktivt foretaks fakturaer vises, for administrator med
leserettighet til fakturaer.

Kontrollen summerer de lagrede linjebeløpene uten ny beregning av sats, rabatt eller
avgiftsgrunnlag. Netto og MVA sammenlignes separat med fakturatotalene; hver total
har toleranse på ett øre. Fortegn beholdes, også for kreditnota. Manglende eller
ugyldige beløp/satser gir ufullstendig kontroll, ikke null eller godkjent resultat.
Tom faktura har ingen sammenligning. Nullsats eller null MVA-beløp varsles særskilt
for vurdering av begrunnelsen. Alle øvrige linjer krever også faglig vurdering.

Beløpene gjelder lagrede felt i foretakets grunnvaluta. Kontrollen omregner ikke
valuta og leser ikke alternative valutabeløp. Norsk omfang krever NOK. Brutto og
særavgifter inngår ikke i sammenligningen. Avvik må undersøkes sammen med blant
annet rabatter, avrunding og kjernens beløpsberegning.

Samsvar bekrefter bare at linjesummene samsvarer med totalfeltene innen toleransen.
Dette bekrefter ikke riktige satser, norsk avgiftsbehandling, registreringsperiode,
fradragsrett, bokføring, hovedboksavstemming eller MVA-melding. Ingen innstillinger,
linjer, dokumenter, valideringsregler eller bokførte beløp endres. Historisk
avgiftsmetadata, rapporteringsmapping og sporbar MVA-rapport gjenstår.

Fra 0.27.0 vises også [lagrede avgiftsopplysninger](invoice-tax-evidence.md), uten endring av beløpskontrollen.
