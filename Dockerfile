# syntax=docker/dockerfile:1

# -------------------------
# 1) Build PHP vendor (composer)
# -------------------------
FROM composer:2 AS vendor
WORKDIR /app

# copy only composer files first for build cache
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-progress --no-scripts


# -------------------------
# 2) Build frontend assets (npm)
# -------------------------
FROM node:20 AS node_builder
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

# Install system deps and PHP extensions
RUN apt-get update \
 && apt-get install -y --no-install-recommends \
    git curl ca-certificates unzip zip libzip-dev \
    libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    libonig-dev libicu-dev nginx supervisor \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j"$(nproc)" gd pdo pdo_mysql mbstring bcmath intl zip opcache \
 && pecl install redis || true \
 && docker-php-ext-enable redis || true \
 && rm -rf /var/lib/apt/lists/*

# Copy application code
COPY . .

# Copy built vendor files
COPY --from=vendor /app/vendor ./vendor

# Copy built frontend assets
COPY --from=node_builder /app/public ./public

# Copy config files
COPY docker/nginx.conf /etc/nginx/sites-enabled/default
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/app.ini

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
