# syntax=docker/dockerfile:1.7
# Imágenes de producción de la aplicación Laravel.
#
#   docker build --target app -t talentcloud-app .    # PHP-FPM; también corre la cola y el programador
#   docker build --target web -t talentcloud-web .    # Caddy con los archivos estáticos
#
# No lleva .env ni secretos: la configuración llega por variables de entorno (ver deploy/).

ARG PHP_VERSION=8.2

# — Dependencias de PHP (sin las de desarrollo) —
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --no-scripts --prefer-dist --ignore-platform-reqs

# — Estilos y scripts (Vite + Tailwind) —
FROM node:22-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.js tailwind.config.js postcss.config.js ./
COPY resources ./resources
# Tailwind genera las clases que encuentra escritas en app/ y en las vistas de paginación del framework.
COPY app ./app
COPY --from=vendor /app/vendor/laravel/framework/src/Illuminate/Pagination ./vendor/laravel/framework/src/Illuminate/Pagination
RUN npm run build

# — Aplicación: PHP-FPM —
FROM php:${PHP_VERSION}-fpm-bookworm AS app

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_pgsql intl zip gd opcache bcmath \
    && rm -rf /var/lib/apt/lists/*

COPY docker/prod/php.ini /usr/local/etc/php/conf.d/zz-talentcloud.ini
COPY docker/prod/php-fpm.conf /usr/local/etc/php-fpm.d/zz-talentcloud.conf
COPY docker/prod/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint.sh && chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www/html
COPY --from=vendor /usr/bin/composer /usr/bin/composer
COPY --from=vendor /app/vendor ./vendor
COPY . .
COPY --from=frontend /app/public/build ./public/build

RUN composer dump-autoload --no-dev --optimize --classmap-authoritative --no-scripts \
    && php artisan package:discover --ansi \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs storage/app/private storage/app/public \
    && chown -R www-data:www-data storage bootstrap/cache \
    && rm /usr/bin/composer

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]

# — Servidor web: Caddy (HTTPS automático) con los archivos estáticos —
FROM caddy:2-alpine AS web
COPY docker/prod/Caddyfile /etc/caddy/Caddyfile
COPY --from=app /var/www/html/public /var/www/html/public
