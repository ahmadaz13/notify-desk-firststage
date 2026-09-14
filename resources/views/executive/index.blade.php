@extends('layouts.app')

@section('content')
@php
    $jod = fn (int $minor) => $saas->jod($minor);
    $bps = fn (?int $value) => $saas->bps($value);
@endphp

<div class="page-head">
    <div>
        <div class="eyebrow">Founder Executive Dashboard</div>
        <h1 class="page-title">Executive Finance and SaaS Dashboard</h1>
        <div class="muted" style="margin-top:5px">MRR/ARR = SaaS contract metrics. Recognized Revenue = accounting revenue. Cash Collected = customer cash receipts. Cash Available = company account balances.</div>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <a class="btn btn-ghost" href="{{ route('finance.index', request()->query()) }}">Finance Reports</a>
        <a class="btn btn-primary" href="{{ route('saas-metrics.index', request()->query()) }}">SaaS Metrics</a>
    </div>
</div>

<div class="grid grid-4">
    <div class="card"><div class="kpi-label">MRR</div><div class="kpi-value">{{ $jod($saasReport['ending_mrr_minor']) }} د.أ</div><div class="muted">SaaS contract metric</div></div>
    <div class="card"><div class="kpi-label">ARR</div><div class="kpi-value">{{ $jod($saasReport['ending_arr_minor']) }} د.أ</div><div class="muted">SaaS contract metric</div></div>
    <div class="card"><div class="kpi-label">Active Subscribers</div><div class="kpi-value">{{ $saasReport['active_subscribing_clients'] }}</div><div class="muted">{{ $saasReport['active_subscriptions'] }} active subscriptions</div></div>
    <div class="card"><div class="kpi-label">Net New MRR</div><div class="kpi-value">{{ $jod($saasReport['net_new_mrr_minor']) }} د.أ</div><div class="muted">New + Expansion + Reactivation - Contraction - Churn</div></div>
    <div class="card"><div class="kpi-label">MRR Churn</div><div class="kpi-value">{{ $jod($saasReport['movements']['churn_mrr_minor']) }} د.أ</div></div>
    <div class="card"><div class="kpi-label">Recognized Revenue This Month</div><div class="kpi-value">{{ $jod($finance['recognized_revenue_this_period_minor']) }} د.أ</div><div class="muted">Accounting revenue, not MRR</div></div>
    <div class="card"><div class="kpi-label">Cash Collected This Month</div><div class="kpi-value">{{ $jod($saasReport['cash_collected_minor']) }} د.أ</div><div class="muted">Customer cash receipts, not MRR</div></div>
    <div class="card"><div class="kpi-label">Cash Available</div><div class="kpi-value">{{ $jod($finance['cash_available_minor']) }} د.أ</div><div class="muted">Company account balances</div></div>
    <div class="card"><div class="kpi-label">Accounts Receivable</div><div class="kpi-value">{{ $jod($finance['accounts_receivable_minor']) }} د.أ</div></div>
    <div class="card"><div class="kpi-label">Operating Expenses</div><div class="kpi-value">{{ $jod($finance['operating_expenses_this_period_minor']) }} د.أ</div></div>
    <div class="card"><div class="kpi-label">Management Net Income</div><div class="kpi-value">{{ $jod($finance['management_net_income_this_period_minor']) }} د.أ</div></div>
    <div class="card"><div class="kpi-label">Upcoming Renewals</div><div class="kpi-value">{{ $saasReport['renewal_metrics']['due_30_count'] }}</div><div class="muted">Operational forecast, not recognized revenue</div></div>
</div>

<div class="grid grid-4" style="margin-top:14px">
    <div class="card"><div class="kpi-label">New MRR</div><div class="kpi-value">{{ $jod($saasReport['movements']['new_mrr_minor']) }}</div></div>
    <div class="card"><div class="kpi-label">Expansion MRR</div><div class="kpi-value">{{ $jod($saasReport['movements']['expansion_mrr_minor']) }}</div></div>
    <div class="card"><div class="kpi-label">Contraction MRR</div><div class="kpi-value">{{ $jod($saasReport['movements']['contraction_mrr_minor']) }}</div></div>
    <div class="card"><div class="kpi-label">Reactivation MRR</div><div class="kpi-value">{{ $jod($saasReport['movements']['reactivation_mrr_minor']) }}</div></div>
    <div class="card"><div class="kpi-label">Logo Churn Rate</div><div class="kpi-value">{{ $bps($saasReport['logo_churn_rate_bps']) }}</div></div>
    <div class="card"><div class="kpi-label">NRR</div><div class="kpi-value">{{ $bps($saasReport['nrr_bps']) }}</div></div>
    <div class="card"><div class="kpi-label">GRR</div><div class="kpi-value">{{ $bps($saasReport['grr_bps']) }}</div></div>
    <div class="card"><div class="kpi-label">Deferred Revenue</div><div class="kpi-value">{{ $jod($finance['deferred_revenue_minor']) }}</div></div>
</div>

<div class="grid grid-2" style="margin-top:18px">
    <div class="card">
        <h3 style="margin-top:0">MRR Movement Waterfall</h3>
        <table class="table">
            <tbody>
            <tr><th>Starting MRR</th><td>{{ $jod($saasReport['starting_mrr_minor']) }}</td></tr>
            <tr><th>New MRR</th><td>{{ $jod($saasReport['movements']['new_mrr_minor']) }}</td></tr>
            <tr><th>Expansion MRR</th><td>{{ $jod($saasReport['movements']['expansion_mrr_minor']) }}</td></tr>
            <tr><th>Reactivation MRR</th><td>{{ $jod($saasReport['movements']['reactivation_mrr_minor']) }}</td></tr>
            <tr><th>Contraction MRR</th><td>-{{ $jod($saasReport['movements']['contraction_mrr_minor']) }}</td></tr>
            <tr><th>Churn MRR</th><td>-{{ $jod($saasReport['movements']['churn_mrr_minor']) }}</td></tr>
            <tr><th>Ending MRR</th><td>{{ $jod($saasReport['ending_mrr_minor']) }}</td></tr>
            </tbody>
        </table>
    </div>
    <div class="card">
        <h3 style="margin-top:0">MRR by Plan</h3>
        <table class="table">
            <thead><tr><th>Plan</th><th>MRR</th><th>ARR</th></tr></thead>
            <tbody>
            @foreach($saasReport['plan_metrics'] as $row)
                <tr><td>{{ $row['plan_name'] }}</td><td>{{ $jod($row['mrr_minor']) }}</td><td>{{ $jod($row['arr_minor']) }}</td></tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="grid grid-2" style="margin-top:18px">
    <div class="card">
        <h3 style="margin-top:0">Recognized Revenue vs Cash Collected</h3>
        <table class="table">
            <tbody>
            <tr><th>Recognized Revenue</th><td>{{ $jod($finance['recognized_revenue_this_period_minor']) }}</td></tr>
            <tr><th>Cash Collected</th><td>{{ $jod($saasReport['cash_collected_minor']) }}</td></tr>
            <tr><th>Operating Expenses</th><td>{{ $jod($finance['operating_expenses_this_period_minor']) }}</td></tr>
            <tr><th>Overdue Receivables</th><td>{{ $jod($finance['overdue_receivables_minor']) }}</td></tr>
            </tbody>
        </table>
    </div>
    <div class="card">
        <h3 style="margin-top:0">Reconciliation</h3>
        <p>SaaS metrics: <strong>{{ $saasReconciliationResult['ok'] ? 'OK' : 'Needs review' }}</strong></p>
        <p>Financial reporting: <strong>{{ $financialReconciliationResult['ok'] ? 'OK' : 'Needs review' }}</strong></p>
        <div class="muted">Discrepancies are exposed here; this dashboard does not create synthetic movements to force totals to balance.</div>
    </div>
</div>
@endsection
