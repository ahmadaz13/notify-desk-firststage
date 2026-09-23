<?php

namespace App\Http\Controllers;

use App\Services\SubscriptionBillingService;
use App\Support\Permissions;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SubscriptionBillingController extends Controller
{
    public function index(SubscriptionBillingService $billing): View
    {
        Gate::authorize(Permissions::RUN_SUBSCRIPTION_BILLING);

        $snapshot = $billing->operationsSnapshot();

        return view('subscription-billing.index', compact('snapshot'));
    }

    public function generateRenewals(Request $request, SubscriptionBillingService $billing): RedirectResponse
    {
        Gate::authorize(Permissions::RUN_SUBSCRIPTION_BILLING);

        $validated = $request->validate([
            'through' => 'nullable|date',
            'dry_run' => 'nullable|boolean',
        ]);
        $counts = $billing->generateRenewals(
            filled($validated['through'] ?? null) ? Carbon::parse($validated['through']) : null,
            $request->boolean('dry_run'),
            $request->user()->id
        );

        return back()->with('success', 'تم تشغيل محرك تجديد الاشتراكات: '.json_encode($counts, JSON_UNESCAPED_UNICODE));
    }

    public function backfillPeriods(Request $request, SubscriptionBillingService $billing): RedirectResponse
    {
        Gate::authorize(Permissions::RESOLVE_SUBSCRIPTION_BILLING_REVIEWS);

        $counts = $billing->backfillInitialPeriods($request->boolean('dry_run'));

        return back()->with('success', 'تم تشغيل backfill لفترات الاشتراك: '.json_encode($counts, JSON_UNESCAPED_UNICODE));
    }
}
