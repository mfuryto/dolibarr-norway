# Tilbud, faktura og kreditnota

NOK brukes som basis- og dokumentvaluta for nye salg. Norsk og engelsk velges
på dokumentet. Bruk kjernens kunder, varer/tjenester, fakturering og betalinger.
Sjekkbetalinger er deaktivert. Det finnes ingen separat fakturakontrollside
eller godkjenningshistorikk i modulen.

## Dokumentflyt fra 0.78.0

Bruk kjernens fakturakort og standardmodellen **sponge**, og **azur** for tilbud.
Velg dokumentets språk norsk eller engelsk, med NOK. Dokumentprøven dekker ENK,
AS og ASA, varer/tjenester, faktura, tilbud og kreditnota. For AS/ASA med den
norske organisasjonsformen valgt legges «Foretaksregisteret» ved organisasjonsnummeret.
Kjernens bunntekst viser organisasjonsformen og avsenderadressen. Velg faktisk
foretaksform i foretaksoppsettet; modulen utleder ikke form fra selskapsnavnet.
NUF med registreringsvilkår og andre særtilfeller inngår ikke i denne aksepten.

**Levering:** Bruk linjens fra-/til-dato for leveringsperiode, og beskriv
leveringssted i linjebeskrivelse eller offentlig merknad. Et fysisk leveringssted
som avviker fra fakturaadressen kan også angis med kjernens leveringskontakt.
Ikke anta at fakturadato eller kundens adresse alltid beskriver faktisk levering.
Kundens bestillingsreferanse registreres i kjernens kundereferansefelt.
Bruk synlige beskrivelser og standardvisningen i endelig PDF; skjulte detaljer
og andre dokumentmaler inngår ikke i prøven. Opplysningene må beskrive den
faktiske leveransen, ikke bare være teknisk utfylt.

**Kreditnota for faktura i denne installasjonen:** Opprett fra originalfakturaen.
Kjernens kobling og originalreferanse beholdes. En feil intern kildekobling
kan ikke overstyres ved å fylle en ekstern referanse.

**Kreditnota ved systembytte:** Bruk kjernens nye faktura → kreditnota uten
intern original. Fyll **Opprinnelig fakturanummer fra tidligere system** i samme
opprettingsskjema. Feltet vises bare for denne situasjonen. Det bruker kjernens
allerede eksisterende `ref_ext`, uten ny tabell eller kopi av bilaget.
Referansen kan rettes på kreditnotakortet mens det er en kladd; etter utstedelse
er den skrivebeskyttet. Den skrives tydelig på PDF-en på valgt språk.

Bevar den gamle originalfakturaen som dokumentasjon, velg samme kunde, og
kontroller beløp, faktisk avgiftsbehandling og opprinnelig registreringsstatus.
En referanse beviser ikke innholdet i et eksternt dokument. Kreditnota etter
avregistrering kan rette avgift fra en tidligere registrert periode, men må
ha dokumentert originalgrunnlag. Ikke opprett en ny salgsfaktura som erstatning
for en gammel original bare for å få en intern kobling; det kan føre salget dobbelt.

Kjernens `INVOICE_CREDIT_NOTE_STANDALONE` aktiveres bare dersom innstillingen
mangler. Et eksplisitt avvikende administratorvalg overstyres ikke. Ved et
bevisst deaktivert valg må administrator bruke kjernens oppsett før denne
systembytteflyten er tilgjengelig.

Kreditnotaen bruker vanlig K-serie når den norske nummereringen er valgt,
kjernens salgsjournal og betalings-/refusjonsfunksjoner. Den syntetiske prøven
viser kreditert grunnlag 100 og MVA 25: debet salg 100, debet utgående MVA 25,
kredit kunder 125, MVA-uttrekk med negativt grunnlag/avgift og refusjon -125.
Dette er ikke en automatisk kobling til en tidligere bankbetaling eller et
bevis på full MVA-innsending/avstemming.

### Primærkilder og kvalifisering

- [Altinn: foretaksopplysninger på forretningsdokumenter](https://info.altinn.no/starte-og-drive/drive-bedrift/juridiske-og-regulatoriske-krav/krav-til-informasjon-om-foretaket-pa-nettsider-og-forretningsdokumenter/)
  beskriver navn/organisasjonsnummer og AS/ASA-opplysninger, samt registerteksten.
- [Skatteetatens håndbok om salgsdokumentets innhold](https://www.skatteetaten.no/nn/rettskjelder/type/handboker/merverdiavgiftshandboken/gjeldende/M-15/M-15-10/M-15-10.6/)
  omtaler blant annet leveringstid/-sted og spesifisering av avgift.
- [Bokføringsforskriften kapittel 5-2](https://lovdata.no/dokument/SF/forskrift/2004-12-01-1558/KAPITTEL_5-2)
  og [Norsk RegnskapsStiftelse GBS 1](https://www.regnskapsstiftelsen.no/wp-content/uploads/2015/01/GBS-1-Utstedelse-av-kreditnota-oppdatert-01-12-14.pdf)
  beskriver kreditnota og referanse til opprinnelig salgsdokument.

Lest 2026-10-17. Dokumentprøven bruker faktiske native PDF-er, normal HTTP-
oppretting/redigering, hovedbok, MVA-uttrekk og refusjonsregistrering. Den dekker
også ugyldig token, andre foretak, leserrolle og vern av utstedt referanse.
Det er teknisk aksept for dette dokumentomfanget, ikke samlet regnskapsfaglig
produksjonsaksept eller en garanti for faktisk leverings-/avgiftsinformasjon.
Full kjøps-/fradragsflyt følger etappe 3 i leveranseplanen.
