#!/usr/bin/env bash

# NotifyDesk Pilot Edition - Setup and Execution Script
# Automated environment setup, migration, seeding, and local server startup.

set -e

echo "=========================================="
echo "  NotifyDesk Pilot Edition - Startup"
echo "=========================================="

# 1. Verify PHP availability
if ! command -v php >/dev/null 2>&1; then
    echo "[ERROR] PHP is not installed or not in PATH."
    exit 1
fi

PHP_VERSION=$(php -r 'echo PHP_VERSION;')
echo "[OK] PHP detected: ${PHP_VERSION}"

# 2. Environment file check
if [ ! -f ".env" ]; then
    if [ -f ".env.example" ]; then
        echo "[INFO] Creating .env from .env.example..."
        cp .env.example .env
        php artisan key:generate --ansi
    else
        echo "[ERROR] .env.example not found."
        exit 1
    fi
else
    echo "[OK] .env configuration file exists."
fi

# Ensure APP_KEY exists
if ! grep -q "^APP_KEY=base64:" .env; then
    echo "[INFO] Generating application key..."
    php artisan key:generate --ansi
fi

# 3. SQLite database file check
mkdir -p database
if [ ! -f "database/database.sqlite" ]; then
    echo "[INFO] Creating database/database.sqlite..."
    touch database/database.sqlite
else
    echo "[OK] SQLite database file exists."
fi

# 4. Composer dependencies check
if [ ! -d "vendor" ]; then
    if command -v composer >/dev/null 2>&1; then
        echo "[INFO] Installing Composer dependencies..."
        composer install --no-interaction --prefer-dist
    else
        echo "[ERROR] vendor directory is missing and composer is not available in PATH."
        exit 1
    fi
else
    echo "[OK] Vendor dependencies present."
fi

# 5. Database migrations (Additive, NEVER fresh)
echo "[INFO] Running database migrations..."
php artisan migrate --force

# 6. Database seeding check (Seed only if users table is empty)
USER_COUNT=$(php -r '
    require __DIR__ . "/vendor/autoload.php";
    $app = require_once __DIR__ . "/bootstrap/app.php";
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();
    try {
        echo \Illuminate\Support\Facades\DB::table("users")->count();
    } catch (\Throwable $e) {
        echo "0";
    }
' 2>/dev/null || echo "0")

if [ "$USER_COUNT" -eq "0" ]; then
    echo "[INFO] Database is unseeded. Running seeders..."
    php artisan db:seed --force
    echo "[OK] Seeded demo accounts (Ahmad & Khalid)."
else
    echo "[OK] Database already seeded (${USER_COUNT} user(s) found)."
fi

# 7. Start the local server
PORT="${PORT:-8000}"
HOST="${HOST:-127.0.0.1}"

echo "=========================================="
echo "  NotifyDesk is ready!"
echo "  Starting server at http://${HOST}:${PORT}"
echo "=========================================="

php artisan serve --host="${HOST}" --port="${PORT}"
