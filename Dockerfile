FROM php:8.2-apache

# Apache
RUN a2enmod rewrite headers

# PHP extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql

# PECL MongoDB + deps
RUN apt-get update && apt-get install -y --no-install-recommends \
    $PHPIZE_DEPS libssl-dev pkg-config git unzip \
 && pecl install mongodb \
 && docker-php-ext-enable mongodb \
 && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
