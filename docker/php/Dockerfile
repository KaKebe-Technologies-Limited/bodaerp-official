# Developer A (Linux) only. PHP + Apache with mod_rewrite and the extensions XAMPP enables by
# default. PHP_VERSION comes from .env (8.5 unless set; 8.2 reproduces XAMPP 8.2.12).
ARG PHP_VERSION=8.5
FROM php:${PHP_VERSION}-apache

RUN apt-get update \
 && apt-get install -y --no-install-recommends libpng-dev libjpeg62-turbo-dev libfreetype6-dev libzip-dev libicu-dev mariadb-client \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j"$(nproc)" mysqli gd zip intl exif \
 && a2enmod rewrite headers \
 && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY docker/php/php-dev.ini /usr/local/etc/php/conf.d/zz-bodaerp-dev.ini
COPY docker/php/apache-site.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php/entrypoint.sh /usr/local/bin/bodaerp-entrypoint
RUN chmod +x /usr/local/bin/bodaerp-entrypoint

WORKDIR /var/www/html/bodaerp
ENTRYPOINT ["bodaerp-entrypoint"]
CMD ["apache2-foreground"]
