#!/usr/bin/env bash
# Run from a reviewed checkout; code fetching is deliberately outside this script.
set -euo pipefail
cd "$(dirname "$0")/.."
exec 9>.installation-update.lock
flock -n 9 || { echo 'Another installation update is running.' >&2; exit 1; }
[[ "$(git branch --show-current)" == main ]] || { echo 'Server updates require the stable main branch.' >&2; exit 1; }
[[ -f deploy/.env ]] || { echo 'Missing deploy/.env; existing installation required.' >&2; exit 1; }
[[ -z "$(git status --porcelain --untracked-files=normal)" ]] || { echo 'Working tree must be clean.' >&2; exit 1; }
project=${NORWAY_COMPOSE_PROJECT:-dolibarr-norway-accounting}
compose=(docker compose --env-file deploy/.env -f deploy/compose.yaml -p "$project")
"${compose[@]}" config --quiet
# Back up the running image/data before building or replacing any container.
bash scripts/backup-installation.sh
"${compose[@]}" build web
python3 scripts/migrate-storage.py
"${compose[@]}" up -d --wait --wait-timeout 300
# Read-only checks, including retained instance key and secured installer.
# Administrator passwords may have changed since installation: do not log in.
"${compose[@]}" exec -T web php < scripts/verify-installation.php
git rev-parse HEAD > .deployed-revision
printf 'Installation updated to %s\n' "$(cat .deployed-revision)"
"${compose[@]}" ps
