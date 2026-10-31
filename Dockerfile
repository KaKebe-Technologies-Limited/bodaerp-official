FROM php:8.2-apache

# Apache: mod_rewrite for .htaccess routing (/pages, /assets -> packages/frontend)
RUN a2enmod rewrite headers \
    && sed -ri '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# PHP extensions — the app talks to MySQL/MariaDB through mysqli
RUN docker-php-ext-install mysqli

WORKDIR /var/www/html
COPY . /var/www/html/

RUN chmod +x docker/entrypoint.sh \
    && mkdir -p uploads/riders \
    && chown -R www-data:www-data /var/www/html

EXPOSE 80

ENTRYPOINT ["/var/www/html/docker/entrypoint.sh"]
CMD ["apache2-foreground"]
