<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\PaymentSchedule;
use App\Models\Service;
use App\Models\Subscription;
use App\Models\User;
use App\Services\NotificationService;
use Database\Seeders\ServiceCatalogSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Phase2VerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingsSeeder::class);
        $this->seed(ServiceCatalogSeeder::class);
    }

    /**
     * 3. Idempotent Reminder Tests (3 Calendar Days)
     */
    public function test_daily_reminder_is_idempotent_and_sets_reminder_sent_at(): void
    {
        $notificationService = app(NotificationService::class);
        $admin = User::factory()->create(['role' => 'founder']);

        $client = Client::create([
            'business_name' => 'صالون رتوش',
            'phone' => '0792223344',
            'city_area' => 'عمان',
            'business_category' => 'صالون',
            'lead_source' => 'Direct',
            'status' => 'subscriber',
        ]);

        $subscription = Subscription::create([
            'client_id' => $client->id,
            'user_id' => $admin->id,
            'billing_type' => 'monthly',
            'total_price' => 116.000,
            'start_date' => now()->subMonth()->toDateString(),
            'status' => 'active',
        ]);

        // Schedule due in exactly 2 days (within 3 calendar days)
        $schedule = PaymentSchedule::create([
            'subscription_id' => $subscription->id,
            'sequence' => 2,
            'amount_due' => 116.000,
            'due_date' => now()->addDays(2)->toDateString(),
            'status' => 'upcoming',
        ]);

        // First run: Should issue notification and record reminder_sent_at
        $count1 = $notificationService->checkDuePaymentReminders();
        $this->assertGreaterThanOrEqual(1, $count1);

        $schedule->refresh();
        $this->assertNotNull($schedule->reminder_sent_at);

        // Second immediate run: Idempotent deduplication (same day & source)
        $count2 = $notificationService->checkDuePaymentReminders();
        $this->assertEquals(0, $count2);
    }

    /**
     * 6. Data-driven Services Catalog, Restaurant POS, and Store Management
     */
    public function test_service_catalog_contains_restaurant_pos_and_store_management(): void
    {
        $pos = Service::where('key', 'restaurant_pos')->first();
        $this->assertNotNull($pos);
        $this->assertEquals('نظام نقاط البيع للمطاعم', $pos->name_ar);
        $this->assertEquals('Restaurant POS', $pos->name_en);
        $this->assertTrue($pos->is_active);

        $store = Service::where('key', 'store_management')->first();
        $this->assertNotNull($store);
        $this->assertEquals('إدارة المتجر', $store->name_ar);
        $this->assertEquals('Store Management', $store->name_en);
        $this->assertTrue($store->is_active);
    }

    public function test_service_deactivation_does_not_break_historical_selections(): void
    {
        $pos = Service::where('key', 'restaurant_pos')->first();
        $admin = User::factory()->create(['role' => 'founder']);
        $client = Client::create([
            'business_name' => 'مطعم برجر ستيشن',
            'phone' => '0795556677',
            'city_area' => 'عمان',
            'business_category' => 'مطاعم',
            'lead_source' => 'Direct',
            'status' => 'subscriber',
        ]);

        $subscription = Subscription::create([
            'client_id' => $client->id,
            'user_id' => $admin->id,
            'billing_type' => 'monthly',
            'total_price' => 350.000,
            'start_date' => now()->toDateString(),
            'status' => 'active',
        ]);

        // Attach with historical snapshot
        DB::table('subscription_service')->insert([
            'subscription_id' => $subscription->id,
            'service_id' => $pos->id,
            'service_key' => $pos->key,
            'service_name_ar' => $pos->name_ar,
            'service_name_en' => $pos->name_en,
            'price_contribution' => $pos->default_price,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Deactivate POS from catalog
        $pos->update(['is_active' => false]);

        // Active catalog query excludes deactivated
        $activeKeys = Service::active()->pluck('key')->all();
        $this->assertNotContains('restaurant_pos', $activeKeys);

        // Historical subscription still retains the attached snapshot
        $subServices = DB::table('subscription_service')
            ->where('subscription_id', $subscription->id)
            ->get();
        $this->assertCount(1, $subServices);
        $this->assertEquals('restaurant_pos', $subServices->first()->service_key);
        $this->assertEquals('نظام نقاط البيع للمطاعم', $subServices->first()->service_name_ar);
    }

    /**
     * 8. Brand System Checks: Notify Identity & Design Tokens
     */
    public function test_login_page_renders_notify_brand_and_fonts(): void
    {
        $response = $this->get(route('login'));
        $response->assertOk();
        $response->assertSee('تسجيل الدخول · Notify');
        $response->assertSee('Cairo');
        $response->assertSee('Inter');
        $response->assertDontSee('NotifyDesk');
    }
}
