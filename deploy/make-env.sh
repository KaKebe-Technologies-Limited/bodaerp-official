#!/bin/sh
# Writes the server's .env once (never overwrites one that exists). Secrets are generated here on the server
# and never printed. Fill SMTP_* and the ioTec / EgoSMS values by hand afterwards (docs/DEPLOYMENT.md).
set -e
cd "$(dirname "$0")/.."
if [ -f .env ]; then echo ".env already exists; nothing changed"; exit 0; fi
umask 077
cat > .env <<ENV
COMPOSE_FILE=docker-compose.yml:deploy/docker-compose.traefik.yml
APP_ENV=live
APP_URL=https://bodaerp.site
DOCKER_DB_ROOT_PASSWORD=$(head -c 32 /dev/urandom | base64 | tr -dc 'A-Za-z0-9' | head -c 32)
PHP_VERSION=8.5
MARIADB_VERSION=11.8
MAIL_FROM=no-reply@bodaerp.site
MAIL_REDIRECT_TO=
SMTP_HOST=
SMTP_PORT=465
SMTP_SECURE=ssl
SMTP_USER=
SMTP_PASS=
IOTEC_MODE=test
ENV
echo ".env written. Add SMTP, ioTec and EgoSMS values, then: docker compose up -d --build"
