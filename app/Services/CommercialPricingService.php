<?php

namespace App\Services;

use App\Models\InvoiceLine;
use App\Models\PlanPrice;
use App\Support\Money;
use Illuminate\Validation\ValidationException;

class CommercialPricingService
{
    public function calculateSubscription(PlanPrice $price, int $quantity, ?string $discountJod = null): array
    {
        $quantity = max(1, $quantity);
        $included = max(1, (int) $price->included_branch_quantity);
        $extraBranches = max(0, $quantity - $included);
        $extraMinor = $price->additional_branch_price_minor !== null
            ? $extraBranches * (int) $price->additional_branch_price_minor
            : 0;

        $subscriptionSubtotalMinor = (int) $price->amount_minor + $extraMinor;
        $setupFeeMinor = (int) $price->setup_fee_minor;
        $discountMinor = $discountJod !== null && trim($discountJod) !== ''
            ? Money::fromJod($discountJod)->minorUnits()
            : 0;

        if ($discountMinor < 0 || $discountMinor > $subscriptionSubtotalMinor) {
            throw ValidationException::withMessages([
                'discount_jod' => 'قيمة الخصم يجب أن تكون بين صفر وقيمة الاشتراك قبل رسوم التأسيس.',
            ]);
        }

        $taxRateBps = $price->default_tax_rate_bps;
        $subscriptionTaxMinor = self::taxMinor($subscriptionSubtotalMinor - $discountMinor, $taxRateBps);
        $setupTaxMinor = self::taxMinor($setupFeeMinor, $taxRateBps);
        $taxMinor = $subscriptionTaxMinor + $setupTaxMinor;
        $subtotalMinor = $subscriptionSubtotalMinor + $setupFeeMinor;
        $totalMinor = ($subtotalMinor - $discountMinor) + $taxMinor;

        $lines = [
            [
                'line_type' => InvoiceLine::TYPE_SUBSCRIPTION,
                'plan_id' => $price->plan_id,
                'plan_price_id' => $price->id,
                'item_code_snapshot' => $price->plan->code,
                'description_snapshot' => $price->plan->name_ar.' - '.($price->billing_interval === PlanPrice::ANNUAL ? 'سنوي' : 'شهري'),
                'quantity' => 1,
                'unit_price_minor' => $subscriptionSubtotalMinor,
                'subtotal_minor' => $subscriptionSubtotalMinor,
                'discount_minor' => $discountMinor,
                'tax_rate_bps' => $taxRateBps,
                'tax_minor' => $subscriptionTaxMinor,
                'total_minor' => ($subscriptionSubtotalMinor - $discountMinor) + $subscriptionTaxMinor,
                'metadata' => [
                    'branch_quantity' => $quantity,
                    'included_branch_quantity' => $included,
                    'extra_branch_quantity' => $extraBranches,
                    'additional_branch_price_minor' => $price->additional_branch_price_minor,
                ],
                'sort_order' => 10,
            ],
        ];

        if ($setupFeeMinor > 0) {
            $lines[] = [
                'line_type' => InvoiceLine::TYPE_SETUP_FEE,
                'plan_id' => $price->plan_id,
                'plan_price_id' => $price->id,
                'item_code_snapshot' => $price->plan->code.'_setup',
                'description_snapshot' => 'رسوم التأسيس والربط - '.$price->plan->name_ar,
                'quantity' => 1,
                'unit_price_minor' => $setupFeeMinor,
                'subtotal_minor' => $setupFeeMinor,
                'discount_minor' => 0,
                'tax_rate_bps' => $taxRateBps,
                'tax_minor' => $setupTaxMinor,
                'total_minor' => $setupFeeMinor + $setupTaxMinor,
                'metadata' => null,
                'sort_order' => 20,
            ];
        }

        return [
            'quantity' => $quantity,
            'unit_price_minor' => (int) $price->amount_minor,
            'setup_fee_minor' => $setupFeeMinor,
            'subtotal_minor' => $subtotalMinor,
            'discount_minor' => $discountMinor,
            'tax_rate_bps' => $taxRateBps,
            'tax_minor' => $taxMinor,
            'total_minor' => $totalMinor,
            'lines' => $lines,
        ];
    }

    public function calculateOneTimeLine(array $data, int $sortOrder): array
    {
        $quantity = max(1, (int) ($data['quantity'] ?? 1));
        $unitMinor = Money::fromJod($data['unit_price_jod'])->minorUnits();
        $subtotalMinor = $unitMinor * $quantity;
        $discountMinor = filled($data['discount_jod'] ?? null)
            ? Money::fromJod($data['discount_jod'])->minorUnits()
            : 0;

        if ($discountMinor < 0 || $discountMinor > $subtotalMinor) {
            throw ValidationException::withMessages([
                'lines' => 'قيمة الخصم يجب أن تكون بين صفر وإجمالي السطر.',
            ]);
        }

        $taxRateBps = $data['tax_rate_bps'] ?? null;
        $taxMinor = self::taxMinor($subtotalMinor - $discountMinor, $taxRateBps);

        return [
            'line_type' => $data['line_type'] ?? InvoiceLine::TYPE_ONE_TIME_SERVICE,
            'service_id' => $data['service_id'] ?? null,
            'item_code_snapshot' => $data['item_code_snapshot'] ?? null,
            'description_snapshot' => $data['description'],
            'quantity' => $quantity,
            'unit_price_minor' => $unitMinor,
            'subtotal_minor' => $subtotalMinor,
            'discount_minor' => $discountMinor,
            'tax_rate_bps' => $taxRateBps,
            'tax_minor' => $taxMinor,
            'total_minor' => ($subtotalMinor - $discountMinor) + $taxMinor,
            'metadata' => null,
            'sort_order' => $sortOrder,
        ];
    }

    public static function taxMinor(int $taxableMinor, ?int $taxRateBps): int
    {
        if ($taxableMinor <= 0 || $taxRateBps === null || $taxRateBps <= 0) {
            return 0;
        }

        return intdiv(($taxableMinor * $taxRateBps) + 5000, 10000);
    }
}
