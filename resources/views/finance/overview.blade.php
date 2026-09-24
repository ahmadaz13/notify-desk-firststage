@extends('layouts.app')

@php
    $kpis = $overview['kpis'];
    $attention = $overview['attention'];
    $trend = $overview['trend'];
    $delta = function (array $comparison) {
        if ($comparison['delta_percent'] === null) {
            return null;
        }

        return ($comparison['delta_percent'] > 0 ? '+' : '').$comparison['delta_percent'].'%';
    };

    // Inline SVG grouped bars (§12.1): server data, no chart library, table fallback below.
    $chartMax = max(1, (int) $trend->max(fn ($row) => max($row['collections_minor'], $row['expenses_minor'])));
    $chartHeight = 160;
    $slot = 100 / max(1, $trend->count());
    $hasAttention = $attention['pending_receipts']['count'] > 0 || $attention['overdue_count'] > 0
        || $attention['renewals']['count'] > 0 || $attention['recurring_expenses']['count'] > 0;
@endphp

@section('content')
<div class="notify-fin" data-finance-page="overview">
    @include('finance.partials.header', [
        'active' => 'overview',
        'title' => __('notify.finance_hub.sections.overview'),
        'subtitle' => __('notify.finance_hub.overview.subtitle'),
    ])

    {{-- 1. Four primary KPIs only (§12.1) --}}
    <section class="notify-fin-kpis" aria-label="{{ __('notify.finance_hub.overview.kpis_label') }}" data-primary-kpis>
        <a class="notify-fin-kpi notify-fin-kpi--cash" href="{{ route('finance.accounts') }}" data-kpi="available-cash">
            <span class="notify-fin-kpi__label">{{ __('notify.finance_hub.overview.available_cash') }}</span>
            <x-notify.money class="notify-fin-kpi__value" :minor="$kpis['available_cash']['total_minor']" />
            <span class="notify-fin-kpi__split">
                <span>{{ __('notify.finance_hub.overview.cash_box') }} <x-notify.money :minor="$kpis['available_cash']['cash_box_minor']" /></span>
                <span>{{ __('notify.finance_hub.overview.cliq') }} <x-notify.money :minor="$kpis['available_cash']['cliq_minor']" /></span>
            </span>
        </a>

        <a class="notify-fin-kpi" href="{{ route('finance.collections', ['tab' => 'due']) }}" data-kpi="receivables">
            <span class="notify-fin-kpi__label">{{ __('notify.finance_hub.overview.receivables') }}</span>
            <x-notify.money class="notify-fin-kpi__value" :minor="$kpis['receivables']['total_minor']" />
            @if($kpis['receivables']['overdue_minor'] > 0)
                <span class="notify-fin-chip notify-fin-chip--danger">{{ __('notify.finance_hub.overview.overdue_label') }} <x-notify.money :minor="$kpis['receivables']['overdue_minor']" /></span>
            @else
                <span class="notify-fin-chip notify-fin-chip--success">{{ __('notify.finance_hub.overview.no_overdue') }}</span>
            @endif
        </a>

        <a class="notify-fin-kpi" href="{{ route('finance.collections', ['tab' => 'payments']) }}" data-kpi="collections">
            <span class="notify-fin-kpi__label">{{ __('notify.finance_hub.overview.collections_month') }}</span>
            <x-notify.money class="notify-fin-kpi__value" :minor="$kpis['collections']['current_minor']" />
            <span class="notify-fin-kpi__meta">
                {{ __('notify.finance_hub.overview.last_month') }} <x-notify.money :minor="$kpis['collections']['previous_minor']" />
                @if($delta($kpis['collections']))
                    <span class="notify-fin-delta {{ $kpis['collections']['delta_minor'] >= 0 ? 'is-up' : 'is-down' }}" dir="ltr">{{ $delta($kpis['collections']) }}</span>
                @endif
            </span>
        </a>

        <a class="notify-fin-kpi" href="{{ route('finance.expenses') }}" data-kpi="expenses">
            <span class="notify-fin-kpi__label">{{ __('notify.finance_hub.overview.expenses_month') }}</span>
            <x-notify.money class="notify-fin-kpi__value" :minor="$kpis['expenses']['current_minor']" />
            <span class="notify-fin-kpi__meta">
                {{ __('notify.finance_hub.overview.last_month') }} <x-notify.money :minor="$kpis['expenses']['previous_minor']" />
                @if($delta($kpis['expenses']))
                    <span class="notify-fin-delta {{ $kpis['expenses']['delta_minor'] <= 0 ? 'is-up' : 'is-down' }}" dir="ltr">{{ $delta($kpis['expenses']) }}</span>
                @endif
            </span>
        </a>
    </section>

    <div class="notify-fin-grid">
        {{-- 2. Trend: collections vs expenses, last 6 months --}}
        <section class="notify-fin-card notify-fin-trend" aria-labelledby="fin-trend-title">
            <div class="notify-fin-card__head">
                <h2 id="fin-trend-title" class="notify-fin-card__title">{{ __('notify.finance_hub.overview.trend_title') }}</h2>
                <div class="notify-fin-legend" aria-hidden="true">
                    <span class="notify-fin-legend__item notify-fin-legend__item--in">{{ __('notify.finance_hub.overview.trend_collections') }}</span>
                    <span class="notify-fin-legend__item notify-fin-legend__item--out">{{ __('notify.finance_hub.overview.trend_expenses') }}</span>
                </div>
            </div>
            <svg class="notify-fin-chart" viewBox="0 0 100 {{ $chartHeight + 18 }}" preserveAspectRatio="none" role="img" aria-labelledby="fin-trend-title" data-trend-chart>
                <line x1="0" y1="{{ $chartHeight }}" x2="100" y2="{{ $chartHeight }}" class="notify-fin-chart__axis" vector-effect="non-scaling-stroke" />
                @foreach($trend as $index => $row)
                    @php
                        $inHeight = round($row['collections_minor'] / $chartMax * ($chartHeight - 8), 2);
                        $outHeight = round(max(0, $row['expenses_minor']) / $chartMax * ($chartHeight - 8), 2);
                        $x = $index * $slot;
                        $barWidth = $slot * 0.3;
                    @endphp
                    <rect class="notify-fin-chart__bar notify-fin-chart__bar--in" x="{{ $x + $slot * 0.17 }}" y="{{ $chartHeight - max(0, $inHeight) }}" width="{{ $barWidth }}" height="{{ max(0, $inHeight) }}"><title>{{ $row['month'] }} · {{ __('notify.finance_hub.overview.trend_collections') }}: {{ \App\Support\Money::fromMinorUnits($row['collections_minor'])->format() }}</title></rect>
                    <rect class="notify-fin-chart__bar notify-fin-chart__bar--out" x="{{ $x + $slot * 0.53 }}" y="{{ $chartHeight - $outHeight }}" width="{{ $barWidth }}" height="{{ $outHeight }}"><title>{{ $row['month'] }} · {{ __('notify.finance_hub.overview.trend_expenses') }}: {{ \App\Support\Money::fromMinorUnits($row['expenses_minor'])->format() }}</title></rect>
                @endforeach
            </svg>
            <div class="notify-fin-chart__labels" dir="ltr" aria-hidden="true">
                @foreach($trend as $row)<span>{{ $row['start']->format('M') }}</span>@endforeach
            </div>
            <details class="notify-fin-disclosure">
                <summary>{{ __('notify.finance_hub.overview.trend_table') }}</summary>
                <div class="notify-fin-table-wrap">
                    <table class="notify-fin-table" data-trend-table>
                        <thead><tr><th scope="col">{{ __('notify.finance_hub.overview.month') }}</th><th scope="col">{{ __('notify.finance_hub.overview.trend_collections') }}</th><th scope="col">{{ __('notify.finance_hub.overview.trend_expenses') }}</th></tr></thead>
                        <tbody>
                            @foreach($trend as $row)
                                <tr><td dir="ltr">{{ $row['month'] }}</td><td><x-notify.money :minor="$row['collections_minor']" /></td><td><x-notify.money :minor="$row['expenses_minor']" /></td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </details>
        </section>

        {{-- 3. Attention queue --}}
        <section class="notify-fin-card notify-fin-attention" aria-labelledby="fin-attention-title" data-attention-queue>
            <div class="notify-fin-card__head">
                <h2 id="fin-attention-title" class="notify-fin-card__title">{{ __('notify.finance_hub.overview.attention_title') }}</h2>
            </div>

            @unless($hasAttention)
                <p class="notify-fin-empty">{{ __('notify.finance_hub.overview.attention_empty') }}</p>
            @endunless

            @if($attention['pending_receipts']['count'] > 0)
                @php $oldest = $attention['pending_receipts']['oldest']; @endphp
                <a class="notify-fin-alert notify-fin-alert--warning" href="{{ route('finance.collections', ['tab' => 'pending']) }}" data-attention="pending-receipts">
                    <span class="notify-fin-alert__count" dir="ltr">{{ $attention['pending_receipts']['count'] }}</span>
                    <span class="notify-fin-alert__body">
                        <strong>{{ __('notify.finance_hub.overview.pending_confirmations') }}</strong>
                        <small><x-notify.money :minor="$attention['pending_receipts']['total_minor']" />@if($oldest) · {{ __('notify.finance_hub.overview.pending_oldest', ['client' => $oldest->client?->business_name, 'age' => $oldest->created_at?->diffForHumans()]) }}@endif</small>
                    </span>
                    <span class="notify-fin-alert__action">{{ __('notify.finance_hub.overview.review') }}</span>
                </a>
            @endif

            @if($attention['overdue_count'] > 0)
                <div class="notify-fin-alert notify-fin-alert--danger" data-attention="overdue">
                    <span class="notify-fin-alert__count" dir="ltr">{{ $attention['overdue_count'] }}</span>
                    <span class="notify-fin-alert__body">
                        <strong>{{ __('notify.finance_hub.overview.overdue_receivables') }}</strong>
                        <ul class="notify-fin-alert__list">
                            @foreach($attention['overdue'] as $item)
                                <li><a href="{{ route('clients.show', $item['invoice']->client_id) }}">{{ $item['client']?->business_name }}</a> · <x-notify.money :minor="$item['outstanding_minor']" /> · {{ __('notify.finance_hub.overview.days_overdue', ['days' => $item['days_overdue']]) }}</li>
                            @endforeach
                        </ul>
                    </span>
                    <a class="notify-fin-alert__action" href="{{ route('finance.collections', ['tab' => 'due']) }}">{{ __('notify.finance_hub.overview.open') }}</a>
                </div>
            @endif

            @if($attention['renewals']['count'] > 0)
                <div class="notify-fin-alert notify-fin-alert--info" data-attention="renewals">
                    <span class="notify-fin-alert__count" dir="ltr">{{ $attention['renewals']['count'] }}</span>
                    <span class="notify-fin-alert__body">
                        <strong>{{ __('notify.finance_hub.overview.renewals_due') }}</strong>
                        <ul class="notify-fin-alert__list">
                            @foreach($attention['renewals']['items'] as $subscription)
                                <li><a href="{{ route('clients.show', $subscription->client_id) }}">{{ $subscription->client?->business_name }}</a> · <span dir="ltr">{{ $subscription->next_billing_date?->format('Y-m-d') }}</span></li>
                            @endforeach
                        </ul>
                    </span>
                </div>
            @endif

            @if($attention['recurring_expenses']['count'] > 0)
                <a class="notify-fin-alert notify-fin-alert--neutral" href="{{ route('finance.expenses') }}" data-attention="recurring-expenses">
                    <span class="notify-fin-alert__count" dir="ltr">{{ $attention['recurring_expenses']['count'] }}</span>
                    <span class="notify-fin-alert__body">
                        <strong>{{ __('notify.finance_hub.overview.recurring_due') }}</strong>
                        <small><x-notify.money :minor="$attention['recurring_expenses']['total_minor']" /> · {{ $attention['recurring_expenses']['items']->map(fn ($obligation) => $obligation->template?->name)->filter()->join('، ') }}</small>
                    </span>
                    <span class="notify-fin-alert__action">{{ __('notify.finance_hub.overview.open') }}</span>
                </a>
            @endif
        </section>
    </div>

    {{-- 4. Compact financial result (secondary) --}}
    <a class="notify-fin-result" href="{{ route('finance.reports', ['report' => 'profit-and-loss']) }}" data-financial-result>
        <span class="notify-fin-result__title">{{ __('notify.finance_hub.overview.result_title') }}</span>
        <span class="notify-fin-result__item">{{ __('notify.finance_hub.overview.recognized_revenue') }} <x-notify.money :minor="$overview['result']['recognized_revenue_minor']" /></span>
        <span class="notify-fin-result__item">{{ __('notify.finance_hub.overview.expenses') }} <x-notify.money :minor="$overview['result']['expenses_minor']" /></span>
        <span class="notify-fin-result__item notify-fin-result__item--net {{ $overview['result']['net_minor'] < 0 ? 'is-negative' : '' }}">{{ __('notify.finance_hub.overview.net_result') }} <x-notify.money :minor="$overview['result']['net_minor']" /></span>
    </a>

    {{-- 5. Subscription metrics strip: visually separate, never accounting revenue --}}
    @if($canViewSaas)
        <section class="notify-fin-saas" aria-labelledby="fin-saas-title" data-saas-strip>
            <h2 id="fin-saas-title" class="notify-fin-saas__title">{{ __('notify.finance_hub.overview.saas_title') }}</h2>
            <dl class="notify-fin-saas__metrics">
                <div><dt>{{ __('notify.finance_hub.overview.mrr') }}</dt><dd><x-notify.money :minor="$overview['saas']['mrr_minor']" /></dd></div>
                <div><dt>{{ __('notify.finance_hub.overview.arr') }}</dt><dd><x-notify.money :minor="$overview['saas']['arr_minor']" /></dd></div>
                <div><dt>{{ __('notify.finance_hub.overview.active_subscriptions') }}</dt><dd dir="ltr">{{ $overview['saas']['active_subscriptions'] }}</dd></div>
            </dl>
            <a class="notify-fin-link" href="{{ route('finance.reports', ['report' => 'subscription-metrics']) }}">{{ __('notify.finance_hub.reports.names.subscription-metrics') }}</a>
        </section>
    @endif

    {{-- 6. Drill-down --}}
    <nav class="notify-fin-drill" aria-label="{{ __('notify.finance_hub.overview.drill_title') }}">
        <a href="{{ route('finance.collections') }}">{{ __('notify.finance_hub.sections.collections') }}</a>
        <a href="{{ route('finance.expenses') }}">{{ __('notify.finance_hub.sections.expenses') }}</a>
        <a href="{{ route('finance.accounts') }}">{{ __('notify.finance_hub.sections.accounts') }}</a>
        <a href="{{ route('finance.reports') }}">{{ __('notify.finance_hub.sections.reports') }}</a>
    </nav>
</div>
@endsection
