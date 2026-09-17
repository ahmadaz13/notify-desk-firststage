@extends('layouts.app')

@section('content')
@php
    $jod = fn (int $minor) => $saas->jod($minor);
    $bps = fn (?int $value) => $saas->bps($value);
@endphp

<div class="p5-wrap">
    {{-- Page Header --}}
    <header class="p5-header">
        <div class="p5-header-main">
            <div class="p5-eyebrow">{{ __('notify.executive.eyebrow') }}</div>
            <h1 class="p5-title">{{ __('notify.executive.title') }}</h1>
            <p class="p5-subtitle">{{ __('notify.executive.subtitle') }}</p>
        </div>
        <div class="p5-header-actions">
            <a class="p5-btn p5-btn-soft" href="{{ route('finance.index', request()->query()) }}">{{ __('notify.executive.finance_reports_btn') }}</a>
            <a class="p5-btn p5-btn-primary" href="{{ route('saas-metrics.index', request()->query()) }}">{{ __('notify.executive.saas_details_btn') }}</a>
        </div>
    </header>

    {{-- Primary Health Metrics --}}
    <div class="p5-kpis">
        <div class="p5-kpi-card">
            <span class="p5-kpi-label">{{ __('notify.executive.mrr') }}</span>
            <span class="p5-kpi-value is-primary">{{ $jod($saasReport['ending_mrr_minor']) }} {{ __('notify.common.currency_jod') }}</span>
            <span class="p5-kpi-meta">{{ __('notify.executive.mrr_contract_meta') }}</span>
        </div>
        <div class="p5-kpi-card">
            <span class="p5-kpi-label">{{ __('notify.executive.arr') }}</span>
            <span class="p5-kpi-value is-primary">{{ $jod($saasReport['ending_arr_minor']) }} {{ __('notify.common.currency_jod') }}</span>
            <span class="p5-kpi-meta">{{ __('notify.executive.arr_contract_meta') }}</span>
        </div>
        <div class="p5-kpi-card">
            <span class="p5-kpi-label">{{ __('notify.executive.active_subscribers') }}</span>
            <span class="p5-kpi-value is-success">{{ $saasReport['active_subscribing_clients'] }}</span>
            <span class="p5-kpi-meta">{{ $saasReport['active_subscriptions'] }} {{ __('notify.executive.active_subscriptions_count') }}</span>
        </div>
        <div class="p5-kpi-card">
            <span class="p5-kpi-label">{{ __('notify.executive.net_new_mrr') }}</span>
            <span class="p5-kpi-value {{ $saasReport['net_new_mrr_minor'] >= 0 ? 'is-success' : 'is-danger' }}">{{ $jod($saasReport['net_new_mrr_minor']) }} {{ __('notify.common.currency_jod') }}</span>
            <span class="p5-kpi-meta">{{ __('notify.executive.net_new_mrr_formula') }}</span>
        </div>
        <div class="p5-kpi-card">
            <span class="p5-kpi-label">{{ __('notify.executive.churn') }}</span>
            <span class="p5-kpi-value is-danger">{{ $jod($saasReport['movements']['churn_mrr_minor']) }} {{ __('notify.common.currency_jod') }}</span>
            <span class="p5-kpi-meta">{{ __('notify.executive.churn_meta') }}</span>
        </div>
        <div class="p5-kpi-card">
            <span class="p5-kpi-label">{{ __('notify.executive.recognized_revenue_this_period') }}</span>
            <span class="p5-kpi-value">{{ $jod($finance['recognized_revenue_this_period_minor']) }} {{ __('notify.common.currency_jod') }}</span>
            <span class="p5-kpi-meta">{{ __('notify.executive.recognized_revenue_meta') }}</span>
        </div>
        <div class="p5-kpi-card">
            <span class="p5-kpi-label">{{ __('notify.executive.cash_collected_this_period') }}</span>
            <span class="p5-kpi-value is-success">{{ $jod($saasReport['cash_collected_minor']) }} {{ __('notify.common.currency_jod') }}</span>
            <span class="p5-kpi-meta">{{ __('notify.executive.cash_collected_meta') }}</span>
        </div>
        <div class="p5-kpi-card">
            <span class="p5-kpi-label">{{ __('notify.executive.cash_available') }}</span>
            <span class="p5-kpi-value is-primary">{{ $jod($finance['cash_available_minor']) }} {{ __('notify.common.currency_jod') }}</span>
            <span class="p5-kpi-meta">{{ __('notify.executive.cash_available_meta') }}</span>
        </div>
        <div class="p5-kpi-card">
            <span class="p5-kpi-label">{{ __('notify.executive.receivables') }}</span>
            <span class="p5-kpi-value">{{ $jod($finance['accounts_receivable_minor']) }} {{ __('notify.common.currency_jod') }}</span>
            <span class="p5-kpi-meta">{{ __('notify.executive.receivables_meta') }}</span>
        </div>
        <div class="p5-kpi-card">
            <span class="p5-kpi-label">{{ __('notify.executive.operating_expenses_label') }}</span>
            <span class="p5-kpi-value is-warning">{{ $jod($finance['operating_expenses_this_period_minor']) }} {{ __('notify.common.currency_jod') }}</span>
            <span class="p5-kpi-meta">{{ __('notify.executive.operating_expenses_meta') }}</span>
        </div>
        <div class="p5-kpi-card">
            <span class="p5-kpi-label">{{ __('notify.executive.management_net_income') }}</span>
            <span class="p5-kpi-value {{ $finance['management_net_income_this_period_minor'] >= 0 ? 'is-success' : 'is-danger' }}">{{ $jod($finance['management_net_income_this_period_minor']) }} {{ __('notify.common.currency_jod') }}</span>
            <span class="p5-kpi-meta">{{ __('notify.executive.management_net_income_meta') }}</span>
        </div>
        <div class="p5-kpi-card">
            <span class="p5-kpi-label">{{ __('notify.executive.upcoming_renewals_30') }}</span>
            <span class="p5-kpi-value">{{ $saasReport['renewal_metrics']['due_30_count'] }}</span>
            <span class="p5-kpi-meta">{{ __('notify.executive.upcoming_renewals_meta') }}</span>
        </div>
    </div>

    {{-- Secondary SaaS Metrics --}}
    <div class="p5-kpis">
        <div class="p5-kpi-card">
            <span class="p5-kpi-label">{{ __('notify.executive.new_mrr') }}</span>
            <span class="p5-kpi-value is-success">{{ $jod($saasReport['movements']['new_mrr_minor']) }}</span>
        </div>
        <div class="p5-kpi-card">
            <span class="p5-kpi-label">{{ __('notify.executive.expansion_mrr') }}</span>
            <span class="p5-kpi-value is-primary">{{ $jod($saasReport['movements']['expansion_mrr_minor']) }}</span>
        </div>
        <div class="p5-kpi-card">
            <span class="p5-kpi-label">{{ __('notify.executive.contraction_mrr') }}</span>
            <span class="p5-kpi-value is-warning">{{ $jod($saasReport['movements']['contraction_mrr_minor']) }}</span>
        </div>
        <div class="p5-kpi-card">
            <span class="p5-kpi-label">{{ __('notify.executive.reactivation_mrr') }}</span>
            <span class="p5-kpi-value is-success">{{ $jod($saasReport['movements']['reactivation_mrr_minor']) }}</span>
        </div>
        <div class="p5-kpi-card">
            <span class="p5-kpi-label">{{ __('notify.executive.logo_churn') }}</span>
            <span class="p5-kpi-value {{ $saasReport['logo_churn_rate_bps'] > 0 ? 'is-danger' : 'is-success' }}">{{ $bps($saasReport['logo_churn_rate_bps']) }}</span>
        </div>
        <div class="p5-kpi-card">
            <span class="p5-kpi-label">{{ __('notify.executive.nrr') }}</span>
            <span class="p5-kpi-value is-primary">{{ $bps($saasReport['nrr_bps']) }}</span>
        </div>
        <div class="p5-kpi-card">
            <span class="p5-kpi-label">{{ __('notify.executive.grr') }}</span>
            <span class="p5-kpi-value">{{ $bps($saasReport['grr_bps']) }}</span>
        </div>
        <div class="p5-kpi-card">
            <span class="p5-kpi-label">{{ __('notify.executive.deferred_revenue') }}</span>
            <span class="p5-kpi-value">{{ $jod($finance['deferred_revenue_minor']) }}</span>
        </div>
    </div>

    {{-- MRR Movement Waterfall & MRR by Plan --}}
    <div class="p5-grid-2">
        <div class="p5-card">
            <div class="p5-card-head">
                <h2 class="p5-card-title">{{ __('notify.executive.waterfall_title') }}</h2>
                <span class="p5-kpi-meta">{{ __('notify.executive.waterfall_subtitle') }}</span>
            </div>
            <div class="p5-table-wrap">
                <table class="p5-table">
                    <tbody>
                        <tr><th>{{ __('notify.executive.starting_mrr') }}</th><td>{{ $jod($saasReport['starting_mrr_minor']) }}</td></tr>
                        <tr><th>{{ __('notify.executive.new_mrr') }}</th><td style="color:#16A34A">+{{ $jod($saasReport['movements']['new_mrr_minor']) }}</td></tr>
                        <tr><th>{{ __('notify.executive.expansion_mrr') }}</th><td style="color:#0055CC">+{{ $jod($saasReport['movements']['expansion_mrr_minor']) }}</td></tr>
                        <tr><th>{{ __('notify.executive.reactivation_mrr') }}</th><td style="color:#16A34A">+{{ $jod($saasReport['movements']['reactivation_mrr_minor']) }}</td></tr>
                        <tr><th>{{ __('notify.executive.contraction_mrr') }}</th><td style="color:#D97706">-{{ $jod($saasReport['movements']['contraction_mrr_minor']) }}</td></tr>
                        <tr><th>{{ __('notify.executive.churn') }}</th><td style="color:#B42318">-{{ $jod($saasReport['movements']['churn_mrr_minor']) }}</td></tr>
                        <tr><th>{{ __('notify.executive.ending_mrr') }}</th><td><strong>{{ $jod($saasReport['ending_mrr_minor']) }}</strong></td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="p5-card">
            <div class="p5-card-head">
                <h2 class="p5-card-title">{{ __('notify.executive.mrr_by_plan') }}</h2>
                <span class="p5-kpi-meta">{{ __('notify.executive.mrr_by_plan_meta') }}</span>
            </div>
            <div class="p5-table-wrap">
                <table class="p5-table">
                    <thead>
                        <tr>
                            <th>{{ __('notify.executive.plan_col') }}</th>
                            <th>MRR</th>
                            <th>ARR</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($saasReport['plan_metrics'] as $row)
                            <tr>
                                <td><strong>{{ $row['plan_name'] }}</strong></td>
                                <td>{{ $jod($row['mrr_minor']) }}</td>
                                <td>{{ $jod($row['arr_minor']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Revenue vs Cash Collected & Reconciliation --}}
    <div class="p5-grid-2">
        <div class="p5-card">
            <div class="p5-card-head">
                <h2 class="p5-card-title">{{ __('notify.executive.revenue_vs_cash') }}</h2>
                <span class="p5-kpi-meta">{{ __('notify.executive.revenue_vs_cash_subtitle') }}</span>
            </div>
            <div class="p5-table-wrap">
                <table class="p5-table">
                    <tbody>
                        <tr><th>{{ __('notify.finance.recognized_revenue') }}</th><td><strong>{{ $jod($finance['recognized_revenue_this_period_minor']) }}</strong></td></tr>
                        <tr><th>{{ __('notify.executive.cash_collected') }}</th><td><strong>{{ $jod($saasReport['cash_collected_minor']) }}</strong></td></tr>
                        <tr><th>{{ __('notify.executive.operating_expenses') }}</th><td>{{ $jod($finance['operating_expenses_this_period_minor']) }}</td></tr>
                        <tr><th>{{ __('notify.executive.overdue_ar') }}</th><td style="color:#B42318">{{ $jod($finance['overdue_receivables_minor']) }}</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="p5-card">
            <div class="p5-card-head">
                <h2 class="p5-card-title">{{ __('notify.executive.reconciliation_title') }}</h2>
                <span class="p5-kpi-meta">{{ __('notify.executive.reconciliation_subtitle') }}</span>
            </div>
            <div class="p5-list">
                <div class="p5-list-item" style="display:flex;justify-content:space-between;align-items:center">
                    <div>
                        <strong>{{ __('notify.executive.saas_reconciliation') }}</strong>
                        <span class="p5-kpi-meta">{{ __('notify.executive.saas_reconciliation_meta') }}</span>
                    </div>
                    <span class="p5-badge {{ $saasReconciliationResult['ok'] ? 'p5-badge-success' : 'p5-badge-danger' }}">
                        {{ $saasReconciliationResult['ok'] ? __('notify.statuses.reconciled') : __('notify.statuses.needs_review') }}
                    </span>
                </div>
                <div class="p5-list-item" style="display:flex;justify-content:space-between;align-items:center">
                    <div>
                        <strong>{{ __('notify.executive.financial_reconciliation') }}</strong>
                        <span class="p5-kpi-meta">{{ __('notify.executive.financial_reconciliation_meta') }}</span>
                    </div>
                    <span class="p5-badge {{ $financialReconciliationResult['ok'] ? 'p5-badge-success' : 'p5-badge-danger' }}">
                        {{ $financialReconciliationResult['ok'] ? __('notify.statuses.reconciled') : __('notify.statuses.needs_review') }}
                    </span>
                </div>
            </div>
            <span class="p5-kpi-meta" style="margin-top:10px;display:block">
                {{ __('notify.executive.reconciliation_note') }}
            </span>
        </div>
    </div>
</div>
@endsection
