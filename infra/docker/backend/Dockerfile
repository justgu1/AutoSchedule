FROM php:8.5-fpm-alpine

WORKDIR /var/www/html

RUN apk add --no-cache \
        postgresql-libs \
        libpng \
        libjpeg-turbo \
        libwebp \
        freetype \
    && apk add --no-cache --virtual .build-deps \
        postgresql-dev \
        libzip-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        libwebp-dev \
        freetype-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    # opcache não entra aqui: a partir do PHP 8.5 já vem compilada na imagem oficial.
    && docker-php-ext-install \
        pdo_pgsql \
        gd \
    && apk del .build-deps

COPY infra/docker/backend/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY infra/docker/backend/fpm-pool.conf /usr/local/etc/php-fpm.d/zz-app.conf

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
