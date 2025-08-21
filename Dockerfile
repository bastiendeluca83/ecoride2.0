# Étape 1 : Image Apache avec PHP 8.2
FROM php:8.2-apache

# --- Extensions PHP natives ---
RUN docker-php-ext-install mysqli pdo pdo_mysql

# --- Outils nécessaires pour PECL / Composer ---
RUN apt-get update && apt-get install -y --no-install-recommends \
    $PHPIZE_DEPS \
    libssl-dev \
    pkg-config \
    libcurl4-openssl-dev \
    libzip-dev \
    git \
    zip \
    unzip \
 && rm -rf /var/lib/apt/lists/*

# --- Composer ---
RUN curl -sS https://getcomposer.org/installer | php \
 && mv composer.phar /usr/local/bin/composer \
 && chmod +x /usr/local/bin/composer

# --- Extension MongoDB (branche 1.x compatible PHP 8.2) ---
RUN pecl install mongodb-1.19.3 \
 && docker-php-ext-enable mongodb

# --- Apache ---
RUN a2enmod rewrite headers

# --- Dossier de travail ---
WORKDIR /var/www/html

# (Optionnel, si tu veux copier le code au build)
# COPY . /var/www/html
# RUN chown -R www-data:www-data /var/www/html
