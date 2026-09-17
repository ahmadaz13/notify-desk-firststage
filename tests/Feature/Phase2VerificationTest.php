<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Partner;
use App\Models\PaymentSchedule;
use App\Models\Service;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\PaymentScheduleService;
use App\Services\SubscriptionPricingService;
use Carbon\Carbon;
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
     * 1. Financial Golden Tests (Monthly, Annual, Installment, Tax, Discount, Setup Fee, JOD Precision)
     */
    public function test_pricing_service_calculates_standard_monthly_with_tax(): void
    {
        $pricingService = app(SubscriptionPricingService::class);

        // Monthly 100.000 JOD base, no discount, 16% tax, 0 setup fee
        $result = $pricingService->calculate(
            baseSubtotal: 100.000,
            billingType: 'monthly',
            setupFee: 0.000,
            customAnnualDiscountPercentage: null,
            customTaxPercentage: 16.0,
            installmentsCount: null,
            monthlyDueDay: 1
        );

        $this->assertEquals(100.000, $result['base_subtotal']);
        $this->assertEquals(0.000, $result['discount_amount']);
        $this->assertEquals(100.000, $result['taxable_base']);
        $this->assertEquals(16.000, $result['tax_amount']);
        $this->assertEquals(116.000, $result['grand_total']);
        $this->assertEquals(12, $result['installments_count']);
        $this->assertEquals(1, $result['monthly_due_day']);
    }

    public function test_pricing_service_calculates_annual_with_10_percent_discount_and_setup_fee(): void
    {
        $pricingService = app(SubscriptionPricingService::class);

        // Annual 1000.000 JOD base, 10% annual discount = 100.000 JOD discount
        // Discounted subtotal = 900.000 JOD
        // Setup fee = 50.000 JOD -> Taxable base = 950.000 JOD
        // 16% sales tax on 950 = 152.000 JOD
        // Grand total = 950 + 152 = 1102.000 JOD
        $result = $pricingService->calculate(
            baseSubtotal: 1000.000,
            billingType: 'annual',
            setupFee: 50.000,
            customAnnualDiscountPercentage: 10.0,
            customTaxPercentage: 16.0,
            installmentsCount: null,
            monthlyDueDay: 15
        );

        $this->assertEquals(1000.000, $result['base_subtotal']);
        $this->assertEquals(10.0, $result['annual_discount_percentage']);
        $this->assertEquals(100.000, $result['discount_amount']);
        $this->assertEquals(900.000, $result['discounted_subtotal']);
        $this->assertEquals(50.000, $result['setup_fee']);
        $this->assertEquals(950.000, $result['taxable_base']);
        $this->assertEquals(16.0, $result['tax_percentage']);
        $this->assertEquals(152.000, $result['tax_amount']);
        $this->assertEquals(1102.000, $result['grand_total']);
        $this->assertEquals(1, $result['installments_count']);
    }

    public function test_pricing_service_does_not_apply_global_discount_or_tax_when_not_explicit(): void
    {
        Setting::set('annual_discount_percentage', '75.0');
        Setting::set('sales_tax_percentage', '99.0');

        $result = app(SubscriptionPricingService::class)->calculate(
            baseSubtotal: 1000.000,
            billingType: 'annual',
            setupFee: 0.000,
            customAnnualDiscountPercentage: null,
            customTaxPercentage: null,
            installmentsCount: null,
            monthlyDueDay: null
        );

        $this->assertEquals(0.0, $result['annual_discount_percentage']);
        $this->assertEquals(0.000, $result['discount_amount']);
        $this->assertEquals(0.0, $result['tax_percentage']);
        $this->assertEquals(0.000, $result['tax_amount']);
        $this->assertEquals(1000.000, $result['grand_total']);
        $this->assertEquals(1, $result['monthly_due_day']);
    }

    public function test_pricing_service_three_decimal_precision_rounding_boundary(): void
    {
        $pricingService = app(SubscriptionPricingService::class);

        // Base 333.333 JOD, 16% tax -> 333.333 * 0.16 = 53.33328 -> rounded to 53.333
        // Grand total = 386.666 JOD
        $result = $pricingService->calculate(
            baseSubtotal: 333.333,
            billingType: 'monthly',
            setupFee: 0.000,
            customAnnualDiscountPercentage: 0.0,
            customTaxPercentage: 16.0,
            installmentsCount: null,
            monthlyDueDay: 5
        );

        $this->assertEquals(53.333, $result['tax_amount']);
        $this->assertEquals(386.666, $result['grand_total']);
    }

    /**
     * 2. Deterministic Payment Schedule Generation & Balance Tests
     */
    public function test_schedule_generation_distributes_setup_fee_and_balances_penny_on_last_installment(): void
    {
        $pricingService = app(SubscriptionPricingService::class);
        $scheduleService = app(PaymentScheduleService::class);

        $client = Client::create([
            'business_name' => 'مطعم القدس',
            'phone' => '0791112233',
            'city_area' => 'عمان',
            'business_category' => 'مطاعم',
            'lead_source' => 'Direct',
            'status' => 'prospect',
        ]);
        $user = User::factory()->create(['role' => 'admin']);

        $pricing = $pricingService->calculate(
            baseSubtotal: 1000.000,
            billingType: 'installment',
            setupFee: 50.000,
            customAnnualDiscountPercentage: 0.0,
            customTaxPercentage: 16.0,
            installmentsCount: 3,
            monthlyDueDay: 30
        );

        // Taxable base = 1000 + 50 = 1050.000 JOD
        // Tax 16% = 168.000 JOD
        // Grand total = 1218.000 JOD

        $subscription = Subscription::create([
            'client_id' => $client->id,
            'user_id' => $user->id,
            'billing_type' => 'installment',
            'total_price' => $pricing['grand_total'],
            'setup_fee' => $pricing['setup_fee'],
            'base_subtotal' => $pricing['base_subtotal'],
            'annual_discount_percentage' => $pricing['annual_discount_percentage'],
            'discount_amount' => $pricing['discount_amount'],
            'tax_percentage' => $pricing['tax_percentage'],
            'tax_amount' => $pricing['tax_amount'],
            'grand_total' => $pricing['grand_total'],
            'monthly_due_day' => $pricing['monthly_due_day'],
            'start_date' => '2026-01-30',
            'renewal_date' => '2027-01-30',
            'installments_count' => 3,
            'status' => 'active',
            'version' => 1,
        ]);

        $schedules = $scheduleService->generateSchedules($subscription, $pricing, '2026-01-30');

        $this->assertCount(3, $schedules);

        // Setup fee on sequence 1
        $this->assertEquals(50.000, $schedules[0]->setup_fee_amount);
        $this->assertEquals(0.000, $schedules[1]->setup_fee_amount);
        $this->assertEquals(0.000, $schedules[2]->setup_fee_amount);

        // Month-end clamping verification:
        // Jan 30 -> Feb 28 (or 29) -> Mar 30
        $this->assertEquals('2026-01-30', $schedules[0]->due_date->toDateString());
        $this->assertStringStartsWith('2026-02-', $schedules[1]->due_date->toDateString());
        $this->assertEquals('2026-03-30', $schedules[2]->due_date->toDateString());

        // Exact sum of amounts equals grand total
        $sumDue = array_sum(array_map(fn($s) => (float)$s->amount_due, $schedules));
        $this->assertEquals(1218.000, round($sumDue, 3));
    }

    /**
     * 3. Idempotent Reminder Tests (3 Calendar Days)
     */
    public function test_daily_reminder_is_idempotent_and_sets_reminder_sent_at(): void
    {
        $notificationService = app(NotificationService::class);
        $admin = User::factory()->create(['role' => 'admin']);

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
     * 4. Cancellation Workflow & Future Reminder Suppression Tests
     */
    public function test_cancellation_workflow_suppresses_future_obligations_and_reminders(): void
    {
        $scheduleService = app(PaymentScheduleService::class);
        $notificationService = app(NotificationService::class);
        $admin = User::factory()->create(['role' => 'admin']);

        $client = Client::create([
            'business_name' => 'مطعم الشام',
            'phone' => '0793334455',
            'city_area' => 'إربد',
            'business_category' => 'مطاعم',
            'lead_source' => 'Direct',
            'status' => 'subscriber',
        ]);

        $subscription = Subscription::create([
            'client_id' => $client->id,
            'user_id' => $admin->id,
            'billing_type' => 'installment',
            'total_price' => 600.000,
            'start_date' => now()->toDateString(),
            'status' => 'active',
        ]);

        // Create 2 upcoming schedules
        $s1 = PaymentSchedule::create([
            'subscription_id' => $subscription->id,
            'sequence' => 1,
            'amount_due' => 300.000,
            'due_date' => now()->toDateString(),
            'status' => 'due',
        ]);

        $s2 = PaymentSchedule::create([
            'subscription_id' => $subscription->id,
            'sequence' => 2,
            'amount_due' => 300.000,
            'due_date' => now()->addDays(2)->toDateString(),
            'status' => 'upcoming',
        ]);

        // Cancel subscription
        $scheduleService->cancelSubscription(
            $subscription,
            'إغلاق المحل',
            $admin->id,
            now()->toDateString()
        );

        $subscription->refresh();
        $this->assertEquals('cancelled', $subscription->status);
        $this->assertEquals('إغلاق المحل', $subscription->cancellation_reason);
        $this->assertNotNull($subscription->cancelled_at);

        $s1->refresh();
        $s2->refresh();
        $this->assertEquals('cancelled', $s1->status);
        $this->assertEquals('cancelled', $s2->status);

        // Verification that reminder service ignores cancelled subscription schedules
        $reminderCount = $notificationService->checkDuePaymentReminders();
        $this->assertEquals(0, $reminderCount);
    }

    /**
     * 5. Renewal Workflow (Non-destructive Versioning)
     */
    public function test_renewal_workflow_creates_new_version_without_mutating_history(): void
    {
        $pricingService = app(SubscriptionPricingService::class);
        $scheduleService = app(PaymentScheduleService::class);
        $admin = User::factory()->create(['role' => 'admin']);

        $client = Client::create([
            'business_name' => 'صيدلية النور',
            'phone' => '0794445566',
            'city_area' => 'عمان',
            'business_category' => 'صيدلية',
            'lead_source' => 'Direct',
            'status' => 'subscriber',
        ]);

        $subV1 = Subscription::create([
            'client_id' => $client->id,
            'user_id' => $admin->id,
            'billing_type' => 'annual',
            'total_price' => 500.000,
            'base_subtotal' => 500.000,
            'annual_discount_percentage' => 10.0,
            'start_date' => '2025-01-01',
            'renewal_date' => '2026-01-01',
            'status' => 'active',
            'version' => 1,
        ]);

        $subV2 = $scheduleService->renewSubscription($subV1, $admin->id);

        $subV1->refresh();
        $this->assertEquals('completed', $subV1->status);
        $this->assertEquals(1, $subV1->version);

        $this->assertEquals(2, $subV2->version);
        $this->assertEquals($subV1->id, $subV2->previous_subscription_id);
        $this->assertEquals('active', $subV2->status);
        $this->assertCount(1, $subV2->paymentSchedules);
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
        $admin = User::factory()->create(['role' => 'admin']);
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
     * 7. End-to-End Client Conversion and Payment Methods
     */
    public function test_legacy_client_convert_action_is_deprecated_and_payment_methods_remain_supported(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = Client::create([
            'business_name' => 'مقهى الكابوتشينو',
            'phone' => '0796667788',
            'city_area' => 'عمان',
            'business_category' => 'كافيه',
            'lead_source' => 'Direct',
            'status' => 'prospect',
        ]);

        $pos = Service::where('key', 'restaurant_pos')->first();
        $menu = Service::where('key', 'e_menu')->first();

        $response = $this->actingAs($admin)->post(route('clients.convert', $client->id), [
            'billing_type' => 'monthly',
            'setup_fee' => 25.000,
            'start_date' => now()->toDateString(),
            'monthly_due_day' => 1,
            'services' => [$pos->id, $menu->id],
        ]);

        $response->assertStatus(410);
        $client->refresh();
        $this->assertEquals('prospect', $client->status);

        $subscription = Subscription::where('client_id', $client->id)->first();
        $this->assertNull($subscription);

        // Legacy top-level payment writes are deprecated; V2 collections remains authoritative.
        $pricing = app(SubscriptionPricingService::class)->calculate(
            baseSubtotal: 100.000,
            billingType: 'monthly',
            setupFee: 0.000,
            customAnnualDiscountPercentage: 0.0,
            customTaxPercentage: 0.0,
            installmentsCount: null,
            monthlyDueDay: 1
        );
        $subscription = Subscription::create([
            'client_id' => $client->id,
            'user_id' => $admin->id,
            'billing_type' => 'monthly',
            'total_price' => $pricing['grand_total'],
            'start_date' => now()->toDateString(),
            'status' => 'active',
        ]);

        $payResponse = $this->actingAs($admin)->post(route('payments.store'), [
            'client_id' => $client->id,
            'amount' => 50.000,
            'payment_method' => 'zain_cash',
            'paid_at' => now()->format('Y-m-d H:i:s'),
        ]);

        $payResponse->assertStatus(410);
        $this->assertDatabaseMissing('payments', [
            'client_id' => $client->id,
            'subscription_id' => $subscription->id,
            'payment_method' => 'zain_cash',
            'amount' => 50.000,
        ]);
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
