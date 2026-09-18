<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\PlanPrice;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlanPriceService
{
    public function createVersion(Plan $plan, array $data, ?int $userId = null): PlanPrice
    {
        $interval = $data['billing_interval'];
        if (! in_array($interval, [PlanPrice::MONTHLY, PlanPrice::ANNUAL], true)) {
            throw ValidationException::withMessages(['billing_interval' => 'Invalid billing interval.']);
        }

        $effectiveFrom = Carbon::parse($data['effective_from']);
        $amountMinor = Money::fromJod($data['amount_jod'])->minorUnits();
        $setupFeeMinor = Money::fromJod($data['setup_fee_jod'] ?? '0.000')->minorUnits();
        $additionalBranchMinor = filled($data['additional_branch_price_jod'] ?? null)
            ? Money::fromJod($data['additional_branch_price_jod'])->minorUnits()
            : null;

        return DB::transaction(function () use ($plan, $data, $userId, $interval, $effectiveFrom, $amountMinor, $setupFeeMinor, $additionalBranchMinor) {
            $current = PlanPrice::where('plan_id', $plan->id)
                ->where('billing_interval', $interval)
                ->where('is_active', true)
                ->orderByDesc('effective_from')
                ->lockForUpdate()
                ->first();

            if ($current && $current->effective_from->greaterThanOrEqualTo($effectiveFrom)) {
                throw ValidationException::withMessages([
                    'effective_from' => 'لا يمكن إنشاء سعر فعال يتداخل مع نسخة سعر حالية أو أقدم منها.',
                ]);
            }

            if ($current) {
                $current->update([
                    'is_active' => false,
                    'effective_until' => $effectiveFrom->copy()->subSecond(),
                ]);
            }

            return PlanPrice::create([
                'plan_id' => $plan->id,
                'billing_interval' => $interval,
                'currency' => PlanPrice::CURRENCY,
                'amount_minor' => $amountMinor,
                'setup_fee_minor' => $setupFeeMinor,
                'included_branch_quantity' => (int) ($data['included_branch_quantity'] ?? 1),
                'additional_branch_price_minor' => $additionalBranchMinor,
                'default_tax_rate_bps' => $data['default_tax_rate_bps'] ?? null,
                'effective_from' => $effectiveFrom,
                'effective_until' => null,
                'is_active' => true,
                'created_by' => $userId,
            ]);
        });
    }

    public function activeEffectivePrice(int $planPriceId): PlanPrice
    {
        $price = PlanPrice::with('plan')->effective(now())->find($planPriceId);

        if (! $price || ! $price->plan || ! $price->plan->is_active || $price->plan->archived_at !== null) {
            throw ValidationException::withMessages([
                'plan_price_id' => 'السعر المختار غير فعال أو غير متاح للبيع حالياً.',
            ]);
        }

        return $price;
    }

    public function resolveEffectivePrice(
        int $productId,
        int $planId,
        string $billingInterval,
        Carbon|string|null $startDate = null
    ): PlanPrice {
        if (! in_array($billingInterval, [PlanPrice::MONTHLY, PlanPrice::ANNUAL], true)) {
            throw ValidationException::withMessages([
                'billing_interval' => __('notify.client_workspace.validation.billing_interval') ?: 'اختر مدة الاشتراك.',
            ]);
        }

        $date = $startDate ? Carbon::parse($startDate)->startOfDay() : now()->startOfDay();

        $plan = \App\Models\Plan::with('product')
            ->where('id', $planId)
            ->where('product_id', $productId)
            ->where('is_active', true)
            ->whereNull('archived_at')
            ->first();

        if (! $plan) {
            throw ValidationException::withMessages([
                'plan_id' => __('notify.client_workspace.validation.plan') ?: 'اختر الباقة.',
            ]);
        }

        if (! $plan->product || ! $plan->product->is_active || $plan->product->archived_at !== null) {
            throw ValidationException::withMessages([
                'product_id' => __('notify.client_workspace.validation.product') ?: 'اختر المنتج.',
            ]);
        }

        $prices = PlanPrice::where('plan_id', $planId)
            ->where('billing_interval', $billingInterval)
            ->effective($date)
            ->get();

        if ($prices->isEmpty()) {
            throw ValidationException::withMessages([
                'billing_interval' => __('notify.client_workspace.validation.price_unavailable') ?: 'لا يوجد سعر فعال لهذه الباقة في التاريخ المحدد.',
            ]);
        }

        if ($prices->count() > 1) {
            throw ValidationException::withMessages([
                'billing_interval' => 'تكوين السعر في الكتالوج غير متسق لوجود أكثر من سعر فعال في نفس التاريخ.',
            ]);
        }

        $price = $prices->first();
        $price->setRelation('plan', $plan);

        return $price;
    }

    public function getSellableCatalogTree(?Carbon $at = null): array
    {
        $at = $at ?: now();
        $products = \App\Models\Product::where('is_active', true)
            ->whereNull('archived_at')
            ->with(['plans' => function ($query) use ($at) {
                $query->where('is_active', true)
                    ->whereNull('archived_at')
                    ->with(['prices' => function ($pQuery) use ($at) {
                        $pQuery->effective($at);
                    }]);
            }])
            ->get();

        return $products->map(function ($product) {
            return [
                'id' => $product->id,
                'code' => $product->code,
                'name_ar' => $product->name_ar,
                'name_en' => $product->name_en,
                'plans' => $product->plans->map(function ($plan) {
                    $availableIntervals = $plan->prices->pluck('billing_interval')->unique()->values()->all();
                    return [
                        'id' => $plan->id,
                        'code' => $plan->code,
                        'tier' => $plan->tier,
                        'name_ar' => $plan->name_ar,
                        'name_en' => $plan->name_en,
                        'available_intervals' => $availableIntervals,
                    ];
                })->filter(fn ($plan) => count($plan['available_intervals']) > 0)->values()->all(),
            ];
        })->filter(fn ($prod) => count($prod['plans']) > 0)->values()->all();
    }
}
