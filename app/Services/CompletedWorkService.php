<?php

namespace App\Services;

use App\Models\PaymentReceiptConfirmation;
use App\Models\User;
use App\Support\OperationalTime;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * "Completed today" for one user (P11 Today signal; the Profile summary reuses this authority).
 *
 * Read-only counts over the records each workflow already writes when work is done — no task table,
 * no stored counters. Only the signed-in user's own work is counted; nobody else's is exposed.
 * The operational day is Asia/Amman.
 */
class CompletedWorkService
{
    public const TYPES = ['calls', 'appointments', 'installations', 'follow_ups', 'collections', 'reviews'];

    /** @return array{total: int, breakdown: array<string, int>} */
    public function forUser(User $user, ?Carbon $now = null): array
    {
        $now = $now ? OperationalTime::inZone($now) : OperationalTime::now();
        $range = [$now->copy()->startOfDay()->format('Y-m-d H:i:s'), $now->copy()->endOfDay()->format('Y-m-d H:i:s')];

        $breakdown = [
            // Record Call (contact attempts are only written by the call workflow).
            'calls' => DB::table('contact_attempts')->where('user_id', $user->id)->whereBetween('created_at', $range)->count(),
            // Appointment results, including "did not attend" (MeetingOutcomeService).
            'appointments' => DB::table('meeting_outcomes')->where('user_id', $user->id)->whereBetween('created_at', $range)->count(),
            'installations' => DB::table('installations')->where('installed_by', $user->id)->whereBetween('created_at', $range)->count(),
            'follow_ups' => DB::table('follow_ups')->where('completed_by', $user->id)->whereBetween('completed_at', $range)->count(),
            // Confirmed payments recorded (owner path, incl. approvals) + receipts submitted (Staff path).
            'collections' => DB::table('payments')->where('recorded_by', $user->id)->whereBetween('created_at', $range)->count()
                + PaymentReceiptConfirmation::query()
                    ->where('submitted_by', $user->id)
                    ->where('status', '!=', PaymentReceiptConfirmation::STATUS_CANCELLED)
                    ->whereBetween('created_at', $range)
                    ->count(),
            'reviews' => DB::table('client_review_items')->where('resolved_by', $user->id)->whereBetween('resolved_at', $range)->count(),
        ];

        return ['total' => array_sum($breakdown), 'breakdown' => $breakdown];
    }
}
