<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\RevenueRecognitionSchedule;
use App\Models\Subscription;
use App\Models\User;
use App\Support\AppointmentTypes;
use App\Support\ClientLifecycle;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class NotificationService
{
    public function createNotification(
        int $userId,
        string $type,
        string $title,
        string $message,
        ?string $actionUrl = null,
        ?string $sourceType = null,
        ?int $sourceId = null,
        ?string $occurrenceKey = null
    ): ?int {
        $occurrenceKey ??= $sourceType && $sourceId ? now()->toDateString() : null;
        $payload = [
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'action_url' => $actionUrl,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'occurrence_key' => $occurrenceKey,
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        try {
            if ($sourceType && $sourceId && $occurrenceKey) {
                $inserted = DB::table('notifications')->insertOrIgnore($payload);

                return $inserted === 1 ? (int) DB::getPdo()->lastInsertId() : null;
            }

            return DB::table('notifications')->insertGetId($payload);
        } catch (QueryException $exception) {
            if ($this->isDuplicateNotification($exception)) {
                return null;
            }

            throw $exception;
        }
    }

    public function sendScheduledAutomationNotifications(): int
    {
        return $this->sendOperationalReminders()
            + $this->sendCommercialFinanceAlerts()
            + $this->sendPaymentReminders();
    }

    public function sendOperationalReminders(?Carbon $at = null): int
    {
        $at ??= now();

        return $this->sendAppointmentReminders($at)
            + $this->sendDueFollowUpReminders($at);
    }

    public function sendCommercialFinanceAlerts(?Carbon $at = null): int
    {
        $at ??= now();

        return $this->sendRenewalDueAlerts($at)
            + $this->sendInvoiceOverdueAlerts($at)
            + $this->sendBillingReviewAlerts($at)
            + $this->sendRevenueRecognitionReviewAlerts($at);
    }

    public function getRecipientUserIds(): array
    {
        return $this->activeInternalUserIds();
    }

    public function sendAppointmentReminders(?Carbon $at = null): int
    {
        $now = $at ?: now();
        $today = $now->toDateString();
        $inFiveHours = $now->copy()->addHours(5);
        $createdCount = 0;

        $appointments = DB::table('appointments')
            ->join('clients', 'clients.id', '=', 'appointments.client_id')
            ->whereDate('appointments.appointment_date', $today)
            ->whereIn('appointments.status', AppointmentTypes::activeStatuses())
            ->where('clients.status', '!=', 'archived')
            ->where(function ($query) {
                $query->whereNull('clients.stage')
                    ->orWhere('clients.stage', '!=', ClientLifecycle::CLOSED);
            })
            ->select(
                'appointments.*',
                'clients.business_name',
                'clients.primary_owner_id'
            )
            ->get();

        foreach ($appointments as $appointment) {
            $appointmentAt = Carbon::parse($appointment->appointment_date.' '.$appointment->appointment_time);
            if (! $appointmentAt->between($now->copy()->subMinutes(30), $inFiveHours)) {
                continue;
            }

            $isInstallation = $appointment->appointment_type === AppointmentTypes::INSTALLATION;
            $type = $isInstallation ? 'installation_reminder' : 'appointment_reminder';
            $typeLabel = AppointmentTypes::label($appointment->appointment_type);
            $title = ($isInstallation ? 'تذكير بتركيب: ' : 'تذكير بموعد '.$typeLabel.': ').$appointment->business_name;
            $location = $appointment->location ?: 'عن بُعد';
            $message = "لديك موعد {$typeLabel} مع {$appointment->business_name} اليوم في تمام الساعة {$appointment->appointment_time} ({$location}).";
            $recipients = $this->appointmentRecipientUserIds((int) $appointment->id, $appointment->primary_owner_id ? (int) $appointment->primary_owner_id : null);

            $createdCount += $this->notifyRecipients(
                $recipients,
                $type,
                $title,
                $message,
                route('clients.show', $appointment->client_id, false),
                'appointment',
                (int) $appointment->id,
                $this->occurrenceKey($appointmentAt)
            );
        }

        return $createdCount;
    }

    public function sendPaymentReminders(): int
    {
        return $this->checkDuePaymentReminders();
    }

    public function checkDuePaymentReminders(?Carbon $at = null): int
    {
        $today = ($at ?: now())->copy()->startOfDay();
        $threeDaysLater = $today->copy()->addDays(3);
        $createdCount = 0;

        $schedules = DB::table('payment_schedules')
            ->join('subscriptions', 'subscriptions.id', '=', 'payment_schedules.subscription_id')
            ->join('clients', 'clients.id', '=', 'subscriptions.client_id')
            ->whereNotIn('payment_schedules.status', ['paid', 'cancelled'])
            ->where('subscriptions.status', '!=', 'cancelled')
            ->where('clients.status', '!=', 'archived')
            ->select(
                'payment_schedules.*',
                'clients.business_name',
                'clients.id as client_id',
                'clients.primary_owner_id'
            )
            ->get();

        foreach ($schedules as $schedule) {
            $dueDate = Carbon::parse($schedule->due_date)->startOfDay();
            $actionUrl = route('clients.show', $schedule->client_id, false);
            $amountFormatted = number_format((float) $schedule->amount_due, 2);
            $recipients = $this->clientRecipientUserIds($schedule->primary_owner_id ? (int) $schedule->primary_owner_id : null);
            $occurrenceKey = 'due:'.$dueDate->toDateString();

            if ($dueDate->lt($today)) {
                $daysOverdue = $dueDate->diffInDays($today);
                $created = $this->notifyRecipients(
                    $recipients,
                    'payment_overdue',
                    "دفعة متأخرة: {$schedule->business_name}",
                    "توجد دفعة متأخرة بمقدار {$daysOverdue} يوم بقيمة {$amountFormatted} د.أ للعميل {$schedule->business_name}.",
                    $actionUrl,
                    'payment_schedule',
                    (int) $schedule->id,
                    $occurrenceKey
                );
            } elseif ($dueDate->equalTo($today)) {
                $created = $this->notifyRecipients(
                    $recipients,
                    'payment_due_today',
                    "دفعة مستحقة اليوم: {$schedule->business_name}",
                    "تستحق اليوم دفعة بقيمة {$amountFormatted} د.أ للعميل {$schedule->business_name}.",
                    $actionUrl,
                    'payment_schedule',
                    (int) $schedule->id,
                    $occurrenceKey
                );
            } elseif ($dueDate->lte($threeDaysLater)) {
                $daysLeft = $today->diffInDays($dueDate);
                $created = $this->notifyRecipients(
                    $recipients,
                    'payment_due_soon',
                    "تذكير بقرب استحقاق دفعة: {$schedule->business_name}",
                    "تستحق دفعة بقيمة {$amountFormatted} د.أ للعميل {$schedule->business_name} خلال {$daysLeft} أيام ({$schedule->due_date}).",
                    $actionUrl,
                    'payment_schedule',
                    (int) $schedule->id,
                    $occurrenceKey
                );
            } else {
                $created = 0;
            }

            if ($created > 0) {
                $createdCount += $created;
                DB::table('payment_schedules')->where('id', $schedule->id)->update(['reminder_sent_at' => now()]);
            }
        }

        return $createdCount;
    }

    private function sendDueFollowUpReminders(Carbon $at): int
    {
        $followUps = DB::table('follow_ups')
            ->join('clients', 'clients.id', '=', 'follow_ups.client_id')
            ->where('clients.status', '!=', 'archived')
            ->whereNotIn('clients.stage', [ClientLifecycle::CLOSED, ClientLifecycle::SUBSCRIBER])
            ->whereNull('follow_ups.completed_at')
            ->where('follow_ups.follow_up_date_time', '<=', $at)
            ->select(
                'follow_ups.*',
                'clients.business_name',
                'clients.stage',
                'clients.primary_owner_id'
            )
            ->orderBy('follow_ups.follow_up_date_time')
            ->get();

        $createdCount = 0;

        foreach ($followUps as $followUp) {
            $followUpAt = Carbon::parse($followUp->follow_up_date_time);
            $type = $this->followUpNotificationType($followUp);
            $title = match ($type) {
                'trial_followup_due' => 'متابعة تجربة مجانية مستحقة: '.$followUp->business_name,
                'decision_followup_due' => 'متابعة قرار مستحقة: '.$followUp->business_name,
                default => 'متابعة اتصال مستحقة: '.$followUp->business_name,
            };
            $message = "متابعة مستحقة للعميل {$followUp->business_name}: {$followUp->next_action}.";

            $createdCount += $this->notifyRecipients(
                $this->followUpRecipientUserIds($followUp),
                $type,
                $title,
                $message,
                route('clients.show', $followUp->client_id, false),
                'follow_up',
                (int) $followUp->id,
                $this->occurrenceKey($followUpAt)
            );
        }

        return $createdCount;
    }

    private function sendRenewalDueAlerts(Carbon $at): int
    {
        $today = $at->copy()->startOfDay();
        $subscriptions = Subscription::with('client')
            ->where('billing_engine_version', 'v2')
            ->where('status', 'active')
            ->where(function ($query) {
                $query->where('cancel_at_period_end', false)->orWhereNull('cancel_at_period_end');
            })
            ->whereNotNull('next_billing_date')
            ->whereDate('next_billing_date', '<=', $today->toDateString())
            ->orderBy('next_billing_date')
            ->orderBy('id')
            ->get();

        $createdCount = 0;
        foreach ($subscriptions as $subscription) {
            $clientName = $subscription->client?->business_name ?: 'عميل';
            $dueDate = $subscription->next_billing_date->toDateString();
            $createdCount += $this->notifyRecipients(
                $this->activeInternalUserIds(),
                'renewal_due',
                'تجديد اشتراك مستحق: '.$clientName,
                "اشتراك {$clientName} مستحق للتجديد بتاريخ {$dueDate}.",
                route('subscription-billing.index', [], false),
                'subscription',
                $subscription->id,
                'renewal:'.$dueDate
            );
        }

        return $createdCount;
    }

    private function sendInvoiceOverdueAlerts(Carbon $at): int
    {
        $overdue = app(ReceivableService::class)->outstandingInvoices(['due_state' => 'overdue'], $at->copy()->startOfDay());
        $createdCount = 0;

        foreach ($overdue as $item) {
            /** @var Invoice $invoice */
            $invoice = $item['invoice'];
            $projection = $item['projection'];
            $invoice->loadMissing('client');
            $clientName = $invoice->client?->business_name ?: 'عميل';

            $createdCount += $this->notifyRecipients(
                $this->activeInternalUserIds(),
                'invoice_overdue',
                'فاتورة متأخرة: '.$clientName,
                'فاتورة '.$invoice->invoice_number.' متأخرة وباقيها '.Money::fromMinorUnits((int) $projection['outstanding_minor'])->format().' د.أ.',
                route('collections.index', [], false),
                'invoice',
                $invoice->id,
                'overdue:'.($invoice->due_date?->toDateString() ?? 'none')
            );
        }

        return $createdCount;
    }

    private function sendBillingReviewAlerts(Carbon $at): int
    {
        $items = app(SubscriptionBillingService::class)->billingReviewItems($at->copy()->addDays(30));
        $createdCount = 0;

        foreach ($items as $item) {
            /** @var Subscription $subscription */
            $subscription = $item['subscription'];
            $clientName = $item['client']?->business_name ?: 'عميل';
            $reason = (string) $item['reason'];

            $createdCount += $this->notifyRecipients(
                $this->activeInternalUserIds(),
                'billing_review_required',
                'مراجعة فوترة مطلوبة: '.$clientName,
                $item['message'],
                route('subscription-billing.index', [], false),
                'subscription',
                $subscription->id,
                'billing-review:'.$reason.':'.$at->toDateString()
            );
        }

        return $createdCount;
    }

    private function sendRevenueRecognitionReviewAlerts(Carbon $at): int
    {
        $schedules = RevenueRecognitionSchedule::with(['invoice', 'invoiceLine'])
            ->where(function ($query) {
                $query->where('status', RevenueRecognitionSchedule::STATUS_NEEDS_REVIEW)
                    ->orWhere(function ($inner) {
                        $inner->where('requires_manual_confirmation', true)
                            ->where('status', RevenueRecognitionSchedule::STATUS_PENDING);
                    });
            })
            ->orderBy('id')
            ->get();

        $createdCount = 0;
        foreach ($schedules as $schedule) {
            $invoiceNumber = $schedule->invoice?->invoice_number ?: ('#'.$schedule->invoice_id);
            $createdCount += $this->notifyRecipients(
                $this->activeInternalUserIds(),
                'revenue_recognition_review_required',
                'مراجعة تحقق إيراد مطلوبة',
                "جدول تحقق الإيراد للفاتورة {$invoiceNumber} يحتاج مراجعة قبل الأتمتة.",
                route('accounting.index', [], false),
                'revenue_recognition_schedule',
                $schedule->id,
                'revenue-review:'.$schedule->status.':'.$at->toDateString()
            );
        }

        return $createdCount;
    }

    private function notifyRecipients(
        array $recipients,
        string $type,
        string $title,
        string $message,
        ?string $actionUrl,
        string $sourceType,
        int $sourceId,
        string $occurrenceKey
    ): int {
        $created = 0;
        foreach (array_unique($recipients) as $userId) {
            if ($this->createNotification((int) $userId, $type, $title, $message, $actionUrl, $sourceType, $sourceId, $occurrenceKey)) {
                $created++;
            }
        }

        return $created;
    }

    private function activeInternalUserIds(): array
    {
        return DB::table('users')
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('role')
                    ->orWhereIn('role', User::activeInternalRoles());
            })
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function clientRecipientUserIds(?int $ownerId): array
    {
        return $this->filterInternalIds($ownerId ? [$ownerId] : []) ?: $this->activeInternalUserIds();
    }

    private function appointmentRecipientUserIds(int $appointmentId, ?int $ownerId): array
    {
        $attendeeIds = DB::table('appointment_user')
            ->where('appointment_id', $appointmentId)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return $this->filterInternalIds($attendeeIds) ?: $this->clientRecipientUserIds($ownerId);
    }

    private function followUpRecipientUserIds(object $followUp): array
    {
        return $this->filterInternalIds([(int) $followUp->user_id])
            ?: $this->clientRecipientUserIds($followUp->primary_owner_id ? (int) $followUp->primary_owner_id : null);
    }

    private function filterInternalIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === []) {
            return [];
        }

        return DB::table('users')
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('role')
                    ->orWhereIn('role', User::activeInternalRoles());
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function followUpNotificationType(object $followUp): string
    {
        if ($followUp->installation_id !== null || $followUp->stage === ClientLifecycle::INSTALLED_FREE) {
            return 'trial_followup_due';
        }

        if ($followUp->stage === ClientLifecycle::DECISION_PENDING) {
            return 'decision_followup_due';
        }

        return 'callback_due';
    }

    private function occurrenceKey(Carbon $at): string
    {
        return $at->copy()->format('Y-m-d H:i:s');
    }

    private function isDuplicateNotification(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? null;
        $driverCode = (string) ($exception->errorInfo[1] ?? '');

        return $sqlState === '23000' || $sqlState === '23505' || $driverCode === '1062' || $driverCode === '19';
    }

    public function markAsRead(int $notificationId, int $userId): bool
    {
        return (bool) DB::table('notifications')
            ->where('id', $notificationId)
            ->where('user_id', $userId)
            ->update([
                'read_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function markAllAsRead(int $userId): int
    {
        return DB::table('notifications')
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->update([
                'read_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function getUnreadCount(int $userId): int
    {
        return DB::table('notifications')
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->count();
    }
}
