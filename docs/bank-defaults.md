# Bekreftet bankkobling

Den eldre bankkoblingshjelperen finnes fra **Norsk regnskapsoppsett**.
Oppstartsoversikten lenker til bankkontoens vanlige oppsett som normalvei.

Opprett den faktiske bankkontoen i Dolibarr først. Den nye siden oppretter ingen bankkonto, saldo, betaling eller bilag. Den viser kontoens referanse/navn, eksisterende koblinger og forslag. Bankkontonummer, IBAN, eier og saldo vises ikke. Bekreft bare når forslagene passer virksomheten og du har en fersk backup.

## Støttet oppsett

- Nøyaktig én åpen ordinær bankkonto (`courant=1`) i NOK i aktivt foretak. Kasse, sparekonto, flere bankkonti og andre valutaer gjennomgås manuelt.
- Den medfølgende norske kontoplanen er valgt og aktiv, med aktiv konto 1920 og uendret kontonavn fra profilen.
- Et tomt journalvalg kan fylles bare når nøyaktig én aktiv bank-/kassejournal med kode og type 4 finnes. Journalens eksisterende kode beholdes; den behøver ikke hete BANK.
- Ingen bankbevegelser i foretakets konti, heller ikke nullbevegelse eller åpningssaldo; ingen hovedboksposter eller validerte kunde-/leverandørfakturaer. Oppsett som er tatt i bruk krever manuell gjennomgang.
- Eksisterende koblinger beholdes. Ugyldig/inaktiv eksisterende kobling eller delt bokføringskonto krever manuell gjennomgang. Et annet bankkontokort, også lukket, med kobling til 1920 sperrer forslag om å fylle 1920.

Bare tom bokføringskonto (tom eller -1) og tom journal (null/ikke positiv) fylles. Konto 1920 er et avgrenset forslag for dette oppsettet, ingen generell regel for alle bankkonti. Forslag som krever vurdering gir ingen skriveknapp. Ingen koblinger endres ved oppdatering eller sidevisning.

## Lagring og bevaring

Bekreftelsen er knyttet til bruker, aktivt foretak og forhåndsvisning og utløper etter ti minutter. CSRF-token og engangsbekreftelse kreves. Ved lagring låses og kontrolleres kontoplan, innstillinger, bankkonti, journaler og historikk på nytt. Endrede opplysninger avviser den gamle forhåndsvisningen. Begge koblinger lagres samlet, eller transaksjonen tilbakeføres.

En avgrenset oppdatering av bare de manglende koblingsfeltene bevarer øvrige bankfelt; kjernens BANKACCOUNT_MODIFY-trigger kjøres. Dette gjør ingen bankavstemming og setter ingen inngående saldo. Etterpå må opplysninger på faktura, betaling/bokføring og reskontroavstemming kontrolleres separat. Siden gir ingen samlet produksjonsaksept.

Syntetiske tester dekker faktisk lagring, alle andre bankfelt, eksisterende valg, gjentakelse, endret forhåndsvisning, foretak/rettigheter, kontoplan, journalvalg, deling og bankhistorikk. Installasjonstesten prøver innlogget HTTP-bekreftelse, lagrede koblinger, avvist gjentakelse og CSRF. Testkontoen slettes før backup-/gjenopprettingsprøven; ingen testdata sendes til drift.
