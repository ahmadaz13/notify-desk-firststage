<?php

namespace App\ViewModels;

use Illuminate\Support\Collection;

final class SalesBillingViewModel
{
    public function __construct(
        private readonly array $snapshot
    ) {
    }

    public static function fromExistingData(array $snapshot): self
    {
        return new self($snapshot);
    }

    /**
     * Raw authoritative snapshot returned by SubscriptionBillingService.
     */
    public function snapshot(): array
    {
        return $this->snapshot;
    }

    public function dueToday(): Collection
    {
        return $this->collection('due_today');
    }

    public function dueSoon7(): Collection
    {
        return $this->collection('due_soon_7');
    }

    public function dueSoon30(): Collection
    {
        return $this->collection('due_soon_30');
    }

    public function pendingCancellations(): Collection
    {
        return $this->collection('pending_cancellation');
    }

    public function generatedPeriods(): Collection
    {
        return $this->collection('generated_periods');
    }

    public function reviewItems(): Collection
    {
        return $this->collection('review_items');
    }

    private function collection(string $key): Collection
    {
        $value = $this->snapshot[$key] ?? null;

        if ($value instanceof Collection) {
            return $value;
        }

        if (is_iterable($value)) {
            return collect($value);
        }

        return collect();
    }
}