<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\RecurringExpenseObligation;
use App\Models\RecurringExpenseTemplate;
use App\Models\Vendor;
use App\Services\FinanceOverviewService;
use App\Services\OperatingExpenseService;
use App\Services\PaymentFinancialAccountResolver;
use App\Services\RecurringExpenseService;
use App\Support\PaymentMethods;
use App\Support\Permissions;
use App\Support\ReportingPeriod;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Expenses (§12.3, FROZEN D-12/D-17): company-paid only, one-time or monthly recurring, Cash|CliQ.
 * The company account is always resolved from the method; personal funding, vendors and arbitrary
 * accounts are engine-only and rejected here. Posting stays in OperatingExpenseService.
 */
class OperatingExpenseController extends Controller
{
    public const TABS = ['expenses', 'recurring', 'categories'];

    /** Engine-only inputs that the normal V1 expense routes never accept. */
    private const NON_V1_EXPENSE_FIELDS = [
        'funding_source', 'financial_account_id', 'paid_by_user_id', 'vendor_id', 'payee_name', 'recurring_expense_obligation_id',
    ];

    private const NON_V1_TEMPLATE_FIELDS = [
        'frequency', 'interval_count', 'end_date', 'next_due_date', 'vendor_id', 'payee_name',
        'default_funding_source', 'default_financial_account_id', 'default_paid_by_user_id',
    ];

    public function __construct(
        private readonly OperatingExpenseService $expenses,
        private readonly RecurringExpenseService $recurring,
        private readonly PaymentFinancialAccountResolver $accounts
    ) {
    }

    public function index(Request $request)
    {
        Gate::authorize(Permissions::VIEW_EXPENSE_MANAGEMENT);

        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'expenses';
        $today = today();
        $dueQuery = RecurringExpenseObligation::query()->pending();

        $data = [
            'tab' => $tab,
            'dueCount' => (clone $dueQuery)->count(),
            'activeCategories' => ExpenseCategory::active()->get(),
            'methods' => PaymentMethods::v1Labels(),
        ];

        if ($tab === 'expenses') {
            // Same figure as Finance Overview "Expenses This Month" (one authority, no second total).
            $data['monthMinor'] = app(FinanceOverviewService::class)->companyExpenseCashOutMinor(
                new ReportingPeriod($today->copy()->startOfMonth(), $today->copy()->endOfMonth()->endOfDay(), 'this_month')
            );
            $data['recentExpenses'] = Expense::with(['categoryModel', 'financialAccount', 'reversal', 'recurringObligation'])
                ->v2()
                ->where('funding_source', Expense::FUNDING_COMPANY_ACCOUNT)
                ->orderByDesc('paid_at')
                ->orderByDesc('id')
                ->paginate(20)
                ->withQueryString();
        } elseif ($tab === 'recurring') {
            $data['dueObligations'] = (clone $dueQuery)->with(['template', 'defaultFinancialAccount'])
                ->orderBy('due_date')
                ->limit(30)
                ->get();
            $data['templates'] = RecurringExpenseTemplate::with(['category', 'defaultFinancialAccount'])
                ->where('frequency', RecurringExpenseTemplate::FREQUENCY_MONTHLY)
                ->where(fn ($query) => $query->whereNull('default_funding_source')->orWhere('default_funding_source', '!=', Expense::FUNDING_PERSONAL))
                ->orderByDesc('is_active')
                ->orderBy('next_due_date')
                ->limit(50)
                ->get();
        } else {
            $data['categories'] = ExpenseCategory::whereNull('archived_at')->orderBy('sort_order')->orderBy('name')->get();
        }

        return view('operating-expenses.index', $data);
    }

    public function storeExpense(Request $request): RedirectResponse
    {
        Gate::authorize(Permissions::MANAGE_EXPENSES);

        $validated = $request->validate([
            'amount' => ['required', 'regex:/^\d+(?:\.\d{1,3})?$/'],
            'category_id' => 'required|exists:expense_categories,id',
            'payment_method' => ['required', Rule::in(PaymentMethods::v1())],
            'expense_date' => 'required|date|before_or_equal:today',
            'description' => 'nullable|string|max:255',
        ] + $this->prohibited(self::NON_V1_EXPENSE_FIELDS));

        $date = Carbon::parse($validated['expense_date']);
        $this->expenses->createV2Expense([
            'amount' => $validated['amount'],
            'category_id' => $validated['category_id'],
            'funding_source' => Expense::FUNDING_COMPANY_ACCOUNT,
            'financial_account_id' => $this->accounts->resolve($validated['payment_method'])->id,
            'incurred_on' => $date->toDateString(),
            'paid_at' => $this->paidAt($date),
            'description' => $validated['description'] ?? null,
        ], $request->user());

        return redirect()->route('finance.expenses')->with('success', __('notify.expenses.saved'));
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
            isset($validated['reversed_at']) ? Carbon::parse($validated['reversed_at']) : null
        );

        return back()->with('success', __('notify.expenses.reversed_done'));
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        Gate::authorize(Permissions::MANAGE_EXPENSE_CATEGORIES);

        $validated = $request->validate([
            'key' => 'nullable|string|max:100|unique:expense_categories,key',
            'code' => 'nullable|string|max:100|unique:expense_categories,code',
            'name_ar' => 'required|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'sort_order' => 'nullable|integer|min:0|max:100000',
        ]);

        $key = $validated['key'] ?? $this->uniqueCategoryKey($validated['name_en'] ?? null);
        $category = ExpenseCategory::create([
            'key' => $key,
            'code' => $validated['code'] ?? $key,
            'name' => $validated['name_ar'],
            'name_ar' => $validated['name_ar'],
            'name_en' => $validated['name_en'] ?? null,
            'description' => $validated['description'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => true,
        ]);
        $this->log($request->user()?->id, 'expense_category_created', 'تم إنشاء تصنيف مصروف', ['expense_category_id' => $category->id]);

        return back()->with('success', __('notify.expenses.category_saved'));
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

        return back()->with('success', __('notify.expenses.category_saved'));
    }

    public function archiveCategory(ExpenseCategory $category): RedirectResponse
    {
        Gate::authorize(Permissions::MANAGE_EXPENSE_CATEGORIES);

        $category->update(['is_active' => false, 'archived_at' => now()]);

        return back()->with('success', __('notify.expenses.category_archived'));
    }

    /** Engine retained; vendors are not exposed in the V1 UI (§12.3). */
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

    /** Monthly recurring company expense: "repeat this expense every month" on a due day 1–28. */
    public function storeTemplate(Request $request): RedirectResponse
    {
        Gate::authorize(Permissions::MANAGE_RECURRING_EXPENSES);

        $validated = $request->validate([
            'amount' => ['required', 'regex:/^\d+(?:\.\d{1,3})?$/'],
            'category_id' => 'required|exists:expense_categories,id',
            'payment_method' => ['required', Rule::in(PaymentMethods::v1())],
            'start_date' => 'required|date',
            'due_day' => 'required|integer|min:1|max:28',
            'description' => 'nullable|string|max:255',
        ] + $this->prohibited(self::NON_V1_TEMPLATE_FIELDS));

        $category = ExpenseCategory::findOrFail($validated['category_id']);
        $description = trim((string) ($validated['description'] ?? ''));

        $this->recurring->createTemplate([
            'name' => $description !== '' ? $description : $category->displayName(),
            'category_id' => $category->id,
            'amount' => $validated['amount'],
            'frequency' => RecurringExpenseTemplate::FREQUENCY_MONTHLY,
            'interval_count' => 1,
            'start_date' => Carbon::parse($validated['start_date'])->toDateString(),
            'next_due_date' => $this->firstDueDate(Carbon::parse($validated['start_date']), (int) $validated['due_day'])->toDateString(),
            'default_funding_source' => Expense::FUNDING_COMPANY_ACCOUNT,
            'default_financial_account_id' => $this->accounts->resolve($validated['payment_method'])->id,
        ], $request->user());

        return redirect()->route('finance.expenses', ['tab' => 'recurring'])->with('success', __('notify.expenses.recurring_saved'));
    }

    /** Existing lifecycle only: change the amount going forward, or stop repeating (deactivate). */
    public function updateTemplate(Request $request, RecurringExpenseTemplate $template): RedirectResponse
    {
        Gate::authorize(Permissions::MANAGE_RECURRING_EXPENSES);

        $validated = $request->validate([
            'amount' => ['sometimes', 'regex:/^\d+(?:\.\d{1,3})?$/'],
            'is_active' => 'sometimes|declined',
        ] + $this->prohibited(array_merge(self::NON_V1_TEMPLATE_FIELDS, ['name', 'category_id', 'start_date'])));

        $changes = [];
        if (array_key_exists('amount', $validated)) {
            $changes['amount'] = $validated['amount'];
        }
        if (array_key_exists('is_active', $validated)) {
            $changes['is_active'] = false;
        }
        if ($changes !== []) {
            $this->recurring->updateTemplate($template, $changes, $request->user());
        }

        return back()->with('success', isset($changes['is_active']) ? __('notify.expenses.recurring_stopped') : __('notify.expenses.recurring_updated'));
    }

    public function generateRecurring(Request $request): RedirectResponse
    {
        Gate::authorize(Permissions::MANAGE_RECURRING_EXPENSES);

        $validated = $request->validate(['business_date' => 'nullable|date']);
        $count = $this->recurring->generateDueObligations(isset($validated['business_date']) ? Carbon::parse($validated['business_date']) : null);

        return back()->with('success', 'تم توليد '.$count.' التزام متكرر.');
    }

    /** "Mark as paid": the owner confirms amount, method and date; the existing expense path posts it. */
    public function payObligation(Request $request, RecurringExpenseObligation $obligation): RedirectResponse
    {
        Gate::authorize(Permissions::MANAGE_EXPENSES);

        $validated = $request->validate([
            'amount' => ['required', 'regex:/^\d+(?:\.\d{1,3})?$/'],
            'payment_method' => ['required', Rule::in(PaymentMethods::v1())],
            'paid_on' => 'required|date|before_or_equal:today',
        ] + $this->prohibited(self::NON_V1_EXPENSE_FIELDS));

        $paidOn = Carbon::parse($validated['paid_on']);
        $this->expenses->createV2Expense([
            'amount' => $validated['amount'],
            'category_id' => $obligation->category_id,
            'payee_name' => $obligation->payee_name_snapshot,
            'funding_source' => Expense::FUNDING_COMPANY_ACCOUNT,
            'financial_account_id' => $this->accounts->resolve($validated['payment_method'])->id,
            'incurred_on' => $obligation->due_date->toDateString(),
            'paid_at' => $this->paidAt($paidOn),
            'description' => $obligation->template?->name,
            'recurring_expense_obligation_id' => $obligation->id,
        ], $request->user());

        return back()->with('success', __('notify.expenses.obligation_paid'));
    }

    public function skipObligation(Request $request, RecurringExpenseObligation $obligation): RedirectResponse
    {
        Gate::authorize(Permissions::MANAGE_RECURRING_EXPENSES);

        $validated = $request->validate(['notes' => 'nullable|string|max:1000']);
        $this->recurring->skipObligation($obligation, $request->user(), $validated['notes'] ?? null);

        return back()->with('success', __('notify.expenses.obligation_skipped'));
    }

    public function cancelObligation(Request $request, RecurringExpenseObligation $obligation): RedirectResponse
    {
        Gate::authorize(Permissions::MANAGE_RECURRING_EXPENSES);

        $validated = $request->validate(['notes' => 'nullable|string|max:1000']);
        $this->recurring->cancelObligation($obligation, $request->user(), $validated['notes'] ?? null);

        return back()->with('success', 'تم إلغاء الالتزام.');
    }

    /** First due date on or after the start date that falls on the chosen day of the month (1–28). */
    private function firstDueDate(Carbon $start, int $dueDay): Carbon
    {
        $candidate = $start->copy()->startOfDay()->day($dueDay);

        return $candidate->lt($start->copy()->startOfDay()) ? $candidate->addMonthNoOverflow() : $candidate;
    }

    /** The day the money left the company; today keeps the current time, earlier days keep the clock time too. */
    private function paidAt(Carbon $date): string
    {
        return $date->copy()->setTimeFrom(now())->toDateTimeString();
    }

    private function prohibited(array $fields): array
    {
        return array_fill_keys($fields, 'prohibited');
    }

    private function uniqueCategoryKey(?string $nameEn): string
    {
        $base = Str::slug((string) $nameEn, '_') ?: 'category';
        $key = $base;
        while (ExpenseCategory::where('key', $key)->orWhere('code', $key)->exists()) {
            $key = $base.'_'.Str::lower(Str::random(5));
        }

        return $key;
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
