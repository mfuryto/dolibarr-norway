#!/usr/bin/env bash
# Verify a fresh installation and a container replacement without sample data.
set -euo pipefail
cd "$(dirname "$0")/.."
install_root=$(mktemp -d /tmp/norway-install-ci.XXXXXXXX)
compose=(docker compose --env-file "$install_root/deploy/.env" -f "$install_root/deploy/compose.yaml" -p "norway-install-ci-$$")
cleanup() {
    "${compose[@]}" down --volumes --remove-orphans >/dev/null 2>&1 || true
    rm -rf -- "$install_root"
}
trap cleanup EXIT
cp -R deploy htdocs scripts tests LICENSE .dockerignore "$install_root/"
rm -f "$install_root/deploy/.env"
python3 - "$install_root/deploy/compose.yaml" <<'PY'
from pathlib import Path
import sys
p = Path(sys.argv[1])
p.write_text(p.read_text().replace("    ports: ['127.0.0.1:8088:80']\n", ''))
PY
python3 "$install_root/scripts/init-install-env.py" --company-name 'SYNTHETIC INSTALLATION ONLY'
"${compose[@]}" config --quiet
"${compose[@]}" up -d --build
wait_ready() {
    for attempt in $(seq 1 120); do
        if "${compose[@]}" exec -T web sh -c 'test -f /var/www/documents/install.lock && curl -fsS -H "X-Forwarded-Proto: https" http://localhost/index.php >/dev/null' 2>/dev/null; then
            return 0
        fi
        sleep 2
    done
    echo 'Installation did not become ready.' >&2
    return 1
}
wait_ready
"${compose[@]}" exec -T -e NORWAY_EXPECT_EMPTY=1 web php < tests/deployment-smoke.php
"${compose[@]}" up -d --force-recreate web
wait_ready
"${compose[@]}" exec -T -e NORWAY_EXPECT_EMPTY=1 web php < tests/deployment-smoke.php
