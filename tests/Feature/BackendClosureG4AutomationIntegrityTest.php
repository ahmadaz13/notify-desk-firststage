<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\JournalEntry;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\RecurringExpenseObligation;
use App\Models\RecurringExpenseTemplate;
use App\Models\RevenueRecognitionSchedule;
use App\Models\Subscription;
use App\Models\SubscriptionBillingPeriod;
use App\Models\User;
use App\Services\AccountingSetupService;
use App\Services\NotificationService;
use App\Services\SubscriptionBillingService;
use App\Support\AppointmentTypes;
use App\Support\ClientLifecycle;
use App\Support\Money;
use Carbon\Carbon;
use Database\Seeders\CommercialCatalogSeeder;
use Database\Seeders\ServiceCatalogSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BackendClosureG4AutomationIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-15 09:00:00');
        config(['app.timezone' => 'Asia/Amman']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_operational_reminders_are_internal_only_and_idempotent(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $admin = User::factory()->create(['role' => 'founder']);
        $partner = User::factory()->create(['role' => 'partner']);

        $appointmentClient = $this->client(['primary_owner_id' => $admin->id, 'stage' => ClientLifecycle::APPOINTMENT]);
        $appointmentId = $this->appointment($appointmentClient, AppointmentTypes::PHYSICAL_VISIT);
        DB::table('appointment_user')->insert([
            ['appointment_id' => $appointmentId, 'user_id' => $staff->id, 'created_at' => now(), 'updated_at' => now()],
            ['appointment_id' => $appointmentId, 'user_id' => $partner->id, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $installationClient = $this->client(['primary_owner_id' => $admin->id, 'stage' => ClientLifecycle::INSTALLATION_SCHEDULED]);
        $this->appointment($installationClient, AppointmentTypes::INSTALLATION);

        $callbackClient = $this->client(['primary_owner_id' => $staff->id, 'stage' => ClientLifecycle::CONTACTING]);
        $this->followUp($callbackClient, $staff, 'callback', 'Call again');

        $trialClient = $this->client(['primary_owner_id' => $staff->id, 'stage' => ClientLifecycle::INSTALLED_FREE]);
        $installationId = DB::table('installations')->insertGetId([
            'client_id' => $trialClient->id,
            'installed_by' => $staff->id,
            'installed_at' => now()->subDays(3),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->followUp($trialClient, $partner, 'free_trial', 'Check trial outcome', ['installation_id' => $installationId]);

        $decisionClient = $this->client(['primary_owner_id' => $admin->id, 'stage' => ClientLifecycle::DECISION_PENDING]);
        $this->followUp($decisionClient, $admin, 'decision', 'Confirm decision');

        $closedClient = $this->client(['primary_owner_id' => $staff->id, 'stage' => ClientLifecycle::CLOSED, 'status' => 'closed']);
        $this->followUp($closedClient, $staff, 'closed', 'Should not appear');
        $this->appointment($closedClient, AppointmentTypes::PHYSICAL_VISIT);

        $subscriberClient = $this->client(['primary_owner_id' => $staff->id, 'stage' => ClientLifecycle::SUBSCRIBER, 'status' => 'subscriber']);
        $this->followUp($subscriberClient, $staff, 'subscriber', 'Should not return to queue');

        $service = app(NotificationService::class);

        $this->assertSame(5, $service->sendOperationalReminders());
        $this->assertSame(0, $service->sendOperationalReminders());

        foreach ([
            'appointment_reminder',
            'installation_reminder',
            'callback_due',
            'trial_followup_due',
            'decision_followup_due',
        ] as $type) {
            $this->assertSame(1, DB::table('notifications')->where('type', $type)->count(), $type);
        }

        $this->assertSame(0, DB::table('notifications')->where('user_id', $partner->id)->count());
        $this->assertDatabaseHas('clients', ['id' => $subscriberClient->id, 'stage' => ClientLifecycle::SUBSCRIBER]);
        $this->assertDatabaseMissing('notifications', ['source_type' => 'client', 'source_id' => $closedClient->id]);
    }

    public function test_commercial_finance_alerts_are_idempotent_projections(): void
    {
        $this->seed(SettingsSeeder::class);
        $this->seed(ServiceCatalogSeeder::class);
        $this->seed(CommercialCatalogSeeder::class);
        app(AccountingSetupService::class)->ensureSeeded();

        $admin = User::factory()->create(['role' => 'founder']);
        $subscription = $this->subscriptionDueForRenewal($admin);
        $invoice = $this->overdueInvoice($admin);
        $reviewSubscription = $this->billingReviewSubscription($admin);
        $schedule = $this->revenueReviewSchedule($admin);

        $before = $this->financialCounts();
        $service = app(NotificationService::class);

        $this->assertSame(4, $service->sendCommercialFinanceAlerts());
        $this->assertSame(0, $service->sendCommercialFinanceAlerts());

        $this->assertDatabaseHas('notifications', ['type' => 'renewal_due', 'source_type' => 'subscription', 'source_id' => $subscription->id]);
        $this->assertDatabaseHas('notifications', ['type' => 'invoice_overdue', 'source_type' => 'invoice', 'source_id' => $invoice->id]);
        $this->assertDatabaseHas('notifications', ['type' => 'billing_review_required', 'source_type' => 'subscription', 'source_id' => $reviewSubscription->id]);
        $this->assertDatabaseHas('notifications', ['type' => 'revenue_recognition_review_required', 'source_type' => 'revenue_recognition_schedule', 'source_id' => $schedule->id]);
        $this->assertSame($before, $this->financialCounts());
    }

    public function test_financial_scheduled_commands_are_idempotent_on_rerun(): void
    {
        $this->seed(SettingsSeeder::class);
        $this->seed(ServiceCatalogSeeder::class);
        $this->seed(CommercialCatalogSeeder::class);
        app(AccountingSetupService::class)->ensureSeeded();

        $admin = User::factory()->create(['role' => 'founder']);
        $category = ExpenseCategory::create([
            'name' => 'Internet',
            'key' => 'g4-internet',
            'code' => 'g4-internet',
            'name_ar' => 'إنترنت',
            'is_active' => true,
        ]);
        RecurringExpenseTemplate::create([
            'name' => 'Monthly internet',
            'category_id' => $category->id,
            'currency' => 'JOD',
            'amount_minor' => 12000,
            'frequency' => RecurringExpenseTemplate::FREQUENCY_MONTHLY,
            'interval_count' => 1,
            'start_date' => '2026-10-01',
            'next_due_date' => '2026-10-01',
            'default_funding_source' => Expense::FUNDING_COMPANY_ACCOUNT,
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        $subscription = $this->startSubscription($admin);
        Carbon::setTestNow('2026-10-01 09:00:00');

        $this->artisan('finance:generate-recurring-expenses')->assertSuccessful();
        $this->artisan('finance:generate-recurring-expenses')->assertSuccessful();
        $this->assertSame(1, RecurringExpenseObligation::count());

        $this->artisan('finance:generate-subscription-renewals', ['--through' => '2026-10-01'])->assertSuccessful();
        $this->artisan('finance:generate-subscription-renewals', ['--through' => '2026-10-01'])->assertSuccessful();
        $this->assertSame(2, Invoice::where('subscription_id', $subscription->id)->count());
        $this->assertSame(2, SubscriptionBillingPeriod::where('subscription_id', $subscription->id)->count());

        $this->artisan('finance:recognize-revenue', ['--through' => '2026-10-31'])->assertSuccessful();
        $this->artisan('finance:recognize-revenue', ['--through' => '2026-10-31'])->assertSuccessful();
        $this->assertSame(
            JournalEntry::where('event_type', 'revenue_recognized')->count(),
            DB::table('revenue_recognition_periods')->where('status', 'recognized')->count()
        );
    }

    private function client(array $overrides = []): Client
    {
        return Client::create(array_merge([
            'business_name' => 'G4 Client '.uniqid(),
            'phone' => '079'.random_int(1000000, 9999999),
            'city_area' => 'Amman',
            'business_category' => 'Restaurant',
            'lead_source' => 'direct',
            'status' => 'prospect',
            'stage' => ClientLifecycle::PROSPECT,
        ], $overrides));
    }

    private function appointment(Client $client, string $type): int
    {
        return DB::table('appointments')->insertGetId([
            'client_id' => $client->id,
            'appointment_date' => now()->toDateString(),
            'appointment_time' => now()->addHour()->format('H:i'),
            'appointment_type' => $type,
            'status' => 'scheduled',
            'location' => 'Office',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function followUp(Client $client, User $user, string $reason, string $nextAction, array $overrides = []): int
    {
        return DB::table('follow_ups')->insertGetId(array_merge([
            'client_id' => $client->id,
            'user_id' => $user->id,
            'method' => 'phone',
            'reason' => $reason,
            'next_action' => $nextAction,
            'next_follow_up_date' => now()->toDateString(),
            'follow_up_date_time' => now()->subMinute(),
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    private function subscriptionDueForRenewal(User $admin): Subscription
    {
        $client = $this->client(['primary_owner_id' => $admin->id, 'stage' => ClientLifecycle::SUBSCRIBER, 'status' => 'subscriber']);
        $price = $this->planPrice('g4_due');

        return Subscription::create([
            'client_id' => $client->id,
            'plan_id' => $price->plan_id,
            'plan_price_id' => $price->id,
            'user_id' => $admin->id,
            'billing_engine_version' => 'v2',
            'billing_interval_v2' => PlanPrice::MONTHLY,
            'currency' => 'JOD',
            'quantity' => 1,
            'plan_code_snapshot' => $price->plan->code,
            'plan_name_snapshot' => $price->plan->name_ar,
            'unit_price_minor' => 10000,
            'setup_fee_minor_v2' => 0,
            'subtotal_minor' => 10000,
            'discount_minor' => 0,
            'tax_minor_v2' => 0,
            'total_minor' => 10000,
            'current_period_start' => '2026-08-15',
            'current_period_end' => '2026-09-14',
            'next_billing_date' => '2026-09-15',
            'billing_type' => PlanPrice::MONTHLY,
            'total_price' => '10.000',
            'start_date' => '2026-08-15',
            'renewal_date' => '2026-09-15',
            'status' => 'active',
            'cancel_at_period_end' => false,
            'version' => 1,
        ]);
    }

    private function overdueInvoice(User $admin): Invoice
    {
        return Invoice::create([
            'invoice_number' => 'G4-OVERDUE-'.uniqid(),
            'client_id' => $this->client(['primary_owner_id' => $admin->id])->id,
            'currency' => 'JOD',
            'status' => Invoice::STATUS_ISSUED,
            'issue_date' => now()->subDays(10)->toDateString(),
            'due_date' => now()->subDay()->toDateString(),
            'subtotal_minor' => 50000,
            'discount_minor' => 0,
            'tax_minor' => 0,
            'total_minor' => 50000,
            'issued_at' => now()->subDays(10),
            'created_by' => $admin->id,
        ]);
    }

    private function billingReviewSubscription(User $admin): Subscription
    {
        $client = $this->client(['primary_owner_id' => $admin->id, 'stage' => ClientLifecycle::SUBSCRIBER, 'status' => 'subscriber']);

        return Subscription::create([
            'client_id' => $client->id,
            'user_id' => $admin->id,
            'billing_engine_version' => 'v2',
            'billing_type' => PlanPrice::MONTHLY,
            'total_price' => '10.000',
            'start_date' => '2026-09-01',
            'status' => 'active',
        ]);
    }

    private function revenueReviewSchedule(User $admin): RevenueRecognitionSchedule
    {
        $invoice = Invoice::create([
            'invoice_number' => 'G4-REVENUE-'.uniqid(),
            'client_id' => $this->client(['primary_owner_id' => $admin->id])->id,
            'currency' => 'JOD',
            'status' => Invoice::STATUS_ISSUED,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'subtotal_minor' => 10000,
            'discount_minor' => 0,
            'tax_minor' => 0,
            'total_minor' => 10000,
            'issued_at' => now(),
            'created_by' => $admin->id,
        ]);
        $line = InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'line_type' => InvoiceLine::TYPE_ONE_TIME_SERVICE,
            'description_snapshot' => 'Manual service',
            'quantity' => 1,
            'unit_price_minor' => 10000,
            'subtotal_minor' => 10000,
            'discount_minor' => 0,
            'tax_minor' => 0,
            'total_minor' => 10000,
        ]);

        return RevenueRecognitionSchedule::create([
            'invoice_line_id' => $line->id,
            'invoice_id' => $invoice->id,
            'revenue_account_id' => app(AccountingSetupService::class)->systemAccount('one_time_service_revenue')->id,
            'policy' => RevenueRecognitionSchedule::POLICY_POINT_IN_TIME_SERVICE,
            'currency' => 'JOD',
            'original_recognizable_minor' => 10000,
            'period_count' => 0,
            'status' => RevenueRecognitionSchedule::STATUS_NEEDS_REVIEW,
            'requires_manual_confirmation' => true,
            'created_by' => $admin->id,
        ]);
    }

    private function startSubscription(User $admin): Subscription
    {
        $price = $this->planPrice('g4_renewal');
        [$subscription] = app(SubscriptionBillingService::class)->startPaidSubscription(
            $this->client(['primary_owner_id' => $admin->id]),
            $price->load('plan'),
            ['quantity' => 1, 'start_date' => '2026-09-01'],
            $admin->id
        );

        return $subscription;
    }

    private function planPrice(string $code): PlanPrice
    {
        $plan = Plan::create([
            'code' => $code,
            'name_ar' => 'باقة '.$code,
            'is_active' => true,
        ]);

        return PlanPrice::create([
            'plan_id' => $plan->id,
            'billing_interval' => PlanPrice::MONTHLY,
            'currency' => 'JOD',
            'amount_minor' => Money::fromJod('10.000')->minorUnits(),
            'setup_fee_minor' => 0,
            'included_branch_quantity' => 1,
            'effective_from' => now()->subMonth(),
            'is_active' => true,
        ])->load('plan');
    }

    private function financialCounts(): array
    {
        return [
            'subscriptions' => Subscription::count(),
            'invoices' => Invoice::count(),
            'billing_periods' => SubscriptionBillingPeriod::count(),
            'journal_entries' => JournalEntry::count(),
            'payments' => DB::table('payments')->count(),
            'cash_movements' => DB::table('cash_movements')->count(),
        ];
    }
}
