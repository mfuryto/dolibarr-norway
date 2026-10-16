# Dekningsanalyse etter forenkling

Kontrollert 2026-10-16. Modul 0.65.0, støttet Dolibarr 24.0.1 ved
[kjernerevisjon b795838](https://github.com/Dolibarr/dolibarr/tree/b7958385f00a92219f76a4dfea67dae93df97a25).
Dette er en kildegjennomgang og beslutning om neste arbeid. Tredjepartstillegg
nedenfor er ikke installert eller funksjonstestet. Ingen norsk produksjonsaksept
følger av gjennomgangen. Nye leveranser følger [arbeidsplanen](delivery-plan.md).
Tidligere forslag om egne kontrollkopier og historikkmodeller videreføres ikke.

## Beslutninger

| Område | Hva som faktisk er funnet | Beslutning og gjenstående gap |
| --- | --- | --- |
| Ordinær regnskapsflyt | Kjernen har faktura, kreditnota, kjøpsjournal, hovedbok, reskontro, bank og perioder. Salgs-/kjøpsflyt, kreditnotaer og vern mot dobbeltføring inngår i 0.65-testene. | Behold kjernens arbeidsflyt. Dette kvalifiserer ikke alle avgiftsscenarioer eller årsavslutningen. |
| Norsk MVA-melding | Kjernen har faktura-/betalingsbaserte MVA-uttrekk, men ingen norsk melding eller innsendingsadapter ble identifisert i undersøkte kataloger. | Prøv uttrekk mot bokføringen før en avgrenset norsk rapporteringsutvidelse velges. En oversikt over MVA-satser eller saldo på MVA-kontoer er ikke komplett meldingsgrunnlag. |
| Norsk SAF-T | Kjernen har hovedbokseksport. Ingen norsk SAF-T-generator ble funnet i regnskaps-, MVA- eller modulkatalogene. | Norsk eksport er fortsatt et gap. Gjenbruk hovedbok og kildekoblinger; velg eksportutvidelse før egen generator. Ingen ny regnskapsmotor. |
| EHF/Peppol | Det finnes eksterne Peppol-moduler med dokumentgenerering og sending. | Prøv eksisterende løsning før egen integrasjon. Norsk profil, aktuelle PHP-/Dolibarr-versjoner, tilgangspunkt, lisens og kostnader må kvalifiseres. |
| Næringsspesifikasjon og skattemelding | Kjernen gir regnskapsdata, men ingen norsk årsoppgjørs-/skattemeldingsleveranse ble identifisert i gjennomgangen. Skatteetaten publiserer modeller og integrasjonsdokumentasjon. | Egen leveranse: årsversjon og foretaksform, kontokoblinger, skattemessige forskjeller, avstemming og konkret leveringsvei. Ikke anta at SAF-T eller en kontoplan utfører skatteberegningen. |

## MVA: konkret kjernedekning

I fast kjerneversjon bruker
[`compta/tva/index.php`](https://github.com/Dolibarr/dolibarr/blob/b7958385f00a92219f76a4dfea67dae93df97a25/htdocs/compta/tva/index.php)
`tax_by_rate()` for salg og kjøp. Funksjonen i
[`core/lib/tax.lib.php`](https://github.com/Dolibarr/dolibarr/blob/b7958385f00a92219f76a4dfea67dae93df97a25/htdocs/core/lib/tax.lib.php#L669)
leser fakturaer og linjer, med separate produkt-/tjenestegrener og valg av
faktura- eller betalingsgrunnlag. Den beholder lokale `vat_src_code` i
satsgrupperingen. Det gir gjenbrukbare kildeopplysninger; det skal ikke
feilaktig beskrives som om kjernen bare kjenner satsen.

Funksjonen leser også reiseregninger, men ikke `accounting_bookkeeping`.
Et manuelt bilag i hovedboken blir derfor ikke i seg selv med i denne
funksjonens MVA-uttrekk. Utslaget på en konkret norsk rapport og riktig
håndtering av datoer og fradrag må prøves med syntetiske data. Det er ikke
gjort en ny ende-til-ende-kvalifisering av MVA-meldingen i denne leveransen.

[Skatteetatens meldingsmodell](https://skatteetaten.github.io/mva-meldingen/mvameldingen/informasjonsmodell/)
bruker norske MVA-koder og egne spesifikasjoner, med intern kode som mulig
referanse. Den skiller hvilke felt som brukes for inngående og utgående
avgift. Lokal kode og sats må derfor kvalifiseres mot aktuell behandling.
[Innsendings-API-et](https://skatteetaten.github.io/mva-meldingen/documentation/api/)
har validering, innsending og tilbakemelding. En XML-fil alene er ikke
bekreftet innsending. Gjeldende tilgang og API-versjon må verifiseres før bruk.

## Resultat fra kjernens MVA-prøve

`tests/core-vat-report.php` kjøres etter ordinær salgs- og kjøpsflyt i den
isolerte integrasjonen. Fem fakturaer/kreditnotaer er først opprettet,
validert og overført gjennom kjernens journaler av `invoice-workflow.php`
og `core-purchases.php`. Testen prøver deretter vare-/tjenestegrenene,
koder og datoer som midlertidige varianter av disse syntetiske kildene.
Den er en karakterisering av kjernen, ikke en ny rapporteringsfunksjon.

| Prøve | Resultat i Dolibarr 24.0.1 |
| --- | --- |
| Fakturagrunnlag, varer og tjenester, uten betaling | Salg netto 1800/MVA 450 og kjøp netto 80/MVA 20, inkludert kreditnotaer, stemmer med de aktuelle hovedbokspostene. |
| Samme sats med to lokale koder | Kodene holdes i separate grupper; faktura og kreditnota med samme kode samles med riktig fortegn. |
| Fakturadato og betaling | Fakturagrunnlaget ligger i februar selv om betaling skjer senere. Betaling endrer ikke denne prøvens fakturabaserte avgift. |
| Betalingsgrunnlag | Ubetalte fakturaer utelates. Native halve betalinger i februar/mars gir 125 i salgsmva og 12,50 i kjøpsmva per måned for den betalte fakturaen. Dette prøver kjernens oppførsel, ikke at betalingsgrunnlag er riktig norsk valg. |
| Manuelt balansert MVA-bilag | +7 på inngående MVA vises i hovedboken, men fakturauttrekket forblir 20. |
| Separat balansert ompostering | -25 på inngående MVA endrer hovedboken ytterligere, men fakturauttrekket forblir 20. Netto inngående MVA i de valgte bilagene er nå 2. En ompostering blir ikke automatisk en oppdatert fradragsvurdering i fakturauttrekket. |
| Bevaring | Betalinger, bilag, kilder og datovarianter rulles tilbake. Antall rader og hash av de opprinnelige faktura-/linje-/hovedboksdataene kontrolleres etterpå. Ingen nye kontrollkopier opprettes. |

Kjernens dashboard beregner betalingsandel fra uttrekkets linjer. Testen
anvender samme formel ved prøven av betalingsgrunnlag; den er ikke en
nettlesertest av dashboardet. Kjerneversjonen gir også et kjent PHP-varsel
om manglende `datep` i tjeneste-/fakturagrenen. Bare dette eksakte varselet
registreres særskilt i prøven; øvrige feil undertrykkes ikke, og kjernen endres ikke.

**Beslutning:** gjenbruk fakturaenes kode, sats, beløp og dokumentkobling,
og kjernens hovedbok som avstemmingsgrunnlag. Kjernens fakturauttrekk kan ikke
alene være norsk MVA-melding når manuelle avgiftsbilag eller omposteringer
inngår. Ingen ny historikkmodell er besluttet av den grunn. Før en minste
rapporteringsutvidelse velges, prøves kjernens konkrete fradragsbehandling,
blandede satser, nullsatskategorier og aktuelle utenlandstransaksjoner.

25 prosent og de beskrevne syntetiske tilfellene er prøvd her. Delvis/intet
fradrag, manuelle bilags norske avgiftskoder/grunnlag, registreringsovergang,
periodisering på grensedatoer, import/eksport og komplett norsk melding er
fortsatt uavklart. En regnskapsmessig ompostering er ikke i seg selv en
faglig godkjenning av fradragsrett.

## SAF-T: kjerneeksport og kandidater

[`BookKeeping::export_bookkeeping()`](https://github.com/Dolibarr/dolibarr/blob/b7958385f00a92219f76a4dfea67dae93df97a25/htdocs/accountancy/class/bookkeeping.class.php#L2182)
henter konto, beløp, bilags-/dokumentreferanser, journal og valideringsdato,
men ikke et ferdig norsk avgiftsgrunnlag og norske avgiftskoder.
[Regnskapsmodulens eksportoversikt](https://wiki.dolibarr.org/index.php/Module_Accounting_Advanced)
er et startpunkt for gjenbruk; generell TSV/FEC-eksport er ikke norsk SAF-T.

[Offisiell SAF-T-dokumentasjon](https://www.skatteetaten.no/en/business-and-organisation/start-and-run/rutiner-regnskap-og-kassasystem/saf-t-financial/documentation/)
angir 1.40 som format fra 2027. Det tidligere undersøkte
[1.40-skjemaet ved fast revisjon](https://github.com/Skatteetaten/saf-t/blob/05179521e435d82feb0b2d6c89a92a32a4f2d02f/SAF-T_Financial_1.4/Norwegian_SAF-T_Financial_Schema_v_1.40.xsd)
har kontoenes `GroupingCategory`/`GroupingCode` og avgiftsklassifisering.
Kontokoblinger til dette formatet må holdes atskilt fra en komplett
skattemelding med næringsspesifikasjon.

[Dolibarr SAF-T Portugal](https://github.com/cialinux/dolibarr-saft/tree/91dc9aed1a7783b18ac4d14dd5289e1558befc93)
beskriver portugisisk validering/import via en kommersiell API-tjeneste.
Den er ikke dokumentert som norsk Financial-eksport og velges ikke som løsning.
Det er ikke kopiert kode. Avgrensede søk på GitHub/DoliStore fant ingen
bekreftet norsk eksportmodul; dette beviser ikke at ingen eksisterer.

## EHF: gjenbruk først

[CAP-REL Peppol](https://doc.cap-rel.fr/peppol/) beskriver BIS Billing 3.0,
generering, sending/mottak, status og flere tilgangspunkt. Dokumentasjonen
krever en tilgangspunktavtale. DoliStore-søket viste nyere opplysninger enn
den åpnete produktsiden; eksakt versjon, PHP-støtte, pris og norsk dekning
regnes derfor som uavklart. Tillegget er en kandidat, ikke en valgt avhengighet.

To åpne kandidater er også funnet:

- [marcrant1/dolibarr-peppol-export](https://github.com/marcrant1/dolibarr-peppol-export/tree/c8d965b1cfc4080880e4a9b51439e4f1a39eceff): README beskriver UBL, kreditnota og Peppyrus, testet på eldre Dolibarr-versjoner. Lisensfil og faktisk 24.0.1-kompatibilitet må undersøkes.
- [cedric-marcoux/dolibarr_peppol-peppyrus](https://github.com/cedric-marcoux/dolibarr_peppol-peppyrus/tree/cede93ae75730d56cbb472e3e2cf6f896b9d54d9): GPL-3.0-repo som beskriver XML, sending og status via Peppyrus. Norske identifikatorer, kreditnotaer og støttet versjon er ikke kvalifisert her.

Ikke legg README-påstander om gratis tilgang til grunn for en driftsavtale.
Ingen konto, kjøp, abonnement, mottakerregistrering eller sending er utført.
[DFØs EHF-veiledning](https://www.anskaffelser.no/verktoy/veiledere/ehf-fakturering)
er formatreferansen for norsk faktura/kreditnota. Før valg må XML og norsk
profil valideres separat fra transportprøven med et tilgangspunkt.

## Næringsspesifikasjon og skattemelding

[Skatteetaten om ENK](https://www.skatteetaten.no/bedrift-og-organisasjon/skatt/skattemelding-naringsdrivende/enk/)
skiller levering via nettsiden fra levering gjennom regnskaps-/årsoppgjørssystem;
ENK med regnskaps- eller revisjonsplikt må bruke system. Installasjonen må
avklare hvilken leveringsvei som gjelder. Det er ikke ett generelt krav om
at denne modulen selv skal være innsendingssystem.

[Skatteetatens leverandørrepo](https://github.com/Skatteetaten/skattemeldingen)
har modeller, kodelister, test- og API-dokumentasjon. README har historiske
inntektsårseksempler; riktig inntektsårs modell må velges før implementasjon.
API-tilgang må bestilles og vilkår avklares ved eventuell direkte integrasjon.

Leveransen skal omfatte kontokoblinger, relevante skattemessige forskjeller
og avskrivninger, tilleggsopplysninger og avstemming mot årsavslutningen.
Velg en konkret overføring til egnet årsoppgjørsløsning eller integrasjon.
En summering av regnskapskontoene eller SAF-T-eksport er ikke tilstrekkelig
bevis på at hele næringsspesifikasjonen er ferdig.

## Avgrensning

Ingen ny rapportmotor, kontrollside, databaseendring eller integrasjon er
implementert i denne gjennomgangen. Foretaksformer og bransjer får bare
støtte etter konkrete prøver. Lønn, kassasystem og andre betingede områder
utvider ikke automatisk leveransen. Papirløs flyt tas fortsatt til slutt.
