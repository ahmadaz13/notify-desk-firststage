<?php

namespace App\Http\Controllers;

use App\Models\ClientReviewItem;
use App\Services\ClientOperationalWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ClientReviewItemController extends Controller
{
    public function resolve(Request $request, ClientReviewItem $reviewItem, ClientOperationalWorkflowService $workflow): RedirectResponse
    {
        Gate::authorize('update', $reviewItem->client);

        $validated = $request->validate([
            'resolution_note' => 'nullable|string|max:1000',
        ]);

        $workflow->resolveReviewItem($reviewItem, $request->user(), ClientReviewItem::STATUS_RESOLVED, $validated['resolution_note'] ?? null);

        return back()->with('success', 'تم حل بند المراجعة.');
    }

    public function dismiss(Request $request, ClientReviewItem $reviewItem, ClientOperationalWorkflowService $workflow): RedirectResponse
    {
        Gate::authorize('update', $reviewItem->client);

        $validated = $request->validate([
            'resolution_note' => 'nullable|string|max:1000',
        ]);

        $workflow->resolveReviewItem($reviewItem, $request->user(), ClientReviewItem::STATUS_DISMISSED, $validated['resolution_note'] ?? null);

        return back()->with('success', 'تم تجاهل بند المراجعة.');
    }
}
