# ==========================================
# Stage 1: Builder (compile dependencies & assets)
# ==========================================
FROM php:8.3-apache AS builder

ARG APP_ENV=production

# Install build dependencies
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
    nodejs \
    npm \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    pdo pdo_mysql pdo_pgsql pgsql mbstring exif pcntl bcmath gd zip intl opcache

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Copy dependency files FIRST for better layer caching
COPY composer.json composer.lock package.json* ./

# Install PHP dependencies (production only)
RUN composer install \
    --no-interaction \
    --no-plugins \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader \
    --no-dev

# Install npm dependencies
RUN npm install --no-audit

# Copy application files AFTER dependencies are installed
COPY . .

# Build frontend assets
RUN npm run build

# ==========================================
# Stage 2: Runtime (minimal production image)
# ==========================================
FROM php:8.3-apache

ARG APP_ENV=production

ENV APP_ENV=${APP_ENV} \
    APP_DEBUG=false \
    COMPOSER_ALLOW_SUPERUSER=1

# Install only runtime dependencies
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libonig-dev \
    libxml2-dev \
    libicu-dev \
    libpq-dev \
    zip \
    curl \
    supervisor \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    pdo pdo_mysql pdo_pgsql pgsql mbstring exif pcntl bcmath gd zip intl opcache

# Enable Apache modules
RUN a2enmod rewrite headers

# Configure Apache
ENV APACHE_DOCUMENT_ROOT=/var/www/public
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf \
    && sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && echo "<Directory ${APACHE_DOCUMENT_ROOT}>" >> /etc/apache2/sites-available/000-default.conf \
    && echo "    AllowOverride All" >> /etc/apache2/sites-available/000-default.conf \
    && echo "    Require all granted" >> /etc/apache2/sites-available/000-default.conf \
    && echo "</Directory>" >> /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www

# Copy built artifacts from builder stage
COPY --from=builder /var/www/vendor ./vendor
COPY --from=builder /var/www/public/build ./public/build

# Copy application files
COPY . .

# Regenerate autoloader to ensure all classes are discovered
RUN php /var/www/vendor/bin/composer dump-autoload --classmap-authoritative

# Create Laravel storage structure
RUN mkdir -p /var/www/storage/framework/cache \
    /var/www/storage/framework/sessions \
    /var/www/storage/framework/views \
    /var/www/storage/logs \
    /var/www/storage/app/public \
    && chown -R www-data:www-data /var/www \
    && chmod -R 775 /var/www/storage \
    && chmod -R 775 /var/www/bootstrap/cache \
    && chmod -R 755 /var/www/public

# Create storage link
RUN ln -srf /var/www/storage/app/public /var/www/public/storage

# Copy supervisor configuration
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Create supervisor log directory
RUN mkdir -p /var/log/supervisor \
    && mkdir -p /var/www/storage/logs \
    && chown -R www-data:www-data /var/www/storage/logs

# Optimize Laravel for production
RUN php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache

EXPOSE 80

CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
