# Kontroll av kontoplan og bokføringsoppsett

Norway 0.6.0 legger til en administratorstyrt, skrivebeskyttet kontrollside fra moduloppsettet. Den henter valgt `CHARTOFACCOUNTS`, kontoer fra denne planen for aktiv entity, åtte standardkontokoblinger og aktive journaler for salg, kjøp, bank og diverse posteringer. Den endrer ikke kontoer, konstanter, koblinger eller bilag.

Kontoplanregisteret er globalt i Dolibarr; kontoer og journaler avgrenses til aktivt foretak. En valgt plan må finnes og være aktiv. Plan uten norsk landtilknytning markeres for vurdering; norsk landtilknytning bekrefter ikke riktig kontoinnhold eller mapping. Kontoene telles som aktive/alle. Standardkontoverdier tolkes som kontonumre, ikke interne konto-ID-er, i samsvar med kjernens oppsett.

Standardkontoene gjelder kunde, leverandør, produkt-/tjenestesalg, produkt-/tjenestekjøp og inngående/utgående MVA. Kontrollresultatet skiller tilgjengelig, manglende, inaktiv, tvetydig og ikke angitt. Uten standardkonto kan andre produkt-, motparts- eller linjekoblinger finnes; de undersøkes ikke her. MVA-kontoer vurderer verken registrering, fradragsrett eller avgiftskode. Journalantall viser tilstedeværelse, ikke at en bestemt transaksjon bruker riktig journal.

## Aksept og begrensninger

Syntetiske databasetester dekker valgt plan, aktiv/inaktiv konto og plan, manglende standarder, konto fra annet foretak/annen plan, aktive/inaktive journaler, tom journalkode, ukjent land og databasefeil. Testdata rulles tilbake. HTTP-testen kontrollerer den autentiserte siden, oppsettlenker og at layout-CSS er lastet. Nettlesertest av 15 sider ved fire skjermbredder (60 visninger), inkludert register- og kontoplanoppsett, bestod uten sideoverskridelse i Eldy-tema. Ingen produksjonsdata brukes.

Dette er første del av kontoplanarbeidet, ikke en norsk kontoplanimport eller bokføringsgodkjenning. Neste del er et avgrenset kontoutvalg med versjonert mapping, forhåndsvisning, konfliktkontroll og repeterbar import. Ingen samlet produksjonsklar-status vises.

Teknisk referanse: [Dolibarr 24.0.1 kilde](https://github.com/Dolibarr/dolibarr/tree/b7958385f00a92219f76a4dfea67dae93df97a25), særlig `accountancy/admin/defaultaccounts.php`, `core/class/html.formaccounting.class.php` og tabellene `accounting_system`, `accounting_account`, `accounting_journal`.
