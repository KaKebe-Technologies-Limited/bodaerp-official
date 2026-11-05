#!/bin/sh
# Waits for MariaDB, applies migrations, seeds demo data once, then starts Apache.
# Every step is idempotent, so restarting the container is always safe.
set -e
cd /var/www/html/bodaerp

i=0
until mariadb-admin ping --skip-ssl -h"${DB_HOST:-db}" -u"${DB_USER:-root}" -p"${DB_PASS}" --silent 2>/dev/null; do
  i=$((i+1))
  if [ "$i" -ge 60 ]; then echo "database not reachable"; exit 1; fi
  sleep 2
done

mkdir -p "${UPLOAD_PATH:-/var/www/html/bodaerp/uploads}" "${STORAGE_PATH:-/var/www/html/bodaerp/storage}"
chown -R www-data:www-data "${UPLOAD_PATH:-/var/www/html/bodaerp/uploads}" "${STORAGE_PATH:-/var/www/html/bodaerp/storage}"

php database/migrate.php
if [ "${SEED_DEMO:-0}" = "1" ]; then
  php database/seed_run.php --if-empty
fi
chown -R www-data:www-data "${STORAGE_PATH:-/var/www/html/bodaerp/storage}"

exec docker-php-entrypoint "$@"
