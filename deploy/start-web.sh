#!/bin/sh
# The upstream image declares custom as a volume. Refresh our shipped module
# before starting PHP, while preserving other modules and existing custom files.
set -eu
module_source=/opt/norway-module
module_target=/var/www/html/custom/norway
test -f "$module_source/core/modules/modNorway.class.php"
mkdir -p "$module_target"
cp -a "$module_source/." "$module_target/"
exec /usr/local/bin/docker-run.sh "$@"
