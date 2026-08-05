# ==============================================================================
# RemitIQ Household Service
# Laravel PHP Application Dockerfile
# ==============================================================================


# ------------------------------------------------------------------------------
# Stage 1: Composer Dependencies (production, no dev packages)
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

# Laravel's package auto-discovery normally runs via the
# post-autoload-dump composer script, which --no-scripts
# intentionally skipped above (to control build ordering). Run it
# explicitly here instead, or core framework services like the cache
# and session managers won't be registered in the container.
RUN php artisan package:discover --ansi


# ------------------------------------------------------------------------------
# Stage 2: Composer Dependencies (development, includes dev packages)
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
# Stage 3: PHP Base — shared system deps + extensions
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
        git \
        curl \
    && docker-php-ext-configure gd --with-jpeg --with-freetype \
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

COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini


# ------------------------------------------------------------------------------
# Stage 4: Development
# ------------------------------------------------------------------------------

FROM php-base AS development

# Source is bind-mounted by docker-compose in development (see
# docker-compose.yml's volumes), so COPY here is just a fallback for
# building the image standalone without a mount active.
COPY . .
COPY --from=composer-builder-dev /app/vendor ./vendor

EXPOSE 9000

CMD ["php-fpm"]


# ------------------------------------------------------------------------------
# Stage 5: Production
# ------------------------------------------------------------------------------

FROM php-base AS production

COPY . .
COPY --from=composer-builder /app/vendor ./vendor

RUN chown -R www-data:www-data storage bootstrap/cache

USER www-data

EXPOSE 9000

CMD ["php-fpm"]
