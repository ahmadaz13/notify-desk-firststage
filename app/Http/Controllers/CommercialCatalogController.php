<?php

namespace App\Http\Controllers;

use App\Models\ClientSystemCredential;
use App\Models\Product;
use App\Support\Permissions;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Systems catalog (§6, §15, P13) — the one System authority (`products`). Stable codes are the
 * identity and are never edited here; identities are never merged or duplicated.
 */
class CommercialCatalogController extends Controller
{
    public function index(): View
    {
        Gate::authorize(Permissions::MANAGE_COMMERCIAL_CATALOG);

        $products = Product::query()
            ->orderByRaw('CASE WHEN archived_at IS NULL THEN 0 ELSE 1 END')
            ->orderByDesc('is_active')
            ->orderBy('id')
            ->get();

        // Stored credentials per System, one grouped query (explains why the capability is locked).
        $credentialCounts = ClientSystemCredential::query()
            ->select('product_id', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('product_id')
            ->pluck('aggregate', 'product_id')
            ->map(fn ($count) => (int) $count)
            ->all();

        return view('commercial-catalog.index', compact('products', 'credentialCounts'));
    }

    public function storeProduct(Request $request): RedirectResponse
    {
        Gate::authorize(Permissions::MANAGE_COMMERCIAL_CATALOG);
        $data = $this->validateSystem($request);
        $system = Product::create($this->attributes($data) + [
            'code' => $this->uniqueCode($data['name_en'] ?: $data['name_ar']),
            'is_active' => true,
            'requires_credentials' => $request->boolean('requires_credentials'),
            'created_by' => $request->user()->id,
        ]);
        $this->log('system_created', 'تم إنشاء نظام جديد: '.$system->name_ar, $system);

        return redirect()->route('commercial-catalog.index')->with('success', __('notify.systems.created'));
    }

    public function updateProduct(Request $request, Product $product): RedirectResponse
    {
        Gate::authorize(Permissions::MANAGE_COMMERCIAL_CATALOG);
        $data = $this->validateSystem($request);
        $attributes = $this->attributes($data) + ['is_active' => $request->boolean('is_active')];

        // Credential capability (§18.1) changes only when the form sends it explicitly.
        if ($request->has('requires_credentials')) {
            $requires = $request->boolean('requires_credentials');
            if (! $requires && $product->requires_credentials) {
                $stored = ClientSystemCredential::query()->where('product_id', $product->id)->count();
                if ($stored > 0) {
                    // Turning it off would hide saved client credentials; they must be removed first.
                    throw ValidationException::withMessages([
                        'requires_credentials' => trans_choice('notify.systems.credentials_locked', $stored, ['count' => $stored]),
                    ]);
                }
            }
            $attributes['requires_credentials'] = $requires;
        }

        $product->update($attributes);

        return redirect()->route('commercial-catalog.index')->with('success', __('notify.systems.updated'));
    }

    public function archiveProduct(Product $product): RedirectResponse
    {
        Gate::authorize(Permissions::MANAGE_COMMERCIAL_CATALOG);
        $product->update(['is_active' => false, 'archived_at' => now()]);
        $this->log('system_archived', 'تمت أرشفة النظام: '.$product->name_ar, $product);

        return redirect()->route('commercial-catalog.index')->with('success', __('notify.systems.archived'));
    }

    private function validateSystem(Request $request): array
    {
        return $request->validate([
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['required', 'string', 'max:255'],
            'description_ar' => ['nullable', 'string', 'max:500'],
            'description_en' => ['nullable', 'string', 'max:500'],
            'default_monthly_price_jod' => ['nullable', 'string', 'regex:/^\d+(\.\d{1,3})?$/'],
            'default_annual_price_jod' => ['nullable', 'string', 'regex:/^\d+(\.\d{1,3})?$/'],
            'is_active' => ['nullable', 'boolean'],
            'requires_credentials' => ['nullable', 'boolean'],
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
