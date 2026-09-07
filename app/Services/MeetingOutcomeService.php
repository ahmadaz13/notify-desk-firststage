<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

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

            $meetingDateTime = !empty($data['meeting_date_time'])
                ? Carbon::parse($data['meeting_date_time'])
                : Carbon::parse($appointment->appointment_date . ' ' . $appointment->appointment_time);

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
}
