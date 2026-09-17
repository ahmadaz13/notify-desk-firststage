@extends('layouts.app')

@section('content')
@php
    $jod = fn (int $minor) => $metrics->jod($minor);
    $bps = fn (?int $value) => $metrics->bps($value);
@endphp

<div class="p5-wrap">
    {{-- Page Header --}}
    <header class="p5-header">
        <div class="p5-header-main">
            <div class="p5-eyebrow">{{ __('notify.saas.eyebrow') }}</div>
            <h1 class="p5-title">{{ __('notify.saas.title') }}</h1>
            <p class="p5-subtitle">{{ __('notify.saas.subtitle') }}</p>
        </div>
        <div class="p5-header-actions">
            <a class="p5-btn p5-btn-soft" href="{{ route('saas-metrics.export', ['report' => 'summary'] + request()->query()) }}">{{ __('notify.saas.export_summary') }}</a>
            <a class="p5-btn p5-btn-soft" href="{{ route('saas-metrics.export', ['report' => 'monthly-movement'] + request()->query()) }}">{{ __('notify.saas.export_monthly_movement') }}</a>
            <a class="p5-btn p5-btn-primary" href="{{ route('executive.index', request()->query()) }}">{{ __('notify.saas.executive_dashboard_btn') }}</a>
        </div>
    </header>

    {{-- Top Headline Metrics --}}
    <div class="p5-kpis">
        <div class="p5-kpi-card">
            <span class="p5-kpi-label">{{ __('notify.saas.mrr') }}</span>
            <span class="p5-kpi-value is-primary">{{ $jod($report['ending_mrr_minor']) }} {{ __('notify.common.currency_jod') }}</span>
            <span class="p5-kpi-meta">{{ __('notify.saas.mrr_calc_meta') }}</span>
        </div>
        <div class="p5-kpi-card">
            <span class="p5-kpi-label">{{ __('notify.saas.arr') }}</span>
            <span class="p5-kpi-value is-primary">{{ $jod($report['ending_arr_minor']) }} {{ __('notify.common.currency_jod') }}</span>
            <span class="p5-kpi-meta">{{ __('notify.saas.arr_calc_meta') }}</span>
        </div>
        <div class="p5-kpi-card">
            <span class="p5-kpi-label">{{ __('notify.saas.active_subscriptions') }}</span>
            <span class="p5-kpi-value is-success">{{ $report['active_subscriptions'] }}</span>
            <span class="p5-kpi-meta">{{ __('notify.saas.active_subscriptions_meta') }}</span>
        </div>
        <div class="p5-kpi-card">
            <span class="p5-kpi-label">{{ __('notify.saas.active_clients') }}</span>
            <span class="p5-kpi-value is-success">{{ $report['active_subscribing_clients'] }}</span>
            <span class="p5-kpi-meta">{{ __('notify.saas.active_clients_meta') }}</span>
        </div>
    </div>

    {{-- Movements & Retention Grid --}}
    <div class="p5-kpis">
        <div class="p5-kpi-card">
            <span class="p5-kpi-label">{{ __('notify.saas.new_mrr') }}</span>
            <span class="p5-kpi-value is-success">+{{ $jod($report['movements']['new_mrr_minor']) }} {{ __('notify.common.currency_jod') }}</span>
        </div>
        <div class="p5-kpi-card">
            <span class="p5-kpi-label">{{ __('notify.saas.expansion_mrr') }}</span>
            <span class="p5-kpi-value is-primary">+{{ $jod($report['movements']['expansion_mrr_minor']) }} {{ __('notify.common.currency_jod') }}</span>
        </div>
        <div class="p5-kpi-card">
            <span class="p5-kpi-label">{{ __('notify.saas.contraction_mrr') }}</span>
            <span class="p5-kpi-value is-warning">-{{ $jod($report['movements']['contraction_mrr_minor']) }} {{ __('notify.common.currency_jod') }}</span>
        </div>
        <div class="p5-kpi-card">
            <span class="p5-kpi-label">{{ __('notify.saas.churn_mrr') }}</span>
            <span class="p5-kpi-value is-danger">-{{ $jod($report['movements']['churn_mrr_minor']) }} {{ __('notify.common.currency_jod') }}</span>
        </div>
        <div class="p5-kpi-card">
            <span class="p5-kpi-label">{{ __('notify.saas.reactivation_mrr') }}</span>
            <span class="p5-kpi-value is-success">+{{ $jod($report['movements']['reactivation_mrr_minor']) }} {{ __('notify.common.currency_jod') }}</span>
        </div>
        <div class="p5-kpi-card">
            <span class="p5-kpi-label">{{ __('notify.saas.net_new') }}</span>
            <span class="p5-kpi-value {{ $report['net_new_mrr_minor'] >= 0 ? 'is-success' : 'is-danger' }}">{{ $jod($report['net_new_mrr_minor']) }} {{ __('notify.common.currency_jod') }}</span>
        </div>
        <div class="p5-kpi-card">
            <span class="p5-kpi-label">{{ __('notify.saas.nrr') }}</span>
            <span class="p5-kpi-value is-primary">{{ $bps($report['nrr_bps']) }}</span>
            <span class="p5-kpi-meta">{{ __('notify.saas.nrr_meta') }}</span>
        </div>
        <div class="p5-kpi-card">
            <span class="p5-kpi-label">{{ __('notify.saas.grr') }}</span>
            <span class="p5-kpi-value">{{ $bps($report['grr_bps']) }}</span>
            <span class="p5-kpi-meta">{{ __('notify.saas.grr_meta') }}</span>
        </div>
    </div>

    {{-- Plan Breakdown & Billing Mix --}}
    <div class="p5-grid-2">
        {{-- Plan Breakdown --}}
        <div class="p5-card">
            <div class="p5-card-head">
                <h2 class="p5-card-title">{{ __('notify.saas.mrr_by_plan') }}</h2>
                <span class="p5-kpi-meta">{{ __('notify.saas.recognized_comparison_meta') }}</span>
            </div>
            <div class="p5-table-wrap">
                <table class="p5-table">
                    <thead>
                        <tr>
                            <th>{{ __('notify.saas.plan_col') }}</th>
                            <th>{{ __('notify.saas.subscriptions_col') }}</th>
                            <th>MRR</th>
                            <th>ARR</th>
                            <th>{{ __('notify.saas.recognized_col') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($report['plan_metrics'] as $row)
                            <tr>
                                <td><strong>{{ $row['plan_name'] }}</strong></td>
                                <td>{{ $row['active_subscriptions'] }}</td>
                                <td>{{ $jod($row['mrr_minor']) }}</td>
                                <td>{{ $jod($row['arr_minor']) }}</td>
                                <td>{{ $jod($row['recognized_revenue_minor']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="p5-kpi-meta" style="text-align:center">{{ __('notify.saas.empty_periods') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <span class="p5-kpi-meta" style="margin-top:8px">{{ __('notify.saas.mrr_by_plan_meta') }}</span>
        </div>

        {{-- Billing Mix & Renewal Visibility --}}
        <div class="p5-card">
            <div class="p5-card-head">
                <h2 class="p5-card-title">{{ __('notify.saas.billing_mix') }}</h2>
                <span class="p5-kpi-meta">{{ __('notify.saas.billing_mix_subtitle') }}</span>
            </div>
            <div class="p5-grid-2" style="margin-bottom:14px">
                <div class="p5-list-item">
                    <span class="p5-kpi-label">{{ __('notify.saas.monthly_subscriptions_count') }}</span>
                    <strong style="font-size:18px;color:#0A1128">{{ $report['billing_interval_metrics']['monthly_count'] }}</strong>
                    <span class="p5-kpi-meta">{{ __('notify.saas.monthly_arr_label') }} {{ $jod($report['billing_interval_metrics']['monthly_arr_minor']) }}</span>
                </div>
                <div class="p5-list-item">
                    <span class="p5-kpi-label">{{ __('notify.saas.annual_subscriptions_count') }}</span>
                    <strong style="font-size:18px;color:#0A1128">{{ $report['billing_interval_metrics']['annual_count'] }}</strong>
                    <span class="p5-kpi-meta">{{ __('notify.saas.annual_arr_label') }} {{ $jod($report['billing_interval_metrics']['annual_arr_minor']) }}</span>
                </div>
            </div>

            <h3 style="font-size:14px;color:#0A1128;margin-bottom:8px">{{ __('notify.saas.renewal_visibility') }}</h3>
            <div class="p5-list">
                <div class="p5-list-item" style="display:flex;justify-content:space-between;align-items:center">
                    <span>{{ __('notify.saas.due_in_7_days') }}</span>
                    <strong>{{ $report['renewal_metrics']['due_7_count'] }}</strong>
                </div>
                <div class="p5-list-item" style="display:flex;justify-content:space-between;align-items:center">
                    <span>{{ __('notify.saas.due_in_30_days') }}</span>
                    <strong>{{ $report['renewal_metrics']['due_30_count'] }}</strong>
                </div>
                <div class="p5-list-item" style="display:flex;justify-content:space-between;align-items:center">
                    <span>{{ __('notify.saas.annual_due') }}</span>
                    <strong>{{ $report['renewal_metrics']['annual_due_count'] }}</strong>
                </div>
                <div class="p5-list-item" style="display:flex;justify-content:space-between;align-items:center">
                    <span>{{ __('notify.saas.monthly_due') }}</span>
                    <strong>{{ $report['renewal_metrics']['monthly_due_count'] }}</strong>
                </div>
                <div class="p5-list-item" style="display:flex;justify-content:space-between;align-items:center">
                    <span>{{ __('notify.saas.pending_cancellations') }}</span>
                    <strong style="color:#B42318">{{ $report['renewal_metrics']['pending_cancellations'] }}</strong>
                </div>
                <div class="p5-list-item" style="display:flex;justify-content:space-between;align-items:center">
                    <span>{{ __('notify.saas.billing_review') }}</span>
                    <strong style="color:#D97706">{{ $report['renewal_metrics']['billing_review_count'] }}</strong>
                </div>
            </div>
        </div>
    </div>

    {{-- 12-Month Trend Table --}}
    <div class="p5-card">
        <div class="p5-card-head">
            <h2 class="p5-card-title">{{ __('notify.saas.trend_title') }}</h2>
            <span class="p5-kpi-meta">{{ __('notify.saas.trend_subtitle') }}</span>
        </div>
        <div class="p5-table-wrap">
            <table class="p5-table">
                <thead>
                    <tr>
                        <th>{{ __('notify.saas.month_col') }}</th>
                        <th>{{ __('notify.saas.ending_mrr_col') }}</th>
                        <th>{{ __("notify.saas.new") }}</th>
                        <th>{{ __("notify.saas.expansion") }}</th>
                        <th>{{ __("notify.saas.contraction") }}</th>
                        <th>{{ __("notify.saas.churn") }}</th>
                        <th>{{ __("notify.saas.reactivation") }}</th>
                        <th>{{ __('notify.saas.net_growth_col') }}</th>
                        <th>{{ __('notify.saas.subscribers_col') }}</th>
                        <th>NRR</th>
                        <th>GRR</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($report['trend'] as $row)
                        <tr>
                            <td><strong>{{ $row['month'] }}</strong></td>
                            <td>{{ $jod($row['ending_mrr_minor']) }}</td>
                            <td style="color:#16A34A">{{ $jod($row['new_mrr_minor']) }}</td>
                            <td style="color:#0055CC">{{ $jod($row['expansion_mrr_minor']) }}</td>
                            <td style="color:#D97706">{{ $jod($row['contraction_mrr_minor']) }}</td>
                            <td style="color:#B42318">{{ $jod($row['churn_mrr_minor']) }}</td>
                            <td style="color:#16A34A">{{ $jod($row['reactivation_mrr_minor']) }}</td>
                            <td><strong>{{ $jod($row['net_new_mrr_minor']) }}</strong></td>
                            <td>{{ $row['active_subscribers'] }}</td>
                            <td>{{ $bps($row['nrr_bps']) }}</td>
                            <td>{{ $bps($row['grr_bps']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- SaaS Metrics Reconciliation --}}
    <div class="p5-card">
        <div class="p5-card-head">
            <h2 class="p5-card-title">{{ __('notify.saas.reconciliation_title') }}</h2>
            <span class="p5-badge {{ $reconciliationResult['ok'] ? 'p5-badge-success' : 'p5-badge-danger' }}">
                {{ $reconciliationResult['ok'] ? __('notify.statuses.reconciled') : __('notify.statuses.needs_review') }}
            </span>
        </div>
        @if(! $reconciliationResult['ok'])
            <div style="background:#FEF2F2;border:1px solid #FCA5A5;border-radius:8px;padding:12px;margin-top:10px">
                <pre style="white-space:pre-wrap;font-size:12px;color:#B42318;margin:0">{{ json_encode($reconciliationResult['failures'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
            </div>
        @else
            <span class="p5-kpi-meta">{{ __('notify.saas.reconciliation_ok') }}</span>
        @endif
    </div>
</div>
@endsection
