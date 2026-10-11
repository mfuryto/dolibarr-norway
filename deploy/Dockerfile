FROM dolibarr/dolibarr:24.0.1@sha256:638727a1a035515b8f1e9c7694ee25315c4b79d618d4f19878efda977b20d49f
COPY htdocs/custom/norway/ /var/www/html/custom/norway/
COPY LICENSE /var/www/html/custom/norway/LICENSE
COPY htdocs/custom/norway/ /opt/norway-module/
COPY LICENSE /opt/norway-module/LICENSE
COPY --chmod=755 deploy/start-web.sh /usr/local/bin/norway-start-web.sh
ENTRYPOINT ["/usr/local/bin/norway-start-web.sh"]
CMD ["apache2-foreground"]
COPY deploy/20-norway.php /var/www/scripts/docker-init.d/20-norway.php
COPY scripts/configure-sales.php /var/www/scripts/docker-init.d/30-sales.php
