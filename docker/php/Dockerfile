FROM php:8.1-fpm

COPY php.ini /usr/local/etc/php/

RUN apt update \
  && apt install -y postgresql-client libpq-dev zlib1g-dev libzip-dev unzip libpng-dev libjpeg-dev \
  && docker-php-ext-configure gd --with-jpeg \
  && docker-php-ext-install pdo_pgsql zip bcmath gd

RUN curl -sS https://getcomposer.org/installer | php \
  && mv composer.phar /usr/local/bin/composer \
  && composer self-update


WORKDIR /var/www
