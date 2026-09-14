<?php

namespace Tests\Feature;

use App\Models\CashMovement;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\ExpenseReversal;
use App\Models\FinancialAccount;
use App\Models\Partner;
use App\Models\RecurringExpenseObligation;
use App\Models\RecurringExpenseTemplate;
use App\Models\User;
use App\Models\Vendor;
use App\Services\FinancialAccountBalanceService;
use App\Support\Money;
use Database\Seeders\ExpenseCategorySeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancePhaseD2ATest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingsSeeder::class);
        $this->seed(ExpenseCategorySeeder::class);
    }

    public function test_admin_can_create_v2_company_account_expense_with_exact_cash_outflow(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = ExpenseCategory::where('key', 'fuel')->firstOrFail();
        $account = $this->createAccount('expense_cash', 'Expense Cash', '10.000');
        $archived = $this->createAccount('archived_cash', 'Archived Cash', '5.000');
        $archived->update(['is_active' => false, 'archived_at' => now()]);

        $this->actingAs($admin)->post(route('operating-expenses.store'), [
            'amount' => '0.001',
            'category_id' => $category->id,
            'funding_source' => Expense::FUNDING_COMPANY_ACCOUNT,
            'financial_account_id' => $archived->id,
            'incurred_on' => '2026-09-14',
            'paid_at' => '2026-09-14 09:30:00',
        ])->assertSessionHasErrors('financial_account_id');

        $this->actingAs($admin)->post(route('operating-expenses.store'), [
            'amount' => '0.001',
            'category_id' => $category->id,
            'funding_source' => Expense::FUNDING_COMPANY_ACCOUNT,
            'incurred_on' => '2026-09-14',
            'paid_at' => '2026-09-14 09:30:00',
        ])->assertSessionHasErrors('financial_account_id');

        $this->actingAs($admin)->post(route('operating-expenses.store'), [
            'amount' => '0.001',
            'category_id' => $category->id,
            'payee_name' => 'Fuel Station',
            'funding_source' => Expense::FUNDING_COMPANY_ACCOUNT,
            'financial_account_id' => $account->id,
            'incurred_on' => '2026-09-14',
            'paid_at' => '2026-09-14 09:30:00',
            'reference' => 'FUEL-1',
        ])->assertSessionHas('success');

        $expense = Expense::v2()->firstOrFail();
        $this->assertSame(1, $expense->amount_minor);
        $this->assertSame('JOD', $expense->currency);
        $this->assertSame($category->displayName(), $expense->category_name_snapshot);
        $this->assertSame('Fuel Station', $expense->payee_name_snapshot);
        $this->assertDatabaseHas('cash_movements', [
            'financial_account_id' => $account->id,
            'event_type' => CashMovement::EVENT_EXPENSE_PAID,
            'direction' => CashMovement::DIRECTION_OUTFLOW,
            'amount_minor' => 1,
            'source_type' => Expense::class,
            'source_id' => $expense->id,
        ]);
        $this->assertSame(9999, app(FinancialAccountBalanceService::class)->currentBalanceMinor($account->fresh()));

        $this->actingAs($admin)->post(route('operating-expenses.store'), [
            'amount' => '1.000',
            'category_id' => $category->id,
            'funding_source' => Expense::FUNDING_COMPANY_ACCOUNT,
            'financial_account_id' => $account->id,
            'incurred_on' => '2026-09-14',
            'paid_at' => '2026-09-14 10:30:00',
        ])->assertSessionHas('success');
        $this->assertSame(2, CashMovement::where('event_type', CashMovement::EVENT_EXPENSE_PAID)->count());
    }

    public function test_personal_expense_requires_internal_payer_and_creates_no_cash_movement(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $payer = User::factory()->create(['role' => 'admin', 'name' => 'Khalid']);
        $category = ExpenseCategory::where('key', 'hosting')->firstOrFail();
        $vendor = Vendor::create(['name' => 'Cloud Vendor', 'is_active' => true, 'created_by' => $admin->id]);

        $this->actingAs($admin)->post(route('operating-expenses.store'), [
            'amount' => '12.375',
            'category_id' => $category->id,
            'vendor_id' => $vendor->id,
            'funding_source' => Expense::FUNDING_PERSONAL,
            'incurred_on' => '2026-09-14',
            'paid_at' => '2026-09-14 11:00:00',
        ])->assertSessionHasErrors('paid_by_user_id');

        $this->actingAs($admin)->post(route('operating-expenses.store'), [
            'amount' => '12.375',
            'category_id' => $category->id,
            'vendor_id' => $vendor->id,
            'funding_source' => Expense::FUNDING_PERSONAL,
            'paid_by_user_id' => $payer->id,
            'incurred_on' => '2026-09-14',
            'paid_at' => '2026-09-14 11:00:00',
        ])->assertSessionHas('success');

        $expense = Expense::v2()->firstOrFail();
        $this->assertSame(12375, $expense->amount_minor);
        $this->assertSame($vendor->name, $expense->payee_name_snapshot);
        $this->assertSame($payer->id, $expense->paid_by_user_id);
        $this->assertSame(0, CashMovement::count());
    }

    public function test_expense_reversal_is_append_only_and_cash_aware(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $payer = User::factory()->create(['role' => 'admin']);
        $category = ExpenseCategory::where('key', 'other')->firstOrFail();
        $account = $this->createAccount('reverse_cash', 'Reverse Cash', '20.000');
        $companyExpense = $this->createCompanyExpense($admin, $category, $account, '4.000');
        $personalExpense = $this->createPersonalExpense($admin, $payer, $category, '2.000');

        $this->actingAs($admin)->post(route('operating-expenses.reverse', $companyExpense), [])
            ->assertSessionHasErrors('reason');

        $this->actingAs($admin)->post(route('operating-expenses.reverse', $companyExpense), [
            'reason' => 'Wrong expense',
            'reversed_at' => '2026-09-14 12:00:00',
        ])->assertSessionHas('success');

        $this->assertSame(1, ExpenseReversal::count());
        $this->assertDatabaseHas('cash_movements', [
            'event_type' => CashMovement::EVENT_EXPENSE_REVERSAL,
            'direction' => CashMovement::DIRECTION_INFLOW,
            'amount_minor' => 4000,
            'source_type' => ExpenseReversal::class,
        ]);
        $this->assertSame(20000, app(FinancialAccountBalanceService::class)->currentBalanceMinor($account->fresh()));
        $this->assertSame(0, Expense::activeV2()->whereKey($companyExpense->id)->count());

        $this->actingAs($admin)->post(route('operating-expenses.reverse', $companyExpense), [
            'reason' => 'Duplicate',
        ])->assertSessionHasErrors('expense_id');

        $movementCount = CashMovement::count();
        $this->actingAs($admin)->post(route('operating-expenses.reverse', $personalExpense), [
            'reason' => 'Personal correction',
        ])->assertSessionHas('success');
        $this->assertSame($movementCount, CashMovement::count());
    }

    public function test_categories_and_vendors_are_configurable_and_archived_not_deleted(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = ExpenseCategory::where('key', 'office_supplies')->firstOrFail();
        $vendor = Vendor::create(['name' => 'Stationery Vendor', 'is_active' => true, 'created_by' => $admin->id]);
        $account = $this->createAccount('category_cash', 'Category Cash', '10.000');
        $expense = $this->createCompanyExpense($admin, $category, $account, '1.000', $vendor);

        $this->actingAs($admin)->post(route('expense-categories.store'), [
            'key' => 'custom_ops',
            'name_ar' => 'تشغيلي مخصص',
            'name_en' => 'Custom Ops',
        ])->assertSessionHas('success');
        $this->assertDatabaseHas('expense_categories', ['key' => 'custom_ops', 'name_ar' => 'تشغيلي مخصص']);

        $this->actingAs($admin)->post(route('vendors.store'), [
            'name' => 'New Vendor',
            'email' => 'vendor@example.com',
        ])->assertSessionHas('success');
        $this->assertDatabaseHas('vendors', ['name' => 'New Vendor']);

        $this->actingAs($admin)->post(route('expense-categories.archive', $category))->assertSessionHas('success');
        $this->actingAs($admin)->post(route('vendors.archive', $vendor))->assertSessionHas('success');

        $this->assertNotNull($category->fresh()->archived_at);
        $this->assertNotNull($vendor->fresh()->archived_at);
        $this->assertSame($category->displayName(), $expense->fresh()->category_name_snapshot);
        $this->assertSame($vendor->name, $expense->fresh()->payee_name_snapshot);
        $this->assertFalse(D2ARouteInspector::hasDeleteRouteContaining('expense-categories'));
        $this->assertFalse(D2ARouteInspector::hasDeleteRouteContaining('vendors'));
    }

    public function test_recurring_templates_generate_obligations_idempotently_and_do_not_move_cash_until_paid(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = ExpenseCategory::where('key', 'internet_communications')->firstOrFail();
        $account = $this->createAccount('recurring_cash', 'Recurring Cash', '100.000');

        $this->actingAs($admin)->post(route('recurring-expense-templates.store'), [
            'name' => 'Internet Monthly',
            'amount' => '25.000',
            'category_id' => $category->id,
            'payee_name' => 'ISP',
            'frequency' => 'monthly',
            'interval_count' => 1,
            'start_date' => '2026-09-01',
            'default_funding_source' => Expense::FUNDING_COMPANY_ACCOUNT,
            'default_financial_account_id' => $account->id,
        ])->assertSessionHas('success');

        $template = RecurringExpenseTemplate::firstOrFail();
        $this->assertSame(0, Expense::count());
        $this->assertSame(1, CashMovement::count());

        $this->artisan('finance:generate-recurring-expenses', ['--date' => '2026-09-14'])->assertSuccessful();
        $this->artisan('finance:generate-recurring-expenses', ['--date' => '2026-09-14'])->assertSuccessful();
        $this->assertSame(1, RecurringExpenseObligation::count());
        $this->assertSame(1, CashMovement::count());

        $obligation = RecurringExpenseObligation::firstOrFail();
        $this->assertSame(25000, $obligation->expected_amount_minor);
        $this->assertSame($category->displayName(), $obligation->category_name_snapshot);

        $template->update(['amount_minor' => 30000, 'category_id' => ExpenseCategory::where('key', 'hosting')->firstOrFail()->id]);
        $this->assertSame(25000, $obligation->fresh()->expected_amount_minor);
        $this->assertSame($category->displayName(), $obligation->fresh()->category_name_snapshot);

        $this->actingAs($admin)->post(route('recurring-expense-obligations.pay', $obligation), [
            'amount' => '27.000',
            'funding_source' => Expense::FUNDING_COMPANY_ACCOUNT,
            'financial_account_id' => $account->id,
            'paid_at' => '2026-09-14 15:00:00',
        ])->assertSessionHas('success');

        $expense = Expense::v2()->firstOrFail();
        $this->assertSame(27000, $expense->amount_minor);
        $this->assertSame(RecurringExpenseObligation::STATUS_PAID, $obligation->fresh()->status);
        $this->assertSame($expense->id, $obligation->fresh()->paid_expense_id);
        $this->assertSame(2, CashMovement::count());
        $this->assertSame(73000, app(FinancialAccountBalanceService::class)->currentBalanceMinor($account->fresh()));

        $this->actingAs($admin)->post(route('recurring-expense-obligations.pay', $obligation), [
            'amount' => '27.000',
            'funding_source' => Expense::FUNDING_COMPANY_ACCOUNT,
            'financial_account_id' => $account->id,
        ])->assertSessionHasErrors('recurring_expense_obligation_id');

        $this->actingAs($admin)->post(route('operating-expenses.reverse', $expense), [
            'reason' => 'Recurring paid by mistake',
        ])->assertSessionHas('success');
        $this->assertSame(RecurringExpenseObligation::STATUS_PENDING, $obligation->fresh()->status);
        $this->assertNull($obligation->fresh()->paid_expense_id);
    }

    public function test_personal_obligation_skip_cancel_legacy_and_partner_boundaries(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $payer = User::factory()->create(['role' => 'admin']);
        $category = ExpenseCategory::where('key', 'software_subscriptions')->firstOrFail();
        $template = RecurringExpenseTemplate::create([
            'name' => 'Software',
            'category_id' => $category->id,
            'currency' => 'JOD',
            'amount_minor' => 5000,
            'frequency' => RecurringExpenseTemplate::FREQUENCY_MONTHLY,
            'interval_count' => 1,
            'start_date' => '2026-09-01',
            'next_due_date' => '2026-09-01',
            'default_funding_source' => Expense::FUNDING_PERSONAL,
            'default_paid_by_user_id' => $payer->id,
            'is_active' => true,
            'created_by' => $admin->id,
        ]);
        $this->artisan('finance:generate-recurring-expenses', ['--date' => '2026-09-14'])->assertSuccessful();
        $personalObligation = RecurringExpenseObligation::firstOrFail();

        $this->actingAs($admin)->post(route('recurring-expense-obligations.pay', $personalObligation), [
            'amount' => '5.000',
            'funding_source' => Expense::FUNDING_PERSONAL,
            'paid_by_user_id' => $payer->id,
            'paid_at' => '2026-09-14 16:00:00',
        ])->assertSessionHas('success');
        $this->assertSame(0, CashMovement::count());

        $skip = RecurringExpenseObligation::create([
            'recurring_expense_template_id' => $template->id,
            'due_date' => '2026-10-01',
            'expected_amount_minor' => 5000,
            'currency' => 'JOD',
            'category_id' => $category->id,
            'category_name_snapshot' => $category->displayName(),
            'default_funding_source' => Expense::FUNDING_PERSONAL,
            'status' => RecurringExpenseObligation::STATUS_PENDING,
        ]);
        $cancel = RecurringExpenseObligation::create([
            'recurring_expense_template_id' => $template->id,
            'due_date' => '2026-11-01',
            'expected_amount_minor' => 5000,
            'currency' => 'JOD',
            'category_id' => $category->id,
            'category_name_snapshot' => $category->displayName(),
            'default_funding_source' => Expense::FUNDING_PERSONAL,
            'status' => RecurringExpenseObligation::STATUS_PENDING,
        ]);
        $expenseCount = Expense::count();
        $this->actingAs($admin)->post(route('recurring-expense-obligations.skip', $skip))->assertSessionHas('success');
        $this->actingAs($admin)->post(route('recurring-expense-obligations.cancel', $cancel))->assertSessionHas('success');
        $this->assertSame($expenseCount, Expense::count());

        Expense::create([
            'amount' => '18.00',
            'category' => 'Legacy',
            'date' => '2026-09-14',
            'paid_by' => $admin->id,
        ]);
        $this->assertSame(1, Expense::whereNull('expense_engine_version')->count());
        $this->assertSame(0, CashMovement::count());

        [, $partnerUser] = $this->createPartnerUser();
        $this->actingAs($partnerUser)->get(route('operating-expenses.index'))->assertForbidden();
        $this->actingAs($partnerUser)->post(route('operating-expenses.store'), [
            'amount' => '1.000',
            'category_id' => $category->id,
            'funding_source' => Expense::FUNDING_PERSONAL,
            'paid_by_user_id' => $payer->id,
            'incurred_on' => '2026-09-14',
            'paid_at' => '2026-09-14 10:00:00',
        ])->assertForbidden();
    }

    private function createCompanyExpense(User $admin, ExpenseCategory $category, FinancialAccount $account, string $amount, ?Vendor $vendor = null): Expense
    {
        $this->actingAs($admin)->post(route('operating-expenses.store'), [
            'amount' => $amount,
            'category_id' => $category->id,
            'vendor_id' => $vendor?->id,
            'payee_name' => $vendor ? null : 'Payee',
            'funding_source' => Expense::FUNDING_COMPANY_ACCOUNT,
            'financial_account_id' => $account->id,
            'incurred_on' => '2026-09-14',
            'paid_at' => '2026-09-14 10:00:00',
        ])->assertSessionHas('success');

        return Expense::v2()->orderByDesc('id')->firstOrFail();
    }

    private function createPersonalExpense(User $admin, User $payer, ExpenseCategory $category, string $amount): Expense
    {
        $this->actingAs($admin)->post(route('operating-expenses.store'), [
            'amount' => $amount,
            'category_id' => $category->id,
            'payee_name' => 'Personal Payee',
            'funding_source' => Expense::FUNDING_PERSONAL,
            'paid_by_user_id' => $payer->id,
            'incurred_on' => '2026-09-14',
            'paid_at' => '2026-09-14 10:00:00',
        ])->assertSessionHas('success');

        return Expense::v2()->orderByDesc('id')->firstOrFail();
    }

    private function createAccount(string $code, string $name, string $openingBalance = '0.000'): FinancialAccount
    {
        $account = FinancialAccount::create([
            'code' => $code,
            'name_ar' => $name,
            'type' => FinancialAccount::TYPE_CASH,
            'currency' => 'JOD',
            'is_active' => true,
        ]);

        $minor = Money::fromJod($openingBalance)->minorUnits();
        if ($minor > 0) {
            CashMovement::create([
                'financial_account_id' => $account->id,
                'direction' => CashMovement::DIRECTION_INFLOW,
                'amount_minor' => $minor,
                'currency' => 'JOD',
                'event_type' => CashMovement::EVENT_OPENING_BALANCE,
                'event_key' => 'test_account:'.$account->id.':opening',
                'source_type' => FinancialAccount::class,
                'source_id' => $account->id,
                'occurred_at' => '2026-09-14 08:00:00',
            ]);
        }

        return $account;
    }

    private function createPartnerUser(): array
    {
        $partner = Partner::create([
            'company_name' => 'Phase D2A Partner',
            'email' => 'phase-d2a-partner@example.com',
        ]);

        $user = User::factory()->create([
            'role' => 'partner',
            'partner_id' => $partner->id,
        ]);

        return [$partner, $user];
    }
}

class D2ARouteInspector
{
    public static function hasDeleteRouteContaining(string $needle): bool
    {
        return collect(app('router')->getRoutes())
            ->map(fn ($route) => ['methods' => $route->methods(), 'uri' => $route->uri()])
            ->contains(fn (array $route) => in_array('DELETE', $route['methods'], true) && str_contains($route['uri'], $needle));
    }
}
