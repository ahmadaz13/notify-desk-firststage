# NotifyDesk Pilot Edition

NotifyDesk Pilot Edition is a lightweight Arabic RTL Sales Operations CRM for Ahmad and Khalid's 60-day sales validation experiment. It is a traditional Laravel 11 monolith using server-rendered Blade templates, session authentication, SQLite for local testing, and migrations fully compatible with MySQL.

---

## Requirements

- PHP 8.2 or newer with `pdo_sqlite` or `pdo_mysql` extensions
- Composer 2.x
- Node.js & NPM (for asset building, if modifying styles)
- Apache / Nginx web server or PHP's built-in development server

---

## Local Development Setup

### Quick Startup with `run.sh` / `run.bat`

A convenience startup script is provided at the root of the project:

```bash
# Linux / macOS / Git Bash
chmod +x run.sh
./run.sh

# Windows Command Prompt / PowerShell
run.bat
```

The script automatically:
1. Verifies PHP availability.
2. Creates `.env` from `.env.example` and generates an application key if missing.
3. Creates `database/database.sqlite` if missing.
4. Checks Composer dependencies.
5. Runs non-destructive database migrations (`php artisan migrate --force`).
6. Seeds initial demo accounts if the database is unseeded.
7. Starts the local server (`php artisan serve`).

### Manual Local Setup

```bash
# 1. Clone repository & install dependencies
composer install

# 2. Configure environment
cp .env.example .env
php artisan key:generate

# 3. Create SQLite database file
touch database/database.sqlite

# 4. Run migrations and seeders
php artisan migrate --force
php artisan db:seed --force

# 5. Serve locally
php artisan serve
```

Open `http://127.0.0.1:8000/login` and use either seeded account:

| User | Email | Password |
|---|---|---|
| Ahmad | `ahmad@example.com` | `password` |
| Khalid | `khalid@example.com` | `password` |

---

## Deployment to Hostinger

Follow these steps to deploy NotifyDesk Pilot Edition on Hostinger (Shared / Cloud Hosting):

### 1. Upload Project Files
- Clone the repository via SSH or upload the project archive via Hostinger File Manager / Git deployment.
- Place the application in your target directory (e.g. `public_html` or a subfolder like `public_html/notifydesk`).
- Ensure `.env`, `vendor/`, and `.git/` are not publicly exposed.

### 2. Set Document Root to `public/`
- In Hostinger hPanel, navigate to **Websites** &rarr; **Manage** &rarr; **Domain / Subdomain settings**.
- Change the **Document Root** to point directly to the project's `public` folder:
  - Example: `public_html/public` or `domains/yourdomain.com/public_html/public`.
- Ensure Apache `mod_rewrite` is enabled. The included `public/.htaccess` handles front controller routing and header authorizations.

### 3. Permissions for Storage and Cache
Ensure the web server has write permissions to the storage and bootstrap cache directories:
```bash
chmod -R 775 storage bootstrap/cache
```

### 4. Configure Production Environment (`.env`)
Create `.env` from `.env.example` in the project root:
```bash
cp .env.example .env
php artisan key:generate
```
Edit `.env` with production values and MySQL credentials from Hostinger hPanel (Databases &rarr; Management):
```env
APP_NAME=NotifyDesk
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=u123456789_notifydesk
DB_USERNAME=u123456789_admin
DB_PASSWORD=YourStrongPasswordHere
```

### 5. Install Dependencies & Build Assets
Via SSH in the project directory:
```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build  # If assets need rebuilding
```

### 6. Run Database Migrations
Run non-destructive migrations safely:
```bash
php artisan migrate --force
```
*(Optional for initial demo data)*:
```bash
php artisan db:seed --force
```

### 7. Configure Laravel Scheduler (Cron Job)
In Hostinger hPanel, navigate to **Advanced** &rarr; **Cron Jobs**:
- Select **Custom** command.
- Set schedule to **Every minute** (`* * * * *`).
- Command:
  ```bash
  * * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
  ```
  *(Replace `/path/to/project` with your full server path, e.g., `/home/u123456789/domains/yourdomain.com/public_html`)*.

### 8. Optimize Application Cache
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## الميزات المكتملة (Application Features)

### 1. مخرجات الاجتماعات (Meeting Outcomes)
- تسجيل الحضور، نوع الاجتماع، العرض التوضيحي، مستوى الاهتمام، الاعتراضات، الخطوة التالية، وتاريخ المتابعة.
- تحديث حالة الموعد آلياً إلى **مكتمل (completed)** وإدراج الحدث في الخط الزمني (Timeline) مع إنشاء سجل متابعة تلقائي.

### 2. إدارة المتابعات (Follow-ups Management)
- متابعة الاتصالات والزيارات الدورية مع تحديد الوسيلة والسبب والنتيجة وتاريخ الاستحقاق.

### 3. العروض التجارية (Commercial Offers)
- إعداد العروض واختيار الباقة، دورة الفوترة (شهري، سنوي، تقسيط)، وحساب السعر الصافي تلقائياً مع الخصومات.

### 4. استيراد العملاء عبر CSV (CSV Import with Duplicate Detection)
- استيراد الفرص (Prospects) والمشتركين (Subscribers).
- تنظيف وتوحيد أرقام الهواتف (Phone Normalization).
- كشف استباقي للتكرار وتصنيف الأسطر إلى صالحة، مكررة، وغير صالحة قبل التأكيد الفعلي داخل Database Transaction.

### 5. التنبيهات والإشعارات المجدولة (In-App Notifications & Scheduled Reminders)
- مركز إشعارات داخلي في شريط التنقل العلوي والقائمة السفلية للهواتف.
- توليد تنبيهات تلقائية للمواعيد القادمة والدفعات المستحقة والمتأخرة.
- أمر مخصص: `php artisan reminders:send` مع نظام لمنع التكرار اليومي.

### 6. لوحة اليوم المعززة (Operational Dashboard)
- مؤشرات مالية وتشغيلية حية (تحصيلات اليوم، دخل الشهر، مصروفات الشهر، صافي السيولة، المتأخرات).
- بطاقات مباشرة للمواعيد القادمة ومواعيد اليوم والتنبيهات غير المقروءة.

---

## الاختبارات الآلية (Automated Tests)

المشروع مزود باختبارات تكاملية ووظيفية تغطي كافة الوظائف:

```bash
php artisan test
```

حزمة الاختبارات تشمل:
- `Tests\Feature\ExampleTest`: حماية المسارات والمصادقة ولوحة التحكم.
- `Tests\Feature\MeetingOutcomeTest`: دورة تسجيل مخرجات المواعيد والتحديث للخط الزمني.
- `Tests\Feature\FollowUpAndOfferTest`: حفظ المتابعات والعروض والخصومات.
- `Tests\Feature\CsvImportTest`: تنظيف الهواتف، كشف التكرارات، واستيراد الفرص والمشتركين مع جداول الأقساط.
- `Tests\Feature\NotificationAndReminderTest`: توليد الإشعارات بأمر المجدول وتعليمها كمقروءة.
- `Tests\Unit\ExampleTest`: فحص بيئة العمل الأساسية.

---

## التوثيق الإضافي

- [دليل النشر والاستضافة](DEPLOYMENT.md)
- خريطة الهيكل والتوثيق التقني: `docs/notifydesk-documentation.json`
