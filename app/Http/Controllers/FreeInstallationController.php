<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\User;
use App\Services\FreeInstallationService;
use App\Support\AppointmentTypes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
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
            'attendees.*' => [
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->where('is_active', true)
                    ->where(fn ($inner) => $inner->whereNull('role')->orWhereIn('role', User::activeInternalRoles()))),
            ],
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
            'installed_at' => 'nullable|date',
            'installed_by' => [
                'nullable',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->where('is_active', true)
                    ->where(fn ($inner) => $inner->whereNull('role')->orWhereIn('role', User::activeInternalRoles()))),
            ],
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

        // Authoritative resolution of appointment_id when not explicitly provided
        if (empty($data['appointment_id'])) {
            $eligibleAppointments = Appointment::where('client_id', $client->id)
                ->where('appointment_type', AppointmentTypes::INSTALLATION)
                ->whereIn('status', AppointmentTypes::activeStatuses())
                ->get();

            if ($eligibleAppointments->count() === 1) {
                $data['appointment_id'] = $eligibleAppointments->first()->id;
            } elseif ($eligibleAppointments->count() > 1) {
                throw ValidationException::withMessages([
                    'appointment_id' => 'توجد عدة مواعيد تركيب نشطة، يرجى تحديد موعد التركيب المراد إكماله.',
                ]);
            }
        }

        $data['installed_at'] = !empty($data['installed_at']) ? $data['installed_at'] : now()->toDateTimeString();
        $data['installed_by'] = $data['installed_by'] ?? $request->user()->id;

        $service->completeInstallation($client, $request->user(), $data);

        return back()->with('success', 'تم تسجيل اكتمال التركيب المجاني.');
    }
}
