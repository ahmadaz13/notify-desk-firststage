<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Support\FinancialPermissions;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CommercialCatalogController extends Controller
{
    public function index(): View
    {
        Gate::authorize(FinancialPermissions::MANAGE_COMMERCIAL_CATALOG);

        return view('commercial-catalog.index', [
            'products' => Product::orderBy('archived_at')->orderBy('name_ar')->get(),
        ]);
    }

    public function storeProduct(Request $request): RedirectResponse
    {
        Gate::authorize(FinancialPermissions::MANAGE_COMMERCIAL_CATALOG);
        $data = $this->validateSystem($request);
        $system = Product::create($this->attributes($data) + [
            'code' => $this->uniqueCode($data['name_en'] ?: $data['name_ar']),
            'is_active' => true,
            'created_by' => $request->user()->id,
        ]);
        $this->log('system_created', 'تم إنشاء نظام جديد: '.$system->name_ar, $system);

        return back()->with('success', __('notify.systems.created'));
    }

    public function updateProduct(Request $request, Product $product): RedirectResponse
    {
        Gate::authorize(FinancialPermissions::MANAGE_COMMERCIAL_CATALOG);
        $data = $this->validateSystem($request);
        $product->update($this->attributes($data) + ['is_active' => $request->boolean('is_active')]);

        return back()->with('success', __('notify.systems.updated'));
    }

    public function archiveProduct(Product $product): RedirectResponse
    {
        Gate::authorize(FinancialPermissions::MANAGE_COMMERCIAL_CATALOG);
        $product->update(['is_active' => false, 'archived_at' => now()]);
        $this->log('system_archived', 'تمت أرشفة النظام: '.$product->name_ar, $product);

        return back()->with('success', __('notify.systems.archived'));
    }

    private function validateSystem(Request $request): array
    {
        return $request->validate([
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['required', 'string', 'max:255'],
            'description_ar' => ['nullable', 'string'],
            'description_en' => ['nullable', 'string'],
            'default_monthly_price_jod' => ['nullable', 'string', 'regex:/^\d+(\.\d{1,3})?$/'],
            'default_annual_price_jod' => ['nullable', 'string', 'regex:/^\d+(\.\d{1,3})?$/'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    private function attributes(array $data): array
    {
        return [
            'name_ar' => $data['name_ar'],
            'name_en' => $data['name_en'],
            'description_ar' => $data['description_ar'] ?? null,
            'description_en' => $data['description_en'] ?? null,
            'default_monthly_price_minor' => filled($data['default_monthly_price_jod'] ?? null) ? Money::fromJod($data['default_monthly_price_jod'])->minorUnits() : null,
            'default_annual_price_minor' => filled($data['default_annual_price_jod'] ?? null) ? Money::fromJod($data['default_annual_price_jod'])->minorUnits() : null,
        ];
    }

    private function uniqueCode(string $name): string
    {
        $base = Str::slug($name) ?: 'system';
        $code = $base;
        $suffix = 2;
        while (Product::where('code', $code)->exists()) {
            $code = $base.'-'.$suffix++;
        }

        return $code;
    }

    private function log(string $type, string $description, Product $system): void
    {
        DB::table('activity_logs')->insert([
            'user_id' => auth()->id(),
            'type' => $type,
            'description' => $description,
            'metadata' => json_encode(['system_id' => $system->id]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
