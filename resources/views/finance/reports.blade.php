@extends('layouts.app')

@php
    $accountName = fn ($account) => $account
        ? (app()->getLocale() === 'en' ? ($account->name_en ?: $account->name_ar) : ($account->name_ar ?: $account->name_en))
        : '';
    $label = fn (string $group, string $key) => \Illuminate\Support\Facades\Lang::has('notify.finance_hub.reports.'.$group.'.'.$key)
        ? __('notify.finance_hub.reports.'.$group.'.'.$key)
        : ucfirst(str_replace('_', ' ', $key));
    $query = request()->only(['range', 'date_from', 'date_to', 'comparison']);
@endphp

@section('content')
<div class="notify-fin" data-finance-page="reports" data-report="{{ $report }}">
    @include('finance.partials.header', [
        'active' => 'reports',
        'title' => __('notify.finance_hub.sections.reports'),
        'subtitle' => __('notify.finance_hub.reports.subtitle'),
    ])

    <nav class="notify-fin-tabs" aria-label="{{ __('notify.finance_hub.sections.reports') }}">
        @foreach($reports as $key)
            <a href="{{ route('finance.reports', ['report' => $key] + $query) }}" class="notify-fin-tabs__item @if($report === $key) is-active @endif" data-report-tab="{{ $key }}" @if($report === $key) aria-current="page" @endif>{{ __('notify.finance_hub.reports.names.'.$key) }}</a>
        @endforeach
    </nav>

    <div class="notify-fin-toolbar">
        @include('finance.partials.period-bar', [
            'action' => route('finance.reports', ['report' => $report]),
            'withComparison' => in_array($report, ['profit-and-loss', 'financial-position'], true),
            'keep' => $report === 'revenue' && $revenueView === 'deferred' ? ['view' => 'deferred'] : [],
        ])
        @if($canExport)
            <a class="notify-button notify-button--primary" data-report-export
               href="{{ route('finance.reports.export', ['report' => $report] + $query + ($report === 'revenue' && $revenueView === 'deferred' ? ['dataset' => 'deferred'] : [])) }}">{{ __('notify.finance_hub.reports.export_csv') }}</a>
        @endif
    </div>

    <section class="notify-fin-card notify-fin-report" aria-label="{{ __('notify.finance_hub.reports.names.'.$report) }}">
        @switch($report)
            @case('profit-and-loss')
                <h2 class="notify-fin-card__title">{{ __('notify.finance_hub.reports.names.profit-and-loss') }}</h2>
                <dl class="notify-fin-statement">
                    <dt class="is-heading">{{ __('notify.finance_hub.reports.revenue') }}</dt><dd></dd>
                    @foreach($data['revenue_rows'] as $row)
                        <dt>{{ $accountName($row['account']) }}</dt><dd><x-notify.money :minor="$row['amount_minor']" /></dd>
                    @endforeach
                    <dt class="is-total">{{ __('notify.finance_hub.reports.total_revenue') }}</dt><dd class="is-total"><x-notify.money :minor="$data['total_revenue_minor']" /></dd>
                    <dt class="is-heading">{{ __('notify.finance_hub.reports.expenses') }}</dt><dd></dd>
                    @foreach($data['expense_rows'] as $row)
                        <dt>{{ $accountName($row['account']) }}</dt><dd><x-notify.money :minor="$row['amount_minor']" /></dd>
                    @endforeach
                    <dt class="is-total">{{ __('notify.finance_hub.reports.total_expenses') }}</dt><dd class="is-total"><x-notify.money :minor="$data['total_expenses_minor']" /></dd>
                    <dt class="is-grand">{{ __('notify.finance_hub.reports.net_result') }}</dt><dd class="is-grand"><x-notify.money :minor="$data['net_income_minor']" /></dd>
                    @if($data['comparison'])
                        <dt>{{ __('notify.finance_hub.reports.comparison_net') }}</dt><dd><x-notify.money :minor="$data['comparison']['net_income_minor']" /></dd>
                    @endif
                </dl>
                <p class="notify-fin-hint">{{ __('notify.finance_hub.reports.pnl_note') }}</p>
                @break

            @case('financial-position')
                @php($sheet = $data['current'])
                <h2 class="notify-fin-card__title">{{ __('notify.finance_hub.reports.names.financial-position') }} · <span dir="ltr">{{ $sheet['as_of']->format('Y-m-d') }}</span></h2>
                <dl class="notify-fin-statement">
                    @foreach(['assets' => 'total_assets_minor', 'liabilities' => 'total_liabilities_minor', 'equity' => 'total_equity_minor'] as $section => $totalKey)
                        <dt class="is-heading">{{ $label('sections', $section) }}</dt><dd></dd>
                        @foreach($sheet['sections'][$section] as $group => $groupData)
                            @if($groupData['total_minor'] !== 0)
                                <dt>{{ $label('sections', $group) }}</dt><dd><x-notify.money :minor="$groupData['total_minor']" /></dd>
                            @endif
                        @endforeach
                        <dt class="is-total">{{ __('notify.finance_hub.reports.total') }} {{ $label('sections', $section) }}</dt><dd class="is-total"><x-notify.money :minor="$sheet[$totalKey]" /></dd>
                        @if($data['comparison'])
                            <dt>{{ __('notify.finance_hub.reports.comparison') }}</dt><dd><x-notify.money :minor="$data['comparison'][$totalKey]" /></dd>
                        @endif
                    @endforeach
                </dl>
                @if($sheet['equation_difference_minor'] !== 0)
                    <p class="notify-fin-flash notify-fin-flash--danger">{{ __('notify.finance_hub.reports.equation_warning') }} <x-notify.money :minor="$sheet['equation_difference_minor']" /></p>
                @endif
                <p class="notify-fin-hint">{{ __('notify.finance_hub.reports.position_note') }}</p>
                @break

            @case('cash-flow')
                <h2 class="notify-fin-card__title">{{ __('notify.finance_hub.reports.names.cash-flow') }}</h2>
                <dl class="notify-fin-statement">
                    <dt>{{ __('notify.finance_hub.reports.opening_cash') }}</dt><dd><x-notify.money :minor="$data['opening_cash_minor']" /></dd>
                    <dt>{{ __('notify.finance_hub.reports.operating') }}</dt><dd><x-notify.money :minor="$data['operating_cash_flow_minor']" :signed="true" /></dd>
                    <dt>{{ __('notify.finance_hub.reports.investing') }}</dt><dd><x-notify.money :minor="$data['investing_cash_flow_minor']" :signed="true" /></dd>
                    <dt>{{ __('notify.finance_hub.reports.financing') }}</dt><dd><x-notify.money :minor="$data['financing_cash_flow_minor']" :signed="true" /></dd>
                    <dt>{{ __('notify.finance_hub.reports.internal_transfers') }}</dt><dd><x-notify.money :minor="$data['internal_transfer_minor']" /></dd>
                    <dt class="is-grand">{{ __('notify.finance_hub.reports.closing_cash') }}</dt><dd class="is-grand"><x-notify.money :minor="$data['closing_cash_minor']" /></dd>
                </dl>
                <p class="notify-fin-hint">{{ __('notify.finance_hub.reports.cash_flow_note') }}</p>
                @break

            @case('revenue')
                <div class="notify-fin-card__head">
                    <h2 class="notify-fin-card__title">{{ __('notify.finance_hub.reports.names.revenue') }}</h2>
                    <nav class="notify-fin-switch">
                        <a href="{{ route('finance.reports', ['report' => 'revenue'] + $query) }}" @class(['is-active' => $revenueView === 'recognized'])>{{ __('notify.finance_hub.reports.recognized') }}</a>
                        <a href="{{ route('finance.reports', ['report' => 'revenue', 'view' => 'deferred'] + $query) }}" @class(['is-active' => $revenueView === 'deferred'])>{{ __('notify.finance_hub.reports.deferred') }}</a>
                    </nav>
                </div>
                @if($revenueView === 'recognized')
                    <dl class="notify-fin-statement">
                        <dt>{{ __('notify.finance_hub.reports.subscription_revenue') }}</dt><dd><x-notify.money :minor="$data['saas_revenue_minor']" /></dd>
                        <dt>{{ __('notify.finance_hub.reports.one_time_revenue') }}</dt><dd><x-notify.money :minor="$data['one_time_revenue_minor']" /></dd>
                        <dt class="is-heading">{{ __('notify.finance_hub.reports.by_month') }}</dt><dd></dd>
                        @foreach($data['by_month'] as $row)
                            <dt dir="ltr">{{ $row['month'] }}</dt><dd><x-notify.money :minor="$row['amount_minor']" /></dd>
                        @endforeach
                    </dl>
                @else
                    <dl class="notify-fin-statement">
                        <dt>{{ __('notify.finance_hub.reports.opening_deferred') }}</dt><dd><x-notify.money :minor="$data['summary']['opening_deferred_revenue_minor']" /></dd>
                        <dt>{{ __('notify.finance_hub.reports.new_deferred') }}</dt><dd><x-notify.money :minor="$data['summary']['new_deferred_billings_minor']" /></dd>
                        <dt>{{ __('notify.finance_hub.reports.recognized_in_period') }}</dt><dd><x-notify.money :minor="$data['summary']['recognition_during_period_minor']" /></dd>
                        <dt class="is-grand">{{ __('notify.finance_hub.reports.closing_deferred') }}</dt><dd class="is-grand"><x-notify.money :minor="$data['summary']['closing_deferred_revenue_minor']" /></dd>
                    </dl>
                @endif
                @break

            @case('expenses')
                <h2 class="notify-fin-card__title">{{ __('notify.finance_hub.reports.names.expenses') }}</h2>
                <dl class="notify-fin-statement">
                    @forelse($data['by_category'] as $row)
                        <dt>{{ $row->category_name_snapshot ?: __('notify.common.unspecified') }}</dt><dd><x-notify.money :minor="(int) $row->amount_minor" /></dd>
                    @empty
                        <dt>{{ __('notify.finance_hub.reports.no_expenses') }}</dt><dd></dd>
                    @endforelse
                    <dt class="is-grand">{{ __('notify.finance_hub.reports.company_paid') }}</dt><dd class="is-grand"><x-notify.money :minor="$data['company_funded_minor']" /></dd>
                </dl>
                @break

            @case('receivables')
                @php($aging = $data['aging'])
                <h2 class="notify-fin-card__title">{{ __('notify.finance_hub.reports.names.receivables') }}</h2>
                <dl class="notify-fin-stats">
                    <div><dt>{{ __('notify.finance_hub.reports.total_receivable') }}</dt><dd><x-notify.money :minor="$aging['summary']['total_ar_minor']" /></dd></div>
                    <div><dt>{{ __('notify.finance_hub.reports.overdue') }}</dt><dd><x-notify.money :minor="$aging['summary']['total_overdue_minor']" /></dd></div>
                    <div><dt>{{ __('notify.finance_hub.reports.collected_in_period') }}</dt><dd><x-notify.money :minor="$data['collected_minor']" /></dd></div>
                </dl>
                <dl class="notify-fin-statement">
                    @foreach($aging['summary']['buckets'] as $bucket => $amount)
                        <dt>{{ $label('buckets', $bucket) }}</dt><dd><x-notify.money :minor="$amount" /></dd>
                    @endforeach
                </dl>
                <h3 class="notify-fin-card__subtitle">{{ __('notify.finance_hub.reports.outstanding_invoices') }}</h3>
                <div class="notify-fin-list notify-fin-list--compact">
                    @forelse($aging['items'] as $item)
                        <article class="notify-fin-movement">
                            <div class="notify-fin-movement__main">
                                <strong>{{ $item['client']?->business_name }}</strong>
                                <small><span dir="ltr">{{ $item['invoice']->invoice_number }} · {{ $item['due_date']?->format('Y-m-d') }}</span>@if($item['days_overdue'] > 0) · {{ __('notify.finance_hub.overview.days_overdue', ['days' => $item['days_overdue']]) }}@endif</small>
                            </div>
                            <x-notify.money class="notify-fin-movement__amount" :minor="$item['outstanding_minor']" />
                        </article>
                    @empty
                        <p class="notify-fin-empty">{{ __('notify.finance_hub.collections.empty_due') }}</p>
                    @endforelse
                </div>
                <h3 class="notify-fin-card__subtitle">{{ __('notify.finance_hub.reports.payments_received') }}</h3>
                <div class="notify-fin-list notify-fin-list--compact">
                    @forelse($data['payments'] as $payment)
                        <article class="notify-fin-movement">
                            <div class="notify-fin-movement__main">
                                <strong>{{ $payment->client?->business_name }}</strong>
                                <small>{{ \App\Support\PaymentMethods::label($payment->payment_method) }} · <span dir="ltr">{{ $payment->received_at?->format('Y-m-d') }}</span></small>
                            </div>
                            <x-notify.money class="notify-fin-movement__amount is-in" :minor="(int) $payment->amount_minor" />
                        </article>
                    @empty
                        <p class="notify-fin-empty">{{ __('notify.finance_hub.collections.empty_payments') }}</p>
                    @endforelse
                </div>
                @break

            @case('subscription-metrics')
                <h2 class="notify-fin-card__title">{{ __('notify.finance_hub.reports.names.subscription-metrics') }}</h2>
                <p class="notify-fin-note" data-not-revenue-note>{{ __('notify.finance_hub.overview.saas_title') }}</p>
                <dl class="notify-fin-stats">
                    <div><dt>{{ __('notify.finance_hub.overview.mrr') }}</dt><dd><x-notify.money :minor="$data['ending_mrr_minor']" /></dd></div>
                    <div><dt>{{ __('notify.finance_hub.overview.arr') }}</dt><dd><x-notify.money :minor="$data['ending_arr_minor']" /></dd></div>
                    <div><dt>{{ __('notify.finance_hub.overview.active_subscriptions') }}</dt><dd dir="ltr">{{ $data['active_subscriptions'] }}</dd></div>
                    <div><dt>{{ __('notify.finance_hub.reports.net_new_mrr') }}</dt><dd><x-notify.money :minor="$data['net_new_mrr_minor']" :signed="true" /></dd></div>
                </dl>
                <dl class="notify-fin-statement">
                    @foreach(['new_mrr_minor', 'expansion_mrr_minor', 'contraction_mrr_minor', 'churn_mrr_minor', 'reactivation_mrr_minor'] as $movementKey)
                        <dt>{{ $label('saas', $movementKey) }}</dt><dd><x-notify.money :minor="$data['movements'][$movementKey]" /></dd>
                    @endforeach
                    <dt>{{ __('notify.finance_hub.reports.renewals_30') }}</dt><dd dir="ltr">{{ $data['renewal_metrics']['due_30_count'] }}</dd>
                </dl>
                @if($canExport)
                    <p class="notify-fin-hint">
                        {{ __('notify.finance_hub.reports.more_exports') }}
                        @foreach(\App\Http\Controllers\FinanceReportController::SAAS_DATASETS as $dataset)
                            <a class="notify-fin-link" href="{{ route('finance.reports.export', ['report' => 'subscription-metrics', 'dataset' => $dataset] + $query) }}">{{ $label('saas_datasets', $dataset) }}</a>
                        @endforeach
                    </p>
                @endif
                @break
        @endswitch
    </section>
</div>
@endsection
