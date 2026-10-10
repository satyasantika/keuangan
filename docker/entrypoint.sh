#!/bin/sh
# Entrypoint container app/queue/scheduler. Migrasi hanya bila RUN_MIGRATIONS=true (container app).
set -eu

cd /var/www/html

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force --no-interaction
fi

# Cache konfigurasi/route/view/event + komponen Filament & ikon (storage:link TIDAK dipakai: tidak ada berkas publik).
php artisan optimize
php artisan filament:optimize
php artisan icons:cache

exec "$@"
