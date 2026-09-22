<?php

namespace App\Http\Controllers;

use App\Models\AssetCategory;
use App\Models\CapitalFundingTransaction;
use App\Models\FinancialAccount;
use App\Models\FixedAsset;
use App\Models\FundingSource;
use App\Models\User;
use App\Models\Vendor;
use App\Services\CapitalManagementService;
use App\Support\FinancialPermissions;
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
        Gate::authorize(FinancialPermissions::VIEW_CAPITAL_MANAGEMENT);

        return view('capital-management.index', [
            'totals' => $this->capital->activeTotals(),
            'fundingSources' => FundingSource::orderByDesc('is_active')->orderBy('name')->get(),
            'activeFundingSources' => FundingSource::active()->get(),
            'fundingTransactions' => CapitalFundingTransaction::with(['fundingSource', 'financialAccount', 'reversal'])->orderByDesc('received_at')->limit(20)->get(),
            'assetCategories' => AssetCategory::orderByDesc('is_active')->orderBy('sort_order')->orderBy('name_ar')->get(),
            'activeAssetCategories' => AssetCategory::active()->get(),
            'fixedAssets' => FixedAsset::with(['category', 'vendor', 'financialAccount', 'personalPayer', 'acquisitionReversal'])->orderByDesc('acquired_at')->orderByDesc('id')->limit(40)->get(),
            'activeFinancialAccounts' => FinancialAccount::where('is_active', true)->whereNull('archived_at')->orderBy('name_ar')->get(),
            'activeVendors' => Vendor::active()->get(),
            'internalUsers' => User::query()
                ->where('is_active', true)
                ->where(fn ($query) => $query->whereNull('role')->orWhereIn('role', User::activeInternalRoles()))
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function storeFundingSource(Request $request): RedirectResponse
    {
        Gate::authorize(FinancialPermissions::MANAGE_FUNDING_SOURCES);
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
        Gate::authorize(FinancialPermissions::MANAGE_FUNDING_SOURCES);
        $fundingSource->update(['is_active' => false, 'archived_at' => now()]);

        return back()->with('success', 'تم أرشفة مصدر التمويل.');
    }

    public function storeFunding(Request $request): RedirectResponse
    {
        Gate::authorize(FinancialPermissions::MANAGE_CAPITAL_FUNDING);
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
        Gate::authorize(FinancialPermissions::MANAGE_CAPITAL_FUNDING);
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

    public function storeAssetCategory(Request $request): RedirectResponse
    {
        Gate::authorize(FinancialPermissions::MANAGE_ASSET_CATEGORIES);
        $validated = $request->validate([
            'code' => 'required|string|max:100|unique:asset_categories,code',
            'name_ar' => 'required|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'sort_order' => 'nullable|integer|min:0|max:100000',
        ]);
        $this->capital->createAssetCategory($validated, $request->user());

        return back()->with('success', 'تم إنشاء تصنيف الأصل.');
    }

    public function archiveAssetCategory(AssetCategory $category): RedirectResponse
    {
        Gate::authorize(FinancialPermissions::MANAGE_ASSET_CATEGORIES);
        $category->update(['is_active' => false, 'archived_at' => now()]);

        return back()->with('success', 'تم أرشفة تصنيف الأصل.');
    }

    public function storeFixedAsset(Request $request): RedirectResponse
    {
        Gate::authorize(FinancialPermissions::MANAGE_FIXED_ASSETS);
        $validated = $request->validate($this->assetRules());
        $this->capital->acquireFixedAsset($validated, $request->user());

        return back()->with('success', 'تم تسجيل الأصل الثابت.');
    }

    public function reverseFixedAsset(Request $request, FixedAsset $asset): RedirectResponse
    {
        Gate::authorize(FinancialPermissions::MANAGE_FIXED_ASSETS);
        $validated = $request->validate([
            'reason' => 'required|string|min:3|max:1000',
            'reversed_at' => 'nullable|date',
        ]);
        $this->capital->reverseAssetAcquisition(
            $asset,
            $validated['reason'],
            $request->user(),
            isset($validated['reversed_at']) ? Carbon::parse($validated['reversed_at']) : null
        );

        return back()->with('success', 'تم عكس اقتناء الأصل.');
    }

    public function updateFixedAssetStatus(Request $request, FixedAsset $asset): RedirectResponse
    {
        Gate::authorize(FinancialPermissions::MANAGE_FIXED_ASSETS);
        $validated = $request->validate(['status' => ['required', Rule::in([FixedAsset::STATUS_ACTIVE, FixedAsset::STATUS_OUT_OF_SERVICE])]]);
        $this->capital->changeAssetStatus($asset, $validated['status'], $request->user());

        return back()->with('success', 'تم تحديث حالة الأصل.');
    }

    private function assetRules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'asset_category_id' => 'required|exists:asset_categories,id',
            'description' => 'nullable|string|max:2000',
            'vendor_id' => 'nullable|exists:vendors,id',
            'payee_name' => 'nullable|string|max:255',
            'serial_number' => 'nullable|string|max:255',
            'quantity' => 'nullable|integer|min:1|max:100000',
            'acquisition_cost' => ['required', 'regex:/^\d+(?:\.\d{1,3})?$/'],
            'funding_source' => ['required', Rule::in(FixedAsset::FUNDING_SOURCES)],
            'financial_account_id' => 'nullable|exists:financial_accounts,id',
            'paid_by_user_id' => 'nullable|exists:users,id',
            'acquired_at' => 'required|date',
            'in_service_at' => 'nullable|date',
            'reference' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'useful_life_months' => 'nullable|integer|min:1|max:1200',
            'residual_value' => ['nullable', 'regex:/^\d+(?:\.\d{1,3})?$/'],
            'notes' => 'nullable|string|max:2000',
        ];
    }
}
