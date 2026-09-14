<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ReportingPeriod
{
    public function __construct(
        public readonly Carbon $start,
        public readonly Carbon $end,
        public readonly string $range,
        public readonly string $comparisonMode = 'none'
    ) {
    }

    public static function fromRequest(Request|array $source): self
    {
        $input = $source instanceof Request ? $source->all() : $source;
        $range = $input['range'] ?? 'this_month';
        $comparison = $input['comparison'] ?? 'none';
        $today = Carbon::today();

        [$start, $end] = match ($range) {
            'today' => [$today->copy()->startOfDay(), $today->copy()->endOfDay()],
            'previous_month' => [$today->copy()->subMonthNoOverflow()->startOfMonth(), $today->copy()->subMonthNoOverflow()->endOfMonth()->endOfDay()],
            'this_year' => [$today->copy()->startOfYear(), $today->copy()->endOfYear()->endOfDay()],
            'previous_year' => [$today->copy()->subYear()->startOfYear(), $today->copy()->subYear()->endOfYear()->endOfDay()],
            'custom_date_range' => self::customRange($input),
            default => [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()->endOfDay()],
        };

        if (! in_array($comparison, ['none', 'previous_period', 'previous_year_same_period'], true)) {
            $comparison = 'none';
        }

        return new self($start, $end, $range, $comparison);
    }

    public function previousComparison(): ?self
    {
        if ($this->comparisonMode === 'none') {
            return null;
        }

        if ($this->comparisonMode === 'previous_year_same_period') {
            return new self(
                $this->start->copy()->subYear(),
                $this->end->copy()->subYear(),
                $this->range,
                'none'
            );
        }

        $days = $this->start->diffInDays($this->end) + 1;
        $end = $this->start->copy()->subDay()->endOfDay();
        $start = $end->copy()->subDays($days - 1)->startOfDay();

        return new self($start, $end, $this->range, 'none');
    }

    public function label(): string
    {
        return $this->start->toDateString().' → '.$this->end->toDateString();
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private static function customRange(array $input): array
    {
        if (empty($input['date_from']) || empty($input['date_to'])) {
            throw ValidationException::withMessages(['date_from' => 'Custom reporting range requires both dates.']);
        }

        $start = Carbon::parse($input['date_from'])->startOfDay();
        $end = Carbon::parse($input['date_to'])->endOfDay();
        if ($end->lt($start)) {
            throw ValidationException::withMessages(['date_to' => 'Report end date must be on or after start date.']);
        }

        return [$start, $end];
    }
}
