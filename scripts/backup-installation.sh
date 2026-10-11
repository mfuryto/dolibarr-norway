#!/usr/bin/env bash
# Back up only the persistent installation; never initialise or remove volumes.
set -euo pipefail
cd "$(dirname "$0")/.."
umask 077
backup_parent=${NORWAY_BACKUP_DIR:-../dolibarr-backups}
project=${NORWAY_COMPOSE_PROJECT:-dolibarr-norway-accounting}
compose=(docker compose --env-file deploy/.env -f deploy/compose.yaml -p "$project")
"${compose[@]}" config --quiet
web_id=$("${compose[@]}" ps -a -q web)
db_id=$("${compose[@]}" ps -q db)
[[ -n "$web_id" && -n "$db_id" ]] || { echo 'Existing web and running database required.' >&2; exit 1; }
# Refuse a development installation or an unexpected Compose project.
docker inspect "$web_id" | python3 -c '
import json,sys
c=json.load(sys.stdin)[0]
e=dict(x.split("=",1) for x in c["Config"]["Env"] if "=" in x)
assert e.get("DOLI_DB_NAME")=="accounting", "Unexpected database; backup refused"
assert c["Config"]["Labels"].get("com.docker.compose.project")==sys.argv[1], "Unexpected project"
' "$project"
mkdir -p "$backup_parent"
chmod 700 "$backup_parent"
backup_dir=$(mktemp -d "$backup_parent/$(date -u +%Y%m%dT%H%M%SZ).XXXXXXXX")
was_running=$(docker inspect -f '{{.State.Running}}' "$web_id")
resume() {
    if [[ "$was_running" == true ]]; then
        "${compose[@]}" start web >/dev/null
    fi
}
trap resume EXIT
"${compose[@]}" stop web >/dev/null
# The application is stopped, preventing document/config changes during the snapshot.
"${compose[@]}" exec -T db sh -c 'export MYSQL_PWD="$MARIADB_ROOT_PASSWORD"; exec mariadb-dump -uroot --single-transaction --routines --events --triggers accounting' | gzip > "$backup_dir/database.sql.gz"
docker cp "$web_id:/var/www/documents/." - | gzip > "$backup_dir/documents.tar.gz"
docker cp "$web_id:/var/www/html/conf/." - | gzip > "$backup_dir/conf.tar.gz"
cp deploy/.env "$backup_dir/installation.env"
docker inspect -f '{{.Image}}' "$web_id" > "$backup_dir/web-image-id"
if [[ -f .deployed-revision ]]; then
    cp .deployed-revision "$backup_dir/revision"
else
    printf '%s\n' 'unknown: installation predates deployment tracking' > "$backup_dir/revision"
fi
# Save the actual running image for recovery, not just a mutable image name.
image_id=$(cat "$backup_dir/web-image-id")
docker image save "$image_id" | gzip > "$backup_dir/web-image.tar.gz"
cp deploy/compose.yaml "$backup_dir/compose.yaml"
(cd "$backup_dir" && sha256sum database.sql.gz documents.tar.gz conf.tar.gz installation.env web-image.tar.gz compose.yaml revision web-image-id > SHA256SUMS)
touch "$backup_dir/COMPLETE"
printf 'Backup completed: %s\n' "$backup_dir"
