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
}
