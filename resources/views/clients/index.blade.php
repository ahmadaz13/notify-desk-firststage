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

    <form method="GET" action="{{ route('clients.index') }}" class="notify-client-search" role="search" x-data="{ filtersOpen: false }" @keydown.escape="filtersOpen = false">
        <input type="hidden" name="view" value="{{ $list->segment }}">
        <label class="notify-visually-hidden" for="client-search">{{ __('notify.client_hub.list.search_label') }}</label>
        <div class="notify-client-search__field">
            <x-notify.icon name="search" :size="18" class="notify-client-search__icon" />
            <input id="client-search" type="search" name="q" value="{{ $filters['q'] }}" placeholder="{{ __('notify.client_hub.list.search_placeholder') }}" autocomplete="off" enterkeyhint="search">
        </div>
        <button type="submit" class="notify-button notify-button--secondary notify-client-search__submit">{{ __('notify.client_hub.list.search_submit') }}</button>
        <button type="button" class="notify-button notify-button--ghost notify-client-search__filters {{ $list->hasActiveFilters ? 'is-active' : '' }}" @click="filtersOpen = true" aria-haspopup="dialog" :aria-expanded="filtersOpen.toString()" aria-expanded="false" data-client-filters-open>
            <x-notify.icon name="settings" :size="18" />
            <span>{{ __('notify.client_hub.list.filters') }}</span>
            @if($list->hasActiveFilters)<span class="notify-count-badge" aria-hidden="true">•</span>@endif
        </button>

        <div class="notify-sheet" x-show="filtersOpen" x-cloak x-transition.opacity.duration.120ms role="dialog" aria-modal="true" aria-labelledby="client-filters-title" data-client-filters>
            <button type="button" class="notify-sheet__backdrop" @click="filtersOpen = false" tabindex="-1" aria-label="{{ __('notify.client_hub.actions.cancel') }}"></button>
            <div class="notify-sheet__panel">
                <header class="notify-sheet__header">
                    <h2 id="client-filters-title">{{ __('notify.client_hub.list.filters_title') }}</h2>
                    <button type="button" class="notify-icon-button" @click="filtersOpen = false" aria-label="{{ __('notify.client_hub.actions.cancel') }}"><x-notify.icon name="x" /></button>
                </header>
                <div class="notify-sheet__body">
                    @if($list->filterOptions['stages'] !== [])
                        <label class="notify-field">
                            <span>{{ __('notify.client_hub.list.stage') }}</span>
                            <select name="stage">
                                <option value="">{{ __('notify.client_hub.list.any') }}</option>
                                @foreach($list->filterOptions['stages'] as $value => $label)
                                    <option value="{{ $value }}" @selected($filters['stage'] === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                    @endif
                    <label class="notify-field">
                        <span>{{ __('notify.client_hub.list.category') }}</span>
                        <select name="category">
                            <option value="">{{ __('notify.client_hub.list.any') }}</option>
                            @foreach($list->filterOptions['categories'] as $category)
                                <option value="{{ $category }}" @selected($filters['category'] === $category)>{{ $category }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="notify-field">
                        <span>{{ __('notify.client_hub.list.area') }}</span>
                        <select name="area">
                            <option value="">{{ __('notify.client_hub.list.any') }}</option>
                            @foreach($list->filterOptions['areas'] as $area)
                                <option value="{{ $area }}" @selected($filters['area'] === $area)>{{ $area }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
                <footer class="notify-sheet__footer">
                    <a class="notify-button notify-button--ghost" href="{{ route('clients.index', ['view' => $list->segment]) }}">{{ __('notify.client_hub.list.clear') }}</a>
                    <button type="submit" class="notify-button notify-button--primary">{{ __('notify.client_hub.list.apply') }}</button>
                </footer>
            </div>
        </div>
    </form>

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

        @if($list->clients->hasPages())
            <nav class="notify-client-pagination" aria-label="{{ __('notify.client_hub.list.results', ['count' => $list->clients->total()]) }}">
                @if($list->clients->previousPageUrl())
                    <a class="notify-button notify-button--ghost" href="{{ $list->clients->previousPageUrl() }}" rel="prev"><x-notify.icon name="chevron-left" class="notify-icon--directional" /><span>{{ __('notify.client_hub.list.previous') }}</span></a>
                @endif
                <span class="notify-client-pagination__status" dir="ltr">{{ $list->clients->currentPage() }} / {{ $list->clients->lastPage() }}</span>
                @if($list->clients->nextPageUrl())
                    <a class="notify-button notify-button--ghost" href="{{ $list->clients->nextPageUrl() }}" rel="next"><span>{{ __('notify.client_hub.list.next_page') }}</span><x-notify.icon name="chevron-right" class="notify-icon--directional" /></a>
                @endif
            </nav>
        @endif
    @endif
</div>
@endsection
