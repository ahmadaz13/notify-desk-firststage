<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureActiveInternalUser;
use App\Models\Appointment;
use App\Models\CashMovement;
use App\Models\Client;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FinancialAccount;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\RevenueRecognitionSchedule;
use App\Models\SubscriptionMetricEvent;
use App\Models\User;
use App\Services\AccountingReconciliationService;
use App\Services\AccountingSetupService;
use App\Services\ClientOperationalWorkflowService;
use App\Services\FinancialAccountService;
use App\Services\FinancialReportingReconciliationService;
use App\Services\FinancialStatementService;
use App\Services\FreeInstallationService;
use App\Services\MeetingOutcomeService;
use App\Services\OperatingExpenseService;
use App\Services\PaymentAllocationService;
use App\Services\ReceivableService;
use App\Services\RevenueRecognitionService;
use App\Services\SaasMetricsReconciliationService;
use App\Services\SaasMetricsService;
use App\Services\SubscriptionBillingService;
use App\Support\AppointmentTypes;
use App\Support\ClientLifecycle;
use App\Support\Money;
use App\Support\ReportingPeriod;
use Carbon\Carbon;
use Database\Seeders\CommercialCatalogSeeder;
use Database\Seeders\ServiceCatalogSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class BackendClosureG5GoldenFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.timezone' => 'Asia/Amman']);
        Carbon::setTestNow('2026-01-01 09:00:00');
        $this->seed(SettingsSeeder::class);
        $this->seed(ServiceCatalogSeeder::class);
        $this->seed(CommercialCatalogSeeder::class);
        app(AccountingSetupService::class)->ensureSeeded();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_golden_backend_flow_from_prospect_to_reactivation_reconciles(): void
    {
        $admin = User::factory()->create(['role' => 'founder']);
        $client = $this->client($admin);
        $cashAccount = app(FinancialAccountService::class)->createAccount([
            'code' => 'g5-bank',
            'name_ar' => 'بنك G5',
            'name_en' => 'G5 Bank',
            'type' => FinancialAccount::TYPE_BANK,
            'opening_balance' => '0.000',
            'opening_date' => '2026-01-01',
        ], $admin->id);

        $contact = app(ClientOperationalWorkflowService::class)->recordContactOutcome($client, $admin, [
            'method' => 'phone',
            'result' => 'appointment',
            'appointment_date' => '2026-01-02',
            'appointment_time' => '10:00',
            'appointment_type' => AppointmentTypes::PHYSICAL_VISIT,
            'location' => 'Client office',
            'attendees' => [$admin->id],
            'note' => 'Golden flow first contact',
        ]);
        $this->assertSame(ClientLifecycle::APPOINTMENT, $client->fresh()->stage);

        app(MeetingOutcomeService::class)->recordOutcome($contact['appointment']->id, $admin->id, [
            'meeting_date_time' => '2026-01-02 10:00:00',
            'meeting_type' => AppointmentTypes::PHYSICAL_VISIT,
            'attendance_status' => 'attended',
            'demo_performed' => true,
            'interest_level' => 'high',
            'next_action' => 'Schedule free installation',
            'outcome_result' => 'installation_scheduled',
            'installation_appointment_date' => '2026-01-03',
            'installation_appointment_time' => '11:00',
        ]);
        $installationAppointment = Appointment::where('client_id', $client->id)
            ->where('appointment_type', AppointmentTypes::INSTALLATION)
            ->firstOrFail();
        $this->assertSame(ClientLifecycle::INSTALLATION_SCHEDULED, $client->fresh()->stage);

        Carbon::setTestNow('2026-01-04 12:00:00');
        $installation = app(FreeInstallationService::class)->completeInstallation($client->fresh(), $admin, [
            'appointment_id' => $installationAppointment->id,
            'installed_at' => '2026-01-04 12:00:00',
            'branch_name' => 'Main',
            'custom_item_names' => 'Notify Desk POS',
        ]);
        $this->assertSame(ClientLifecycle::INSTALLED_FREE, $client->fresh()->stage);
        // P13: post_install_followup_days (3) at workday_start (09:00) — no longer a hard-coded 10:00.
        $this->assertDatabaseHas('follow_ups', [
            'installation_id' => $installation->id,
            'follow_up_date_time' => '2026-01-07 09:00:00',
        ]);
        $this->assertSame(0, Invoice::count());

        Carbon::setTestNow('2026-01-01 12:00:00');
        $basePrice = $this->planPrice('g5_base', '10.000', '2025-12-01');
        [$subscription, $invoice] = app(SubscriptionBillingService::class)->startPaidSubscription(
            $client->fresh(),
            $basePrice->load('plan'),
            ['quantity' => 1, 'start_date' => '2026-01-01'],
            $admin->id
        );
        $this->assertSame(ClientLifecycle::SUBSCRIBER, $client->fresh()->stage);
        $this->assertSame(1, Invoice::where('subscription_id', $subscription->id)->count());
        $this->assertSame(1, RevenueRecognitionSchedule::where('invoice_id', $invoice->id)->count());

        app(PaymentAllocationService::class)->recordV2Payment($client->fresh(), [
            'amount' => '10.000',
            'financial_account_id' => $cashAccount->id,
            'payment_method' => 'cash',
            'received_at' => '2026-01-01 12:30:00',
        ], [
            ['invoice_id' => $invoice->id, 'amount' => '10.000'],
        ], false, $admin->id);
        $this->assertSame(0, app(ReceivableService::class)->invoiceOutstandingMinor($invoice->fresh()));
        $this->assertSame(1, Payment::where('client_id', $client->id)->count());
        $this->assertSame(1, CashMovement::where('event_type', CashMovement::EVENT_PAYMENT_RECEIVED)->count());
        $this->assertTrue(JournalEntry::where('event_type', 'invoice_issued_accounting')->exists());
        $this->assertTrue(JournalEntry::where('event_type', 'payment_allocation_accounting')->exists());

        $recognition = app(RevenueRecognitionService::class)->recognizeDue('2026-01-31');
        $this->assertSame(0, $recognition['ambiguous']);
        $this->assertSame(1, $recognition['recognized']);

        $category = ExpenseCategory::create([
            'name' => 'G5 Operations',
            'key' => 'g5-operations',
            'code' => 'g5-operations',
            'name_ar' => 'تشغيل G5',
            'is_active' => true,
        ]);
        app(OperatingExpenseService::class)->createV2Expense([
            'amount' => '2.000',
            'category_id' => $category->id,
            'funding_source' => Expense::FUNDING_COMPANY_ACCOUNT,
            'financial_account_id' => $cashAccount->id,
            'paid_at' => '2026-01-20 09:00:00',
            'incurred_on' => '2026-01-20',
        ], $admin);
        $period = new ReportingPeriod(Carbon::parse('2026-01-01'), Carbon::parse('2026-01-31')->endOfDay(), 'custom_date_range');
        $pl = app(FinancialStatementService::class)->profitAndLoss($period);
        $this->assertSame(10000, $pl['total_revenue_minor']);
        $this->assertSame(2000, $pl['total_expenses_minor']);
        $this->assertSame(8000, $pl['net_income_minor']);

        $billing = app(SubscriptionBillingService::class);
        $higherPrice = $this->planPrice('g5_expansion', '15.000', '2026-02-01');
        $lowerPrice = $this->planPrice('g5_contraction', '8.000', '2026-03-01');

        Carbon::setTestNow('2026-02-01 09:00:00');
        $billing->schedulePlanChange($subscription->fresh(), $higherPrice, 1, $admin->id);
        $this->assertSame(1, $billing->generateRenewals('2026-02-01', false, $admin->id)['renewed']);
        $this->assertDatabaseHas('subscription_metric_events', [
            'subscription_id' => $subscription->id,
            'movement_type' => SubscriptionMetricEvent::TYPE_EXPANSION,
        ]);

        Carbon::setTestNow('2026-03-01 09:00:00');
        $billing->schedulePlanChange($subscription->fresh(), $lowerPrice, 1, $admin->id);
        $this->assertSame(1, $billing->generateRenewals('2026-03-01', false, $admin->id)['renewed']);
        $this->assertDatabaseHas('subscription_metric_events', [
            'subscription_id' => $subscription->id,
            'movement_type' => SubscriptionMetricEvent::TYPE_CONTRACTION,
        ]);

        Carbon::setTestNow('2026-03-15 09:00:00');
        $billing->scheduleCancellation($subscription->fresh(), 'Golden flow boundary cancellation', $admin->id);
        Carbon::setTestNow('2026-03-31 18:00:00');
        $this->assertSame(1, $billing->generateRenewals('2026-03-31', false, $admin->id)['cancelled']);
        $this->assertSame('cancelled', $subscription->fresh()->status);
        $this->assertDatabaseHas('subscription_metric_events', [
            'subscription_id' => $subscription->id,
            'movement_type' => SubscriptionMetricEvent::TYPE_CHURN,
        ]);

        Carbon::setTestNow('2026-04-10 09:00:00');
        [$reactivated] = $billing->reactivate($subscription->fresh(), $basePrice, [
            'quantity' => 1,
            'start_date' => '2026-04-10',
        ], $admin->id);
        $this->assertSame('active', $reactivated->status);
        $this->assertDatabaseHas('subscription_metric_events', [
            'subscription_id' => $subscription->id,
            'movement_type' => SubscriptionMetricEvent::TYPE_REACTIVATION,
        ]);

        $saasPeriod = new ReportingPeriod(Carbon::parse('2026-01-01'), Carbon::parse('2026-04-30')->endOfDay(), 'custom_date_range');
        $movement = app(SaasMetricsService::class)->movementSummary($saasPeriod);
        $this->assertGreaterThan(0, $movement['new_arr_minor']);
        $this->assertGreaterThan(0, $movement['expansion_arr_minor']);
        $this->assertGreaterThan(0, $movement['contraction_arr_minor']);
        $this->assertGreaterThan(0, $movement['churn_arr_minor']);
        $this->assertGreaterThan(0, $movement['reactivation_arr_minor']);

        $this->assertTrue(app(AccountingReconciliationService::class)->run()['ok']);
        $this->assertTrue(app(FinancialReportingReconciliationService::class)->run($saasPeriod)['ok']);
        $this->assertTrue(app(SaasMetricsReconciliationService::class)->run($saasPeriod)['ok']);
    }

    public function test_active_write_routes_have_expected_auth_boundary(): void
    {
        $allowedPublicWriteRoutes = [
            'login.store',
            'logout',
            'public.client.store',
            'default.livewire.update',
            'livewire.update',
            'livewire.upload-file',
        ];

        $violations = [];
        foreach (Route::getRoutes() as $route) {
            if ($route->getName() === null || ! array_intersect($route->methods(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
                continue;
            }
            if (in_array($route->getName(), $allowedPublicWriteRoutes, true)) {
                continue;
            }

            $middleware = $route->gatherMiddleware();
            if (! in_array('auth', $middleware, true) || ! in_array(EnsureActiveInternalUser::class, $middleware, true)) {
                $violations[] = $route->getName();
            }
        }

        $this->assertSame([], $violations);
    }

    private function client(User $admin): Client
    {
        return Client::create([
            'business_name' => 'G5 Golden Client',
            'phone' => '079'.random_int(1000000, 9999999),
            'city_area' => 'Amman',
            'business_category' => 'Restaurant',
            'lead_source' => 'direct',
            'primary_owner_id' => $admin->id,
            'status' => 'prospect',
            'stage' => ClientLifecycle::PROSPECT,
        ]);
    }

    private function planPrice(string $code, string $amountJod, string $effectiveFrom): PlanPrice
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
            'amount_minor' => Money::fromJod($amountJod)->minorUnits(),
            'setup_fee_minor' => 0,
            'included_branch_quantity' => 1,
            'effective_from' => $effectiveFrom,
            'is_active' => true,
        ])->load('plan');
    }
}
