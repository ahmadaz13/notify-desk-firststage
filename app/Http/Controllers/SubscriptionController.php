<?php

namespace App\Http\Controllers;

use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Services\SubscriptionBillingService;
use App\Support\FinancialPermissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SubscriptionController extends Controller
{
    public function schedulePlanChange(Request $request, Subscription $subscription, SubscriptionBillingService $billing): RedirectResponse
    {
        Gate::authorize(FinancialPermissions::MANAGE_SUBSCRIPTION_LIFECYCLE);
        Gate::authorize('update', $subscription->client);

        $validated = $request->validate([
            'plan_price_id' => 'required|exists:plan_prices,id',
            'quantity' => 'required|integer|min:1|max:999',
        ]);

        $price = PlanPrice::with('plan')->findOrFail((int) $validated['plan_price_id']);
        $billing->schedulePlanChange($subscription, $price, (int) $validated['quantity'], $request->user()->id);

        return back()->with('success', 'تمت جدولة تغيير الخطة عند حد فترة الفوترة القادمة بدون تعديل الفترة الحالية.');
    }

    public function scheduleCancellation(Request $request, Subscription $subscription, SubscriptionBillingService $billing): RedirectResponse
    {
        Gate::authorize(FinancialPermissions::MANAGE_SUBSCRIPTION_LIFECYCLE);
        Gate::authorize('update', $subscription->client);

        $validated = $request->validate([
            'cancellation_reason' => 'nullable|string|max:1000',
        ]);

        $billing->scheduleCancellation($subscription, $validated['cancellation_reason'] ?? null, $request->user()->id);

        return back()->with('success', 'تمت جدولة إلغاء الاشتراك في نهاية الفترة الحالية.');
    }

    public function undoCancellation(Request $request, Subscription $subscription, SubscriptionBillingService $billing): RedirectResponse
    {
        Gate::authorize(FinancialPermissions::MANAGE_SUBSCRIPTION_LIFECYCLE);
        Gate::authorize('update', $subscription->client);

        $billing->undoCancellation($subscription, $request->user()->id);

        return back()->with('success', 'تم إلغاء جدولة الإلغاء مع الحفاظ على سجل الحدث السابق.');
    }

    public function reactivate(Request $request, Subscription $subscription, SubscriptionBillingService $billing): RedirectResponse
    {
        Gate::authorize(FinancialPermissions::MANAGE_SUBSCRIPTION_LIFECYCLE);
        Gate::authorize('update', $subscription->client);

        $validated = $request->validate([
            'plan_price_id' => 'required|exists:plan_prices,id',
            'quantity' => 'required|integer|min:1|max:999',
            'start_date' => 'required|date',
        ]);

        $price = PlanPrice::with('plan')->findOrFail((int) $validated['plan_price_id']);
        $billing->reactivate($subscription, $price, $validated, $request->user()->id);

        return back()->with('success', 'تمت إعادة تفعيل الاشتراك بفترة وفاتورة جديدتين دون تعديل التاريخ السابق.');
    }
}
