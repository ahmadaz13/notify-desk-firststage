<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Services\FreeInstallationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class FreeInstallationController extends Controller
{
    public function schedule(Request $request, Client $client, FreeInstallationService $service): RedirectResponse
    {
        Gate::authorize('update', $client);

        $data = $request->validate([
            'appointment_date' => 'required|date',
            'appointment_time' => 'required',
            'attendees' => 'nullable|array',
            'attendees.*' => 'exists:users,id',
            'location' => 'nullable|string|max:255',
            'branch_name' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $service->scheduleInstallation($client, $request->user(), $data);

        return back()->with('success', 'تم جدولة التركيب المجاني.');
    }

    public function complete(Request $request, Client $client, FreeInstallationService $service): RedirectResponse
    {
        Gate::authorize('update', $client);

        $data = $request->validate([
            'appointment_id' => 'nullable|exists:appointments,id',
            'installed_at' => 'required|date',
            'installed_by' => 'nullable|exists:users,id',
            'branch_name' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'service_ids' => 'nullable|array',
            'service_ids.*' => 'exists:services,id',
            'custom_item_names' => 'nullable|string',
            'next_follow_up_date' => 'nullable|date',
            'next_action' => 'nullable|string|max:255',
            'follow_up_notes' => 'nullable|string',
            'no_follow_up' => 'nullable|boolean',
            'no_follow_up_reason' => 'nullable|string|max:255',
        ]);

        if (empty($data['next_follow_up_date']) && empty($data['no_follow_up'])) {
            throw ValidationException::withMessages([
                'next_follow_up_date' => 'اختر تاريخ متابعة بعد التركيب أو سجل سبب عدم المتابعة.',
            ]);
        }

        if (!empty($data['no_follow_up']) && empty($data['no_follow_up_reason'])) {
            throw ValidationException::withMessages([
                'no_follow_up_reason' => 'سبب عدم جدولة متابعة مطلوب.',
            ]);
        }

        $data['installed_by'] = $data['installed_by'] ?? $request->user()->id;
        if (!empty($data['no_follow_up'])) {
            $data['next_follow_up_date'] = null;
        }

        $service->completeInstallation($client, $request->user(), $data);

        return back()->with('success', 'تم تسجيل اكتمال التركيب المجاني.');
    }
}
