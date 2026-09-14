<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Services\PaymentScheduleService;
use App\Support\FinancialPermissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SubscriptionController extends Controller
{
    protected PaymentScheduleService $scheduleService;

    public function __construct(PaymentScheduleService $scheduleService)
    {
        $this->scheduleService = $scheduleService;
    }

    /**
     * Cancel an active subscription.
     */
    public function cancel(Request $request, int $id): RedirectResponse
    {
        Gate::authorize(FinancialPermissions::MANAGE_SUBSCRIPTION_BILLING);

        $subscription = Subscription::findOrFail($id);
        Gate::authorize('update', $subscription->client);

        $validated = $request->validate([
            'cancellation_reason' => 'required|string|max:255',
            'effective_date' => 'nullable|date',
        ]);

        $this->scheduleService->cancelSubscription(
            $subscription,
            $validated['cancellation_reason'],
            auth()->id(),
            $validated['effective_date'] ?? null
        );

        return back()->with('success', 'تم إلغاء الاشتراك وإيقاف المطالبات والتنبيهات المستقبلية.');
    }

    /**
     * Renew a subscription.
     */
    public function renew(Request $request, int $id): RedirectResponse
    {
        Gate::authorize(FinancialPermissions::MANAGE_SUBSCRIPTION_BILLING);

        $subscription = Subscription::findOrFail($id);
        Gate::authorize('update', $subscription->client);

        $validated = $request->validate([
            'billing_type' => 'nullable|in:monthly,annual,installment',
            'base_subtotal' => 'nullable|numeric|min:0.01',
        ]);

        $newSubscription = $this->scheduleService->renewSubscription(
            $subscription,
            auth()->id(),
            $validated
        );

        return back()->with('success', "تم تجديد الاشتراك بنجاح بالإصدار الجديد #{$newSubscription->id} وتوليد جدول الأقساط.");
    }
}
