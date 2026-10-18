# Norsk SAF-T 1.40 — arbeid og aksept

Oppdatert 2026-10-18. **SAF-T er ikke levert.** 0.82.0 på staging starter
etappen med offisielt formatgrunnlag og et skrivebeskyttet periodeuttrekk.
Ingen ny eksportknapp, kontrollside eller bekreftelsesflyt er lagt til.
EHF avventer ekstern testtilgang etter brukerens valg; dette lukker ikke EHF.

## Kilder og formatvalg

[Skatteetaten](https://www.skatteetaten.no/bedrift-og-organisasjon/starte-og-drive/rutiner-regnskap-og-kassasystem/saf-t-regnskap/dokumentasjon/)
tillater 1.40 nå og krever formatet fra 2027. XSD og kodelister er hentet fra
[offisielt repository](https://github.com/Skatteetaten/saf-t/tree/10c8fd82a6ff47e53385bc33c7638c5fde239a4f).
Revisjon og SHA-256 for hver originalfil står i modulens
`data/saft/manifest.json`. Originalfilene endres ikke. Kodelisten for
næringsspesifikasjon gjelder 2025–2026; 2027-gruppering blir ikke antatt lik.

SAF-T fra 2025 krever **GroupingCategory + GroupingCode**. To-/firesifret
standardkonto er ikke et alternativ i 1.30/1.40. Et likt nummer er ikke i seg
selv grunnlag for å koble kontoene. Eksempel: lokale MVA-kontoer 2700 og 2710
kan knyttes til `kortsiktigGjeld/2740`; 2700 er ikke en gyldig gruppekode her.
Dette er eksportklassifisering. Kontoplanen og bokførte kontonumre beholdes.

Arbeidsflyten sammenlignes med
[Fiken: eksport av regnskap for valgt periode](https://hjelp.fiken.no/tips-til-revisorer-hvordan-revidere-effektivt-i-fiken),
[Tripletex: SAF-T-eksport](https://hjelp.tripletex.no/hc/no/sections/21672753915025-Eksport)
og [Microsoft: norsk SAF-T og mapping](https://learn.microsoft.com/en-us/dynamics365/finance/localizations/norway/emea-nor-satndard-audit-file-for-tax).
Leverandørdokumentasjon er arbeidsflytinspirasjon, ikke rettslig grunnlag.

## Gjenbruk av kjernen og konkret gap

Dolibarr 24.0.1 har hovedbok, kontoplan, reskontro, bilags-/dokumentkoblinger
og eksportrettigheter. `AccountancyExport` tilbyr format-hooks; den native
regnskapseksportsiden er førstevalg for plassering når funksjonen er ferdig.
Den undersøkte kjernen har ingen norsk SAF-T-generator. Undersøkte offentlige
Dolibarr-eksporttillegg dekker blant annet fransk FEC; ingen kvalifisert norsk
1.40-løsning ble funnet i denne gjennomgangen. Det er ikke et bevis på at
ingen kommersiell løsning finnes.

Kjernens vanlige eksport tillater konto-/bilagsfiltre, utelatelse av tidligere
eksporterte rader og markering av eksportdato. SAF-T må hente alle relevante
rader for valgt foretak og periode, bevare hele bilag og inkludere nødvendige
stamdata og saldoer. En ubalansert eller ufullstendig fil skal ikke lastes ned.
Det lages ingen parallell hovedbok eller lagret kopimodell.

`NorwaySaftLedger` leser én konsistent visning av hovedboken med database-
transaksjon og samme faste desimalregning som MVA-grunnlaget. Ingen skrivekall,
eksportmarkering eller brukerfilter brukes. Rader, bilags-ID, kildefelt,
reskontrofelt og historiske metadata bevares i minnet for videre eksport.
Periodeuttrekket kontrollerer hele bilag ved begge datogrenser, debet/kredit,
foretak og eksportrettighet. Årlig saldooverføring (`closure`) avvises foreløpig:
kumulative historiske summer kan ellers telle åpningssaldoen dobbelt.
Uttrekket er internt og åpner ikke en brukerrettet eksport.

## Systematisk ferdigstilling av etappen

| Del | Status og ferdigkriterium |
|---|---|
| 1. Format og kildeuttrekk | Referanser og første periodeprøver er på plass. Låst original-XSD, gyldig/ugyldig XML, eksplisitt gruppevalg, komplette native bilag, eksporterte rader og uendret hovedbok er prøvd. Årlig saldooverføring er et uttrykkelig gjenværende gap. |
| 2. Konto-/avgiftskoblinger og reskontro | Gjenbruk eksisterende kontooppsett der det passer. Lever dokumenterte standardkoblinger for støttet kontoplan og nødvendige valg for egne kontoer. Bevar kunder/leverandører og betalinger, også samme motpart med begge roller og eldre koder. Ingen koder gjettes ut fra sats alene. |
| 3. XML fra normal bokføring | Header, MasterFiles og GeneralLedgerEntries fra samme periodevisning. MVA-informasjon legges på grunnlagslinjene, ikke både grunnlag og MVA-konto. Delvis fradrag og omvendt avgiftsplikt må representeres korrekt. Kildebilag, kreditnota og saldoer avstemmes. |
| 4. Native eksport og samlet aksept | Rettigheter, komplett periodevalg, privat nedlasting, gjentatt eksport og feiltilfeller i norsk/engelsk grensesnitt. XSD pluss uavhengig beløps-/fullstendighetsfasit på fersk og oppgradert installasjon. Ingen regnskapsdata eller eksportdatoer endres. |

MVA-prinsipper og saldo-/periodekrav følger
[generell dokumentasjon 1.40](https://www.skatteetaten.no/globalassets/bedrift-og-organisasjon/starte-og-drive/rutiner-regnskap-og-kassasystem/saf-t-regnskap/august2026/norwegian-saf-t-financial-data---documentation-1.40.pdf)
og [teknisk beskrivelse](https://www.skatteetaten.no/globalassets/bedrift-og-organisasjon/starte-og-drive/rutiner-regnskap-og-kassasystem/saf-t-regnskap/august2026/norwegian-saf-t-financial-data---technical-description-1.4.pdf).
Skjemavalid XML alene er ikke ferdig SAF-T. Regnskapsdata skal kunne avstemmes
med kjernen og sporbarheten skal bevares.

`tests/saft-foundation.php` prøver referansefiler, obligatoriske grupper,
skjemavalidering, egne native bilag, datogrenser, kildebevaring og tilgang.
Testen er begrenset til det syntetiske miljøet. Den prøver ikke en ferdig eksport
av faktura, MVA eller reskontro og skal ikke omtales som slik aksept.

Årsavslutning og skattemelding starter først etter at denne SAF-T-etappen
oppfyller ferdigkriteriene. SAF-T erstatter ikke næringsspesifikasjon eller
skattemelding selv om eksporten bruker deres grupperingskoder.
