<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $ahmad = DB::table('users')->insertGetId(['name' => 'Ahmad', 'email' => 'ahmad@example.com', 'password' => Hash::make('password'), 'created_at' => $now, 'updated_at' => $now]);
        $khalid = DB::table('users')->insertGetId(['name' => 'Khalid', 'email' => 'khalid@example.com', 'password' => Hash::make('password'), 'created_at' => $now, 'updated_at' => $now]);
        $clients = [
            ['business_name' => 'مخبز السنابل', 'phone' => '079 550 2410', 'contact_person' => 'سامي', 'city_area' => 'عبدون', 'business_category' => 'مخبز', 'lead_source' => 'Google Maps', 'primary_owner_id' => $ahmad, 'status' => 'prospect', 'notes' => 'مهتم بعرض المتجر.'],
            ['business_name' => 'عيادة نبض', 'phone' => '079 331 8922', 'contact_person' => 'د. ليان', 'city_area' => 'الشميساني', 'business_category' => 'عيادة', 'lead_source' => 'Referral', 'primary_owner_id' => $khalid, 'status' => 'subscriber', 'notes' => 'اشتراك شهري فعال.'],
            ['business_name' => 'استوديو لُمعة', 'phone' => '078 902 1176', 'contact_person' => 'نور', 'city_area' => 'الجبيهة', 'business_category' => 'صالون', 'lead_source' => 'Instagram', 'primary_owner_id' => $ahmad, 'status' => 'prospect', 'notes' => 'العرض قيد الإرسال.'],
        ];
        foreach ($clients as &$client) { $client['created_at'] = $now; $client['updated_at'] = $now; $client['id'] = DB::table('clients')->insertGetId($client); }
        unset($client);
        DB::table('appointments')->insert([
            ['client_id' => $clients[0]['id'], 'appointment_date' => now()->toDateString(), 'appointment_time' => '10:30', 'appointment_type' => 'physical_visit', 'status' => 'confirmed', 'location' => 'عبدون · شارع الأمير هاشم', 'notes' => 'تأكيد العرض الأساسي', 'created_at' => $now, 'updated_at' => $now],
            ['client_id' => $clients[2]['id'], 'appointment_date' => now()->toDateString(), 'appointment_time' => '14:00', 'appointment_type' => 'phone_call', 'status' => 'scheduled', 'location' => 'عن بُعد', 'notes' => 'مراجعة تفاصيل الباقة', 'created_at' => $now, 'updated_at' => $now],
        ]);
        foreach ($clients as $client) { DB::table('activity_logs')->insert(['client_id' => $client['id'], 'user_id' => $client['primary_owner_id'], 'type' => 'client_created', 'description' => 'تم إنشاء العميل من بيانات التجربة', 'created_at' => $now, 'updated_at' => $now]); }
        $subscription = DB::table('subscriptions')->insertGetId(['client_id' => $clients[1]['id'], 'user_id' => $khalid, 'billing_type' => 'monthly', 'total_price' => 450, 'start_date' => now()->subDays(12)->toDateString(), 'renewal_date' => now()->addDays(18)->toDateString(), 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        $schedule = DB::table('payment_schedules')->insertGetId(['subscription_id' => $subscription, 'amount_due' => 450, 'due_date' => now()->subDays(1)->toDateString(), 'status' => 'due', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('payments')->insert(['client_id' => $clients[1]['id'], 'subscription_id' => $subscription, 'payment_schedule_id' => $schedule, 'amount' => 450, 'payment_method' => 'bank_transfer', 'paid_at' => now(), 'recorded_by' => $khalid, 'notes' => null, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('expenses')->insert(['amount' => 18, 'category' => 'تنقلات', 'date' => now()->toDateString(), 'paid_by' => $ahmad, 'notes' => 'زيارة ميدانية', 'created_at' => $now, 'updated_at' => $now]);
        $this->call(ExpenseCategorySeeder::class);
        $this->call(AssetCategorySeeder::class);
        $this->call(ServiceCatalogSeeder::class);
        $this->call(AccountingSeeder::class);
        app(\App\Services\CompanyAccountBootstrapService::class)->ensureDefaults();
    }
}
