# -------- Stage 1: Composer deps --------
FROM php:8.2-fpm-alpine AS composer_build

# libs p/ extensões do PHP (inclui GD com jpeg+freetype)
RUN apk add --no-cache git unzip libzip-dev oniguruma-dev icu-dev \
    libpng-dev libjpeg-turbo-dev freetype-dev

# Habilita extensões necessárias (inclui GD)
RUN docker-php-ext-configure gd --with-freetype --with-jpeg && \
    docker-php-ext-install pdo pdo_mysql mbstring zip intl gd

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
# Se você não tiver package-lock.json, troque para `npm install`
RUN npm ci || npm install

COPY public ./public

# Agora copia de fato os assets (inclui resources/)
COPY resources ./resources
COPY vite.config.* ./
COPY tailwind.config.* ./
COPY postcss.config.* ./
# Se o build precisar ler algo de public/, descomente:
# COPY public ./public

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

# Copia vendor do stage Composer
COPY --from=composer_build /app/vendor ./vendor

# Copia build do Vite (assets prontos)
COPY --from=node_build /app/resources ./resources
COPY --from=node_build /app/public/build ./public/build

# Permissões
RUN chown -R www-data:www-data storage bootstrap/cache && \
    chmod -R 775 storage bootstrap/cache

# Otimizações Laravel (não falha se APP_KEY ainda não existir)
RUN php -r "file_exists('.env') || copy('.env.example', '.env');" || true

# Nginx + Supervisor
COPY ./deploy/nginx.conf /etc/nginx/nginx.conf
COPY ./deploy/supervisord.conf /etc/supervisord.conf

# Porta dinâmica do Railway
ENV PORT=8080
ENV NGINX_PORT=8080

# Usa a porta do Railway no Nginx e sobe tudo
CMD sed -i "s/NGINX_PORT/${PORT}/g" /etc/nginx/nginx.conf && \
    php artisan config:cache || true && \
    php artisan route:cache || true && \
    /usr/bin/supervisord -c /etc/supervisord.conf
