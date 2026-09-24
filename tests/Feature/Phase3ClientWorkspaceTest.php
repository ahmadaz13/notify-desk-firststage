<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\ContactAttempt;
use App\Models\Contract;
use App\Models\FollowUp;
use App\Models\Installation;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use App\Support\AppointmentTypes;
use App\Support\ClientLifecycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Phase3ClientWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->staff = User::factory()->create(['role' => 'staff']);
    }

    public function test_workspace_renders_operational_execution_center_with_lightweight_financial_summary(): void
    {
        $client = $this->createClient([
            'business_name' => 'مطعم الياسمين الدمشقي',
            'business_category' => 'مطاعم وسياحة',
            'city_area' => 'عمان - شارع الجاردنز',
            'stage' => ClientLifecycle::SUBSCRIBER,
        ]);
        $client->contacts()->create([
            'name' => 'سامر الحلبي',
            'primary_phone' => '0798765432',
            'role' => 'المدير العام',
            'is_primary' => true,
        ]);

        $product = Product::create(['name_ar' => 'نظام نقاط البيع POS', 'name_en' => 'POS System', 'code' => 'POS_SYS']);
        $plan = Plan::create(['product_id' => $product->id, 'name_ar' => 'الباقة الشاملة', 'name_en' => 'All Inclusive', 'code' => 'ALL_INC', 'tier' => 1]);
        $sub = $this->createSubscription($client, $plan);

        // Add a contract
        $contract = Contract::create([
            'client_id' => $client->id,
            'subscription_id' => $sub->id,
            'contract_number' => 'CNT-2026-0088',
            'generated_by' => $this->admin->id,
            'snapshot_data' => ['dummy' => 'data'],
            'status' => 'issued',
            'issued_at' => now()->toDateString(),
        ]);

        // Add a payment
        $payment = Payment::create([
            'client_id' => $client->id,
            'amount' => 150.000,
            'amount_minor' => 150000,
            'paid_at' => now()->subDays(2)->toDateString(),
            'payment_method' => 'cash',
            'recorded_by' => $this->admin->id,
            'reference' => 'REC-99881',
        ]);

        $response = $this->actingAs($this->admin)->get(route('clients.show', $client));

        $response->assertOk()
            ->assertSee('مطعم الياسمين الدمشقي')
            ->assertSee('مطاعم وسياحة')
            ->assertSee('عمان - شارع الجاردنز')
            ->assertSee('سامر الحلبي')
            ->assertSee('0798765432')
            ->assertSee('نظام نقاط البيع POS')
            ->assertSee('الباقة الشاملة')
            ->assertSee('CNT-2026-0088')
            ->assertSee('150.000 د.أ')
            ->assertSee('REC-99881')
            ->assertSee(route('finance.collections', ['client_id' => $client->id]), false)
            // P10 (§19): state card + compact money card replace the Phase 3 command card.
            ->assertSee('data-client-state', false)
            ->assertSee('data-client-money', false);
    }

    public function test_workspace_does_not_contain_dense_mini_erp_tables_in_normal_view(): void
    {
        $client = $this->createClient(['stage' => ClientLifecycle::PROSPECT]);

        $response = $this->actingAs($this->admin)->get(route('clients.show', $client));

        $response->assertOk();
        // Normal operational view does not render full-width raw invoices / credit allocations
        $response->assertDontSee('notify-management-finance-dense-table', false);
        // P10: a prospect with no money history gets no money card at all (the Collections link lives on the money card).
        $response->assertDontSee('data-client-money', false);
        $response->assertDontSee('payment_allocation', false);
    }

    public function test_valid_path_prospect_to_record_call(): void
    {
        $client = $this->createClient(['stage' => ClientLifecycle::PROSPECT]);

        $response = $this->actingAs($this->staff)->post(route('clients.contact-attempts.store', $client), [
            'method' => 'phone',
            'result' => 'busy',
            'note' => 'الخط مشغول، إعادة المحاولة لاحقاً',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('contact_attempts', [
            'client_id' => $client->id,
            'result' => 'no_answer_busy',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'client_id' => $client->id,
            'type' => 'contact_attempt_recorded',
        ]);
    }

    public function test_valid_path_prospect_to_direct_appointment_without_prior_call(): void
    {
        $client = $this->createClient(['stage' => ClientLifecycle::PROSPECT]);

        $response = $this->actingAs($this->staff)->post(route('appointments.store'), [
            'client_id' => $client->id,
            'appointment_date' => now()->addDays(2)->toDateString(),
            'appointment_time' => '11:00',
            'appointment_type' => 'physical_visit',
            'notes' => 'حجز موعد زيارة مباشرة للعميل المحتمل دون الحاجة لاتصال مسبق',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('appointments', [
            'client_id' => $client->id,
            'appointment_type' => 'physical_visit',
            'status' => 'scheduled',
        ]);
        $this->assertTrue(Appointment::where('client_id', $client->id)->where('status', 'scheduled')->exists());
    }

    public function test_valid_path_prospect_to_direct_free_installation(): void
    {
        $client = $this->createClient(['stage' => ClientLifecycle::PROSPECT]);

        $response = $this->actingAs($this->staff)->post(route('clients.installations.schedule', $client), [
            'appointment_date' => now()->addDays(3)->toDateString(),
            'appointment_time' => '10:00',
            'location' => 'خلدا - مجمع دابوق',
            'notes' => 'تركيب مجاني مباشر بدون موعد أو اتصال مسبق',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('appointments', [
            'client_id' => $client->id,
            'appointment_type' => AppointmentTypes::INSTALLATION,
            'status' => 'scheduled',
        ]);
        $this->assertSame(ClientLifecycle::INSTALLATION_SCHEDULED, $client->fresh()->stage);
    }

    public function test_valid_path_prospect_to_direct_subscription_for_authorized_user(): void
    {
        $client = $this->createClient(['stage' => ClientLifecycle::PROSPECT]);
        $product = Product::create(['name_ar' => 'برنامج كاشير سحابي', 'code' => 'CSH_CLOUD']);
        $plan = Plan::create(['product_id' => $product->id, 'name_ar' => 'باقة الكاشير الأساسية', 'code' => 'CSH_BASIC', 'tier' => 1]);
        $price = PlanPrice::create([
            'plan_id' => $plan->id,
            'billing_interval' => 'monthly',
            'amount_minor' => 50000,
            'price' => 50.000,
            'price_minor' => 50000,
            'is_active' => true,
            'effective_from' => now()->subMonth(),
        ]);

        $response = $this->actingAs($this->admin)->post(route('clients.guided-subscription.store', $client), [
            '_idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
            'system_ids' => [$product->id],
            'billing_interval' => 'monthly',
            'agreed_value_jod' => '50.000',
            'start_date' => now()->toDateString(),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('subscriptions', [
            'client_id' => $client->id,
            'billing_engine_version' => 'v1_simple',
            'status' => 'active',
        ]);
        $this->assertSame(ClientLifecycle::SUBSCRIBER, $client->fresh()->stage);
    }

    public function test_staff_subscription_start_rejects_legacy_plan_payload(): void
    {
        $client = $this->createClient(['stage' => ClientLifecycle::PROSPECT]);
        $product = Product::create(['name_ar' => 'برنامج سحابي', 'code' => 'CLOUD']);
        $plan = Plan::create(['product_id' => $product->id, 'name_ar' => 'باقة سحابية', 'code' => 'CLOUD_PLN', 'tier' => 1]);

        $response = $this->actingAs($this->staff)->post(route('clients.guided-subscription.store', $client), [
            'product_id' => $product->id,
            'plan_id' => $plan->id,
            'billing_interval' => 'monthly',
            'start_date' => now()->toDateString(),
        ]);

        // P2 / FROZEN D-06: staff is authorized; the legacy plan payload fails validation and creates nothing.
        $response->assertSessionHasErrors(['system_ids', 'agreed_value_jod']);
        $this->assertDatabaseMissing('subscriptions', ['client_id' => $client->id]);
    }

    public function test_valid_path_appointment_to_free_installation(): void
    {
        $client = $this->createClient(['stage' => ClientLifecycle::APPOINTMENT]);
        Appointment::create([
            'client_id' => $client->id,
            'appointment_date' => now()->addDay()->toDateString(),
            'appointment_time' => '10:00',
            'appointment_type' => 'physical_visit',
            'status' => 'scheduled',
        ]);

        $response = $this->actingAs($this->staff)->post(route('clients.installations.schedule', $client), [
            'appointment_date' => now()->addDays(2)->toDateString(),
            'appointment_time' => '14:00',
            'notes' => 'جدولة تركيب مجاني بعد الاتفاق في الموعد',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('appointments', [
            'client_id' => $client->id,
            'appointment_type' => AppointmentTypes::INSTALLATION,
        ]);
        $this->assertSame(ClientLifecycle::INSTALLATION_SCHEDULED, $client->fresh()->stage);
    }

    public function test_valid_path_appointment_to_direct_subscription(): void
    {
        $client = $this->createClient(['stage' => ClientLifecycle::APPOINTMENT]);
        Appointment::create([
            'client_id' => $client->id,
            'appointment_date' => now()->toDateString(),
            'appointment_time' => '10:00',
            'appointment_type' => 'physical_visit',
            'status' => 'completed',
        ]);

        $product = Product::create(['name_ar' => 'نظام الحسابات', 'code' => 'ACC_SYS']);
        $plan = Plan::create(['product_id' => $product->id, 'name_ar' => 'باقة الأعمال', 'code' => 'BIZ_PLN', 'tier' => 1]);
        PlanPrice::create([
            'plan_id' => $plan->id,
            'billing_interval' => 'monthly',
            'amount_minor' => 70000,
            'price' => 70.000,
            'price_minor' => 70000,
            'is_active' => true,
            'effective_from' => now()->subMonth(),
        ]);

        $response = $this->actingAs($this->admin)->post(route('clients.guided-subscription.store', $client), [
            '_idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
            'system_ids' => [$product->id],
            'billing_interval' => 'monthly',
            'agreed_value_jod' => '70.000',
            'start_date' => now()->toDateString(),
        ]);

        $response->assertRedirect();
        $this->assertSame(ClientLifecycle::SUBSCRIBER, $client->fresh()->stage);
    }

    public function test_installation_completion_never_automatically_creates_subscription(): void
    {
        $client = $this->createClient(['stage' => ClientLifecycle::INSTALLATION_SCHEDULED]);
        $appt = Appointment::create([
            'client_id' => $client->id,
            'appointment_date' => now()->toDateString(),
            'appointment_time' => '12:00',
            'appointment_type' => AppointmentTypes::INSTALLATION,
            'status' => 'scheduled',
        ]);

        $response = $this->actingAs($this->staff)->post(route('clients.installations.complete', $client), [
            'appointment_id' => $appt->id,
            'custom_item_names' => 'شاشة لمس ونظام كاشير',
            'notes' => 'تم التركيب بنجاح',
        ]);

        $response->assertRedirect();
        $this->assertSame(ClientLifecycle::INSTALLED_FREE, $client->fresh()->stage);
        $this->assertDatabaseHas('appointments', [
            'id' => $appt->id,
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('installations', [
            'client_id' => $client->id,
            'appointment_id' => $appt->id,
        ]);
        // Critical: zero subscriptions and zero invoices created automatically
        $this->assertDatabaseMissing('subscriptions', ['client_id' => $client->id]);
        $this->assertDatabaseMissing('invoices', ['client_id' => $client->id]);
        // Auto-generated 3-day follow-up
        $this->assertDatabaseHas('follow_ups', [
            'client_id' => $client->id,
        ]);
    }

    public function test_valid_path_free_installation_to_start_subscription(): void
    {
        $client = $this->createClient(['stage' => ClientLifecycle::INSTALLED_FREE]);
        Installation::create([
            'client_id' => $client->id,
            'installed_by' => $this->staff->id,
            'installed_at' => now()->subDays(2),
        ]);

        $product = Product::create(['name_ar' => 'منظومة الولاء', 'code' => 'LOYALTY']);
        $plan = Plan::create(['product_id' => $product->id, 'name_ar' => 'باقة الولاء الذهبية', 'code' => 'LYL_GLD', 'tier' => 1]);
        PlanPrice::create([
            'plan_id' => $plan->id,
            'billing_interval' => 'monthly',
            'amount_minor' => 40000,
            'price' => 40.000,
            'price_minor' => 40000,
            'is_active' => true,
            'effective_from' => now()->subMonth(),
        ]);

        $response = $this->actingAs($this->admin)->post(route('clients.guided-subscription.store', $client), [
            '_idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
            'system_ids' => [$product->id],
            'billing_interval' => 'monthly',
            'agreed_value_jod' => '40.000',
            'start_date' => now()->toDateString(),
        ]);

        $response->assertRedirect();
        $this->assertSame(ClientLifecycle::SUBSCRIBER, $client->fresh()->stage);
    }

    public function test_valid_path_follow_up_completion_can_create_appointment(): void
    {
        $client = $this->createClient(['stage' => ClientLifecycle::DECISION_PENDING]);
        $followUpId = DB::table('follow_ups')->insertGetId([
            'client_id' => $client->id,
            'user_id' => $this->staff->id,
            'method' => 'phone',
            'reason' => 'متابعة القرار',
            'next_action' => 'اتصال',
            'next_follow_up_date' => now()->toDateString(),
            'follow_up_date_time' => now()->toDateTimeString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->staff)->post(route('follow-ups.complete', $followUpId), [
            'outcome' => 'appointment',
            'appointment_date' => now()->addDays(3)->toDateString(),
            'appointment_time' => '15:30',
            'appointment_type' => 'physical_visit',
            'notes' => 'العميل طلب اجتماعاً إضافياً لمناقشة العرض',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('appointments', [
            'client_id' => $client->id,
            'appointment_time' => '15:30',
        ]);
        $this->assertNotNull(DB::table('follow_ups')->where('id', $followUpId)->value('completed_at'));
    }

    public function test_closing_client_under_secondary_actions_preserves_full_history(): void
    {
        $client = $this->createClient(['stage' => ClientLifecycle::CONTACTING]);
        $client->contacts()->create([
            'name' => 'جمال',
            'primary_phone' => '0791112233',
            'role' => 'مالك',
            'is_primary' => true,
        ]);
        Appointment::create([
            'client_id' => $client->id,
            'appointment_date' => now()->subDay()->toDateString(),
            'appointment_time' => '10:00',
            'appointment_type' => 'physical_visit',
            'status' => 'completed',
        ]);
        DB::table('activity_logs')->insert([
            'client_id' => $client->id,
            'user_id' => $this->staff->id,
            'type' => 'contact_attempt',
            'description' => 'اتصال مسجل سابق',
            'created_at' => now()->subHours(5),
            'updated_at' => now()->subHours(5),
        ]);

        $response = $this->actingAs($this->admin)->post(route('clients.close', $client), [
            'closed_reason_code' => 'not_interested',
            'closed_reason' => 'العميل غير مهتم بالخدمة حالياً',
        ]);

        $response->assertRedirect();
        $fresh = $client->fresh();
        $this->assertSame(ClientLifecycle::CLOSED, $fresh->stage);

        // Verify full history is strictly preserved
        $this->assertDatabaseHas('client_contacts', ['client_id' => $client->id, 'name' => 'جمال']);
        $this->assertDatabaseHas('appointments', ['client_id' => $client->id, 'status' => 'completed']);
        $this->assertDatabaseHas('activity_logs', ['client_id' => $client->id, 'description' => 'اتصال مسجل سابق']);
        $this->assertDatabaseHas('activity_logs', ['client_id' => $client->id, 'type' => 'client_closed']);
    }

    public function test_explicit_reopen_restores_operational_access_without_history_loss(): void
    {
        $client = $this->createClient([
            'stage' => ClientLifecycle::CLOSED,
            'status' => 'archived',
            'closed_reason' => 'عدم التفرغ',
        ]);
        DB::table('activity_logs')->insert([
            'client_id' => $client->id,
            'user_id' => $this->admin->id,
            'type' => 'client_closed',
            'description' => 'تم إغلاق ملف العميل',
            'created_at' => now()->subDays(5),
            'updated_at' => now()->subDays(5),
        ]);

        $response = $this->actingAs($this->admin)->post(route('clients.reopen', $client), [
            'stage' => 'prospect',
            'reason' => 'تواصل العميل معنا مرة أخرى وأبدى رغبته في إعادة دراسة العرض',
        ]);

        $response->assertRedirect();
        $fresh = $client->fresh();
        $this->assertSame(ClientLifecycle::PROSPECT, $fresh->stage);
        $this->assertDatabaseHas('activity_logs', [
            'client_id' => $client->id,
            'type' => 'client_reopened',
        ]);
        // Past activity log is preserved
        $this->assertDatabaseHas('activity_logs', [
            'client_id' => $client->id,
            'type' => 'client_closed',
        ]);
    }

    private function createClient(array $overrides = []): Client
    {
        return Client::create(array_merge([
            'business_name' => 'شركة الأعمال المتقدمة',
            'phone' => '0797771234',
            'business_phone' => '0797771234',
            'contact_person' => 'مأمون',
            'city_area' => 'عمان - الشميساني',
            'city' => 'Amman',
            'area' => 'Shmeisani',
            'business_category' => 'تقنية وتجزئة',
            'business_type' => 'متجر إلكتروني',
            'lead_source' => 'Direct Prospecting',
            'number_of_branches' => 1,
            'status' => 'prospect',
            'stage' => ClientLifecycle::PROSPECT,
        ], $overrides));
    }

    private function createSubscription(Client $client, Plan $plan, array $overrides = []): Subscription
    {
        return Subscription::create(array_merge([
            'client_id' => $client->id,
            'plan_id' => $plan->id,
            'user_id' => $this->admin->id,
            'billing_type' => 'monthly',
            'total_price' => 100.000,
            'base_subtotal' => 100.000,
            'setup_fee' => 0.000,
            'annual_discount_percentage' => 0.00,
            'discount_amount' => 0.000,
            'tax_percentage' => 16.00,
            'tax_amount' => 16.000,
            'grand_total' => 116.000,
            'start_date' => now()->toDateString(),
            'renewal_date' => now()->addMonth()->toDateString(),
            'status' => 'active',
        ], $overrides));
    }
}
