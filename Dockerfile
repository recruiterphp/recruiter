ARG PHP_VERSION

FROM php:$PHP_VERSION-cli AS base

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libssl-dev \
    libcurl4-openssl-dev \
    pkg-config \
    && rm -rf /var/lib/apt/lists/*

RUN pecl install mongodb \
    && docker-php-ext-enable mongodb \
    && docker-php-ext-install -j$(nproc) \
        bcmath \
        pdo_mysql \
        opcache \
        pcntl

# Copy Composer from official image
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /app

CMD ["tail", "-f", "/dev/null"]

FROM base AS ci

COPY composer.json composer.lock* ./

RUN composer install --optimize-autoloader

COPY . .
