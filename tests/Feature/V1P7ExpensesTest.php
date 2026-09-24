<?php

namespace Tests\Feature;

use App\Models\CashMovement;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FinancialAccount;
use App\Models\JournalEntry;
use App\Models\RecurringExpenseObligation;
use App\Models\RecurringExpenseTemplate;
use App\Models\User;
use App\Services\AccountingSetupService;
use App\Services\FinanceOverviewService;
use App\Services\FinancialAccountBalanceService;
use App\Services\RecurringExpenseService;
use Carbon\Carbon;
use Database\Seeders\ExpenseCategorySeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * P7 — Expenses simplification (§12.3, FROZEN D-12, D-17).
 * Company-paid only; Cash → CASH-BOX, CliQ → CLIQ through the fixed resolver; posting stays in the engine.
 */
class V1P7ExpensesTest extends TestCase
{
    use RefreshDatabase;

    private User $founder;
    private User $admin;
    private User $staff;
    private FinancialAccount $cashBox;
    private FinancialAccount $cliq;
    private ExpenseCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-24 10:00:00');
        $this->seed(SettingsSeeder::class);
        $this->seed(ExpenseCategorySeeder::class);
        app(AccountingSetupService::class)->ensureSeeded();

        $this->founder = User::factory()->create(['role' => User::ROLE_FOUNDER, 'is_active' => true]);
        $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'is_active' => true]);
        $this->staff = User::factory()->create(['role' => User::ROLE_STAFF, 'is_active' => true]);
        $this->cashBox = FinancialAccount::where('code', 'CASH-BOX')->firstOrFail();
        $this->cliq = FinancialAccount::where('code', 'CLIQ')->firstOrFail();
        $this->category = ExpenseCategory::where('key', 'hosting')->firstOrFail();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ── One-time expense ───────────────────────────────────────────────

    public function test_cash_expense_resolves_cash_box_and_posts_through_the_engine(): void
    {
        $metricEvents = DB::table('subscription_metric_events')->count();

        $this->actingAs($this->founder)->post(route('operating-expenses.store'), $this->oneTime(['amount' => '12.345', 'payment_method' => 'cash']))
            ->assertRedirect(route('finance.expenses'))
            ->assertSessionHas('success');

        $expense = Expense::v2()->sole();
        $this->assertSame(12345, $expense->amount_minor);
        $this->assertSame(Expense::FUNDING_COMPANY_ACCOUNT, $expense->funding_source);
        $this->assertSame($this->cashBox->id, $expense->financial_account_id);
        $this->assertNull($expense->paid_by_user_id);
        $this->assertNull($expense->vendor_id);
        $this->assertSame('2026-09-24', $expense->incurred_on->toDateString());
        $this->assertSame('Server bill', $expense->description);

        $movement = CashMovement::where('source_type', Expense::class)->where('source_id', $expense->id)->sole();
        $this->assertSame(CashMovement::EVENT_EXPENSE_PAID, $movement->event_type);
        $this->assertSame(CashMovement::DIRECTION_OUTFLOW, $movement->direction);
        $this->assertSame(12345, (int) $movement->amount_minor);
        $this->assertSame($this->cashBox->id, $movement->financial_account_id);

        $journal = JournalEntry::where('event_type', 'company_funded_operating_expense')->where('source_id', $expense->id)->sole();
        $this->assertSame(12345, (int) $journal->lines()->sum('debit_minor'));
        $this->assertSame(12345, (int) $journal->lines()->sum('credit_minor'));

        $balances = app(FinancialAccountBalanceService::class);
        $this->assertSame(-12345, $balances->currentBalanceMinor($this->cashBox->fresh()));
        $this->assertSame(0, $balances->currentBalanceMinor($this->cliq->fresh()));
        $this->assertSame($metricEvents, DB::table('subscription_metric_events')->count(), 'Expenses never touch MRR/ARR.');
    }

    public function test_cliq_expense_resolves_cliq(): void
    {
        $this->actingAs($this->admin)->post(route('operating-expenses.store'), $this->oneTime(['amount' => '7', 'payment_method' => 'cliq']))
            ->assertSessionHas('success');

        $expense = Expense::v2()->sole();
        $this->assertSame(7000, $expense->amount_minor);
        $this->assertSame($this->cliq->id, $expense->financial_account_id);
        $this->assertSame(-7000, app(FinancialAccountBalanceService::class)->currentBalanceMinor($this->cliq->fresh()));
        $this->assertSame(0, app(FinancialAccountBalanceService::class)->currentBalanceMinor($this->cashBox->fresh()));
    }

    public function test_financial_account_id_and_other_methods_cannot_bypass_the_fixed_resolver(): void
    {
        $other = FinancialAccount::create([
            'code' => 'OTHER-BANK', 'name_ar' => 'بنك آخر', 'name_en' => 'Other bank',
            'type' => 'bank', 'currency' => 'JOD', 'is_active' => true,
        ]);

        $this->actingAs($this->founder)->post(route('operating-expenses.store'), $this->oneTime(['financial_account_id' => $other->id]))
            ->assertSessionHasErrors('financial_account_id');
        foreach (['bank_transfer', 'zain_cash', 'other', ''] as $method) {
            $this->actingAs($this->founder)->post(route('operating-expenses.store'), $this->oneTime(['payment_method' => $method]))
                ->assertSessionHasErrors('payment_method');
        }

        $this->assertSame(0, Expense::count());
        $this->assertSame(0, CashMovement::where('financial_account_id', $other->id)->count());
    }

    public function test_expense_date_cannot_be_in_the_future_and_amount_must_be_positive(): void
    {
        $this->actingAs($this->founder)->post(route('operating-expenses.store'), $this->oneTime(['expense_date' => '2026-09-25']))
            ->assertSessionHasErrors('expense_date');
        $this->actingAs($this->founder)->post(route('operating-expenses.store'), $this->oneTime(['amount' => '0']))
            ->assertSessionHasErrors('amount');
        $this->actingAs($this->founder)->post(route('operating-expenses.store'), $this->oneTime(['amount' => '1.2345']))
            ->assertSessionHasErrors('amount');

        $this->actingAs($this->founder)->post(route('operating-expenses.store'), $this->oneTime(['expense_date' => '2026-08-30']))
            ->assertSessionHas('success');
        $expense = Expense::v2()->sole();
        $this->assertSame('2026-08-30', $expense->incurred_on->toDateString());
        $this->assertSame('2026-08-30', $expense->paid_at->toDateString());
    }

    public function test_personal_paid_and_vendor_inputs_are_not_accepted_by_the_v1_route(): void
    {
        $vendor = \App\Models\Vendor::create(['name' => 'Legacy vendor', 'is_active' => true, 'created_by' => $this->founder->id]);

        foreach ([
            ['funding_source' => Expense::FUNDING_PERSONAL, 'paid_by_user_id' => $this->admin->id],
            ['funding_source' => Expense::FUNDING_COMPANY_ACCOUNT],
            ['paid_by_user_id' => $this->admin->id],
            ['vendor_id' => $vendor->id],
        ] as $extra) {
            $response = $this->actingAs($this->founder)->post(route('operating-expenses.store'), $this->oneTime($extra));
            $response->assertSessionHasErrors(array_keys($extra));
        }

        $this->assertSame(0, Expense::count());
        $this->assertSame(0, Expense::where('funding_source', Expense::FUNDING_PERSONAL)->count());
    }

    // ── Monthly recurring expense ──────────────────────────────────────

    public function test_monthly_recurring_expense_uses_the_existing_engine_and_due_day(): void
    {
        $this->actingAs($this->founder)->post(route('recurring-expense-templates.store'), $this->monthly([
            'amount' => '150', 'payment_method' => 'cliq', 'start_date' => '2026-09-24', 'due_day' => 1, 'description' => 'Office rent',
        ]))->assertRedirect(route('finance.expenses', ['tab' => 'recurring']))->assertSessionHas('success');

        $template = RecurringExpenseTemplate::sole();
        $this->assertSame('Office rent', $template->name);
        $this->assertSame(150000, $template->amount_minor);
        $this->assertSame(RecurringExpenseTemplate::FREQUENCY_MONTHLY, $template->frequency);
        $this->assertSame(1, $template->interval_count);
        $this->assertSame(Expense::FUNDING_COMPANY_ACCOUNT, $template->default_funding_source);
        $this->assertSame($this->cliq->id, $template->default_financial_account_id);
        $this->assertNull($template->default_paid_by_user_id);
        $this->assertSame('2026-10-01', $template->next_due_date->toDateString(), 'Day 1 after the 24th starts next month.');
        $this->assertSame(0, Expense::count());
        $this->assertSame(0, CashMovement::count(), 'Creating a recurring expense moves no money.');

        // The existing scheduler engine generates the month and advances to the same day next month.
        $engine = app(RecurringExpenseService::class);
        $this->assertSame(0, $engine->generateDueObligations(Carbon::parse('2026-09-30')));
        $this->assertSame(1, $engine->generateDueObligations(Carbon::parse('2026-10-01')));
        $this->assertSame(0, $engine->generateDueObligations(Carbon::parse('2026-10-01')), 'Idempotent.');
        $obligation = RecurringExpenseObligation::sole();
        $this->assertSame('2026-10-01', $obligation->due_date->toDateString());
        $this->assertSame(150000, $obligation->expected_amount_minor);
        $this->assertSame($this->cliq->id, $obligation->default_financial_account_id);
        $this->assertSame('2026-11-01', $template->fresh()->next_due_date->toDateString());
    }

    public function test_due_day_28_accepted_and_0_or_29_rejected(): void
    {
        $this->actingAs($this->founder)->post(route('recurring-expense-templates.store'), $this->monthly(['due_day' => 28, 'start_date' => '2026-09-24', 'payment_method' => 'cash']))
            ->assertSessionHas('success');
        $template = RecurringExpenseTemplate::sole();
        $this->assertSame('2026-09-28', $template->next_due_date->toDateString());
        $this->assertSame($this->cashBox->id, $template->default_financial_account_id);

        app(RecurringExpenseService::class)->generateDueObligations(Carbon::parse('2027-03-01'));
        $this->assertSame(
            ['2026-09-28', '2026-10-28', '2026-11-28', '2026-12-28', '2027-01-28', '2027-02-28'],
            RecurringExpenseObligation::orderBy('due_date')->get()->map(fn ($o) => $o->due_date->toDateString())->all(),
            'Day 28 exists in every month, including February.'
        );

        foreach ([0, 29, 31, -1, 'abc', ''] as $day) {
            $this->actingAs($this->founder)->post(route('recurring-expense-templates.store'), $this->monthly(['due_day' => $day]))
                ->assertSessionHasErrors('due_day');
        }
        $this->assertSame(1, RecurringExpenseTemplate::count());
    }

    public function test_recurring_route_rejects_non_monthly_personal_and_account_inputs(): void
    {
        foreach ([
            ['frequency' => 'weekly'],
            ['interval_count' => 3],
            ['default_funding_source' => Expense::FUNDING_PERSONAL],
            ['default_paid_by_user_id' => $this->admin->id],
            ['default_financial_account_id' => $this->cliq->id],
            ['vendor_id' => 1],
        ] as $extra) {
            $this->actingAs($this->founder)->post(route('recurring-expense-templates.store'), $this->monthly($extra))
                ->assertSessionHasErrors(array_keys($extra));
        }
        $this->actingAs($this->founder)->post(route('recurring-expense-templates.store'), $this->monthly(['payment_method' => 'e_wallet']))
            ->assertSessionHasErrors('payment_method');

        $this->assertSame(0, RecurringExpenseTemplate::count());
    }

    public function test_mark_as_paid_resolves_method_and_posts_once(): void
    {
        $obligation = $this->dueObligation($this->cashBox, 25000);

        $this->actingAs($this->founder)->post(route('recurring-expense-obligations.pay', $obligation), [
            'amount' => '26.500', 'payment_method' => 'cliq', 'paid_on' => '2026-09-24',
        ])->assertSessionHas('success');

        $expense = Expense::v2()->sole();
        $this->assertSame(26500, $expense->amount_minor);
        $this->assertSame($this->cliq->id, $expense->financial_account_id, 'The confirmed method wins over the template default.');
        $this->assertSame($obligation->id, $expense->recurring_expense_obligation_id);
        $this->assertSame(RecurringExpenseObligation::STATUS_PAID, $obligation->fresh()->status);
        $this->assertSame(1, CashMovement::where('event_type', CashMovement::EVENT_EXPENSE_PAID)->count());
        $this->assertSame(-26500, app(FinancialAccountBalanceService::class)->currentBalanceMinor($this->cliq->fresh()));

        // A fresh submission (new idempotency key) reaches the engine guard: already paid.
        $this->actingAs($this->founder)->post(route('recurring-expense-obligations.pay', $obligation), [
            '_idempotency_key' => 'second-attempt', 'amount' => '26.500', 'payment_method' => 'cliq', 'paid_on' => '2026-09-24',
        ])->assertSessionHasErrors('recurring_expense_obligation_id');
        $this->assertSame(1, Expense::count());
    }

    public function test_mark_as_paid_rejects_account_bypass_and_personal_funding(): void
    {
        $obligation = $this->dueObligation($this->cashBox, 5000);
        $base = ['amount' => '5', 'payment_method' => 'cash', 'paid_on' => '2026-09-24'];

        foreach ([
            ['financial_account_id' => $this->cliq->id],
            ['funding_source' => Expense::FUNDING_PERSONAL, 'paid_by_user_id' => $this->admin->id],
        ] as $extra) {
            $this->actingAs($this->founder)->post(route('recurring-expense-obligations.pay', $obligation), $base + $extra)
                ->assertSessionHasErrors(array_keys($extra));
        }
        $this->actingAs($this->founder)->post(route('recurring-expense-obligations.pay', $obligation), ['payment_method' => 'bank_transfer'] + $base)
            ->assertSessionHasErrors('payment_method');
        $this->actingAs($this->founder)->post(route('recurring-expense-obligations.pay', $obligation), ['paid_on' => '2026-09-30'] + $base)
            ->assertSessionHasErrors('paid_on');

        $this->assertSame(0, Expense::count());
        $this->assertSame(RecurringExpenseObligation::STATUS_PENDING, $obligation->fresh()->status);
    }

    public function test_skip_and_stop_repeating_are_preserved_without_financial_effect(): void
    {
        $obligation = $this->dueObligation($this->cashBox, 5000);
        $template = $obligation->template;

        $this->actingAs($this->founder)->post(route('recurring-expense-obligations.skip', $obligation))->assertSessionHas('success');
        $this->assertSame(RecurringExpenseObligation::STATUS_SKIPPED, $obligation->fresh()->status);

        $this->actingAs($this->founder)->patch(route('recurring-expense-templates.update', $template), ['amount' => '60'])->assertSessionHas('success');
        $this->assertSame(60000, $template->fresh()->amount_minor);
        $this->assertTrue($template->fresh()->is_active, 'Changing the amount does not stop the template.');
        $this->assertSame(5000, $obligation->fresh()->expected_amount_minor, 'Existing months keep their amount.');

        $this->actingAs($this->founder)->patch(route('recurring-expense-templates.update', $template), ['is_active' => '0'])->assertSessionHas('success');
        $this->assertFalse($template->fresh()->is_active);
        $this->assertSame(0, app(RecurringExpenseService::class)->generateDueObligations(Carbon::parse('2027-01-01')), 'Stopped templates generate nothing.');

        // No resume/versioning workflow is invented; account and frequency cannot be changed here.
        $this->actingAs($this->founder)->patch(route('recurring-expense-templates.update', $template), ['is_active' => '1'])->assertSessionHasErrors('is_active');
        $this->actingAs($this->founder)->patch(route('recurring-expense-templates.update', $template), ['default_financial_account_id' => $this->cliq->id])->assertSessionHasErrors('default_financial_account_id');
        $this->assertFalse($template->fresh()->is_active);

        $this->assertSame(0, Expense::count());
        $this->assertSame(0, CashMovement::count());
    }

    // ── Page surface ───────────────────────────────────────────────────

    public function test_page_shows_simple_forms_and_hides_non_v1_surface(): void
    {
        $this->actingAs($this->founder)->post(route('operating-expenses.store'), $this->oneTime(['description' => 'Printer ink']))->assertSessionHas('success');
        $this->dueObligation($this->cliq, 9000);

        $html = $this->actingAs($this->founder)->withSession(['locale' => 'en'])->get(route('finance.expenses'))->assertOk()->getContent();
        $this->assertStringContainsString('data-finance-page="expenses"', $html);
        $this->assertStringContainsString('data-expense-form="one-time"', $html);
        $this->assertStringContainsString('name="payment_method" value="cash"', $html);
        $this->assertStringContainsString('name="payment_method" value="cliq"', $html);
        $this->assertStringContainsString('Printer ink', $html);
        $this->assertStringContainsString('data-expense-type="one-time"', $html);
        $this->assertStringContainsString('One-time', $html);

        $recurring = $this->actingAs($this->founder)->withSession(['locale' => 'en'])->get(route('finance.expenses', ['tab' => 'recurring']))->assertOk()->getContent();
        $this->assertStringContainsString('data-expense-form="monthly"', $recurring);
        $this->assertStringContainsString('name="due_day"', $recurring);
        $this->assertStringContainsString('Repeat this expense every month', $recurring);
        $this->assertStringContainsString('data-due-obligations', $recurring);
        $this->assertStringContainsString('Mark as paid', $recurring);
        $this->assertStringContainsString('Skip this month', $recurring);
        $this->assertStringContainsString('data-recurring-template', $recurring);
        $this->assertStringContainsString('Stop repeating', $recurring);

        $categories = $this->actingAs($this->founder)->withSession(['locale' => 'en'])->get(route('finance.expenses', ['tab' => 'categories']))->assertOk()->getContent();
        $this->assertStringContainsString('data-category-form', $categories);

        foreach ([$html, $recurring, $categories] as $page) {
            foreach (['funding_source', 'financial_account_id', 'paid_by_user_id', 'vendor_id', 'payee_name', 'frequency', 'interval_count',
                'business_date', route('vendors.store'), route('recurring-expense-obligations.generate'), 'fixed-asset', 'asset-categor', 'depreciation',
                'bank_transfer', 'zain_cash', 'orange_money', 'e_wallet'] as $hidden) {
                $this->assertStringNotContainsString($hidden, $page);
            }
            foreach (['Personal', 'Vendor', 'Fixed asset'] as $word) {
                $this->assertStringNotContainsStringIgnoringCase($word, strip_tags($page));
            }
        }

        $this->actingAs($this->founder)->withSession(['locale' => 'ar'])->get(route('finance.expenses'))
            ->assertOk()
            ->assertDontSee('دفع شخصي')
            ->assertDontSee('المورد');
    }

    public function test_recurring_and_one_time_expenses_are_distinguished_in_the_list(): void
    {
        $obligation = $this->dueObligation($this->cashBox, 5000);
        $this->actingAs($this->founder)->post(route('recurring-expense-obligations.pay', $obligation), ['amount' => '5', 'payment_method' => 'cash', 'paid_on' => '2026-09-24']);
        $this->actingAs($this->founder)->post(route('operating-expenses.store'), $this->oneTime());

        $html = $this->actingAs($this->founder)->withSession(['locale' => 'en'])->get(route('finance.expenses'))->getContent();
        $this->assertSame(1, substr_count($html, 'data-expense-type="monthly"'));
        $this->assertSame(1, substr_count($html, 'data-expense-type="one-time"'));
        $this->assertStringContainsString('Monthly recurring', $html);
    }

    public function test_category_can_be_added_without_technical_code_and_archived(): void
    {
        $this->actingAs($this->founder)->post(route('expense-categories.store'), ['name_ar' => 'ضيافة العملاء', 'name_en' => 'Client Hospitality'])
            ->assertSessionHas('success');
        $category = ExpenseCategory::where('name_ar', 'ضيافة العملاء')->sole();
        $this->assertSame('client_hospitality', $category->key);

        $this->actingAs($this->founder)->post(route('expense-categories.store'), ['name_ar' => 'ضيافة ثانية', 'name_en' => 'Client Hospitality'])
            ->assertSessionHas('success');
        $this->assertSame(2, ExpenseCategory::where('key', 'like', 'client_hospitality%')->count(), 'Keys stay unique.');

        $this->actingAs($this->founder)->post(route('expense-categories.archive', $category))->assertSessionHas('success');
        $this->actingAs($this->founder)->post(route('operating-expenses.store'), $this->oneTime(['category_id' => $category->id]))
            ->assertSessionHasErrors('category_id');
    }

    // ── Authorization ──────────────────────────────────────────────────

    public function test_owner_and_admin_allowed_staff_denied_server_side(): void
    {
        foreach ([$this->founder, $this->admin] as $owner) {
            foreach (['expenses', 'recurring', 'categories'] as $tab) {
                $this->actingAs($owner)->get(route('finance.expenses', ['tab' => $tab]))->assertOk();
            }
        }

        $obligation = $this->dueObligation($this->cashBox, 5000);
        $template = $obligation->template;

        $this->actingAs($this->staff)->get(route('finance.expenses'))->assertForbidden();
        $this->actingAs($this->staff)->get(route('finance.expenses', ['tab' => 'recurring']))->assertForbidden();
        $this->actingAs($this->staff)->post(route('operating-expenses.store'), $this->oneTime())->assertForbidden();
        $this->actingAs($this->staff)->post(route('recurring-expense-templates.store'), $this->monthly())->assertForbidden();
        $this->actingAs($this->staff)->patch(route('recurring-expense-templates.update', $template), ['amount' => '1'])->assertForbidden();
        $this->actingAs($this->staff)->patch(route('recurring-expense-templates.update', $template), ['is_active' => '0'])->assertForbidden();
        $this->actingAs($this->staff)->post(route('recurring-expense-obligations.pay', $obligation), ['amount' => '5', 'payment_method' => 'cash', 'paid_on' => '2026-09-24'])->assertForbidden();
        $this->actingAs($this->staff)->post(route('recurring-expense-obligations.skip', $obligation))->assertForbidden();
        $this->actingAs($this->staff)->post(route('expense-categories.store'), ['name_ar' => 'x'])->assertForbidden();

        $this->assertSame(0, Expense::count());
        $this->assertSame(1, RecurringExpenseTemplate::count());
        $this->assertTrue($template->fresh()->is_active);
        $this->assertSame(5000, $template->fresh()->amount_minor);
        $this->assertSame(RecurringExpenseObligation::STATUS_PENDING, $obligation->fresh()->status);
    }

    // ── Finance Overview contracts (P6) ────────────────────────────────

    public function test_overview_expense_kpi_and_recurring_queue_follow_v1_expenses(): void
    {
        $this->actingAs($this->founder)->post(route('operating-expenses.store'), $this->oneTime(['amount' => '10', 'payment_method' => 'cash']));
        $this->actingAs($this->founder)->post(route('operating-expenses.store'), $this->oneTime(['amount' => '4', 'payment_method' => 'cliq']));
        $this->actingAs($this->founder)->post(route('operating-expenses.store'), $this->oneTime(['amount' => '99', 'expense_date' => '2026-08-15']));
        $reversed = Expense::v2()->where('amount_minor', 4000)->sole();
        $this->actingAs($this->founder)->post(route('operating-expenses.reverse', $reversed), ['reason' => 'Entered twice'])->assertSessionHas('success');

        $this->actingAs($this->founder)->post(route('recurring-expense-templates.store'), $this->monthly(['amount' => '30', 'start_date' => '2026-09-24', 'due_day' => 28]));
        app(RecurringExpenseService::class)->generateDueObligations(Carbon::parse('2026-09-28'));

        $overview = app(FinanceOverviewService::class)->build();
        $this->assertSame(10000, $overview['kpis']['expenses']['current_minor'], 'This month: 10 cash + 4 CliQ − 4 reversed.');
        $this->assertSame(99000, $overview['kpis']['expenses']['previous_minor']);
        $this->assertSame(1, $overview['attention']['recurring_expenses']['count']);
        $this->assertSame(30000, $overview['attention']['recurring_expenses']['total_minor']);

        $balances = app(FinancialAccountBalanceService::class);
        $this->assertSame(-109000, $balances->currentBalanceMinor($this->cashBox->fresh()));
        $this->assertSame(0, $balances->currentBalanceMinor($this->cliq->fresh()));
        $this->assertSame(
            $balances->currentBalanceMinor($this->cashBox->fresh()) + $balances->currentBalanceMinor($this->cliq->fresh()),
            $overview['kpis']['available_cash']['total_minor']
        );

        // The Expenses page shows the same month figure (no second, independent total).
        $this->actingAs($this->founder)->withSession(['locale' => 'en'])->get(route('finance.expenses'))->assertSee('10.000');
        $this->actingAs($this->founder)->get(route('finance.index'))
            ->assertSee(route('finance.expenses', ['tab' => 'recurring']), false);
    }

    // ── Helpers ────────────────────────────────────────────────────────

    private function oneTime(array $overrides = []): array
    {
        return array_merge([
            'amount' => '12.500',
            'category_id' => $this->category->id,
            'payment_method' => 'cash',
            'expense_date' => '2026-09-24',
            'description' => 'Server bill',
        ], $overrides);
    }

    private function monthly(array $overrides = []): array
    {
        return array_merge([
            'amount' => '20',
            'category_id' => $this->category->id,
            'payment_method' => 'cash',
            'start_date' => '2026-09-24',
            'due_day' => 5,
            'description' => 'Hosting plan',
        ], $overrides);
    }

    private function dueObligation(FinancialAccount $account, int $amountMinor): RecurringExpenseObligation
    {
        $template = RecurringExpenseTemplate::create([
            'name' => 'Internet',
            'category_id' => $this->category->id,
            'currency' => 'JOD',
            'amount_minor' => $amountMinor,
            'frequency' => RecurringExpenseTemplate::FREQUENCY_MONTHLY,
            'interval_count' => 1,
            'start_date' => '2026-09-20',
            'next_due_date' => '2026-09-20',
            'default_financial_account_id' => $account->id,
            'default_funding_source' => Expense::FUNDING_COMPANY_ACCOUNT,
            'is_active' => true,
            'created_by' => $this->founder->id,
        ]);
        app(RecurringExpenseService::class)->generateDueObligations(Carbon::parse('2026-09-24'));

        return RecurringExpenseObligation::where('recurring_expense_template_id', $template->id)->sole();
    }
}
