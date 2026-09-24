<?php

namespace Tests\Feature;

use App\Models\CapitalFundingTransaction;
use App\Models\CashMovement;
use App\Models\Client;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FinancialAccount;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\JournalLine;
use App\Models\Payment;
use App\Models\PaymentReceiptConfirmation;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\AccountingSetupService;
use App\Services\FinanceOverviewService;
use App\Services\FinancialAccountBalanceService;
use App\Services\FinancialStatementService;
use App\Services\OperatingExpenseService;
use App\Services\SaasMetricsService;
use App\Services\SubscriptionBillingService;
use App\Support\ClientLifecycle;
use Carbon\Carbon;
use Database\Seeders\ExpenseCategorySeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Mockery;
use Tests\TestCase;

/**
 * P6 — Finance architecture & dashboards (§9.6–9.7, §10.4, §12–14, D-16, D-24).
 * Money figures are asserted against the authoritative engine, never recomputed by hand in the UI.
 */
class V1P6FinanceArchitectureTest extends TestCase
{
    use RefreshDatabase;

    private User $founder;
    private User $admin;
    private User $staff;
    private Client $client;
    private FinancialAccount $cashBox;
    private FinancialAccount $cliq;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-23 12:00:00');
        $this->seed(SettingsSeeder::class);
        $this->seed(ExpenseCategorySeeder::class);
        app(AccountingSetupService::class)->ensureSeeded();

        $this->founder = User::factory()->create(['role' => User::ROLE_FOUNDER, 'is_active' => true]);
        $this->admin = User::factory()->create(['role' => User::ROLE_FOUNDER, 'is_active' => true]);
        $this->staff = User::factory()->create(['role' => User::ROLE_STAFF, 'is_active' => true]);
        $this->client = $this->makeClient('P6 Bakery');
        $this->cashBox = FinancialAccount::where('code', 'CASH-BOX')->firstOrFail();
        $this->cliq = FinancialAccount::where('code', 'CLIQ')->firstOrFail();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ── Routes & authorization ─────────────────────────────────────────

    public function test_canonical_finance_pages_render_for_owner_level_users(): void
    {
        $this->seedScenario();

        foreach ([$this->founder, $this->admin] as $owner) {
            foreach (['finance.index', 'finance.collections', 'finance.expenses', 'finance.accounts', 'finance.accounting'] as $route) {
                $this->actingAs($owner)->get(route($route))->assertOk()->assertSee('data-finance-sections', false);
            }
            foreach (\App\Http\Controllers\FinanceReportController::REPORTS as $report) {
                $this->actingAs($owner)->get(route('finance.reports', ['report' => $report]))->assertOk()->assertSee('data-report="'.$report.'"', false);
            }
            foreach (\App\Http\Controllers\CollectionsController::TABS as $tab) {
                $this->actingAs($owner)->get(route('finance.collections', ['tab' => $tab]))->assertOk()->assertSee('data-collections-panel="'.$tab.'"', false);
            }
            foreach (\App\Http\Controllers\AccountingController::TABS as $tab) {
                $this->actingAs($owner)->get(route('finance.accounting', ['tab' => $tab]))->assertOk()->assertSee('data-accounting-panel="'.$tab.'"', false);
            }
        }
    }

    public function test_staff_is_denied_every_owner_finance_route_server_side(): void
    {
        foreach (['finance.index', 'finance.collections', 'finance.expenses', 'finance.accounts', 'finance.accounting', 'finance.reports'] as $route) {
            $this->actingAs($this->staff)->get(route($route))->assertForbidden();
        }
        $this->actingAs($this->staff)->get(route('finance.reports.export', ['report' => 'profit-and-loss']))->assertForbidden();
        $this->actingAs($this->staff)->get('/finance?section=collections')->assertForbidden();

        foreach (['/collections', '/financial-accounts', '/operating-expenses', '/accounting', '/executive', '/saas-metrics', '/subscription-billing', '/finance/export/profit-and-loss'] as $legacy) {
            $this->actingAs($this->staff)->get($legacy)->assertForbidden();
        }

        Setting::set('feature_capital_financing', '1');
        $this->actingAs($this->staff)->get(route('finance.capital'))->assertForbidden();
    }

    public function test_legacy_finance_urls_redirect_to_canonical_destinations(): void
    {
        $map = [
            '/collections?client_id='.$this->client->id => route('finance.collections', ['client_id' => $this->client->id]),
            '/financial-accounts' => route('finance.accounts'),
            '/operating-expenses' => route('finance.expenses'),
            '/accounting' => route('finance.accounting'),
            '/executive' => route('finance.index'),
            '/saas-metrics' => route('finance.reports', ['report' => 'subscription-metrics']),
            '/subscription-billing' => route('finance.accounting', ['tools' => 1]),
            '/finance?section=collections' => route('finance.collections'),
            '/finance?section=reports' => route('finance.reports'),
            '/finance?section=advanced' => route('finance.accounts'),
            '/finance?section=capital_assets' => route('finance.index'),
            '/finance/export/balance-sheet' => route('finance.reports.export', ['report' => 'financial-position']),
            '/saas-metrics/export/summary' => route('finance.reports.export', ['report' => 'subscription-metrics', 'dataset' => 'summary']),
        ];

        foreach ($map as $from => $to) {
            $this->actingAs($this->founder)->get($from)->assertStatus(301)->assertRedirect($to);
        }
    }

    public function test_capital_route_is_gated_and_fixed_assets_stay_absent(): void
    {
        $this->actingAs($this->founder)->get(route('finance.capital'))->assertNotFound();
        $this->actingAs($this->founder)->get('/capital-management')->assertNotFound();
        $this->actingAs($this->founder)->get(route('finance.index'))->assertDontSee('data-finance-section="capital"', false);

        Setting::set('feature_capital_financing', '1');
        $this->actingAs($this->founder)->get(route('finance.capital'))
            ->assertOk()
            ->assertSee('name="payment_method"', false)
            ->assertDontSee('name="financial_account_id"', false);
        $this->actingAs($this->founder)->get(route('finance.index'))->assertSee('data-finance-section="capital"', false);
        $this->actingAs($this->founder)->get('/capital-management')->assertRedirect(route('finance.capital'));

        $this->assertFalse(Route::has('fixed-assets.index'));
        $this->assertFalse(Route::has('asset-categories.store'));
    }

    // ── Overview ───────────────────────────────────────────────────────

    public function test_overview_numbers_come_from_the_authoritative_engine(): void
    {
        $this->seedScenario();
        $overview = app(FinanceOverviewService::class)->build();
        $balances = app(FinancialAccountBalanceService::class);

        $cash = $overview['kpis']['available_cash'];
        $this->assertSame(33000, $cash['cash_box_minor']);
        $this->assertSame(10000, $cash['cliq_minor']);
        $this->assertSame($balances->currentBalanceMinor($this->cashBox) + $balances->currentBalanceMinor($this->cliq), $cash['total_minor']);
        $this->assertSame(43000, $cash['total_minor']);

        // 100 JOD invoice − 40 (this month) − 10 (last month) auto-allocated → 50 outstanding, all overdue.
        $this->assertSame(50000, $overview['kpis']['receivables']['total_minor']);
        $this->assertSame(50000, $overview['kpis']['receivables']['overdue_minor']);
        $aging = app(FinancialStatementService::class)->arAging(Carbon::today());
        $this->assertSame($aging['summary']['total_ar_minor'], $overview['kpis']['receivables']['total_minor']);

        $this->assertSame(40000, $overview['kpis']['collections']['current_minor']);
        $this->assertSame(10000, $overview['kpis']['collections']['previous_minor']);
        $this->assertSame(300, $overview['kpis']['collections']['delta_percent']);
        $this->assertSame(7000, $overview['kpis']['expenses']['current_minor']);
        $this->assertNull($overview['kpis']['expenses']['delta_percent'], 'No percentage against an empty month.');

        $this->assertSame(1, $overview['attention']['pending_receipts']['count']);
        $this->assertSame(1, $overview['attention']['overdue_count']);
        $this->assertCount(6, $overview['trend']);
        $this->assertSame(['2026-04', '2026-05', '2026-06', '2026-07', '2026-08', '2026-09'], $overview['trend']->pluck('month')->all());
        $this->assertSame(40000, $overview['trend']->last()['collections_minor']);
        $this->assertSame(7000, $overview['trend']->last()['expenses_minor']);

        $pnl = app(FinancialStatementService::class)->profitAndLoss(new \App\Support\ReportingPeriod(Carbon::today()->startOfMonth(), Carbon::today()->endOfMonth(), 'this_month'));
        $this->assertSame($pnl['net_income_minor'], $overview['result']['net_minor']);
    }

    public function test_overview_page_shows_four_primary_kpis_attention_and_separate_saas_strip(): void
    {
        $this->seedScenario();

        $html = $this->actingAs($this->founder)->withSession(['locale' => 'en'])->get(route('finance.index'))->assertOk()->getContent();

        preg_match('/data-primary-kpis.*?<\/section>/s', $html, $kpiSection);
        $this->assertSame(4, substr_count($kpiSection[0], 'data-kpi="'));
        foreach (['available-cash', 'receivables', 'collections', 'expenses'] as $kpi) {
            $this->assertStringContainsString('data-kpi="'.$kpi.'"', $kpiSection[0]);
        }
        $this->assertStringContainsString('43.000', $kpiSection[0]);
        $this->assertStringContainsString('data-attention="pending-receipts"', $html);
        $this->assertStringContainsString('data-attention="overdue"', $html);
        $this->assertStringContainsString(route('finance.collections', ['tab' => 'pending']), $html);
        $this->assertStringContainsString('Subscription Metrics — not accounting revenue', $html);
        $this->assertStringContainsString('data-trend-table', $html);
        preg_match('/data-finance-page="overview".*?class="notify-fin-drill".*?<\/nav>/s', $html, $page);
        $this->assertStringNotContainsString('<form', $page[0], 'No data-entry forms on the Overview.');

        $this->actingAs($this->founder)->withSession(['locale' => 'ar'])->get(route('finance.index'))
            ->assertSee('مؤشرات الاشتراكات — ليست إيراداً محاسبياً');
    }

    public function test_overview_renewals_and_recurring_expense_queues(): void
    {
        $product = Product::sellable()->orderBy('id')->firstOrFail();
        app(SubscriptionBillingService::class)->startAgreedSubscription($this->makeClient('Renewal Cafe'), collect([$product]), [
            'billing_interval' => 'monthly',
            'agreed_value_minor' => 20000,
            'start_date' => '2026-09-01',
            'payment_terms' => 'full',
        ], $this->founder->id);
        $category = ExpenseCategory::firstOrFail();
        $template = \App\Models\RecurringExpenseTemplate::create([
            'name' => 'Office rent',
            'category_id' => $category->id,
            'currency' => 'JOD',
            'amount_minor' => 150000,
            'frequency' => 'monthly',
            'interval_count' => 1,
            'start_date' => '2026-09-01',
            'next_due_date' => '2026-10-26',
            'default_financial_account_id' => $this->cashBox->id,
            'default_funding_source' => Expense::FUNDING_COMPANY_ACCOUNT,
            'is_active' => true,
        ]);
        \App\Models\RecurringExpenseObligation::create([
            'recurring_expense_template_id' => $template->id,
            'category_id' => $category->id,
            'category_name_snapshot' => $category->name_ar,
            'currency' => 'JOD',
            'due_date' => '2026-09-26',
            'expected_amount_minor' => 150000,
            'default_financial_account_id' => $this->cashBox->id,
            'default_funding_source' => Expense::FUNDING_COMPANY_ACCOUNT,
            'status' => \App\Models\RecurringExpenseObligation::STATUS_PENDING,
        ]);

        $overview = app(FinanceOverviewService::class)->build();
        $this->assertSame(1, $overview['attention']['renewals']['count']);
        $this->assertSame(1, $overview['attention']['recurring_expenses']['count']);
        $this->assertSame(150000, $overview['attention']['recurring_expenses']['total_minor']);

        $this->actingAs($this->founder)->get(route('finance.index'))
            ->assertSee('data-attention="renewals"', false)
            ->assertSee('data-attention="recurring-expenses"', false)
            ->assertSee('Renewal Cafe');
    }

    // ── Collections ────────────────────────────────────────────────────

    public function test_collections_tabs_show_the_right_items_and_approve_uses_the_p1_path(): void
    {
        $this->seedScenario();
        $receipt = PaymentReceiptConfirmation::pending()->sole();

        $this->actingAs($this->founder)->get(route('finance.collections'))
            ->assertOk()
            ->assertSee('data-collections-panel="pending"', false)
            ->assertSee('data-pending-receipt="'.$receipt->id.'"', false)
            ->assertSee(route('payment-receipts.approve', $receipt), false)
            ->assertSee(route('payment-receipts.reject', $receipt), false);

        $this->actingAs($this->founder)->get(route('finance.collections', ['tab' => 'due']))
            ->assertSee('data-due-invoice=', false)
            ->assertSee(route('clients.show', ['client' => $this->client->id, 'open' => 'record-payment']), false);
        $this->actingAs($this->founder)->get(route('finance.collections', ['tab' => 'partial']))->assertSee('data-due-invoice=', false);
        $this->actingAs($this->founder)->get(route('finance.collections', ['tab' => 'payments']))->assertSee('data-recent-payment', false);

        $paymentsBefore = Payment::count();
        $this->actingAs($this->founder)->post(route('payment-receipts.approve', $receipt), ['_idempotency_key' => 'p6-approve'])->assertRedirect();
        $this->assertSame($paymentsBefore + 1, Payment::count());
        $this->assertSame(PaymentReceiptConfirmation::STATUS_APPROVED, $receipt->fresh()->status);
        $this->assertNotNull($receipt->fresh()->payment_id);
    }

    public function test_staff_collections_due_is_operational_only(): void
    {
        $this->seedScenario();

        $html = $this->actingAs($this->staff)->get(route('collections-due.index'))
            ->assertOk()
            ->assertSee('data-due-client="'.$this->client->id.'"', false)
            ->assertSee('data-my-pending-receipts', false)
            ->assertDontSee('data-finance-sections', false)
            ->assertDontSee('data-primary-kpis', false)
            ->getContent();

        // Per-client amount due only; no company cash, P&L or expense figures.
        $this->assertStringContainsString('50.000', $html);
        $this->assertStringNotContainsString('43.000', $html, 'Company cash total must not appear.');
        $this->assertStringNotContainsString('33.000', $html);
        $this->assertStringNotContainsString('7.000', $html);
        $this->assertStringContainsString(route('clients.show', ['client' => $this->client->id, 'open' => 'record-payment']), $html);

        $this->actingAs($this->staff)->get(route('dashboard'))->assertSee('data-nav-destination="collections-due"', false);
        $this->actingAs($this->founder)->get(route('dashboard'))->assertDontSee('data-nav-destination="collections-due"', false);
        $this->actingAs(User::factory()->create(['role' => User::ROLE_EMPLOYEE, 'is_active' => true]))->get(route('collections-due.index'))->assertForbidden();
    }

    // ── Company accounts ───────────────────────────────────────────────

    public function test_company_accounts_show_derived_balances_and_movements(): void
    {
        $this->seedScenario();

        $this->actingAs($this->founder)->get(route('finance.accounts'))
            ->assertOk()
            ->assertSee('data-company-account="CASH-BOX"', false)
            ->assertSee('data-company-account="CLIQ"', false)
            ->assertSee('33.000')
            ->assertSee('10.000')
            ->assertDontSee(route('financial-accounts.store'), false)
            ->assertDontSee(route('financial-accounts.archive', $this->cashBox), false);

        $this->actingAs($this->founder)->get(route('finance.accounts', ['account' => 'CLIQ', 'range' => 'previous_month']))
            ->assertOk()
            ->assertSee('data-account-movements', false)
            ->assertSee('+10.000', false);
    }

    public function test_internal_transfer_moves_balances_without_revenue_or_expense(): void
    {
        $this->seedScenario();
        $revenueBefore = $this->accountTypeTotal('revenue');
        $expenseBefore = $this->accountTypeTotal('expense');
        $expensesBefore = Expense::count();

        $this->actingAs($this->founder)->post(route('financial-transfers.store'), [
            'direction' => 'cash_to_cliq',
            'amount' => '12.500',
            'transferred_at' => '2026-09-23',
            'notes' => 'Deposit',
            '_idempotency_key' => 'p6-transfer',
        ])->assertSessionHasNoErrors();

        $balances = app(FinancialAccountBalanceService::class);
        $this->assertSame(20500, $balances->currentBalanceMinor($this->cashBox));
        $this->assertSame(22500, $balances->currentBalanceMinor($this->cliq));
        $this->assertSame(43000, app(FinanceOverviewService::class)->availableCash()['total_minor'], 'Total cash unchanged.');
        $this->assertSame($revenueBefore, $this->accountTypeTotal('revenue'));
        $this->assertSame($expenseBefore, $this->accountTypeTotal('expense'));
        $this->assertSame($expensesBefore, Expense::count());
        $this->assertSame(7000, app(FinanceOverviewService::class)->build()['kpis']['expenses']['current_minor']);

        $this->actingAs($this->staff)->post(route('financial-transfers.store'), [
            'direction' => 'cliq_to_cash', 'amount' => '1.000', 'transferred_at' => '2026-09-23', '_idempotency_key' => 'p6-staff-transfer',
        ])->assertForbidden();
    }

    public function test_cash_box_and_cliq_cannot_be_archived(): void
    {
        foreach ([$this->cashBox, $this->cliq] as $account) {
            $this->actingAs($this->founder)
                ->post(route('financial-accounts.archive', $account))
                ->assertSessionHasErrors('financial_account_id');
            $this->assertTrue($account->fresh()->is_active);
            $this->assertNull($account->fresh()->archived_at);
        }

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(\App\Services\FinancialAccountService::class)->archiveAccount($this->cashBox, $this->founder->id);
    }

    // ── Capital ────────────────────────────────────────────────────────

    public function test_capital_funding_resolves_cash_or_cliq_and_posts(): void
    {
        $this->post(route('capital-funding-transactions.store'))->assertRedirect();
        $this->actingAs($this->founder)->post(route('capital-funding-transactions.store'), [
            'source_name' => 'Founder', 'funding_type' => 'founder_contribution', 'payment_method' => 'cliq',
            'amount' => '500.000', 'received_at' => '2026-09-20 10:00', '_idempotency_key' => 'p6-capital-off',
        ])->assertNotFound();

        Setting::set('feature_capital_financing', '1');
        $this->actingAs($this->founder)->post(route('capital-funding-transactions.store'), [
            'source_name' => 'Founder', 'funding_type' => 'founder_contribution', 'payment_method' => 'cliq',
            'amount' => '500.000', 'received_at' => '2026-09-20 10:00', '_idempotency_key' => 'p6-capital-on',
        ])->assertSessionHasNoErrors();

        $funding = CapitalFundingTransaction::sole();
        $this->assertSame($this->cliq->id, $funding->financial_account_id);
        $this->assertSame(500000, app(FinancialAccountBalanceService::class)->currentBalanceMinor($this->cliq));
        $this->assertSame(0, $this->accountTypeTotal('revenue'), 'Capital funding is not revenue.');

        $this->actingAs($this->founder)->post(route('capital-funding-transactions.store'), [
            'source_name' => 'Founder', 'funding_type' => 'founder_contribution', 'payment_method' => 'bank_transfer',
            'amount' => '1.000', 'received_at' => '2026-09-20 10:00', '_idempotency_key' => 'p6-capital-bad',
        ])->assertSessionHasErrors('payment_method');
    }

    // ── Accounting ─────────────────────────────────────────────────────

    public function test_accounting_tabs_compute_only_their_data_and_tools_are_collapsed(): void
    {
        $this->seedScenario();

        $reconciliation = Mockery::mock(\App\Services\AccountingReconciliationService::class);
        $reconciliation->shouldNotReceive('run');
        $this->app->instance(\App\Services\AccountingReconciliationService::class, $reconciliation);

        $html = $this->actingAs($this->founder)->withSession(['locale' => 'ar'])->get(route('finance.accounting'))
            ->assertOk()
            ->assertSee('data-chart-of-accounts', false)
            ->assertSee('الحسابات والقيود المحاسبية')
            ->assertDontSee('دفتر الأستاذ')
            ->getContent();
        $this->assertMatchesRegularExpression('/<details[^>]*data-advanced-tools(?![^>]*\sopen)[^>]*>/', $html);

        $this->actingAs($this->founder)->get(route('finance.accounting', ['tab' => 'journal']))->assertOk()->assertSee('data-journal-list', false);
        $entry = \App\Models\JournalEntry::first();
        $this->actingAs($this->founder)->get(route('finance.accounting', ['tab' => 'journal', 'entry' => $entry->id]))->assertSee('data-journal-entry', false);

        $this->app->forgetInstance(\App\Services\AccountingReconciliationService::class);
        $this->actingAs($this->founder)->get(route('finance.accounting', ['tab' => 'reconciliation']))->assertOk()->assertSee('data-reconciliation-status', false);
        $this->actingAs($this->founder)->get(route('finance.accounting', ['tools' => 1]))->assertSee('data-tool="renewals_generate"', false);
    }

    // ── Reports ────────────────────────────────────────────────────────

    public function test_only_the_selected_report_is_computed(): void
    {
        $this->seedScenario();
        $statements = Mockery::mock(FinancialStatementService::class, [
            app(AccountingSetupService::class),
            app(\App\Services\ReceivableService::class),
            app(FinancialAccountBalanceService::class),
            app(\App\Services\RevenueRecognitionService::class),
        ])->makePartial();
        $statements->shouldReceive('cashFlow')->once()->passthru();
        foreach (['profitAndLoss', 'balanceSheet', 'arAging', 'recognizedRevenueReport', 'deferredRevenueReport', 'expenseReport', 'dashboard'] as $method) {
            $statements->shouldNotReceive($method);
        }
        $this->app->instance(FinancialStatementService::class, $statements);
        $saas = Mockery::mock(SaasMetricsService::class);
        $saas->shouldNotReceive('dashboard');
        $this->app->instance(SaasMetricsService::class, $saas);

        $this->actingAs($this->founder)->get(route('finance.reports', ['report' => 'cash-flow']))->assertOk();
    }

    public function test_reports_export_csv_with_bom_only_from_reports(): void
    {
        $this->seedScenario();

        foreach (\App\Http\Controllers\FinanceReportController::REPORTS as $report) {
            $response = $this->actingAs($this->founder)->get(route('finance.reports.export', ['report' => $report]));
            $response->assertOk();
            $this->assertStringStartsWith("\xEF\xBB\xBF", $response->streamedContent(), $report.' CSV must start with a UTF-8 BOM.');
            $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        }

        $saasCsv = $this->actingAs($this->founder)->get(route('finance.reports.export', ['report' => 'subscription-metrics']))->streamedContent();
        $this->assertStringContainsString('not accounting revenue', $saasCsv);

        $exportRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => in_array('GET', $route->methods(), true) && str_contains($route->uri(), 'export'))
            ->reject(fn ($route) => in_array($route->getName(), ['finance.reports.export', 'finance.export', 'saas-metrics.export'], true))
            ->map(fn ($route) => $route->getName());
        $this->assertSame([], $exportRoutes->values()->all(), 'Financial export lives only in Financial Reports (legacy URLs redirect there).');

        $this->actingAs($this->founder)->get(route('finance.reports', ['report' => 'subscription-metrics']))
            ->assertSee('data-not-revenue-note', false);
        $this->actingAs($this->founder)->get(route('finance.reports', ['report' => 'unknown']))->assertNotFound();
    }

    // ── Subscription metrics for V1 agreed-value subscriptions ─────────

    public function test_monthly_v1_subscription_contributes_to_mrr_arr_and_overview_strip(): void
    {
        $this->startV1Subscription($this->makeClient('Monthly Cafe'), 'monthly', 20000);

        $dashboard = app(SaasMetricsService::class)->dashboard(\App\Support\ReportingPeriod::fromRequest([]));
        $this->assertSame(240000, $dashboard['ending_arr_minor'], 'Monthly agreed value × 12.');
        $this->assertSame(20000, $dashboard['ending_mrr_minor']);
        $this->assertSame(1, $dashboard['active_subscriptions']);

        $strip = app(FinanceOverviewService::class)->build()['saas'];
        $this->assertSame(['arr_minor' => 240000, 'mrr_minor' => 20000, 'active_subscriptions' => 1], $strip);
        $this->actingAs($this->founder)->get(route('finance.index'))->assertSee('20.000')->assertSee('240.000');
    }

    public function test_annual_v1_subscription_uses_the_existing_normalization_rule(): void
    {
        // Annual installments do not change the recurring value; ARR = the annual agreed value.
        $this->startV1Subscription($this->makeClient('Annual Cafe'), 'annual', 1200000);
        $this->startV1Subscription($this->makeClient('Annual Installments Cafe'), 'annual', 600000, ['payment_terms' => 'installments', 'installments_count' => 3, 'installment_due_day' => 5]);

        $dashboard = app(SaasMetricsService::class)->dashboard(\App\Support\ReportingPeriod::fromRequest([]));
        $this->assertSame(1800000, $dashboard['ending_arr_minor']);
        $this->assertSame(app(\App\Services\SaasMetricEventService::class)->mrrMinorFromArr(1800000), $dashboard['ending_mrr_minor']);
        $this->assertSame(150000, $dashboard['ending_mrr_minor']);
        $this->assertSame(2, $dashboard['active_subscriptions']);
    }

    public function test_ended_v1_subscription_is_excluded_after_its_effective_end(): void
    {
        $billing = app(SubscriptionBillingService::class);
        $metrics = app(SaasMetricsService::class);
        [$subscription] = $this->startV1Subscription($this->makeClient('Leaving Cafe'), 'monthly', 20000);
        $this->startV1Subscription($this->makeClient('Staying Cafe'), 'monthly', 30000);

        $billing->scheduleCancellation($subscription, 'Closing shop', $this->founder->id);
        $this->assertSame(600000, $metrics->endingArrAt('2026-09-23'), 'Scheduled cancellation is not churn yet.');

        $billing->generateRenewals('2026-10-02', false, $this->founder->id);
        $subscription->refresh();
        $this->assertSame('cancelled', $subscription->status);
        $this->assertNotNull($subscription->ended_at);

        $this->assertSame(600000, $metrics->endingArrAt('2026-09-30'), 'Still counted until the effective end.');
        $this->assertSame(360000, $metrics->endingArrAt('2026-10-02'), 'Only the staying subscription remains.');
        // Churn is effective at the end of the paid period (2026-09-30 23:59:59), i.e. in September.
        $september = \App\Support\ReportingPeriod::fromRequest([]);
        $dashboard = $metrics->dashboard($september);
        $this->assertSame(240000, $dashboard['movements']['churn_arr_minor']);
        $this->assertSame(360000, $dashboard['ending_arr_minor']);
        $this->assertTrue(app(\App\Services\SaasMetricsReconciliationService::class)->run($september)['ok']);

        $october = new \App\Support\ReportingPeriod(Carbon::parse('2026-10-01'), Carbon::parse('2026-10-31')->endOfDay(), 'custom_date_range');
        $this->assertSame(1, $metrics->dashboard($october)['active_subscriptions']);
    }

    public function test_renewed_v1_subscription_is_not_double_counted_and_metrics_reconcile(): void
    {
        $this->startV1Subscription($this->makeClient('Renewing Cafe'), 'monthly', 20000);
        app(SubscriptionBillingService::class)->generateRenewals('2026-10-01', false, $this->founder->id);

        $this->assertSame(2, \App\Models\SubscriptionBillingPeriod::count(), 'Renewal opened the next period.');
        $metrics = app(SaasMetricsService::class);
        $this->assertSame(240000, $metrics->endingArrAt('2026-09-30'));
        $this->assertSame(240000, $metrics->endingArrAt('2026-10-15'), 'One active period per subscription, never both.');

        $before = [\App\Models\JournalEntry::count(), Invoice::count(), \App\Models\RevenueRecognitionPeriod::where('status', 'recognized')->count()];
        $september = \App\Support\ReportingPeriod::fromRequest([]);
        $this->assertTrue(app(\App\Services\SaasMetricsReconciliationService::class)->run($september)['ok'], 'Starting ARR + movements = ending ARR.');
        app(FinanceOverviewService::class)->build();
        $this->assertSame($before, [\App\Models\JournalEntry::count(), Invoice::count(), \App\Models\RevenueRecognitionPeriod::where('status', 'recognized')->count()], 'Metrics never post, invoice or recognize.');
    }

    // ── Helpers ────────────────────────────────────────────────────────

    private function startV1Subscription(Client $client, string $interval, int $agreedMinor, array $extra = []): array
    {
        $product = Product::sellable()->whereDoesntHave('subscriptions', fn ($query) => $query->where('client_id', $client->id))->orderBy('id')->firstOrFail();

        return app(SubscriptionBillingService::class)->startAgreedSubscription($client, collect([$product]), array_merge([
            'billing_interval' => $interval,
            'agreed_value_minor' => $agreedMinor,
            'start_date' => '2026-09-01',
            'payment_terms' => 'full',
        ], $extra), $this->founder->id);
    }

    /**
     * 100 JOD invoice due 2026-09-01. Owner payments: 10 JOD CliQ last month, 40 JOD cash this month
     * (both auto-allocated). One company-paid 7 JOD cash expense this month. One pending 5 JOD staff receipt.
     */
    private function seedScenario(): void
    {
        $invoice = Invoice::create([
            'invoice_number' => 'P6-000001',
            'client_id' => $this->client->id,
            'currency' => 'JOD',
            'status' => Invoice::STATUS_ISSUED,
            'issue_date' => '2026-08-01',
            'due_date' => '2026-09-01',
            'subtotal_minor' => 100000,
            'discount_minor' => 0,
            'tax_minor' => 0,
            'total_minor' => 100000,
            'issued_at' => '2026-08-01 09:00:00',
            'created_by' => $this->founder->id,
        ]);
        $invoice->lines()->create([
            'line_type' => InvoiceLine::TYPE_CUSTOM,
            'description_snapshot' => 'P6 line',
            'quantity' => 1,
            'unit_price_minor' => 100000,
            'subtotal_minor' => 100000,
            'discount_minor' => 0,
            'tax_rate_bps' => 0,
            'tax_minor' => 0,
            'total_minor' => 100000,
            'sort_order' => 10,
        ]);

        $this->actingAs($this->founder)->post(route('clients.payments.normal.store', $this->client), [
            'amount' => '10.000', 'payment_method' => 'cliq', 'received_at' => '2026-08-15 10:00:00', '_idempotency_key' => 'p6-pay-aug',
        ])->assertSessionHasNoErrors();
        $this->actingAs($this->founder)->post(route('clients.payments.normal.store', $this->client), [
            'amount' => '40.000', 'payment_method' => 'cash', 'received_at' => '2026-09-10 10:00:00', '_idempotency_key' => 'p6-pay-sep',
        ])->assertSessionHasNoErrors();

        app(OperatingExpenseService::class)->createV2Expense([
            'amount' => '7.000',
            'category_id' => ExpenseCategory::firstOrFail()->id,
            'funding_source' => Expense::FUNDING_COMPANY_ACCOUNT,
            'financial_account_id' => $this->cashBox->id,
            'paid_at' => '2026-09-12 10:00:00',
        ], $this->founder);

        $this->actingAs($this->staff)->post(route('clients.payment-receipts.store', $this->client), [
            'amount' => '5.000', 'payment_method' => 'cash', '_idempotency_key' => 'p6-receipt',
        ])->assertSessionHasNoErrors();

        $this->assertSame(3, CashMovement::count());
    }

    private function accountTypeTotal(string $type): int
    {
        return (int) JournalLine::query()
            ->join('chart_accounts', 'chart_accounts.id', '=', 'journal_lines.chart_account_id')
            ->where('chart_accounts.account_type', $type)
            ->selectRaw('COALESCE(SUM(journal_lines.debit_minor + journal_lines.credit_minor), 0) as total')
            ->value('total');
    }

    private function makeClient(string $name): Client
    {
        return Client::create([
            'business_name' => $name,
            'phone' => '079'.random_int(1000000, 9999999),
            'city_area' => 'Amman',
            'business_category' => 'Bakery',
            'lead_source' => 'Google Maps',
            'status' => 'subscriber',
            'stage' => ClientLifecycle::SUBSCRIBER,
        ]);
    }
}
