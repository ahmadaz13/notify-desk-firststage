@extends('layouts.app')

{{--
    Today (P11, §20): the operational home. One unified queue — Overdue → Next → Later today — built
    from the authoritative workflow records by UnifiedOperationalWorkProjection. Cards orient; the
    Client Workspace sheets (P10) execute. "All open" is Today's internal Work mode (§20).
--}}
@section('content')
@php
    $isDaily = $currentMode === 'daily';
    $scopeParam = $scope === 'my' ? 'my' : null;
    $modeHref = fn (string $mode) => route('dashboard', array_filter(['mode' => $mode === 'work' ? 'work' : null, 'scope' => $scopeParam]));
    $scopeHref = fn (string $value) => route('dashboard', array_filter(['mode' => $isDaily ? null : 'work', 'filter' => $isDaily ? null : ($todayViewModel->workFilter() !== 'all' ? $todayViewModel->workFilter() : null), 'scope' => $value === 'my' ? 'my' : null]));
@endphp
<div class="notify-today" data-today-board data-today-mode="{{ $currentMode }}">
    <x-notify.page-header :title="__('notify.today_board.title')" :description="\App\Support\OperationalTime::longDate($businessNow)">
        @can('create', \App\Models\Client::class)
            <x-slot:actions>
                <x-notify.button :href="route('clients.create')" variant="secondary" icon="plus" :hide-label-on-mobile="true" data-page-action="add-client">{{ __('notify.actions.add_client') }}</x-notify.button>
            </x-slot:actions>
        @endcan
    </x-notify.page-header>

    <div class="notify-today__controls">
        <nav class="notify-switch" aria-label="{{ __('notify.today_board.mode_label') }}">
            <a href="{{ $modeHref('daily') }}" class="notify-switch__item {{ $isDaily ? 'is-active' : '' }}" data-today-tab="today" @if($isDaily) aria-current="page" @endif>{{ __('notify.today_board.mode_today') }}</a>
            <a href="{{ $modeHref('work') }}" class="notify-switch__item {{ $isDaily ? '' : 'is-active' }}" data-today-tab="work" @unless($isDaily) aria-current="page" @endunless>{{ __('notify.today_board.mode_open') }}</a>
        </nav>
        <nav class="notify-switch" aria-label="{{ __('notify.today_board.scope_label') }}" data-team-filter>
            <a href="{{ $scopeHref('all') }}" class="notify-switch__item {{ $scope !== 'my' ? 'is-active' : '' }}" data-team-scope="all" @if($scope !== 'my') aria-current="true" @endif>
                <x-notify.icon name="users" :size="16" /><span>{{ __('notify.today_board.scope_all') }}</span>
            </a>
            <a href="{{ $scopeHref('my') }}" class="notify-switch__item {{ $scope === 'my' ? 'is-active' : '' }}" data-team-scope="my" @if($scope === 'my') aria-current="true" @endif>
                <x-notify.icon name="user" :size="16" /><span>{{ __('notify.today_board.scope_my') }}</span>
            </a>
        </nav>
    </div>

    @if($isDaily)
        @php
            $overdueItems = $todayViewModel->overdueItems();
            $nextItems = $todayViewModel->nextItems();
            $laterTodayItems = $todayViewModel->laterTodayItems();
            $completed = $todayViewModel->completed();
            $pendingConfirmations = $todayViewModel->pendingConfirmations();
            $myPendingReceipts = $todayViewModel->myPendingReceipts();
            $readyToContact = $todayViewModel->readyToContact();
            $visibleOverdue = \App\ViewModels\TodayViewModel::OVERDUE_VISIBLE;
        @endphp

        {{-- Compact signals: each one is actionable or orienting; no finance totals (§20, D-07). --}}
        <ul class="notify-today__signals" aria-label="{{ __('notify.today_board.signals_label') }}" data-today-signals>
            <li class="notify-today__signal-phone">
                <a class="notify-status notify-status--success notify-status--link" href="#today-completed" data-today-completed-chip>
                    <x-notify.icon name="check-circle" :size="14" />
                    {{ __('notify.today_board.completed_title') }}: {{ trans_choice('notify.today_board.completed_count', $completed['total'], ['count' => $completed['total']]) }}
                </a>
            </li>
            @if($pendingConfirmations > 0)
                <li>
                    <a class="notify-status notify-status--warning notify-status--link" href="{{ route('finance.collections', ['tab' => 'pending']) }}" data-today-signal="pending-confirmations">
                        <x-notify.icon name="receipt" :size="14" />
                        {{ trans_choice('notify.today_board.pending_confirmations', $pendingConfirmations, ['count' => $pendingConfirmations]) }}
                    </a>
                </li>
            @endif
            @if($myPendingReceipts > 0)
                <li>
                    <a class="notify-status notify-status--neutral notify-status--link" href="{{ route('collections-due.index') }}" data-today-signal="my-pending-receipts">
                        <x-notify.icon name="receipt" :size="14" />
                        {{ trans_choice('notify.today_board.my_pending_receipts', $myPendingReceipts, ['count' => $myPendingReceipts]) }}
                    </a>
                </li>
            @endif
            @if($readyToContact > 0)
                <li>
                    <a class="notify-status notify-status--neutral notify-status--link" href="{{ route('dashboard', array_filter(['mode' => 'work', 'filter' => 'calls', 'scope' => $scopeParam])) }}" data-today-signal="ready-to-contact">
                        <x-notify.icon name="phone" :size="14" />
                        {{ trans_choice('notify.today_board.ready_to_contact', $readyToContact, ['count' => $readyToContact]) }}
                    </a>
                </li>
            @endif
            <li class="notify-today__signal-phone">
                <a class="notify-status notify-status--neutral notify-status--link" href="#daily-notes">
                    <x-notify.icon name="book-open" :size="14" />
                    {{ __('notify.today_board.notes_jump') }}
                </a>
            </li>
        </ul>

        <div class="notify-today__layout">
            <div class="notify-today__main">
                @if($todayViewModel->hasTodayWork())
                    @if($overdueItems->isNotEmpty())
                        <section class="notify-today-section notify-today-section--overdue" aria-labelledby="today-overdue-title" data-today-section="overdue" x-data="{ expanded: false }">
                            <h2 class="notify-today-section__title" id="today-overdue-title">
                                <x-notify.icon name="alert-circle" :size="18" />
                                <span>{{ __('notify.today_board.sections.overdue') }}</span>
                                <span class="notify-today-section__count">{{ $overdueItems->count() }}</span>
                            </h2>
                            <div class="notify-today-section__list">
                                @foreach($overdueItems->take($visibleOverdue) as $item)
                                    <x-notify.work-card :item="$item" />
                                @endforeach
                            </div>
                            @if($overdueItems->count() > $visibleOverdue)
                                <div class="notify-today-section__list" id="today-overdue-more" x-show="expanded" x-cloak>
                                    @foreach($overdueItems->slice($visibleOverdue) as $item)
                                        <x-notify.work-card :item="$item" />
                                    @endforeach
                                </div>
                                <button type="button" class="notify-button notify-button--ghost notify-today-section__more" @click="expanded = !expanded" :aria-expanded="expanded.toString()" aria-expanded="false" aria-controls="today-overdue-more" data-today-overdue-toggle>
                                    <span x-show="!expanded">{{ __('notify.today_board.show_all_overdue', ['count' => $overdueItems->count()]) }}</span>
                                    <span x-show="expanded" x-cloak>{{ __('notify.today_board.show_less') }}</span>
                                </button>
                            @endif
                        </section>
                    @endif

                    <section class="notify-today-section notify-today-section--next" aria-labelledby="today-next-title" data-today-section="next">
                        <h2 class="notify-today-section__title" id="today-next-title">
                            <x-notify.icon name="activity" :size="18" />
                            <span>{{ __('notify.today_board.sections.next') }}</span>
                            <span class="notify-today-section__count">{{ $nextItems->count() }}</span>
                        </h2>
                        @if($nextItems->isNotEmpty())
                            <div class="notify-today-section__list">
                                @foreach($nextItems as $item)
                                    <x-notify.work-card :item="$item" />
                                @endforeach
                            </div>
                        @else
                            <p class="notify-today-section__empty">{{ __('notify.today_board.empty_message') }}</p>
                        @endif
                    </section>

                    @if($laterTodayItems->isNotEmpty())
                        <section class="notify-today-section notify-today-section--later" aria-labelledby="today-later-title" data-today-section="later_today">
                            <h2 class="notify-today-section__title" id="today-later-title">
                                <x-notify.icon name="calendar" :size="18" />
                                <span>{{ __('notify.today_board.sections.later_today') }}</span>
                                <span class="notify-today-section__count">{{ $laterTodayItems->count() }}</span>
                            </h2>
                            <div class="notify-today-section__list">
                                @foreach($laterTodayItems as $item)
                                    <x-notify.work-card :item="$item" />
                                @endforeach
                            </div>
                        </section>
                    @endif
                @else
                    {{-- Quiet day: calm state, the nearest real upcoming item, and a role-appropriate destination. --}}
                    <section class="notify-card notify-today-empty" aria-labelledby="today-empty-title" data-today-empty>
                        <span class="notify-today-empty__icon" aria-hidden="true"><x-notify.icon name="check-circle" :size="24" /></span>
                        <h2 id="today-empty-title">{{ __('notify.today_board.empty_title') }}</h2>
                        <p>{{ __('notify.today_board.empty_message') }}</p>
                        @if($upcoming = $todayViewModel->nextUpcoming())
                            <p class="notify-today-empty__next" data-today-next-upcoming>
                                <span>{{ __('notify.today_board.next_upcoming') }}:</span>
                                <a href="{{ route('clients.show', array_filter(['client' => $upcoming['client_id'], 'from' => 'today', 'scope' => $scopeParam])) }}">{{ $upcoming['client_name'] }}</a>
                                <span>· {{ $upcoming['label'] }} · {{ $upcoming['timing']['exact'] ?? $upcoming['timing']['text'] }}</span>
                            </p>
                        @endif
                        <x-notify.button :href="route('clients.index')" variant="secondary" icon="users">{{ __('notify.today_board.go_to_clients') }}</x-notify.button>
                    </section>
                @endif
            </div>

            <aside class="notify-today__side" aria-label="{{ __('notify.today_board.signals_label') }}">
                <section class="notify-card notify-today-done" id="today-completed" aria-labelledby="today-completed-title" data-today-completed>
                    <h2 class="notify-today-done__title" id="today-completed-title">
                        <x-notify.icon name="check-circle" :size="18" />
                        <span>{{ __('notify.today_board.completed_title') }}</span>
                    </h2>
                    <p class="notify-today-done__count" data-completed-total="{{ $completed['total'] }}">{{ trans_choice('notify.today_board.completed_count', $completed['total'], ['count' => $completed['total']]) }}</p>
                    @php $doneTypes = array_filter($completed['breakdown'] ?? []); @endphp
                    @if($doneTypes !== [])
                        <ul class="notify-today-done__breakdown">
                            @foreach($doneTypes as $doneType => $doneCount)
                                <li data-completed-type="{{ $doneType }}"><span>{{ __('notify.today_board.completed_types.'.$doneType) }}</span><strong>{{ $doneCount }}</strong></li>
                            @endforeach
                        </ul>
                    @endif
                </section>

                <x-notify.daily-notes :daily-note="$dailyNote" />
            </aside>
        </div>
    @else
        @php
            $currentFilter = $todayViewModel->workFilter();
            $filterCounts = $todayViewModel->workFilterCounts();
            $workGroups = [
                'overdue' => [$todayViewModel->workOverdueItems(), __('notify.work.groups.overdue'), 'alert-circle'],
                'today' => [$todayViewModel->workTodayItems(), __('notify.work.groups.today'), 'activity'],
                'upcoming' => [$todayViewModel->workUpcomingItems(), __('notify.work.groups.upcoming'), 'calendar'],
            ];
            $canViewCollections = \App\Support\Permissions::allows(auth()->user(), \App\Support\Permissions::VIEW_COLLECTIONS_DUE);
            $filters = array_values(array_filter(['all', 'calls', 'appointments', 'installations', 'follow_ups', $canViewCollections ? 'collections' : null]));
        @endphp
        <section id="mobile-work" class="notify-work-board" aria-labelledby="open-work-title">
            <h2 class="notify-today__mode-title" id="open-work-title">{{ __('notify.work.title') }}</h2>

            <nav class="notify-work-filters" aria-label="{{ __('notify.work.title') }}">
                @foreach($filters as $filterKey)
                    <a
                        href="{{ route('dashboard', array_filter(['mode' => 'work', 'filter' => $filterKey, 'scope' => $scopeParam])) }}"
                        class="notify-segments__item {{ $currentFilter === $filterKey ? 'is-active' : '' }}"
                        @if($currentFilter === $filterKey) aria-current="page" @endif
                    >
                        <span>{{ __('notify.work.filters.'.$filterKey) }}</span>
                        <span class="notify-segments__count" dir="ltr">{{ $filterCounts[$filterKey] ?? 0 }}</span>
                    </a>
                @endforeach
            </nav>

            @if(collect($workGroups)->sum(fn ($group) => $group[0]->count()) > 0)
                @foreach($workGroups as $groupKey => [$groupItems, $groupLabel, $groupIcon])
                    @if($groupItems->isNotEmpty())
                        <section class="notify-today-section notify-today-section--{{ $groupKey }}" aria-labelledby="work-group-{{ $groupKey }}">
                            <h2 class="notify-today-section__title" id="work-group-{{ $groupKey }}">
                                <x-notify.icon :name="$groupIcon" :size="18" />
                                <span>{{ $groupLabel }}</span>
                                <span class="notify-today-section__count">{{ $groupItems->count() }}</span>
                            </h2>
                            <div class="notify-today-section__list notify-today-section__list--grid">
                                @foreach($groupItems as $item)
                                    <x-notify.work-card :item="$item" />
                                @endforeach
                            </div>
                        </section>
                    @endif
                @endforeach
            @else
                <x-notify.empty-state
                    class="notify-card"
                    :title="__('notify.work.no_work')"
                    :message="__('notify.work.empty_filter')"
                    icon="check-circle"
                />
            @endif
        </section>
    @endif
</div>
@endsection
