<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use App\Services\AccountingSetupService;
use App\Services\NotificationService;
use App\Support\AppointmentTypes;
use App\Support\ClientLifecycle;
use Carbon\Carbon;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Phase10FreezeCandidateTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $staff;
    protected User $inactiveUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingsSeeder::class);
        app(AccountingSetupService::class)->ensureSeeded();

        $this->admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);

        $this->staff = User::factory()->create([
            'role' => User::ROLE_STAFF,
            'is_active' => true,
        ]);

        $this->inactiveUser = User::factory()->create([
            'role' => User::ROLE_STAFF,
            'is_active' => false,
        ]);
    }

    protected function createClient(array $attributes = []): Client
    {
        return Client::create(array_merge([
            'business_name' => 'Test Business',
            'business_category' => 'Restaurant',
            'phone' => '0791234567',
            'city_area' => 'Amman',
            'lead_source' => 'Direct',
            'stage' => ClientLifecycle::PROSPECT,
            'status' => 'prospect',
        ], $attributes));
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get(route('work'))->assertRedirect(route('login'));
        $this->get(route('clients.index'))->assertRedirect(route('login'));
        $this->get(route('finance.index'))->assertRedirect(route('login'));
        $this->get(route('accounting.index'))->assertRedirect(route('login'));
    }

    public function test_inactive_user_is_forbidden_on_all_surfaces(): void
    {
        $this->actingAs($this->inactiveUser)->get(route('dashboard'))->assertForbidden();
        $this->actingAs($this->inactiveUser)->get(route('work'))->assertForbidden();
        $this->actingAs($this->inactiveUser)->get(route('clients.index'))->assertForbidden();
    }

    public function test_staff_has_daily_access_but_is_forbidden_from_financial_and_accounting_actions(): void
    {
        $client = $this->createClient([
            'business_name' => 'Amman Bistro',
            'phone' => '0799000001',
            'assigned_user_id' => $this->staff->id,
            'created_by' => $this->staff->id,
        ]);

        // Daily surfaces accessible
        $this->actingAs($this->staff)->get(route('dashboard'))->assertOk();
        $this->actingAs($this->staff)->get(route('work'))->assertOk();
        $this->actingAs($this->staff)->get(route('clients.index'))->assertOk();
        $this->actingAs($this->staff)->get(route('clients.show', $client))->assertOk();

        // Guided Financial / Subscription actions forbidden for staff
        $this->actingAs($this->staff)->getJson(route('clients.guided-subscription.catalog', $client))->assertForbidden();
        $this->actingAs($this->staff)->postJson(route('clients.guided-subscription.store', $client), [])->assertForbidden();
        $this->actingAs($this->staff)->post(route('clients.payments.normal.store', $client), [])->assertForbidden();

        // Advanced financial management routes forbidden for staff
        $this->actingAs($this->staff)->get(route('finance.index'))->assertForbidden();
        $this->actingAs($this->staff)->get(route('accounting.index'))->assertForbidden();
        $this->actingAs($this->staff)->get(route('financial-accounts.index'))->assertForbidden();
    }

    public function test_completed_follow_ups_are_excluded_from_open_work_and_remain_in_history(): void
    {
        Carbon::setTestNow('2026-09-18 10:00:00');

        $client = $this->createClient([
            'business_name' => 'Royal Cafe',
            'phone' => '0799000002',
            'assigned_user_id' => $this->admin->id,
            'created_by' => $this->admin->id,
        ]);

        $completedFollowUpId = DB::table('follow_ups')->insertGetId([
            'client_id' => $client->id,
            'user_id' => $this->admin->id,
            'method' => 'phone_call',
            'reason' => 'Completed initial check-in',
            'next_action' => 'Call done',
            'next_follow_up_date' => '2026-09-17',
            'follow_up_date_time' => '2026-09-17 10:00:00',
            'completed_at' => now()->subHours(2),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $openFollowUpId = DB::table('follow_ups')->insertGetId([
            'client_id' => $client->id,
            'user_id' => $this->admin->id,
            'method' => 'phone_call',
            'reason' => 'Pending review discussion',
            'next_action' => 'Discuss proposal',
            'next_follow_up_date' => '2026-09-18',
            'follow_up_date_time' => '2026-09-18 15:00:00',
            'completed_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Today view contains open follow-up, but never completed follow-up
        $response = $this->actingAs($this->admin)->get(route('dashboard'));
        $response->assertOk();
        $response->assertSee('Pending review discussion');
        $response->assertDontSee('Completed initial check-in');

        // Work view contains open follow-up, but never completed follow-up
        $workResponse = $this->actingAs($this->admin)->get(route('work'));
        $workResponse->assertOk();
        $workResponse->assertSee('Pending review discussion');
        $workResponse->assertDontSee('Completed initial check-in');

        // Notification service does not resurrect completed follow-up
        app(NotificationService::class)->sendOperationalReminders(now());
        $this->assertDatabaseMissing('notifications', [
            'source_type' => 'follow_up',
            'source_id' => $completedFollowUpId,
        ]);

        // Client history retains the completed follow-up
        $this->assertDatabaseHas('follow_ups', [
            'id' => $completedFollowUpId,
        ]);
        $this->assertNotNull(DB::table('follow_ups')->where('id', $completedFollowUpId)->value('completed_at'));
    }

    public function test_client_can_own_multiple_different_products_and_duplicate_active_same_product_is_prevented(): void
    {
        $client = $this->createClient([
            'business_name' => 'Multi-Product Enterprise',
            'phone' => '0799000003',
            'stage' => ClientLifecycle::SUBSCRIBER,
            'status' => 'active',
            'assigned_user_id' => $this->admin->id,
            'created_by' => $this->admin->id,
        ]);

        $productA = Product::firstOrCreate(
            ['code' => 'pos_system'],
            [
                'name_ar' => 'نظام نقاط البيع',
                'name_en' => 'POS System',
                'is_active' => true,
                'created_by' => $this->admin->id,
            ]
        );

        $productB = Product::firstOrCreate(
            ['code' => 'kds_system'],
            [
                'name_ar' => 'شاشة المطبخ KDS',
                'name_en' => 'KDS Display',
                'is_active' => true,
                'created_by' => $this->admin->id,
            ]
        );

        $planA = Plan::firstOrCreate(
            ['code' => 'pos_pro'],
            [
                'product_id' => $productA->id,
                'name_ar' => 'باقة نقاط البيع المتقدمة',
                'name_en' => 'POS Pro',
                'tier' => 1,
                'offer_type' => 'package',
                'is_active' => true,
                'created_by' => $this->admin->id,
            ]
        );

        $planB = Plan::firstOrCreate(
            ['code' => 'kds_pro'],
            [
                'product_id' => $productB->id,
                'name_ar' => 'باقة شاشة المطبخ',
                'name_en' => 'KDS Ultra',
                'tier' => 1,
                'offer_type' => 'package',
                'is_active' => true,
                'created_by' => $this->admin->id,
            ]
        );

        $priceA = PlanPrice::create([
            'plan_id' => $planA->id,
            'billing_interval' => PlanPrice::MONTHLY,
            'currency' => PlanPrice::CURRENCY,
            'amount_minor' => 50000,
            'is_active' => true,
            'effective_from' => now()->subDay(),
            'created_by' => $this->admin->id,
        ]);

        $priceB = PlanPrice::create([
            'plan_id' => $planB->id,
            'billing_interval' => PlanPrice::MONTHLY,
            'currency' => PlanPrice::CURRENCY,
            'amount_minor' => 30000,
            'is_active' => true,
            'effective_from' => now()->subDay(),
            'created_by' => $this->admin->id,
        ]);

        // Create Subscription 1 for Product A using guided store
        $this->actingAs($this->admin)->post(route('clients.guided-subscription.store', $client->id), [
            'product_id' => $productA->id,
            'plan_id' => $planA->id,
            'billing_interval' => 'monthly',
        ])->assertRedirect();

        // Create Subscription 2 for Product B (allowed because different product)
        $this->actingAs($this->admin)->post(route('clients.guided-subscription.store', $client->id), [
            'product_id' => $productB->id,
            'plan_id' => $planB->id,
            'billing_interval' => 'monthly',
        ])->assertRedirect();

        $this->assertDatabaseCount('subscriptions', 2);

        // Workspace renders both products distinctly
        $wsResponse = $this->actingAs($this->admin)
            ->withSession(['locale' => 'en'])
            ->get(route('clients.show', $client->id));
        $wsResponse->assertOk();
        $wsResponse->assertSee('POS System');
        $wsResponse->assertSee('KDS Display');

        // Attempting to start another subscription for Product A through guided subscription should be rejected
        $duplicateResponse = $this->actingAs($this->admin)->post(route('clients.guided-subscription.store', $client->id), [
            'product_id' => $productA->id,
            'plan_id' => $planA->id,
            'billing_interval' => 'monthly',
        ]);

        $duplicateResponse->assertSessionHasErrors(['product_id']);
        $this->assertDatabaseCount('subscriptions', 2);
    }

    public function test_rendering_today_and_work_does_not_mutate_domain_state(): void
    {
        $client = $this->createClient([
            'business_name' => 'Zero Mutation Bistro',
            'phone' => '0799000004',
            'assigned_user_id' => $this->admin->id,
            'created_by' => $this->admin->id,
        ]);

        Appointment::create([
            'client_id' => $client->id,
            'user_id' => $this->admin->id,
            'appointment_date' => now()->toDateString(),
            'appointment_time' => '11:00:00',
            'appointment_type' => AppointmentTypes::PHYSICAL_VISIT,
            'status' => 'scheduled',
            'notes' => 'Zero Mutation Meeting',
        ]);

        $clientsCountBefore = Client::count();
        $appointmentsCountBefore = Appointment::count();
        $followUpsCountBefore = DB::table('follow_ups')->count();
        $subscriptionsCountBefore = Subscription::count();

        // Render Today
        $this->actingAs($this->admin)->get(route('dashboard'))->assertOk();

        // Render Work
        $this->actingAs($this->admin)->get(route('work'))->assertOk();

        // Assert zero mutation
        $this->assertSame($clientsCountBefore, Client::count());
        $this->assertSame($appointmentsCountBefore, Appointment::count());
        $this->assertSame($followUpsCountBefore, DB::table('follow_ups')->count());
        $this->assertSame($subscriptionsCountBefore, Subscription::count());
    }
}
