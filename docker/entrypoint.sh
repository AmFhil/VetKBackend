#!/bin/sh
set -e

# Dynamically set port for Render ($PORT is passed by Render, defaults to 80)
PORT=${PORT:-80}
echo "Configuring Nginx to listen on port ${PORT}..."
sed -i "s/PORT_NUMBER/${PORT}/g" /etc/nginx/sites-enabled/default

# Ensure storage and bootstrap directories exist
mkdir -p /var/www/html/storage/framework/cache/data \
         /var/www/html/storage/framework/sessions \
         /var/www/html/storage/framework/views \
         /var/www/html/storage/logs \
         /var/www/html/bootstrap/cache

# If SQLite is used and db file doesn't exist, create it
if [ "$DB_CONNECTION" = "sqlite" ]; then
    mkdir -p /var/www/html/database
    if [ ! -f /var/www/html/database/database.sqlite ]; then
        touch /var/www/html/database/database.sqlite
    fi
    chown -R www-data:www-data /var/www/html/database
    chmod -R 775 /var/www/html/database
fi

# Set proper ownership and permissions for Laravel writable directories
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Create storage symlink
php artisan storage:link --force || true

# Run database migrations if enabled
if [ "$RUN_MIGRATIONS" = "true" ] || [ "$RUN_MIGRATIONS" = "1" ]; then
    echo "Running database migrations..."
    php artisan migrate --force || echo "Migration encountered an issue, continuing..."
fi

# Optimize Laravel if in production
if [ "$APP_ENV" = "production" ]; then
    echo "Optimizing application for production..."
    php artisan config:cache || true
    php artisan route:cache || true
    php artisan view:cache || true
fi

# Start PHP-FPM in background
echo "Starting PHP-FPM on 127.0.0.1:9000..."
php-fpm -D

# Start Nginx in foreground to keep container running
echo "Starting Nginx on port ${PORT}..."
exec nginx -g "daemon off;"
