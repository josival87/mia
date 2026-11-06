#!/bin/sh
set -e

# No Railway cada servico usa a mesma imagem; MIA_ROLE escolhe o processo.
case "$MIA_ROLE" in
  worker) exec php artisan queue:work --sleep=1 --tries=1 --timeout=120 --max-time=3600 ;;
  scheduler) exec php artisan schedule:work ;;
esac

if [ -z "$APP_KEY" ]; then
  echo "APP_KEY ausente. Gere uma com: php artisan key:generate --show" >&2
  exit 1
fi

echo "Aguardando o PostgreSQL e executando migrações..."
until php artisan migrate --force; do
  sleep 2
done

if [ "$APP_ENV" = "production" ]; then
  # Em produção: só categorias, menus e admin (sem cliente demo). Exige MIA_ADMIN_PASSWORD forte.
  php artisan db:seed --force
  php artisan optimize
else
  php artisan db:seed --force
  php artisan optimize:clear
fi

php artisan mia:telegram-sync --webhook-only || true

exec frankenphp php-server --listen ":${PORT:-8000}" --root public/
