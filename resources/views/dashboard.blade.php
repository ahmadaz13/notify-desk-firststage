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

    {{-- Operational Page Header --}}
    <div class="notify-page-head">
        <div>
            <div class="eyebrow">{{ now()->translatedFormat('l، d F Y') }}</div>
            <h1 class="page-title">{{ __('notify.common.greeting_morning') }}، {{ auth()->user()->name }}</h1>
            <p class="notify-page-lede">{{ __('notify.today.subtitle') }}</p>
        </div>
        <div class="notify-page-actions">
            <x-notify.button :href="route('clients.import')" variant="ghost" icon="clipboard-list">
                {{ __('notify.actions.import_csv') }}
            </x-notify.button>
        </div>
    </div>

    {{-- Operational Mode Tabs (Primary Daily Board & Work Categories) --}}
    <div class="notify-mode-tabs" role="tablist" aria-label="{{ __('notify.navigation.mobile_navigation') }}">
        <a
            href="{{ route('dashboard', ['mode' => 'daily']) }}"
            role="tab"
            :aria-selected="mode === 'daily' ? 'true' : 'false'"
            @click.prevent="setMode('daily')"
            class="notify-mode-tab"
            :class="{ 'is-active': mode === 'daily' }"
        >
            {{ __('notify.today.tab_today') }}
        </a>
        <a
            href="{{ route('dashboard', ['mode' => 'work']) }}"
            role="tab"
            :aria-selected="mode === 'work' ? 'true' : 'false'"
            @click.prevent="setMode('work')"
            class="notify-mode-tab"
            :class="{ 'is-active': mode === 'work' }"
        >
            {{ __('notify.today.tab_work') }}
        </a>
        @if($currentMode === 'financial')
            <a
                href="{{ route('dashboard', ['mode' => 'financial']) }}"
                role="tab"
                :aria-selected="mode === 'financial' ? 'true' : 'false'"
                @click.prevent="setMode('financial')"
                class="notify-mode-tab notify-mode-tab--deprecated"
                :class="{ 'is-active': mode === 'financial' }"
            >
                {{ __('notify.today.tab_financial_deprecated') }}
            </a>
        @endif
    </div>

    {{-- ========================================================================= --}}
    {{-- 1. PRIMARY OPERATIONAL DAILY BOARD (mode === 'daily')                     --}}
    {{-- ========================================================================= --}}
    <section x-show="mode === 'daily'" style="{{ $currentMode !== 'daily' ? 'display:none;' : '' }}" class="notify-daily-board">
        {{-- Daily Board Hero Banner --}}
        <div class="notify-today-hero">
            <div>
                <span class="eyebrow">{{ __('notify.today.hero_badge') }}</span>
                <h2>{{ __('notify.today.hero_title') }}</h2>
                <p>{{ __('notify.today.hero_lede') }}</p>
            </div>
            <div class="notify-today-hero__metric">
                <span>{{ __('notify.today.open_actions') }}</span>
                <strong>{{ $todayViewModel->totalWorkActions() }}</strong>
            </div>
        </div>

        {{-- Daily Note Capture (Private Operational Field Note) --}}
        <section
            class="notify-panel notify-daily-note-panel"
            x-data="{
                noteContent: @js($dailyNote?->content ?? ''),
                saveStatus: '{{ __('notify.common.ready') }}',
                saveTimeout: null,
                saveNote() {
                    this.saveStatus = '{{ __('notify.actions.saving') }}';
                    clearTimeout(this.saveTimeout);
                    this.saveTimeout = setTimeout(async () => {
                        try {
                            const response = await fetch('{{ route('daily-notes.save') }}', {
                                method: 'PUT',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                },
                                body: JSON.stringify({ content: this.noteContent })
                            });
                            this.saveStatus = response.ok ? '{{ __('notify.actions.saved') }}' : '{{ __('notify.common.needs_retry') }}';
                        } catch (e) {
                            this.saveStatus = '{{ __('notify.common.needs_retry') }}';
                        }
                    }, 700);
                }
            }"
        >
            <div class="notify-section-title notify-section-title--compact">
                <div>
                    <h2>{{ __('notify.today.daily_note_title') }}</h2>
                    <p>{{ __('notify.today.daily_note_desc') }}</p>
                </div>
                <x-notify.badge variant="info" x-text="saveStatus">{{ __('notify.common.ready') }}</x-notify.badge>
            </div>
            <textarea
                class="notify-daily-note"
                x-model="noteContent"
                @input="saveNote()"
                rows="3"
                placeholder="{{ __('notify.today.daily_note_placeholder') }}"
            >{{ $dailyNote?->content }}</textarea>
        </section>

        {{-- Operational Queues (Primary Task Grid) --}}
        @foreach($todayViewModel->primaryGroups() as $group)
            @php
                $groupKey = match($group['title']) {
                    'Now' => 'now',
                    'Follow-up Required' => 'followups',
                    'Money Requiring Attention' => 'money',
                    'New' => 'new',
                    default => 'now',
                };
                $groupTitleKey = 'notify.today.groups.'.$groupKey.'.title';
                $groupDescriptionKey = 'notify.today.groups.'.$groupKey.'.description';
            @endphp
            <div class="notify-section-title">
                <div>
                    <h2>{{ \Illuminate\Support\Facades\Lang::has($groupTitleKey) ? __($groupTitleKey) : $group['title'] }}</h2>
                    <p>{{ \Illuminate\Support\Facades\Lang::has($groupDescriptionKey) ? __($groupDescriptionKey) : $group['description'] }}</p>
                </div>
            </div>
            <div class="notify-queue-grid">
                @foreach($group['queues'] as $queue)
                    <x-notify.queue-card :queue="$queue" />
                @endforeach
            </div>
        @endforeach

        {{-- Split Operational Grid: Appointments Today + Recent Activity --}}
        <div class="notify-section-grid">
            {{-- Appointments Today Detail --}}
            <section class="notify-panel">
                <div class="notify-section-title notify-section-title--compact">
                    <div>
                        <h2>{{ __('notify.today.today_appointments') }}</h2>
                        <p>{{ __('notify.today.scheduled_today_count', ['count' => $todayAppointments->count()]) }}</p>
                    </div>
                </div>
                <div class="notify-activity-list">
                    @forelse($todayAppointments as $appointment)
                        <article class="notify-activity-row">
                            <span class="notify-activity-row__time">{{ $appointment->appointment_time }}</span>
                            <div>
                                <strong>{{ $appointment->client?->business_name ?? $appointment->business_name }}</strong>
                                <small>{{ $appointment->location ?: __('notify.common.remote') }}</small>
                                @if(isset($appointment->users) && $appointment->users->isNotEmpty())
                                    <div class="notify-chip-row">
                                        @foreach($appointment->users as $attendee)
                                            <span class="notify-chip">👤 {{ $attendee->name }}</span>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                            <x-notify.button :href="route('clients.show', $appointment->client_id)" variant="ghost" icon="building">
                                {{ __('notify.actions.open') }}
                            </x-notify.button>
                        </article>
                    @empty
                        <x-notify.empty-state
                            title="{{ __('notify.today.no_appointments_today') }}"
                            message="{{ __('notify.today.no_appointments_msg') }}"
                            icon="clipboard-list"
                        />
                    @endforelse
                </div>
            </section>

            {{-- Recent Unread Activity Stream --}}
            <section class="notify-panel">
                <div class="notify-section-title notify-section-title--compact">
                    <div>
                        <h2>{{ __('notify.today.recent_activity') }}</h2>
                        <p>{{ __('notify.today.recent_activity_desc') }}</p>
                    </div>
                    <x-notify.button :href="route('notifications.index')" variant="ghost" icon="bell">
                        {{ __('notify.actions.all') }}
                    </x-notify.button>
                </div>
                <div class="notify-activity-list">
                    @forelse($todayViewModel->recentActivity() as $activity)
                        <a class="notify-activity-row" href="{{ $activity['href'] ?? route('notifications.index') }}">
                            <span class="notify-activity-row__dot"></span>
                            <span>
                                <strong>{{ $activity['title'] }}</strong>
                                <small>{{ $activity['body'] }}</small>
                                @if($activity['meta'])
                                    <em>{{ $activity['meta'] }}</em>
                                @endif
                            </span>
                        </a>
                    @empty
                        <x-notify.empty-state
                            title="{{ __('notify.today.no_notifications') }}"
                            message="{{ __('notify.today.no_notifications_msg') }}"
                            icon="bell"
                        />
                    @endforelse
                </div>
            </section>
        </div>

        {{-- Operational At-A-Glance Indicators (Secondary) --}}
        <div class="notify-snapshot-grid" aria-label="{{ __('notify.today.title') }}">
            @foreach($todayViewModel->secondarySnapshots() as $snapshot)
                @php
                    $snapshotKey = match($snapshot['label']) {
                        'Recent Activity' => 'recent_activity',
                        'Subscription Snapshot' => 'subscription_snapshot',
                        'Collections Snapshot' => 'collections_snapshot',
                        'Cash Snapshot' => 'cash_snapshot',
                        default => null,
                    };

                    $captionKey = match($snapshot['caption']) {
                        'Unread notifications' => 'unread_notifications',
                        'Free installs awaiting follow-up' => 'free_installs_awaiting',
                        'Collected today from existing service data' => 'collected_today',
                        'Today net from existing dashboard service data' => 'today_net',
                        default => null,
                    };
                    $snapshotTranslationKey = $snapshotKey ? 'notify.today.snapshots.'.$snapshotKey : null;
                    $captionTranslationKey = $captionKey ? 'notify.today.snapshots.'.$captionKey : null;
                @endphp
                <article class="notify-snapshot notify-snapshot--{{ $snapshot['variant'] }}">
                    <span>{{ $snapshotTranslationKey && \Illuminate\Support\Facades\Lang::has($snapshotTranslationKey) ? __($snapshotTranslationKey) : $snapshot['label'] }}</span>
                    <strong>{{ $snapshot['value'] }}</strong>
                    <small>{{ $captionTranslationKey && \Illuminate\Support\Facades\Lang::has($captionTranslationKey) ? __($captionTranslationKey) : $snapshot['caption'] }}</small>
                </article>
            @endforeach
        </div>
    </section>

    {{-- ========================================================================= --}}
    {{-- 2. WORK BY CATEGORY (mode === 'work')                                     --}}
    {{-- ========================================================================= --}}
    <section x-show="mode === 'work'" style="{{ $currentMode !== 'work' ? 'display:none;' : '' }}" id="mobile-work" class="notify-work-board">
        <div class="notify-work-hero">
            <div>
                <span class="eyebrow">{{ __('notify.today.work_tab.title') }}</span>
                <h2>{{ __('notify.today.work_tab.title') }}</h2>
                <p>{{ __('notify.today.work_tab.subtitle') }}</p>
            </div>
            <div class="notify-today-hero__metric">
                <span>{{ __('notify.today.open_actions') }}</span>
                <strong>{{ $todayViewModel->totalOpenActions() }}</strong>
            </div>
        </div>

        <div class="notify-work-categories">
            @foreach($todayViewModel->workCategories() as $category)
                @php
                    $categoryTitleKey = 'notify.today.work_tab.categories.'.$category['key'].'.title';
                    $categoryDescriptionKey = 'notify.today.work_tab.categories.'.$category['key'].'.description';
                @endphp
                <section class="notify-work-category" id="work-{{ $category['key'] }}">
                    <div class="notify-work-category__head">
                        <span class="notify-work-category__icon">
                            <x-notify.icon :name="$category['icon']" />
                        </span>
                        <div>
                            <p class="notify-eyebrow">{{ \Illuminate\Support\Facades\Lang::has($categoryTitleKey) ? __($categoryTitleKey) : $category['title'] }}</p>
                            <h2>{{ \Illuminate\Support\Facades\Lang::has($categoryTitleKey) ? __($categoryTitleKey) : $category['title'] }}</h2>
                            <small>{{ \Illuminate\Support\Facades\Lang::has($categoryDescriptionKey) ? __($categoryDescriptionKey) : $category['description'] }}</small>
                        </div>
                    </div>
                    <div class="notify-work-category__queues">
                        @foreach($category['queues'] as $queue)
                            <x-notify.queue-card :queue="$queue" />
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
    </section>

    {{-- ========================================================================= --}}
    {{-- 3. DEPRECATED FINANCIAL VIEW (mode === 'financial' backward compatibility)--}}
    {{-- ========================================================================= --}}
    <section x-show="mode === 'financial'" style="{{ $currentMode !== 'financial' ? 'display:none;' : '' }}">
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
</div>
@endsection
