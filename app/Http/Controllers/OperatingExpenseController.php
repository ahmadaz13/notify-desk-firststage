<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FinancialAccount;
use App\Models\RecurringExpenseObligation;
use App\Models\RecurringExpenseTemplate;
use App\Models\User;
use App\Models\Vendor;
use App\Services\OperatingExpenseService;
use App\Services\RecurringExpenseService;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class OperatingExpenseController extends Controller
{
    public function __construct(
        private readonly OperatingExpenseService $expenses,
        private readonly RecurringExpenseService $recurring
    ) {
    }

    public function index()
    {
        Gate::authorize(Permissions::VIEW_EXPENSE_MANAGEMENT);

        $totals = $this->expenses->activeTotals();
        $today = today();

        return view('operating-expenses.index', [
            'totals' => $totals,
            'expenseByCategory' => Expense::activeV2()
                ->select('category_name_snapshot', DB::raw('sum(amount_minor) as total_minor'))
                ->groupBy('category_name_snapshot')
                ->orderByDesc('total_minor')
                ->limit(8)
                ->get(),
            'recentExpenses' => Expense::with(['categoryModel', 'vendor', 'financialAccount', 'personalPayer', 'reversal', 'recurringObligation'])
                ->v2()
                ->orderByDesc('paid_at')
                ->orderByDesc('id')
                ->limit(20)
                ->get(),
            'upcomingObligations' => RecurringExpenseObligation::with(['template', 'vendor'])
                ->pending()
                ->whereDate('due_date', '>=', $today)
                ->orderBy('due_date')
                ->limit(10)
                ->get(),
            'overdueObligations' => RecurringExpenseObligation::with(['template', 'vendor'])
                ->pending()
                ->whereDate('due_date', '<', $today)
                ->orderBy('due_date')
                ->limit(10)
                ->get(),
            'pendingObligations' => RecurringExpenseObligation::with(['template', 'vendor'])
                ->pending()
                ->orderBy('due_date')
                ->limit(25)
                ->get(),
            'templates' => RecurringExpenseTemplate::with(['category', 'vendor'])
                ->orderByDesc('is_active')
                ->orderBy('next_due_date')
                ->limit(20)
                ->get(),
            'vendors' => Vendor::orderByDesc('is_active')->orderBy('name')->get(),
            'categories' => ExpenseCategory::orderByDesc('is_active')->orderBy('sort_order')->orderBy('name')->get(),
            'activeCategories' => ExpenseCategory::active()->get(),
            'activeVendors' => Vendor::active()->get(),
            'activeFinancialAccounts' => FinancialAccount::where('is_active', true)->whereNull('archived_at')->orderBy('name_ar')->get(),
            'internalUsers' => User::query()
                ->where('is_active', true)
                ->where(fn ($query) => $query->whereNull('role')->orWhereIn('role', User::activeInternalRoles()))
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function storeExpense(Request $request): RedirectResponse
    {
        Gate::authorize(Permissions::MANAGE_EXPENSES);

        $validated = $request->validate($this->expenseRules());
        $this->expenses->createV2Expense($validated, $request->user());

        return back()->with('success', 'تم تسجيل المصروف التشغيلي.');
    }

    public function reverseExpense(Request $request, Expense $expense): RedirectResponse
    {
        Gate::authorize(Permissions::MANAGE_EXPENSES);

        $validated = $request->validate([
            'reason' => 'required|string|min:3|max:1000',
            'reversed_at' => 'nullable|date',
        ]);

        $this->expenses->reverseExpense(
            $expense,
            $validated['reason'],
            $request->user(),
            isset($validated['reversed_at']) ? \Carbon\Carbon::parse($validated['reversed_at']) : null
        );

        return back()->with('success', 'تم عكس المصروف التشغيلي.');
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        Gate::authorize(Permissions::MANAGE_EXPENSE_CATEGORIES);

        $validated = $request->validate([
            'key' => 'required|string|max:100|unique:expense_categories,key',
            'code' => 'nullable|string|max:100|unique:expense_categories,code',
            'name_ar' => 'required|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'sort_order' => 'nullable|integer|min:0|max:100000',
        ]);

        $category = ExpenseCategory::create([
            'key' => $validated['key'],
            'code' => $validated['code'] ?? $validated['key'],
            'name' => $validated['name_ar'],
            'name_ar' => $validated['name_ar'],
            'name_en' => $validated['name_en'] ?? null,
            'description' => $validated['description'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => true,
        ]);
        $this->log($request->user()?->id, 'expense_category_created', 'تم إنشاء تصنيف مصروف', ['expense_category_id' => $category->id]);

        return back()->with('success', 'تم إنشاء تصنيف المصروف.');
    }

    public function updateCategory(Request $request, ExpenseCategory $category): RedirectResponse
    {
        Gate::authorize(Permissions::MANAGE_EXPENSE_CATEGORIES);

        $validated = $request->validate([
            'name_ar' => 'required|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'sort_order' => 'nullable|integer|min:0|max:100000',
            'is_active' => 'nullable|boolean',
        ]);

        $category->update([
            'name' => $validated['name_ar'],
            'name_ar' => $validated['name_ar'],
            'name_en' => $validated['name_en'] ?? null,
            'description' => $validated['description'] ?? null,
            'sort_order' => $validated['sort_order'] ?? $category->sort_order,
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ]);

        return back()->with('success', 'تم تحديث تصنيف المصروف.');
    }

    public function archiveCategory(ExpenseCategory $category): RedirectResponse
    {
        Gate::authorize(Permissions::MANAGE_EXPENSE_CATEGORIES);

        $category->update(['is_active' => false, 'archived_at' => now()]);

        return back()->with('success', 'تم أرشفة تصنيف المصروف.');
    }

    public function storeVendor(Request $request): RedirectResponse
    {
        Gate::authorize(Permissions::MANAGE_VENDORS);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:100',
            'email' => 'nullable|email|max:255',
            'tax_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
        ]);

        $vendor = Vendor::create($validated + [
            'is_active' => true,
            'created_by' => $request->user()?->id,
        ]);
        $this->log($request->user()?->id, 'vendor_created', 'تم إنشاء مورد', ['vendor_id' => $vendor->id]);

        return back()->with('success', 'تم إنشاء المورد.');
    }

    public function archiveVendor(Vendor $vendor): RedirectResponse
    {
        Gate::authorize(Permissions::MANAGE_VENDORS);

        $vendor->update(['is_active' => false, 'archived_at' => now()]);

        return back()->with('success', 'تم أرشفة المورد.');
    }

    public function storeTemplate(Request $request): RedirectResponse
    {
        Gate::authorize(Permissions::MANAGE_RECURRING_EXPENSES);

        $validated = $request->validate($this->templateRules());
        $this->recurring->createTemplate($validated, $request->user());

        return back()->with('success', 'تم إنشاء قالب المصروف المتكرر.');
    }

    public function updateTemplate(Request $request, RecurringExpenseTemplate $template): RedirectResponse
    {
        Gate::authorize(Permissions::MANAGE_RECURRING_EXPENSES);

        $validated = $request->validate($this->templateRules(false));
        $this->recurring->updateTemplate($template, $validated + ['is_active' => $request->boolean('is_active')], $request->user());

        return back()->with('success', 'تم تحديث قالب المصروف المتكرر.');
    }

    public function generateRecurring(Request $request): RedirectResponse
    {
        Gate::authorize(Permissions::MANAGE_RECURRING_EXPENSES);

        $validated = $request->validate(['business_date' => 'nullable|date']);
        $count = $this->recurring->generateDueObligations(isset($validated['business_date']) ? \Carbon\Carbon::parse($validated['business_date']) : null);

        return back()->with('success', 'تم توليد '.$count.' التزام متكرر.');
    }

    public function payObligation(Request $request, RecurringExpenseObligation $obligation): RedirectResponse
    {
        Gate::authorize(Permissions::MANAGE_EXPENSES);

        $defaults = [
            'amount' => $request->input('amount') ?: $obligation->expectedAmountJod(),
            'category_id' => $request->input('category_id') ?: $obligation->category_id,
            'vendor_id' => $request->input('vendor_id') ?: $obligation->vendor_id,
            'payee_name' => $request->input('payee_name') ?: $obligation->payee_name_snapshot,
            'funding_source' => $request->input('funding_source') ?: $obligation->default_funding_source,
            'financial_account_id' => $request->input('financial_account_id') ?: $obligation->default_financial_account_id,
            'paid_by_user_id' => $request->input('paid_by_user_id'),
            'incurred_on' => $request->input('incurred_on') ?: $obligation->due_date->toDateString(),
            'paid_at' => $request->input('paid_at') ?: now()->toDateTimeString(),
            'recurring_expense_obligation_id' => $obligation->id,
        ];
        $validated = validator($defaults + $request->all(), $this->expenseRules())->validate();
        $this->expenses->createV2Expense($validated, $request->user());

        return back()->with('success', 'تم دفع الالتزام المتكرر.');
    }

    public function skipObligation(Request $request, RecurringExpenseObligation $obligation): RedirectResponse
    {
        Gate::authorize(Permissions::MANAGE_RECURRING_EXPENSES);

        $validated = $request->validate(['notes' => 'nullable|string|max:1000']);
        $this->recurring->skipObligation($obligation, $request->user(), $validated['notes'] ?? null);

        return back()->with('success', 'تم تخطي الالتزام.');
    }

    public function cancelObligation(Request $request, RecurringExpenseObligation $obligation): RedirectResponse
    {
        Gate::authorize(Permissions::MANAGE_RECURRING_EXPENSES);

        $validated = $request->validate(['notes' => 'nullable|string|max:1000']);
        $this->recurring->cancelObligation($obligation, $request->user(), $validated['notes'] ?? null);

        return back()->with('success', 'تم إلغاء الالتزام.');
    }

    private function expenseRules(): array
    {
        return [
            'amount' => ['required', 'regex:/^\d+(?:\.\d{1,3})?$/'],
            'category_id' => 'required|exists:expense_categories,id',
            'vendor_id' => 'nullable|exists:vendors,id',
            'payee_name' => 'nullable|string|max:255',
            'funding_source' => ['required', Rule::in([Expense::FUNDING_COMPANY_ACCOUNT, Expense::FUNDING_PERSONAL])],
            'financial_account_id' => 'nullable|exists:financial_accounts,id',
            'paid_by_user_id' => 'nullable|exists:users,id',
            'incurred_on' => 'required|date',
            'paid_at' => 'required|date',
            'reference' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:2000',
            'recurring_expense_obligation_id' => 'nullable|exists:recurring_expense_obligations,id',
        ];
    }

    private function templateRules(bool $creating = true): array
    {
        return [
            'name' => ($creating ? 'required' : 'sometimes').'|string|max:255',
            'category_id' => ($creating ? 'required' : 'sometimes').'|exists:expense_categories,id',
            'vendor_id' => 'nullable|exists:vendors,id',
            'payee_name' => 'nullable|string|max:255',
            'amount' => ($creating ? 'required' : 'sometimes').'|regex:/^\d+(?:\.\d{1,3})?$/',
            'frequency' => ($creating ? 'required' : 'sometimes').'|in:weekly,monthly,quarterly,annual',
            'interval_count' => 'nullable|integer|min:1|max:120',
            'start_date' => ($creating ? 'required' : 'sometimes').'|date',
            'end_date' => 'nullable|date',
            'next_due_date' => 'nullable|date',
            'default_financial_account_id' => 'nullable|exists:financial_accounts,id',
            'default_funding_source' => ['required', Rule::in([Expense::FUNDING_COMPANY_ACCOUNT, Expense::FUNDING_PERSONAL])],
            'default_paid_by_user_id' => 'nullable|exists:users,id',
            'notes' => 'nullable|string|max:2000',
        ];
    }

    private function log(?int $userId, string $type, string $description, array $metadata): void
    {
        DB::table('activity_logs')->insert([
            'client_id' => null,
            'user_id' => $userId,
            'type' => $type,
            'description' => $description,
            'metadata' => json_encode($metadata),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
