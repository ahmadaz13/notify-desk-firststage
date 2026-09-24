<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\ClientReviewItem;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use App\Services\FollowUpService;
use App\Services\UnifiedOperationalWorkProjection;
use App\Support\AppointmentTypes;
use App\Support\ClientLifecycle;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Phase08TodayAndWorkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-18 10:00:00');
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

    protected function createSubscription(Client $client, Plan $plan, array $overrides = []): Subscription
    {
        return Subscription::create(array_merge([
            'client_id' => $client->id,
            'plan_id' => $plan->id,
            'user_id' => $overrides['user_id'] ?? 1,
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

    public function test_today_has_no_kpi_dashboard_dependency(): void
    {
        $staff = User::factory()->create([
            'role' => User::ROLE_STAFF,
            'is_active' => true,
        ]);

        $response = $this->actingAs($staff)
            ->withSession(['locale' => 'en'])
            ->get(route('dashboard', ['mode' => 'daily']))
            ->assertOk();

        // No revenue / cash / KPI tiles or widgets
        $response->assertDontSee('Monthly Net Cash', false)
            ->assertDontSee('Cash Snapshot', false)
            ->assertDontSee('Gross Revenue', false)
            ->assertDontSee('MRR', false)
            ->assertDontSee('ARR', false);
    }

    public function test_today_groups_overdue_next_and_later_today_correctly(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_FOUNDER,
            'is_active' => true,
        ]);

        $client = $this->createClient([
            'business_name' => 'Al-Mawsem Restaurant',
            'stage' => ClientLifecycle::PROSPECT,
            'status' => 'prospect',
        ]);

        // 1. Overdue appointment (earlier today at 08:30, current test time is 10:00)
        $overdueApt = Appointment::create([
            'client_id' => $client->id,
            'appointment_date' => '2026-09-18',
            'appointment_time' => '08:30:00',
            'appointment_type' => AppointmentTypes::PHYSICAL_VISIT,
            'status' => 'scheduled',
            'notes' => 'Overdue Morning Meeting',
        ]);

        // 2. Next appointment (within next 2 hours: at 11:30)
        $nextApt = Appointment::create([
            'client_id' => $client->id,
            'appointment_date' => '2026-09-18',
            'appointment_time' => '11:30:00',
            'appointment_type' => AppointmentTypes::ONLINE_DEMO,
            'status' => 'scheduled',
            'notes' => 'Next Demo Visit',
        ]);

        // 3. Later Today appointment (at 16:00, after the 2-hour window)
        $laterApt = Appointment::create([
            'client_id' => $client->id,
            'appointment_date' => '2026-09-18',
            'appointment_time' => '16:00:00',
            'appointment_type' => AppointmentTypes::PHYSICAL_VISIT,
            'status' => 'scheduled',
            'notes' => 'Evening Discussion',
        ]);

        // 4. Future appointment (tomorrow, should NOT appear on Today)
        $futureApt = Appointment::create([
            'client_id' => $client->id,
            'appointment_date' => '2026-09-19',
            'appointment_time' => '10:00:00',
            'appointment_type' => AppointmentTypes::PHYSICAL_VISIT,
            'status' => 'scheduled',
            'notes' => 'Tomorrow Meeting',
        ]);

        /** @var UnifiedOperationalWorkProjection $service */
        $service = app(UnifiedOperationalWorkProjection::class);
        $projection = $service->today($user, Carbon::now('Asia/Amman'));

        $this->assertCount(1, $projection['overdue']);
        $this->assertSame("apt-{$overdueApt->id}", $projection['overdue']->first()['id']);

        $this->assertTrue($projection['next']->contains('id', "apt-{$nextApt->id}"));

        $this->assertCount(1, $projection['later_today']);
        $this->assertSame("apt-{$laterApt->id}", $projection['later_today']->first()['id']);

        // Future appointment excluded from Today
        $allTodayIds = $projection['overdue']->pluck('id')
            ->concat($projection['next']->pluck('id'))
            ->concat($projection['later_today']->pluck('id'));
        $this->assertFalse($allTodayIds->contains("apt-{$futureApt->id}"));

        // Verify HTML rendering on Today page
        $response = $this->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->get(route('dashboard', ['mode' => 'daily']))
            ->assertOk();

        $response->assertSee('Overdue Morning Meeting')
            ->assertSee('Next Demo Visit')
            ->assertSee('Evening Discussion')
            ->assertDontSee('Tomorrow Meeting');
    }

    public function test_work_shows_overdue_today_upcoming_groups(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_STAFF,
            'is_active' => true,
        ]);

        $client = $this->createClient([
            'business_name' => 'Amman Pharmacy',
            'stage' => ClientLifecycle::CONTACTING,
            'status' => 'prospect',
        ]);

        // Yesterday's appointment (Overdue)
        Appointment::create([
            'client_id' => $client->id,
            'appointment_date' => '2026-09-17',
            'appointment_time' => '14:00:00',
            'appointment_type' => AppointmentTypes::PHYSICAL_VISIT,
            'status' => 'scheduled',
            'notes' => 'Yesterday Unattended',
        ]);

        // Today's appointment (Today)
        Appointment::create([
            'client_id' => $client->id,
            'appointment_date' => '2026-09-18',
            'appointment_time' => '14:00:00',
            'appointment_type' => AppointmentTypes::PHYSICAL_VISIT,
            'status' => 'scheduled',
            'notes' => 'Today Afternoon',
        ]);

        // Next week's appointment (Upcoming)
        Appointment::create([
            'client_id' => $client->id,
            'appointment_date' => '2026-09-25',
            'appointment_time' => '11:00:00',
            'appointment_type' => AppointmentTypes::PHYSICAL_VISIT,
            'status' => 'scheduled',
            'notes' => 'Next Week Discussion',
        ]);

        $response = $this->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->get(route('dashboard', ['mode' => 'work']))
            ->assertOk();

        $response->assertSee('Yesterday Unattended')
            ->assertSee('Today Afternoon')
            ->assertSee('Next Week Discussion');
    }

    public function test_work_filters_by_category(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_FOUNDER,
            'is_active' => true,
        ]);

        $client = $this->createClient([
            'business_name' => 'Petra Retail',
            'stage' => ClientLifecycle::PROSPECT,
            'status' => 'prospect',
        ]);

        // 1. Regular Appointment
        $apt = Appointment::create([
            'client_id' => $client->id,
            'appointment_date' => '2026-09-18',
            'appointment_time' => '12:00:00',
            'appointment_type' => AppointmentTypes::PHYSICAL_VISIT,
            'status' => 'scheduled',
            'notes' => 'General Sales Meeting',
        ]);

        // 2. Installation Appointment
        $inst = Appointment::create([
            'client_id' => $client->id,
            'appointment_date' => '2026-09-18',
            'appointment_time' => '15:00:00',
            'appointment_type' => AppointmentTypes::INSTALLATION,
            'status' => 'scheduled',
            'notes' => 'Free Pos Installation',
        ]);

        // 3. Follow-up
        $followUpId = DB::table('follow_ups')->insertGetId([
            'client_id' => $client->id,
            'user_id' => $user->id,
            'method' => 'phone_call',
            'reason' => 'Contract Review Followup',
            'next_action' => 'Call manager',
            'next_follow_up_date' => '2026-09-18',
            'follow_up_date_time' => '2026-09-18 11:00:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Filter: Appointments only
        $aptResponse = $this->actingAs($user)
            ->get(route('dashboard', ['mode' => 'work', 'filter' => 'appointments']))
            ->assertOk();
        $aptResponse->assertSee('General Sales Meeting')
            ->assertDontSee('Free Pos Installation');

        // Filter: Installations only
        $instResponse = $this->actingAs($user)
            ->get(route('dashboard', ['mode' => 'work', 'filter' => 'installations']))
            ->assertOk();
        $instResponse->assertSee('Free Pos Installation')
            ->assertDontSee('General Sales Meeting');

        // Filter: Follow-ups only
        $fuResponse = $this->actingAs($user)
            ->get(route('dashboard', ['mode' => 'work', 'filter' => 'follow_ups']))
            ->assertOk();
        $fuResponse->assertSee('Contract Review Followup')
            ->assertDontSee('General Sales Meeting');

        // Filter: Calls
        $callResponse = $this->actingAs($user)
            ->get(route('dashboard', ['mode' => 'work', 'filter' => 'calls']))
            ->assertOk();
        $callResponse->assertSee('Contract Review Followup');
    }

    public function test_collections_filter_gives_owner_payment_and_staff_receipt_paths(): void
    {
        $founder = User::factory()->create([
            'role' => User::ROLE_FOUNDER,
            'is_active' => true,
        ]);

        $staff = User::factory()->create([
            'role' => User::ROLE_STAFF,
            'is_active' => true,
        ]);

        $client = $this->createClient([
            'business_name' => 'Jerash Supermarket',
            'stage' => ClientLifecycle::SUBSCRIBER,
            'status' => 'subscriber',
        ]);

        $product = Product::create([
            'name_ar' => 'نظام كور POS',
            'name_en' => 'Notify Core POS',
            'code' => 'CORE_POS',
        ]);
        $plan = Plan::create([
            'product_id' => $product->id,
            'name_ar' => 'الباقة الاحترافية',
            'name_en' => 'Pro Plan',
            'code' => 'CORE_PRO',
            'tier' => 1,
        ]);
        $planPrice = PlanPrice::create([
            'plan_id' => $plan->id,
            'billing_interval' => 'monthly',
            'amount_minor' => 15000,
            'currency' => 'JOD',
            'effective_from' => now()->subYear(),
            'is_active' => true,
        ]);
        $subscription = $this->createSubscription($client, $plan, [
            'user_id' => $founder->id,
            'plan_price_id' => $planPrice->id,
        ]);

        // Overdue unpaid invoice
        Invoice::create([
            'client_id' => $client->id,
            'subscription_id' => $subscription->id,
            'invoice_number' => 'INV-2026-9901',
            'status' => Invoice::STATUS_ISSUED,
            'total_minor' => 15000,
            'subtotal_minor' => 15000,
            'tax_minor' => 0,
            'discount_minor' => 0,
            'currency' => 'JOD',
            'due_date' => '2026-09-15',
            'issue_date' => '2026-09-01',
        ]);

        // Founder sees the client's collection work with the confirmed-payment path.
        $founderResponse = $this->actingAs($founder)
            ->get(route('dashboard', ['mode' => 'work', 'filter' => 'collections']))
            ->assertOk();
        $founderResponse->assertSee('Jerash Supermarket')
            ->assertSee('data-card-primary-action="payment"', false);

        // P11 (§9.7, D-07): Staff get operational collections too — per-client amount due and the
        // "Payment received" receipt path only; no invoice numbers or company totals.
        $staffResponse = $this->actingAs($staff)
            ->get(route('dashboard', ['mode' => 'work']))
            ->assertOk();
        $staffResponse->assertSee('filter=collections', false)
            ->assertSee('Jerash Supermarket')
            ->assertSee('data-card-primary-action="payment_receipt"', false)
            ->assertDontSee('data-card-primary-action="payment"', false)
            ->assertDontSee('INV-2026-9901');

        $staffCollectionsResponse = $this->actingAs($staff)
            ->get(route('dashboard', ['mode' => 'work', 'filter' => 'collections']))
            ->assertOk();
        $staffCollectionsResponse->assertSee('Jerash Supermarket')
            ->assertDontSee('INV-2026-9901');
    }

    public function test_completed_follow_ups_never_appear_as_open_work(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_STAFF,
            'is_active' => true,
        ]);

        $client = $this->createClient([
            'business_name' => 'Aqaba Marina Cafe',
            'stage' => ClientLifecycle::CONTACTING,
            'status' => 'prospect',
        ]);

        // Completed follow-up
        DB::table('follow_ups')->insert([
            'client_id' => $client->id,
            'user_id' => $user->id,
            'method' => 'phone_call',
            'reason' => 'Already Handled Followup',
            'next_action' => 'None',
            'next_follow_up_date' => '2026-09-18',
            'follow_up_date_time' => '2026-09-18 09:00:00',
            'completed_at' => '2026-09-18 09:30:00',
            'completed_by' => $user->id,
            'completion_outcome' => FollowUpService::OUTCOME_SUBSCRIBE,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $todayResponse = $this->actingAs($user)
            ->get(route('dashboard', ['mode' => 'daily']))
            ->assertOk();
        $todayResponse->assertDontSee('Already Handled Followup');

        $workResponse = $this->actingAs($user)
            ->get(route('dashboard', ['mode' => 'work']))
            ->assertOk();
        $workResponse->assertDontSee('Already Handled Followup');
    }

    public function test_cancelled_and_completed_appointments_do_not_appear(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_STAFF,
            'is_active' => true,
        ]);

        $client = $this->createClient([
            'business_name' => 'Madaba Bakery',
            'stage' => ClientLifecycle::CONTACTING,
            'status' => 'prospect',
        ]);

        Appointment::create([
            'client_id' => $client->id,
            'appointment_date' => '2026-09-18',
            'appointment_time' => '10:00:00',
            'appointment_type' => AppointmentTypes::PHYSICAL_VISIT,
            'status' => 'cancelled',
            'notes' => 'Cancelled Madaba Visit',
        ]);

        Appointment::create([
            'client_id' => $client->id,
            'appointment_date' => '2026-09-18',
            'appointment_time' => '11:00:00',
            'appointment_type' => AppointmentTypes::PHYSICAL_VISIT,
            'status' => 'completed',
            'notes' => 'Completed Madaba Visit',
        ]);

        $response = $this->actingAs($user)
            ->get(route('dashboard', ['mode' => 'daily']))
            ->assertOk();

        $response->assertDontSee('Cancelled Madaba Visit')
            ->assertDontSee('Completed Madaba Visit');
    }

    public function test_review_required_items_project_correctly(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_FOUNDER,
            'is_active' => true,
        ]);

        $client = $this->createClient([
            'business_name' => 'Salt Crafts',
            'stage' => ClientLifecycle::CONTACTING,
            'status' => 'prospect',
        ]);

        ClientReviewItem::create([
            'client_id' => $client->id,
            'type' => ClientReviewItem::TYPE_NOT_INTERESTED,
            'note' => 'Client reported invalid phone',
            'status' => ClientReviewItem::STATUS_PENDING,
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->get(route('dashboard', ['mode' => 'work']))
            ->assertOk();

        $response->assertSee('Client reported invalid phone')
            ->assertSee('Salt Crafts');
    }

    public function test_no_domain_writes_occur_during_rendering(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_FOUNDER,
            'is_active' => true,
        ]);

        $client = $this->createClient([
            'business_name' => 'Zerqa Motors',
            'stage' => ClientLifecycle::PROSPECT,
            'status' => 'prospect',
        ]);

        Appointment::create([
            'client_id' => $client->id,
            'appointment_date' => '2026-09-18',
            'appointment_time' => '11:00:00',
            'appointment_type' => AppointmentTypes::PHYSICAL_VISIT,
            'status' => 'scheduled',
        ]);

        $clientsCountBefore = Client::count();
        $appointmentsCountBefore = Appointment::count();
        $followUpsCountBefore = DB::table('follow_ups')->count();
        $activityLogsCountBefore = DB::table('activity_logs')->count();

        $this->actingAs($user)->get(route('dashboard', ['mode' => 'daily']))->assertOk();
        $this->actingAs($user)->get(route('dashboard', ['mode' => 'work']))->assertOk();

        $this->assertSame($clientsCountBefore, Client::count());
        $this->assertSame($appointmentsCountBefore, Appointment::count());
        $this->assertSame($followUpsCountBefore, DB::table('follow_ups')->count());
        $this->assertSame($activityLogsCountBefore, DB::table('activity_logs')->count());
    }

    public function test_dedicated_work_route_renders_correctly(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_STAFF,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->get(route('dashboard', ['mode' => 'work']))
            ->assertOk();

        $response->assertSee('Open Work');
    }
}
