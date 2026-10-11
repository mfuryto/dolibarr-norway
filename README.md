# Norsk lokalisering for Dolibarr

Under utvikling. **Ikke klart for norsk produksjonsregnskap.**

Målet er en ekstern modul under `htdocs/custom/norway` uten endringer i kjernen.
Første testmål er Dolibarr **24.0.1**, låst til kilde og containerdigest.
Repoet inneholdt bare README og GPLv3-lisens ved oppstart 11. oktober 2026.

- [Teknisk dekningsanalyse og datagap](docs/coverage-analysis.md)
- [Omfang, milepæler og akseptansekriterier](docs/delivery-plan.md)
- [Testmiljø, installasjon og drift](docs/test-and-operations.md)
- [Kilderegister](docs/sources.md)
- [Opprinnelig prosjektbestilling](docs/project-brief.txt)

## Prøv modulgrunnlaget

Krever Docker Compose og Python 3. Fra repoets rot:

```sh
python3 scripts/init-test-env.py
python3 -m unittest discover -s tests -v
docker compose up -d
# Vent til web-loggen sier at installasjonen er ferdig:
docker compose logs --tail 20 web
docker compose exec -T web php /opt/norway-tests/integration.php
python3 scripts/package.py
```

Modulen kan også kopieres direkte fra `htdocs/custom/norway` til en separat
Dolibarr 24.0.1-installasjon. Installerbar pakke bygges i
`dist/module_norway-0.1.0.zip`. Se driftsdokumentet for testinnlogging og nettverk.

Leveransen inneholder modulaktivering, statusside, isolert testmiljø og syntetiske
bilag med avstemminger. Kontoplanimport, norsk MVA-rapport, SAF-T, EHF og
lisensportal er videre arbeid. Ingen reelle data eller hemmeligheter skal i Git.

Lisens: GPL-3.0-or-later; se [LICENSE](LICENSE).
