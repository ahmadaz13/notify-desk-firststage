@props([
    'filters' => [],
    'resetHref' => null,
])

{{--
    Active filters (P12): each applied filter is visible and removable; one link resets to the default.
    $filters: [['label' => 'Stage: Contacting', 'remove' => url-without-it], …].
--}}
@if($filters !== [])
    <div {{ $attributes->merge(['class' => 'notify-filter-chips']) }} role="group" aria-label="{{ __('notify.ui.active_filters') }}" data-active-filters>
        @foreach($filters as $filter)
            <a class="notify-filter-chip" href="{{ $filter['remove'] }}" aria-label="{{ __('notify.ui.remove_filter', ['filter' => $filter['label']]) }}" data-filter-chip>
                <span>{{ $filter['label'] }}</span>
                <x-notify.icon name="x" :size="14" />
            </a>
        @endforeach
        @if($resetHref && count($filters) > 1)
            <a class="notify-filter-chips__reset" href="{{ $resetHref }}" data-filters-reset>{{ __('notify.ui.clear_filters') }}</a>
        @endif
    </div>
@endif
