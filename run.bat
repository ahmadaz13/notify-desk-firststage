@echo off
setlocal

cd /d "%~dp0"

:: 1. Check PHP
php -v >nul 2>&1
if %errorlevel% neq 0 (
    echo [ERROR] PHP is not installed or not in PATH.
    pause
    exit /b 1
)

:: 2. Check vendor autoload
if not exist "vendor\autoload.php" (
    echo [WARN] vendor\autoload.php not found. Running composer install...
    where composer >nul 2>&1
    if %errorlevel% neq 0 (
        echo [ERROR] Composer not found. Please install dependencies manually.
        pause
        exit /b 1
    )
    call composer install
)

:: 3. Check .env
if not exist ".env" (
    echo [INFO] Creating .env from .env.example...
    copy .env.example .env >nul
    call php artisan key:generate
)

:: 4. Check SQLite database
if not exist "database" mkdir "database"
if not exist "database\database.sqlite" (
    echo [INFO] Creating database.sqlite...
    type nul > "database\database.sqlite"
)

:: 5. Run migrations (safe, non-destructive)
echo [INFO] Running migrations...
call php artisan migrate --force
if %errorlevel% neq 0 (
    echo [ERROR] Migration failed.
    pause
    exit /b 1
)

:: 6. Clear caches
echo [INFO] Clearing caches...
call php artisan optimize:clear

:: 7. Start server using PHP built-in server (more reliable than artisan serve on PHP 8.5)
echo [INFO] Starting server on http://127.0.0.1:8080...
start "NotifyDesk Pilot Edition" cmd /k "php -d error_reporting=0 -S 127.0.0.1:8080 -t public"

:: 8. Wait and open browser
timeout /t 2 /nobreak >nul
start http://127.0.0.1:8080/login

echo ===================================================
echo   NotifyDesk Pilot Edition is running!
echo   URL: http://127.0.0.1:8080/login
echo   Ahmad: ahmad@example.com / password
echo   Khalid: khalid@example.com / password
echo ===================================================

endlocal