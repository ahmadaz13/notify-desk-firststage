@extends('layouts.app')

@section('content')
@php
    $jod = fn (int $minor) => $metrics->jod($minor);
    $bps = fn (?int $value) => $metrics->bps($value);
@endphp

<div class="page-head">
    <div>
        <div class="eyebrow">SaaS Metrics / Audited V2 Subscription Metrics</div>
        <h1 class="page-title">MRR, ARR, Movements and Retention</h1>
        <div class="muted" style="margin-top:5px">MRR/ARR are recurring commercial contract metrics from V2 billing periods, not recognized revenue, cash, invoice totals, or tax.</div>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <a class="btn btn-ghost" href="{{ route('saas-metrics.export', ['report' => 'summary'] + request()->query()) }}">Export Summary</a>
        <a class="btn btn-ghost" href="{{ route('saas-metrics.export', ['report' => 'monthly-movement'] + request()->query()) }}">Export Movement</a>
        <a class="btn btn-primary" href="{{ route('executive.index', request()->query()) }}">Executive Dashboard</a>
    </div>
</div>

<div class="grid grid-4">
    <div class="card"><div class="kpi-label">MRR</div><div class="kpi-value">{{ $jod($report['ending_mrr_minor']) }} د.أ</div><div class="muted">Derived from aggregate canonical ARR / 12</div></div>
    <div class="card"><div class="kpi-label">ARR</div><div class="kpi-value">{{ $jod($report['ending_arr_minor']) }} د.أ</div><div class="muted">Canonical normalized ARR minor units</div></div>
    <div class="card"><div class="kpi-label">Active Subscriptions</div><div class="kpi-value">{{ $report['active_subscriptions'] }}</div><div class="muted">Distinct active V2 subscriptions</div></div>
    <div class="card"><div class="kpi-label">Active Clients</div><div class="kpi-value">{{ $report['active_subscribing_clients'] }}</div><div class="muted">Distinct subscribing clients</div></div>
</div>

<div class="grid grid-4" style="margin-top:14px">
    <div class="card"><div class="kpi-label">New MRR</div><div class="kpi-value">{{ $jod($report['movements']['new_mrr_minor']) }} د.أ</div></div>
    <div class="card"><div class="kpi-label">Expansion MRR</div><div class="kpi-value">{{ $jod($report['movements']['expansion_mrr_minor']) }} د.أ</div></div>
    <div class="card"><div class="kpi-label">Contraction MRR</div><div class="kpi-value">{{ $jod($report['movements']['contraction_mrr_minor']) }} د.أ</div></div>
    <div class="card"><div class="kpi-label">Churn MRR</div><div class="kpi-value">{{ $jod($report['movements']['churn_mrr_minor']) }} د.أ</div></div>
    <div class="card"><div class="kpi-label">Reactivation MRR</div><div class="kpi-value">{{ $jod($report['movements']['reactivation_mrr_minor']) }} د.أ</div></div>
    <div class="card"><div class="kpi-label">Net New MRR</div><div class="kpi-value">{{ $jod($report['net_new_mrr_minor']) }} د.أ</div></div>
    <div class="card"><div class="kpi-label">NRR</div><div class="kpi-value">{{ $bps($report['nrr_bps']) }}</div><div class="muted">Excludes New and Reactivation MRR</div></div>
    <div class="card"><div class="kpi-label">GRR</div><div class="kpi-value">{{ $bps($report['grr_bps']) }}</div><div class="muted">Expansion cannot lift GRR above 100%</div></div>
</div>

<div class="grid grid-2" style="margin-top:18px">
    <div class="card">
        <h3 style="margin-top:0">MRR by Plan</h3>
        <table class="table">
            <thead><tr><th>Plan</th><th>Subscriptions</th><th>MRR</th><th>ARR</th><th>Recognized Revenue</th></tr></thead>
            <tbody>
            @forelse($report['plan_metrics'] as $row)
                <tr>
                    <td>{{ $row['plan_name'] }}</td>
                    <td>{{ $row['active_subscriptions'] }}</td>
                    <td>{{ $jod($row['mrr_minor']) }}</td>
                    <td>{{ $jod($row['arr_minor']) }}</td>
                    <td>{{ $jod($row['recognized_revenue_minor']) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">No active V2 subscription periods.</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="muted" style="margin-top:8px">Recognized Revenue by Plan is shown for comparison only and is not MRR.</div>
    </div>
    <div class="card">
        <h3 style="margin-top:0">Billing Interval Mix</h3>
        <div class="grid grid-2">
            <div><div class="kpi-label">Monthly Count</div><div class="kpi-value">{{ $report['billing_interval_metrics']['monthly_count'] }}</div></div>
            <div><div class="kpi-label">Annual Count</div><div class="kpi-value">{{ $report['billing_interval_metrics']['annual_count'] }}</div></div>
            <div><div class="kpi-label">Monthly ARR</div><div class="kpi-value">{{ $jod($report['billing_interval_metrics']['monthly_arr_minor']) }}</div></div>
            <div><div class="kpi-label">Annual ARR</div><div class="kpi-value">{{ $jod($report['billing_interval_metrics']['annual_arr_minor']) }}</div></div>
        </div>
        <h3>Renewal Visibility</h3>
        <ul>
            <li>Due in 7 days: {{ $report['renewal_metrics']['due_7_count'] }}</li>
            <li>Due in 30 days: {{ $report['renewal_metrics']['due_30_count'] }}</li>
            <li>Annual due: {{ $report['renewal_metrics']['annual_due_count'] }}</li>
            <li>Monthly due: {{ $report['renewal_metrics']['monthly_due_count'] }}</li>
            <li>Pending cancellations: {{ $report['renewal_metrics']['pending_cancellations'] }}</li>
            <li>Billing review items: {{ $report['renewal_metrics']['billing_review_count'] }}</li>
        </ul>
    </div>
</div>

<div class="card" style="margin-top:18px">
    <h3 style="margin-top:0">12-Month Trend</h3>
    <table class="table">
        <thead><tr><th>Month</th><th>Ending MRR</th><th>New</th><th>Expansion</th><th>Contraction</th><th>Churn</th><th>Reactivation</th><th>Net New</th><th>Subscribers</th><th>NRR</th><th>GRR</th></tr></thead>
        <tbody>
        @foreach($report['trend'] as $row)
            <tr>
                <td>{{ $row['month'] }}</td>
                <td>{{ $jod($row['ending_mrr_minor']) }}</td>
                <td>{{ $jod($row['new_mrr_minor']) }}</td>
                <td>{{ $jod($row['expansion_mrr_minor']) }}</td>
                <td>{{ $jod($row['contraction_mrr_minor']) }}</td>
                <td>{{ $jod($row['churn_mrr_minor']) }}</td>
                <td>{{ $jod($row['reactivation_mrr_minor']) }}</td>
                <td>{{ $jod($row['net_new_mrr_minor']) }}</td>
                <td>{{ $row['active_subscribers'] }}</td>
                <td>{{ $bps($row['nrr_bps']) }}</td>
                <td>{{ $bps($row['grr_bps']) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>

<div class="card" style="margin-top:18px">
    <h3 style="margin-top:0">SaaS Metrics Reconciliation</h3>
    <div class="{{ $reconciliationResult['ok'] ? 'badge ok' : 'badge danger' }}">{{ $reconciliationResult['ok'] ? 'OK' : 'Needs Review' }}</div>
    @if(! $reconciliationResult['ok'])
        <pre style="white-space:pre-wrap">{{ json_encode($reconciliationResult['failures'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
    @endif
</div>
@endsection
