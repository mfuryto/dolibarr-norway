#!/bin/sh
# The upstream image declares custom as a volume. Refresh our shipped module
# before starting PHP, while preserving other modules and non-retired custom files.
set -eu
module_source=/opt/norway-module
module_target=/var/www/html/custom/norway
test -f "$module_source/core/modules/modNorway.class.php"
mkdir -p "$module_target"
# Remove only the explicitly retired, module-owned PHP files. Keep other modules
# and non-code files; the updater has already backed up the previous tree.
while IFS= read -r retired; do
    case "$retired" in
        admin/*.php|class/*.php) ;;
        *) echo 'Invalid retired module path' >&2; exit 1 ;;
    esac
    case "$retired" in *..*|/*) echo 'Invalid retired module path' >&2; exit 1 ;; esac
    test ! -e "$module_source/$retired"
    rm -f -- "$module_target/$retired"
done < "$module_source/data/retired-files.txt"
cp -a "$module_source/." "$module_target/"
exec /usr/local/bin/docker-run.sh "$@"
