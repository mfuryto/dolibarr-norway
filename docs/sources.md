# Kilderegister

Kontrollert 2026-10-11. Nettsider er bevegelige; bruk fast kildecommit/skjemaversjon
ved reproduksjon. Ingen tidligere undersøkelse alene regnes som bekreftelse.

| ID | Primærkilde | Kontrollert versjon / bruk |
|---|---|---|
| S1 | [Dolibarr-utgivelser](https://github.com/Dolibarr/dolibarr/releases/tag/24.0.1) og [API](https://api.github.com/repos/Dolibarr/dolibarr/releases/latest) | 24.0.1, publisert 2026-09-07; stabilt versjonsvalg |
| S2 | [Fast Dolibarr-kilde](https://github.com/Dolibarr/dolibarr/tree/b7958385f00a92219f76a4dfea67dae93df97a25) | Tag 24.0.1; SQL, klasser, regnskapseksport og modulmal undersøkt lokalt |
| S3 | [Modulutvikling](https://wiki.dolibarr.org/index.php/Module_development) | Ekstern modul og modulbeskriver; kontrollert mot malen i S2 |
| S4 | [Advanced Accounting](https://wiki.dolibarr.org/index.php/Module_Accounting_Advanced) | Standard funksjonsoversikt; oppførsel må testes i 24.0.1 |
| S5 | [Skatteetatens SAF-T-dokumentasjon](https://www.skatteetaten.no/bedrift-og-organisasjon/starte-og-drive/rutiner-regnskap-og-kassasystem/saf-t-regnskap/dokumentasjon/) | 1.40, dokumenter oppdatert 2026-08-31; krav fra 2027 |
| S6 | [Offisielt skjema-/kilderepo](https://github.com/Skatteetaten/saf-t/tree/05179521e435d82feb0b2d6c89a92a32a4f2d02f) | `SAF-T_Financial_1.4/Norwegian_SAF-T_Financial_Schema_v_1.40.xsd` og kodelister; [generell dokumentasjon 1.8](https://www.skatteetaten.no/globalassets/bedrift-og-organisasjon/starte-og-drive/rutiner-regnskap-og-kassasystem/saf-t-regnskap/august2026/norwegian-saf-t-financial-data---documentation-1.40.pdf), [teknisk beskrivelse](https://www.skatteetaten.no/globalassets/bedrift-og-organisasjon/starte-og-drive/rutiner-regnskap-og-kassasystem/saf-t-regnskap/august2026/norwegian-saf-t-financial-data---technical-description-1.4.pdf) |
| S7 | [Altinn: faktura](https://info.altinn.no/starte-og-drive/regnskap-og-revisjon/regnskap/faktura-salgsdokumentasjon/) | Oppdatert 2026-03-18; faktura, kreditnota og EHF |
| S8 | [Skatteetaten: satser](https://www.skatteetaten.no/nn/tabellar-og-satsar/merverdiavgift/) og [Altinn: MVA](https://info.altinn.no/starte-og-drive/skatt-og-avgift/avgift/merverdiavgift) | Kontrollerte aktuelle satskategorier; registrering og forhåndsregistrering. ENK-status ikke utledet av disse kildene |
| S9 | [DoliStore: SAF-T PT](https://www.dolistore.com/product.php?id=1981&l=en) | 1.0.3 / Dolibarr 3.5 / AGPL / utilgjengelig. Kildekode ikke undersøkt |
| S10 | [CAP-REL Peppol på DoliStore](https://www.dolistore.com/product.php?id=884&l=en&title=peppol-xml-facture-electronique) | 1.4.0; oppdatert 2026-10-06; V18–V24 / PHP 7.4–8.3; leverandøropplysninger, ikke vår kompatibilitetsgodkjenning |
| S11 | [Opphavsrett for SAF-T-standardkontoer](https://github.com/Skatteetaten/saf-t/blob/05179521e435d82feb0b2d6c89a92a32a4f2d02f/General%20Ledger%20Standard%20Accounts/Copyright%20Notice%20GL%20Standard%20Accounts.txt) | Regnskap Norge 2018; bruksbegrensning. Ikke distribuert som kontoplan |
| S12 | [Altinn: regnskapssystem](https://info.altinn.no/starte-og-drive/regnskap-og-revisjon/regnskap/regnskapssystem/) | Elektronisk regnskap og SAF-T; ikke anta fritak basert på bilagstall |
| S13 | [Offisielt Docker-oppsett](https://github.com/Dolibarr/dolibarr-docker) | Variabler og monteringspunkter for 24.0.1-containeren; lokal digest låst i Compose |

Ingen modul ble funnet gjennom kombinerte GitHub-/DoliStore-søk etter
Dolibarr Norway, Norwegian accounting og Norwegian SAF-T. Negativt søk er
begrenset til indeksert offentlig materiale og er ikke et markedsbevis.

SAF-T-kodereferansen fra 0.28.0 bruker kun de 30 numeriske identifikatorene fra [Standard Tax Codes CSV ved S6-revisjonen](https://github.com/Skatteetaten/saf-t/blob/05179521e435d82feb0b2d6c89a92a32a4f2d02f/Standard%20Tax%20Codes/CSV/Standard_Tax_Codes.csv), kontrollert 2026-10-12. Beskrivelser og behandlingsregler distribueres ikke. Eksakt kodemedlemskap er ikke historisk mapping eller valg av avgiftsbehandling.


## Revidert kravgrunnlag 2026-10-13

S5/S7/S8/S12 er kontrollert på nytt. SAF-T 1.40 kan brukes nå og er pliktig
fra 2027; eldre skjema gjelder tidligere regnskapsår. De generelle satsene er
25/15/12. Faktura-/kreditnotakrav og krav til elektronisk regnskap inngår i
akseptmatrisen, ikke bare oppsettveiledningen.

| ID | Primærkilde | Bruk / avgrensning |
| --- | --- | --- |
| S14 | [Standard Norge: NS 4102:2023, forhåndsvisning](https://forhandsvis.standard.no/product/2557323/en?filePath=096d0e6f-da0b-4f01-8c47-39cdf687ddc7.zip) | Bekrefter revisjonen og strukturendringer. Den medfølgende profilen er ikke verifisert mot hele standarden; innholdet kopieres ikke til Git. |
| S15 | [Norsk Regnskapsstiftelse: bokføringsstandarder](https://www.regnskapsstiftelsen.no/bokforing/bokforingsstandarder/) | NBS 1/2/3/4/5 april 2025 og NBS 7 november 2025 som separate kravgrunnlag for sikring, kontrollspor, elektronisk tilgjengelighet, e-faktura, balanse og betaling. Full funksjonell kvalifisering gjenstår. |
| S16 | [Altinn: oppbevaring](https://info.altinn.no/starte-og-drive/regnskap-og-revisjon/regnskap/oppbevaring-av-regnskapsmateriale) | Primær-/sekundærdokumentasjon og særregler; backup alene er ikke dokumentert etterlevelse. |
| S17 | [Skatteetaten: utenlandshandel](https://www.skatteetaten.no/bedrift-og-organisasjon/avgifter/mva/utland/) og [kjøp av tjenester fra utlandet](https://www.skatteetaten.no/satser/mva-satser-kjop-av-tjenester-fra-utlandet-svalbard-og-jan-mayen/) | Kjøp/salg, omvendt avgiftsplikt og egne rapporteringsløp må kvalifiseres per scenario. |
| S18 | [DFØ: EHF-fakturering](https://www.anskaffelser.no/verktoy/veiledere/ehf-fakturering) | EHF faktura/kreditnota 3.0 og offentlig mottakerprosess; ingen leverandør eller tilgangspunkt er valgt av denne kildekontrollen. |

Lovgrunnlaget er bokføringsloven og bokføringsforskriften, blant annet
salgsdokumentasjon, retting, spesifikasjoner og oppbevaring. Altinns
veiledninger lenker til gjeldende bestemmelser. Direkte Lovdata-lesing ble
avvist av tjenesten ved denne kontrollen; det hevdes derfor ikke full ny
paragrafgjennomgang. Rettskilder og konkrete akseptkrav kontrolleres på nytt
før hver avhengige leveranse.

## Supplerende norsk kravkontroll 2026-10-13

Kildene nedenfor begrunner tilleggene i [kravregisteret](norwegian-requirements.md).
De beskriver også betingede bruksområder; ingen av dem gir automatisk
funksjonsaksept for modulen.

| ID | Primærkilde | Bruk / avgrensning |
| --- | --- | --- |
| S19 | [Finansdepartementet: ikrafttredelse av e-fakturering og digital bokføring](https://www.regjeringen.no/no/aktuelt/nye-lovregler-om-e-fakturering-i-naringslivet-og-enkelte-andre-lovendringer-pa-finansmarkedsomradet-settes-i-kraft/id3166726/) og [overgangsregel for mottaksplikt](https://www.regjeringen.no/no/aktuelt/fastsetter-overgangsregel-om-plikten-til-a-motta-e-faktura-og-skattedirektoratet-delegeres-myndighet/id3173725/) | Vedtak/ikrafttredelse omtalt 19.06.2026 og overgangsregel 25.09.2026. Sending fra 2027, mottak og digital bokføring fra 2030. Gjeldende formatforskrift og unntak må kontrolleres ved kvalifisering. |
| S20 | [Altinn: bokføringsplikt](https://info.altinn.no/starte-og-drive/regnskap-og-revisjon/regnskap/bokforingsplikt/) og [regnskapsrutiner](https://info.altinn.no/starte-og-drive/regnskap-og-revisjon/regnskap/krav-til-regnskapet) | Ajourhold, bilag, dokumentasjon, periodearbeid og skille mellom bokførings- og regnskapsplikt. Detaljerte spesifikasjonskrav kontrolleres mot forskriften og NBS før aksept. |
| S21 | [Altinn: dokumentasjon av balansen](https://info.altinn.no/starte-og-drive/regnskap-og-revisjon/regnskap/dokumentasjon-av-balansen-avstemminger) og [varetelling](https://info.altinn.no/starte-og-drive/regnskap-og-revisjon/regnskap/varetelling) | Vesentlige balanseposter, lagerdokumentasjon og avstemming før årsavslutning. |
| S22 | [Skatteetaten: dokumentasjon av inngående MVA](https://www.skatteetaten.no/rettskilder/type/handboker/merverdiavgiftshandboken/merverdiavgiftshandboken-2024/M-15/M-15-10/M-15-10.2/), [tap på fordringer](https://www.skatteetaten.no/bedrift-og-organisasjon/skatt/skattemelding-naringsdrivende/fradrag/tap-pa-krav/), [kapitalvarejustering](https://www.skatteetaten.no/rettskilder/type/handboker/merverdiavgiftshandboken/gjeldende/M-9/M-9-4/) og [vareimport](https://www.skatteetaten.no/bedrift-og-organisasjon/avgifter/mva/utland/import/beregne/) | Identifiserer dokumentasjons- og behandlingsscenarioer. Historisk håndbok brukes som identifikasjon av problemområdet; gjeldende detaljvilkår, fradragsbegrensninger og bankbetalingskrav må kontrolleres før funksjonsaksept. |
| S23 | [Altinn: regnskapsplikt](https://info.altinn.no/starte-og-drive/regnskap-og-revisjon/regnskap/regnskapsplikt/) og [Skatteetaten: skattemeldingens struktur](https://www.skatteetaten.no/bedrift-og-organisasjon/skatt/skattemelding-naringsdrivende/innhold-og-struktur-i-skattemeldingen/) | Årsregnskap og næringsspesifikasjon er ulike leveranser; årsversjon og foretaksform styrer rapportgrunnlaget. |
| S24 | [Datatilsynet: personvernprinsippene](https://www.datatilsynet.no/rettigheter-og-plikter/personvernprinsippene/) og [rett til sletting](https://www.datatilsynet.no/rettigheter-og-plikter/den-registrertes-rettigheter/rett-til-sletting/) | Behandlingsgrunnlag, dataminimering, sikring, ansvarlighet og lagringsbegrensning sammenholdt med bokføringspliktig oppbevaring i S16. Leverandøravtaler og konkrete sletteregler kvalifiseres separat. |
| S25 | [Skatteetaten: arbeidsgivere og ny innkreving](https://www.skatteetaten.no/om-skatteetaten/fremtidens-innkreving/arbeidsgivere/) | Arbeidsgiverløpet må ta hensyn til 2026-reglene for trekk og betaling. Ingen lønnsstøtte godkjennes av dette kilderegistret. |
| S26 | [Altinn: regnskap før oppstart](https://info.altinn.no/starte-og-drive/starte/for-oppstart/regnskap-for-oppstart/) og [Skatteetaten: dokumentasjon av kontantsalg](https://www.skatteetaten.no/rettskilder/type/handboker/skatteforvaltningshandboken/gjeldende/kapittel-14-administrative-reaksjoner-og-straff/ID-14-7.001/ID-14-7.003/) | Avklar kontantsalg, kassasystem og aktuelle bransjekrav. Ingen generell norsk kvalifisering av Dolibarrs POS følger av at funksjonen finnes. |
| S27 | [Finanstilsynet: forsinkelsesrente](https://www.finanstilsynet.no/analyser-og-statistikk/forsinkelsesrente-og-standardkompensasjon/forsinkelsesrente/) og [inkassovirksomhet](https://www.finanstilsynet.no/forbrukerinformasjon/inkassovirksomhet/) | Hvis purring/innkreving brukes: gjeldende vilkår, frister, kundetype, satser og tillegg vurderes før automatisering. Ingen fast sats hardkodes som varig standard. |
| S28 | [Forbrukertilsynet: standardvilkår for forbrukerkjøp over internett](https://www.forbrukertilsynet.no/wp-content/uploads/2017/12/Standard-salgsbetingelser-for-forbrukerkj%C3%B8p-av-varer-over-internett.pdf) og [veiledning for digitale tjenester](https://www.forbrukertilsynet.no/wp-content/uploads/2021/11/veiledning-om-avtalevilkar-norsk.pdf) | Identifiserer forbrukerscenarioer som må vurderes separat. Eldre veiledninger er ikke komplett kontroll av gjeldende 2026-regler; lovvilkår og eventuelle nye krav verifiseres før funksjonsaksept. |

## Gjenbruk og rapportering kontrollert 2026-10-16

Denne kontrollen gjelder [dekningsanalysen](coverage-analysis.md). Ingen
nye tillegg er installert, og ingen tidligere lov-/API-kontroll erstattes
utenfor disse områdene.

| ID | Primærkilde | Kontroll og begrensning |
| --- | --- | --- |
| S29 | [MVA-modell](https://skatteetaten.github.io/mva-meldingen/mvameldingen/informasjonsmodell/) og [innsending](https://skatteetaten.github.io/mva-meldingen/documentation/api/) | Norske koder, spesifikasjoner, validering og tilbakemelding. Dokumentasjonen har eldre endringslogg; aktuell tilgang og versjon må prøves før integrasjon. |
| S30 | [ENK-skattemelding](https://www.skatteetaten.no/bedrift-og-organisasjon/skatt/skattemelding-naringsdrivende/enk/) og [leverandørrepo](https://github.com/Skatteetaten/skattemeldingen) | Leveringsveier og årsversjon er egne avklaringer. README-eksempler er ikke automatisk gjeldende modell. |
| S31 | [CAP-REL Peppol](https://doc.cap-rel.fr/peppol/) | Funksjonsdokumentasjon undersøkt; versjon/PHP-støtte og norsk profil ikke funksjonstestet. Produktside og søkeindeks ga ulike versjonsopplysninger. |
| S32 | [SAF-T PT-repo](https://github.com/cialinux/dolibarr-saft/tree/91dc9aed1a7783b18ac4d14dd5289e1558befc93) | README: portugisisk import/validering med kommersiell API. Ikke norsk eksport. |
| S33 | [Åpen Peppol-kandidat](https://github.com/marcrant1/dolibarr-peppol-export/tree/c8d965b1cfc4080880e4a9b51439e4f1a39eceff) og [Peppyrus-kandidat](https://github.com/cedric-marcoux/dolibarr_peppol-peppyrus/tree/cede93ae75730d56cbb472e3e2cf6f896b9d54d9) | README/repometadata undersøkt. Kilde, lisensvilkår, norsk støtte, aktuelle tjenestevilkår og kompatibilitet må kvalifiseres før valg. Ingen kode kopiert. |

Kjernefunn er lest mot S2, særlig `core/lib/tax.lib.php`,
`compta/tva/index.php` og `accountancy/class/bookkeeping.class.php`.
S5 ble kontrollert på nytt: 1.40 er angitt som format fra 2027.
Søk etter `dolibarr saft`, `dolibarr norway` og `dolibarr peppol` på GitHub
og avgrensede web-/DoliStore-søk er dokumentert som kandidatfunn, ikke som
bevis på et uttømmende markedssøk.

MVA-kildegjennomgangen er fulgt opp med kjøring av kjernens `tax_by_rate()`
i `tests/core-vat-report.php`, mot syntetiske, journalførte salgs-/kjøpsbilag.
Resultat og begrensninger står i dekningsanalysen. Testen verifiserer oppførsel
i 24.0.1; den godkjenner ikke norsk avgiftsbehandling eller komplett melding.

Fradrag og NPR fulgt opp i 0.66.0: [Skatteetatens gjeldende håndbok kapittel 8](https://oppslag.rettskilder.skatteetaten.no/rettskilder2/type/handboker/merverdiavgiftshandboken/gjeldende/MVA2025_M-8)
og [Finansdepartementets tolkningsuttalelse om fellesanskaffelser](https://www.regjeringen.no/no/dokumenter/-8-2-fradrag-for-inngaende-merverdiavgift-pa-fellesanskaffelser/id2828446/)
er faglige referanser for behovet for å vurdere fradragsrett og fordeling.
Eksempelandelene 50/0 prosent i prøven er syntetiske valg, ikke generelle regler.
NPR-begrepet er kontrollert mot S2: `install/mysql/tables/llx_c_tva.sql`,
`core/lib/price.lib.php`, `fourn/class/fournisseur.facture.class.php` og
`accountancy/journal/purchasesjournal.php`. Native blandede satser,
kreditnotaer og manuelle omposteringer er prøvd i `tests/core-purchases.php`.

0.67.0: Ordinære salgskoder 3/31/33 og kjøpskoder 1/11/13 er kontrollert mot
S6s `Standard Tax Codes/CSV/Standard_Tax_Codes.csv` og S29s meldingsmodell.
Bare numeriske kodeidentifikatorer og egne sats-/bruksvalg distribueres.
Kjernen S2 er undersøkt i `core/lib/functions.lib.php` (standardoppslag),
`core/class/html.form.class.php` (`load_tva` og `type_vat`),
`core/tpl/objectline_create.tpl.php` og produkt-/prisbildene.
Native widgetvalg og kodelagring er prøvd i `tests/vat-rates.php`.

## ENK: forskuddsskatt og årsoppgjør — kontrollert 2026-10-18

- [Skatteetaten: forskuddsskatt ENK](https://www.skatteetaten.no/bedrift-og-organisasjon/skatt/forskuddsskatt/forskuddsskatt-for-enkeltpersonforetak/) — personlig skattekort, endringer, terminer og tilleggsforskudd.
- [Skatteetaten: eiendeler](https://www.skatteetaten.no/bedrift-og-organisasjon/skatt/skattemelding-naringsdrivende/fradrag/eiendeler-utstyr-eiendom/bedriftens-eiendeler/kjope-eiendeler-til-bedriften/) og [satser](https://www.skatteetaten.no/satser/avskrivningssatser/) — skattemessige saldoer og skillet mot lineær avskrivning.
- [Skatteetaten: personinntekt](https://www.skatteetaten.no/bedrift-og-organisasjon/skatt/skattemelding-naringsdrivende/fradrag/inntekt-formue-gjeld/personinntekt-i-enkeltpersonforetak/) og [innsending/kvittering](https://www.skatteetaten.no/bedrift-og-organisasjon/skatt/skattemelding-naringsdrivende/hjelp-til-skattemelding/innsending/) — tilleggsgrunnlag og faktisk levering.
- [DNB Regnskap: ENK-uttak](https://hjelp.dnbregnskap.dnb.no/no/article/uttak-enkeltpersonsforetak-amp22n/) og [Tripletex: ENK-driftsmidler](https://hjelp.tripletex.no/hc/no/articles/20560625350801-Varige-driftsmidler-for-enkeltpersonforetak) — sammenligning av normal arbeidsflyt før implementasjon, ikke regelkilder.

[Årsavslutning](year-end.md) og [portalvei](enk-tax-return.md) skiller prøvd
regnskapsgrunnlag fra veiledet, personlig innlevering. 2025 er regneeksemplets
inntektsår. Ingen faktisk privat innsending eller automatisk skatteberegning
er kvalifisert. Skatteetatens publiserte 2026-modeller/tekster erstatter ikke
kontroll av den endelige 2026-portalen ved levering i 2027.
