#!/usr/bin/env bash
# Fresh disposable environment; never remove the interactive development volumes.
set -euo pipefail
cd "$(dirname "$0")/.."
ci_root=$(mktemp -d /tmp/norway-ci.XXXXXXXX)
cleanup() {
    docker compose --project-directory "$ci_root" -f "$ci_root/compose.yaml" -p "norway-ci-$$" down --volumes --remove-orphans >/dev/null 2>&1 || true
    rm -rf -- "$ci_root"
}
trap cleanup EXIT
cp compose.test.yaml "$ci_root/compose.yaml"
cp -R htdocs tests scripts "$ci_root/"
# Tests use HTTP inside the web container; no host port needed.
python3 - "$ci_root/compose.yaml" <<'PYCODE'
from pathlib import Path
import sys
p = Path(sys.argv[1])
p.write_text(p.read_text().replace("    ports: ['127.0.0.1:8087:80']\n", ''))
PYCODE
python3 "$ci_root/scripts/init-test-env.py"
compose=(docker compose --project-directory "$ci_root" -f "$ci_root/compose.yaml" -p "norway-ci-$$")
"${compose[@]}" config --quiet
"${compose[@]}" up -d
ready=0
for attempt in $(seq 1 90); do
    if "${compose[@]}" exec -T web test -f /var/www/documents/install.lock; then
        ready=1
        break
    fi
    sleep 2
done
if [[ "$ready" != 1 ]]; then
    echo 'Dolibarr installation did not finish within 180 seconds.' >&2
    exit 1
fi
"${compose[@]}" exec -T web sh -c 'find /var/www/html/custom/norway /opt/norway-tests -name "*.php" -exec php -l {} \;'
"${compose[@]}" exec -T web php /opt/norway-tests/home-dashboard.php
"${compose[@]}" exec -T web php /opt/norway-tests/readiness.php
"${compose[@]}" exec -T web php /opt/norway-tests/invoice-check.php
"${compose[@]}" exec -T web php /opt/norway-tests/pdf-identity.php
"${compose[@]}" exec -T web php /opt/norway-tests/legacy-currency.php
"${compose[@]}" exec -T web php /opt/norway-tests/integration.php
"${compose[@]}" exec -T web php /opt/norway-tests/schema-migration.php
"${compose[@]}" exec -T web php /opt/norway-tests/bank-setup.php
"${compose[@]}" exec -T web php /opt/norway-tests/bank-defaults.php
"${compose[@]}" exec -T web php /opt/norway-tests/journal-setup.php
"${compose[@]}" exec -T web php /opt/norway-tests/company-setup.php
"${compose[@]}" exec -T web php /opt/norway-tests/vat-accounts.php
"${compose[@]}" exec -T web php /opt/norway-tests/vat-rates.php
"${compose[@]}" exec -T web php /opt/norway-tests/registry.php
"${compose[@]}" exec -T web php /opt/norway-tests/eur-pricing.php
"${compose[@]}" exec -T web php /opt/norway-tests/invoice-workflow.php
"${compose[@]}" exec -T web php /opt/norway-tests/numbering.php
"${compose[@]}" exec -T web php /opt/norway-tests/numbering-http.php
"${compose[@]}" exec -T web php /opt/norway-tests/partner-prefixes.php
"${compose[@]}" exec -T web php /opt/norway-tests/chart-preview.php
"${compose[@]}" exec -T web php /opt/norway-tests/chart-import.php
"${compose[@]}" exec -T web php /opt/norway-tests/chart-profile.php
"${compose[@]}" exec -T web php /opt/norway-tests/standard-accounts.php
"${compose[@]}" exec -T web php /opt/norway-tests/http-smoke.php
"${compose[@]}" exec -T web php /opt/norway-tests/company-vat-http.php

"${compose[@]}" exec -T web php /opt/norway-tests/navigation.php

"${compose[@]}" exec -T web php /opt/norway-tests/core-purchases.php
"${compose[@]}" exec -T web php /opt/norway-tests/legacy-cleanup.php
