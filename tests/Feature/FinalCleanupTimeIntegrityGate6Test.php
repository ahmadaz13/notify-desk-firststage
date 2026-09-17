<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\User;
use App\Services\ClientOperationalWorkflowService;
use App\Services\DailyOperationalService;
use App\Services\PaymentScheduleService;
use App\Services\SubscriptionBillingService;
use App\Support\ClientLifecycle;
use Carbon\Carbon;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FinalCleanupTimeIntegrityGate6Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->seed(SettingsSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_application_clock_and_today_use_asia_amman(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 1, 2, 0, 30, 0, 'Asia/Amman'));

        $this->assertSame('Asia/Amman', config('app.timezone'));
        $this->assertSame('Asia/Amman', now()->timezoneName);
        $this->assertSame('2026-01-02', today()->toDateString());
    }

    public function test_appointment_callback_and_today_queue_preserve_jordan_business_time(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 1, 2, 0, 30, 0, 'Asia/Amman'));
        $user = $this->founder();
        $appointmentClient = $this->client('Appointment');
        $callbackClient = $this->client('Callback');
        $workflow = app(ClientOperationalWorkflowService::class);

        $appointment = $workflow->recordContactOutcome($appointmentClient, $user, [
            'method' => 'phone',
            'result' => 'appointment',
            'appointment_date' => '2026-01-02',
            'appointment_time' => '00:45',
            'appointment_type' => 'sales_meeting',
            'attendees' => [$user->id],
        ])['appointment'];
        $callbackId = $workflow->recordContactOutcome($callbackClient, $user, [
            'method' => 'phone',
            'result' => 'callback_later',
            'follow_up_date_time' => '2026-01-02 01:15:00',
        ])['follow_up_id'];

        $this->assertSame('2026-01-02', $appointment->fresh()->appointment_date->toDateString());
        $this->assertSame('00:45', substr((string) $appointment->fresh()->appointment_time, 0, 5));
        $this->assertSame('2026-01-02 01:15:00', DB::table('follow_ups')->find($callbackId)->follow_up_date_time);
        $this->assertTrue(app(DailyOperationalService::class)->getTodayAppointments($user)->contains($appointment));
    }

    public function test_month_end_leap_year_and_installment_dates_use_calendar_arithmetic_without_date_shift(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 1, 1, 9, 0, 0, 'Asia/Amman'));
        $user = $this->founder();
        $monthlyPrice = $this->price('gate6_monthly', PlanPrice::MONTHLY, 10000, $user);
        $annualPrice = $this->price('gate6_annual', PlanPrice::ANNUAL, 120000, $user);
        $billing = app(SubscriptionBillingService::class);

        [$monthly] = $billing->startPaidSubscription(
            $this->client('Month End'),
            $monthlyPrice,
            ['quantity' => 1, 'start_date' => '2026-01-31'],
            $user->id
        );
        [$annual] = $billing->startPaidSubscription(
            $this->client('Leap Year'),
            $annualPrice,
            ['quantity' => 1, 'start_date' => '2028-02-29', 'payment_terms' => 'full'],
            $user->id
        );
        $schedule = app(PaymentScheduleService::class)->previewAnnualInstallments(120000, 3, '2026-01-31', 30);

        $this->assertSame('2026-01-31', $monthly->start_date->toDateString());
        $this->assertSame('2026-02-28', $monthly->next_billing_date->toDateString());
        $this->assertSame('2028-02-29', $annual->start_date->toDateString());
        $this->assertSame('2029-02-28', $annual->next_billing_date->toDateString());
        $this->assertSame(['2026-01-31', '2026-02-28', '2026-03-30'], array_column($schedule, 'due_date'));
    }

    public function test_preferred_contact_prioritizes_owner_then_manager_then_business_fallback(): void
    {
        $client = $this->client('Contact Priority', [
            'phone' => '0790000000',
            'business_phone' => '064440000',
            'contact_person' => null,
        ]);
        ClientContact::create([
            'client_id' => $client->id,
            'name' => 'Manager Name',
            'role' => 'manager',
            'primary_phone' => '0791111111',
            'is_primary' => true,
        ]);
        ClientContact::create([
            'client_id' => $client->id,
            'name' => 'Owner Name',
            'role' => 'owner',
            'primary_phone' => '0792222222',
            'is_primary' => false,
        ]);

        $preferred = $client->fresh('contacts')->preferredOperationalContact();
        $this->assertSame('Owner Name', $preferred['name']);
        $this->assertSame('0792222222', $preferred['phone']);
        $this->assertFalse($preferred['uses_business_fallback']);

        $fallbackClient = $this->client('Business Fallback', [
            'phone' => '064445555',
            'business_phone' => '064445555',
            'contact_person' => null,
        ]);
        $fallback = $fallbackClient->preferredOperationalContact();
        $this->assertSame('064445555', $fallback['phone']);
        $this->assertTrue($fallback['uses_business_fallback']);
    }

    public function test_client_create_synchronizes_primary_decision_maker_without_schema_change(): void
    {
        $user = $this->founder();

        $this->actingAs($user)->post(route('clients.store'), [
            'business_name' => 'Gate 6 Created Client',
            'business_category' => 'Retail',
            'business_phone' => '064441111',
            'city_area' => 'Amman',
            'contact_person' => 'Owner Person',
            'phone' => '0793333333',
            'primary_contact_role' => 'owner',
            'lead_source' => 'Direct Prospecting',
        ])->assertRedirect();

        $client = Client::where('business_name', 'Gate 6 Created Client')->firstOrFail();
        $contact = $client->contacts()->sole();
        $this->assertTrue($contact->is_primary);
        $this->assertSame('owner', $contact->role);
        $this->assertSame('0793333333', $contact->primary_phone);
        $this->assertSame('064441111', $client->business_phone);
    }

    private function founder(): User
    {
        return User::factory()->create(['role' => User::ROLE_FOUNDER, 'is_active' => true]);
    }

    private function client(string $name, array $overrides = []): Client
    {
        return Client::create(array_merge([
            'business_name' => 'Gate 6 '.$name,
            'phone' => '079'.random_int(1000000, 9999999),
            'business_phone' => '06'.random_int(4000000, 4999999),
            'city_area' => 'Amman',
            'business_category' => 'Technology',
            'lead_source' => 'Direct',
            'status' => 'prospect',
            'stage' => ClientLifecycle::PROSPECT,
        ], $overrides));
    }

    private function price(string $code, string $interval, int $amountMinor, User $user): PlanPrice
    {
        $plan = Plan::create([
            'code' => $code,
            'name_ar' => $code,
            'name_en' => $code,
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        return PlanPrice::create([
            'plan_id' => $plan->id,
            'billing_interval' => $interval,
            'currency' => PlanPrice::CURRENCY,
            'amount_minor' => $amountMinor,
            'setup_fee_minor' => 0,
            'included_branch_quantity' => 1,
            'effective_from' => now()->subDay(),
            'is_active' => true,
            'created_by' => $user->id,
        ])->load('plan');
    }
}
