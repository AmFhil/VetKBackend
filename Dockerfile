
### Dockerfile (place at project root)
# syntax=docker/dockerfile:1


# -------------------------
# 1) Build PHP vendor (composer)
# -------------------------
FROM composer:2 AS vendor
WORKDIR /app
# copy only composer files first for build cache
COPY composer.json composer.lock ./
# install without running project composer scripts (safer in CI/image build)
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-progress --no-scripts


# -------------------------
# 2) Build frontend assets (npm)
# -------------------------
FROM node:20 AS node_builder
WORKDIR /app
COPY package*.json ./
# use npm ci if lockfile exists (reproducible installs)
RUN if [ -f package-lock.json ]; then npm ci --silent; else npm install --silent; fi
# copy source and build (vite/webpack/etc.). --if-present keeps it safe if no build script
COPY . .
RUN npm run build --if-present


# -------------------------
# 3) Production image
# -------------------------
FROM php:8.2-fpm AS production


ENV APP_ENV=production
WORKDIR /var/www/html


# Install system deps and PHP extensions (combine to keep layers small)
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
COPY
