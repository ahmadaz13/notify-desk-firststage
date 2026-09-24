{{-- One period bar (§14): ReportingPeriod ranges + optional comparison. Expects $period, $action; optional $keep (hidden query), $withComparison. --}}
@php $withComparison ??= false; $keep ??= []; @endphp
<form method="GET" action="{{ $action }}" class="notify-fin-period" x-data="{ range: @js($period->range) }">
    @foreach($keep as $name => $value)
        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
    @endforeach
    <div class="notify-fin-field">
        <label for="fin-range">{{ __('notify.finance.period') }}</label>
        <select id="fin-range" name="range" x-model="range">
            @foreach(['today', 'this_month', 'previous_month', 'this_year', 'previous_year', 'custom_date_range'] as $value)
                <option value="{{ $value }}" @selected($period->range === $value)>{{ __('notify.finance.ranges.'.$value) }}</option>
            @endforeach
        </select>
    </div>
    <div class="notify-fin-field" x-show="range === 'custom_date_range'" @if($period->range !== 'custom_date_range') x-cloak @endif>
        <label for="fin-from">{{ __('notify.finance.from') }}</label>
        <input id="fin-from" type="date" name="date_from" value="{{ request('date_from', $period->start->toDateString()) }}">
    </div>
    <div class="notify-fin-field" x-show="range === 'custom_date_range'" @if($period->range !== 'custom_date_range') x-cloak @endif>
        <label for="fin-to">{{ __('notify.finance.to') }}</label>
        <input id="fin-to" type="date" name="date_to" value="{{ request('date_to', $period->end->toDateString()) }}">
    </div>
    @if($withComparison)
        <div class="notify-fin-field">
            <label for="fin-comparison">{{ __('notify.finance.comparison') }}</label>
            <select id="fin-comparison" name="comparison">
                @foreach(['none', 'previous_period', 'previous_year_same_period'] as $value)
                    <option value="{{ $value }}" @selected($period->comparisonMode === $value)>{{ __('notify.finance.comparisons.'.$value) }}</option>
                @endforeach
            </select>
        </div>
    @endif
    <button type="submit" class="notify-button notify-button--soft">{{ __('notify.actions.apply') }}</button>
    <p class="notify-fin-period__label" dir="ltr">{{ $period->label() }}</p>
</form>
