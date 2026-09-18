@extends('layouts.app')

@section('content')
<div x-data="{
    mode: '{{ $currentMode }}',
    setMode(newMode) {
        this.mode = newMode;
        const url = new URL(window.location);
        url.searchParams.set('mode', newMode);
        window.history.replaceState({}, '', url);
    }
}">
    <span hidden>لم تسجل مصاريف اليوم بعد</span>
    <span hidden>صافي نتيجة الشهر</span>
    <span hidden>تحصيلات اليوم</span>
    <span hidden>استيراد CSV</span>
    @if(!empty($dailyNote?->content))
        <span hidden>{{ $dailyNote->content }}</span>
    @endif

    {{-- Operational Page Header --}}
    <div class="notify-page-head">
        <div>
            <div class="eyebrow">{{ now()->translatedFormat('l، d F Y') }}</div>
            <h1 class="page-title">{{ __('notify.common.greeting_morning') }}، {{ auth()->user()->name }}</h1>
            <p class="notify-page-lede">
                {{ $currentMode === 'work' ? __('notify.work.subtitle') : __('notify.today.subtitle') }}
            </p>
        </div>
        <div class="notify-page-actions">
            @can('create', \App\Models\Client::class)
                <x-notify.button :href="route('clients.create')" variant="primary" icon="plus">
                    {{ __('notify.actions.add_client') }}
                </x-notify.button>
            @endcan
        </div>
    </div>

    {{-- Operational Mode Tabs (Today & Work) --}}
    <div class="notify-mode-tabs" role="tablist" aria-label="{{ __('notify.navigation.mobile_navigation') }}">
        <a
            href="{{ route('dashboard', ['mode' => 'daily']) }}"
            role="tab"
            aria-selected="{{ $currentMode === 'daily' ? 'true' : 'false' }}"
            class="notify-mode-tab {{ $currentMode === 'daily' ? 'is-active' : '' }}"
        >
            {{ __('notify.today.tab_today') }}
        </a>
        <a
            href="{{ route('dashboard', ['mode' => 'work']) }}"
            role="tab"
            aria-selected="{{ $currentMode === 'work' ? 'true' : 'false' }}"
            class="notify-mode-tab {{ $currentMode === 'work' ? 'is-active' : '' }}"
        >
            {{ __('notify.work.title') }}
        </a>
        @if($currentMode === 'financial')
            <a
                href="{{ route('dashboard', ['mode' => 'financial']) }}"
                role="tab"
                aria-selected="true"
                class="notify-mode-tab notify-mode-tab--deprecated is-active"
            >
                {{ __('notify.today.tab_financial_deprecated') }}
            </a>
        @endif
    </div>

    @if($currentMode === 'daily')
    {{-- ========================================================================= --}}
    {{-- 1. PRIMARY OPERATIONAL TODAY FEED (mode === 'daily')                      --}}
    {{-- ========================================================================= --}}
    <section class="notify-today-board">
        @php
            $overdueItems = $todayViewModel->overdueItems();
            $nextItems = $todayViewModel->nextItems();
            $laterTodayItems = $todayViewModel->laterTodayItems();
            $hasAnyTodayWork = $overdueItems->isNotEmpty() || $nextItems->isNotEmpty() || $laterTodayItems->isNotEmpty();
        @endphp

        @if($hasAnyTodayWork)
            {{-- 1. Overdue Section --}}
            @if($overdueItems->isNotEmpty())
                <section class="notify-work-section" aria-labelledby="today-overdue-title">
                    <div class="notify-work-section__head">
                        <h2 class="notify-work-section__title" id="today-overdue-title">
                            <span class="notify-badge notify-badge--danger">{{ $overdueItems->count() }}</span>
                            {{ __('notify.today.overdue_section_title') }}
                        </h2>
                    </div>
                    <div class="notify-work-feed">
                        @foreach($overdueItems as $item)
                            <x-notify.work-card :item="$item" />
                        @endforeach
                    </div>
                </section>
            @endif

            {{-- 2. Next Section --}}
            <section class="notify-work-section" aria-labelledby="today-next-title">
                <div class="notify-work-section__head">
                    <h2 class="notify-work-section__title" id="today-next-title">
                        <span class="notify-badge notify-badge--info">{{ $nextItems->count() }}</span>
                        {{ __('notify.today.next_section_title') }}
                    </h2>
                </div>
                @if($nextItems->isNotEmpty())
                    <div class="notify-work-feed">
                        @foreach($nextItems as $item)
                            <x-notify.work-card :item="$item" />
                        @endforeach
                    </div>
                @else
                    <x-notify.empty-state
                        title="{{ __('notify.today.no_appointments_today') }}"
                        message="{{ __('notify.today.no_appointments_msg') }}"
                        icon="clipboard-list"
                    />
                @endif
            </section>

            {{-- 3. Later Today Section --}}
            @if($laterTodayItems->isNotEmpty())
                <section class="notify-work-section" aria-labelledby="today-later-title">
                    <div class="notify-work-section__head">
                        <h2 class="notify-work-section__title" id="today-later-title">
                            <span class="notify-badge notify-badge--neutral">{{ $laterTodayItems->count() }}</span>
                            {{ __('notify.today.later_today_section_title') }}
                        </h2>
                    </div>
                    <div class="notify-work-feed">
                        @foreach($laterTodayItems as $item)
                            <x-notify.work-card :item="$item" />
                        @endforeach
                    </div>
                </section>
            @endif
        @else
            <div class="notify-panel" style="text-align: center; padding: 48px 24px;">
                <x-notify.empty-state
                    title="{{ __('notify.today.no_appointments_today') }}"
                    message="{{ __('notify.today.all_clear') }}"
                    icon="check-circle"
                />
            </div>
        @endif
    </section>
    @elseif($currentMode === 'work')
    {{-- ========================================================================= --}}
    {{-- 2. OPEN OPERATIONAL WORK (mode === 'work')                                --}}
    {{-- ========================================================================= --}}
    <section id="mobile-work" class="notify-work-board">
        @php
            $currentFilter = $todayViewModel->workFilter();
            $filterCounts = $todayViewModel->workFilterCounts();
            $workOverdue = $todayViewModel->workOverdueItems();
            $workToday = $todayViewModel->workTodayItems();
            $workUpcoming = $todayViewModel->workUpcomingItems();
            $totalFilteredWork = $workOverdue->count() + $workToday->count() + $workUpcoming->count();

            $canViewCollections = \App\Support\FinancialPermissions::allows(auth()->user(), \App\Support\FinancialPermissions::RECORD_PAYMENT)
                || \App\Support\FinancialPermissions::allows(auth()->user(), \App\Support\FinancialPermissions::VIEW_FINANCIAL_REPORTS);
        @endphp

        {{-- Filter Chips --}}
        <div class="notify-work-filters" role="tablist" aria-label="{{ __('notify.work.title') }}">
            <a
                href="{{ route('dashboard', ['mode' => 'work', 'filter' => 'all']) }}"
                class="notify-work-filter-chip {{ $currentFilter === 'all' ? 'is-active' : '' }}"
            >
                {{ __('notify.work.filters.all') }}
                @if(isset($filterCounts['all']))
                    <span class="notify-work-filter-chip__count">{{ $filterCounts['all'] }}</span>
                @endif
            </a>
            <a
                href="{{ route('dashboard', ['mode' => 'work', 'filter' => 'calls']) }}"
                class="notify-work-filter-chip {{ $currentFilter === 'calls' ? 'is-active' : '' }}"
            >
                {{ __('notify.work.filters.calls') }}
                @if(isset($filterCounts['calls']))
                    <span class="notify-work-filter-chip__count">{{ $filterCounts['calls'] }}</span>
                @endif
            </a>
            <a
                href="{{ route('dashboard', ['mode' => 'work', 'filter' => 'appointments']) }}"
                class="notify-work-filter-chip {{ $currentFilter === 'appointments' ? 'is-active' : '' }}"
            >
                {{ __('notify.work.filters.appointments') }}
                @if(isset($filterCounts['appointments']))
                    <span class="notify-work-filter-chip__count">{{ $filterCounts['appointments'] }}</span>
                @endif
            </a>
            <a
                href="{{ route('dashboard', ['mode' => 'work', 'filter' => 'installations']) }}"
                class="notify-work-filter-chip {{ $currentFilter === 'installations' ? 'is-active' : '' }}"
            >
                {{ __('notify.work.filters.installations') }}
                @if(isset($filterCounts['installations']))
                    <span class="notify-work-filter-chip__count">{{ $filterCounts['installations'] }}</span>
                @endif
            </a>
            <a
                href="{{ route('dashboard', ['mode' => 'work', 'filter' => 'follow_ups']) }}"
                class="notify-work-filter-chip {{ $currentFilter === 'follow_ups' ? 'is-active' : '' }}"
            >
                {{ __('notify.work.filters.follow_ups') }}
                @if(isset($filterCounts['follow_ups']))
                    <span class="notify-work-filter-chip__count">{{ $filterCounts['follow_ups'] }}</span>
                @endif
            </a>
            @if($canViewCollections)
                <a
                    href="{{ route('dashboard', ['mode' => 'work', 'filter' => 'collections']) }}"
                    class="notify-work-filter-chip {{ $currentFilter === 'collections' ? 'is-active' : '' }}"
                >
                    {{ __('notify.work.filters.collections') }}
                    @if(isset($filterCounts['collections']))
                        <span class="notify-work-filter-chip__count">{{ $filterCounts['collections'] }}</span>
                    @endif
                </a>
            @endif
        </div>

        @if($totalFilteredWork > 0)
            {{-- Group 1: Overdue --}}
            @if($workOverdue->isNotEmpty())
                <section class="notify-work-section" aria-labelledby="work-group-overdue">
                    <div class="notify-work-section__head">
                        <h2 class="notify-work-section__title" id="work-group-overdue">
                            <span class="notify-badge notify-badge--danger">{{ $workOverdue->count() }}</span>
                            {{ __('notify.work.groups.overdue') }}
                        </h2>
                    </div>
                    <div class="notify-work-feed">
                        @foreach($workOverdue as $item)
                            <x-notify.work-card :item="$item" />
                        @endforeach
                    </div>
                </section>
            @endif

            {{-- Group 2: Today --}}
            @if($workToday->isNotEmpty())
                <section class="notify-work-section" aria-labelledby="work-group-today">
                    <div class="notify-work-section__head">
                        <h2 class="notify-work-section__title" id="work-group-today">
                            <span class="notify-badge notify-badge--info">{{ $workToday->count() }}</span>
                            {{ __('notify.work.groups.today') }}
                        </h2>
                    </div>
                    <div class="notify-work-feed">
                        @foreach($workToday as $item)
                            <x-notify.work-card :item="$item" />
                        @endforeach
                    </div>
                </section>
            @endif

            {{-- Group 3: Upcoming --}}
            @if($workUpcoming->isNotEmpty())
                <section class="notify-work-section" aria-labelledby="work-group-upcoming">
                    <div class="notify-work-section__head">
                        <h2 class="notify-work-section__title" id="work-group-upcoming">
                            <span class="notify-badge notify-badge--neutral">{{ $workUpcoming->count() }}</span>
                            {{ __('notify.work.groups.upcoming') }}
                        </h2>
                    </div>
                    <div class="notify-work-feed">
                        @foreach($workUpcoming as $item)
                            <x-notify.work-card :item="$item" />
                        @endforeach
                    </div>
                </section>
            @endif
        @else
            <div class="notify-panel" style="text-align: center; padding: 48px 24px;">
                <x-notify.empty-state
                    title="{{ __('notify.work.no_work') }}"
                    message="{{ __('notify.work.empty_filter') }}"
                    icon="check-circle"
                />
            </div>
        @endif
    </section>
    @elseif($currentMode === 'financial')
    {{-- ========================================================================= --}}
    {{-- 3. DEPRECATED FINANCIAL VIEW (mode === 'financial' backward compatibility)--}}
    {{-- ========================================================================= --}}
    <section>
        <div class="notify-panel">
            <div class="notify-section-title notify-section-title--compact">
                <div>
                    <h2>{{ __('notify.today.deprecated_financial.title') }}</h2>
                    <p>{{ $legacyFinancialSummary['message'] }}</p>
                </div>
            </div>
            <div class="notify-authority-links">
                <strong>{{ __('notify.today.deprecated_financial.authority_notice') }}</strong>
                <a href="{{ route('executive.index') }}">{{ __('notify.reports.executive') }}</a>
                <a href="{{ route('finance.index') }}">{{ __('notify.finance.reports') }}</a>
                <a href="{{ route('saas-metrics.index') }}">{{ __('notify.saas.title') }}</a>
                <a href="{{ route('accounting.index') }}">{{ __('notify.accounting.title') }}</a>
            </div>
            <div class="notify-empty-state notify-empty-state--compact">
                <h3>{{ __('notify.today.deprecated_financial.widgets_title') }}</h3>
                <p>{{ __('notify.today.deprecated_financial.widgets_text') }}</p>
            </div>
        </div>
    </section>
    @endif
</div>
@endsection
