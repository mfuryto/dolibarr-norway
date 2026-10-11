# Foretaksopplysninger: organisasjonsnummer, MVA og organisasjonsform

Fra modul 0.15.0 er norske etiketter tydeligere, og 14 norske organisasjonsformer legges til i Dolibarrs eksisterende register. Ingen organisasjonsform velges automatisk. Oppdateringen endrer ikke organisasjonsnummer, MVA-nummer, MVA-status eller eksisterende foretaksvalg.

## Hvor fylles opplysningene inn?

Åpne **Oppsett → Firma/organisasjon**, eller bruk lenken i norsk oppsettsveiviser, steg 2. Velg **Norge** som land og lagre først. De landavhengige feltene og alternativene vises da i **Foretaksidentifikasjon**.

| Felt | Hva som legges inn |
|---|---|
| Organisasjonsnummer | Foretakets ni sifre, uten NO og uten MVA. Feltet het tidligere Profesjonell ID 1. |
| MVA-nummer (ved registrering) | Separat MVA-identifikasjon når faktisk registrering er bekreftet. Feltet het MVA-ID. Dette erstatter ikke organisasjonsnummerfeltet. |
| Organisasjonsform | Velg registrert form, eksempelvis ENK — Enkeltpersonforetak eller AS — Aksjeselskap. Feltet het Forretningsenhetstype. |

MVA-innstillingen er et eget valg og registreringens virkningsdato må vurderes separat. Å fylle inn et nummer registrerer ikke foretaket og endrer ikke historiske avgiftsperioder. På salgsdokumenter skal MVA-merking bare brukes ved registrering. Se [Altinns foretaksinformasjon](https://info.altinn.no/starte-og-drive/drive-bedrift/juridiske-og-regulatoriske-krav/krav-til-informasjon-om-foretaket-pa-nettsider-og-forretningsdokumenter/) og [fakturakrav](https://info.altinn.no/starte-og-drive/regnskap-og-revisjon/regnskap/faktura-salgsdokumentasjon/).

Dette er forbedret oppsettveiledning, ikke ny MVA-registrering eller full kontroll av historisk MVA-status. Norsk selskapsform er heller ikke en automatisk avgiftsregel.

## Alternativer og bevaring

De medfølgende formene er ENK, AS, ASA, ANS, DA, SA, NUF, STI, FLI, IKS, KF, SF, KS og SE. Det er et avgrenset utvalg, ikke hele listen over norske registerenheter eller støtte for alle disse virksomhetenes regnskapsregler. Bruk [Brønnøysundregistrenes organisasjonsformer](https://data.brreg.no/enhetsregisteret/api/organisasjonsformer) ved vurdering av andre former. Forkortelser og beskrivelser er kontrollert mot denne offentlige kodelisten 2026-10-11. Ingen virksomhets- eller persondata er hentet.

Interne Dolibarr-koder 173001–173014 er faste prosjektkoder og skal ikke brukes som myndighetskoder. Navn og nummerrekkefølge ligger versjonert i NorwayCompanySetup::forms(). Registeret er globalt i Dolibarr; de nye alternativene gjelder Norge og kan derfor sees av andre foretak i samme installasjon. Ingen av foretakenes valgte form endres.

Eksisterende kode med samme land og navn beholdes. Inaktive alternativer forblir inaktive. En kode som allerede har et annet land eller navn, sperrer registertillegget med forklaring fremfor å overskrives. Administrator må gjennomgå konflikten; ikke slett eller endre brukte koder for å omgå den. Moduldeaktivering sletter ikke alternativer som kan være i bruk. Norske tekstoverstyringer avgrenses til aktivt foretak og nb_NO; organisasjonsnummeretiketten brukes bare for land NO, slik at utenlandske identifikasjonsfelter bevares.

## Verifikasjon

Syntetiske tester kontrollerer 14 alternativer, uendret gjentakelse, inaktivt valg, kodekonflikt og bevaring av foretaksinnstillinger. Autentisert HTTP-test kontrollerer de faktiske feltnavnene og ENK-/AS-alternativene på kjernens foretaksoppsett. Fersk installasjon og gjenoppretting kontrollerer at alternativene er tilgjengelige. Ingen nye tabeller eller skjemaendringer innføres.

## Automatisk MVA-nummer (fra 0.16.0)

Velg **MVA brukes** når foretaket faktisk er registrert i Merverdiavgiftsregisteret. For Norge, et gyldig organisasjonsnummer og tomt MVA-felt fyller modulen automatisk organisasjonsnummerets ni sifre med `MVA` etter ved ordinær lagring, eksempelvis `974760673MVA`. Du trenger ikke skrive nummeret på nytt eller bekrefte et ekstra valg.

Et eksisterende MVA-nummer beholdes. Andre land og foretak som ikke bruker MVA berøres ikke. Ugyldig organisasjonsnummer stopper lagringen når norsk MVA-nummer skal fylles automatisk. Feltet endres ikke ved visning eller moduloppdatering. Endret organisasjonsnummer synkroniserer ikke et eksisterende MVA-nummer automatisk; kontroller begge ved en slik endring.

Dette registrerer ikke foretaket hos myndighetene og vurderer ikke virkningsdato eller historisk MVA. MVA-nummeret bygger på samme organisasjonsnummer, jf. [Brønnøysundregistrenes forklaring](https://www.brreg.no/om-oss/registrene-vare/om-enhetsregisteret/organisasjonsnummeret/).

MVA-autoutfyllingen verifiseres også gjennom innlogging og faktisk lagring i kjernens formular: tomt felt fylles, eksisterende felt beholdes, ugyldig organisasjonsnummer avbryter, MVA av lar feltet være tomt og ugyldig CSRF-token lagrer ingen endring. Testen er sperret til en syntetisk engangsdatabase og gjenoppretter foretaksinnstillingene.
