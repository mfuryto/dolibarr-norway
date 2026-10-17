# Norway 0.81.0

**Versjon 0.81.0 · Under utvikling**

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
Vareimport, særordninger, direkte innsending, EHF/Peppol, norsk SAF-T og
årsoppgjør/skatt gjenstår. Ingen versjon har samlet
regnskapsfaglig produksjonsaksept.

Utgåtte kontrollsider og særskilt kjøpsbokføring er fjernet. Eldre
registreringer beholdes i et skrivebeskyttet arkiv; allerede særposterte kjøp
sperres mot dobbel overføring. Modulen lager ingen nye kontrollkopier.

Se prosjektets README, docs/purchase-vat.md, docs/norwegian-setup-guide.md og
docs/delivery-plan.md. GPL-3.0-or-later; kontoplanens kilde og lisens følger dataene.

Fra 0.81.0 prøves en tilpasset åpen EHF-generator med native fakturaer og
offisielle formatregler. Sending kan bare aktiveres i separat testnettverk.
Faktisk nettverkslevering er ikke kvalifisert, og produksjonssending er sperret.
