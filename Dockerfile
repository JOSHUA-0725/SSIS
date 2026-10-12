FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip git \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install pdo_mysql opcache \
    && a2enmod rewrite headers

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Serve Laravel's public folder and let its .htaccess rewrite to index.php.
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri "s!/var/www/html!${APACHE_DOCUMENT_ROOT}!g" /etc/apache2/sites-available/*.conf \
    && printf '<Directory %s>\n    AllowOverride All\n    Require all granted\n</Directory>\nServerName localhost\n' "${APACHE_DOCUMENT_ROOT}" > /etc/apache2/conf-available/laravel.conf \
    && a2enconf laravel

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . .
RUN composer dump-autoload --optimize --no-dev \
    && sed -i 's/\r$//' docker/start.sh \
    && chmod +x docker/start.sh \
    && mkdir -p storage/app storage/logs storage/framework/cache/data storage/framework/sessions storage/framework/views bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 10000
CMD ["docker/start.sh"]
