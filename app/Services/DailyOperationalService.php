<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Expense;
use App\Models\User;
use App\Support\AppointmentTypes;
use App\Support\ClientLifecycle;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DailyOperationalService
{
    /**
     * Compute today's operational snapshot with 60-second caching per user.
     */
    public function getTodaySnapshot(User $user): array
    {
        $today = Carbon::today();
        $todayString = $today->toDateString();
        $cacheKey = "daily_snapshot_{$user->id}_{$todayString}";

        return Cache::remember($cacheKey, 60, function () use ($user, $today, $todayString) {
            // Appointments count: all for admin, or user-attended for non-admin
            $appointmentQuery = Appointment::whereDate('appointment_date', $today);
            if ($user->isPartner()) {
                $appointmentQuery->whereHas('client', fn ($q) => $q->where('partner_id', $user->partner_id));
            } elseif (!$user->isAdmin()) {
                $appointmentQuery->whereHas('users', fn ($q) => $q->where('users.id', $user->id));
            }
            $appointmentsCount = $appointmentQuery->count();

            // Pending follow-ups (next_follow_up_date <= today)
            $followUpQuery = DB::table('follow_ups')
                ->join('clients', 'clients.id', '=', 'follow_ups.client_id')
                ->where('clients.status', '!=', 'archived')
                ->whereDate('follow_ups.next_follow_up_date', '<=', $today);

            if ($user->isPartner()) {
                $followUpQuery->where('clients.partner_id', $user->partner_id);
            }
            $pendingFollowUps = $followUpQuery->count();

            // Today's collections
            $paymentQuery = DB::table('payments')
                ->join('clients', 'clients.id', '=', 'payments.client_id')
                ->whereDate('payments.paid_at', $today);

            if ($user->isPartner()) {
                $paymentQuery->where('clients.partner_id', $user->partner_id);
            }
            $todayCollections = (float) $paymentQuery->sum('payments.amount');

            // Today's expenses (visible to user; strictly 0 for partners)
            $todayExpenses = (float) Expense::visibleTo($user)
                ->today($todayString)
                ->sum('amount');

            $todayNet = $todayCollections - $todayExpenses;

            return [
                'appointments_count' => $appointmentsCount,
                'installation_appointments_today' => $this->getTodayInstallationAppointments($user)->count(),
                'installed_free_clients' => $this->getInstalledFreeClientsCount($user),
                'decision_pending_clients' => $this->getDecisionPendingClientsCount($user),
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
        $query = Appointment::with(['client', 'users'])
            ->whereDate('appointment_date', Carbon::today());

        if ($user->isPartner()) {
            $query->whereHas('client', fn ($q) => $q->where('partner_id', $user->partner_id));
        }

        return $query->orderBy('appointment_time')->get();
    }

    public function getTodayInstallationAppointments(User $user): Collection
    {
        $query = Appointment::with(['client', 'users'])
            ->whereDate('appointment_date', Carbon::today())
            ->where('appointment_type', AppointmentTypes::INSTALLATION);

        if ($user->isPartner()) {
            $query->whereHas('client', fn ($q) => $q->where('partner_id', $user->partner_id));
        }

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
        $query = DB::table('follow_ups')
            ->join('clients', 'clients.id', '=', 'follow_ups.client_id')
            ->whereDate('follow_ups.next_follow_up_date', '<=', Carbon::today());

        if ($user->isPartner()) {
            $query->where('clients.partner_id', $user->partner_id);
        }

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

        if ($user->isPartner()) {
            $query->where('partner_id', $user->partner_id);
        }

        return $query->count();
    }
}
