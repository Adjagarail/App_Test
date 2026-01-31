# Dockerfile (Debian bookworm)
FROM php:8.4-fpm

# Packages système nécessaires
RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip zip curl \
    libpq-dev libzip-dev libicu-dev libxml2-dev \
    libjpeg-dev libpng-dev libfreetype6-dev \
    && rm -rf /var/lib/apt/lists/*

# Extensions PHP (pgsql, intl, zip, gd, opcache)
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
    pdo pdo_pgsql intl zip gd opcache

#RUN pecl install redis \
# && docker-php-ext-enable redis
# Copie une conf de pool pour exposer /status
#COPY docker/php-fpm/www-monitoring.conf /usr/local/etc/php-fpm.d/www-monitoring.conf

# (Optionnel) Redis via PECL
# RUN pecl install redis && docker-php-ext-enable redis

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1

# Répertoire de travail
WORKDIR /var/www/html

# Permissions (à adapter selon ton workflow)
RUN chown -R www-data:www-data /var/www/html

EXPOSE 9000
