<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\ClientReviewItem;
use App\Models\ContactAttempt;
use App\Models\Subscription;
use App\Models\User;
use App\Support\AppointmentTypes;
use App\Support\ClientLifecycle;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClientOperationalWorkflowService
{
    public const CLOSED_REASONS = [
        'price',
        'not_needed',
        'not_ready',
        'competitor',
        'no_response',
        'business_closed',
        'invalid_contact',
        'not_interested',
        'other',
    ];

    public function transition(Client $client, string $stage, User $actor, array $options = []): Client
    {
        if (!in_array($stage, ClientLifecycle::STAGES, true)) {
            throw ValidationException::withMessages(['stage' => 'مرحلة العميل غير صالحة.']);
        }

        if ($stage === ClientLifecycle::SUBSCRIBER && empty($options['allow_subscriber'])) {
            throw ValidationException::withMessages([
                'stage' => 'يتم إدخال العميل إلى مرحلة مشترك فقط عبر مسار الاشتراك المدفوع V2.',
            ]);
        }

        if ($stage === ClientLifecycle::FORMER_SUBSCRIBER && empty($options['allow_former_subscriber'])) {
            throw ValidationException::withMessages([
                'stage' => __('notify.clients.former_subscriber_system_only'),
            ]);
        }

        if ($stage === ClientLifecycle::CLOSED) {
            return $this->closeClient(
                $client,
                $actor,
                $options['closed_reason_code'] ?? 'other',
                $options['closed_reason_note'] ?? ($options['closed_reason'] ?? null)
            );
        }

        if (in_array($stage, [ClientLifecycle::PROSPECT, ClientLifecycle::CONTACTING, ClientLifecycle::APPOINTMENT, ClientLifecycle::INSTALLATION_SCHEDULED, ClientLifecycle::INSTALLED_FREE, ClientLifecycle::DECISION_PENDING], true)
            && ClientLifecycle::normalizeStage($client->stage, $client->status) === ClientLifecycle::CLOSED
            && empty($options['allow_reopen'])) {
            throw ValidationException::withMessages(['stage' => 'يجب استخدام إجراء إعادة الفتح الصريح للعميل المغلق.']);
        }

        return DB::transaction(function () use ($client, $stage, $actor, $options) {
            $oldStage = ClientLifecycle::normalizeStage($client->stage, $client->status);
            $client->update([
                'stage' => $stage,
                'status' => self::legacyStatusFor($stage),
                'closed_at' => null,
                'closed_reason' => null,
            ]);

            if ($oldStage !== $stage || !empty($options['force_log'])) {
                $this->log($client->id, $actor->id, 'client_stage_changed', 'تم تغيير مرحلة العميل من '.ClientLifecycle::label($oldStage).' إلى '.ClientLifecycle::label($stage), [
                    'from' => $oldStage,
                    'to' => $stage,
                    'reason' => $options['reason'] ?? null,
                ]);
            }

            return $client->refresh();
        });
    }

    /**
     * Automatic sales-pipeline move; subscribers and former subscribers keep their stage (§4).
     * Closed clients still go through transition(), which requires an explicit reopen.
     */
    private function pipelineTransition(Client $client, string $stage, User $actor): Client
    {
        $current = ClientLifecycle::normalizeStage($client->stage, $client->status);
        if (in_array($current, ClientLifecycle::SYSTEM_MANAGED_STAGES, true)) {
            return $client;
        }

        return $this->transition($client, $stage, $actor);
    }

    public static function legacyStatusFor(string $stage): string
    {
        return match ($stage) {
            ClientLifecycle::SUBSCRIBER => 'subscriber',
            ClientLifecycle::FORMER_SUBSCRIBER => 'former_subscriber',
            default => 'prospect',
        };
    }

    public function closeClient(Client $client, User $actor, string $reasonCode, ?string $note): Client
    {
        $reasonCode = in_array($reasonCode, self::CLOSED_REASONS, true) ? $reasonCode : 'other';
        $note = trim((string) $note);

        if ($reasonCode === 'other' && $note === '') {
            throw ValidationException::withMessages(['closed_reason' => 'ملاحظة الإغلاق مطلوبة عند اختيار سبب آخر.']);
        }

        // §4: closing never cancels subscriptions; an active paid subscription must be cancelled first.
        if ($client->subscriptions()->where('status', 'active')->exists()) {
            throw ValidationException::withMessages(['closed_reason_code' => __('notify.client_hub.close.blocked_active_subscription')]);
        }

        return DB::transaction(function () use ($client, $actor, $reasonCode, $note) {
            $oldStage = ClientLifecycle::normalizeStage($client->stage, $client->status);
            $closedReason = $note !== '' ? "{$reasonCode}: {$note}" : $reasonCode;

            $client->update([
                'stage' => ClientLifecycle::CLOSED,
                'status' => 'archived',
                'closed_at' => now(),
                'closed_reason' => $closedReason,
            ]);

            $this->log($client->id, $actor->id, 'client_closed', 'تم إغلاق ملف العميل مع الحفاظ على السجل', [
                'from' => $oldStage,
                'reason_code' => $reasonCode,
                'reason_note' => $note !== '' ? $note : null,
            ]);

            return $client->refresh();
        });
    }

    public function reopen(Client $client, User $actor, string $stage, string $reason): Client
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'سبب إعادة الفتح مطلوب.']);
        }

        $allowed = [ClientLifecycle::PROSPECT, ClientLifecycle::CONTACTING, ClientLifecycle::APPOINTMENT, ClientLifecycle::DECISION_PENDING];
        if (!in_array($stage, $allowed, true)) {
            throw ValidationException::withMessages(['stage' => 'مرحلة إعادة الفتح غير صالحة.']);
        }

        // §4: reopening a client with paid-subscription history returns it to former_subscriber
        // (or subscriber while a paid subscription is still active) instead of the sales pipeline.
        $subscriptions = Subscription::query()->where('client_id', $client->id);
        $options = [];
        if ((clone $subscriptions)->where('status', 'active')->exists()) {
            [$stage, $options] = [ClientLifecycle::SUBSCRIBER, ['allow_subscriber' => true]];
        } elseif ($subscriptions->exists()) {
            [$stage, $options] = [ClientLifecycle::FORMER_SUBSCRIBER, ['allow_former_subscriber' => true]];
        }

        return DB::transaction(function () use ($client, $actor, $stage, $reason, $options) {
            $previous = [
                'stage' => ClientLifecycle::normalizeStage($client->stage, $client->status),
                'status' => $client->status,
                'closed_at' => $client->closed_at,
                'closed_reason' => $client->closed_reason,
            ];

            $client = $this->transition($client, $stage, $actor, $options + [
                'allow_reopen' => true,
                'force_log' => true,
                'reason' => 'reopened',
            ]);

            $this->log($client->id, $actor->id, 'client_reopened', 'تمت إعادة فتح ملف العميل', [
                'previous' => $previous,
                'reopened_to' => $stage,
                'reason' => $reason,
            ]);

            return $client->refresh();
        });
    }

    public function recordContactOutcome(Client $client, User $actor, array $data): array
    {
        return DB::transaction(function () use ($client, $actor, $data) {
            $result = $this->canonicalContactResult($data['result']);
            $note = trim((string) ($data['note'] ?? ''));

            if ($result === 'callback_later' && empty($data['follow_up_date_time']) && empty($data['next_follow_up_date'])) {
                throw ValidationException::withMessages(['follow_up_date_time' => 'وقت معاودة الاتصال مطلوب.']);
            }

            if ($result === 'appointment' && (empty($data['appointment_date']) || empty($data['appointment_time']))) {
                throw ValidationException::withMessages(['appointment_date' => 'تاريخ ووقت الموعد مطلوبان.']);
            }

            if ($result === 'not_interested' && $note === '' && empty($data['closed_reason'])) {
                throw ValidationException::withMessages(['note' => 'ملاحظة سبب عدم الاهتمام مطلوبة للمراجعة.']);
            }

            $attempt = ContactAttempt::create([
                'client_id' => $client->id,
                'user_id' => $actor->id,
                'method' => $data['method'],
                'result' => $result,
                'note' => $note !== '' ? $note : ($data['closed_reason'] ?? null),
                'next_action' => $data['next_action'] ?? null,
                'next_follow_up_date' => !empty($data['next_follow_up_date']) ? Carbon::parse($data['next_follow_up_date'])->toDateString() : null,
            ]);

            $created = ['contact_attempt' => $attempt];

            $this->log($client->id, $actor->id, 'contact_attempt_recorded', "تم تسجيل نتيجة تواصل: {$result}", [
                'contact_attempt_id' => $attempt->id,
                'outcome' => $result,
            ]);

            if ($result === 'appointment') {
                $appointment = Appointment::create([
                    'client_id' => $client->id,
                    'appointment_date' => Carbon::parse($data['appointment_date'])->toDateString(),
                    'appointment_time' => $data['appointment_time'],
                    'appointment_type' => $data['appointment_type'] ?? 'sales_meeting',
                    'location' => $data['location'] ?? null,
                    'branch_name' => $data['branch_name'] ?? null,
                    'notes' => $data['appointment_notes'] ?? ($data['note'] ?? null),
                    'status' => 'scheduled',
                ]);
                $appointment->users()->sync($data['attendees'] ?? [$actor->id]);
                $this->pipelineTransition($client, ClientLifecycle::APPOINTMENT, $actor);
                $this->log($client->id, $actor->id, 'appointment_scheduled', 'تم جدولة موعد بعد نتيجة التواصل', [
                    'appointment_id' => $appointment->id,
                ]);
                $created['appointment'] = $appointment;
            } elseif ($result === 'callback_later') {
                $followUpId = $this->scheduleFollowUp($client, $actor, [
                    'method' => $data['method'],
                    'reason' => 'معاودة اتصال',
                    'next_action' => $data['next_action'] ?? 'معاودة الاتصال بالعميل',
                    'follow_up_date_time' => $data['follow_up_date_time'] ?? $data['next_follow_up_date'],
                    'notes' => $data['note'] ?? null,
                ]);
                $this->pipelineTransition($client, ClientLifecycle::CONTACTING, $actor);
                $this->log($client->id, $actor->id, 'callback_scheduled', 'تمت جدولة معاودة اتصال', [
                    'follow_up_id' => $followUpId,
                ]);
                $created['follow_up_id'] = $followUpId;
            } elseif (in_array($result, ['wrong_invalid', 'not_interested'], true)) {
                $review = $this->createReviewItem(
                    $client,
                    $actor,
                    $result === 'wrong_invalid' ? ClientReviewItem::TYPE_WRONG_INVALID : ClientReviewItem::TYPE_NOT_INTERESTED,
                    $note !== '' ? $note : ($data['closed_reason'] ?? null)
                );
                $this->pipelineTransition($client, ClientLifecycle::CONTACTING, $actor);
                $created['review_item'] = $review;
            } else {
                $this->pipelineTransition($client, ClientLifecycle::CONTACTING, $actor);
            }

            return $created;
        });
    }

    public function scheduleFollowUp(Client $client, User $actor, array $data): int
    {
        $followUpAt = Carbon::parse($data['follow_up_date_time'] ?? $data['next_follow_up_date'] ?? now()->addDay());

        return DB::table('follow_ups')->insertGetId([
            'client_id' => $client->id,
            'installation_id' => $data['installation_id'] ?? null,
            'user_id' => $data['user_id'] ?? $actor->id,
            'method' => $data['method'] ?? 'phone',
            'reason' => $data['reason'] ?? 'متابعة تشغيلية',
            'result' => $data['result'] ?? null,
            'next_action' => $data['next_action'] ?? 'متابعة العميل',
            'next_follow_up_date' => $followUpAt->toDateString(),
            'follow_up_date_time' => $followUpAt->toDateTimeString(),
            'notes' => $data['notes'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function createReviewItem(Client $client, User $actor, string $type, ?string $note): ClientReviewItem
    {
        $review = ClientReviewItem::where('client_id', $client->id)
            ->where('type', $type)
            ->where('status', ClientReviewItem::STATUS_PENDING)
            ->first();

        if ($review) {
            $review->update([
                'note' => $note ?? $review->note,
                'updated_at' => now(),
            ]);
        } else {
            $review = ClientReviewItem::create([
                'client_id' => $client->id,
                'type' => $type,
                'status' => ClientReviewItem::STATUS_PENDING,
                'note' => $note,
                'created_by' => $actor->id,
            ]);
        }

        $this->log($client->id, $actor->id, 'client_review_created', 'تم إنشاء بند مراجعة تشغيلية للعميل', [
            'client_review_item_id' => $review->id,
            'type' => $type,
        ]);

        return $review->refresh();
    }

    public function resolveReviewItem(ClientReviewItem $review, User $actor, string $status, ?string $note = null): ClientReviewItem
    {
        if (!in_array($status, [ClientReviewItem::STATUS_RESOLVED, ClientReviewItem::STATUS_DISMISSED], true)) {
            throw ValidationException::withMessages(['status' => 'حالة المراجعة غير صالحة.']);
        }

        $review->update([
            'status' => $status,
            'resolved_by' => $actor->id,
            'resolved_at' => now(),
            'resolution_note' => $note,
        ]);

        $this->log($review->client_id, $actor->id, 'client_review_resolved', 'تمت معالجة بند مراجعة تشغيلية', [
            'client_review_item_id' => $review->id,
            'status' => $status,
        ]);

        return $review->refresh();
    }

    private function canonicalContactResult(string $result): string
    {
        return match ($result) {
            'call_later' => 'callback_later',
            'wrong_number' => 'wrong_invalid',
            'no_answer', 'busy' => 'no_answer_busy',
            default => $result,
        };
    }

    private function log(int $clientId, ?int $userId, string $type, string $description, array $metadata = []): void
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
