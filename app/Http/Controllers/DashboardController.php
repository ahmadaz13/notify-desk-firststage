<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\DailyNote;
use App\Models\User;
use App\Services\FreeInstallationService;
use App\Services\UnifiedOperationalWorkProjection;
use App\Support\AppointmentTypes;
use App\ViewModels\TodayViewModel;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DashboardController extends Controller
{
    public function __construct(
        protected UnifiedOperationalWorkProjection $unifiedWorkProjection
    ) {}

    public function index()
    {
        $user = auth()->user();
        $businessNow = Carbon::now('Asia/Amman');
        $dailyNote = DailyNote::where('user_id', $user->id)->whereDate('date', $businessNow->toDateString())->first();

        $requestedMode = request()->query('mode', 'daily');
        $mode = in_array($requestedMode, ['daily', 'work'], true) ? $requestedMode : 'daily';
        $currentMode = $mode;

        $requestedScope = request()->query('scope', 'all');
        $scope = in_array($requestedScope, ['all', 'my'], true) ? $requestedScope : 'all';

        $todayProjection = $this->unifiedWorkProjection->today($user, $businessNow, $scope);
        $requestedFilter = request()->query('filter', UnifiedOperationalWorkProjection::FILTER_ALL);
        $workProjection = $this->unifiedWorkProjection->work($user, $requestedFilter, $businessNow, $scope);

        $todayViewModel = TodayViewModel::make(
            $todayProjection,
            $workProjection
        );

        return view('dashboard', compact(
            'dailyNote', 'currentMode', 'scope', 'todayViewModel',
            'todayProjection', 'workProjection'
        ));
    }

    public function storeAppointment(Request $request)
    {
        $data = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'appointment_date' => 'required|date',
            'appointment_time' => 'required',
            'appointment_type' => 'required|string',
            'location' => 'nullable|string|max:255',
            'branch_name' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'attendees' => 'nullable|array',
            'attendees.*' => [
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->where('is_active', true)
                    ->where(fn ($inner) => $inner->whereNull('role')->orWhereIn('role', User::activeInternalRoles()))),
            ],
        ]);
        $clientModel = \App\Models\Client::findOrFail($data['client_id']);
        \Illuminate\Support\Facades\Gate::authorize('update', $clientModel);

        DB::transaction(function () use ($data) {
            $appointment = Appointment::create([
                'client_id' => $data['client_id'],
                'appointment_date' => $data['appointment_date'],
                'appointment_time' => $data['appointment_time'],
                'appointment_type' => $data['appointment_type'],
                'location' => $data['location'] ?? null,
                'branch_name' => $data['branch_name'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => 'scheduled',
            ]);

            $attendees = !empty($data['attendees']) ? $data['attendees'] : [auth()->id()];
            $appointment->users()->sync($attendees);

            $this->log($data['client_id'], 'appointment_created', 'تم جدولة موعد جديد');
        });

        return back()->with('success', 'تم جدولة الموعد.');
    }

    public function updateAppointment(Request $request, int $appointment, FreeInstallationService $freeInstallationService)
    {
        $data = $request->validate([
            'status' => 'required|in:scheduled,confirmed,rescheduled,cancelled,no_show,completed',
            'next_stage' => 'nullable|string',
        ]);
        $item = Appointment::with('client')->findOrFail($appointment);
        $clientModel = $item->client;
        \Illuminate\Support\Facades\Gate::authorize('update', $clientModel);

        if ($item->appointment_type === AppointmentTypes::INSTALLATION && $data['status'] === 'cancelled') {
            $freeInstallationService->cancelInstallationAppointment($item, $request->user(), $data['next_stage'] ?? null);

            return back()->with('success', 'تم تحديث حالة الموعد.');
        }

        DB::transaction(function () use ($data, $item) { DB::table('appointments')->where('id', $item->id)->update(['status' => $data['status'], 'updated_at' => now()]); $this->log($item->client_id, 'appointment_'.$data['status'], 'تم تحديث حالة الموعد إلى '.$data['status']); });
        return back()->with('success', 'تم تحديث حالة الموعد.');
    }

    private function log(int $clientId, string $type, string $description): void
    {
        DB::table('activity_logs')->insert(['client_id' => $clientId, 'user_id' => auth()->id(), 'type' => $type, 'description' => $description, 'created_at' => now(), 'updated_at' => now()]);
    }

}
