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
cp -R deploy htdocs scripts tests LICENSE .dockerignore .gitignore compose.yaml "$install_root/"
rm -f "$install_root/deploy/.env"
# Verify default and explicit deployment files resolve to the same configuration.
python3 - "$install_root" <<'PYCONFIG'
import json
import subprocess
import sys
from pathlib import Path
root = Path(sys.argv[1])
base = ['docker', 'compose', '--env-file', str(root/'deploy/.env'), '-p', 'configuration-check']
# Generate settings before comparing; stdout contains no credentials.
subprocess.run(['python3', str(root/'scripts/init-install-env.py'), '--company-name', 'SYNTHETIC INSTALLATION ONLY', '--url', 'https://accounting.example.com'], check=True)
def config(file):
    return json.loads(subprocess.check_output(base + ['-f', str(root/file), 'config', '--format', 'json']))
if config('compose.yaml') != config('deploy/compose.yaml'):
    raise SystemExit('Default and explicit deployment configurations differ')
PYCONFIG
python3 - "$install_root/deploy/compose.yaml" <<'PY'
from pathlib import Path
import sys
p = Path(sys.argv[1])
p.write_text(p.read_text().replace("    ports: ['127.0.0.1:8088:80']\n", ''))
PY
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

# Exercise backup and recovery using synthetic data, never the interactive volumes.
cd "$install_root"
export NORWAY_COMPOSE_PROJECT="norway-install-ci-$$"
export NORWAY_BACKUP_DIR="$install_root/backups"
"${compose[@]}" exec -T web sh -c 'printf "synthetic backup marker\n" > /var/www/documents/norway-backup-marker'
git init --quiet -b main
git config user.name 'Dolibarr Norway verification'
git config user.email 'verification@dolibarr-norway.invalid'
git add .
git commit --quiet -m 'Synthetic installation snapshot'
bash scripts/update-installation.sh
test "$(cat .deployed-revision)" = "$(git rev-parse HEAD)"
backup_dir=$(find "$NORWAY_BACKUP_DIR" -mindepth 1 -maxdepth 1 -type d)
test -f "$backup_dir/COMPLETE"
(cd "$backup_dir" && sha256sum -c SHA256SUMS)
"${compose[@]}" stop web
"${compose[@]}" exec -T db sh -c 'export MYSQL_PWD="$MARIADB_ROOT_PASSWORD"; exec mariadb -uroot accounting -e "DROP TABLE llx_const"'
gzip -dc "$backup_dir/database.sql.gz" | "${compose[@]}" exec -T db sh -c 'export MYSQL_PWD="$MARIADB_ROOT_PASSWORD"; exec mariadb -uroot accounting'
"${compose[@]}" run --rm --no-deps --entrypoint sh web -c 'rm -f /var/www/documents/norway-backup-marker'
gzip -dc "$backup_dir/documents.tar.gz" | "${compose[@]}" run --rm -T --no-deps --entrypoint tar web -xf - -C /var/www/documents
gzip -dc "$backup_dir/conf.tar.gz" | "${compose[@]}" run --rm -T --no-deps --entrypoint tar web -xf - -C /var/www/html/conf
"${compose[@]}" start web
wait_ready
"${compose[@]}" exec -T web grep -qx 'synthetic backup marker' /var/www/documents/norway-backup-marker
"${compose[@]}" exec -T web php < scripts/verify-installation.php
"${compose[@]}" exec -T -e NORWAY_EXPECT_EMPTY=1 web php < tests/deployment-smoke.php
printf 'PASS backup checksums, database and document/config recovery\n'
