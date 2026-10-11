# Kontroll av kontoplan og bokføringsoppsett

Norway 0.6.0 legger til en administratorstyrt, skrivebeskyttet kontrollside fra moduloppsettet. Den henter valgt `CHARTOFACCOUNTS`, kontoer fra denne planen for aktiv entity, ti standardkontokoblinger og aktive journaler for salg, kjøp, bank og diverse posteringer. Den endrer ikke kontoer, konstanter, koblinger eller bilag.

Kontoplanregisteret er globalt i Dolibarr; kontoer og journaler avgrenses til aktivt foretak. En valgt plan må finnes og være aktiv. Plan uten norsk landtilknytning markeres for vurdering; norsk landtilknytning bekrefter ikke riktig kontoinnhold eller mapping. Kontoene telles som aktive/alle. Standardkontoverdier tolkes som kontonumre, ikke interne konto-ID-er, i samsvar med kjernens oppsett.

Standardkontoene gjelder kunde, leverandør, produkt-/tjenestesalg, produkt-/tjenestekjøp og inngående/utgående MVA, feilkonto og overføringskonto. Kontrollresultatet skiller tilgjengelig, manglende, inaktiv, tvetydig og ikke angitt. Uten standardkonto kan andre produkt-, motparts- eller linjekoblinger finnes; de undersøkes ikke her. MVA-kontoer vurderer verken registrering, fradragsrett eller avgiftskode. Journalantall viser tilstedeværelse, ikke at en bestemt transaksjon bruker riktig journal.

## Aksept og begrensninger

Syntetiske databasetester dekker valgt plan, aktiv/inaktiv konto og plan, manglende standarder, konto fra annet foretak/annen plan, aktive/inaktive journaler, tom journalkode, ukjent land og databasefeil. Testdata rulles tilbake. HTTP-testen kontrollerer den autentiserte siden, oppsettlenker og at layout-CSS er lastet. Nettlesertest av 15 sider ved fire skjermbredder (60 visninger), inkludert register- og kontoplanoppsett, bestod uten sideoverskridelse i Eldy-tema. Ingen produksjonsdata brukes.

## Kontoforslag fra versjon 0.10.0

Når valgt plan er aktiv, norsk og har versjon NORWAY-NO-1, viser siden fem forslag fra den medfølgende kontoplanen:

| Standard | Konto | Avgrensning |
|---|---|---|
| Kunder | 1500 Kundefordringer | Reskontrostandard; egne kundekoblinger kan overstyre |
| Leverandører | 2400 Leverandørgjeld | Reskontrostandard; egne leverandørkoblinger kan overstyre |
| Tjenestesalg | 3020 Salgsinntekt tjenester, avgiftspliktig, høy sats | Innenlandsk tjenestesalg med høy sats for MVA-registrert virksomhet |
| Utgående MVA | 2700 Utgående merverdiavgift, høy sats | Ingen avgiftskode eller registrering settes |
| Inngående MVA | 2710 Inngående merverdiavgift, høy sats | Fradragsrett må vurderes separat |

Forslagene vises med nåværende standard og kontoens navn. «Tilgjengelig» krever at standarden allerede peker på den foreslåtte, aktive kontoen med uendret navn fra kildedatasettet. Avvik vises for vurdering, også når en annen gyldig standard er valgt. Dette betyr ikke at en egendefinert konto er feil. Kontoer i andre foretak eller planer brukes ikke. Andre kontoplaner får ingen slike forslag.

Siden er skrivebeskyttet. Den lagrer ingen standarder og endrer ikke journaler, bankkontoer, bilag eller produkt-/motparts-/linjekoblinger. Standardene kan gjennomgås via lenken til Dolibarrs standardkontoer. 1920 Bankinnskudd må knyttes til den faktiske bankkontoen i bankoppsettet; den er ingen global betalingsstandard. Kjøpskostnader, feilkonto og overføringskonto får ingen generell automatisk mapping.

Salg til utlandet og salg uten MVA må avklares før bruk av 3020 som standard. Kjøp av fjernleverbare tjenester fra utlandet krever særskilt vurdering av omvendt avgiftsplikt, også uten norsk MVA-registrering. Se [Skatteetatens omtale av fjernleverbare tjenester](https://www.skatteetaten.no/rettskilder/type/handboker/merverdiavgiftshandboken/gjeldende/M-3/M-3-30/M-3-30.3/) og [registrering i MVA-registeret](https://oppslag.rettskilder.skatteetaten.no/rettskilder2/type/handboker/merverdiavgiftshandboken/gjeldende/MVA2025_M-2). Moduloppdatering gjør ikke virksomheten MVA-registrert.

Syntetiske integrasjonstester dekker forslag etter full kontoplaninstallering, eksisterende samsvar, manglende standard, isolering mellom foretak/planer, inaktiv konto og endret kontonavn. Automatisk oppsett av standardkontoer, journalvalg, avgiftskoder, utenlandshandel og SAF-T-mapping gjenstår.

Teknisk referanse: [Dolibarr 24.0.1 kilde](https://github.com/Dolibarr/dolibarr/tree/b7958385f00a92219f76a4dfea67dae93df97a25), særlig `accountancy/admin/defaultaccounts.php`, `core/class/html.formaccounting.class.php` og tabellene `accounting_system`, `accounting_account`, `accounting_journal`.

## Bankkoblinger fra versjon 0.11.0

Kontrollsiden viser hver åpen bank-/kassekonto i aktivt foretak med lenke til kontokortet, bokføringskonto, tilknyttet journal, valuta og eventuell deling av bokføringskonto. Den henter ikke faktiske bankkontonumre, IBAN, eiere eller saldoer. Lukkede konti utelates; historiske bevegelser og avstemming er fortsatt eget arbeid.

Bokføringskontoen må finnes og være aktiv i valgt, aktiv kontoplan for samme foretak. Journalen må tilhøre samme foretak, være aktiv, ha kode og ha natur 4 (bank/kasse). En aktiv salgsjournal kan derfor ikke passere som bankjournal. En kobling til et annet foretaks journal vises som manglende uten å vise journalens kode. Tom konto eller journal vises som ikke angitt. Manglende valgt plan eller inaktiv plan blir ikke godkjent for kontokoblingen. Andre valutaer enn NOK og deling av bokføringskonto mellom flere åpne bank-/kassekonti vises for vurdering, uten å sperre eller endre betalinger.

«Tilgjengelig» bekrefter strukturell kobling, ikke at kontoen har riktig regnskapsmessig klassifisering. Kontoplanimporten setter foreløpig ingen kontokategorier. Deling kan være tilsiktet; kontrollen krever ikke separate journaler eller tvinger alle bankkonti til 1920. En tom bankliste gir en tydelig melding fremfor en grønn status.

### Manuell gjennomgang

1. Åpne journaloppsettet fra kontrollsiden. Kontroller aktive journaler med egen kode og riktig type for salg (2), kjøp (3), bank/kasse (4) og diverse (1). Eksisterende koder kan beholdes; kodene er ikke norske myndighetskoder.
2. Åpne bank-/kasselisten og det aktuelle kontokortet. Under redigering velges bokføringskonto og bankjournal. 1920 Bankinnskudd er et kontoforslag for en ordinær NOK-bankkonto; flere konti og kasse krever egne vurderinger.
3. Åpne kontrollsiden igjen og gjennomgå konto-, journal- og valutastatus hver for seg. Produkt-, motparts- og linjekoblinger, MVA-koder og hovedbokoverføring må kontrolleres separat.

Siden oppretter ingen journaler og lagrer ingen bankkoblinger. Den kontrollerer ikke salgs-/kjøpsjournalens konkrete bilagsoverføring eller bankavstemming. Syntetiske transaksjonstester dekker gyldig kobling, tom/manglende/inaktiv konto, feil plan og foretak, inaktiv/feil journaltype, tom journalkode, fremmed journal, lukkede konti, kasse, valuta, deling og databasefeil. Testdata rulles tilbake. Autentisert HTTP-test kontrollerer at bankdelen finnes.

Teknisk grunnlag: Dolibarr 24.0.1, særlig bankkontokortets felt account_number og fk_accountancy_journal samt accountancy/journal/bankjournal.php. Bankjournalens utvalg bruker bankkontoens tilknyttede journal; det er ikke tilstrekkelig at en aktiv bankjournal finnes i registeret.
