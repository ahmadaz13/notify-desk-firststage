<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\User;
use App\Services\FreeInstallationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AppointmentController extends Controller
{
    public function reschedule(Request $request, Appointment $appointment, FreeInstallationService $service): RedirectResponse
    {
        Gate::authorize('update', $appointment->client);

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

        $service->rescheduleAppointment($appointment, $request->user(), $data);

        return back()->with('success', 'تمت إعادة جدولة الموعد.');
    }
}
