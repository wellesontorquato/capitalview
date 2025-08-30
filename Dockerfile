# -------- Stage 1: Composer deps --------
FROM php:8.2-fpm-alpine AS composer_build

RUN apk add --no-cache git unzip libzip-dev oniguruma-dev icu-dev && \
    docker-php-ext-install pdo pdo_mysql mbstring zip intl

WORKDIR /app

# Copia apenas composer.* primeiro (cache eficiente)
COPY composer.json composer.lock ./
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
 && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
 && rm composer-setup.php \
 && composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

# -------- Stage 2: Node build (Vite) --------
FROM node:20-alpine AS node_build
WORKDIR /app

# Copia package.json/yarn.lock/pnpm-lock etc. (ajuste se usar yarn/pnpm)
COPY package*.json ./
RUN npm ci

# Agora copia de fato os assets (inclui resources/)
COPY resources ./resources
COPY vite.config.* ./
COPY tailwind.config.* ./
COPY postcss.config.* ./
# Se você tiver qualquer coisa em public/ que o build precise ler (raramente)
# COPY public ./public

RUN npm run build

# -------- Stage 3: Runtime (PHP-FPM + Nginx + Supervisor) --------
FROM php:8.2-fpm-alpine

# Dependências do sistema e Nginx + Supervisor
RUN apk add --no-cache nginx supervisor bash curl libzip-dev oniguruma-dev icu-dev tzdata && \
    docker-php-ext-install pdo pdo_mysql mbstring zip intl && \
    mkdir -p /run/nginx /var/log/supervisor

WORKDIR /var/www/html

# Copia app inteiro (menos o que o .dockerignore tirou)
COPY . .

# Copia vendor do stage Composer
COPY --from=composer_build /app/vendor ./vendor

# Copia build do Vite (assets prontos)
COPY --from=node_build /app/resources ./resources
COPY --from=node_build /app/dist ./public/build

# Permissões
RUN chown -R www-data:www-data storage bootstrap/cache && \
    chmod -R 775 storage bootstrap/cache

# Otimizações Laravel (se APP_KEY já estiver setado no Railway)
# Não falha se APP_KEY ainda não existir
RUN php -r "file_exists('.env') || copy('.env.example', '.env');" || true

# Nginx + Supervisor
COPY ./deploy/nginx.conf /etc/nginx/nginx.conf
COPY ./deploy/supervisord.conf /etc/supervisord.conf

# Exponha a porta que o Railway usa dinamicamente via $PORT (Nginx ouvirá nela)
ENV PORT=8080
ENV NGINX_PORT=8080

# Substitui porta no nginx.conf em runtime (garante uso do $PORT do Railway)
CMD sed -i "s/NGINX_PORT/${PORT}/g" /etc/nginx/nginx.conf && \
    php artisan config:cache || true && \
    php artisan route:cache || true && \
    /usr/bin/supervisord -c /etc/supervisord.conf
