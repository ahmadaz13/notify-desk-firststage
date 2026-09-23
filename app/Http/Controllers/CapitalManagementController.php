<?php

namespace App\Http\Controllers;

use App\Models\CapitalFundingTransaction;
use App\Models\FinancialAccount;
use App\Models\FundingSource;
use App\Services\CapitalManagementService;
use App\Support\Permissions;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CapitalManagementController extends Controller
{
    public function __construct(private readonly CapitalManagementService $capital)
    {
    }

    public function index()
    {
        Gate::authorize(Permissions::VIEW_CAPITAL_MANAGEMENT);

        return view('capital-management.index', [
            'totals' => $this->capital->activeTotals(),
            'fundingSources' => FundingSource::orderByDesc('is_active')->orderBy('name')->get(),
            'activeFundingSources' => FundingSource::active()->get(),
            'fundingTransactions' => CapitalFundingTransaction::with(['fundingSource', 'financialAccount', 'reversal'])->orderByDesc('received_at')->limit(20)->get(),
            'activeFinancialAccounts' => FinancialAccount::where('is_active', true)->whereNull('archived_at')->orderBy('name_ar')->get(),
        ]);
    }

    public function storeFundingSource(Request $request): RedirectResponse
    {
        Gate::authorize(Permissions::MANAGE_FUNDING_SOURCES);
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => ['required', Rule::in(FundingSource::TYPES)],
            'user_id' => 'nullable|exists:users,id',
            'phone' => 'nullable|string|max:100',
            'email' => 'nullable|email|max:255',
            'notes' => 'nullable|string|max:2000',
        ]);
        $this->capital->createFundingSource($validated, $request->user());

        return back()->with('success', 'تم إنشاء مصدر التمويل.');
    }

    public function archiveFundingSource(FundingSource $fundingSource): RedirectResponse
    {
        Gate::authorize(Permissions::MANAGE_FUNDING_SOURCES);
        $fundingSource->update(['is_active' => false, 'archived_at' => now()]);

        return back()->with('success', 'تم أرشفة مصدر التمويل.');
    }

    public function storeFunding(Request $request): RedirectResponse
    {
        Gate::authorize(Permissions::MANAGE_CAPITAL_FUNDING);
        $validated = $request->validate([
            'funding_source_id' => 'nullable|exists:funding_sources,id',
            'source_name' => 'nullable|string|max:255',
            'funding_type' => ['required', Rule::in(CapitalFundingTransaction::TYPES)],
            'financial_account_id' => 'required|exists:financial_accounts,id',
            'amount' => ['required', 'regex:/^\d+(?:\.\d{1,3})?$/'],
            'received_at' => 'required|date',
            'reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:2000',
        ]);
        $this->capital->recordCapitalFunding($validated, $request->user());

        return back()->with('success', 'تم تسجيل التمويل الرأسمالي.');
    }

    public function reverseFunding(Request $request, CapitalFundingTransaction $transaction): RedirectResponse
    {
        Gate::authorize(Permissions::MANAGE_CAPITAL_FUNDING);
        $validated = $request->validate([
            'reason' => 'required|string|min:3|max:1000',
            'reversed_at' => 'nullable|date',
        ]);
        $this->capital->reverseCapitalFunding(
            $transaction,
            $validated['reason'],
            $request->user(),
            isset($validated['reversed_at']) ? Carbon::parse($validated['reversed_at']) : null
        );

        return back()->with('success', 'تم عكس التمويل الرأسمالي.');
    }
}
