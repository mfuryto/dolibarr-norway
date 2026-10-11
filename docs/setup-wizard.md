# Norsk oppsettsveiviser

Åpne Norway-moduloppsettet → **Norsk oppsettsveiviser**. Administrator med aktiv
Norway-modul kreves. Veiviseren bruker aktivt foretak og gjør ingen eksterne oppslag
ved åpning.

## Slik bruker du den

Elleve steg følger [samlet norsk oppsettveiledning](norwegian-setup-guide.md).
Hvert steg viser en kort innledning, nummererte oppgaver og «Før du går videre».
Knapper åpner innstillingssiden i en ny fane: gjør og lagre endringene der, og gå
tilbake til veiviseren. Manuelle oppgaver uten knapp avklares utenfor systemet.

Lagrede innstillinger ligger under «Vis dagens innstillinger (kun lesing)».
Dette er ikke utfyllingsfelt. Tilleggskontroller og avanserte innstillinger ligger
separat med forklaring per lenke. Begrensninger har en egen seksjon.

Fra 0.24.0 kan du velge **Din arbeidsstatus** nederst i hvert steg og trykke
**Lagre arbeidsstatus**:

- Ikke startet
- Pågår
- Avventer avklaring
- Gjennomgått av administrator
- Ikke relevant

Fra 0.25.0 åpner veiviseren et foreslått steg når du åpner den uten å velge steg. Steg som krever ny gjennomgang etter endret organisasjonsnummer prioriteres først. Ellers foreslås sist lagrede steg som pågår eller avventer avklaring, deretter første steg som ikke er startet. Hvis alle er gjennomgått eller ikke relevante, åpnes siste gjennomgangsside med tydelig beskjed om at dette ikke er en godkjenning. Et eksplisitt valgt steg beholdes alltid. Navigasjonen lagrer eller endrer ingen markeringer.

Åpne «Vis alle elleve steg og arbeidsstatus» for å finne frem igjen. Status vises
også øverst i det aktuelle steget, bevares etter ny innlogging og deles mellom
administratorer i samme foretak. Forrige/neste bytter side uten å lagre status.
Alle steg kan fortsatt åpnes uansett arbeidsstatus.

«Gjennomgått» er administratorens egen, redigerbare markering. Det er ingen
ferdigknapp, samlet grønn status eller produksjonsgodkjenning. Ingen innstillinger,
bilag eller kontrollresultater endres. Begrunnelser, kontrollbevis og faglig
ansvar må fortsatt dokumenteres separat. MVA, EHF og SAF-T som ikke er levert,
blir ikke ferdige ved å markere et steg som gjennomgått eller ikke relevant.

## Lagring og avgrensning

Ett versjonert JSON-objekt per steg lagres i kjernens eksisterende konstanttabell,
kun i aktivt foretak. Det inneholder steg, arbeidsstatus, organisasjonsnummer,
bruker-ID og UTC-tid for siste lagring. Ingen fritekst eller dokumentvedlegg lagres.
Det er ingen nye tabeller eller skjemaendringer. Vanlig databasebackup inkluderer
markeringene. Dette er siste arbeidsstatus, ikke et uforanderlig revisjonsspor.

Hvis organisasjonsnummeret endres, vises «Må gjennomgås på nytt». Gammel status
blir ikke tolket som gjeldende for den nye identiteten. Ny gjennomgang og eksplisitt
lagring knytter markeringen til den aktuelle identiteten. Andre oppsettsendringer
oppdages ikke automatisk; vurder selv om arbeidsstatus må endres.

Lagring krever administrator, POST, CSRF-token og en engangsbekreftelse bundet til
steg, foretak, bruker og identitet. Endret status etter sideåpning avvises under
transaksjonslås. Last siden på nytt hvis en annen fane eller administrator har
endret den. Lesefeil gir synlig feil og sperrer statuslagring, mens oppgavene
fortsatt kan leses. Markeringer arves ikke fra andre foretak eller globale valg.

## Oppsettsider og kontroller

- Steg 2: [foretaksfelt og automatisk tomt MVA-nummer](company-setup.md).
- Steg 3: [første MVA-virkningsdato](vat-registration.md) og [MVA-kontokontroll](vat-setup.md).
- Steg 4: [norsk kontoplan](norwegian-chart.md), [kunde-/leverandørstandard](default-accounts.md), øvrige standardkontoer og [journaler](journal-setup.md).
- Steg 5: [bekreftet bankkobling](bank-defaults.md) i avgrenset nytt NOK-oppsett.
- Steg 7: [dokumentoppsett](document-setup.md) og [fakturaens MVA-beløp](invoice-amounts.md).
- Steg 9: [periodediagnostikk](period-setup.md) og separat bilagskontroll.

## Verifikasjon

Autentisert HTTP-test åpner alle elleve steg på norsk og engelsk, prøver lagring,
gjenåpning, nonce-gjenbruk og CSRF-avvisning. Databasetester dekker statusvalg,
foretak/steg, administratortilgang, endret identitet, ugyldige data og gamle
lagringsforsøk. Backup-/gjenopprettingsprøven kontrollerer en syntetisk arbeidsstatus.
Nettleserprøven inkluderer alle steg med åpne og lukkede detaljer ved fire
skjermbredder. Testene er ingen regnskapsfaglig aksept.
