#!/bin/bash
set -e

echo "[Entrypoint] Initializing NotifyDesk container environment..."

# 1. Wait for MySQL database to be ready (if configured as mysql)
if [ "${DB_CONNECTION}" = "mysql" ]; then
    echo "[Entrypoint] Checking MySQL connectivity at ${DB_HOST:-db}:${DB_PORT:-3306}..."
    until mysqladmin ping -h"${DB_HOST:-db}" -P"${DB_PORT:-3306}" -u"${DB_USERNAME}" -p"${DB_PASSWORD}" --silent > /dev/null 2>&1; do
        echo "[Entrypoint] MySQL is unavailable - waiting 2 seconds..."
        sleep 2
    done
    echo "[Entrypoint] MySQL is up and reachable!"
fi

# 2. Run Database Migrations (if enabled)
if [ "${MIGRATE_ON_START}" = "true" ]; then
    echo "[Entrypoint] Running database migrations..."
    php artisan migrate --force
fi

# 3. Run Seeders (if enabled)
if [ "${SEED_ON_START}" = "true" ]; then
    echo "[Entrypoint] Seeding initial database settings..."
    php artisan db:seed --class=SettingsSeeder --force
fi

# 4. Ensure storage symlink exists
if [ ! -L /var/www/public/storage ]; then
    echo "[Entrypoint] Creating storage symlink..."
    php artisan storage:link || true
fi

# 5. Production Optimization Caching (only for app server, not scheduler/queue)
if [ "$1" = "php-fpm" ]; then
    echo "[Entrypoint] Optimizing Laravel application caches..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
fi

# 6. Ensure runtime directory permissions
mkdir -p /var/www/storage/framework/cache/data \
         /var/www/storage/framework/sessions \
         /var/www/storage/framework/views \
         /var/www/storage/logs \
         /var/www/bootstrap/cache

chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
chmod -R 775 /var/www/storage /var/www/bootstrap/cache

echo "[Entrypoint] Initialization complete. Executing command: $@"
exec "$@"
