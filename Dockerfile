# ==============================================================================
# RemitIQ Household Service
# Laravel PHP Application Dockerfile
# ==============================================================================


# ------------------------------------------------------------------------------
# Stage 1: Composer Dependencies
# ------------------------------------------------------------------------------

FROM composer:2 AS composer-builder

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --optimize-autoloader \
    --ignore-platform-reqs


# ------------------------------------------------------------------------------
# Stage 2: PHP Runtime
# ------------------------------------------------------------------------------

FROM php:8.3-fpm-alpine AS production


WORKDIR /var/www/html


# ------------------------------------------------------------------------------
# System Dependencies + PHP Extensions
# ------------------------------------------------------------------------------

RUN apk add --no-cache \
        postgresql-dev \
        icu-dev \
        oniguruma-dev \
        libzip-dev \
        unzip \
        git \
        curl \
    && docker-php-ext-install \
        pdo \
        pdo_pgsql \
        intl \
        mbstring \
        bcmath \
        opcache \
        zip


# ------------------------------------------------------------------------------
# PHP Configuration
# ------------------------------------------------------------------------------

COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini


# ------------------------------------------------------------------------------
# Application Source
# ------------------------------------------------------------------------------

COPY . .


# ------------------------------------------------------------------------------
# Composer Vendor
# ------------------------------------------------------------------------------

COPY --from=composer-builder /app/vendor ./vendor


# ------------------------------------------------------------------------------
# Laravel Permissions
# ------------------------------------------------------------------------------

RUN chown -R www-data:www-data \
        storage \
        bootstrap/cache


# ------------------------------------------------------------------------------
# Production Runtime
# ------------------------------------------------------------------------------

USER www-data


EXPOSE 9000


CMD ["php-fpm"]
