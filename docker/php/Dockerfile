# PHP + Apache with mod_rewrite and the extensions XAMPP enables by default. PHP_VERSION comes from .env
# (8.5 unless set; 8.2 reproduces XAMPP 8.2.12). SERVER_PROFILE=live (set by deploy/docker-compose.traefik.yml)
# bakes in the production PHP and Apache settings, so a changed config file rebuilds the image on
# docker compose up -d --build instead of hiding behind a stale single-file bind mount.
ARG PHP_VERSION=8.5
FROM php:${PHP_VERSION}-apache
ARG SERVER_PROFILE=laptop

RUN apt-get update \
 && apt-get install -y --no-install-recommends libpng-dev libjpeg62-turbo-dev libfreetype6-dev libzip-dev libicu-dev mariadb-client \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j"$(nproc)" mysqli gd zip intl exif \
 && a2enmod rewrite headers remoteip \
 && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY docker/php/php-dev.ini docker/php/php-prod.ini docker/php/apache-site.conf docker/php/apache-site.prod.conf /usr/local/share/bodaerp/
RUN cd /usr/local/share/bodaerp \
 && if [ "$SERVER_PROFILE" = "live" ]; then ini=php-prod.ini site=apache-site.prod.conf; else ini=php-dev.ini site=apache-site.conf; fi \
 && cp "$ini" /usr/local/etc/php/conf.d/zz-bodaerp.ini \
 && cp "$site" /etc/apache2/sites-available/000-default.conf
COPY docker/php/entrypoint.sh /usr/local/bin/bodaerp-entrypoint
RUN chmod +x /usr/local/bin/bodaerp-entrypoint

WORKDIR /var/www/html/bodaerp
ENTRYPOINT ["bodaerp-entrypoint"]
CMD ["apache2-foreground"]
