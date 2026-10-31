#!/bin/sh
# BodaERP web container entrypoint: initialise the database, then start Apache.
set -e
cd /var/www/html
mkdir -p uploads/riders
chown -R www-data:www-data uploads
php docker/init_db.php
exec "$@"
