<?php

namespace Tests\Feature;

use App\Models\CashMovement;
use App\Models\Client;
use App\Models\FinancialAccount;
use App\Models\InvoiceLine;
use App\Models\Partner;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Models\SubscriptionMetricEvent;
use App\Models\User;
use App\Services\AccountingReconciliationService;
use App\Services\AccountingSetupService;
use App\Services\FinancialAccountService;
use App\Services\FinancialReportingReconciliationService;
use App\Services\PaymentAllocationService;
use App\Services\PlanPriceService;
use App\Services\SaasMetricEventService;
use App\Services\SaasMetricsReconciliationService;
use App\Services\SaasMetricsService;
use App\Services\SubscriptionBillingService;
use App\Support\ClientLifecycle;
use App\Support\Money;
use App\Support\ReportingPeriod;
use Carbon\Carbon;
use Database\Seeders\AssetCategorySeeder;
use Database\Seeders\CommercialCatalogSeeder;
use Database\Seeders\ExpenseCategorySeeder;
use Database\Seeders\ServiceCatalogSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancePhaseF2Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-01-01 09:00:00');
        $this->seed(SettingsSeeder::class);
        $this->seed(ServiceCatalogSeeder::class);
        $this->seed(CommercialCatalogSeeder::class);
        $this->seed(ExpenseCategorySeeder::class);
        $this->seed(AssetCategorySeeder::class);
        app(AccountingSetupService::class)->ensureSeeded();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_canonical_arr_mrr_excludes_setup_tax_one_time_cash_and_uses_aggregate_rounding(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $monthly = $this->startSubscription($admin, $this->createClient(), $this->createPlanWithPrice('f2_monthly', 10000, PlanPrice::MONTHLY, 50000, 1600), '2026-01-01');
        $annual = $this->startSubscription($admin, $this->createClient(), $this->createPlanWithPrice('f2_annual', 120000, PlanPrice::ANNUAL), '2026-01-01');
        $roundA = $this->startSubscription($admin, $this->createClient(), $this->createPlanWithPrice('f2_round_a', 6, PlanPrice::ANNUAL), '2026-01-01');
        $roundB = $this->startSubscription($admin, $this->createClient(), $this->createPlanWithPrice('f2_round_b', 6, PlanPrice::ANNUAL), '2026-01-01');

        $service = app(SaasMetricsService::class);
        $report = $service->dashboard($this->period('2026-01-01', '2026-01-31'));

        $this->assertSame(240012, $report['ending_arr_minor']);
        $this->assertSame(20001, $report['ending_mrr_minor']);
        $this->assertSame(4, $report['active_subscriptions']);
        $this->assertSame(4, $report['active_subscribing_clients']);
        $this->assertSame(240012, $report['movements']['new_arr_minor']);
        $this->assertSame(20001, $report['movements']['new_mrr_minor']);
        $this->assertDatabaseCount('subscription_metric_events', 4);
        $this->assertSame(10000, app(SaasMetricEventService::class)->recurringContractMinor($monthly->billingPeriods()->firstOrFail()));
        $this->assertSame(120000, app(SaasMetricEventService::class)->normalizedArrMinorForPeriod($monthly->billingPeriods()->firstOrFail()));
        $this->assertSame(120000, app(SaasMetricEventService::class)->normalizedArrMinorForPeriod($annual->billingPeriods()->firstOrFail()));
        $this->assertSame(1, app(SaasMetricEventService::class)->mrrMinorFromArr(
            app(SaasMetricEventService::class)->normalizedArrMinorForPeriod($roundA->billingPeriods()->firstOrFail())
            + app(SaasMetricEventService::class)->normalizedArrMinorForPeriod($roundB->billingPeriods()->firstOrFail())
        ));
    }

    public function test_renewal_movements_classify_expansion_contraction_and_ignore_same_value(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $monthly = $this->createPlanWithPrice('f2_growth', 10000, PlanPrice::MONTHLY);
        $subscription = $this->startSubscription($admin, $this->createClient(), $monthly, '2026-01-01');

        Carbon::setTestNow('2026-02-01 09:00:00');
        app(SubscriptionBillingService::class)->generateRenewals('2026-02-01', false, $admin->id);
        $this->assertSame(1, SubscriptionMetricEvent::where('movement_type', SubscriptionMetricEvent::TYPE_NEW)->count());
        $this->assertSame(0, SubscriptionMetricEvent::where('movement_type', SubscriptionMetricEvent::TYPE_EXPANSION)->count());

        app(PlanPriceService::class)->createVersion($monthly->plan, [
            'billing_interval' => PlanPrice::MONTHLY,
            'amount_jod' => '15.000',
            'setup_fee_jod' => '0.000',
            'included_branch_quantity' => 1,
            'effective_from' => '2026-03-01 00:00:00',
        ], $admin->id);

        Carbon::setTestNow('2026-03-01 09:00:00');
        app(SubscriptionBillingService::class)->generateRenewals('2026-03-01', false, $admin->id);
        $this->assertDatabaseHas('subscription_metric_events', [
            'subscription_id' => $subscription->id,
            'movement_type' => SubscriptionMetricEvent::TYPE_EXPANSION,
            'arr_delta_minor' => 60000,
        ]);

        app(PlanPriceService::class)->createVersion($monthly->plan, [
            'billing_interval' => PlanPrice::MONTHLY,
            'amount_jod' => '12.000',
            'setup_fee_jod' => '0.000',
            'included_branch_quantity' => 1,
            'effective_from' => '2026-04-01 00:00:00',
        ], $admin->id);

        Carbon::setTestNow('2026-04-01 09:00:00');
        app(SubscriptionBillingService::class)->generateRenewals('2026-04-01', false, $admin->id);
        $this->assertDatabaseHas('subscription_metric_events', [
            'subscription_id' => $subscription->id,
            'movement_type' => SubscriptionMetricEvent::TYPE_CONTRACTION,
            'arr_delta_minor' => -36000,
        ]);
    }

    public function test_scheduled_plan_change_and_cancellation_do_not_move_mrr_until_effective_and_reactivation_is_not_new(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $base = $this->createPlanWithPrice('f2_lifecycle_base', 10000, PlanPrice::MONTHLY);
        $upgrade = $this->createPlanWithPrice('f2_lifecycle_upgrade', 30000, PlanPrice::MONTHLY);
        $subscription = $this->startSubscription($admin, $this->createClient(), $base, '2026-01-01');

        app(SubscriptionBillingService::class)->schedulePlanChange($subscription, $upgrade, 1, $admin->id);
        $this->assertSame(1, SubscriptionMetricEvent::count());

        Carbon::setTestNow('2026-02-01 09:00:00');
        app(SubscriptionBillingService::class)->generateRenewals('2026-02-01', false, $admin->id);
        $this->assertDatabaseHas('subscription_metric_events', ['movement_type' => SubscriptionMetricEvent::TYPE_EXPANSION, 'arr_delta_minor' => 240000]);

        app(SubscriptionBillingService::class)->scheduleCancellation($subscription->fresh(), 'End', $admin->id);
        $this->assertSame(0, SubscriptionMetricEvent::where('movement_type', SubscriptionMetricEvent::TYPE_CHURN)->count());

        Carbon::setTestNow('2026-02-28 09:00:00');
        app(SubscriptionBillingService::class)->generateRenewals('2026-02-28', false, $admin->id);
        $this->assertDatabaseHas('subscription_metric_events', ['movement_type' => SubscriptionMetricEvent::TYPE_CHURN, 'arr_delta_minor' => -360000]);
        $this->assertSame(0, app(SaasMetricsService::class)->dashboard($this->period('2026-02-01', '2026-02-28'))['ending_arr_minor']);

        Carbon::setTestNow('2026-03-10 09:00:00');
        app(SubscriptionBillingService::class)->reactivate($subscription->fresh(), $base, ['quantity' => 1, 'start_date' => '2026-03-10'], $admin->id);
        $this->assertDatabaseHas('subscription_metric_events', ['movement_type' => SubscriptionMetricEvent::TYPE_REACTIVATION, 'arr_delta_minor' => 120000]);
        $this->assertSame(1, SubscriptionMetricEvent::where('movement_type', SubscriptionMetricEvent::TYPE_NEW)->count());
    }

    public function test_metrics_equation_counts_retention_backfill_exports_and_partner_boundaries(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [, $partnerUser] = $this->createPartnerUser();
        $subscription = $this->startSubscription($admin, $this->createClient(), $this->createPlanWithPrice('f2_ops', 10000, PlanPrice::MONTHLY), '2026-01-01');
        $cash = app(FinancialAccountService::class)->createAccount([
            'code' => 'f2_cash',
            'name_ar' => 'F2 Cash',
            'type' => FinancialAccount::TYPE_CASH,
            'opening_balance' => '0.000',
            'opening_date' => '2025-12-31 10:00:00',
        ], $admin->id);
        app(AccountingSetupService::class)->ensureFinancialAccountMapping($cash);
        app(PaymentAllocationService::class)->recordV2Payment($subscription->client, [
            'amount' => '5.000',
            'financial_account_id' => $cash->id,
            'payment_method' => 'cash',
            'received_at' => '2026-01-10 10:00:00',
        ], [], false, $admin->id);

        $period = $this->period('2026-01-01', '2026-01-31');
        $report = app(SaasMetricsService::class)->dashboard($period);
        $this->assertSame($report['starting_arr_minor'] + $report['movements']['new_arr_minor'] - $report['movements']['churn_arr_minor'], $report['ending_arr_minor']);
        $this->assertSame(5000, $report['cash_collected_minor']);
        $this->assertNull($report['nrr_bps']);
        $this->assertTrue(app(SaasMetricsReconciliationService::class)->run($period)['ok']);

        $this->artisan('finance:backfill-saas-metrics --dry-run')->assertExitCode(0);
        $this->assertSame(1, SubscriptionMetricEvent::count());
        $this->artisan('finance:backfill-saas-metrics')->assertExitCode(0);
        $this->artisan('finance:backfill-saas-metrics')->assertExitCode(0);
        $this->assertSame(1, SubscriptionMetricEvent::count());

        $this->actingAs($admin)->get(route('saas-metrics.index'))->assertOk()->assertSee('MRR, ARR, Movements and Retention');
        $this->actingAs($admin)->get(route('executive.index'))->assertOk()->assertSee('Executive Finance and SaaS Dashboard');
        $export = $this->actingAs($admin)->get(route('saas-metrics.export', ['report' => 'summary']));
        $export->assertOk();
        $export->assertDownload('saas-summary.csv');
        $this->assertStringContainsString('MRR', $export->streamedContent());

        $this->actingAs($partnerUser)->get(route('saas-metrics.index'))->assertForbidden();
        $this->actingAs($partnerUser)->get(route('executive.index'))->assertForbidden();
        $this->actingAs($partnerUser)->get(route('saas-metrics.export', ['report' => 'summary']))->assertForbidden();
        $this->assertTrue(app(AccountingReconciliationService::class)->run()['ok']);
        $this->assertTrue(app(FinancialReportingReconciliationService::class)->run($period)['ok']);
    }

    private function startSubscription(User $admin, Client $client, PlanPrice $price, string $startDate): Subscription
    {
        app(SubscriptionBillingService::class)->startPaidSubscription($client, $price->load('plan'), [
            'quantity' => 1,
            'start_date' => $startDate,
        ], $admin->id);

        return Subscription::where('client_id', $client->id)->where('billing_engine_version', 'v2')->firstOrFail();
    }

    private function createPlanWithPrice(string $code, int $amountMinor, string $interval, int $setupMinor = 0, ?int $taxRateBps = null): PlanPrice
    {
        $plan = Plan::create([
            'code' => $code,
            'name_ar' => 'باقة '.$code,
            'name_en' => 'Plan '.$code,
            'is_active' => true,
        ]);

        return PlanPrice::create([
            'plan_id' => $plan->id,
            'billing_interval' => $interval,
            'currency' => 'JOD',
            'amount_minor' => $amountMinor,
            'setup_fee_minor' => $setupMinor,
            'included_branch_quantity' => 1,
            'default_tax_rate_bps' => $taxRateBps,
            'effective_from' => now()->subDay(),
            'is_active' => true,
        ])->load('plan');
    }

    private function createClient(array $overrides = []): Client
    {
        return Client::create(array_merge([
            'business_name' => 'Phase F2 Client '.uniqid(),
            'phone' => '079'.random_int(1000000, 9999999),
            'city_area' => 'Amman',
            'business_category' => 'Restaurant',
            'lead_source' => 'Direct',
            'status' => 'prospect',
            'stage' => ClientLifecycle::PROSPECT,
        ], $overrides));
    }

    private function createPartnerUser(): array
    {
        $partner = Partner::create([
            'company_name' => 'Phase F2 Partner',
            'email' => 'phase-f2-partner@example.com',
        ]);

        $user = User::factory()->create([
            'role' => 'partner',
            'partner_id' => $partner->id,
        ]);

        return [$partner, $user];
    }

    private function period(string $from, string $to): ReportingPeriod
    {
        return new ReportingPeriod(Carbon::parse($from)->startOfDay(), Carbon::parse($to)->endOfDay(), 'custom_date_range');
    }
}
