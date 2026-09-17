<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Services\ClientOperationalWorkflowService;
use App\Support\ClientLifecycle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ClientStageController extends Controller
{
    public function update(Request $request, Client $client, ClientOperationalWorkflowService $workflow): RedirectResponse
    {
        Gate::authorize('update', $client);

        $validated = $request->validate([
            'stage' => ['required', Rule::in(ClientLifecycle::STAGES)],
            'closed_reason' => 'nullable|string|max:255',
            'closed_reason_code' => 'nullable|string|max:80',
        ]);

        $workflow->transition($client, $validated['stage'], $request->user(), [
            'closed_reason_code' => $validated['closed_reason_code'] ?? 'other',
            'closed_reason_note' => $validated['closed_reason'] ?? null,
        ]);

        return back()->with('success', 'تم تحديث مرحلة العميل بنجاح.');
    }

    public function reopen(Request $request, Client $client, ClientOperationalWorkflowService $workflow): RedirectResponse
    {
        Gate::authorize('update', $client);

        $validated = $request->validate([
            'stage' => ['nullable', Rule::in([
                ClientLifecycle::PROSPECT,
                ClientLifecycle::CONTACTING,
                ClientLifecycle::APPOINTMENT,
                ClientLifecycle::DECISION_PENDING,
            ])],
            'reason' => 'required|string|max:1000',
        ]);

        $workflow->reopen($client, $request->user(), $validated['stage'] ?? ClientLifecycle::PROSPECT, $validated['reason']);

        return back()->with('success', 'تمت إعادة فتح ملف العميل.');
    }

    public function close(Request $request, Client $client, ClientOperationalWorkflowService $workflow): RedirectResponse
    {
        Gate::authorize('update', $client);

        $validated = $request->validate([
            'closed_reason_code' => ['required', Rule::in(ClientOperationalWorkflowService::CLOSED_REASONS)],
            'closed_reason' => 'nullable|string|max:1000',
        ]);

        $workflow->closeClient(
            $client,
            $request->user(),
            $validated['closed_reason_code'],
            $validated['closed_reason'] ?? null
        );

        return back()->with('success', 'تم إغلاق ملف العميل مع الحفاظ على السجل.');
    }
}
