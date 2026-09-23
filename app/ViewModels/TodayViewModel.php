<?php

namespace App\ViewModels;

use Illuminate\Support\Collection;

class TodayViewModel
{
    public function __construct(
        private readonly array $todayProjection,
        private readonly array $workProjection
    ) {}

    public static function make(array $todayProjection, array $workProjection): self
    {
        return new self($todayProjection, $workProjection);
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
}
