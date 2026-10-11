FROM dolibarr/dolibarr:24.0.1@sha256:638727a1a035515b8f1e9c7694ee25315c4b79d618d4f19878efda977b20d49f
COPY htdocs/custom/norway/ /var/www/html/custom/norway/
COPY LICENSE /var/www/html/custom/norway/LICENSE
COPY deploy/20-norway.php /var/www/scripts/docker-init.d/20-norway.php
COPY scripts/configure-sales.php /var/www/scripts/docker-init.d/30-sales.php
