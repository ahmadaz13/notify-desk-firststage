<?php

namespace App\Services;

use App\Models\PaymentSchedule;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PaymentScheduleService
{
    protected SubscriptionPricingService $pricingService;

    public function __construct(SubscriptionPricingService $pricingService)
    {
        $this->pricingService = $pricingService;
    }

    /**
     * Generate or regenerate payment schedules for a subscription deterministically.
     *
     * @param Subscription $subscription
     * @param array $pricingResult Pricing calculation result
     * @param string|Carbon $startDate
     * @return array Collection of created PaymentSchedule records
     */
    public function generateSchedules(
        Subscription $subscription,
        array $pricingResult,
        string|Carbon $startDate
    ): array {
        $count = $pricingResult['installments_count'];
        $grandTotal = $pricingResult['grand_total'];
        $baseSubtotal = $pricingResult['base_subtotal'];
        $discountTotal = $pricingResult['discount_amount'];
        $setupFeeTotal = $pricingResult['setup_fee'];
        $taxTotal = $pricingResult['tax_amount'];
        $dueDay = $pricingResult['monthly_due_day'];

        $parsedStart = Carbon::parse($startDate);
        $schedules = [];

        // Equal distribution with penny/fils balancing on the last installment
        $basePerSeq = round($baseSubtotal / $count, SubscriptionPricingService::DEFAULT_PRECISION);
        $discountPerSeq = round($discountTotal / $count, SubscriptionPricingService::DEFAULT_PRECISION);
        $taxPerSeq = round($taxTotal / $count, SubscriptionPricingService::DEFAULT_PRECISION);
        $totalPerSeq = round($grandTotal / $count, SubscriptionPricingService::DEFAULT_PRECISION);

        // Track accumulated sum for exact penny balancing on final sequence
        $accumulatedSubtotal = 0.000;
        $accumulatedDiscount = 0.000;
        $accumulatedTax = 0.000;
        $accumulatedTotal = 0.000;

        for ($seq = 1; $seq <= $count; $seq++) {
            $isLast = ($seq === $count);

            // Compute exact date based on sequence and dueDay
            if ($pricingResult['billing_type'] === 'annual') {
                $dueDate = $parsedStart->copy();
            } else {
                if ($seq === 1) {
                    $dueDate = $parsedStart->copy();
                } else {
                    // Month advancement respecting selected monthly due day (1, 5, 15, 30)
                    $targetMonth = $parsedStart->copy()->addMonthsNoOverflow($seq - 1);
                    $daysInMonth = $targetMonth->daysInMonth;
                    $clampedDay = min($dueDay, $daysInMonth);
                    $dueDate = $targetMonth->day($clampedDay);
                }
            }

            // Setup fee is fully applied on the 1st installment
            $seqSetupFee = ($seq === 1) ? $setupFeeTotal : 0.000;

            if (!$isLast) {
                $seqSubtotal = $basePerSeq;
                $seqDiscount = $discountPerSeq;
                $seqTax = $taxPerSeq;
                $seqTotal = round($basePerSeq - $seqDiscount + $seqSetupFee + $seqTax, SubscriptionPricingService::DEFAULT_PRECISION);

                $accumulatedSubtotal += $seqSubtotal;
                $accumulatedDiscount += $seqDiscount;
                $accumulatedTax += $seqTax;
                $accumulatedTotal += $seqTotal;
            } else {
                // Final balance to ensure 100% exact match with grand total
                $seqSubtotal = round($baseSubtotal - $accumulatedSubtotal, SubscriptionPricingService::DEFAULT_PRECISION);
                $seqDiscount = round($discountTotal - $accumulatedDiscount, SubscriptionPricingService::DEFAULT_PRECISION);
                $seqTax = round($taxTotal - $accumulatedTax, SubscriptionPricingService::DEFAULT_PRECISION);
                $seqTotal = round($grandTotal - $accumulatedTotal, SubscriptionPricingService::DEFAULT_PRECISION);
            }

            $schedule = PaymentSchedule::create([
                'subscription_id' => $subscription->id,
                'sequence' => $seq,
                'amount_due' => $seqTotal,
                'subtotal' => $seqSubtotal,
                'discount_amount' => $seqDiscount,
                'setup_fee_amount' => $seqSetupFee,
                'tax_amount' => $seqTax,
                'total_amount' => $seqTotal,
                'paid_amount' => 0.000,
                'payment_method' => null,
                'due_date' => $dueDate->toDateString(),
                'status' => ($seq === 1 && $dueDate->isPast()) ? 'due' : ($seq === 1 ? 'due' : 'upcoming'),
                'reminder_sent_at' => null,
            ]);

            $schedules[] = $schedule;
        }

        return $schedules;
    }

    /**
     * Cancel a subscription with safe historical preservation and future reminder suppression.
     */
    public function cancelSubscription(
        Subscription $subscription,
        string $reason,
        int $cancelledByUserId,
        ?string $effectiveDate = null
    ): Subscription {
        return DB::transaction(function () use ($subscription, $reason, $cancelledByUserId, $effectiveDate) {
            $cancelTime = $effectiveDate ? Carbon::parse($effectiveDate) : now();

            $subscription->update([
                'status' => 'cancelled',
                'cancelled_at' => $cancelTime,
                'cancellation_reason' => $reason,
                'cancelled_by' => $cancelledByUserId,
            ]);

            // Suppress future unpaid obligations
            PaymentSchedule::where('subscription_id', $subscription->id)
                ->whereIn('status', ['upcoming', 'due'])
                ->where('due_date', '>=', $cancelTime->toDateString())
                ->update([
                    'status' => 'cancelled',
                    'updated_at' => now(),
                ]);

            DB::table('activity_logs')->insert([
                'client_id' => $subscription->client_id,
                'user_id' => $cancelledByUserId,
                'type' => 'subscription_cancelled',
                'description' => "تم إلغاء الاشتراك #{$subscription->id}: {$reason}",
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $subscription->fresh();
        });
    }

    /**
     * Renew a subscription safely by creating a new subscription version without mutating history.
     */
    public function renewSubscription(
        Subscription $oldSubscription,
        int $actorUserId,
        ?array $overridePricingParams = null
    ): Subscription {
        return DB::transaction(function () use ($oldSubscription, $actorUserId, $overridePricingParams) {
            // New start date is the renewal date or today
            $newStartDate = $oldSubscription->renewal_date
                ? Carbon::parse($oldSubscription->renewal_date)->toDateString()
                : now()->toDateString();

            $newRenewalDate = Carbon::parse($newStartDate)->addYear()->toDateString();

            // Pricing parameters: reuse old snapshot rates unless overridden
            $baseSubtotal = $overridePricingParams['base_subtotal'] ?? $oldSubscription->base_subtotal ?? $oldSubscription->total_price;
            $billingType = $overridePricingParams['billing_type'] ?? $oldSubscription->billing_type;
            $setupFee = $overridePricingParams['setup_fee'] ?? 0.000; // renewal usually has no setup fee
            $annualDiscount = $overridePricingParams['annual_discount_percentage'] ?? $oldSubscription->annual_discount_percentage;
            $taxPercentage = $overridePricingParams['tax_percentage'] ?? $oldSubscription->tax_percentage;
            $monthlyDueDay = $overridePricingParams['monthly_due_day'] ?? $oldSubscription->monthly_due_day;

            $pricing = $this->pricingService->calculate(
                $baseSubtotal,
                $billingType,
                $setupFee,
                $annualDiscount,
                $taxPercentage,
                $oldSubscription->installments_count,
                $monthlyDueDay
            );

            // Mark old subscription as completed/renewed
            $oldSubscription->update([
                'status' => 'completed',
                'updated_at' => now(),
            ]);

            // Create new version
            $newSubscription = Subscription::create([
                'client_id' => $oldSubscription->client_id,
                'user_id' => $actorUserId,
                'billing_type' => $billingType,
                'total_price' => $pricing['grand_total'],
                'setup_fee' => $pricing['setup_fee'],
                'base_subtotal' => $pricing['base_subtotal'],
                'annual_discount_percentage' => $pricing['annual_discount_percentage'],
                'discount_amount' => $pricing['discount_amount'],
                'tax_percentage' => $pricing['tax_percentage'],
                'tax_amount' => $pricing['tax_amount'],
                'grand_total' => $pricing['grand_total'],
                'monthly_due_day' => $pricing['monthly_due_day'],
                'start_date' => $newStartDate,
                'renewal_date' => $newRenewalDate,
                'installments_count' => $pricing['installments_count'],
                'status' => 'active',
                'version' => ($oldSubscription->version ?? 1) + 1,
                'previous_subscription_id' => $oldSubscription->id,
            ]);

            // Copy service catalog selections with price snapshots
            $oldServices = DB::table('subscription_service')
                ->where('subscription_id', $oldSubscription->id)
                ->get();

            foreach ($oldServices as $svc) {
                DB::table('subscription_service')->insert([
                    'subscription_id' => $newSubscription->id,
                    'service_id' => $svc->service_id,
                    'service_key' => $svc->service_key,
                    'service_name_ar' => $svc->service_name_ar,
                    'service_name_en' => $svc->service_name_en,
                    'price_contribution' => $svc->price_contribution,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Generate payment schedules for new subscription
            $this->generateSchedules($newSubscription, $pricing, $newStartDate);

            DB::table('activity_logs')->insert([
                'client_id' => $oldSubscription->client_id,
                'user_id' => $actorUserId,
                'type' => 'subscription_renewed',
                'description' => "تم تجديد الاشتراك بإصدار جديد #{$newSubscription->id} (النسخة {$newSubscription->version})",
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $newSubscription;
        });
    }
}
