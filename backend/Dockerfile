# Build Stage
FROM composer:2.6 AS composer
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

COPY . .
RUN composer dump-autoload --optimize --classmap-authoritative

# Production Stage
FROM php:8.3-fpm-alpine

# Install dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    libpng-dev \
    libjpeg-turbo-dev \
    libwebp-dev \
    freetype-dev \
    icu-dev \
    libzip-dev \
    oniguruma-dev \
    mysql-client \
    redis \
    curl

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        gd \
        intl \
        zip \
        mbstring \
        opcache \
        pcntl \
        bcmath

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# PHP Configuration
COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-custom.ini
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

# Nginx Configuration
COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf

# Supervisor Configuration
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Application
WORKDIR /var/www/html
COPY --from=composer /app /var/www/html
COPY . /var/www/html

# Permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
RUN chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Health Check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/health || exit 1

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
