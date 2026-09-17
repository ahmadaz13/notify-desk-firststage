<?php

namespace Tests\Feature;

use App\Models\CashMovement;
use App\Models\Client;
use App\Models\ClientPartnerAttribution;
use App\Models\Contract;
use App\Models\FinancialAccount;
use App\Models\Invoice;
use App\Models\PaymentSchedule;
use App\Models\Partner;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Models\SubscriptionMetricEvent;
use App\Models\User;
use App\Services\AccountingSetupService;
use App\Services\FinancialAccountService;
use App\Services\PaymentAllocationService;
use App\Services\PaymentScheduleService;
use App\Services\PartnerCommissionService;
use App\Services\SaasMetricEventService;
use App\Services\SubscriptionBillingService;
use App\Support\ClientLifecycle;
use Carbon\Carbon;
use Database\Seeders\AssetCategorySeeder;
use Database\Seeders\ExpenseCategorySeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BillingTermsAndAnnualInstallmentsGate5Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-17 09:00:00');
        Storage::fake('local');
        $this->seed([SettingsSeeder::class, ExpenseCategorySeeder::class, AssetCategorySeeder::class]);
        app(AccountingSetupService::class)->ensureSeeded();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_backend_preview_is_exact_and_creates_no_financial_or_subscription_records(): void
    {
        [, , $annual] = $this->fixture('preview', PlanPrice::ANNUAL, 100001);

        $preview = app(SubscriptionBillingService::class)->previewPaidSubscriptionTerms($annual, [
            'quantity' => 1,
            'start_date' => '2026-09-17',
            'payment_terms' => 'installments',
            'installments_count' => 3,
            'installment_due_day' => 5,
        ]);

        $this->assertSame(100001, $preview['total_minor']);
        $this->assertSame([33333, 33333, 33335], array_column($preview['schedule'], 'amount_due_minor'));
        $this->assertSame(['2026-09-17', '2026-10-05', '2026-11-05'], array_column($preview['schedule'], 'due_date'));
        $this->assertDatabaseCount('subscriptions', 0);
        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseCount('payment_schedules', 0);
        $this->assertDatabaseCount('cash_movements', 0);
    }

    public function test_monthly_and_annual_full_terms_keep_existing_period_and_invoice_behavior(): void
    {
        [$user, $monthlyClient, $monthly] = $this->fixture('monthly', PlanPrice::MONTHLY, 10000);
        [, $annualClient, $annual] = $this->fixture('annual_full', PlanPrice::ANNUAL, 120000, $user);

        [$monthlySubscription, $monthlyInvoice] = app(SubscriptionBillingService::class)->startPaidSubscription(
            $monthlyClient,
            $monthly,
            ['quantity' => 1, 'start_date' => '2026-09-17'],
            $user->id
        );
        [$annualSubscription, $annualInvoice] = app(SubscriptionBillingService::class)->startPaidSubscription(
            $annualClient,
            $annual,
            ['quantity' => 1, 'start_date' => '2026-09-17', 'payment_terms' => 'full'],
            $user->id
        );

        $this->assertSame('2026-10-17', $monthlySubscription->next_billing_date->toDateString());
        $this->assertSame('2027-09-17', $annualSubscription->next_billing_date->toDateString());
        $this->assertSame(1, $monthlySubscription->installments_count);
        $this->assertSame(1, $annualSubscription->installments_count);
        $this->assertSame(10000, $monthlyInvoice->total_minor);
        $this->assertSame(120000, $annualInvoice->total_minor);
        $this->assertDatabaseCount('payment_schedules', 0);

        $this->expectException(ValidationException::class);
        app(SubscriptionBillingService::class)->previewPaidSubscriptionTerms($monthly, [
            'quantity' => 1,
            'start_date' => '2026-09-17',
            'payment_terms' => 'installments',
            'installments_count' => 3,
            'installment_due_day' => 1,
        ]);
    }

    public function test_installments_remain_one_annual_obligation_with_stable_metrics_and_contract_snapshot(): void
    {
        [$user, $installmentClient, $annual] = $this->fixture('annual_installments', PlanPrice::ANNUAL, 100001);
        [, $fullClient] = $this->fixture('annual_comparison', PlanPrice::ANNUAL, 100001, $user);

        [$installmentSubscription, $invoice] = app(SubscriptionBillingService::class)->startPaidSubscription(
            $installmentClient,
            $annual,
            [
                'quantity' => 1,
                'start_date' => '2026-09-17',
                'payment_terms' => 'installments',
                'installments_count' => 3,
                'installment_due_day' => 5,
            ],
            $user->id
        );
        [$fullSubscription] = app(SubscriptionBillingService::class)->startPaidSubscription(
            $fullClient,
            $annual,
            ['quantity' => 1, 'start_date' => '2026-09-17', 'payment_terms' => 'full'],
            $user->id
        );

        $schedules = PaymentSchedule::where('subscription_id', $installmentSubscription->id)->orderBy('sequence')->get();
        $this->assertCount(3, $schedules);
        $this->assertSame([33333, 33333, 33335], $schedules->pluck('amount_due_minor')->all());
        $this->assertSame($invoice->total_minor, $schedules->sum('amount_due_minor'));
        $this->assertSame([$invoice->id], $schedules->pluck('invoice_id')->unique()->values()->all());
        $this->assertSame(PlanPrice::ANNUAL, $installmentSubscription->billing_interval_v2);
        $this->assertSame(PlanPrice::ANNUAL, $installmentSubscription->billing_type);
        $this->assertSame('2027-09-17', $installmentSubscription->next_billing_date->toDateString());
        $this->assertDatabaseCount('invoices', 2);
        $this->assertDatabaseCount('cash_movements', 0);

        $partner = Partner::create([
            'company_name' => 'Gate 5 Partner',
            'email' => 'gate5-partner@example.com',
            'status' => 'active',
        ]);
        ClientPartnerAttribution::create([
            'client_id' => $installmentClient->id,
            'partner_id' => $partner->id,
            'commission_bps_snapshot' => 1000,
            'attributed_at' => now(),
            'created_by' => $user->id,
        ]);
        $this->assertSame(0, app(PartnerCommissionService::class)->summary($partner)['commission_minor']);

        $metrics = app(SaasMetricEventService::class);
        $this->assertSame(
            $metrics->normalizedArrMinorForPeriod($fullSubscription->billingPeriods()->firstOrFail()),
            $metrics->normalizedArrMinorForPeriod($installmentSubscription->billingPeriods()->firstOrFail())
        );
        $this->assertSame(2, SubscriptionMetricEvent::count());

        app(PaymentScheduleService::class)->ensureAnnualInstallmentSchedule(
            $installmentSubscription,
            $invoice,
            3,
            5
        );
        $this->assertSame(3, PaymentSchedule::where('subscription_id', $installmentSubscription->id)->count());

        $contract = Contract::where('subscription_id', $installmentSubscription->id)->sole();
        $snapshot = $contract->snapshot_data;
        $this->assertSame('installments', $snapshot['subscription']['payment_terms']);
        $this->assertSame(3, $snapshot['subscription']['installments_count']);
        $this->assertSame([33333, 33333, 33335], array_column($snapshot['schedules'], 'amount_due_minor'));
        $schedules->first()->update(['due_date' => '2030-01-01']);
        $this->assertSame($snapshot, $contract->fresh()->snapshot_data);
    }

    public function test_actual_allocated_payment_drives_projection_and_annual_renewal_gets_a_new_schedule(): void
    {
        [$user, $client, $annual] = $this->fixture('payment_and_renewal', PlanPrice::ANNUAL, 120000);
        [$subscription, $invoice] = app(SubscriptionBillingService::class)->startPaidSubscription(
            $client,
            $annual,
            [
                'quantity' => 1,
                'start_date' => '2026-09-17',
                'payment_terms' => 'installments',
                'installments_count' => 4,
                'installment_due_day' => 15,
            ],
            $user->id
        );

        $account = app(FinancialAccountService::class)->createAccount([
            'code' => 'gate5_cash',
            'name_ar' => 'Gate 5 Cash',
            'type' => FinancialAccount::TYPE_CASH,
            'opening_balance' => '0.000',
            'opening_date' => '2026-09-16 09:00:00',
        ], $user->id);
        app(AccountingSetupService::class)->ensureFinancialAccountMapping($account);
        app(PaymentAllocationService::class)->recordV2Payment($client, [
            'amount' => '35.000',
            'financial_account_id' => $account->id,
            'payment_method' => 'cash',
            'received_at' => '2026-09-17 10:00:00',
        ], [['invoice_id' => $invoice->id, 'amount' => '35.000']], false, $user->id);

        $projection = app(PaymentScheduleService::class)->annualInstallmentProjection($subscription);
        $this->assertSame(35000, $projection['paid_minor']);
        $this->assertSame(85000, $projection['remaining_minor']);
        $this->assertSame('paid', $projection['rows'][0]['status']);
        $this->assertSame('partially_paid', $projection['rows'][1]['status']);
        $this->assertSame(1, CashMovement::where('event_type', CashMovement::EVENT_PAYMENT_RECEIVED)->count());

        Carbon::setTestNow('2027-09-17 09:00:00');
        $result = app(SubscriptionBillingService::class)->generateRenewals('2027-09-17', false, $user->id);
        $this->assertSame(1, $result['renewed']);
        $this->assertSame(2, Invoice::where('subscription_id', $subscription->id)->count());
        $this->assertSame(8, PaymentSchedule::where('subscription_id', $subscription->id)->count());
        $this->assertSame(2, PaymentSchedule::where('subscription_id', $subscription->id)->pluck('invoice_id')->unique()->count());
        $this->assertSame('2028-09-17', $subscription->fresh()->next_billing_date->toDateString());
    }

    private function fixture(
        string $code,
        string $interval,
        int $amountMinor,
        ?User $user = null
    ): array {
        $user ??= User::factory()->create(['role' => User::ROLE_FOUNDER, 'is_active' => true]);
        $client = Client::create([
            'business_name' => 'Gate 5 '.$code,
            'phone' => '079'.random_int(1000000, 9999999),
            'city_area' => 'Amman',
            'business_category' => 'Technology',
            'lead_source' => 'Direct',
            'status' => 'prospect',
            'stage' => ClientLifecycle::PROSPECT,
            'created_by' => $user->id,
        ]);
        $plan = Plan::create([
            'code' => 'gate5_'.$code,
            'name_ar' => 'Gate 5 '.$code,
            'name_en' => 'Gate 5 '.$code,
            'is_active' => true,
            'created_by' => $user->id,
        ]);
        $price = PlanPrice::create([
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

        return [$user, $client, $price];
    }
}
