<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\ClientReviewItem;
use App\Models\Contract;
use App\Models\Installation;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use App\Support\ClientLifecycle;
use App\Support\FinancialPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ClientWorkspacePhase04Test extends TestCase
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

    /**
     * WORKSPACE RENDER TESTS
     */
    public function test_prospect_workspace_renders_identity_preferred_contact_stage_and_record_call_primary_action(): void
    {
        $client = $this->createClient([
            'business_name' => 'مطعم النجوم اللامعة',
            'business_category' => 'مطاعم وكافيهات',
            'city_area' => 'عمان - الجبيهة',
            'stage' => ClientLifecycle::PROSPECT,
        ]);
        $client->contacts()->create([
            'name' => 'خالد العلي',
            'primary_phone' => '0799887766',
            'role' => 'مدير التشغيل',
            'is_primary' => true,
        ]);

        $response = $this->actingAs($this->admin)->get(route('clients.show', $client));

        $response->assertOk()
            ->assertSee('مطعم النجوم اللامعة')
            ->assertSee('مطاعم وكافيهات')
            ->assertSee('عمان - الجبيهة')
            ->assertSee('خالد العلي')
            ->assertSee('0799887766')
            ->assertSee('فرصة جديدة')
            ->assertSee('تسجيل اتصال')
            ->assertSee('data-trigger-call-outcome', false);
    }

    public function test_appointment_scheduled_client_renders_appointment_context(): void
    {
        $client = $this->createClient(['stage' => ClientLifecycle::APPOINTMENT]);
        Appointment::create([
            'client_id' => $client->id,
            'appointment_date' => now()->addDay()->toDateString(),
            'appointment_time' => '14:00',
            'appointment_type' => 'physical_visit',
            'status' => 'scheduled',
        ]);

        $response = $this->actingAs($this->admin)->get(route('clients.show', $client));

        $response->assertOk()
            ->assertSee('موعد')
            ->assertSee('عرض الموعد');
    }

    public function test_installed_followup_client_renders_followup_context(): void
    {
        $client = $this->createClient(['stage' => ClientLifecycle::INSTALLED_FREE]);
        Installation::create([
            'client_id' => $client->id,
            'installed_by' => $this->admin->id,
            'status' => 'completed',
            'installed_at' => now()->subDays(2),
        ]);
        DB::table('follow_ups')->insert([
            'client_id' => $client->id,
            'user_id' => $this->admin->id,
            'method' => 'phone',
            'reason' => 'متابعة تشغيلية',
            'next_action' => 'اتصال بالعميل',
            'next_follow_up_date' => now()->addDay()->toDateString(),
            'follow_up_date_time' => now()->addDay()->toDateTimeString(),
            'notes' => 'متابعة بعد التركيب التجريبي',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->get(route('clients.show', $client));

        $response->assertOk()
            ->assertSee('تم التركيب المجاني')
            ->assertSee('تسجيل متابعة');
    }

    public function test_subscriber_workspace_renders_independent_product_subscription_cards(): void
    {
        $client = $this->createClient(['stage' => ClientLifecycle::SUBSCRIBER]);
        $product = Product::create(['name_ar' => 'نظام كور POS', 'name_en' => 'Notify Core POS', 'code' => 'CORE_POS']);
        $plan = Plan::create([
            'product_id' => $product->id,
            'name_ar' => 'الباقة الاحترافية',
            'name_en' => 'Pro Plan',
            'code' => 'CORE_PRO',
            'tier' => 1,
        ]);
        $this->createSubscription($client, $plan, [
            'start_date' => now()->subMonths(1)->toDateString(),
            'next_billing_date' => now()->addMonth()->toDateString(),
        ]);

        $response = $this->actingAs($this->admin)->get(route('clients.show', $client));

        $response->assertOk()
            ->assertSee('نظام كور POS')
            ->assertSee('الباقة الاحترافية')
            ->assertSee('نشط');
    }

    public function test_multi_product_subscriber_shows_multiple_product_cards_independently(): void
    {
        $client = $this->createClient(['stage' => ClientLifecycle::SUBSCRIBER]);

        $prod1 = Product::create(['name_ar' => 'نظام نقاط البيع POS', 'code' => 'PROD_POS']);
        $plan1 = Plan::create(['product_id' => $prod1->id, 'name_ar' => 'باقة المطاعم', 'code' => 'PLN_REST', 'tier' => 1]);
        $this->createSubscription($client, $plan1, [
            'start_date' => now()->subMonths(2)->toDateString(),
            'next_billing_date' => now()->addMonth()->toDateString(),
        ]);

        $prod2 = Product::create(['name_ar' => 'خدمة الواتساب التفاعلية', 'code' => 'PROD_WA']);
        $plan2 = Plan::create(['product_id' => $prod2->id, 'name_ar' => 'باقة الرسائل غير المحدودة', 'code' => 'PLN_WA_UNL', 'tier' => 1]);
        $this->createSubscription($client, $plan2, [
            'start_date' => now()->subMonth()->toDateString(),
            'next_billing_date' => now()->addMonths(2)->toDateString(),
        ]);

        $response = $this->actingAs($this->admin)->get(route('clients.show', $client));

        $response->assertOk()
            ->assertSee('نظام نقاط البيع POS')
            ->assertSee('باقة المطاعم')
            ->assertSee('خدمة الواتساب التفاعلية')
            ->assertSee('باقة الرسائل غير المحدودة');
    }

    public function test_closed_client_renders_reopen_as_primary_operational_action(): void
    {
        $client = $this->createClient([
            'stage' => ClientLifecycle::CLOSED,
            'status' => 'archived',
            'closed_reason' => 'عدم التفرغ',
        ]);

        $response = $this->actingAs($this->admin)->get(route('clients.show', $client));

        $response->assertOk()
            ->assertSee('مغلق')
            ->assertSee('إعادة فتح العميل')
            ->assertSee('#sec-reopen-client');
    }

    public function test_review_required_client_renders_review_oriented_action(): void
    {
        $client = $this->createClient(['stage' => ClientLifecycle::CONTACTING]);
        $review = ClientReviewItem::create([
            'client_id' => $client->id,
            'type' => ClientReviewItem::TYPE_WRONG_INVALID,
            'status' => ClientReviewItem::STATUS_PENDING,
            'notes' => 'الرقم مغلق تماماً يحتاج مراجعة',
        ]);

        $response = $this->actingAs($this->admin)->get(route('clients.show', $client));

        $response->assertOk()
            ->assertSee('مراجعة القرار')
            ->assertSee('#sec-review-item-' . $review->id);
    }

    /**
     * PERMISSIONS TESTS
     */
    public function test_staff_does_not_see_start_subscription_or_record_payment(): void
    {
        $client = $this->createClient(['stage' => ClientLifecycle::PROSPECT]);

        $response = $this->actingAs($this->staff)->get(route('clients.show', $client));

        $response->assertOk()
            ->assertDontSee('#sec-start-subscription')
            ->assertDontSee('#sec-record-payment');
    }

    public function test_admin_sees_authorized_financial_and_subscription_controls(): void
    {
        $client = $this->createClient(['stage' => ClientLifecycle::PROSPECT]);

        $response = $this->actingAs($this->admin)->get(route('clients.show', $client));

        $response->assertOk()
            ->assertSee('sec-start-subscription', false);
    }

    public function test_contract_links_respect_policy_and_routes(): void
    {
        $client = $this->createClient(['stage' => ClientLifecycle::SUBSCRIBER]);
        $product = Product::create(['name_ar' => 'Notify ERP', 'code' => 'ERP']);
        $plan = Plan::create(['product_id' => $product->id, 'name_ar' => 'ERP Basic', 'code' => 'ERP_BSC', 'tier' => 1]);
        $sub = $this->createSubscription($client, $plan);
        $contract = Contract::create([
            'client_id' => $client->id,
            'subscription_id' => $sub->id,
            'contract_number' => 'CNT-2026-9901',
            'generated_by' => $this->admin->id,
            'snapshot_data' => ['sample' => 'data'],
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->admin)->get(route('clients.show', $client));

        $response->assertOk()
            ->assertSee('CNT-2026-9901')
            ->assertSee(route('contracts.preview', $contract), false);
    }

    /**
     * FINANCIAL READ INTEGRITY TESTS
     */
    public function test_amount_due_comes_from_authoritative_receivable_projection(): void
    {
        $client = $this->createClient();
        Invoice::create([
            'client_id' => $client->id,
            'invoice_number' => 'INV-0099',
            'status' => Invoice::STATUS_ISSUED,
            'currency' => 'JOD',
            'total_minor' => 250000,
            'issue_date' => now()->subDays(5),
            'due_date' => now()->addDays(10),
        ]);

        $response = $this->actingAs($this->admin)->get(route('clients.show', $client));

        $response->assertOk()
            ->assertSee('250.000 د.أ')
            ->assertSee('المبلغ المستحق');
    }

    public function test_zero_due_state_is_rendered_correctly(): void
    {
        $client = $this->createClient();

        $response = $this->actingAs($this->admin)->get(route('clients.show', $client));

        $response->assertOk()
            ->assertSee('0.000 د.أ')
            ->assertSee('مسدد بالكامل');
    }

    public function test_rendering_workspace_causes_no_database_side_effects(): void
    {
        $client = $this->createClient();
        $invoicesCountBefore = Invoice::count();
        $paymentsCountBefore = DB::table('payments')->count();
        $subscriptionsCountBefore = Subscription::count();

        $this->actingAs($this->admin)->get(route('clients.show', $client));

        $this->assertSame($invoicesCountBefore, Invoice::count());
        $this->assertSame($paymentsCountBefore, DB::table('payments')->count());
        $this->assertSame($subscriptionsCountBefore, Subscription::count());
    }

    /**
     * HISTORY AND ACTIVITIES TESTS
     */
    public function test_last_3_activities_render_most_recent_first(): void
    {
        $client = $this->createClient();
        DB::table('activity_logs')->insert([
            ['client_id' => $client->id, 'user_id' => $this->admin->id, 'type' => 'client_created', 'description' => 'النشاط الأول قديم', 'created_at' => now()->subHours(3), 'updated_at' => now()->subHours(3)],
            ['client_id' => $client->id, 'user_id' => $this->admin->id, 'type' => 'contact_attempt', 'description' => 'النشاط الثاني متوسط', 'created_at' => now()->subHours(2), 'updated_at' => now()->subHours(2)],
            ['client_id' => $client->id, 'user_id' => $this->admin->id, 'type' => 'stage_changed', 'description' => 'النشاط الثالث حديث', 'created_at' => now()->subHour(), 'updated_at' => now()->subHour()],
            ['client_id' => $client->id, 'user_id' => $this->admin->id, 'type' => 'note_added', 'description' => 'النشاط الأحدث جداً', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $response = $this->actingAs($this->admin)->get(route('clients.show', $client));

        $response->assertOk();
        $vm = $response->viewData('clientWorkspaceViewModel');
        $this->assertCount(3, $vm->recentActivities);
        $this->assertSame('النشاط الأحدث جداً', $vm->recentActivities[0]['description']);
        $this->assertSame('النشاط الثالث حديث', $vm->recentActivities[1]['description']);
        $this->assertSame('النشاط الثاني متوسط', $vm->recentActivities[2]['description']);
    }

    public function test_full_history_remains_accessible_in_collapsible_section(): void
    {
        $client = $this->createClient();
        Appointment::create([
            'client_id' => $client->id,
            'appointment_date' => now()->subDays(10)->toDateString(),
            'appointment_time' => '11:00',
            'appointment_type' => 'physical_visit',
            'status' => 'completed',
            'notes' => 'زيارة سابقة مكتملة',
        ]);

        $response = $this->actingAs($this->admin)->get(route('clients.show', $client));

        $response->assertOk()
            ->assertSee('collapsible-history', false)
            ->assertSee('السجل الكامل للعميل')
            ->assertSee('زيارة سابقة مكتملة');
    }

    private function createClient(array $overrides = []): Client
    {
        return Client::create(array_merge([
            'business_name' => 'مطعم الأصالة',
            'phone' => '0795551234',
            'business_phone' => '0795551234',
            'contact_person' => 'عمر',
            'city_area' => 'عمان - الدوار السابع',
            'city' => 'Amman',
            'area' => '7th Circle',
            'business_category' => 'خدمات وأغذية',
            'business_type' => 'مطعم',
            'lead_source' => 'ميداني',
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
