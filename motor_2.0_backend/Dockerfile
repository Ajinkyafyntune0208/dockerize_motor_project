FROM php:8.2-fpm

# Install PHP extensions and system dependencies
RUN apt-get update && apt-get install -y \
    build-essential \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip unzip curl git \
    libzip-dev \
    mariadb-client \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Install MongoDB extension (compatible version)
RUN pecl install mongodb-1.15.0 && docker-php-ext-enable mongodb

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www
COPY . .

# Install dependencies (without triggering artisan)
RUN COMPOSER_ALLOW_SUPERUSER=1 composer install \
    --no-interaction --prefer-dist --optimize-autoloader --no-scripts

EXPOSE 9000
