<?php

namespace App\ViewModels;

use Illuminate\Support\Collection;

/**
 * Today page (P11): the operational queue plus compact signals. Only the active mode's projection
 * is computed; the other is an empty shape.
 */
class TodayViewModel
{
    /** Overdue cards shown before "Show all", so Next stays on the first phone screen. */
    public const OVERDUE_VISIBLE = 2;

    public function __construct(
        private readonly array $todayProjection,
        private readonly array $workProjection,
        public readonly array $signals = [],
    ) {}

    public static function make(array $todayProjection, array $workProjection, array $signals = []): self
    {
        return new self($todayProjection, $workProjection, $signals);
    }

    public function overdueItems(): Collection
    {
        return $this->todayProjection['overdue'] ?? collect();
    }

    public function nextItems(): Collection
    {
        return $this->todayProjection['next'] ?? collect();
    }

    public function laterTodayItems(): Collection
    {
        return $this->todayProjection['later_today'] ?? collect();
    }

    public function hasTodayWork(): bool
    {
        return ($this->todayProjection['counts']['total'] ?? 0) > 0;
    }

    public function readyToContact(): int
    {
        return (int) ($this->todayProjection['ready_to_contact'] ?? 0);
    }

    public function workOverdueItems(): Collection
    {
        return $this->workProjection['overdue'] ?? collect();
    }

    public function workTodayItems(): Collection
    {
        return $this->workProjection['today'] ?? collect();
    }

    public function workUpcomingItems(): Collection
    {
        return $this->workProjection['upcoming'] ?? collect();
    }

    public function workFilter(): string
    {
        return $this->workProjection['filter'] ?? 'all';
    }

    public function workFilterCounts(): array
    {
        return $this->workProjection['filter_counts'] ?? [];
    }

    /** @return array{total: int, breakdown: array<string, int>} */
    public function completed(): array
    {
        return $this->signals['completed'] ?? ['total' => 0, 'breakdown' => []];
    }

    public function pendingConfirmations(): int
    {
        return (int) ($this->signals['pending_confirmations'] ?? 0);
    }

    public function myPendingReceipts(): int
    {
        return (int) ($this->signals['my_pending_receipts'] ?? 0);
    }

    public function nextUpcoming(): ?array
    {
        return $this->signals['next_upcoming'] ?? null;
    }

    public static function emptyToday(): array
    {
        return ['overdue' => collect(), 'next' => collect(), 'later_today' => collect(), 'ready_to_contact' => 0, 'counts' => ['total' => 0]];
    }

    public static function emptyWork(): array
    {
        return ['filter' => 'all', 'overdue' => collect(), 'today' => collect(), 'upcoming' => collect(), 'filter_counts' => [], 'counts' => ['total' => 0]];
    }
}
