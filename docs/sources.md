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
