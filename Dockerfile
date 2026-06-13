# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# Render deployment image: nginx + php-fpm (PHP 8.1) in a single container,
# managed by supervisord.
# ---------------------------------------------------------------------------
FROM php:8.1-fpm

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        nginx \
        supervisor \
        libpq-dev \
        libzip-dev \
        unzip \
        git \
    && docker-php-ext-install pdo_pgsql zip bcmath \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY src/ ./

RUN composer install --no-dev --optimize-autoloader --no-interaction \
    && composer clear-cache

COPY docker/php/php.ini /usr/local/etc/php/conf.d/app.ini
COPY docker/render/nginx.conf /etc/nginx/sites-available/default
COPY docker/render/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/render/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN chmod +x /usr/local/bin/entrypoint.sh \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 10000

ENTRYPOINT ["entrypoint.sh"]
