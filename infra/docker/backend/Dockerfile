FROM php:8.4-fpm-alpine

WORKDIR /var/www/html

RUN apk add --no-cache \
        postgresql-libs \
    && apk add --no-cache --virtual .build-deps \
        postgresql-dev \
        libzip-dev \
    && docker-php-ext-install \
        pdo_pgsql \
        opcache \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY backend/composer.json backend/composer.lock* ./

RUN composer install \
    --no-interaction \
    --prefer-dist \
    --no-progress \
    --no-dev

COPY backend/ .

RUN mkdir -p storage/tmp \
    && chown -R www-data:www-data storage

EXPOSE 9000

CMD ["php-fpm"]