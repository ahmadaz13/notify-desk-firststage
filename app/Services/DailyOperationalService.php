<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Expense;
use App\Models\User;
use App\Support\AppointmentTypes;
use App\Support\ClientLifecycle;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DailyOperationalService
{
    public function __construct(private readonly OperationalQueueService $queues)
    {
    }

    /**
     * Compute today's operational snapshot with 60-second caching per user.
     */
    public function getTodaySnapshot(User $user): array
    {
        $this->assertInternalUser($user);

        $today = Carbon::today();
        $todayString = $today->toDateString();
        $cacheKey = "daily_snapshot_{$user->id}_{$todayString}";

        return Cache::remember($cacheKey, 60, function () use ($user, $today, $todayString) {
            // Appointments count: all for admin, or user-attended for non-admin
            $appointmentQuery = Appointment::whereDate('appointment_date', $today);
            if (!$user->isAdmin()) {
                $appointmentQuery->whereHas('users', fn ($q) => $q->where('users.id', $user->id));
            }
            $appointmentsCount = $appointmentQuery->count();

            // Pending follow-ups (next_follow_up_date <= today)
            $followUpQuery = DB::table('follow_ups')
                ->join('clients', 'clients.id', '=', 'follow_ups.client_id')
                ->where('clients.status', '!=', 'archived')
                ->whereNull('follow_ups.completed_at')
                ->whereDate('follow_ups.next_follow_up_date', '<=', $today);

            $pendingFollowUps = $followUpQuery->count();

            // Today's collections
            $paymentQuery = DB::table('payments')
                ->join('clients', 'clients.id', '=', 'payments.client_id')
                ->whereDate('payments.paid_at', $today);

            $todayCollections = (float) $paymentQuery->sum('payments.amount');

            // Today's expenses (visible to user; strictly 0 for partners)
            $todayExpenses = (float) Expense::visibleTo($user)
                ->today($todayString)
                ->sum('amount');

            $todayNet = $todayCollections - $todayExpenses;

            $queueSummary = $this->queues->summary($user, $today->copy()->endOfDay());

            return [
                'new_prospects' => $queueSummary['new_prospects'] ?? 0,
                'calls_due' => $queueSummary['active_contact_queue'] ?? 0,
                'callbacks_due' => $queueSummary['callbacks_due'] ?? 0,
                'appointments_count' => $appointmentsCount,
                'installation_appointments_today' => $this->getTodayInstallationAppointments($user)->count(),
                'installed_free_clients' => $this->getInstalledFreeClientsCount($user),
                'decision_pending_clients' => $this->getDecisionPendingClientsCount($user),
                'trial_followups_due' => $queueSummary['trial_followups_due'] ?? 0,
                'pending_client_reviews' => $queueSummary['client_reviews_pending'] ?? 0,
                'pending_follow_ups' => $pendingFollowUps,
                'today_collections' => $todayCollections,
                'today_expenses' => $todayExpenses,
                'today_net' => $todayNet,
            ];
        });
    }

    /**
     * Get recent operational expenses visible to user with category and payer eager-loaded.
     */
    public function getRecentExpenses(User $user, int $limit = 5): Collection
    {
        $this->assertInternalUser($user);

        return Expense::visibleTo($user)
            ->with(['categoryModel', 'payer'])
            ->orderByDesc('date')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Get today's appointments with client and attendees loaded.
     */
    public function getTodayAppointments(User $user): Collection
    {
        $this->assertInternalUser($user);

        $query = Appointment::with(['client', 'users'])
            ->whereDate('appointment_date', Carbon::today());

        return $query->orderBy('appointment_time')->get();
    }

    public function getTodayInstallationAppointments(User $user): Collection
    {
        $this->assertInternalUser($user);

        $query = Appointment::with(['client', 'users'])
            ->whereDate('appointment_date', Carbon::today())
            ->where('appointment_type', AppointmentTypes::INSTALLATION);

        return $query->orderBy('appointment_time')->get();
    }

    public function getInstalledFreeClientsCount(User $user): int
    {
        return $this->countClientsByStage($user, ClientLifecycle::INSTALLED_FREE);
    }

    public function getDecisionPendingClientsCount(User $user): int
    {
        return $this->countClientsByStage($user, ClientLifecycle::DECISION_PENDING);
    }

    /**
     * Get pending follow-ups due today or overdue.
     */
    public function getPendingFollowUps(User $user, int $limit = 10): Collection
    {
        $this->assertInternalUser($user);

        $query = DB::table('follow_ups')
            ->join('clients', 'clients.id', '=', 'follow_ups.client_id')
            ->whereNull('follow_ups.completed_at')
            ->whereDate('follow_ups.next_follow_up_date', '<=', Carbon::today());

        return $query->select(
            'follow_ups.*',
            'clients.business_name',
            'clients.phone',
            'clients.city_area'
        )
            ->orderBy('follow_ups.next_follow_up_date')
            ->limit($limit)
            ->get();
    }

    /**
     * Clear snapshot cache for a specific user.
     */
    public function clearSnapshotCache(User $user): void
    {
        $todayString = Carbon::today()->toDateString();
        Cache::forget("daily_snapshot_{$user->id}_{$todayString}");
    }

    private function countClientsByStage(User $user, string $stage): int
    {
        $query = DB::table('clients')->where('stage', $stage);

        return $query->count();
    }

    private function assertInternalUser(User $user): void
    {
        if (!$user->isActiveApplicationUser()) {
            throw new AuthorizationException('Daily operations are limited to internal Notify users.');
        }
    }
}
