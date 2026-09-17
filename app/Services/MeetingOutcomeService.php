<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\Appointment;
use App\Models\Client;
use App\Support\AppointmentTypes;
use App\Support\ClientLifecycle;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MeetingOutcomeService
{
    /**
     * Record a structured meeting outcome, complete the appointment, and update timeline.
     *
     * @param int $appointmentId
     * @param int $userId
     * @param array $data
     * @return int Created outcome ID
     */
    public function recordOutcome(int $appointmentId, int $userId, array $data): int
    {
        return DB::transaction(function () use ($appointmentId, $userId, $data) {
            $appointment = DB::table('appointments')->where('id', $appointmentId)->first();
            abort_unless($appointment, 404, 'الموعد غير موجود');

            $clientId = $appointment->client_id;
            $client = Client::findOrFail($clientId);

            $meetingDateTime = !empty($data['meeting_date_time'])
                ? Carbon::parse($data['meeting_date_time'])
                : Carbon::parse(Carbon::parse($appointment->appointment_date)->toDateString() . ' ' . $appointment->appointment_time);

            $outcomeId = DB::table('meeting_outcomes')->insertGetId([
                'appointment_id' => $appointmentId,
                'client_id' => $clientId,
                'user_id' => $userId,
                'attendance_status' => $data['attendance_status'] ?? 'attended',
                'meeting_date_time' => $meetingDateTime->toDateTimeString(),
                'meeting_type' => $data['meeting_type'] ?? $appointment->appointment_type ?? 'physical_visit',
                'demo_performed' => !empty($data['demo_performed']),
                'interest_level' => $data['interest_level'] ?? 'medium',
                'customer_needs' => $data['customer_needs'] ?? null,
                'main_objections' => $data['main_objections'] ?? null,
                'price_discussed' => !empty($data['price_discussed']),
                'package_discussed' => $data['package_discussed'] ?? null,
                'customer_response' => $data['customer_response'] ?? null,
                'next_action' => $data['next_action'] ?? 'متابعة هاتفية',
                'next_follow_up_date' => !empty($data['next_follow_up_date']) ? Carbon::parse($data['next_follow_up_date'])->toDateString() : null,
                'meeting_notes' => $data['meeting_notes'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Update appointment status to completed
            DB::table('appointments')->where('id', $appointmentId)->update([
                'status' => 'completed',
                'updated_at' => now(),
            ]);

            // If follow-up date and next action provided, create follow-up record
            if (!empty($data['next_follow_up_date']) && !empty($data['next_action'])) {
                DB::table('follow_ups')->insert([
                    'client_id' => $clientId,
                    'user_id' => $userId,
                    'method' => $data['meeting_type'] ?? 'phone_call',
                    'reason' => 'متابعة بعد اجتماع: ' . ($data['interest_level'] ?? ''),
                    'result' => $data['customer_response'] ?? null,
                    'next_action' => $data['next_action'],
                    'next_follow_up_date' => Carbon::parse($data['next_follow_up_date'])->toDateString(),
                    'follow_up_date_time' => Carbon::parse($data['next_follow_up_date'])->setTime(10, 0)->toDateTimeString(),
                    'notes' => $data['meeting_notes'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $workflow = app(ClientOperationalWorkflowService::class);
            $outcomeResult = $data['outcome_result'] ?? null;

            if ($outcomeResult === 'installation_scheduled') {
                if (empty($data['installation_appointment_date']) || empty($data['installation_appointment_time'])) {
                    throw ValidationException::withMessages([
                        'installation_appointment_date' => 'تاريخ ووقت التركيب مطلوبان عند اختيار تركيب مجاني.',
                    ]);
                }

                $installationAppointment = Appointment::create([
                    'client_id' => $clientId,
                    'appointment_date' => Carbon::parse($data['installation_appointment_date'])->toDateString(),
                    'appointment_time' => $data['installation_appointment_time'],
                    'appointment_type' => AppointmentTypes::INSTALLATION,
                    'status' => 'scheduled',
                    'notes' => $data['meeting_notes'] ?? null,
                ]);
                $installationAppointment->users()->sync([$userId]);
                $workflow->transition($client, ClientLifecycle::INSTALLATION_SCHEDULED, \App\Models\User::findOrFail($userId));
                $this->log($clientId, $userId, 'installation_scheduled', 'تمت جدولة تركيب مجاني من مخرجات الاجتماع', [
                    'appointment_id' => $installationAppointment->id,
                    'meeting_outcome_id' => $outcomeId,
                ]);
            } elseif ($outcomeResult === 'follow_up_required') {
                if (empty($data['next_follow_up_date'])) {
                    throw ValidationException::withMessages([
                        'next_follow_up_date' => 'تاريخ المتابعة مطلوب.',
                    ]);
                }
                $workflow->transition($client, ClientLifecycle::CONTACTING, \App\Models\User::findOrFail($userId));
            } elseif ($outcomeResult === 'decision_pending') {
                $workflow->transition($client, ClientLifecycle::DECISION_PENDING, \App\Models\User::findOrFail($userId));
            } elseif ($outcomeResult === 'closed') {
                $workflow->closeClient(
                    $client,
                    \App\Models\User::findOrFail($userId),
                    $data['closed_reason_code'] ?? 'other',
                    $data['closed_reason'] ?? $data['meeting_notes'] ?? null
                );
            }

            // Append to activity logs
            $interestLabels = [
                'high' => 'مرتفع جداً',
                'medium' => 'متوسط',
                'low' => 'منخفض',
                'none' => 'غير مهتم',
            ];
            $interestText = $interestLabels[$data['interest_level'] ?? ''] ?? ($data['interest_level'] ?? 'غير محدد');

            DB::table('activity_logs')->insert([
                'client_id' => $clientId,
                'user_id' => $userId,
                'type' => 'meeting_outcome_recorded',
                'description' => "تم تسجيل مخرجات الاجتماع (مستوى الاهتمام: {$interestText}، الخطوة القادمة: " . ($data['next_action'] ?? '-') . ")",
                'metadata' => json_encode([
                    'outcome_id' => $outcomeId,
                    'appointment_id' => $appointmentId,
                    'demo_performed' => !empty($data['demo_performed']),
                    'interest_level' => $data['interest_level'] ?? 'medium',
                    'next_action' => $data['next_action'] ?? null,
                    'next_follow_up_date' => $data['next_follow_up_date'] ?? null,
                ], JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $outcomeId;
        });
    }

    private function log(int $clientId, int $userId, string $type, string $description, array $metadata = []): void
    {
        DB::table('activity_logs')->insert([
            'client_id' => $clientId,
            'user_id' => $userId,
            'type' => $type,
            'description' => $description,
            'metadata' => $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
