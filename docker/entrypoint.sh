#!/bin/sh
set -e

# Docker entrypoint script for Laravel 12 application
# This script initializes the application on container start

echo "=== Laravel Application Entrypoint ==="

# Create necessary directories
mkdir -p /var/www/html/storage/app/public
mkdir -p /var/www/html/storage/framework/cache
mkdir -p /var/www/html/storage/framework/sessions
mkdir -p /var/www/html/storage/framework/testing
mkdir -p /var/www/html/storage/framework/views
mkdir -p /var/www/html/storage/logs
mkdir -p /var/www/html/bootstrap/cache
mkdir -p /var/log/nginx
mkdir -p /var/log/php
mkdir -p /var/log/mysql
mkdir -p /var/log/supervisor

# Set proper permissions
chown -R www:www /var/www/html/storage
chown -R www:www /var/www/html/bootstrap/cache
chmod -R 755 /var/www/html/storage
chmod -R 755 /var/www/html/bootstrap/cache

# Wait for database to be ready
echo "Waiting for database..."
/usr/local/bin/wait-for-db.sh mysql 3306 "${DB_USER:-${DB_USERNAME}}" "${DB_PASSWORD}"

# Generate app key if not set
if [ -z "$APP_KEY" ]; then
    echo "Generating application key..."
    php artisan key:generate --ansi --force
fi

# Run database migrations
echo "Running database migrations..."
php artisan migrate --force --ansi

# Cache configuration for production
if [ "$APP_ENV" = "production" ]; then
    echo "Caching configuration..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache

    # Optimize autoloader
    composer dump-autoload --optimize --no-dev --classmap-authoritative
fi

# Create storage link
echo "Creating storage link..."
php artisan storage:link --force 2>/dev/null || true

# Seed database if needed (only if tables are empty)
echo "Checking if database needs seeding..."
TABLE_COUNT=$(mysql -h"${DB_HOST:-mysql}" -P"${DB_PORT:-3306}" -u"${DB_USERNAME}" -p"${DB_PASSWORD}" -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${DB_DATABASE}';" 2>/dev/null || echo "0")

if [ "$TABLE_COUNT" = "0" ]; then
    echo "Seeding database..."
    php artisan db:seed --force --ansi
fi

echo "=== Application initialization complete ==="

# Execute the main command
exec "$@"
