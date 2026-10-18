# Norway 0.86.0

**Versjon 0.86.0 · Under utvikling**

Ekstern modul for Dolibarr 24.0.1 og PHP 8.2 eller nyere. Ett foretak og NOK.
Kjernen endres ikke. Modulen gir norsk kontoplan, foretaksoppsett, norske og
engelske dokumenter, valgfrie nummerserier og sperring av sjekkbetalinger.

Bruk kjernens fakturaer, tilbud, kjøpsjournal, hovedbok, perioder, bank og
produktkontoer. Oppstartsoversikten viser oppgave, status, forklaring og lenke.
Norsk regnskapsoppsett ligger under Regnskap → Innstillinger.

Fra 0.79.0 finnes MVA-fradrag (%) på den vanlige kjøpslinjen. Innenlandske
varer/tjenester med 25/15/12 prosent og utenlandske fjernleverbare tjenester
med 25 prosent beregnet avgift kan ha fullt, delvis eller ingen fradrag.
Fritekst fungerer uten katalogprodukt. Kjernens kjøpsjournal bokfører riktig
kostnad, fradrag og gjeld i ett bilag; native kreditnotaer kopierer valget.
Originalbeløp og tidligere manuelle rettinger bevares. Skjemamigrering 003
legger ett valgfritt native linjeekstrafelt til; ingen ny hovedboksmotor.

MVA-uttrekket avstemmer støttede koder og faktisk fradrag mot bokføringen.
Fra 0.80.0 finnes termingrunnlag for manuell levering i Skatteetatens portal,
historisk rapportering etter oppsettsendring og forslag til oppgjørsbilag.
Bankavstemming, oppgjørsbilag, kvittering, betaling og perioder bruker kjernen.
Ved manuell modulinstallasjon aktiveres også kjernens avgifts-/MVA-betalingsmodul
for oppgjør og vedlegg. Standard Compose-oppsett gjør dette automatisk.
Vareimport, særordninger, direkte innsending og faktisk EHF-levering gjenstår.
ENK-årsavslutning og portalgrunnlag for skatt har avgrenset støtte. Ingen versjon har samlet
regnskapsfaglig produksjonsaksept.

Utgåtte kontrollsider og særskilt kjøpsbokføring er fjernet. Eldre
registreringer beholdes i et skrivebeskyttet arkiv; allerede særposterte kjøp
sperres mot dobbel overføring. Modulen lager ingen nye kontrollkopier.

Se prosjektets README, docs/purchase-vat.md, docs/norwegian-setup-guide.md og
docs/delivery-plan.md. GPL-3.0-or-later; kontoplanens kilde og lisens følger dataene.

Fra 0.81.0 prøves en tilpasset åpen EHF-generator med native fakturaer og
offisielle formatregler. Sending kan bare aktiveres i separat testnettverk.
Faktisk nettverkslevering er ikke kvalifisert, og produksjonssending er sperret.

Fra **0.83.0** finnes SAF-T 1.40 på kjernens regnskapseksportside, med
kontokoblinger, MVA, reskontro, avstemte saldoer og komplett periodevalg.
Første omfang er NOK og kalenderårene **2025–2026**; øvrige år og ukjent
behandling avvises. [Arbeidsflyt, prøvebevis og avgrensning](https://github.com/mfuryto/dolibarr-norway/blob/main/docs/saft-export.md).

Fra **0.84.0** er [ENK-forskuddsskatt](https://github.com/mfuryto/dolibarr-norway/blob/main/docs/tax-accounts.md),
[årsavslutning](https://github.com/mfuryto/dolibarr-norway/blob/main/docs/year-end.md) og [portalveien for skattemelding med
næringsspesifikasjon](https://github.com/mfuryto/dolibarr-norway/blob/main/docs/enk-tax-return.md) dokumentert og årsgrunnlaget prøvd.
På Saldobalanse finnes **Årsgrunnlag for ENK**, med postkoder, beløp, kjente
korreksjoner, fradragsoversikt og CSV med konti/bilag. Kjernens bank, bilag,
rapporter og avslutning brukes. Et vern hindrer gjentatt
årsavslutning og saldooverføring uten reskontro. Omfanget er ENK uten regnskaps-/
revisjonsplikt; faktisk innlevering krever egen portalutfylling. Årsgrunnlaget støtter
2025–2026; endelig 2026-portalutfylling kontrolleres ved levering i 2027. Direkte innsending
og et automatisk norsk årsoppgjørssystem er ikke levert.

Fra **0.85.0** finnes papirløs bilagsflyt på vanlige kjøps-/bilagskort: originalvedlegg, forhåndsvisning, dokument-/hovedbokstatus, bevaring og samlet ZIP fra kjernens eksportside. Betaling og bankavstemming bruker kjernen. [Arbeidsflyt og omfang](https://github.com/mfuryto/dolibarr-norway/blob/main/docs/paperless-accounting.md).

Fra **0.86.0** finnes **Regnskap → Balanseoversikt**, med eiendeler, egenkapital og gjeld per valgt dato, udisponert resultat og lenker til kontobevegelser. Rapporten bruker kjernens kontosummer og viser uoverførte kilder og eventuell ubalanse. [Bruk og omfang](https://github.com/mfuryto/dolibarr-norway/blob/main/docs/balance-overview.md).
