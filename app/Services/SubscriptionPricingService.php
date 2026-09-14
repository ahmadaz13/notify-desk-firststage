<?php

namespace App\Services;

use App\Models\Setting;
use InvalidArgumentException;

class SubscriptionPricingService
{
    public const DEFAULT_PRECISION = 3;

    /**
     * Calculate deterministic subscription pricing with Jordanian Dinar (JOD) 3-decimal precision.
     *
     * @param float|string $baseSubtotal Base subtotal or service contributions total
     * @param string $billingType 'monthly' | 'annual' | 'installment'
     * @param float|string|null $setupFee One-time setup fee
     * @param float|string|null $customAnnualDiscountPercentage Explicit discount percentage or null for global setting
     * @param float|string|null $customTaxPercentage Explicit tax percentage or null for global setting
     * @param int|null $installmentsCount Count of installments (2 to 12) if billingType is 'installment'
     * @param int|null $monthlyDueDay 1, 5, 15, or 30
     * @return array
     */
    public function calculate(
        float|string $baseSubtotal,
        string $billingType,
        float|string|null $setupFee = 0.000,
        float|string|null $customAnnualDiscountPercentage = null,
        float|string|null $customTaxPercentage = null,
        ?int $installmentsCount = null,
        ?int $monthlyDueDay = null
    ): array {
        $subtotal = round((float) $baseSubtotal, self::DEFAULT_PRECISION);
        if ($subtotal < 0) {
            throw new InvalidArgumentException('Base subtotal cannot be negative.');
        }

        $fee = round((float) ($setupFee ?? 0.000), self::DEFAULT_PRECISION);
        if ($fee < 0) {
            throw new InvalidArgumentException('Setup fee cannot be negative.');
        }

        // 1. Discount calculation
        // Annual billing applies annual_discount_percentage
        $appliedDiscountRate = 0.0;
        $discountAmount = 0.000;

        if ($billingType === 'annual') {
            $appliedDiscountRate = $customAnnualDiscountPercentage !== null
                ? (float) $customAnnualDiscountPercentage
                : (float) Setting::get('annual_discount_percentage', 10.0);

            if ($appliedDiscountRate > 0) {
                // Discount is applied on base subtotal
                $discountAmount = round(($subtotal * $appliedDiscountRate) / 100.0, self::DEFAULT_PRECISION);
            }
        }

        // Subtotal after discount
        $discountedSubtotal = max(0.000, round($subtotal - $discountAmount, self::DEFAULT_PRECISION));

        // Taxable base includes discounted subtotal + setup fee
        $taxableBase = round($discountedSubtotal + $fee, self::DEFAULT_PRECISION);

        // 2. Sales tax calculation (Default 16.0% in Jordan)
        $appliedTaxRate = $customTaxPercentage !== null
            ? (float) $customTaxPercentage
            : (float) Setting::get('sales_tax_percentage', 16.0);

        $taxAmount = 0.000;
        if ($appliedTaxRate > 0) {
            $taxAmount = round(($taxableBase * $appliedTaxRate) / 100.0, self::DEFAULT_PRECISION);
        }

        // 3. Grand total
        $grandTotal = round($taxableBase + $taxAmount, self::DEFAULT_PRECISION);

        // 4. Monthly due day (1, 5, 15, 30)
        $allowedDueDays = [1, 5, 15, 30];
        $selectedDueDay = $monthlyDueDay ?? (int) Setting::get('monthly_due_day', 1);
        if (!in_array($selectedDueDay, $allowedDueDays, true)) {
            $selectedDueDay = 1;
        }

        // 5. Installments count
        $count = match ($billingType) {
            'annual' => 1,
            'installment' => max(2, min(12, $installmentsCount ?: 3)),
            default => 12, // monthly
        };

        return [
            'currency' => 'JOD',
            'currency_display_ar' => 'د.أ',
            'precision' => self::DEFAULT_PRECISION,
            'billing_type' => $billingType,
            'base_subtotal' => $subtotal,
            'annual_discount_percentage' => $appliedDiscountRate,
            'discount_amount' => $discountAmount,
            'discounted_subtotal' => $discountedSubtotal,
            'setup_fee' => $fee,
            'taxable_base' => $taxableBase,
            'tax_percentage' => $appliedTaxRate,
            'tax_amount' => $taxAmount,
            'grand_total' => $grandTotal,
            'installments_count' => $count,
            'monthly_due_day' => $selectedDueDay,
            'rounding_rule' => 'ROUND_HALF_UP_3_DECIMALS',
        ];
    }
}
