@extends('layouts.app')

{{-- Client list (P10, §5, §21): segments, search, one operational signal per client. --}}
@section('content')
@php
    $filters = $list->filters;
    $isFiltered = $filters['q'] !== '' || $list->hasActiveFilters;
    $emptyKey = $isFiltered ? 'search' : $list->segment;
@endphp
<div class="notify-clients" data-client-list data-segment="{{ $list->segment }}">
    <x-notify.page-header :title="__('notify.clients.title')" :description="__('notify.client_hub.list.description')">
        @can('create', \App\Models\Client::class)
            <x-slot:actions>
                <x-notify.button :href="route('clients.create')" variant="primary" icon="plus" data-page-action="add-client">{{ __('notify.actions.add_client') }}</x-notify.button>
            </x-slot:actions>
        @endcan
    </x-notify.page-header>

    <nav class="notify-segments" aria-label="{{ __('notify.client_hub.list.segments_label') }}" data-client-segments
         x-data x-init="(() => { const current = $el.querySelector('[aria-current]'); if (!current || $el.scrollWidth <= $el.clientWidth) return; const box = $el.getBoundingClientRect(); const item = current.getBoundingClientRect(); $el.scrollLeft += (item.left + item.width / 2) - (box.left + box.width / 2); })()">
        @foreach($list->segments as $segment)
            <a href="{{ $segment['href'] }}" class="notify-segments__item {{ $segment['active'] ? 'is-active' : '' }}" data-segment="{{ $segment['key'] }}" @if($segment['active']) aria-current="page" @endif>
                <span>{{ $segment['label'] }}</span>
                <span class="notify-segments__count" dir="ltr">{{ $segment['count'] }}</span>
            </a>
        @endforeach
    </nav>

    @php
        // Active filters as removable chips (P12); search keeps its own clear control.
        $baseQuery = array_filter(['view' => $list->segment, 'q' => $filters['q'] ?: null, 'stage' => $filters['stage'] ?? null, 'category' => $filters['category'] ?? null, 'area' => $filters['area'] ?? null]);
        $activeChips = [];
        if (filled($filters['stage'] ?? null)) {
            $activeChips[] = ['label' => __('notify.client_hub.list.stage').': '.($list->filterOptions['stages'][$filters['stage']] ?? $filters['stage']), 'remove' => route('clients.index', \Illuminate\Support\Arr::except($baseQuery, 'stage'))];
        }
        foreach (['category', 'area'] as $filterKey) {
            if (filled($filters[$filterKey] ?? null)) {
                $activeChips[] = ['label' => __('notify.client_hub.list.'.$filterKey).': '.$filters[$filterKey], 'remove' => route('clients.index', \Illuminate\Support\Arr::except($baseQuery, $filterKey))];
            }
        }
    @endphp

    <form method="GET" action="{{ route('clients.index') }}" class="notify-client-search" role="search" data-client-search>
        <input type="hidden" name="view" value="{{ $list->segment }}">
        <x-notify.search id="client-search" :value="$filters['q']" :label="__('notify.client_hub.list.search_label')"
            :placeholder="__('notify.client_hub.list.search_placeholder')"
            :clear-href="route('clients.index', \Illuminate\Support\Arr::except($baseQuery, 'q'))" />
        <button type="submit" class="notify-visually-hidden">{{ __('notify.client_hub.list.search_submit') }}</button>
        <button type="button" class="notify-button notify-button--ghost notify-client-search__filters {{ $list->hasActiveFilters ? 'is-active' : '' }}" data-open-sheet="client-filters" aria-haspopup="dialog" data-client-filters-open>
            <x-notify.icon name="settings" :size="18" />
            <span>{{ __('notify.client_hub.list.filters') }}</span>
            @if($activeChips !== [])<span class="notify-count-badge" dir="ltr">{{ count($activeChips) }}</span>@endif
        </button>

        <x-notify.sheet id="client-filters" size="sm" :title="__('notify.client_hub.list.filters_title')" data-client-filters>
            @if($list->filterOptions['stages'] !== [])
                <x-notify.form-field :label="__('notify.client_hub.list.stage')" for="client-filter-stage">
                    <select id="client-filter-stage" class="notify-input" name="stage">
                        <option value="">{{ __('notify.client_hub.list.any') }}</option>
                        @foreach($list->filterOptions['stages'] as $value => $label)
                            <option value="{{ $value }}" @selected($filters['stage'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </x-notify.form-field>
            @endif
            <x-notify.form-field :label="__('notify.client_hub.list.category')" for="client-filter-category">
                <select id="client-filter-category" class="notify-input" name="category">
                    <option value="">{{ __('notify.client_hub.list.any') }}</option>
                    @foreach($list->filterOptions['categories'] as $category)
                        <option value="{{ $category }}" @selected($filters['category'] === $category)>{{ $category }}</option>
                    @endforeach
                </select>
            </x-notify.form-field>
            <x-notify.form-field :label="__('notify.client_hub.list.area')" for="client-filter-area">
                <select id="client-filter-area" class="notify-input" name="area">
                    <option value="">{{ __('notify.client_hub.list.any') }}</option>
                    @foreach($list->filterOptions['areas'] as $area)
                        <option value="{{ $area }}" @selected($filters['area'] === $area)>{{ $area }}</option>
                    @endforeach
                </select>
            </x-notify.form-field>
            <x-slot:footer>
                <a class="notify-button notify-button--ghost" href="{{ route('clients.index', ['view' => $list->segment]) }}" data-filters-reset>{{ __('notify.client_hub.list.clear') }}</a>
                <button type="submit" class="notify-button notify-button--primary">{{ __('notify.client_hub.list.apply') }}</button>
            </x-slot:footer>
        </x-notify.sheet>
    </form>

    <x-notify.filter-chips :filters="$activeChips" :reset-href="route('clients.index', ['view' => $list->segment])" />

    @if($isFiltered)
        <p class="notify-client-results" role="status">
            <span>{{ __('notify.client_hub.list.results', ['count' => $list->clients->total()]) }}</span>
            <a href="{{ route('clients.index', ['view' => $list->segment]) }}">{{ __('notify.client_hub.list.clear') }}</a>
        </p>
    @endif

    @if($list->rows === [])
        <x-notify.empty-state
            :title="__('notify.client_hub.empty.'.$emptyKey.'_title')"
            :message="__('notify.client_hub.empty.'.$emptyKey.'_message')"
            :action-href="! $isFiltered && in_array($list->segment, ['prospects', 'all'], true) && auth()->user()->can('create', \App\Models\Client::class) ? route('clients.create') : null"
            :action-label="__('notify.actions.add_client')"
            icon="users"
        />
    @else
        <div class="notify-client-list" role="table" aria-label="{{ __('notify.client_hub.segments.'.$list->segment) }}">
            <div class="notify-client-list__head" role="row">
                <span role="columnheader">{{ __('notify.client_hub.list.business') }}</span>
                <span role="columnheader">{{ __('notify.client_hub.list.status') }}</span>
                <span role="columnheader">{{ __('notify.client_hub.list.contact') }}</span>
                <span role="columnheader">{{ __('notify.client_hub.list.next') }}</span>
                <span role="columnheader"><span class="notify-visually-hidden">{{ __('notify.client_hub.actions.more') }}</span></span>
            </div>
            @foreach($list->rows as $row)
                <article class="notify-client-row" role="row" data-client-row="{{ $row['id'] }}" data-client-segment="{{ $row['segment'] }}">
                    <div class="notify-client-row__identity" role="cell">
                        <span class="notify-avatar" aria-hidden="true">{{ $row['initial'] }}</span>
                        <div class="notify-client-row__names">
                            <a class="notify-client-row__link" href="{{ $row['href'] }}">{{ $row['business_name'] }}</a>
                            @if($row['category'] || $row['area'])
                                <span class="notify-client-row__meta">{{ collect([$row['category'], $row['area']])->filter()->join(' · ') }}</span>
                            @endif
                        </div>
                    </div>
                    <div class="notify-client-row__stage" role="cell">
                        <span class="notify-status notify-status--{{ $row['stage_tone'] }}"><x-notify.icon :name="$row['stage_icon']" :size="14" />{{ $row['stage_label'] }}</span>
                    </div>
                    <div class="notify-client-row__phone" role="cell">
                        @if($row['phone'])<span dir="ltr">{{ $row['phone'] }}</span>@endif
                    </div>
                    <div class="notify-client-row__signal notify-client-row__signal--{{ $row['signal']['tone'] ?? 'neutral' }}" role="cell" data-client-signal>
                        @if($row['signal'])
                            <x-notify.icon :name="$row['signal']['icon']" :size="16" />
                            <span class="notify-client-row__signal-text">
                                <strong>
                                    @isset($row['signal']['money_minor'])<x-notify.money :minor="$row['signal']['money_minor']" /> ·@endisset
                                    {{ $row['signal']['text'] }}
                                </strong>
                                @if(! empty($row['signal']['meta']))<small>{{ $row['signal']['meta'] }}</small>@endif
                            </span>
                        @endif
                    </div>
                    <div class="notify-client-row__action" role="cell">
                        @if($row['action'])
                            <a class="notify-button notify-button--soft notify-button--sm" href="{{ $row['action']['href'] }}" data-client-quick-action>
                                <x-notify.icon :name="$row['action']['icon']" :size="16" />
                                <span>{{ $row['action']['label'] }}</span>
                            </a>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>

        {{ $list->clients->links() }}
    @endif
</div>
@endsection
