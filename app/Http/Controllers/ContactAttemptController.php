<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\User;
use App\Services\ClientOperationalWorkflowService;
use App\Support\ClientLifecycle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ContactAttemptController extends Controller
{
    public function store(Request $request, Client $client, ClientOperationalWorkflowService $workflow): RedirectResponse
    {
        Gate::authorize('update', $client);

        $validated = $request->validate([
            'method' => 'required|string|max:80',
            'result' => ['required', Rule::in(ClientLifecycle::CONTACT_OUTCOMES)],
            'note' => 'nullable|string',
            'next_action' => 'nullable|string|max:255',
            'next_follow_up_date' => 'nullable|date',
            'follow_up_date_time' => 'nullable|date',
            'appointment_date' => 'nullable|date',
            'appointment_time' => 'nullable',
            'appointment_type' => 'nullable|string|max:80',
            'location' => 'nullable|string|max:255',
            'branch_name' => 'nullable|string|max:255',
            'appointment_notes' => 'nullable|string',
            'attendees' => 'nullable|array',
            'attendees.*' => [
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->where('is_active', true)
                    ->where(fn ($inner) => $inner->whereNull('role')->orWhereIn('role', User::activeInternalRoles()))),
            ],
            'close_client' => 'nullable|boolean',
            'closed_reason' => 'nullable|string|max:255',
        ]);

        $workflow->recordContactOutcome($client, $request->user(), $validated);

        return back()->with('success', 'تم تسجيل نتيجة التواصل بنجاح.');
    }
}
