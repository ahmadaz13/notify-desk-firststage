<?php

namespace Tests\Feature;

use App\Models\AssetCategory;
use App\Models\CapitalFundingTransaction;
use App\Models\Client;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FinancialAccount;
use App\Models\FixedAsset;
use App\Models\FundingSource;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\User;
use App\Services\AccountingReconciliationService;
use App\Services\AccountingSetupService;
use App\Services\CapitalManagementService;
use App\Services\CreditNoteService;
use App\Services\FinancialAccountService;
use App\Services\FinancialReportingReconciliationService;
use App\Services\FinancialStatementService;
use App\Services\InvoiceService;
use App\Services\OperatingExpenseService;
use App\Services\PaymentAllocationService;
use App\Services\SubscriptionBillingService;
use App\Support\ClientLifecycle;
use App\Support\ReportingPeriod;
use Carbon\Carbon;
use Database\Seeders\AssetCategorySeeder;
use Database\Seeders\ExpenseCategorySeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancePhaseF1Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-01-31 12:00:00');
        $this->seed(SettingsSeeder::class);
        $this->seed(ExpenseCategorySeeder::class);
        $this->seed(AssetCategorySeeder::class);
        app(AccountingSetupService::class)->ensureSeeded();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_financial_statement_service_uses_accounting_sources_and_reconciles_management_reports(): void
    {
        $admin = User::factory()->create(['role' => 'founder']);
        $this->buildReportingScenario($admin);
        $period = $this->januaryPeriod();
        $service = app(FinancialStatementService::class);

        $profitAndLoss = $service->profitAndLoss($period);
        $this->assertSame(100000, $profitAndLoss['total_revenue_minor']);
        $this->assertSame(100000, $profitAndLoss['saas_revenue_minor']);
        $this->assertSame(0, $profitAndLoss['one_time_revenue_minor']);
        $this->assertSame(22000, $profitAndLoss['total_expenses_minor']);
        $this->assertSame(78000, $profitAndLoss['net_income_minor']);

        $balanceSheet = $service->balanceSheet($period->end);
        $this->assertSame(265000, $balanceSheet['sections']['assets']['cash_and_cash_equivalents']['total_minor']);
        $this->assertSame(118000, $balanceSheet['sections']['assets']['accounts_receivable']['total_minor']);
        $this->assertSame(42000, $balanceSheet['sections']['assets']['fixed_assets_at_recorded_cost']['total_minor']);
        $this->assertSame(20000, $balanceSheet['sections']['liabilities']['billing_clearing']['total_minor']);
        $this->assertSame(11600, $balanceSheet['sections']['liabilities']['customer_credits']['total_minor']);
        $this->assertSame(6400, $balanceSheet['sections']['liabilities']['sales_tax_payable']['total_minor']);
        $this->assertSame(40000, $balanceSheet['sections']['liabilities']['deferred_revenue']['total_minor']);
        $this->assertSame(19000, $balanceSheet['sections']['liabilities']['due_to_related_parties']['total_minor']);
        $this->assertSame(0, $balanceSheet['equation_difference_minor']);

        $cashFlow = $service->cashFlow($period);
        $this->assertSame(200000, $cashFlow['opening_cash_minor']);
        $this->assertSame(45000, $cashFlow['operating_cash_flow_minor']);
        $this->assertSame(-30000, $cashFlow['investing_cash_flow_minor']);
        $this->assertSame(50000, $cashFlow['financing_cash_flow_minor']);
        $this->assertSame(0, $cashFlow['internal_transfer_minor']);
        $this->assertSame(265000, $cashFlow['closing_cash_minor']);
        $this->assertSame(0, $cashFlow['closing_difference_minor']);
        $this->assertSame(0, $cashFlow['gl_cash_difference_minor']);

        $aging = $service->arAging($period->end);
        $this->assertSame(118000, $aging['summary']['total_ar_minor']);
        $this->assertSame(118000, $aging['summary']['total_overdue_minor']);
        $this->assertSame(0, $aging['summary']['difference_minor']);

        $deferred = $service->deferredRevenueReport($period);
        $this->assertSame(40000, $deferred['summary']['closing_deferred_revenue_minor']);
        $this->assertSame(40000, $deferred['summary']['operational_closing_deferred_minor']);
        $this->assertSame(0, $deferred['summary']['difference_minor']);

        $recognized = $service->recognizedRevenueReport($period);
        $this->assertSame(100000, $recognized['saas_revenue_minor']);
        $this->assertSame(0, $recognized['one_time_revenue_minor']);
        $this->assertSame(100000, (int) $recognized['by_month']->sum('amount_minor'));

        $credits = $service->customerCredits();
        $this->assertSame(20000, $credits['unallocated_payment_credit_minor']);
        $this->assertSame(11600, $credits['credit_note_credit_minor']);
        $this->assertSame(20000, $credits['billing_clearing_gl_minor']);
        $this->assertSame(11600, $credits['customer_credits_gl_minor']);

        $tax = $service->salesTaxReport();
        $this->assertSame(8000, $tax['invoice_tax_minor']);
        $this->assertSame(1600, $tax['credit_note_tax_reductions_minor']);
        $this->assertSame(6400, $tax['net_sales_tax_payable_minor']);
        $this->assertSame(0, $tax['difference_minor']);

        $expenses = $service->expenseReport($period);
        $this->assertSame(15000, $expenses['company_funded_minor']);
        $this->assertSame(7000, $expenses['personally_funded_minor']);

        $capital = $service->capitalAssetReport($period);
        $this->assertSame(50000, (int) $capital['funding_by_source']->sum('amount_minor'));
        $this->assertSame(30000, $capital['company_funded_assets_minor']);
        $this->assertSame(12000, $capital['personally_funded_assets_minor']);
        $this->assertSame(2, $capital['active_asset_count']);

        $this->assertTrue(app(AccountingReconciliationService::class)->run()['ok']);
        $reporting = app(FinancialReportingReconciliationService::class)->run($period);
        $this->assertTrue($reporting['ok'], json_encode($reporting['failures']));
    }

    public function test_management_dashboard_route_exports_csv_and_keeps_partner_boundary(): void
    {
        $admin = User::factory()->create(['role' => 'founder']);
        [, $partnerUser] = $this->createPartnerUser();
        $this->buildReportingScenario($admin);
        $query = [
            'range' => 'custom_date_range',
            'date_from' => '2026-01-01',
            'date_to' => '2026-01-31',
        ];

        // P6 (§12, §14): the overview is a dashboard; statements live under Financial Reports.
        $this->actingAs($admin)->get(route('finance.index'))
            ->assertOk()
            ->assertSee(__('notify.finance_hub.sections.overview'))
            ->assertSee(__('notify.finance_hub.overview.saas_title'));
        $this->actingAs($admin)->get(route('finance.reports', ['report' => 'profit-and-loss'] + $query))
            ->assertOk()
            ->assertSee(__('notify.finance_hub.reports.names.profit-and-loss'))
            ->assertSee(__('notify.finance_hub.reports.names.financial-position'))
            ->assertSee(__('notify.finance_hub.reports.names.cash-flow'));
        $this->actingAs($admin)->get(route('finance.reports', ['report' => 'financial-position'] + $query))
            ->assertOk()
            ->assertSee(__('notify.finance_hub.reports.position_note'));

        $export = $this->actingAs($admin)->get(route('finance.reports.export', ['report' => 'profit-and-loss'] + $query));
        $export->assertOk();
        $export->assertDownload('notify-profit-and-loss-2026-01-01_2026-01-31.csv');
        $this->assertStringContainsString('text/csv', (string) $export->headers->get('content-type'));
        $csv = $export->streamedContent();
        $this->assertStringContainsString('Total Recognized Revenue', $csv);
        $this->assertStringContainsString('100.000', $csv);
        $this->assertStringContainsString('Management Net Income', $csv);
        $this->assertStringContainsString('78.000', $csv);

        $this->actingAs($partnerUser)->get(route('finance.index', $query))->assertForbidden();
        $this->actingAs($partnerUser)->get(route('finance.reports.export', ['report' => 'profit-and-loss'] + $query))->assertForbidden();
    }

    private function buildReportingScenario(User $admin): void
    {
        $client = $this->createClient();
        $cash = $this->createFinancialAccount($admin, 'f1_cash_main', 'F1 Main Cash', '200.000', '2025-12-31 10:00:00');
        $wallet = $this->createFinancialAccount($admin, 'f1_cash_wallet', 'F1 Wallet', '0.000', '2025-12-31 10:00:00');

        [, $subscriptionInvoice] = $this->startSubscription($admin, $client, 100000, '2026-01-01');
        $this->artisan('finance:recognize-revenue --through=2026-01-31')->assertExitCode(0);
        Carbon::setTestNow('2026-01-31 12:00:00');

        app(PaymentAllocationService::class)->recordV2Payment($client, [
            'amount' => '60.000',
            'financial_account_id' => $cash->id,
            'payment_method' => 'cash',
            'received_at' => '2026-01-15 10:00:00',
        ], [['invoice_id' => $subscriptionInvoice->id, 'amount' => '40.000']], false, $admin->id);

        $oneTime = app(InvoiceService::class)->createIssuedOneTime($client, [[
            'line_type' => InvoiceLine::TYPE_ONE_TIME_SERVICE,
            'description_snapshot' => 'Implementation service',
            'quantity' => 1,
            'unit_price_minor' => 50000,
            'subtotal_minor' => 50000,
            'discount_minor' => 0,
            'tax_rate_bps' => 1600,
            'tax_minor' => 8000,
            'total_minor' => 58000,
            'sort_order' => 10,
        ]], Carbon::parse('2026-01-05'), Carbon::parse('2026-01-20'), 'Implementation service', $admin->id);
        $line = $oneTime->lines()->firstOrFail();
        app(CreditNoteService::class)->createIssued($client, [
            'issue_date' => '2026-01-08',
            'original_invoice_id' => $oneTime->id,
            'reason' => 'Scope adjustment',
            'lines' => [[
                'invoice_line_id' => $line->id,
                'description' => 'Scope adjustment',
                'subtotal_jod' => '10.000',
                'tax_jod' => '1.600',
            ]],
        ], $admin->id);

        $companyCategory = ExpenseCategory::where('key', 'hosting')->firstOrFail();
        $personalCategory = ExpenseCategory::where('key', 'software_subscriptions')->firstOrFail();
        $payer = User::factory()->create(['role' => 'founder']);
        app(OperatingExpenseService::class)->createV2Expense([
            'amount' => '15.000',
            'category_id' => $companyCategory->id,
            'payee_name' => 'Cloud Host',
            'funding_source' => Expense::FUNDING_COMPANY_ACCOUNT,
            'financial_account_id' => $cash->id,
            'incurred_on' => '2026-01-16',
            'paid_at' => '2026-01-16 11:00:00',
        ], $admin);
        app(OperatingExpenseService::class)->createV2Expense([
            'amount' => '7.000',
            'category_id' => $personalCategory->id,
            'payee_name' => 'Software Vendor',
            'funding_source' => Expense::FUNDING_PERSONAL,
            'paid_by_user_id' => $payer->id,
            'incurred_on' => '2026-01-16',
            'paid_at' => '2026-01-16 12:00:00',
        ], $admin);

        $source = app(CapitalManagementService::class)->createFundingSource([
            'name' => 'Owner Funding',
            'type' => FundingSource::TYPE_OWNER,
        ], $admin);
        app(CapitalManagementService::class)->recordCapitalFunding([
            'funding_source_id' => $source->id,
            'funding_type' => CapitalFundingTransaction::TYPE_OWNER_CONTRIBUTION,
            'financial_account_id' => $cash->id,
            'amount' => '50.000',
            'received_at' => '2026-01-17 09:00:00',
        ], $admin);

        $computerCategory = AssetCategory::where('code', 'computers')->firstOrFail();
        $mobileCategory = AssetCategory::where('code', 'mobile_devices')->firstOrFail();
        app(CapitalManagementService::class)->acquireFixedAsset([
            'name' => 'Office Laptop',
            'asset_category_id' => $computerCategory->id,
            'acquisition_cost' => '30.000',
            'funding_source' => FixedAsset::FUNDING_COMPANY_ACCOUNT,
            'financial_account_id' => $cash->id,
            'acquired_at' => '2026-01-18',
            'in_service_at' => '2026-01-18',
        ], $admin);
        app(CapitalManagementService::class)->acquireFixedAsset([
            'name' => 'Founder Phone',
            'asset_category_id' => $mobileCategory->id,
            'acquisition_cost' => '12.000',
            'funding_source' => FixedAsset::FUNDING_PERSONAL,
            'paid_by_user_id' => $payer->id,
            'acquired_at' => '2026-01-18',
            'in_service_at' => '2026-01-18',
        ], $admin);

        app(FinancialAccountService::class)->createTransfer([
            'from_financial_account_id' => $cash->id,
            'to_financial_account_id' => $wallet->id,
            'amount' => '10.000',
            'transferred_at' => '2026-01-19 10:00:00',
        ], $admin->id);
    }

    private function januaryPeriod(): ReportingPeriod
    {
        return new ReportingPeriod(
            Carbon::parse('2026-01-01')->startOfDay(),
            Carbon::parse('2026-01-31')->endOfDay(),
            'custom_date_range'
        );
    }

    private function startSubscription(User $admin, Client $client, int $amountMinor, string $startDate): array
    {
        $plan = Plan::create([
            'code' => 'f1_plan_'.uniqid(),
            'name_ar' => 'F1 Revenue Plan',
            'name_en' => 'F1 Revenue Plan',
            'is_active' => true,
            'created_by' => $admin->id,
        ]);
        $price = PlanPrice::create([
            'plan_id' => $plan->id,
            'billing_interval' => PlanPrice::MONTHLY,
            'currency' => 'JOD',
            'amount_minor' => $amountMinor,
            'setup_fee_minor' => 0,
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

    private function createFinancialAccount(User $admin, string $code, string $name, string $openingBalance, string $openingDate): FinancialAccount
    {
        $account = app(FinancialAccountService::class)->createAccount([
            'code' => $code,
            'name_ar' => $name,
            'type' => FinancialAccount::TYPE_CASH,
            'opening_balance' => $openingBalance,
            'opening_date' => $openingDate,
        ], $admin->id);

        app(AccountingSetupService::class)->ensureFinancialAccountMapping($account);

        return $account->fresh();
    }

    private function createClient(): Client
    {
        return Client::create([
            'business_name' => 'Phase F1 Client',
            'phone' => '0791111111',
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
}
