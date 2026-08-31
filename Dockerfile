# Dockerfile for Laravel 12 Application
# Multi-stage build for production optimization

# Stage 1: Build frontend assets
FROM node:20-alpine AS frontend-build
WORKDIR /app

# Copy package files
COPY package.json package-lock.json* ./

# Install dependencies
RUN npm ci

# Copy source files
COPY vite.config.js ./
COPY resources/ ./resources/

# Build frontend assets
RUN npm run build

# Stage 2: Build PHP dependencies
FROM composer:2 AS vendor-build
WORKDIR /app

# Copy composer files
COPY composer.json composer.lock* ./

# Install production dependencies (no dev)
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --optimize-autoloader

# Stage 3: Production image
FROM php:8.5-fpm-alpine AS production

# Install system dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    mysql-client \
    libpng-dev \
    libzip-dev \
    zip \
    unzip \
    curl \
    oniguruma-dev \
    libxml2-dev \
    freetype-dev \
    libjpeg-turbo-dev \
    linux-headers

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        intl \
        xml \
        opcache

# Configure PHP for production
COPY docker/php/php.ini-production /usr/local/etc/php/php.ini
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

# Create non-root user
RUN addgroup -g 1000 -S www && \
    adduser -u 1000 -S www -G www

# Set working directory
WORKDIR /var/www/html

# Copy vendor from build stage
COPY --from=vendor-build --chown=www:www /app/vendor ./vendor

# Copy frontend build from build stage
COPY --from=frontend-build --chown=www:www /app/public/build ./public/build

# Copy application files
COPY --chown=www:www . .

# Install composer for runtime scripts
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy Docker entrypoint and scripts
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
COPY docker/wait-for-db.sh /usr/local/bin/wait-for-db.sh
COPY docker/healthcheck.sh /usr/local/bin/healthcheck.sh
RUN chmod +x /usr/local/bin/entrypoint.sh /usr/local/bin/wait-for-db.sh /usr/local/bin/healthcheck.sh

# Copy Nginx configuration
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf

# Copy Supervisor configuration
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Set permissions
RUN chown -R www:www /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# Create storage directories
RUN mkdir -p /var/www/html/storage/app/public \
    /var/www/html/storage/framework/cache \
    /var/www/html/storage/framework/sessions \
    /var/www/html/storage/framework/testing \
    /var/www/html/storage/framework/views \
    /var/www/html/storage/logs \
    /var/www/html/bootstrap/cache

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD /usr/local/bin/healthcheck.sh

# Expose port
EXPOSE 8080

# Switch to non-root user
USER www

# Entrypoint
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

# Default command
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
