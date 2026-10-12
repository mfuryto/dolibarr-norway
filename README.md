# Norsk lokalisering for Dolibarr

Under utvikling. **Ikke klart for norsk produksjonsregnskap.**

Målet er en ekstern modul under `htdocs/custom/norway` uten endringer i kjernen.
Første testmål er Dolibarr **24.0.1**, låst til kilde og containerdigest.
Repoet inneholdt bare README og GPLv3-lisens ved oppstart 11. oktober 2026.

- [Foretaksfelt og norske organisasjonsformer](docs/company-setup.md)
- [Bekreftet oppretting av manglende journaltyper](docs/journal-setup.md)
- [Bekreftet utfylling av kunde- og leverandørkonto](docs/default-accounts.md)
- [Norsk oppsettsveiviser inne i Dolibarr](docs/setup-wizard.md)
- [Start her: samlet veiledning for norsk oppsett](docs/norwegian-setup-guide.md)
- [Installere med norsk modul på VPS og eget domene](docs/vps-installation.md)
- [Tilbud og faktura på norsk/engelsk i NOK, veiledende EUR-pris og bilagskontroll](docs/sales-documents.md)
- [Sikkerhet og privat sårbarhetsrapportering](SECURITY.md)
- [Grenflyt: stabil main og utvikling på staging](docs/branch-workflow.md)
- [Teknisk dekningsanalyse og datagap](docs/coverage-analysis.md)
- [Installerbar norsk kontoplan med 745 kontoer](docs/norwegian-chart.md)
- [Omfang, milepæler og akseptansekriterier](docs/delivery-plan.md)
- [Testmiljø, installasjon og drift](docs/test-and-operations.md)
- [Kilderegister](docs/sources.md)
- [Generelle prosjektkrav](docs/project-brief.txt)

## Serverinstallasjon

Driftsoppsettet bruker databasen `accounting`, vedvarende data og ingen
syntetiske bilag. Standardfilen `compose.yaml` og `deploy/compose.yaml` beskriver
samme driftsprosjekt med persistente data i `data/` og backup i `backups/`; bruk alltid `--env-file deploy/.env`. Utviklingstestene
krever eksplisitt `-f compose.test.yaml` og har egne volumer.

Se [serveroppdatering og backup](docs/vps-installation.md).

## Utviklingstester

Krever Docker Compose og Python 3. Fra repoets rot:

```sh
python3 scripts/init-test-env.py
python3 -m unittest discover -s tests -v
docker compose -f compose.test.yaml up -d
# Vent til web-loggen sier at installasjonen er ferdig:
docker compose -f compose.test.yaml logs --tail 20 web
docker compose -f compose.test.yaml exec -T web php /opt/norway-tests/integration.php
python3 scripts/package.py
```

Modulen kan også kopieres direkte fra `htdocs/custom/norway` til en separat
Dolibarr 24.0.1-installasjon. Installerbar pakke bygges i
`dist/module_norway-0.27.0.zip`. Se driftsdokumentet for testinnlogging og nettverk.

Leveransen inneholder modulaktivering, statusside, isolert testmiljø og syntetiske
bilag med avstemminger. Norsk MVA-rapport, SAF-T, EHF og
lisensportal er videre arbeid. Ingen reelle data eller hemmeligheter skal i Git.

Lisens: GPL-3.0-or-later; se [LICENSE](LICENSE).

Skrivebeskyttet kontoplan-/bokføringskontroll: [oppsettkontroll](docs/accounting-setup.md).

Manuell MVA-sammenligning med Brønnøysundregistrene: [registerkontroll](docs/registry.md).

[Kontoplan fra CSV](docs/chart-preview.md) har skrivebeskyttet forhåndsvisning og bekreftet import av bare nye kontoer. Norsk kontoplan kan installeres separat; avgifts- og rapporteringsmapping gjenstår.

Se [bekreftet bankkobling](docs/bank-defaults.md) for én ny ordinær NOK-bankkonto.

Se [kontroll av faktura- og tilbudsoppsett](docs/document-setup.md) for samlet oversikt over nummerering, PDF-valg og språk.

Se [kontroll av regnskapsperioder](docs/period-setup.md) for datodekning, overlapp og eksisterende periodestatus.

Se [kontroll av MVA-kontokoblinger](docs/vat-setup.md) for satskontoer og standardkontoer som reserve.

Se [eksplisitt MVA-virkningsdato](docs/vat-registration.md) for bekreftet første lagring og datovarsler.

Se [fakturaens MVA-beløp](docs/invoice-amounts.md) for lesekontroll av linjer mot fakturatotaler.

Se [fakturakontroll for periode](docs/invoice-period.md) for samlet lesekontroll og dokumentlenker.

Se [lagrede avgiftsopplysninger](docs/invoice-tax-evidence.md) for fakturalinjenes koder og særmarkeringer.
