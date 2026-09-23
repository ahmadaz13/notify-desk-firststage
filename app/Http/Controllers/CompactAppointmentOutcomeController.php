<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\ClientReviewItem;
use App\Services\ClientOperationalWorkflowService;
use App\Services\FreeInstallationService;
use App\Services\MeetingOutcomeService;
use App\Support\AppointmentTypes;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CompactAppointmentOutcomeController extends Controller
{
    public function store(
        Request $request,
        Appointment $appointment,
        MeetingOutcomeService $outcomeService,
        FreeInstallationService $installationService,
        ClientOperationalWorkflowService $workflow
    ): RedirectResponse {
        Gate::authorize('update', $appointment->client);

        $data = $request->validate([
            'first_decision' => 'required|in:attended,no_show,reschedule,cancelled',
            'attended_choice' => 'nullable|in:installation,follow_up,start_subscription,not_interested',
            'installation_date' => 'nullable|date',
            'installation_time' => 'nullable',
            'follow_up_date' => 'nullable|date',
            'follow_up_time' => 'nullable',
            'reschedule_date' => 'nullable|date',
            'reschedule_time' => 'nullable',
            'reason' => 'nullable|string',
            'note' => 'nullable|string',
        ]);

        $actor = $request->user();
        $client = $appointment->client;
        $decision = $data['first_decision'];

        if ($decision === 'no_show') {
            $outcomeService->recordOutcome($appointment->id, $actor->id, [
                'attendance_status' => 'no_show',
                'interest_level' => 'none',
                'next_action' => 'معاودة التواصل بعد تعذر الحضور',
                'meeting_notes' => $data['note'] ?? 'لم يحضر العميل الموعد',
            ]);

            return redirect()->route('clients.show', $client->id)
                ->with('success', 'تم تسجيل عدم حضور العميل وحفظ السجل بنجاح.');
        }

        if ($decision === 'cancelled') {
            DB::transaction(function () use ($appointment, $client, $actor, $data) {
                $appointment->update([
                    'status' => 'cancelled',
                    'updated_at' => now(),
                ]);

                DB::table('activity_logs')->insert([
                    'client_id' => $client->id,
                    'user_id' => $actor->id,
                    'type' => 'appointment_cancelled',
                    'description' => 'تم إلغاء الموعد: ' . ($data['note'] ?? 'تم الإلغاء'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

            return redirect()->route('clients.show', $client->id)
                ->with('success', 'تم إلغاء الموعد مع الحفاظ على السجل.');
        }

        if ($decision === 'reschedule') {
            if (empty($data['reschedule_date']) || empty($data['reschedule_time'])) {
                throw ValidationException::withMessages([
                    'reschedule_date' => 'حدد تاريخ ووقت إعادة الجدولة.',
                ]);
            }

            $installationService->rescheduleAppointment($appointment, $actor, [
                'appointment_date' => $data['reschedule_date'],
                'appointment_time' => $data['reschedule_time'],
                'notes' => $data['note'] ?? $appointment->notes,
            ]);

            return redirect()->route('clients.show', $client->id)
                ->with('success', 'تمت إعادة جدولة الموعد بنجاح.');
        }

        // Attended branch
        $choice = $data['attended_choice'] ?? null;
        if (!$choice) {
            throw ValidationException::withMessages([
                'attended_choice' => 'اختر الخطوة التالية بعد حضور الموعد.',
            ]);
        }

        if ($choice === 'installation') {
            if (empty($data['installation_date']) || empty($data['installation_time'])) {
                throw ValidationException::withMessages([
                    'installation_date' => 'حدد تاريخ ووقت التركيب المجاني.',
                ]);
            }

            $outcomeService->recordOutcome($appointment->id, $actor->id, [
                'attendance_status' => 'attended',
                'outcome_result' => 'installation_scheduled',
                'installation_appointment_date' => $data['installation_date'],
                'installation_appointment_time' => $data['installation_time'],
                'interest_level' => 'high',
                'next_action' => 'تركيب مجاني',
                'meeting_notes' => $data['note'] ?? null,
            ]);

            return redirect()->route('clients.show', $client->id)
                ->with('success', 'تم تسجيل حضور الموعد وجدولة التركيب المجاني بنجاح.');
        }

        if ($choice === 'follow_up') {
            if (empty($data['follow_up_date'])) {
                throw ValidationException::withMessages([
                    'follow_up_date' => 'حدد تاريخ المتابعة.',
                ]);
            }

            $outcomeService->recordOutcome($appointment->id, $actor->id, [
                'attendance_status' => 'attended',
                'outcome_result' => 'follow_up_required',
                'next_follow_up_date' => $data['follow_up_date'],
                'interest_level' => 'medium',
                'next_action' => 'متابعة لاحقة بعد الموعد',
                'meeting_notes' => $data['note'] ?? null,
            ]);

            return redirect()->route('clients.show', $client->id)
                ->with('success', 'تم تسجيل حضور الموعد وجدولة المتابعة بنجاح.');
        }

        if ($choice === 'start_subscription') {
            // Complete appointment, transition to decision_pending. Never auto-subscribe.
            $outcomeService->recordOutcome($appointment->id, $actor->id, [
                'attendance_status' => 'attended',
                'outcome_result' => 'decision_pending',
                'interest_level' => 'high',
                'next_action' => 'بدء الاشتراك',
                'meeting_notes' => $data['note'] ?? null,
            ]);

            return redirect()->route('clients.show', ['client' => $client->id, '#collapsible-management'])
                ->with('success', 'تم تسجيل حضور الموعد والعميل جاهز لبدء الاشتراك.');
        }

        if ($choice === 'not_interested') {
            $reason = trim((string) ($data['reason'] ?? ($data['note'] ?? '')));
            if ($reason === '') {
                throw ValidationException::withMessages([
                    'reason' => 'اكتب سبب عدم الاهتمام للمراجعة.',
                ]);
            }

            DB::transaction(function () use ($appointment, $actor, $client, $reason, $outcomeService, $workflow) {
                $outcomeService->recordOutcome($appointment->id, $actor->id, [
                    'attendance_status' => 'attended',
                    'interest_level' => 'none',
                    'next_action' => 'مراجعة عدم الاهتمام',
                    'meeting_notes' => $reason,
                ]);

                $workflow->createReviewItem(
                    $client,
                    $actor,
                    ClientReviewItem::TYPE_NOT_INTERESTED,
                    $reason
                );
            });

            return redirect()->route('clients.show', $client->id)
                ->with('success', 'تم تسجيل عدم الاهتمام وإنشاء مراجعة تشغيلية دون إغلاق العميل.');
        }

        return redirect()->route('clients.show', $client->id);
    }
}
