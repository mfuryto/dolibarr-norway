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
cp compose.yaml "$ci_root/compose.yaml"
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
"${compose[@]}" exec -T web php /opt/norway-tests/integration.php
"${compose[@]}" exec -T web php /opt/norway-tests/http-smoke.php
