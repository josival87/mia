# Servidor de produção: FrankenPHP (Caddy + PHP) no modo clássico, no lugar do `php artisan serve`.
FROM dunglas/frankenphp:1-php8.4-bookworm

RUN install-php-extensions pdo_pgsql intl pcntl opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --optimize-autoloader
COPY . .
RUN composer dump-autoload --no-dev --optimize \
    && chmod +x /app/docker/entrypoint.sh \
    && chown -R www-data:www-data storage bootstrap/cache

ENV SERVER_NAME=:8000
EXPOSE 8000
ENTRYPOINT ["/app/docker/entrypoint.sh"]
