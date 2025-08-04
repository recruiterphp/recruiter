FROM php:8.4-cli AS base

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libssl-dev \
    libcurl4-openssl-dev \
    pkg-config \
    && rm -rf /var/lib/apt/lists/*

# Install MongoDB extension
RUN pecl install mongodb \
    && docker-php-ext-enable mongodb \
    && docker-php-ext-install -j$(nproc) \
        bcmath \
        pdo_mysql \
        opcache \
        pcntl

# Copy Composer from official image
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

FROM base AS code

# Set environment variable for Composer
ENV COMPOSER_ALLOW_SUPERUSER=1

# Copy composer files
COPY composer.json composer.lock* ./

# Install dependencies including dev dependencies for testing
RUN composer install --optimize-autoloader

# Copy application code
COPY . .

CMD ["tail", "-f", "/dev/null"]
