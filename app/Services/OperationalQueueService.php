<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\ClientReviewItem;
use App\Models\User;
use App\Support\AppointmentTypes;
use App\Support\ClientLifecycle;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OperationalQueueService
{
    public function queues(User $user, ?Carbon $at = null): array
    {
        $this->assertInternalUser($user);
        $at ??= now();
        $today = $at->toDateString();

        return [
            'new_prospects' => $this->scopeClients($user)
                ->where('stage', ClientLifecycle::PROSPECT)
                ->orderBy('created_at')
                ->get(),
            'active_contact_queue' => $this->activeContactQueue($user, $at),
            'callbacks_due' => $this->dueFollowUps($user, $at, false),
            'appointments_today' => $this->appointments($user, $today, false),
            'installations_today' => $this->appointments($user, $today, true),
            'trial_followups_due' => $this->dueFollowUps($user, $at, true),
            'decision_pending' => $this->scopeClients($user)
                ->where('stage', ClientLifecycle::DECISION_PENDING)
                ->orderBy('updated_at')
                ->get(),
            'client_reviews_pending' => ClientReviewItem::with('client')
                ->where('status', ClientReviewItem::STATUS_PENDING)
                ->whereHas('client', fn (Builder $query) => $this->applyClientScope($query, $user))
                ->orderBy('created_at')
                ->get(),
        ];
    }

    public function summary(User $user, ?Carbon $at = null): array
    {
        $this->assertInternalUser($user);

        return collect($this->queues($user, $at))
            ->map(fn ($items) => $items instanceof Collection ? $items->count() : count($items))
            ->all();
    }

    public function nextActionFor(Client $client, ?Carbon $at = null): array
    {
        $at ??= now();
        $stage = ClientLifecycle::normalizeStage($client->stage, $client->status);

        if ($stage === ClientLifecycle::CLOSED) {
            return ['label' => 'Closed', 'queue' => null, 'at' => null];
        }

        if ($stage === ClientLifecycle::SUBSCRIBER) {
            return ['label' => 'Subscriber', 'queue' => null, 'at' => null];
        }

        $pendingReview = ClientReviewItem::where('client_id', $client->id)->where('status', ClientReviewItem::STATUS_PENDING)->oldest()->first();
        if ($pendingReview) {
            return ['label' => 'Needs review', 'queue' => 'client_reviews_pending', 'at' => $pendingReview->created_at];
        }

        $followUp = DB::table('follow_ups')
            ->where('client_id', $client->id)
            ->whereNull('completed_at')
            ->orderBy('follow_up_date_time')
            ->first();
        if ($followUp) {
            return [
                'label' => $followUp->installation_id ? 'Trial follow-up' : 'Callback at date/time',
                'queue' => Carbon::parse($followUp->follow_up_date_time)->lessThanOrEqualTo($at) ? ($followUp->installation_id ? 'trial_followups_due' : 'callbacks_due') : null,
                'at' => $followUp->follow_up_date_time,
            ];
        }

        $appointment = Appointment::where('client_id', $client->id)
            ->whereIn('status', AppointmentTypes::activeStatuses())
            ->orderBy('appointment_date')
            ->orderBy('appointment_time')
            ->first();
        if ($appointment) {
            return [
                'label' => $appointment->appointment_type === AppointmentTypes::INSTALLATION ? 'Prepare installation' : 'Attend appointment',
                'queue' => $appointment->appointment_type === AppointmentTypes::INSTALLATION ? 'installations_today' : 'appointments_today',
                'at' => trim($appointment->appointment_date?->toDateString().' '.$appointment->appointment_time),
            ];
        }

        return match ($stage) {
            ClientLifecycle::DECISION_PENDING => ['label' => 'Waiting for decision', 'queue' => 'decision_pending', 'at' => null],
            ClientLifecycle::INSTALLED_FREE => ['label' => 'Trial follow-up', 'queue' => 'trial_followups_due', 'at' => null],
            default => ['label' => 'Call client', 'queue' => 'active_contact_queue', 'at' => null],
        };
    }

    private function activeContactQueue(User $user, Carbon $at): Collection
    {
        $futureFollowClientIds = DB::table('follow_ups')
            ->whereNull('completed_at')
            ->where('follow_up_date_time', '>', $at)
            ->pluck('client_id')
            ->all();

        $pendingReviewClientIds = ClientReviewItem::where('status', ClientReviewItem::STATUS_PENDING)
            ->pluck('client_id')
            ->all();

        return $this->scopeClients($user)
            ->whereIn('stage', [ClientLifecycle::PROSPECT, ClientLifecycle::CONTACTING])
            ->whereNotIn('id', array_unique(array_merge($futureFollowClientIds, $pendingReviewClientIds)))
            ->orderByRaw('(select max(created_at) from contact_attempts where contact_attempts.client_id = clients.id) asc')
            ->orderBy('created_at')
            ->get();
    }

    private function dueFollowUps(User $user, Carbon $at, bool $installationOnly): Collection
    {
        $query = DB::table('follow_ups')
            ->join('clients', 'clients.id', '=', 'follow_ups.client_id')
            ->where('clients.status', '!=', 'archived')
            ->whereNull('follow_ups.completed_at')
            ->whereDate('follow_ups.next_follow_up_date', '<=', $at->toDateString());

        if ($installationOnly) {
            $query->whereNotNull('follow_ups.installation_id');
        } else {
            $query->whereNull('follow_ups.installation_id');
        }

        return $query->select('follow_ups.*', 'clients.business_name', 'clients.phone', 'clients.stage')
            ->orderBy('follow_ups.follow_up_date_time')
            ->get();
    }

    private function appointments(User $user, string $date, bool $installationOnly): Collection
    {
        $query = Appointment::with(['client', 'users'])
            ->whereDate('appointment_date', $date);

        if ($installationOnly) {
            $query->where('appointment_type', AppointmentTypes::INSTALLATION);
        } else {
            $query->where('appointment_type', '!=', AppointmentTypes::INSTALLATION);
        }

        return $query->orderBy('appointment_time')->get();
    }

    private function scopeClients(User $user): Builder
    {
        return $this->applyClientScope(Client::query(), $user)
            ->whereNotIn('stage', [ClientLifecycle::CLOSED, ClientLifecycle::SUBSCRIBER])
            ->where('status', '!=', 'archived');
    }

    private function applyClientScope(Builder $query, User $user): Builder
    {
        return $query;
    }

    private function assertInternalUser(User $user): void
    {
        if (!$user->isActiveApplicationUser()) {
            throw new AuthorizationException('Operational queues are limited to internal Notify users.');
        }
    }
}
