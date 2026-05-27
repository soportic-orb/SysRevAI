# SysRevAI — PHP-FPM image with the extensions the platform requires.
FROM php:8.3-fpm

# System libs needed to build the PHP extensions.
RUN apt-get update && apt-get install -y --no-install-recommends \
        libicu-dev \
        libzip-dev \
        libpng-dev \
        libjpeg-dev \
        libfreetype6-dev \
        unzip \
        git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql intl zip gd \
    && rm -rf /var/lib/apt/lists/*

# Composer (mounted from the official image).
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Custom PHP settings (upload limits, etc.).
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-sysrevai.ini

WORKDIR /var/www/html
