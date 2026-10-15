> Historisk dokumentasjon til og med 0.64.0. Funksjonen er fjernet i 0.65.0.
> Se [gjeldende plan](delivery-plan.md). Eldre tilleggshistorikk beholdes i arkivet.

# Lagrede koblinger av avgiftskoder

Fra 0.30.0 åpnes siden **Lagrede koblinger av avgiftskoder** fra Norsk
fakturakontroll. Den er et frivillig oppsettsverktøy for administrator. Fra 0.45.0 bevares en teknisk kobling fra dette oppsettet ved støttede nye
salgsposteringer, og fra 0.49.0 også daterte kjøpskoblinger ved støttede
kjøpsposteringer; se [kildeobservasjoner](posting-observations.md). Koblingene
velger ikke avgiftsbehandling ved fakturavalidering eller rapportering. De er
ikke en MVA-melding, SAF-T-eksport eller godkjenning av avgiftsbehandling.

## Opprette, rette og avslutte

1. Kontroller at aktivt foretak har norsk landkode og gyldig organisasjonsnummer.
2. Velg **Ny kobling**. Fyll inn eksakt lokal kode, SAF-T-kode, fra/til-dato og
   avgiftsretning **Salg** eller **Kjøp**. Begge datoene inkluderes. Mål finnes i
   den [faste offisielle kodereferansen](invoice-tax-evidence.md); dette alene
   bekrefter ikke at koden passer til transaksjonen.
3. Velg **Forhåndsvis endring**, gjennomgå opplysningene, bekreft og lagre.
   Først bekreftelsen lagrer en ny historikkversjon.
4. Ved retting velger du **Rett eller avslutt kobling** på riktig ID. Lagre den
   korrigerte versjonen på samme måte. Forrige versjon beholdes og vises i
   forhåndsvisningen.
5. For avslutning velger du samme lenke, lar alle andre felt stå uendret og
   markerer **Avslutt denne koblingen**. Avsluttet ID kan ikke åpnes på nytt;
   en eventuell senere kobling opprettes med ny ID.

Siste versjon vises per ID. **Alle lagrede versjoner** viser tidligere felt,
versjonsnummer, tidspunkt i UTC og bruker-ID. Retting erstatter siste
oppsettsversjon; tidligere versjoner slettes ikke gjennom denne siden.

## Avgrensning og vern

Oppsettet bindes til aktivt foretaks ID og organisasjonsnummer. Endret eller
manglende foretaksidentitet sperrer lesing/lagring fremfor å overføre koblinger
automatisk. Det er ingen global reservekonfigurasjon fra foretak 0.

Intervaller kan ikke overlappe for samme eksakte lokale kode og avgiftsretning
blant de siste, ikke-avsluttede versjonene. En delt grensedato er overlapp;
neste intervall må begynne dagen etter. Salg og kjøp holdes atskilt. Ingen
avgiftsretning eller mål utledes fra sats, konto, beløp eller kundeland.

Siden krever administrator og aktiv modul, CSRF-token og en engangsbekreftelse
bundet til bruker/foretak med ti minutters levetid. Bekreftelsen bruker lagrede
forhåndsvisningsdata, ikke nye felt fra bekreftelsesforespørselen. Lagring låser
eksisterende foretaksidentitet og konfigurasjon, avviser foreldet forhåndsvisning
og kontrollerer at hele historikken fortsatt er gyldig. Skrivefeil tilbakeføres
atomisk, også innenfor en omsluttende transaksjon.

## Lagringsformat og videre arbeid

Format v1 ligger som ett JSON-oppsett i kjernens eksisterende `const`-tabell,
under `NORWAY_TAX_MAPPINGS_V1` for aktivt foretak. Ingen tabeller eller
skjemaendringer innføres. Historikken beholder referanselistens faste kilde,
foretaksidentitet og alle endringsversjoner. Grensen er 100 versjoner og 60 000
byte per foretak. Ved kapasitetsgrense, ugyldig historikk eller duplikate
oppsettsrader sperres endringer; historikk beskjæres aldri automatisk.

Dette er versjonert **oppsett**, ikke uforanderlige avgiftsmetadata på bokførte
bilag. En databaseadministrator kan endre konfigurasjonen direkte; det er ikke
et manipulasjonssikkert revisjonsspor. Ingen rapport velger gamle eller nye
oppsettsversjoner automatisk. Videre bruk trenger eksplisitt kobling til
bokført avgiftsgrunnlag, historisk rapportversjon og rettingsregler. Fremtidig
endring av format/kodereferanse må håndtere eksisterende historikk eksplisitt.

Teknisk aksept dekker lagring, retting, avslutning, overlappsvern, retning,
foretaks-/administratorvern, foreldede forhåndsvisninger, feiltilbakeføring og
kapasitetsvern. Installasjonstesten oppretter og retter syntetisk oppsett via
HTTP, avviser gjentatt bekreftelse og ugyldig CSRF-token, og kontrollerer at begge
versjonene bevares gjennom oppdatering og full backup/gjenoppretting. Norsk og
engelsk visning og sidebredde kontrolleres separat. Dette kvalifiserer ikke
regnskapsfaglig produksjon eller rapportering.
