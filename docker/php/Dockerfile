FROM php:8.5-fpm-alpine

ARG UID=1000
ARG GID=1000

WORKDIR /var/www/html

RUN apk add --no-cache \
    bash \
    curl \
    git \
    unzip \
    icu-dev \
    libzip-dev \
    oniguruma-dev \
    linux-headers \
    $PHPIZE_DEPS

RUN docker-php-ext-install \
    pdo \
    pdo_mysql \
    mbstring \
    intl \
    zip \
    pcntl \
    sockets

RUN pecl install redis \
    && docker-php-ext-enable redis

COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/99-vanguard-uploads.ini

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

RUN addgroup -g ${GID} vanguard \
    && adduser -D -G vanguard -u ${UID} vanguard

USER vanguard

CMD ["php-fpm"]
