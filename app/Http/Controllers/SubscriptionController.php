<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Services\SubscriptionBillingService;
use App\Support\FinancialPermissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SubscriptionController extends Controller
{
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

}
