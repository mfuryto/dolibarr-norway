> Historisk dokumentasjon til og med 0.64.0. Funksjonen er fjernet i 0.65.0.
> Se [gjeldende plan](delivery-plan.md). Eldre tilleggshistorikk beholdes i arkivet.

# Prøve en avgiftsmapping uten lagring

Fra 0.29.0 har Norsk fakturakontroll en egen forhåndsvisning under linjebeløpene.
Åpne dokumentet fra veiviserens steg 7 eller periodeoversikten. Angi eksakt lokal
kode, foreslått SAF-T-kode og fra/til-dato. Begge datoene inkluderes. Feltene er
bevisst uten forhåndsvalgt kode eller gyldighetsperiode.

Forhåndsvisningen gjelder bare det åpne dokumentet og bruker fakturadato, ikke
bokføringsdato, leveringsdato eller tidspunktet for betaling. Fakturaens status
og type beholdes: et utkast eller en kreditnota er ikke en godkjent bokføring.
Gyldig SAF-T-kode kontrolleres mot den faste [referanselisten](invoice-tax-evidence.md).
En lokal kode kan bestå av opptil 10 byte uten kontrolltegn, tilsvarende kjernens
begrensede kildekodefelt. Mellomrom normaliseres ikke.

Mulig treff krever at fakturadato ligger i intervallet, den lagrede linjekoden
er eksakt lik den valgte lokale koden, og særmarkeringen er kjent og lik null.
Andre koder, datoer utenfor intervallet, manglende dato/kode og særmarkeringer
vises særskilt. Kode `0` behandles som en faktisk kode. Manglende koder fylles
ikke inn. MVA-sats, beløp, kontonummer og kundeland brukes ikke til å velge kode.

Et mulig treff er ikke en regnskapsfaglig vurdering. Selv et manuelt valgt mål
kan være feil for salget, fradraget, tidspunktet eller den aktuelle rapporten.
Ingen SAF-T-eksport, rapporteringssummer eller MVA-melding beregnes.

## Lagring og historikk

Valget finnes kun i den aktuelle forespørselen. Det lagres ikke i databasen,
brukes ikke ved fakturavalidering og påvirker ingen tidligere eller fremtidige
bilag. Det er ikke versjonert mapping eller et revisjonsspor. Nettleseradresse
kan inneholde de valgte kodene og datoene; bruk ingen personopplysninger i felt.
Ingen skjemaendring eller migrering innføres.

Neste grunnlag må omfatte eksplisitt, datert lagring per foretak, kilde og
avgiftsretning, vern mot overlapp, retting med historikk og kobling til bokført
avgiftsmetadata. Denne forhåndsvisningen kvalifiserer ikke det arbeidet.

## Teknisk aksept

Støttet omfang er en skrivebeskyttet kandidatprøve på ett dokument, på norsk
og engelsk. Tester dekker intervallgrenser, eksakt kodematch, kode `0`, ugyldige
planer, manglende opplysninger, særmarkeringer og uendrede inngangsdata. HTTP og
bredde kontrolleres i syntetisk installasjon. Eksisterende administrator-,
modul-, leserettighets- og foretaksavgrensning på fakturakontrollen gjelder.
