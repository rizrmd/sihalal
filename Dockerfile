FROM php:8.3-apache

# 1. Install system dependencies & libraries
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
    libpq-dev \
    zip \
    && rm -rf /var/lib/apt/lists/*

# 2. Install & Configure PHP extensions (including PostgreSQL)
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    pdo \
    pdo_mysql \
    pdo_pgsql \
    pgsql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    intl \
    opcache

# 3. Enable Apache modules
RUN a2enmod rewrite headers

# 4. Configure Apache
# Set DocumentRoot to Laravel's public directory
ENV APACHE_DOCUMENT_ROOT=/var/www/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# 5. Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 6. Set Environment Variables
ENV COMPOSER_ALLOW_SUPERUSER=1
WORKDIR /var/www

# 7. Copy composer files first (Optimize Layer Cache)
COPY composer.json composer.lock ./

# 8. Install Dependencies
RUN composer install \
    --no-interaction \
    --no-plugins \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader \
    --no-dev

# 9. Copy application files
COPY . /var/www

# 10. Set proper permissions
RUN chown -R www-data:www-data /var/www \
    && chmod -R 775 /var/www/storage \
    && chmod -R 775 /var/www/bootstrap/cache \
    && chmod -R 755 /var/www/public

# 11. Create storage link if not exists
RUN if [ ! -L /var/www/public/storage ]; then \
    ln -s /var/www/storage/app/public /var/www/public/storage; \
    fi

# 12. Optimize Laravel for production
RUN php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache

# Expose port 80 for HTTP traffic
EXPOSE 80

# Start Apache in foreground
CMD ["apache2-foreground"]
