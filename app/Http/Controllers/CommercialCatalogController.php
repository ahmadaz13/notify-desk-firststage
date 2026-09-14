<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Service;
use App\Services\PlanPriceService;
use App\Support\FinancialPermissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CommercialCatalogController extends Controller
{
    public function index(): View
    {
        Gate::authorize(FinancialPermissions::MANAGE_COMMERCIAL_CATALOG);

        $plans = Plan::with(['services', 'prices.creator'])->orderBy('code')->get();
        $services = Service::active()->get();

        return view('commercial-catalog.index', compact('plans', 'services'));
    }

    public function storePlan(Request $request): RedirectResponse
    {
        Gate::authorize(FinancialPermissions::MANAGE_COMMERCIAL_CATALOG);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9_\\-]+$/', 'unique:plans,code'],
            'name_ar' => 'required|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'description_ar' => 'nullable|string',
            'description_en' => 'nullable|string',
            'services' => 'nullable|array',
            'services.*' => 'exists:services,id',
        ]);

        $plan = Plan::create([
            'code' => $validated['code'],
            'name_ar' => $validated['name_ar'],
            'name_en' => $validated['name_en'] ?? null,
            'description_ar' => $validated['description_ar'] ?? null,
            'description_en' => $validated['description_en'] ?? null,
            'is_active' => true,
            'created_by' => auth()->id(),
        ]);

        $plan->services()->sync($validated['services'] ?? []);
        $this->log(null, 'plan_created', 'تم إنشاء باقة تجارية جديدة: '.$plan->name_ar, ['plan_id' => $plan->id]);

        return back()->with('success', 'تم إنشاء الباقة التجارية بنجاح.');
    }

    public function updatePlan(Request $request, Plan $plan): RedirectResponse
    {
        Gate::authorize(FinancialPermissions::MANAGE_COMMERCIAL_CATALOG);

        $validated = $request->validate([
            'name_ar' => 'required|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'description_ar' => 'nullable|string',
            'description_en' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'services' => 'nullable|array',
            'services.*' => 'exists:services,id',
        ]);

        $plan->update([
            'name_ar' => $validated['name_ar'],
            'name_en' => $validated['name_en'] ?? null,
            'description_ar' => $validated['description_ar'] ?? null,
            'description_en' => $validated['description_en'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        $plan->services()->sync($validated['services'] ?? []);

        return back()->with('success', 'تم تحديث الباقة التجارية.');
    }

    public function archivePlan(Plan $plan): RedirectResponse
    {
        Gate::authorize(FinancialPermissions::MANAGE_COMMERCIAL_CATALOG);

        $plan->update([
            'is_active' => false,
            'archived_at' => now(),
        ]);

        $this->log(null, 'plan_archived', 'تمت أرشفة الباقة التجارية: '.$plan->name_ar, ['plan_id' => $plan->id]);

        return back()->with('success', 'تمت أرشفة الباقة مع الحفاظ على السجل التاريخي.');
    }

    public function storePrice(Request $request, Plan $plan, PlanPriceService $priceService): RedirectResponse
    {
        Gate::authorize(FinancialPermissions::MANAGE_COMMERCIAL_CATALOG);

        $validated = $request->validate([
            'billing_interval' => ['required', Rule::in(['monthly', 'annual'])],
            'amount_jod' => $this->moneyRules(),
            'setup_fee_jod' => $this->nullableMoneyRules(),
            'included_branch_quantity' => 'required|integer|min:1|max:999',
            'additional_branch_price_jod' => $this->nullableMoneyRules(),
            'default_tax_rate_bps' => 'nullable|integer|min:0|max:10000',
            'effective_from' => 'required|date',
        ]);

        $price = $priceService->createVersion($plan, $validated, auth()->id());
        $this->log(null, 'plan_price_created', 'تم إنشاء نسخة سعر جديدة للباقة: '.$plan->name_ar, [
            'plan_id' => $plan->id,
            'plan_price_id' => $price->id,
            'amount_minor' => $price->amount_minor,
        ]);

        return back()->with('success', 'تم إنشاء نسخة السعر الجديدة بنجاح.');
    }

    private function moneyRules(): array
    {
        return ['required', 'string', 'regex:/^\\d+(\\.\\d{1,3})?$/'];
    }

    private function nullableMoneyRules(): array
    {
        return ['nullable', 'string', 'regex:/^\\d+(\\.\\d{1,3})?$/'];
    }

    private function log(?int $clientId, string $type, string $description, array $metadata): void
    {
        DB::table('activity_logs')->insert([
            'client_id' => $clientId,
            'user_id' => auth()->id(),
            'type' => $type,
            'description' => $description,
            'metadata' => json_encode($metadata),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
