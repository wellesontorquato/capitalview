# -------- Stage 1: Composer deps --------
FROM php:8.2-fpm-alpine AS composer_build

ENV COMPOSER_ALLOW_SUPERUSER=1

# Libs p/ extensões PHP (inclui GD com jpeg+freetype+webp)
RUN apk add --no-cache \
    git unzip curl \
    libzip-dev oniguruma-dev icu-dev \
    libpng-dev libjpeg-turbo-dev freetype-dev libwebp-dev

# Extensões necessárias (GD com WebP)
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp && \
    docker-php-ext-install pdo pdo_mysql mbstring zip intl gd

WORKDIR /app

# Copia apenas composer.* primeiro (cache eficiente)
COPY composer.json composer.lock ./

# Instala o Composer e deps (sem scripts)
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
 && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
 && rm composer-setup.php \
 && composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

# -------- Stage 2: Node build (Vite) --------
FROM node:20-alpine AS node_build
WORKDIR /app

# Copia package.json / lock
COPY package*.json ./
RUN npm ci || npm install

# Vite escreve em public/build — precisamos da pasta public
COPY public ./public

# Agora os assets
COPY resources ./resources
COPY vite.config.* ./
COPY tailwind.config.* ./
COPY postcss.config.* ./

RUN npm run build

# -------- Stage 3: Runtime (PHP-FPM + Nginx + Supervisor) --------
FROM php:8.2-fpm-alpine

# Dependências do sistema e Nginx + Supervisor
# + fontes TTF para o Dompdf (DejaVu)
RUN apk add --no-cache \
    nginx supervisor bash curl tzdata \
    libzip-dev oniguruma-dev icu-dev \
    libpng-dev libjpeg-turbo-dev freetype-dev libwebp-dev \
    ttf-dejavu

# Extensões PHP (GD com WebP) + diretórios de runtime
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp && \
    docker-php-ext-install pdo pdo_mysql mbstring zip intl gd && \
    mkdir -p /run/nginx /var/log/supervisor

WORKDIR /var/www/html

# Copia app inteiro (respeita .dockerignore)
COPY . .

# Copia vendor do stage Composer
COPY --from=composer_build /app/vendor ./vendor

# Copia build do Vite
COPY --from=node_build /app/resources ./resources
COPY --from=node_build /app/public/build ./public/build

# Permissões para cache do Laravel
RUN chown -R www-data:www-data storage bootstrap/cache && \
    chmod -R 775 storage bootstrap/cache

# Gera .env se não existir (não falha sem APP_KEY)
RUN php -r "file_exists('.env') || copy('.env.example', '.env');" || true

# Nginx + Supervisor
COPY ./deploy/nginx.conf /etc/nginx/nginx.conf
COPY ./deploy/supervisord.conf /etc/supervisord.conf

# Porta do serviço (Nginx deve escutar nela)
ENV PORT=8080

# Boot do app
CMD php artisan package:discover || true && \
    php artisan config:cache || true && \
    php artisan route:cache || true && \
    /usr/bin/supervisord -c /etc/supervisord.conf
