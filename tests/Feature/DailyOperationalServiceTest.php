<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Partner;
use App\Models\User;
use App\Services\DailyOperationalService;
use Carbon\Carbon;
use Database\Seeders\ExpenseCategorySeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DailyOperationalServiceTest extends TestCase
{
    use RefreshDatabase;

    protected DailyOperationalService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ExpenseCategorySeeder::class);
        $this->service = app(DailyOperationalService::class);
    }

    public function test_today_snapshot_returns_correct_counts(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'name' => 'Ahmad']);
        $client = Client::create([
            'business_name' => 'مطعم القدس',
            'contact_person' => 'خالد',
            'phone' => '0790000001',
            'city_area' => 'عمان',
            'business_category' => 'مطاعم',
            'lead_source' => 'ميداني',
            'status' => 'prospect',
        ]);

        // 1 today appointment
        $appointment = Appointment::create([
            'client_id' => $client->id,
            'appointment_date' => Carbon::today()->toDateString(),
            'appointment_time' => '10:00',
            'appointment_type' => 'physical_visit',
            'status' => 'scheduled',
        ]);
        $appointment->users()->sync([$admin->id]);

        // 1 pending follow-up (next_follow_up_date <= today)
        DB::table('follow_ups')->insert([
            'client_id' => $client->id,
            'user_id' => $admin->id,
            'reason' => 'متابعة العرض المالي',
            'method' => 'phone_call',
            'next_action' => 'اتصال هاتفي للتأكيد',
            'next_follow_up_date' => Carbon::today()->toDateString(),
            'follow_up_date_time' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 1 collection today = 150.00
        $subId = DB::table('subscriptions')->insertGetId([
            'client_id' => $client->id,
            'user_id' => $admin->id,
            'billing_type' => 'monthly',
            'total_price' => 150.00,
            'start_date' => now()->toDateString(),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('payments')->insert([
            'client_id' => $client->id,
            'subscription_id' => $subId,
            'amount' => 150.00,
            'paid_at' => Carbon::today(),
            'payment_method' => 'cash',
            'recorded_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 1 visible expense today = 45.00
        $fuel = ExpenseCategory::where('key', 'fuel')->first();
        Expense::create([
            'amount' => 45.00,
            'category_id' => $fuel->id,
            'category' => $fuel->name,
            'description' => 'بنزين جولة',
            'date' => Carbon::today()->toDateString(),
            'visibility' => 'shared',
            'paid_by' => $admin->id,
        ]);

        $snapshot = $this->service->getTodaySnapshot($admin);

        $this->assertEquals(1, $snapshot['appointments_count']);
        $this->assertEquals(1, $snapshot['pending_follow_ups']);
        $this->assertArrayNotHasKey('today_collections', $snapshot);
        $this->assertArrayNotHasKey('today_net', $snapshot);
    }

    public function test_today_snapshot_respects_visibility(): void
    {
        $ahmad = User::factory()->create(['role' => 'admin', 'name' => 'Ahmad']);
        $khalid = User::factory()->create(['role' => 'admin', 'name' => 'Khalid']);
        $cat = ExpenseCategory::first();

        // Ahmad personal expense: 20
        Expense::create([
            'amount' => 20.00,
            'category_id' => $cat->id,
            'category' => $cat->name,
            'description' => 'مصروف شخصي أحمد',
            'date' => Carbon::today()->toDateString(),
            'visibility' => 'personal',
            'paid_by' => $ahmad->id,
        ]);

        // Ahmad shared expense: 50
        Expense::create([
            'amount' => 50.00,
            'category_id' => $cat->id,
            'category' => $cat->name,
            'description' => 'مصروف مشترك للشركة',
            'date' => Carbon::today()->toDateString(),
            'visibility' => 'shared',
            'paid_by' => $ahmad->id,
        ]);

        // Khalid personal expense: 40
        Expense::create([
            'amount' => 40.00,
            'category_id' => $cat->id,
            'category' => $cat->name,
            'description' => 'مصروف شخصي خالد',
            'date' => Carbon::today()->toDateString(),
            'visibility' => 'personal',
            'paid_by' => $khalid->id,
        ]);

        $ahmadSnapshot = $this->service->getTodaySnapshot($ahmad);
        $khalidSnapshot = $this->service->getTodaySnapshot($khalid);

        // Ahmad sees: Ahmad personal (20) + shared (50) = 70. Khalid's personal (40) is excluded.
        $this->assertArrayNotHasKey('today_expenses', $ahmadSnapshot);

        // Khalid sees: Khalid personal (40) + shared (50) = 90. Ahmad's personal (20) is excluded.
        $this->assertArrayNotHasKey('today_expenses', $khalidSnapshot);
    }

    public function test_today_snapshot_is_internal_only_and_referral_data_is_visible_to_internal_users(): void
    {
        $partner = Partner::create([
            'company_name' => 'الشريك الذهبي',
            'email' => 'partner@gold.com',
            'phone' => '0799999999',
        ]);
        $partnerUser = User::factory()->create([
            'role' => 'partner',
            'partner_id' => $partner->id,
        ]);

        // Admin client and expense
        $adminClient = Client::create([
            'business_name' => 'عميل الشركة المباشر',
            'contact_person' => 'علي',
            'phone' => '0791111111',
            'city_area' => 'عمان',
            'business_category' => 'تجارة',
            'lead_source' => 'مباشر',
            'status' => 'subscriber',
        ]);
        $cat = ExpenseCategory::first();
        Expense::create([
            'amount' => 100.00,
            'category_id' => $cat->id,
            'category' => $cat->name,
            'description' => 'مصروف تشغيلي عام',
            'date' => Carbon::today()->toDateString(),
            'visibility' => 'shared',
            'paid_by' => 1,
        ]);

        // Partner client and payment
        $partnerClient = Client::create([
            'business_name' => 'عميل تابع للشريك',
            'contact_person' => 'سالم',
            'phone' => '0792222222',
            'city_area' => 'اربد',
            'business_category' => 'خدمات',
            'lead_source' => 'شريك',
            'status' => 'subscriber',
            'partner_id' => $partner->id,
        ]);
        $subPartnerId = DB::table('subscriptions')->insertGetId([
            'client_id' => $partnerClient->id,
            'user_id' => $partnerUser->id,
            'billing_type' => 'monthly',
            'total_price' => 80.00,
            'start_date' => now()->toDateString(),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('payments')->insert([
            'client_id' => $partnerClient->id,
            'subscription_id' => $subPartnerId,
            'amount' => 80.00,
            'paid_at' => Carbon::today(),
            'payment_method' => 'cash',
            'recorded_by' => $partnerUser->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(AuthorizationException::class);
        $this->service->getTodaySnapshot($partnerUser);
    }

    public function test_today_snapshot_includes_referral_attributed_clients_for_internal_users(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $partner = Partner::create([
            'company_name' => 'الشريك الذهبي',
            'email' => 'partner@gold.com',
            'phone' => '0799999999',
        ]);

        $partnerClient = Client::create([
            'business_name' => 'عميل إحالة',
            'contact_person' => 'سالم',
            'phone' => '0792222222',
            'city_area' => 'اربد',
            'business_category' => 'خدمات',
            'lead_source' => 'شريك',
            'status' => 'subscriber',
            'partner_id' => $partner->id,
        ]);
        $subPartnerId = DB::table('subscriptions')->insertGetId([
            'client_id' => $partnerClient->id,
            'user_id' => $admin->id,
            'billing_type' => 'monthly',
            'total_price' => 80.00,
            'start_date' => now()->toDateString(),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('payments')->insert([
            'client_id' => $partnerClient->id,
            'subscription_id' => $subPartnerId,
            'amount' => 80.00,
            'paid_at' => Carbon::today(),
            'payment_method' => 'cash',
            'recorded_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $snapshot = $this->service->getTodaySnapshot($admin);

        $this->assertArrayNotHasKey('today_collections', $snapshot);
    }

    public function test_pending_follow_ups_includes_overdue(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = Client::create([
            'business_name' => 'شركة الأفق',
            'contact_person' => 'رامي',
            'phone' => '0793333333',
            'city_area' => 'عمان',
            'business_category' => 'تقنية',
            'lead_source' => 'ميداني',
            'status' => 'prospect',
        ]);

        // Overdue follow up (yesterday)
        DB::table('follow_ups')->insert([
            'client_id' => $client->id,
            'user_id' => $admin->id,
            'reason' => 'متابعة متأخرة',
            'method' => 'phone_call',
            'next_action' => 'إعادة الاتصال',
            'next_follow_up_date' => Carbon::yesterday()->toDateString(),
            'follow_up_date_time' => now()->subDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Due today follow up
        DB::table('follow_ups')->insert([
            'client_id' => $client->id,
            'user_id' => $admin->id,
            'reason' => 'متابعة اليوم',
            'method' => 'meeting',
            'next_action' => 'زيارة ميدانية',
            'next_follow_up_date' => Carbon::today()->toDateString(),
            'follow_up_date_time' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Future follow up (tomorrow)
        DB::table('follow_ups')->insert([
            'client_id' => $client->id,
            'user_id' => $admin->id,
            'reason' => 'متابعة مستقبلية',
            'method' => 'phone_call',
            'next_action' => 'مكالمة لاحقة',
            'next_follow_up_date' => Carbon::tomorrow()->toDateString(),
            'follow_up_date_time' => now()->addDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $pending = $this->service->getPendingFollowUps($admin);

        $this->assertCount(2, $pending);
        $reasons = $pending->pluck('reason')->all();
        $this->assertContains('متابعة متأخرة', $reasons);
        $this->assertContains('متابعة اليوم', $reasons);
        $this->assertNotContains('متابعة مستقبلية', $reasons);
    }


}
