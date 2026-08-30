#!/bin/sh
set -e

echo "Aguardando o PostgreSQL e executando migrações..."
until php artisan migrate --force; do
  sleep 2
done

php artisan db:seed --force
php artisan optimize:clear
php artisan mia:telegram-sync --webhook-only || true

exec php artisan serve --host=0.0.0.0 --port=8000
