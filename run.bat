@echo off
setlocal

cd /d "%~dp0"

:: 1. Check PHP
php -v >nul 2>&1
if %errorlevel% neq 0 (
    echo [ERROR] PHP is not installed or not available in PATH.
    pause
    exit /b 1
)

:: 2. Check vendor
if not exist "vendor\autoload.php" (
    echo [ERROR] vendor\autoload.php not found. Run composer install first.
    pause
    exit /b 1
)

:: 3. Check .env
if not exist ".env" (
    echo [INFO] Creating .env from .env.example...
    copy /y ".env.example" ".env" >nul
    call php artisan key:generate --ansi
)

:: 4. Check SQLite database
if not exist "database" mkdir "database"
if not exist "database\database.sqlite" (
    echo [INFO] Creating database\database.sqlite...
    type nul > "database\database.sqlite"
)

:: 5. Run migrations (safe, non-destructive)
echo [INFO] Running database migrations...
call php artisan migrate --force
if %errorlevel% neq 0 (
    echo [ERROR] Migration failed.
    pause
    exit /b 1
)

:: 6. Seed only if users table is empty
set "SEED_FILE=temp_seed_check.txt"
set "seed_status="
call php -d error_reporting=0 artisan tinker --execute="if (\App\Models\User::count() == 0) { echo 'SEED_NEEDED'; } else { echo 'SEEDED'; }" > "%SEED_FILE%" 2>nul
if exist "%SEED_FILE%" (
    set /p seed_status=<"%SEED_FILE%"
    del "%SEED_FILE%" >nul 2>nul
)

if "%seed_status%"=="SEED_NEEDED" (
    echo [INFO] Seeding database...
    call php artisan db:seed --force
) else (
    echo [OK] Database already seeded.
)

:: 7. Clear caches
echo [INFO] Clearing cache...
call php artisan optimize:clear

:: 8. Check port
netstat -ano | findstr :8000 >nul 2>&1
if %errorlevel% equ 0 (
    echo [WARNING] Port 8000 is already in use.
)

:: 9. Start server in new window
echo [INFO] Starting server...
start "NotifyDesk Pilot Edition Server" cmd /k "php artisan serve --host=127.0.0.1 --port=8000"

:: 10. Open browser
timeout /t 2 /nobreak >nul 2>&1
start http://127.0.0.1:8000/login

:: 11. Final message
echo ===================================================
echo   NotifyDesk Pilot Edition is running!
echo   URL: http://127.0.0.1:8000/login
echo   Ahmad: ahmad@example.com / password
echo   Khalid: khalid@example.com / password
echo ===================================================

endlocal