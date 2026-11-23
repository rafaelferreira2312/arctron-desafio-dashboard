FROM php:8.3-fpm-alpine AS base

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_pgsql intl amqp opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
ENV COMPOSER_ALLOW_SUPERUSER=1

COPY docker/entrypoint.sh /usr/local/bin/app-entrypoint
RUN chmod +x /usr/local/bin/app-entrypoint
ENTRYPOINT ["app-entrypoint"]
CMD ["php-fpm"]

# Desenvolvimento: o código entra por volume, dependências instaladas no start
FROM base AS dev
COPY docker/php/dev.ini $PHP_INI_DIR/conf.d/zz-app.ini

# Produção: imagem imutável, sem dependências de dev e com cache aquecido
FROM base AS prod
ENV APP_ENV=prod APP_DEBUG=0
COPY docker/php/prod.ini $PHP_INI_DIR/conf.d/zz-app.ini

COPY composer.json composer.lock* ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-progress

COPY . .
RUN composer dump-autoload --no-dev --classmap-authoritative \
    && php bin/console cache:warmup \
    && chown -R www-data:www-data var
