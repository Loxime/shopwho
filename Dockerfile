FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
        icu-dev \
        libpq-dev \
        libzip-dev \
        oniguruma-dev \
        libxml2-dev \
        git \
        unzip \
    && docker-php-ext-install \
        intl \
        mbstring \
        pdo_pgsql \
        zip \
        dom \
        xml \
        xmlwriter \
    && git config --global --add safe.directory /var/www/html \
    && rm -rf /tmp/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/shopwho-uploads.ini

WORKDIR /var/www/html
