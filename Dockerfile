# ==============================================================================
# RemitIQ Household Service
# Laravel PHP Application Dockerfile
# ==============================================================================


# ------------------------------------------------------------------------------
# Stage 1: Composer Dependencies (production)
# ------------------------------------------------------------------------------

FROM composer:2 AS composer-builder

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --no-scripts \
    --no-autoloader \
    --ignore-platform-reqs

COPY . .

RUN composer dump-autoload --optimize --no-dev

RUN php artisan package:discover --ansi



# ------------------------------------------------------------------------------
# Stage 2: Composer Dependencies (development)
# Includes dev packages:
# - Laravel Pint
# - Pest
# - PHPStan
# ------------------------------------------------------------------------------

FROM composer:2 AS composer-builder-dev

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --no-scripts \
    --no-autoloader \
    --ignore-platform-reqs

COPY . .

RUN composer dump-autoload --optimize

RUN php artisan package:discover --ansi



# ------------------------------------------------------------------------------
# Stage 3: PHP Runtime Base
# Shared PHP extensions only
# No Composer
# No development tools
# ------------------------------------------------------------------------------

FROM php:8.3-fpm-alpine AS php-base

WORKDIR /var/www/html


RUN apk add --no-cache \
        postgresql-dev \
        icu-dev \
        oniguruma-dev \
        libzip-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        unzip \
    && docker-php-ext-configure gd \
        --with-jpeg \
        --with-freetype \
    && docker-php-ext-install \
        pdo \
        pdo_pgsql \
        intl \
        mbstring \
        bcmath \
        opcache \
        zip \
        exif \
        gd


COPY docker/php/php.ini \
    /usr/local/etc/php/conf.d/custom.ini



# ------------------------------------------------------------------------------
# Stage 4: Development Environment
#
# Contains:
# - Composer
# - Git
# - Laravel Pint
# - Testing tools
# ------------------------------------------------------------------------------

FROM php-base AS development


ARG WWWUSER=1000
ARG WWWGROUP=1000


RUN apk add --no-cache \
        git \
        curl


# Install Composer only for development
RUN curl -sS https://getcomposer.org/installer | php \
        -- --install-dir=/usr/local/bin \
        --filename=composer



# Match container user permissions with host user

RUN deluser www-data 2>/dev/null; \
    delgroup www-data 2>/dev/null; \
    addgroup -g ${WWWGROUP} www-data && \
    adduser -D \
        -u ${WWWUSER} \
        -G www-data \
        www-data



COPY . .

COPY --from=composer-builder-dev \
    /app/vendor \
    ./vendor



EXPOSE 9000


CMD ["php-fpm"]




# ------------------------------------------------------------------------------
# Stage 5: Production Environment
#
# Contains:
# - PHP Runtime
# - Application
# - Production dependencies only
#
# Does NOT contain:
# - Composer
# - Git
# - Development tools
# ------------------------------------------------------------------------------

FROM php-base AS production


COPY . .

COPY --from=composer-builder \
    /app/vendor \
    ./vendor



RUN chown -R www-data:www-data \
        storage \
        bootstrap/cache


USER www-data


EXPOSE 9000


CMD ["php-fpm"]
