# syntax=docker/dockerfile:1

# -------------------------
# 1) Build PHP vendor (composer)
# -------------------------
FROM php:8.2-cli-alpine AS vendor
WORKDIR /app

RUN apk add --no-cache git unzip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-progress --no-scripts --ignore-platform-reqs

# -------------------------
# 2) Build frontend assets (npm)
# -------------------------
FROM node:20-alpine AS node_builder
WORKDIR /app

COPY package*.json ./
RUN if [ -f package-lock.json ]; then npm ci --silent; else npm install --silent; fi

COPY . .
RUN npm run build --if-present

# -------------------------
# 3) Production image
# -------------------------
FROM php:8.2-fpm AS production

ENV APP_ENV=production
WORKDIR /var/www/html

# Install system dependencies and PHP extensions
RUN apt-get update \
 && apt-get install -y --no-install-recommends \
    git curl ca-certificates unzip zip libzip-dev \
    libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    libonig-dev libicu-dev sqlite3 libsqlite3-dev nginx \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j"$(nproc)" gd pdo pdo_mysql pdo_sqlite mbstring bcmath intl zip opcache \
 && pecl install redis || true \
 && docker-php-ext-enable redis || true \
 && rm -rf /var/lib/apt/lists/*

# Copy application code
COPY . .

# Copy built vendor files from composer stage
COPY --from=vendor /app/vendor ./vendor

# Copy built frontend assets from node stage
COPY --from=node_builder /app/public ./public

# Copy Docker configuration files
COPY docker/nginx.conf /etc/nginx/sites-enabled/default
COPY docker/php.ini /usr/local/etc/php/conf.d/app.ini

# Add entrypoint script
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Ensure storage and bootstrap directories exist with proper permissions
RUN mkdir -p /var/www/html/storage /var/www/html/bootstrap/cache \
 && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
 && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Render injects $PORT (default 10000) or standard HTTP (80)
EXPOSE 80 10000

ENTRYPOINT ["/entrypoint.sh"]
