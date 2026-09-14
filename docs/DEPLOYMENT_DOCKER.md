# NotifyDesk Production Deployment Guide (Docker & Docker Compose)

This guide documents the complete end-to-end procedure for deploying NotifyDesk to an Ubuntu/Debian Virtual Private Server (VPS) such as Hostinger KVM VPS, DigitalOcean Droplet, Hetzner Cloud, or AWS EC2.

---

## Architecture Overview

The production deployment consists of 6 dedicated Docker containers connected over a private bridge network:
1. **`notifydesk_webserver`**: Nginx reverse proxy handling SSL, static asset compression, security headers, and fastcgi routing.
2. **`notifydesk_app`**: PHP 8.3-FPM production application with OPcache, MySQL PDO, and Redis extensions.
3. **`notifydesk_db`**: MySQL 8.0 database with persistent storage volume and automated health checks.
4. **`notifydesk_redis`**: Redis 7 in-memory cache, session, and queue backend.
5. **`notifydesk_scheduler`**: Continuous Laravel task scheduler (`php artisan schedule:work`).
6. **`notifydesk_queue`**: Async background worker for jobs and notifications (`php artisan queue:work`).

---

## 1. Prerequisites

### Server Specifications
- **Operating System**: Ubuntu 22.04 LTS or 24.04 LTS (x86_64)
- **RAM**: Minimum 2 GB (4 GB recommended for smooth multi-stage builds)
- **Disk**: 25 GB SSD/NVMe
- **Domain Name**: Pointed via DNS A record to your server's public IP address.

### Server Dependencies
Install Docker Engine and Docker Compose Plugin on the VPS:
```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y curl git ufw fail2ban

# Install official Docker
curl -fsSL https://get.docker.com -o get-docker.sh
sudo sh get-docker.sh
sudo usermod -aG docker $USER
```

Log out and log back in to apply Docker group permissions:
```bash
docker --version
docker compose version
```

---

## 2. Step 1: Clone Repository on VPS

```bash
cd /var/www
sudo git clone https://github.com/your-org/notifydesk-laravel.git notifydesk
sudo chown -R $USER:$USER /var/www/notifydesk
cd /var/www/notifydesk
```

---

## 3. Step 2: Configure Environment Variables

Copy the production Docker template:
```bash
cp .env.docker.example .env
```

Generate a secure application key:
```bash
# You can generate a key locally or use php inside docker
docker run --rm -it php:8.3-cli php -r "echo 'base64:' . base64_encode(random_bytes(32)) . PHP_EOL;"
```

Edit `.env` using your preferred editor:
```bash
nano .env
```

Key values to configure:
```env
APP_NAME=NotifyDesk
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com
APP_KEY=base64:YOUR_GENERATED_32_BYTE_KEY

DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=notifydesk
DB_USERNAME=notifydesk_user
DB_PASSWORD=YOUR_STRONG_RANDOM_PASSWORD
DB_ROOT_PASSWORD=YOUR_STRONG_ROOT_PASSWORD

CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
REDIS_HOST=redis
REDIS_PORT=6379

MIGRATE_ON_START=true
SEED_ON_START=false
```

---

## 4. Step 3: Run Deployment Script

Make deployment scripts executable and run the automated deployment:
```bash
chmod +x docker/deploy.sh docker/entrypoint.sh
./docker/deploy.sh
```

The script will automatically:
1. Build the multi-stage production Docker image (PHP 8.3 + Composer + Vite assets).
2. Start MySQL, Redis, App, Webserver, Scheduler, and Queue workers.
3. Wait for MySQL healthcheck to report healthy.
4. Execute database migrations (`php artisan migrate --force`).
5. Compile and cache configurations, routes, and views.

---

## 5. Step 4: Host Nginx & SSL Configuration (Certbot)

If your VPS runs an external host Nginx managing multiple domains and SSL certificates:

### Host Nginx Virtual Host (`/etc/nginx/sites-available/notifydesk.conf`):
```nginx
server {
    listen 80;
    server_name your-domain.com www.your-domain.com;

    location / {
        proxy_pass http://127.0.0.1:80;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

Enable site:
```bash
sudo ln -s /etc/nginx/sites-available/notifydesk.conf /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### Free SSL Certificate with Let's Encrypt:
```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d your-domain.com -d www.your-domain.com
```

---

## 6. Step 5: Configure Firewall (UFW)

Protect non-public ports from external access:
```bash
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow ssh
sudo ufw allow http
sudo ufw allow https
sudo ufw enable
```

Verify that MySQL (3306) and Redis (6379) are inaccessible from the outside world:
```bash
sudo ufw status
```

---

## 7. Step 6: Health Monitoring and Logs

### View Container Status:
```bash
docker compose ps
```

### View Live Logs:
```bash
# All containers
docker compose logs -f

# Application logs only
docker compose logs -f app

# Webserver access & error logs
docker compose logs -f webserver

# Scheduler logs
docker compose logs -f scheduler
```

### Query Application Health Endpoint:
```bash
curl -i http://localhost/health
```
Expected output:
```json
{"status":"ok","database":"connected","cache":"working","timestamp":"2026-09-12T00:55:00+03:00"}
```

---

## 8. Step 7: Database Backup Strategy

Create an automated daily database backup script (`/var/www/notifydesk/backup.sh`):
```bash
#!/bin/bash
BACKUP_DIR="/var/backups/notifydesk"
DATE=$(date +%Y%m%d_%H%M%S)
mkdir -p "$BACKUP_DIR"

docker compose -f /var/www/notifydesk/docker-compose.yml exec -T db \
    mysqldump -u notifydesk_user -p"YOUR_PASSWORD" notifydesk | gzip > "$BACKUP_DIR/notifydesk_$DATE.sql.gz"

# Retain backups for 14 days
find "$BACKUP_DIR" -type f -name "*.sql.gz" -mtime +14 -exec rm {} \;
```

Make it executable and add to crontab:
```bash
chmod +x /var/www/notifydesk/backup.sh
(crontab -l 2>/dev/null; echo "0 3 * * * /var/www/notifydesk/backup.sh > /dev/null 2>&1") | crontab -
```

---

## 9. Step 8: Application Update Procedure

When updating code from git:
```bash
cd /var/www/notifydesk
git pull origin main
./docker/deploy.sh
```

---

## 10. Troubleshooting Common Issues

### Issue 1: Database connection refused on startup
- **Cause**: MySQL container takes 15-20 seconds to initialize tables on the first run.
- **Solution**: The `docker/entrypoint.sh` includes an automatic retry loop checking `mysqladmin ping`. Verify credentials in `.env`.

### Issue 2: Storage or cache write permission error
- **Cause**: Incorrect file ownership inside container volumes.
- **Solution**:
  ```bash
  docker compose exec -u root app chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
  docker compose exec -u root app chmod -R 775 /var/www/storage /var/www/bootstrap/cache
  ```

### Issue 3: Stale views or configuration changes not reflecting
- **Cause**: Production caching (`config:cache`, `view:cache`).
- **Solution**:
  ```bash
  docker compose exec app php artisan optimize:clear
  docker compose exec app php artisan optimize
  ```
