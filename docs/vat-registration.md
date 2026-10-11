# MVA-registreringens virkningsdato

Fra modul 0.21.0: åpne **Norsk oppsettsveiviser → steg 3 → MVA-registreringens virkningsdato**. Oppgi dato fra bekreftet registreringsgrunnlag, forhåndsvis og bekreft organisasjonsnummer/dato. Datoen kan ikke utledes fra dagens Brønnøysund-oppslag eller første fakturadato.

Første versjon lagrer én eksplisitt virkningsdato for et norsk organisasjonsnummer i aktivt foretak. Registrert dato, organisasjonsnummer, intern bruker-ID og UTC-tid for bekreftelsen lagres som versjonert JSON i Dolibarrs eksisterende konstantsystem. Ingen nye tabeller eller skjemaendringer. Personlige vedlegg eller vedtaksdokumenter lastes ikke opp her; oppbevar grunnlaget etter virksomhetens dokumentasjonsrutiner.

Bekreftelsen gjelder forhåndsvisningen i ti minutter og er knyttet til bruker/foretak. CSRF og engangsbekreftelse kreves. Foretaksopplysninger og tidligere lagring kontrolleres på nytt under transaksjonslås. Ugyldig dato, endret organisasjonsnummer, annet land/foretak, eksisterende dato eller endret forhåndsvisning avviser lagringen. Dato lagres ikke ved installasjon, oppdatering eller sidevisning.

## Bruk og avgrensning

Siden sammenligner en valgt dato med den lagrede virkningsdatoen. Norsk fakturakontroll viser samme sammenligning for fakturaens dato. Før datoen gir varsel om nødvendig gjennomgang; på/etter datoen gir ingen avgiftsgodkjenning. Dette er ikke en automatisk valideringssperre. Kreditnota, etterfakturering og korreksjoner må vurderes mot kilden og registreringsgrunnlaget.

Registreringsvedtak/-tidspunkt og virkningsdato er ikke alltid samme forhold. Det kan være avgiftspliktig omsetning før avgift kan anføres på faktura, med behov for etterfakturering. Se [Skatteetatens omtale av fakturering og etterfakturering ved registrering](https://www.skatteetaten.no/rettskilder/type/vedtak/skatteklagenemnda/sporsmal-om-manglende-innberetning-av-merverdiavgift-av-hele-belopet-pa-faktura-som-overstiger-belopsgrensen-for-registrering-i-merverdiavgiftsregisteret/) og [registreringsveiledningen](https://www.skatteetaten.no/bedrift-og-organisasjon/avgifter/mva/registrere-endre-slette/).

Lagringen endrer ikke «MVA brukes», MVA-nummer, registrering hos myndighetene, historiske bilag eller satser. Eksisterende fakturavalidering beholdes. Manglende dato vises som udokumentert. Endret organisasjonsnummer eller ugyldig lagret data gir varsel fremfor å bruke datoen som grunnlag.

Tidligere lagret dato kan ikke erstattes/slettes fra denne siden. Feil dato, avregistrering, ny registrering og flere historiske perioder krever særskilt gjennomgang; en full modell med historikk/retting gjenstår. Ikke overskriv konstanten manuelt for å skjule avvik. Denne avgrensede første registreringen kvalifiserer ikke fradrag, utenlandshandel, MVA-melding eller avgiftsperioder.

## Verifikasjon

Syntetiske tester dekker første lagring, dato-/skuddårsgrenser, identitet, foretak/rettigheter, endret forhåndsvisning, overskrivingssperre, ugyldig data og bevaring av MVA-status/nummer. Installasjonstesten bekrefter gjennom HTTP, avviser gjentakelse/ugyldig CSRF og kontrollerer at den lagrede datoen bevares ved oppdatering og backup/gjenoppretting. Ingen testdata legges inn i driftsinstallasjonen.
