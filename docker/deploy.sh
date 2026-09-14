#!/bin/bash
set -e

echo "=========================================================="
echo "      NotifyDesk Automated Production Deployment"
echo "=========================================================="

# 1. Verify that .env exists
if [ ! -f ".env" ]; then
    echo "[-] Error: Production .env file not found!"
    echo "    Please create .env by running: cp .env.docker.example .env"
    echo "    and update the database and application secrets."
    exit 1
fi
echo "[+] Step 1: Production .env verified."

# 2. Build production Docker images
echo "[+] Step 2: Building multi-stage Docker images..."
docker compose build --pull

# 3. Start containers in background
echo "[+] Step 3: Starting container stack (app, webserver, db, redis, scheduler, queue)..."
docker compose up -d

# 4. Wait for database and application bootstrap
echo "[+] Step 4: Waiting 30 seconds for MySQL and services to initialize..."
sleep 30

# 5. Run database migrations
echo "[+] Step 5: Executing database migrations..."
docker compose exec -T app php artisan migrate --force

# 6. Cache configuration
echo "[+] Step 6: Caching Laravel configuration..."
docker compose exec -T app php artisan config:cache

# 7. Cache routes
echo "[+] Step 7: Caching application routes..."
docker compose exec -T app php artisan route:cache

# 8. Cache views
echo "[+] Step 8: Compiling Blade views..."
docker compose exec -T app php artisan view:cache

# 9. Verify container status
echo "[+] Step 9: Container status:"
docker compose ps

# 10. Success summary
APP_URL=$(grep "^APP_URL=" .env | cut -d '=' -f2 | tr -d '"' | tr -d "'")
if [ -z "$APP_URL" ]; then
    APP_URL="http://localhost"
fi

echo "=========================================================="
echo " [SUCCESS] NotifyDesk deployed successfully!"
echo " Web Application: ${APP_URL}"
echo " Health Status:   ${APP_URL}/health"
echo " Monitoring:      docker compose logs -f"
echo "=========================================================="
