<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\ClientReviewItem;
use App\Models\User;
use App\Support\AppointmentTypes;
use App\Support\ClientLifecycle;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FollowUpService
{
    public const OUTCOME_SUBSCRIBE = 'subscribe';
    public const OUTCOME_CALLBACK_LATER = 'callback_later';
    public const OUTCOME_APPOINTMENT = 'appointment';
    public const OUTCOME_NO_ANSWER = 'no_answer';
    public const OUTCOME_NOT_INTERESTED = 'not_interested';

    public const CANONICAL_OUTCOMES = [
        self::OUTCOME_SUBSCRIBE,
        self::OUTCOME_CALLBACK_LATER,
        self::OUTCOME_APPOINTMENT,
        self::OUTCOME_NO_ANSWER,
        self::OUTCOME_NOT_INTERESTED,
    ];

    /**
     * Create a follow-up record for a client and append to timeline.
     *
     * @param int $clientId
     * @param int $userId
     * @param array $data
     * @return int Created follow up ID
     */
    public function createFollowUp(int $clientId, int $userId, array $data): int
    {
        return DB::transaction(function () use ($clientId, $userId, $data) {
            $client = DB::table('clients')->where('id', $clientId)->first();
            abort_unless($client, 404, 'العميل غير موجود');

            $followUpDateTime = !empty($data['follow_up_date_time'])
                ? Carbon::parse($data['follow_up_date_time'])
                : now();

            $id = DB::table('follow_ups')->insertGetId([
                'client_id' => $clientId,
                'user_id' => $userId,
                'method' => $data['method'] ?? 'phone_call',
                'reason' => $data['reason'] ?? 'متابعة دورية',
                'result' => $data['result'] ?? null,
                'next_action' => $data['next_action'],
                'next_follow_up_date' => Carbon::parse($data['next_follow_up_date'])->toDateString(),
                'follow_up_date_time' => $followUpDateTime->toDateTimeString(),
                'notes' => $data['notes'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Append to activity logs
            DB::table('activity_logs')->insert([
                'client_id' => $clientId,
                'user_id' => $userId,
                'type' => 'follow_up_recorded',
                'description' => 'تم تسجيل متابعة: ' . ($data['reason'] ?? 'متابعة') . ' - الخطوة التالية: ' . $data['next_action'] . ' (الموعد: ' . Carbon::parse($data['next_follow_up_date'])->toDateString() . ')',
                'metadata' => json_encode([
                    'follow_up_id' => $id,
                    'method' => $data['method'] ?? 'phone_call',
                    'next_action' => $data['next_action'],
                    'next_follow_up_date' => $data['next_follow_up_date'],
                ], JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $id;
        });
    }

    /**
     * Complete a follow-up with an authoritative human outcome and transactionally create the next work item.
     * Delegates domain lifecycle transitions, appointments, and reviews to authoritative services.
     *
     * @param int $followUpId
     * @param User $actor
     * @param string $outcome
     * @param array $data
     * @return array
     */
    public function completeFollowUp(int $followUpId, User $actor, string $outcome, array $data = []): array
    {
        return DB::transaction(function () use ($followUpId, $actor, $outcome, $data) {
            $followUp = DB::table('follow_ups')->where('id', $followUpId)->first();
            abort_unless($followUp, 404, 'المتابعة غير موجودة');

            $client = Client::findOrFail($followUp->client_id);
            $workflow = app(ClientOperationalWorkflowService::class);
            $created = [];

            $canonicalOutcome = match ($outcome) {
                'subscribe', 'start_subscription' => self::OUTCOME_SUBSCRIBE,
                'callback_later', 'wait', 'call_later' => self::OUTCOME_CALLBACK_LATER,
                'appointment' => self::OUTCOME_APPOINTMENT,
                'no_answer', 'no_answer_busy' => self::OUTCOME_NO_ANSWER,
                'not_interested' => self::OUTCOME_NOT_INTERESTED,
                default => throw ValidationException::withMessages(['outcome' => 'نتيجة المتابعة غير صالحة.']),
            };

            // 1. Process specific next actions and validations based on outcome
            if ($canonicalOutcome === self::OUTCOME_CALLBACK_LATER) {
                $followUpAt = null;
                if (!empty($data['follow_up_date_time'])) {
                    $followUpAt = Carbon::parse($data['follow_up_date_time']);
                } elseif (!empty($data['next_follow_up_date'])) {
                    $time = !empty($data['next_follow_up_time']) ? $data['next_follow_up_time'] : '10:00';
                    $followUpAt = Carbon::parse(Carbon::parse($data['next_follow_up_date'])->toDateString() . ' ' . $time);
                }

                if (!$followUpAt) {
                    throw ValidationException::withMessages([
                        'follow_up_date_time' => 'حدد موعد معاودة الاتصال.',
                    ]);
                }

                $nextFollowUpId = $workflow->scheduleFollowUp($client, $actor, [
                    'installation_id' => $followUp->installation_id,
                    'method' => 'phone',
                    'reason' => 'متابعة لاحقة',
                    'next_action' => $data['next_action'] ?? 'معاودة الاتصال بالعميل',
                    'follow_up_date_time' => $followUpAt->toDateTimeString(),
                    'notes' => $data['notes'] ?? ($data['note'] ?? null),
                ]);

                $created['next_follow_up_id'] = $nextFollowUpId;

                $this->logActivity($client->id, $actor->id, 'callback_scheduled', 'تمت جدولة معاودة اتصال بعد إكمال المتابعة', [
                    'previous_follow_up_id' => $followUpId,
                    'follow_up_id' => $nextFollowUpId,
                    'follow_up_date_time' => $followUpAt->toDateTimeString(),
                ]);
            } elseif ($canonicalOutcome === self::OUTCOME_APPOINTMENT) {
                if (empty($data['appointment_date']) || empty($data['appointment_time'])) {
                    throw ValidationException::withMessages([
                        'appointment_date' => 'حدد تاريخ ووقت الموعد.',
                    ]);
                }

                $appointment = Appointment::create([
                    'client_id' => $client->id,
                    'appointment_date' => Carbon::parse($data['appointment_date'])->toDateString(),
                    'appointment_time' => $data['appointment_time'],
                    'appointment_type' => $data['appointment_type'] ?? AppointmentTypes::PHYSICAL_VISIT,
                    'location' => $data['location'] ?? $client->location_text,
                    'branch_name' => $data['branch_name'] ?? null,
                    'notes' => $data['notes'] ?? ($data['note'] ?? null),
                    'status' => 'scheduled',
                ]);
                $appointment->users()->sync($data['attendees'] ?? [$actor->id]);

                // Delegate stage transition to authoritative workflow service
                $workflow->transition($client, ClientLifecycle::APPOINTMENT, $actor);

                $created['appointment'] = $appointment;

                $this->logActivity($client->id, $actor->id, 'appointment_scheduled', 'تم جدولة موعد جديد من خلال المتابعة', [
                    'previous_follow_up_id' => $followUpId,
                    'appointment_id' => $appointment->id,
                ]);
            } elseif ($canonicalOutcome === self::OUTCOME_NOT_INTERESTED) {
                $note = trim((string) ($data['note'] ?? ($data['reason'] ?? '')));
                if ($note === '') {
                    throw ValidationException::withMessages([
                        'note' => 'اكتب سبب عدم الاهتمام للمراجعة.',
                    ]);
                }

                // Delegate review item creation and contacting stage transition to workflow authority
                $review = $workflow->createReviewItem(
                    $client,
                    $actor,
                    ClientReviewItem::TYPE_NOT_INTERESTED,
                    $note
                );

                $currentStage = ClientLifecycle::normalizeStage($client->stage, $client->status);
                if (!in_array($currentStage, ClientLifecycle::PIPELINE_LOCKED_STAGES, true)) {
                    $workflow->transition($client, ClientLifecycle::CONTACTING, $actor);
                }

                $created['review_item'] = $review;

                $this->logActivity($client->id, $actor->id, 'follow_up_not_interested', 'تم تسجيل عدم الاهتمام وفتح مراجعة تشغيلية دون إغلاق العميل', [
                    'follow_up_id' => $followUpId,
                    'review_id' => $review->id,
                ]);
            } elseif ($canonicalOutcome === self::OUTCOME_NO_ANSWER) {
                // For a Follow-up outcome of No Answer, require the next callback date/time
                // so the follow-up does not disappear without a deterministic next operational item.
                $followUpAt = null;
                if (!empty($data['follow_up_date_time'])) {
                    $followUpAt = Carbon::parse($data['follow_up_date_time']);
                } elseif (!empty($data['next_follow_up_date'])) {
                    $time = !empty($data['next_follow_up_time']) ? $data['next_follow_up_time'] : '10:00';
                    $followUpAt = Carbon::parse(Carbon::parse($data['next_follow_up_date'])->toDateString() . ' ' . $time);
                }

                if (!$followUpAt) {
                    throw ValidationException::withMessages([
                        'follow_up_date_time' => 'حدد موعد معاودة الاتصال القادم عند تعذر الرد.',
                    ]);
                }

                $nextFollowUpId = $workflow->scheduleFollowUp($client, $actor, [
                    'installation_id' => $followUp->installation_id,
                    'method' => 'phone',
                    'reason' => 'معاودة اتصال بعد تعذر الرد',
                    'next_action' => $data['next_action'] ?? 'معاودة الاتصال بالعميل',
                    'follow_up_date_time' => $followUpAt->toDateTimeString(),
                    'notes' => $data['notes'] ?? ($data['note'] ?? null),
                ]);

                $created['next_follow_up_id'] = $nextFollowUpId;

                $this->logActivity($client->id, $actor->id, 'follow_up_no_answer', 'تم تسجيل تعذر الرد وجدولة موعد معاودة اتصال جديد', [
                    'previous_follow_up_id' => $followUpId,
                    'next_follow_up_id' => $nextFollowUpId,
                    'follow_up_date_time' => $followUpAt->toDateTimeString(),
                ]);
            } elseif ($canonicalOutcome === self::OUTCOME_SUBSCRIBE) {
                // Delegate to ClientOperationalWorkflowService to move client to decision_pending (ready for subscription)
                // NEVER automatically create a Subscription!
                $currentStage = ClientLifecycle::normalizeStage($client->stage, $client->status);
                if (!in_array($currentStage, ClientLifecycle::PIPELINE_LOCKED_STAGES, true)) {
                    $workflow->transition($client, ClientLifecycle::DECISION_PENDING, $actor);
                }

                $this->logActivity($client->id, $actor->id, 'follow_up_ready_to_subscribe', 'العميل جاهز للاشتراك وتم نقله إلى بانتظار القرار، جاهز للبدء الصريح', [
                    'follow_up_id' => $followUpId,
                ]);
            }

            // 2. Mark current follow-up completed
            DB::table('follow_ups')->where('id', $followUpId)->update([
                'completed_at' => now(),
                'completed_by' => $actor->id,
                'completion_outcome' => $canonicalOutcome,
                'result' => $data['result'] ?? ($data['notes'] ?? ($data['note'] ?? $canonicalOutcome)),
                'updated_at' => now(),
            ]);

            return $created;
        });
    }

    private function logActivity(int $clientId, int $userId, string $type, string $description, array $metadata = []): void
    {
        DB::table('activity_logs')->insert([
            'client_id' => $clientId,
            'user_id' => $userId,
            'type' => $type,
            'description' => $description,
            'metadata' => !empty($metadata) ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
