# Journaler

Bruk **Regnskap → Innstillinger → Journaler**. Opprett nødvendige journaler
med en entydig kode, et forståelig navn og riktig type:

| Formål | Kjernens type | Eksempel på kode |
| --- | --- | --- |
| Salg | 2 | SALG |
| Kjøp | 3 | KJOP |
| Bank/kasse | 4 | BANK |
| Diverse bilag | 1 | DIV |
| Inngående saldo ved avslutning | 9 | IB |

Eksempelkodene er ikke myndighetskoder. Eksisterende egnede koder beholdes;
flere journaler av samme type er tillatt. Velg bankjournal på hvert bankkontokort.
Avslutningsjournal velges i kjernens avslutningsoppsett. Oppdatering oppretter
ikke nye journaler og avslutter ingen perioder.

Fra 0.77.0 er modulens separate opprettingsside fjernet. Kjernens skjema brukes
både før og etter at regnskapet er tatt i bruk. Tidligere journaler og bilag
bevares. Installasjonsprøven oppretter salg/kjøp/bank/diverse gjennom de faktiske
native HTTP-skjemaene; saldoovergangsprøven bruker type 9.
