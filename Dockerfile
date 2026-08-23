FROM php:8.4-fpm-alpine

RUN apk add --no-cache icu-dev libpq-dev git unzip \
    && docker-php-ext-install intl pdo_pgsql \
    && rm -rf /tmp/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
