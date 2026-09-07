<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class NotificationService
{
    /**
     * Create an in-app notification if a duplicate hasn't already been issued recently.
     */
    public function createNotification(
        int $userId,
        string $type,
        string $title,
        string $message,
        ?string $actionUrl = null,
        ?string $sourceType = null,
        ?int $sourceId = null
    ): ?int {
        // Prevent duplicate notification for same source & type created today
        if ($sourceType && $sourceId) {
            $exists = DB::table('notifications')
                ->where('user_id', $userId)
                ->where('source_type', $sourceType)
                ->where('source_id', $sourceId)
                ->where('type', $type)
                ->whereDate('created_at', now()->toDateString())
                ->exists();

            if ($exists) {
                return null;
            }
        }

        return DB::table('notifications')->insertGetId([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'action_url' => $actionUrl,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Scan and send reminders for appointments occurring within the next 5 hours.
     *
     * @return int Number of notifications created
     */
    public function sendAppointmentReminders(): int
    {
        $now = now();
        $today = $now->toDateString();
        $inFiveHours = $now->copy()->addHours(5);

        // Fetch scheduled or confirmed appointments for today
        $appointments = DB::table('appointments')
            ->join('clients', 'clients.id', '=', 'appointments.client_id')
            ->whereDate('appointments.appointment_date', $today)
            ->whereIn('appointments.status', ['scheduled', 'confirmed'])
            ->select('appointments.*', 'clients.business_name')
            ->get();

        $users = DB::table('users')->pluck('id');
        $createdCount = 0;

        foreach ($appointments as $apt) {
            $aptTimeStr = $apt->appointment_date . ' ' . $apt->appointment_time;
            $aptDateTime = Carbon::parse($aptTimeStr);

            // Check if appointment is between now - 30 minutes and now + 5 hours
            if ($aptDateTime->between($now->copy()->subMinutes(30), $inFiveHours)) {
                $location = $apt->location ?: 'عن بُعد';
                $title = 'تذكير بموعد قادم: ' . $apt->business_name;
                $message = "لديك موعد مع {$apt->business_name} اليوم في تمام الساعة {$apt->appointment_time} ({$location}).";
                $actionUrl = route('clients.show', $apt->client_id, false);

                foreach ($users as $userId) {
                    $id = $this->createNotification(
                        $userId,
                        'appointment_reminder',
                        $title,
                        $message,
                        $actionUrl,
                        'appointment',
                        $apt->id
                    );
                    if ($id) {
                        $createdCount++;
                    }
                }
            }
        }

        return $createdCount;
    }

    /**
     * Scan and send reminders for payment schedules (due in 3 days, due today, overdue).
     *
     * @return int Number of notifications created
     */
    public function sendPaymentReminders(): int
    {
        $today = Carbon::today();
        $threeDaysLater = $today->copy()->addDays(3);
        $users = DB::table('users')->pluck('id');
        $createdCount = 0;

        // Fetch unpaid schedules
        $schedules = DB::table('payment_schedules')
            ->join('subscriptions', 'subscriptions.id', '=', 'payment_schedules.subscription_id')
            ->join('clients', 'clients.id', '=', 'subscriptions.client_id')
            ->whereNotIn('payment_schedules.status', ['paid', 'cancelled'])
            ->where('clients.status', '!=', 'archived')
            ->select(
                'payment_schedules.*',
                'clients.business_name',
                'clients.id as client_id'
            )
            ->get();

        foreach ($schedules as $schedule) {
            $dueDate = Carbon::parse($schedule->due_date)->startOfDay();
            $actionUrl = route('clients.show', $schedule->client_id, false);
            $amountFormatted = number_format((float) $schedule->amount_due, 2);

            // 1. Overdue
            if ($dueDate->lt($today)) {
                $daysOverdue = $today->diffInDays($dueDate);
                $title = "دفعة متأخرة: {$schedule->business_name}";
                $message = "توجد دفعة متأخرة بمقدار {$daysOverdue} يوم بقيمة {$amountFormatted} د.أ للعميل {$schedule->business_name}.";

                foreach ($users as $userId) {
                    $id = $this->createNotification(
                        $userId,
                        'payment_overdue',
                        $title,
                        $message,
                        $actionUrl,
                        'payment_schedule',
                        $schedule->id
                    );
                    if ($id) {
                        $createdCount++;
                    }
                }
            }
            // 2. Due today
            elseif ($dueDate->equalTo($today)) {
                $title = "دفعة مستحقة اليوم: {$schedule->business_name}";
                $message = "تستحق اليوم دفعة بقيمة {$amountFormatted} د.أ للعميل {$schedule->business_name}.";

                foreach ($users as $userId) {
                    $id = $this->createNotification(
                        $userId,
                        'payment_due_today',
                        $title,
                        $message,
                        $actionUrl,
                        'payment_schedule',
                        $schedule->id
                    );
                    if ($id) {
                        $createdCount++;
                    }
                }
            }
            // 3. Due within 3 days
            elseif ($dueDate->lte($threeDaysLater)) {
                $daysLeft = $today->diffInDays($dueDate);
                $title = "تذكير بقرب استحقاق دفعة: {$schedule->business_name}";
                $message = "تستحق دفعة بقيمة {$amountFormatted} د.أ للعميل {$schedule->business_name} خلال {$daysLeft} أيام ({$schedule->due_date}).";

                foreach ($users as $userId) {
                    $id = $this->createNotification(
                        $userId,
                        'payment_due_soon',
                        $title,
                        $message,
                        $actionUrl,
                        'payment_schedule',
                        $schedule->id
                    );
                    if ($id) {
                        $createdCount++;
                    }
                }
            }
        }

        return $createdCount;
    }

    /**
     * Mark a single notification as read.
     */
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

    /**
     * Mark all notifications as read for a user.
     */
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

    /**
     * Get unread notifications count for a user.
     */
    public function getUnreadCount(int $userId): int
    {
        return DB::table('notifications')
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->count();
    }
}
