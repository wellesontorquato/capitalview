# -------- Stage 1: Composer deps --------
FROM php:8.2-fpm-alpine AS composer_build

ENV COMPOSER_ALLOW_SUPERUSER=1

# libs p/ extensões do PHP (inclui GD com jpeg+freetype)
RUN apk add --no-cache git unzip libzip-dev oniguruma-dev icu-dev \
    libpng-dev libjpeg-turbo-dev freetype-dev

# Habilita extensões necessárias (inclui GD)
RUN docker-php-ext-configure gd --with-freetype --with-jpeg && \
    docker-php-ext-install pdo pdo_mysql mbstring zip intl gd

WORKDIR /app

# Copia apenas composer.* primeiro (cache eficiente)
COPY composer.json composer.lock ./

# Instala o Composer e as dependências, MAS sem rodar scripts (sem artisan aqui)
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
 && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
 && rm composer-setup.php \
 && composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

# -------- Stage 2: Node build (Vite) --------
FROM node:20-alpine AS node_build
WORKDIR /app

# Copia package.json/yarn.lock/pnpm-lock etc.
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
RUN apk add --no-cache nginx supervisor bash curl tzdata \
    libzip-dev oniguruma-dev icu-dev \
    libpng-dev libjpeg-turbo-dev freetype-dev

# Extensões PHP (inclui GD com jpeg+freetype)
RUN docker-php-ext-configure gd --with-freetype --with-jpeg && \
    docker-php-ext-install pdo pdo_mysql mbstring zip intl gd && \
    mkdir -p /run/nginx /var/log/supervisor

WORKDIR /var/www/html

# Copia app inteiro (menos o que o .dockerignore tirou)
COPY . .

# Copia vendor do stage Composer (já instalado sem scripts)
COPY --from=composer_build /app/vendor ./vendor

# Copia build do Vite (assets prontos)
COPY --from=node_build /app/resources ./resources
COPY --from=node_build /app/public/build ./public/build

# Permissões
RUN chown -R www-data:www-data storage bootstrap/cache && \
    chmod -R 775 storage bootstrap/cache

# Gera .env se não existir (não falha sem APP_KEY)
RUN php -r "file_exists('.env') || copy('.env.example', '.env');" || true

# Nginx + Supervisor
COPY ./deploy/nginx.conf /etc/nginx/nginx.conf
COPY ./deploy/supervisord.conf /etc/supervisord.conf

# Porta fixa do serviço
ENV PORT=8080

# Sobe o app (sem sed); artisan só roda quando o app completo já está no estágio final
CMD php artisan package:discover || true && \
    php artisan config:cache || true && \
    php artisan route:cache || true && \
    /usr/bin/supervisord -c /etc/supervisord.conf
