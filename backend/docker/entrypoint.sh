#!/bin/sh
set -e

# Marca de "pronto" para o healthcheck; recriada depois das migrations
rm -f var/.ready

if [ "$APP_ENV" != "prod" ] && [ ! -f vendor/autoload_runtime.php ]; then
    echo "Instalando dependências do composer..."
    composer install --no-interaction --prefer-dist --no-progress
fi

echo "Aguardando o PostgreSQL..."
until php bin/console dbal:run-sql -q "SELECT 1" >/dev/null 2>&1; do
    sleep 2
done

if [ "${RUN_MIGRATIONS:-0}" = "1" ]; then
    php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
    mkdir -p var && touch var/.ready
fi

exec docker-php-entrypoint "$@"
