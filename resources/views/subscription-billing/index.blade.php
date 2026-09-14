@extends('layouts.app')

@section('content')
<div class="page-head">
    <div>
        <div class="eyebrow">Finance / Subscription Billing</div>
        <h1 class="page-title">إدارة تجديد الاشتراكات</h1>
        <div class="muted" style="margin-top:5px">قائمة تشغيلية لتجديد فواتير V2 ومراجعة الحالات غير الحتمية. ليست لوحة MRR/ARR أو Churn.</div>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <form method="POST" action="{{ route('subscription-billing.generate-renewals') }}">
            @csrf
            <input type="hidden" name="dry_run" value="1">
            <button class="btn btn-soft" type="submit">Dry run renewals</button>
        </form>
        <form method="POST" action="{{ route('subscription-billing.generate-renewals') }}">
            @csrf
            <button class="btn btn-primary" type="submit">Generate due renewals</button>
        </form>
    </div>
</div>

<div class="grid grid-4" style="margin-bottom:16px">
    <div class="card"><div class="kpi-label">Due today</div><div class="kpi-value">{{ $snapshot['due_today']->count() }}</div></div>
    <div class="card"><div class="kpi-label">Due in 7 days</div><div class="kpi-value">{{ $snapshot['due_soon_7']->count() }}</div></div>
    <div class="card"><div class="kpi-label">Due in 30 days</div><div class="kpi-value">{{ $snapshot['due_soon_30']->count() }}</div></div>
    <div class="card"><div class="kpi-label">Review items</div><div class="kpi-value">{{ $snapshot['review_items']->count() }}</div></div>
</div>

<div class="grid grid-2">
    <div class="card">
        <div class="section-head" style="margin-top:0">
            <h2>Renewals Due</h2>
            <span class="muted">{{ $snapshot['due_today']->count() }} today</span>
        </div>
        <div class="list">
            @forelse($snapshot['due_today'] as $subscription)
                <div class="list-row">
                    <span class="badge gold">due</span>
                    <div class="list-main">
                        <strong>{{ $subscription->client?->business_name }} · #{{ $subscription->id }}</strong>
                        <small>{{ $subscription->plan_name_snapshot }} · next {{ optional($subscription->next_billing_date)->format('Y-m-d') }}</small>
                    </div>
                    <a class="btn btn-ghost" href="{{ route('clients.show', $subscription->client_id) }}">Open</a>
                </div>
            @empty
                <div class="muted">No subscriptions due today.</div>
            @endforelse
        </div>
    </div>

    <div class="card">
        <div class="section-head" style="margin-top:0">
            <h2>Billing Review</h2>
            <form method="POST" action="{{ route('subscription-billing.backfill-periods') }}">
                @csrf
                <input type="hidden" name="dry_run" value="1">
                <button class="btn btn-ghost" type="submit">Backfill dry run</button>
            </form>
        </div>
        <div class="list">
            @forelse($snapshot['review_items'] as $item)
                <div class="list-row">
                    <span class="badge red">review</span>
                    <div class="list-main">
                        <strong>{{ $item['client']?->business_name }} · #{{ $item['subscription']->id }}</strong>
                        <small>{{ $item['reason'] }} · {{ $item['message'] }}</small>
                        <small>{{ $item['resolution'] }}</small>
                    </div>
                    <a class="btn btn-ghost" href="{{ route('clients.show', $item['subscription']->client_id) }}">Resolve</a>
                </div>
            @empty
                <div class="muted">No billing review items.</div>
            @endforelse
        </div>
    </div>
</div>

<div class="grid grid-2" style="margin-top:16px">
    <div class="card">
        <h2 style="margin-top:0">Pending Cancellations</h2>
        <div class="list">
            @forelse($snapshot['pending_cancellation'] as $subscription)
                <div class="list-row">
                    <span class="badge red">scheduled</span>
                    <div class="list-main">
                        <strong>{{ $subscription->client?->business_name }} · #{{ $subscription->id }}</strong>
                        <small>Ends {{ optional($subscription->current_period_end)->format('Y-m-d') }}</small>
                    </div>
                </div>
            @empty
                <div class="muted">No scheduled cancellations.</div>
            @endforelse
        </div>
    </div>

    <div class="card">
        <h2 style="margin-top:0">Generated Renewal Invoices</h2>
        <div class="list">
            @forelse($snapshot['generated_periods'] as $period)
                <div class="list-row">
                    <span class="badge green">invoiced</span>
                    <div class="list-main">
                        <strong>{{ $period->subscription?->client?->business_name }} · {{ $period->invoice?->invoice_number }}</strong>
                        <small>{{ $period->period_start->format('Y-m-d') }} → {{ $period->period_end->format('Y-m-d') }}</small>
                    </div>
                </div>
            @empty
                <div class="muted">No generated renewal invoices yet.</div>
            @endforelse
        </div>
    </div>
</div>
@endsection
