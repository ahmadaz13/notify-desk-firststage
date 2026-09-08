# NotifyDesk - Hostinger Deployment Guide

This guide provides step-by-step instructions for deploying the **NotifyDesk Pilot Edition** Laravel application to a shared hosting environment such as **Hostinger** (hPanel).

---

## Pre-Deployment Checklist

- [ ] Domain / Subdomain created in Hostinger hPanel.
- [ ] MySQL Database, Database User, and Password created via **hPanel &rarr; Databases &rarr; Management**.
- [ ] SSH access enabled in **hPanel &rarr; Advanced &rarr; SSH Access** (recommended).
- [ ] PHP version set to **8.2+** via **hPanel &rarr; Advanced &rarr; PHP Configuration**.

---

## Step 1: Upload Files

### Option A: Via Git & SSH (Recommended)
Connect via SSH to your Hostinger server:
```bash
cd domains/yourdomain.com
git clone https://github.com/ahmadaz13/notify-desk-firststage.git public_html
cd public_html
```

### Option B: Via Hostinger File Manager
1. Create a zip archive of the repository (excluding `vendor/`, `node_modules/`, `.env`, and `database/*.sqlite`).
2. Upload and extract into your application directory in File Manager.

---

## Step 2: Set Document Root

In Laravel, only the `public/` directory should be accessible by the web browser:

1. Go to **hPanel &rarr; Websites &rarr; Manage**.
2. Under **Domain**, click on your domain settings or directory configuration.
3. Change the **Document Root** to point to the `public/` folder:
   - Example: `public_html/public` or `domains/yourdomain.com/public_html/public`.
4. The `public/.htaccess` included with the application will automatically manage rewrite rules and routing.

---

## Step 3: Directory Permissions

Ensure the web server has write permissions for caching and logging:

```bash
chmod -R 775 storage bootstrap/cache
```

---

## Step 4: Configure Production Environment (`.env`)

1. Copy `.env.example` to `.env`:
   ```bash
   cp .env.example .env
   ```
2. Generate an application encryption key:
   ```bash
   php artisan key:generate
   ```
3. Open `.env` and set the production parameters:
   ```env
   APP_NAME=NotifyDesk
   APP_ENV=production
   APP_KEY=base64:... (generated above)
   APP_DEBUG=false
   APP_URL=https://yourdomain.com

   # Hostinger MySQL Database Settings
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=u123456789_notifydesk
   DB_USERNAME=u123456789_user
   DB_PASSWORD=YourSecurePassword
   ```

---

## Step 5: Install Dependencies

Run Composer with production flags:

```bash
composer install --no-dev --optimize-autoloader
```

*(Optional: If frontend assets were modified)*:
```bash
npm ci && npm run build
```

---

## Step 6: Run Database Migrations

Apply database migrations to your MySQL database:

```bash
php artisan migrate --force
```

*(Optional: If initial demo users are needed)*:
```bash
php artisan db:seed --force
```

---

## Step 7: Configure Laravel Scheduler (Cron Job)

NotifyDesk uses the Laravel scheduler for daily reminders and appointment alerts.

1. In hPanel, go to **Advanced &rarr; Cron Jobs**.
2. Select **Custom** type.
3. Set schedule to run **Every minute**:
   ```
   * * * * *
   ```
4. Set the command to:
   ```bash
   * * * * * cd /home/u123456789/domains/yourdomain.com/public_html && php artisan schedule:run >> /dev/null 2>&1
   ```
   *(Update the path to match your server's exact home directory)*.

---

## Step 8: Production Optimization

Run Laravel cache optimization commands:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

To clear these caches after future deployments:
```bash
php artisan optimize:clear
```

---

## Verification & Health Check

1. Open `https://yourdomain.com/login` in your browser.
2. Confirm the RTL interface and Arabic styling load properly.
3. Log in with your admin credentials.
4. Test client view, appointments, follow-ups, and notifications.
