FROM php:8.2-apache

# Apache modules utiles (URL rewriting, headers)
RUN a2enmod rewrite headers

# Extensions PHP de base (MySQL)
RUN docker-php-ext-install mysqli pdo pdo_mysql

# PECL MongoDB + dépendances build
# + extension zip (utile pour Composer) + opcache (perf)
RUN apt-get update && apt-get install -y --no-install-recommends \
    $PHPIZE_DEPS libssl-dev pkg-config git unzip libzip-dev \
 && pecl install mongodb \
 && docker-php-ext-enable mongodb \
 && docker-php-ext-install zip opcache \
 && rm -rf /var/lib/apt/lists/*

# Composer (depuis l'image officielle)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Dossier de travail
WORKDIR /var/www/html
