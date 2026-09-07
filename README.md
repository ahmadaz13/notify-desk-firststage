<<<<<<< HEAD
# NotifyDesk Pilot Edition

NotifyDesk Pilot Edition is a lightweight Arabic RTL Sales Operations CRM for Ahmad and Khalid's 60-day sales validation experiment. It is a traditional Laravel 11 monolith using server-rendered Blade templates, session authentication, SQLite for local testing, and migrations compatible with MySQL.

## Requirements

- PHP 8.2 or newer with SQLite or MySQL extensions
- Composer
- A web server or PHP's built-in development server

## Quick Startup with run.sh

A convenience startup script is provided at the root of the project:

```bash
chmod +x run.sh
./run.sh
```

The script automatically:
1. Verifies PHP availability.
2. Creates `.env` from `.env.example` and generates an application key if missing.
3. Creates `database/database.sqlite` if missing.
4. Checks Composer dependencies.
5. Runs non-destructive database migrations (`php artisan migrate --force`).
6. Seeds initial demo accounts if the database is unseeded.
7. Starts the local server (`php artisan serve`).

## Manual Local Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --force
php artisan db:seed --force
php artisan serve
```

Open `http://127.0.0.1:8000/login` and use either seeded account:

| User | Email | Password |
|---|---|---|
| Ahmad | `ahmad@example.com` | `password` |
| Khalid | `khalid@example.com` | `password` |

---

## الميزات الجديدة والترقيات المنجزة (New Features)

### 1. مخرجات الاجتماعات (Meeting Outcomes)
- نموذج تفصيلي مهيكل لتسجيل حضور الموعد، نوع الاجتماع، تقديم العرض التوضيحي (Demo)، مستوى الاهتمام (مرتفع، متوسط، منخفض، غير مهتم)، احتياجات العميل، الاعتراضات، مناقشة الأسعار، الخطوة التالية، وتاريخ المتابعة القادم.
- عند الحفظ، تتحول حالة الموعد تلقائياً إلى **مكتمل (completed)** ويتم توثيق الحدث فورياً في الخط الزمني (Timeline)، مع إنشاء سجل متابعة تلقائي بالخطوة القادمة.
- إمكانية الوصول للنموذج مباشرة من بطاقة الموعد القادم أو مواعيد اليوم باللوحة الرئيسية، أو من تبويب المواعيد بملف العميل.

### 2. إدارة المتابعات (Follow-ups Management)
- تسجيل ومتابعة كافة الاتصالات والزيارات الدورية للعملاء.
- يتضمن تحديد طريقة التواصل (هاتف، واتساب، زيارة، بريد)، سبب المتابعة، نتيجتها، والخطوة التالية مع تاريخ استحقاقها.
- تظهر المتابعات ضمن تبويب مخصص بملف العميل ومرتبة زمنياً حسب تاريخ الاستحقاق.

### 3. العروض التجارية (Commercial Offers)
- تقديم وإدارة العروض المالية من ملف العميل مباشرة.
- يدعم اختيار الباقة، دورة الفوترة (شهري، سنوي، تقسيط)، السعر الأساسي، الخصم الممنوح، وحساب السعر الصافي المتفق عليه تلقائياً، مع تحديد مهلة اتخاذ القرار وملاحظات العرض.

### 4. استيراد العملاء عبر CSV (CSV Import with Duplicate Detection)
- يدعم استيراد **الفرص (Prospects)** و**المشتركين (Subscribers)**.
- نماذج CSV قياسية جاهزة للتحميل من واجهة الاستيراد (`public/csv-templates/`).
- خوارزمية ذكية لتنظيف وتوحيد أرقام الهواتف (Phone Normalization) بإزالة المسافات والرموز والمفاتيح الدولية والمحلية.
- كشف استباقي للتكرار بمقارنة الأرقام مع قاعدة بيانات النظام وداخل أسطر الملف نفسه.
- نظام معاينة ثلاثي: يفرز الأسطر إلى صالحة (Valid)، مكررة ومستبعدة (Duplicates)، وغير مكتملة ومرفوضة (Invalid) قبل التأكيد الفعلي، مع تنفيذ عملية الإدخال داخل معاملة ذرية (Database Transaction).

### 5. التنبيهات والإشعارات المجدولة (In-App Notifications & Scheduled Reminders)
- مركز إشعارات داخلي مزود بعداد حي للتنبيهات غير المقروءة في شريط التنقل العلوي والقائمة السفلية للهواتف.
- توليد تنبيهات تلقائية لـ:
  - المواعيد القادمة خلال أقل من 5 ساعات.
  - الدفعات المستحقة خلال 3 أيام.
  - الدفعات المستحقة اليوم.
  - الدفعات المتأخرة (Overdue).
- أمر آرتيزان مخصص:
  ```bash
  php artisan reminders:send
  ```
- نظام منع تكرار يومي يضمن عدم إرسال نفس التنبيه أكثر من مرة في اليوم الواحد عند تشغيل المجدول آلياً.

### 6. لوحة اليوم المعززة (Operational Dashboard)
- مؤشرات مالية وتشغيلية مباشرة: تحصيلات اليوم، دخل الشهر، مصروفات الشهر، صافي السيولة المحققة، والمتأخرات غير المحصلة.
- بطاقة الموعد القادم مع زر "تسجيل النتيجة" المباشر.
- قائمة مواعيد اليوم مع أزرار التأكيد وإكمال النتيجة.
- قائمة التنبيهات الحديثة غير المقروءة مع إمكانية التعليم كمقروء بضغطة واحدة.

---

## تشغيل المجدول التلقائي (Scheduler Setup)

في بيئة الإنتاج والاستضافة (مثل Hostinger)، قم بضبط مهمة مجدولة (Cron Job) لتنفيذ مجدول لارافيل كل دقيقة:

```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

الأمر المجدول يقوم بتشغيل `reminders:send` كل دقيقة لفحص المواعيد والدفعات وإشعار أحمد وخالد.

---

## إعداد قاعدة البيانات لبيئة الإنتاج (MySQL on Hostinger)

للانتقال من SQLite إلى MySQL في بيئة الإنتاج:

1. أنشئ قاعدة بيانات ومستخدم MySQL في لوحة التحكم (cPanel / hPanel).
2. عدل المتغيرات في ملف `.env`:
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=notifydesk
   DB_USERNAME=your_db_user
   DB_PASSWORD=your_db_password
   ```
3. نفذ ترحيل الجداول دون حذف البيانات السابقة:
   ```bash
   php artisan migrate --force
   ```

---

## الفحوصات والاختبارات الآلية (Tests)

يحتوي المشروع على حزمة اختبارات تكاملية ووظيفية كاملة تغطي كافة المسارات والخدمات الجديدة:

```bash
php artisan test
```

حزمة الاختبارات تشمل:
- `ExampleTest`: التحقق من حماية المسارات وتوجيه الزوار للوجن، وعرض اللوحة للمستخدم المسجل.
- `MeetingOutcomeTest`: التحقق من دورة تسجيل مخرجات الموعد وتحديث حالته للخط الزمني وإضافة المتابعة.
- `FollowUpAndOfferTest`: التحقق من حفظ المتابعات والعروض التجارية وتفاصيل الخصومات.
- `CsvImportTest`: التحقق من تنظيف أرقام الهواتف، كشف التكرارات بالمعاينة، واستيراد الفرص والمشتركين مع جداول الأقساط.
- `NotificationAndReminderTest`: التحقق من توليد التنبيهات بأمر `reminders:send` وتعليم الإشعارات كمقروءة.

---

## Production Deployment Checklist

```bash
composer install --no-dev --optimize-autoloader
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force
```

---

## التوثيق الفني والتقني

التوثيق الآلي وهيكل المشروع الكامل متوفر في:
- `docs/notifydesk-documentation.json`
=======
# notify-desk-firststage

>>>>>>> 91778e0ab6f74007443ac243bf088d89525399ee
