<?php

namespace Tests\Feature;

use App\Models\AccountingPeriod;
use App\Models\CashMovement;
use App\Models\Client;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\RevenueRecognitionAdjustment;
use App\Models\RevenueRecognitionPeriod;
use App\Models\RevenueRecognitionSchedule;
use App\Models\Subscription;
use App\Models\User;
use App\Services\AccountingReconciliationService;
use App\Services\AccountingSetupService;
use App\Services\CreditNoteService;
use App\Services\InvoiceService;
use App\Services\JournalPostingService;
use App\Services\RevenueRecognitionService;
use App\Services\SubscriptionBillingService;
use App\Support\ClientLifecycle;
use Carbon\Carbon;
use Database\Seeders\AssetCategorySeeder;
use Database\Seeders\ExpenseCategorySeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancePhaseE2BTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingsSeeder::class);
        $this->seed(ExpenseCategorySeeder::class);
        $this->seed(AssetCategorySeeder::class);
        app(AccountingSetupService::class)->ensureSeeded();
    }

    public function test_annual_subscription_creates_twelve_exact_periods_and_uneven_residual_distribution(): void
    {
        $admin = User::factory()->create(['role' => 'founder']);
        [, $invoice] = $this->startSubscription($admin, 384000, PlanPrice::ANNUAL, 0, '2026-01-01');
        $schedule = RevenueRecognitionSchedule::where('invoice_id', $invoice->id)
            ->where('policy', RevenueRecognitionSchedule::POLICY_SUBSCRIPTION_ANNUAL)
            ->firstOrFail();

        $this->assertSame(12, $schedule->periods()->count());
        $this->assertSame(array_fill(0, 12, 32000), $schedule->periods()->orderBy('period_index')->pluck('scheduled_minor')->all());

        [, $unevenInvoice] = $this->startSubscription($admin, 100001, PlanPrice::ANNUAL, 0, '2027-01-01');
        $amounts = RevenueRecognitionSchedule::where('invoice_id', $unevenInvoice->id)
            ->where('policy', RevenueRecognitionSchedule::POLICY_SUBSCRIPTION_ANNUAL)
            ->firstOrFail()
            ->periods()
            ->orderBy('period_index')
            ->pluck('scheduled_minor')
            ->all();

        $this->assertSame(100001, array_sum($amounts));
        $this->assertSame(1, max($amounts) - min($amounts));
    }

    public function test_monthly_recognition_posts_deferred_to_saas_revenue_without_cash_or_ar_and_is_idempotent(): void
    {
        $admin = User::factory()->create(['role' => 'founder']);
        [, $invoice] = $this->startSubscription($admin, 100000, PlanPrice::MONTHLY, 0, '2026-01-01');
        $setup = app(AccountingSetupService::class);
        $cashBefore = CashMovement::count();
        $arBefore = $this->accountLineTotal($setup->systemAccount('accounts_receivable')->id);

        $this->artisan('finance:recognize-revenue --through=2026-01-31 --dry-run')->assertExitCode(0);
        $this->assertDatabaseMissing('journal_entries', ['event_type' => 'revenue_recognized']);

        $this->artisan('finance:recognize-revenue --through=2026-01-15')->assertExitCode(0);
        $this->assertDatabaseMissing('journal_entries', ['event_type' => 'revenue_recognized']);

        $this->artisan('finance:recognize-revenue --through=2026-01-31')->assertExitCode(0);
        $period = RevenueRecognitionPeriod::firstOrFail();
        $journal = JournalEntry::where('event_key', 'revenue-recognition:period:'.$period->id)->firstOrFail();
        $this->assertDatabaseHas('journal_lines', ['journal_entry_id' => $journal->id, 'chart_account_id' => $setup->systemAccount('deferred_revenue')->id, 'debit_minor' => 100000]);
        $this->assertDatabaseHas('journal_lines', ['journal_entry_id' => $journal->id, 'chart_account_id' => $setup->systemAccount('saas_subscription_revenue')->id, 'credit_minor' => 100000]);
        $this->assertSame($cashBefore, CashMovement::count());
        $this->assertSame($arBefore, $this->accountLineTotal($setup->systemAccount('accounts_receivable')->id));

        $before = JournalEntry::count();
        $this->artisan('finance:recognize-revenue --through=2026-01-31')->assertExitCode(0);
        $this->assertSame($before, JournalEntry::count());
        $this->assertTrue(app(AccountingReconciliationService::class)->run()['ok']);
        $this->assertSame($invoice->id, $period->schedule->invoice_id);
    }

    public function test_setup_fee_recognizes_to_one_time_revenue_but_one_time_invoice_requires_confirmation(): void
    {
        $admin = User::factory()->create(['role' => 'founder']);
        $client = $this->createClient();
        [, $subscriptionInvoice] = $this->startSubscription($admin, 100000, PlanPrice::MONTHLY, 5000, '2026-02-01', $client);

        $this->artisan('finance:recognize-revenue --through=2026-02-01')->assertExitCode(0);
        $setupSchedule = RevenueRecognitionSchedule::where('invoice_id', $subscriptionInvoice->id)
            ->where('policy', RevenueRecognitionSchedule::POLICY_POINT_IN_TIME_SETUP)
            ->firstOrFail();
        $setupPeriod = $setupSchedule->periods()->firstOrFail();
        $setupJournal = JournalEntry::where('event_key', 'revenue-recognition:period:'.$setupPeriod->id)->firstOrFail();
        $this->assertDatabaseHas('journal_lines', ['journal_entry_id' => $setupJournal->id, 'chart_account_id' => app(AccountingSetupService::class)->systemAccount('one_time_service_revenue')->id, 'credit_minor' => 5000]);

        $invoice = app(InvoiceService::class)->createIssuedOneTime($client, [[
            'line_type' => InvoiceLine::TYPE_ONE_TIME_SERVICE,
            'description_snapshot' => 'Training',
            'quantity' => 1,
            'unit_price_minor' => 20000,
            'subtotal_minor' => 20000,
            'discount_minor' => 0,
            'tax_rate_bps' => 0,
            'tax_minor' => 0,
            'total_minor' => 20000,
            'sort_order' => 10,
        ]], Carbon::parse('2026-02-10'), Carbon::parse('2026-02-10'), 'Training', $admin->id);

        $manual = RevenueRecognitionSchedule::where('invoice_id', $invoice->id)->firstOrFail();
        $this->assertSame(RevenueRecognitionSchedule::STATUS_NEEDS_REVIEW, $manual->status);
        $this->artisan('finance:recognize-revenue --through=2026-02-10')->assertExitCode(0);
        $this->assertDatabaseMissing('journal_entries', ['event_key' => 'revenue-recognition:period:'.$manual->id]);

        app(RevenueRecognitionService::class)->confirmPointInTimeService($manual, '2026-02-12', 'Work completed', $admin->id);
        $this->artisan('finance:recognize-revenue --through=2026-02-12')->assertExitCode(0);
        $period = $manual->fresh('periods')->periods->first();
        $this->assertSame(20000, $period->fresh()->recognized_minor);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(RevenueRecognitionService::class)->confirmPointInTimeService($manual->fresh(), '2026-02-13', 'Try twice', $admin->id);
    }

    public function test_recognition_aware_credit_note_splits_future_deferred_and_recognized_revenue(): void
    {
        $admin = User::factory()->create(['role' => 'founder']);
        [, $invoice] = $this->startSubscription($admin, 120000, PlanPrice::ANNUAL, 0, '2026-01-01');
        $line = $invoice->lines()->where('line_type', InvoiceLine::TYPE_SUBSCRIPTION)->firstOrFail();
        $this->artisan('finance:recognize-revenue --through=2026-01-31')->assertExitCode(0);

        $credit = app(CreditNoteService::class)->createIssued($invoice->client, [
            'issue_date' => '2026-02-01',
            'original_invoice_id' => $invoice->id,
            'reason' => 'Service scope reduction',
            'lines' => [[
                'invoice_line_id' => $line->id,
                'description' => 'Annual reduction',
                'subtotal_jod' => '115.000',
                'tax_jod' => '0.000',
            ]],
        ], $admin->id);

        $journal = JournalEntry::where('event_key', 'billing:credit-note:'.$credit->id.':issued')->firstOrFail();
        $setup = app(AccountingSetupService::class);
        $this->assertDatabaseHas('journal_lines', ['journal_entry_id' => $journal->id, 'chart_account_id' => $setup->systemAccount('deferred_revenue')->id, 'debit_minor' => 110000]);
        $this->assertDatabaseHas('journal_lines', ['journal_entry_id' => $journal->id, 'chart_account_id' => $setup->systemAccount('saas_subscription_revenue')->id, 'debit_minor' => 5000]);
        $this->assertSame(110000, RevenueRecognitionAdjustment::where('adjustment_type', RevenueRecognitionAdjustment::TYPE_FUTURE_DEFERRED_REDUCTION)->sum('amount_minor'));
        $this->assertSame(5000, RevenueRecognitionAdjustment::where('adjustment_type', RevenueRecognitionAdjustment::TYPE_RECOGNIZED_REVENUE_REDUCTION)->sum('amount_minor'));

        $this->artisan('finance:recognize-revenue --through=2026-12-31')->assertExitCode(0);
        $result = app(AccountingReconciliationService::class)->run();
        $this->assertTrue($result['ok'], json_encode($result['failures']));
        $this->assertSame(5000, $result['recognized_revenue']['saas_subscription_revenue']['general_ledger_minor']);
    }

    public function test_schedule_backfill_is_idempotent_and_partner_cannot_manage_recognition(): void
    {
        $admin = User::factory()->create(['role' => 'founder']);
        [, $partnerUser] = $this->createPartnerUser();
        $invoice = $this->directIssuedInvoice($admin, 33333, '2026-03-01', '2026-03-31');

        $this->artisan('finance:backfill-revenue-schedules --dry-run')->assertExitCode(0);
        $this->assertSame(0, RevenueRecognitionSchedule::count());

        $this->artisan('finance:backfill-revenue-schedules')->assertExitCode(0);
        $this->assertSame(1, RevenueRecognitionSchedule::count());
        $this->artisan('finance:backfill-revenue-schedules')->assertExitCode(0);
        $this->assertSame(1, RevenueRecognitionSchedule::count());

        $this->actingAs($partnerUser)->post(route('accounting.revenue-recognition.run'), [
            'through' => '2026-03-31',
        ])->assertForbidden();
        $this->assertSame($invoice->id, RevenueRecognitionSchedule::firstOrFail()->invoice_id);
    }

    public function test_closed_period_recognition_posts_in_current_open_period_with_original_period_metadata(): void
    {
        Carbon::setTestNow('2026-02-15 10:00:00');
        $admin = User::factory()->create(['role' => 'founder']);
        $this->startSubscription($admin, 50000, PlanPrice::MONTHLY, 0, '2026-01-01');
        $period = AccountingPeriod::firstOrCreate([
            'period_key' => '2026-01',
        ], [
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'status' => AccountingPeriod::STATUS_OPEN,
        ]);
        app(JournalPostingService::class)->closePeriod($period, $admin->id, 'Close January');

        $this->artisan('finance:recognize-revenue --through=2026-01-31')->assertExitCode(0);
        $journal = JournalEntry::where('event_type', 'revenue_recognized')->firstOrFail();
        $this->assertSame('2026-02-15', $journal->entry_date->toDateString());
        $this->assertSame('2026-01-31', $journal->metadata['original_service_period_end']);

        Carbon::setTestNow();
    }

    private function startSubscription(
        User $admin,
        int $amountMinor,
        string $interval,
        int $setupFeeMinor,
        string $startDate,
        ?Client $client = null
    ): array {
        $client = $client ?: $this->createClient();
        $plan = Plan::create([
            'code' => 'plan_'.strtolower($interval).'_'.$amountMinor.'_'.$setupFeeMinor.'_'.uniqid(),
            'name_ar' => 'Revenue Plan',
            'name_en' => 'Revenue Plan',
            'is_active' => true,
            'created_by' => $admin->id,
        ]);
        $price = PlanPrice::create([
            'plan_id' => $plan->id,
            'billing_interval' => $interval,
            'currency' => 'JOD',
            'amount_minor' => $amountMinor,
            'setup_fee_minor' => $setupFeeMinor,
            'included_branch_quantity' => 1,
            'default_tax_rate_bps' => 0,
            'effective_from' => '2025-01-01 00:00:00',
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        return app(SubscriptionBillingService::class)->startPaidSubscription($client, $price, [
            'quantity' => 1,
            'start_date' => $startDate,
        ], $admin->id);
    }

    private function directIssuedInvoice(User $admin, int $preTaxMinor, string $start, string $end): Invoice
    {
        $client = $this->createClient();
        $subscription = Subscription::create([
            'client_id' => $client->id,
            'user_id' => $admin->id,
            'billing_engine_version' => 'v2',
            'billing_interval_v2' => PlanPrice::MONTHLY,
            'currency' => 'JOD',
            'quantity' => 1,
            'unit_price_minor' => $preTaxMinor,
            'subtotal_minor' => $preTaxMinor,
            'discount_minor' => 0,
            'tax_rate_bps' => 0,
            'tax_minor_v2' => 0,
            'total_minor' => $preTaxMinor,
            'current_period_start' => $start,
            'current_period_end' => $end,
            'next_billing_date' => Carbon::parse($end)->addDay()->toDateString(),
            'billing_type' => PlanPrice::MONTHLY,
            'total_price' => '33.333',
            'grand_total' => '33.333',
            'start_date' => $start,
            'renewal_date' => Carbon::parse($end)->addDay()->toDateString(),
            'installments_count' => 1,
            'status' => 'active',
            'version' => 1,
        ]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-DIRECT-'.$preTaxMinor,
            'client_id' => $client->id,
            'subscription_id' => $subscription->id,
            'currency' => 'JOD',
            'status' => Invoice::STATUS_ISSUED,
            'issue_date' => $start,
            'due_date' => $start,
            'subtotal_minor' => $preTaxMinor,
            'discount_minor' => 0,
            'tax_minor' => 0,
            'total_minor' => $preTaxMinor,
            'billing_period_start' => $start,
            'billing_period_end' => $end,
            'issued_at' => now(),
            'created_by' => $admin->id,
        ]);
        $invoice->lines()->create([
            'line_type' => InvoiceLine::TYPE_SUBSCRIPTION,
            'description_snapshot' => 'Direct subscription',
            'quantity' => 1,
            'unit_price_minor' => $preTaxMinor,
            'subtotal_minor' => $preTaxMinor,
            'discount_minor' => 0,
            'tax_rate_bps' => 0,
            'tax_minor' => 0,
            'total_minor' => $preTaxMinor,
            'sort_order' => 10,
        ]);

        return $invoice->fresh('lines');
    }

    private function createClient(): Client
    {
        return Client::create([
            'business_name' => 'Phase E2B Client '.uniqid(),
            'phone' => '079'.random_int(1000000, 9999999),
            'city_area' => 'Amman',
            'business_category' => 'Restaurant',
            'lead_source' => 'Direct',
            'status' => 'prospect',
            'stage' => ClientLifecycle::PROSPECT,
        ]);
    }

    private function createPartnerUser(): array
    {
        return [null, User::factory()->create(['role' => 'external'])];
    }

    private function accountLineTotal(int $chartAccountId): int
    {
        return (int) JournalLine::where('chart_account_id', $chartAccountId)->sum('debit_minor')
            - (int) JournalLine::where('chart_account_id', $chartAccountId)->sum('credit_minor');
    }
}
