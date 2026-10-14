#!/usr/bin/env bash
# Verify a fresh installation and a container replacement without sample data.
set -euo pipefail
cd "$(dirname "$0")/.."
install_root=$(mktemp -d /tmp/norway-install-ci.XXXXXXXX)
compose=(docker compose --env-file "$install_root/deploy/.env" -f "$install_root/deploy/compose.yaml" -p "norway-install-ci-$$")
cleanup() {
    "${compose[@]}" down --volumes --remove-orphans >/dev/null 2>&1 || true
    # Old named volumes intentionally survive migration; remove only this test's volumes.
    mapfile -t old_volumes < <(docker volume ls -q --filter "label=com.docker.compose.project=norway-install-ci-$$")
    if (( ${#old_volumes[@]} )); then docker volume rm "${old_volumes[@]}" >/dev/null; fi
    if [[ -d "$install_root/data" ]]; then
        docker run --rm --network none --mount "type=bind,src=$install_root/data,dst=/cleanup" \
            --entrypoint sh "norway-install-ci-$$-web" -c 'find /cleanup -mindepth 1 -delete' >/dev/null 2>&1 || true
    fi
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
bind_config = p.read_text().replace("    ports: ['127.0.0.1:8088:80']\n", '')
(p.parent/'compose.bind.yaml').write_text(bind_config)
legacy = bind_config.replace('../data/database:', 'db:').replace('../data/documents:', 'documents:').replace('../data/config:', 'conf:')
legacy = legacy.replace('  web:\n', '  web:\n    entrypoint: [docker-run.sh]\n    command: [apache2-foreground]\n')
p.write_text(legacy + '\nvolumes:\n  db:\n  documents:\n  conf:\n')
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

# Install the complete Norwegian chart explicitly, then verify a repeated run.
"${compose[@]}" exec -T web php < scripts/install-chart-profile.php
"${compose[@]}" exec -T web php < scripts/install-chart-profile.php

"${compose[@]}" exec -T -e NORWAY_EXPECT_EMPTY=1 -e NORWAY_TEST_DEFAULTS=1 -e NORWAY_TEST_JOURNALS=1 -e NORWAY_TEST_BANK_DEFAULTS=1 -e NORWAY_TEST_REGISTRATION=1 web php < tests/deployment-smoke.php

# Exercise backup and recovery using synthetic data, never the interactive volumes.
cd "$install_root"
export NORWAY_COMPOSE_PROJECT="norway-install-ci-$$"
export NORWAY_BACKUP_DIR="$install_root/backups"
"${compose[@]}" exec -T -e NORWAY_EXPECT_EMPTY=1 -e NORWAY_CREATE_SENTINEL=1 web php < tests/storage-sentinel.php
# Reproduce an old image's reused custom volume without the new entrypoint.
"${compose[@]}" exec -T web sh -c 'sed -i s/0.57.0/0.1.0/g /var/www/html/custom/norway/core/modules/modNorway.class.php; mkdir -p /var/www/html/custom/synthetic-other-module; echo SYNTHETIC > /var/www/html/custom/synthetic-other-module/retained'
"${compose[@]}" exec -T web sh -c 'grep -q 0.1.0 /var/www/html/custom/norway/core/modules/modNorway.class.php'
"${compose[@]}" exec -T web php <<'PHPTEST'
<?php
require '/var/www/html/master.inc.php';
if (getenv('DOLI_COMPANY_NAME') !== 'SYNTHETIC INSTALLATION ONLY') { exit(1); }
$names = "'BANK_DISABLE_CHECK_DEPOSIT','MAIN_MULTILANGS','MAIN_MODULE_NORWAY_HOOKS'";
if (!$db->query('DELETE FROM '.MAIN_DB_PREFIX.'const WHERE entity=1 AND name IN ('.$names.')')) {
    throw new RuntimeException('Legacy configuration fixture failed');
}
if (!$db->query("UPDATE ".MAIN_DB_PREFIX."const SET value='[\"/norway/css/legacy.css\"]' WHERE entity=1 AND name='MAIN_MODULE_NORWAY_CSS'")) {
    throw new RuntimeException('Legacy layout registration fixture failed');
}
$db->close();
PHPTEST
cp deploy/compose.bind.yaml deploy/compose.yaml
rm deploy/compose.bind.yaml
git init --quiet -b main
git config user.name 'Dolibarr Norway verification'
git config user.email 'verification@dolibarr-norway.invalid'
git add .
git commit --quiet -m 'Synthetic installation snapshot'
bash scripts/update-installation.sh
python3 scripts/migrate-storage.py
"${compose[@]}" exec -T web sh -c 'cmp -s /opt/norway-module/core/modules/modNorway.class.php /var/www/html/custom/norway/core/modules/modNorway.class.php; test "$(cat /var/www/html/custom/synthetic-other-module/retained)" = SYNTHETIC'
"${compose[@]}" exec -T -e NORWAY_EXPECT_EMPTY=1 web php < tests/storage-sentinel.php
"${compose[@]}" ps -q web | xargs docker inspect | python3 -c '
import json,sys
from pathlib import Path
c=json.load(sys.stdin)[0]
expected={"/var/www/documents": "documents", "/var/www/html/conf": "config"}
for target,name in expected.items():
    m=next(m for m in c["Mounts"] if m["Destination"]==target)
    assert m["Type"]=="bind" and Path(m["Source"])==Path.cwd()/"data"/name
'
test "$(cat .deployed-revision)" = "$(git rev-parse HEAD)"
backup_dir=$(find "$NORWAY_BACKUP_DIR" -mindepth 1 -maxdepth 1 -type d)
test -f "$backup_dir/COMPLETE"
(cd "$backup_dir" && sha256sum -c SHA256SUMS)
"${compose[@]}" exec -T web rm -f /var/www/html/custom/synthetic-other-module/retained
"${compose[@]}" exec -T web php <<'PHPCHART'
<?php
require '/var/www/html/master.inc.php';
if (getenv('DOLI_COMPANY_NAME') !== 'SYNTHETIC INSTALLATION ONLY') { exit(1); }
if (!$db->query('DROP TABLE '.$db->prefix().'norway_tax_evidence')) { exit(1); }
if (!$db->query("UPDATE ".$db->prefix()."accounting_account SET label='SYNTHETIC-CORRUPTED' WHERE fk_pcg_version='SYN-BACKUP' AND account_number='8891'")) { exit(1); }
PHPCHART
web_id=$("${compose[@]}" ps -q web)
"${compose[@]}" stop web
"${compose[@]}" exec -T db sh -c 'export MYSQL_PWD="$MARIADB_ROOT_PASSWORD"; exec mariadb -uroot accounting -e "DROP TABLE llx_const"'
gzip -dc "$backup_dir/database.sql.gz" | "${compose[@]}" exec -T db sh -c 'export MYSQL_PWD="$MARIADB_ROOT_PASSWORD"; exec mariadb -uroot accounting'
"${compose[@]}" run --rm --no-deps --entrypoint sh web -c 'rm -f /var/www/documents/norway-backup-marker'
gzip -dc "$backup_dir/documents.tar.gz" | "${compose[@]}" run --rm -T --no-deps --entrypoint tar web -xf - -C /var/www/documents
gzip -dc "$backup_dir/custom.tar.gz" | docker cp - "$web_id:/var/www/html/custom"
gzip -dc "$backup_dir/conf.tar.gz" | "${compose[@]}" run --rm -T --no-deps --entrypoint tar web -xf - -C /var/www/html/conf
"${compose[@]}" start web
wait_ready
"${compose[@]}" exec -T web grep -qx 'synthetic backup marker' /var/www/documents/norway-backup-marker
"${compose[@]}" exec -T web php < scripts/configure-sales.php
"${compose[@]}" exec -T web php < scripts/verify-installation.php
# Run the negative path from the host; production PHP disables subprocess APIs.
if "${compose[@]}" exec -T -e DOLI_INSTANCE_UNIQUE_ID=SYNTHETIC-INTENTIONAL-MISMATCH web php < scripts/verify-installation.php \
    > "$install_root/verification-failure.stdout" 2> "$install_root/verification-failure.stderr"; then
    echo 'Verification unexpectedly accepted a mismatched instance key.' >&2
    exit 1
else
    verification_status=$?
fi
[[ "$verification_status" == 1 ]]
python3 - "$install_root" <<'PYCODE'
from pathlib import Path
import sys
root = Path(sys.argv[1])
output = (root/'verification-failure.stdout').read_text()
error = (root/'verification-failure.stderr').read_text()
assert 'FAIL instance_key_match\n' in error
assert 'Undefined constant' not in error and 'Fatal error' not in error
assert 'SYNTHETIC-INTENTIONAL-MISMATCH' not in error + output
assert 'PASS core version' not in output
print('PASS stdin verification reports failed checks without fatal errors or credential values')
PYCODE
"${compose[@]}" exec -T -e NORWAY_EXPECT_EMPTY=1 web php < tests/deployment-smoke.php
"${compose[@]}" exec -T -e NORWAY_EXPECT_EMPTY=1 web php < tests/storage-sentinel.php
"${compose[@]}" exec -T web sh -c 'test "$(cat /var/www/html/custom/synthetic-other-module/retained)" = SYNTHETIC'
printf 'PASS backup checksums, database, documents, config and custom recovery\n'
