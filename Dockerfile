# -------- Stage 1: Node build (Vite) --------
FROM node:20-alpine AS nodebuild
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY resources ./resources
COPY vite.config.* ./
COPY tailwind.config.* postcss.config.* ./ 2>/dev/null || true
RUN npm run build

# -------- Stage 2: Composer deps --------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-progress --no-interaction --no-scripts

# -------- Stage 3: PHP-FPM runtime --------
FROM php:8.2-fpm-alpine

# Dependências do PHP e extensões
RUN apk add --no-cache \
    bash git curl libzip-dev oniguruma-dev icu-dev libpng-dev libjpeg-turbo-dev freetype-dev \
    mariadb-connector-c-dev \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install pdo_mysql mbstring zip gd intl bcmath opcache

# diretório de trabalho
WORKDIR /var/www/html

# Copia app (menos o que está no .dockerignore)
COPY . .

# Copia vendor do stage Composer
COPY --from=vendor /app/vendor ./vendor

# Copia build do Vite para public/
COPY --from=nodebuild /app/dist ./public/build

# Otimizações Laravel
RUN php artisan storage:link || true \
 && chown -R www-data:www-data storage bootstrap/cache \
 && chmod -R 775 storage bootstrap/cache

# Opcional: configs de produção
# COPY docker/php.ini /usr/local/etc/php/conf.d/app.ini

EXPOSE 9000
CMD ["php-fpm", "-y", "/usr/local/etc/php-fpm.conf", "-R"]
