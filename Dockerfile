FROM php:8.3-fpm

# 1. Install system dependencies & library untuk ekstensi PHP
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    curl \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libonig-dev \
    libxml2-dev \
    libicu-dev \
    zip \
    && rm -rf /var/lib/apt/lists/*

# 2. Install & Configure PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    pdo \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    intl \
    opcache

# 3. Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 4. Set Environment Variables
ENV COMPOSER_ALLOW_SUPERUSER=1
WORKDIR /var/www

# 5. Copy composer files dulu (Optimasi Layer Cache)
COPY composer.json composer.lock ./

# 6. Install Dependencies
# Kita gunakan --ignore-platform-reqs untuk menghindari error jika 
# ada library yang minta ekstensi aneh saat build
RUN composer install \
    --no-interaction \
    --no-plugins \
    --no-scripts \
    --prefer-dist \
    --ignore-platform-reqs

# 7. Copy seluruh source code
COPY . /var/www

# 8. Atur Permissions
# Laravel butuh akses tulis ke storage dan bootstrap/cache
RUN chown -R www-data:www-data /var/www \
    && chmod -R 775 /var/www/storage \
    && chmod -R 775 /var/www/bootstrap/cache

# 9. Finalisasi Autoload
RUN composer dump-autoload --optimize

EXPOSE 9000

CMD ["php-fpm"]