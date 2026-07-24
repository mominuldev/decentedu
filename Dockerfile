# syntax=docker/dockerfile:1

# ---- Frontend build -------------------------------------------------------
FROM node:24-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources ./resources
COPY vite.config.js tsconfig.json ./
RUN npm run build

# ---- PHP dependencies ------------------------------------------------------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --optimize-autoloader

# ---- Runtime ---------------------------------------------------------------
FROM php:8.3-fpm-alpine AS app
WORKDIR /var/www/html

RUN apk add --no-cache \
        libpng libjpeg-turbo libwebp freetype \
        libzip icu-libs oniguruma \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS libpng-dev libjpeg-turbo-dev libwebp-dev freetype-dev \
        libzip-dev icu-dev oniguruma-dev linux-headers \
    && docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql bcmath gd intl mbstring zip pcntl opcache \
    && pecl install redis && docker-php-ext-enable redis \
    && apk del .build-deps

COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-app.ini
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/99-opcache.ini

COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=frontend /app/public/build ./public/build

RUN addgroup -g 1000 www && adduser -G www -u 1000 -D www \
    && chown -R www:www /var/www/html \
    && mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chown -R www:www storage bootstrap/cache

USER www
EXPOSE 9000
CMD ["php-fpm"]
