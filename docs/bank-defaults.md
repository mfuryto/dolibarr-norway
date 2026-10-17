# Bankkontoens regnskapskoblinger

Åpne **Bank → Bankkontolisten → aktuell konto → Rediger**. Fyll inn faktiske
betalingsopplysninger og velg NOK, bokføringskonto og en aktiv bankjournal
(type 4). Konto 1920 er et vanlig valg for hovedbanken i den medfølgende planen.
Andre faktiske bankkonti må få passende separate bokføringskontoer.

Fra 0.77.0 er den separate bankkoblingshjelperen fjernet. Kjernens bankkort
håndterer flere bankkonti og egne kontonavn, også i et brukt regnskap. Eksisterende
kontoer, koblinger og betalinger bevares ved moduloppdatering. Endrer du kobling
etter bokføring, må gamle og nye perioder kunne avstemmes.

Dette er oppsett av koblinger. Betaling, bankavstemming, saldoimport og
bankintegrasjon er separate oppgaver. Den syntetiske installasjonsprøven
oppretter to bankkonti gjennom kjernens skjema og redigerer en konto etter
historikk, uten å endre eksisterende hovedbokspost.
