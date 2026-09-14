<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Partner;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\RevenueRecognitionSchedule;
use App\Models\Subscription;
use App\Models\SubscriptionBillingPeriod;
use App\Models\SubscriptionEvent;
use App\Models\User;
use App\Services\AccountingReconciliationService;
use App\Services\AccountingSetupService;
use App\Services\FinancialReportingReconciliationService;
use App\Services\PlanPriceService;
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

class FinancePhaseB2Test extends TestCase
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

    public function test_monthly_v2_subscription_generates_one_next_period_invoice_idempotently_through_invoice_service(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $subscription = $this->startSubscription($admin, $this->createClient(), $this->createPlanWithPrice('b2_monthly', '10.000', PlanPrice::MONTHLY), '2026-01-01');

        Carbon::setTestNow('2026-02-01 09:00:00');
        $first = app(SubscriptionBillingService::class)->generateRenewals('2026-02-01', false, $admin->id);
        $second = app(SubscriptionBillingService::class)->generateRenewals('2026-02-01', false, $admin->id);

        $this->assertSame(1, $first['renewed']);
        $this->assertSame(0, $second['renewed']);
        $this->assertSame(2, Invoice::where('subscription_id', $subscription->id)->count());
        $this->assertSame(2, SubscriptionBillingPeriod::where('subscription_id', $subscription->id)->count());
        $this->assertSame(2, JournalEntry::where('event_type', 'invoice_issued_accounting')->count());
        $this->assertSame(2, RevenueRecognitionSchedule::count());
        $this->assertSame(0, Payment::count());

        $renewalInvoice = Invoice::where('subscription_id', $subscription->id)->orderByDesc('id')->firstOrFail();
        $this->assertSame(Invoice::STATUS_ISSUED, $renewalInvoice->status);
        $this->assertSame('2026-02-01', $renewalInvoice->billing_period_start->toDateString());
        $this->assertSame('2026-02-28', $renewalInvoice->billing_period_end->toDateString());
    }

    public function test_annual_renewal_creates_one_annual_invoice_not_twelve(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $subscription = $this->startSubscription($admin, $this->createClient(), $this->createPlanWithPrice('b2_annual', '120.000', PlanPrice::ANNUAL), '2026-01-01');

        Carbon::setTestNow('2027-01-01 09:00:00');
        $counts = app(SubscriptionBillingService::class)->generateRenewals('2027-01-01', false, $admin->id);

        $this->assertSame(1, $counts['renewed']);
        $this->assertSame(2, Invoice::where('subscription_id', $subscription->id)->count());
        $renewalInvoice = Invoice::where('subscription_id', $subscription->id)->orderByDesc('id')->firstOrFail();
        $this->assertSame('2027-01-01', $renewalInvoice->billing_period_start->toDateString());
        $this->assertSame('2027-12-31', $renewalInvoice->billing_period_end->toDateString());
        $this->assertSame(2, SubscriptionBillingPeriod::where('subscription_id', $subscription->id)->where('billing_interval', PlanPrice::ANNUAL)->count());
    }

    public function test_future_price_resolution_and_scheduled_plan_change_preserve_current_period_snapshot(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = $this->createClient();
        $oldPrice = $this->createPlanWithPrice('b2_price', '10.000', PlanPrice::MONTHLY);
        $subscription = $this->startSubscription($admin, $client, $oldPrice, '2026-01-01');
        $firstPeriod = $subscription->billingPeriods()->firstOrFail();

        app(PlanPriceService::class)->createVersion($oldPrice->plan, [
            'billing_interval' => PlanPrice::MONTHLY,
            'amount_jod' => '20.000',
            'setup_fee_jod' => '0.000',
            'included_branch_quantity' => 1,
            'effective_from' => '2026-02-01 00:00:00',
        ], $admin->id);

        Carbon::setTestNow('2026-02-01 09:00:00');
        app(SubscriptionBillingService::class)->generateRenewals('2026-02-01', false, $admin->id);
        $subscription->refresh();
        $this->assertSame(10000, $firstPeriod->fresh()->price_snapshot_minor);
        $this->assertSame(20000, $subscription->unit_price_minor);

        $newPlanPrice = $this->createPlanWithPrice('b2_change_target', '30.000', PlanPrice::MONTHLY);
        app(SubscriptionBillingService::class)->schedulePlanChange($subscription, $newPlanPrice, 2, $admin->id);
        $subscription->refresh();
        $this->assertSame(20000, $subscription->unit_price_minor);
        $this->assertSame($newPlanPrice->id, $subscription->pending_plan_price_id);

        Carbon::setTestNow('2026-03-01 09:00:00');
        app(SubscriptionBillingService::class)->generateRenewals('2026-03-01', false, $admin->id);
        $subscription->refresh();
        $this->assertSame($newPlanPrice->plan_id, $subscription->plan_id);
        $this->assertSame(30000, $subscription->unit_price_minor);
        $this->assertSame(2, $subscription->quantity);
        $this->assertNull($subscription->pending_plan_price_id);
        $this->assertDatabaseHas('subscription_events', ['subscription_id' => $subscription->id, 'event_type' => SubscriptionEvent::TYPE_PLAN_CHANGE_APPLIED, 'from_price_minor' => 20000, 'to_price_minor' => 30000]);
    }

    public function test_cancellation_undo_and_reactivation_do_not_rewrite_history(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $price = $this->createPlanWithPrice('b2_cancel', '10.000', PlanPrice::MONTHLY);
        $subscription = $this->startSubscription($admin, $this->createClient(), $price, '2026-01-01');

        app(SubscriptionBillingService::class)->scheduleCancellation($subscription, 'Customer requested end', $admin->id);
        $this->assertTrue($subscription->fresh()->cancel_at_period_end);
        $this->assertSame(1, Invoice::where('subscription_id', $subscription->id)->count());

        app(SubscriptionBillingService::class)->undoCancellation($subscription->fresh(), $admin->id);
        $this->assertFalse($subscription->fresh()->cancel_at_period_end);
        $this->assertDatabaseHas('subscription_events', ['subscription_id' => $subscription->id, 'event_type' => SubscriptionEvent::TYPE_CANCELLATION_CANCELLED]);

        app(SubscriptionBillingService::class)->scheduleCancellation($subscription->fresh(), 'End after period', $admin->id);
        Carbon::setTestNow('2026-01-31 09:00:00');
        app(SubscriptionBillingService::class)->generateRenewals('2026-01-31', false, $admin->id);
        $this->assertSame('cancelled', $subscription->fresh()->status);
        $this->assertSame(1, Invoice::where('subscription_id', $subscription->id)->count());

        Carbon::setTestNow('2026-02-10 09:00:00');
        app(SubscriptionBillingService::class)->reactivate($subscription->fresh(), $price, ['quantity' => 1, 'start_date' => '2026-02-10'], $admin->id);
        $this->assertSame('active', $subscription->fresh()->status);
        $this->assertSame(2, Invoice::where('subscription_id', $subscription->id)->count());
        $this->assertSame(2, SubscriptionBillingPeriod::where('subscription_id', $subscription->id)->count());
        $this->assertDatabaseHas('subscription_events', ['subscription_id' => $subscription->id, 'event_type' => SubscriptionEvent::TYPE_REACTIVATED]);
    }

    public function test_backfill_review_legacy_skip_partner_denial_and_reconciliations(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [, $partnerUser] = $this->createPartnerUser();
        $subscription = $this->startSubscription($admin, $this->createClient(), $this->createPlanWithPrice('b2_backfill', '10.000', PlanPrice::MONTHLY), '2026-01-01');
        SubscriptionBillingPeriod::where('subscription_id', $subscription->id)->delete();
        SubscriptionEvent::where('subscription_id', $subscription->id)->delete();
        $ambiguous = Subscription::create([
            'client_id' => $this->createClient(['phone' => '0793333333'])->id,
            'user_id' => $admin->id,
            'billing_engine_version' => 'v2',
            'billing_type' => 'monthly',
            'total_price' => '10.000',
            'start_date' => '2026-01-01',
            'status' => 'active',
        ]);
        Subscription::create([
            'client_id' => $this->createClient(['phone' => '0794444444'])->id,
            'user_id' => $admin->id,
            'billing_type' => 'monthly',
            'total_price' => '10.000',
            'start_date' => '2026-01-01',
            'status' => 'active',
        ]);

        $dryRun = app(SubscriptionBillingService::class)->backfillInitialPeriods(true);
        $this->assertSame(1, $dryRun['created']);
        $this->assertSame(1, $dryRun['review']);

        $counts = app(SubscriptionBillingService::class)->backfillInitialPeriods(false);
        $again = app(SubscriptionBillingService::class)->backfillInitialPeriods(false);
        $this->assertSame(1, $counts['created']);
        $this->assertGreaterThanOrEqual(1, $again['existing']);
        $this->assertSame(0, SubscriptionBillingPeriod::where('subscription_id', $ambiguous->id)->count());

        $this->actingAs($partnerUser)->get(route('subscription-billing.index'))->assertForbidden();
        $this->actingAs($partnerUser)->post(route('subscriptions.cancel', $subscription))->assertForbidden();

        $this->assertTrue(app(AccountingReconciliationService::class)->run()['ok']);
        $period = new ReportingPeriod(Carbon::parse('2026-01-01'), Carbon::parse('2026-01-31')->endOfDay(), 'custom_date_range');
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

    private function createPlanWithPrice(string $code, string $amountJod, string $interval): PlanPrice
    {
        $plan = Plan::create([
            'code' => $code,
            'name_ar' => 'باقة '.$code,
            'is_active' => true,
        ]);

        return PlanPrice::create([
            'plan_id' => $plan->id,
            'billing_interval' => $interval,
            'currency' => 'JOD',
            'amount_minor' => Money::fromJod($amountJod)->minorUnits(),
            'setup_fee_minor' => 0,
            'included_branch_quantity' => 1,
            'effective_from' => now()->subDay(),
            'is_active' => true,
        ])->load('plan');
    }

    private function createClient(array $overrides = []): Client
    {
        return Client::create(array_merge([
            'business_name' => 'Phase B2 Client '.uniqid(),
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
            'company_name' => 'Phase B2 Partner',
            'email' => 'phase-b2-partner@example.com',
        ]);

        $user = User::factory()->create([
            'role' => 'partner',
            'partner_id' => $partner->id,
        ]);

        return [$partner, $user];
    }
}
