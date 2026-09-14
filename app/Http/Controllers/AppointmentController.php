<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Services\FreeInstallationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AppointmentController extends Controller
{
    public function reschedule(Request $request, Appointment $appointment, FreeInstallationService $service): RedirectResponse
    {
        Gate::authorize('update', $appointment->client);

        $data = $request->validate([
            'appointment_date' => 'required|date',
            'appointment_time' => 'required',
            'attendees' => 'nullable|array',
            'attendees.*' => 'exists:users,id',
            'location' => 'nullable|string|max:255',
            'branch_name' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $service->rescheduleAppointment($appointment, $request->user(), $data);

        return back()->with('success', 'تمت إعادة جدولة الموعد.');
    }
}
