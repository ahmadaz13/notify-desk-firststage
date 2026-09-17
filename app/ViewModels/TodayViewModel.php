<?php

namespace App\ViewModels;

use Carbon\Carbon;
use Illuminate\Support\Collection;

class TodayViewModel
{
    public function __construct(
        private readonly array $snapshot,
        private readonly array $queues,
        private readonly Collection $notifications,
        private readonly Collection $recentExpenses
    ) {
    }

    public static function make(array $snapshot, array $queues, mixed $notifications, mixed $recentExpenses): self
    {
        return new self(
            $snapshot,
            $queues,
            collect($notifications),
            collect($recentExpenses)
        );
    }

    public function totalOpenActions(): int
    {
        return collect($this->primaryGroups())
            ->flatMap(fn (array $group) => $group['queues'])
            ->sum(fn (array $queue) => $queue['count']);
    }

    public function totalWorkActions(): int
    {
        return collect($this->workCategories())
            ->flatMap(fn (array $category) => $category['queues'])
            ->sum(fn (array $queue) => $queue['count']);
    }

    public function primaryGroups(): array
    {
        return [
            [
                'title' => 'Now',
                'description' => 'Work that is due today or already waiting.',
                'queues' => [
                    $this->queue('Callbacks Due', 'callbacks_due', 'activity', 'warning', 'Call clients waiting for a callback.'),
                    $this->queue('Appointments Today', 'appointments_today', 'clipboard-list', 'info', 'Attend or record outcomes for today.'),
                    $this->queue('Installations Today', 'installations_today', 'wrench', 'success', 'Prepare scheduled installation visits.'),
                ],
            ],
            [
                'title' => 'Follow-up Required',
                'description' => 'Items that need a human review or decision loop.',
                'queues' => [
                    $this->queue('Trial Follow-ups', 'trial_followups_due', 'activity', 'warning', 'Follow up after free installation.'),
                    $this->queue('Decision Pending', 'decision_pending', 'clipboard-list', 'neutral', 'Clients waiting on a decision.'),
                    $this->queue('Client Reviews', 'client_reviews_pending', 'bell', 'danger', 'Wrong, invalid, or not-interested reviews.'),
                ],
            ],
            [
                'title' => 'Money Requiring Attention',
                'description' => 'Displayed only when an authoritative queue projection is available.',
                'queues' => [
                    $this->deferredQueue('Renewals Due', 'briefcase'),
                    $this->deferredQueue('Overdue Collections', 'wallet'),
                ],
            ],
            [
                'title' => 'New',
                'description' => 'Fresh prospects that have not moved into a deeper workflow.',
                'queues' => [
                    $this->queue('New Prospects', 'new_prospects', 'users', 'info', 'Start first contact from the client file.'),
                ],
            ],
        ];
    }

    public function workCategories(): array
    {
        return [
            [
                'key' => 'calls_outcomes',
                'title' => 'Calls & Outcomes',
                'description' => 'Contact work grouped around one next human action.',
                'icon' => 'phone',
                'queues' => [
                    $this->queue('Active Contact Queue', 'active_contact_queue', 'phone', 'info', 'Call prospects that are ready for an outcome.'),
                    $this->queue('Callbacks Due', 'callbacks_due', 'activity', 'warning', 'Clients waiting for a scheduled callback.'),
                    $this->queue('Client Reviews', 'client_reviews_pending', 'bell', 'danger', 'Wrong, invalid, or not-interested reviews.'),
                ],
            ],
            [
                'key' => 'appointments',
                'title' => 'Appointments',
                'description' => 'Attend, reschedule, or record outcomes from existing appointment routes.',
                'icon' => 'calendar',
                'queues' => [
                    $this->queue('Appointments Today', 'appointments_today', 'calendar', 'info', 'Attend or record outcomes for today.'),
                ],
            ],
            [
                'key' => 'installations',
                'title' => 'Installations',
                'description' => 'Prepare installation visits and complete free installs without billing side effects.',
                'icon' => 'wrench',
                'queues' => [
                    $this->queue('Installations Today', 'installations_today', 'wrench', 'success', 'Prepare scheduled installation visits.'),
                ],
            ],
            [
                'key' => 'followups',
                'title' => 'Follow-ups',
                'description' => 'Trial and decision loops that need a next operational touch.',
                'icon' => 'clipboard-list',
                'queues' => [
                    $this->queue('Trial Follow-ups', 'trial_followups_due', 'activity', 'warning', 'Follow up after free installation.'),
                    $this->queue('Decision Pending', 'decision_pending', 'clipboard-list', 'neutral', 'Clients waiting on a decision.'),
                ],
            ],
        ];
    }

    private function deferredQueue(string $title, string $icon): array
    {
        return [
            'key' => str($title)->lower()->replace(' ', '_')->toString(),
            'title' => $title,
            'icon' => $icon,
            'variant' => 'neutral',
            'description' => 'Deferred until the approved billing or collections projection is exposed here.',
            'count' => 0,
            'items' => [],
            'deferred' => true,
        ];
    }

    public function secondarySnapshots(): array
    {
        return [
            [
                'label' => 'Recent Activity',
                'value' => (string) $this->notifications->count(),
                'caption' => 'Unread notifications',
                'variant' => 'info',
            ],
            [
                'label' => 'Subscription Snapshot',
                'value' => (string) ($this->snapshot['installed_free_clients'] ?? 0),
                'caption' => 'Free installs awaiting follow-up',
                'variant' => 'success',
            ],
            [
                'label' => 'Collections Snapshot',
                'value' => $this->money($this->snapshot['today_collections'] ?? 0),
                'caption' => 'Collected today from existing service data',
                'variant' => 'success',
            ],
            [
                'label' => 'Cash Snapshot',
                'value' => $this->money($this->snapshot['today_net'] ?? 0),
                'caption' => 'Today net from existing dashboard service data',
                'variant' => (($this->snapshot['today_net'] ?? 0) < 0) ? 'danger' : 'neutral',
            ],
        ];
    }

    public function recentActivity(): array
    {
        return $this->notifications
            ->take(5)
            ->map(fn ($notification) => [
                'title' => (string) ($notification->title ?? 'Notification'),
                'meta' => (string) ($notification->created_at ?? ''),
                'body' => (string) ($notification->message ?? ''),
                'href' => $notification->action_url ?? null,
            ])
            ->values()
            ->all();
    }

    public function recentExpenses(): array
    {
        return $this->recentExpenses
            ->take(5)
            ->map(fn ($expense) => [
                'title' => (string) ($expense->categoryModel?->name ?? $expense->category ?? 'Expense'),
                'meta' => trim(($expense->payer?->name ? 'By '.$expense->payer->name.' · ' : '').($expense->date?->format('Y-m-d') ?? '')),
                'body' => (string) ($expense->description ?: 'No description'),
                'amount' => $this->money($expense->amount ?? 0),
            ])
            ->values()
            ->all();
    }

    private function queue(string $title, string $key, string $icon, string $variant, string $description): array
    {
        $items = collect($this->queues[$key] ?? []);

        return [
            'key' => $key,
            'title' => $title,
            'icon' => $icon,
            'variant' => $variant,
            'description' => $description,
            'count' => $items->count(),
            'items' => $items->take(3)->map(fn ($item) => $this->queueItem($item, $key))->values()->all(),
        ];
    }

    private function queueItem(mixed $item, string $queue): array
    {
        $client = $item->client ?? null;
        $clientId = $item->client_id ?? $client?->id ?? $item->id ?? null;
        $name = $client?->business_name ?? $item->business_name ?? 'Client';
        $phone = $client?->phone ?? $item->phone ?? null;
        $at = $this->firstDate($item, ['follow_up_date_time', 'next_follow_up_date', 'appointment_date', 'created_at', 'updated_at']);
        $description = $item->next_action
            ?? $item->note
            ?? $item->appointment_type
            ?? $item->stage
            ?? null;

        return [
            'title' => (string) $name,
            'description' => $description ? (string) $description : $this->descriptionForQueue($queue),
            'meta' => $at,
            'phone' => $phone,
            'href' => $clientId ? route('clients.show', $clientId) : null,
        ];
    }

    private function firstDate(mixed $item, array $fields): ?string
    {
        foreach ($fields as $field) {
            if (empty($item->{$field})) {
                continue;
            }

            $value = $item->{$field};
            if ($value instanceof Carbon) {
                return $value->format('Y-m-d H:i');
            }

            return (string) $value;
        }

        return null;
    }

    private function descriptionForQueue(string $queue): string
    {
        return match ($queue) {
            'appointments_today' => 'Appointment scheduled today',
            'installations_today' => 'Installation scheduled today',
            'client_reviews_pending' => 'Review required',
            'new_prospects' => 'New prospect',
            default => 'Operational follow-up',
        };
    }

    private function money(mixed $amount): string
    {
        return number_format((float) $amount, 2).' JOD';
    }
}
