@extends('layouts.app')

@section('content')
<div class="p3-wrap">
    {{-- Page Header --}}
    <header class="p3-header">
        <div class="p3-header-main">
            <div class="p3-eyebrow">{{ __('notify.navigation.finance') }} / {{ __('notify.billing.title') }}</div>
            <h1 class="p3-title">{{ __('notify.billing.title') }}</h1>
            <p class="p3-subtitle">{{ __('notify.billing.subtitle') }}</p>
        </div>
        <div class="p3-header-actions">
            <form method="POST" action="{{ route('subscription-billing.generate-renewals') }}">
                @csrf
                <input type="hidden" name="dry_run" value="1">
                <button class="p3-btn p3-btn-soft" type="submit">{{ __('notify.billing.dry_run_renewals') }}</button>
            </form>
            <form method="POST" action="{{ route('subscription-billing.generate-renewals') }}">
                @csrf
                <button class="p3-btn p3-btn-primary" type="submit">{{ __('notify.billing.generate_renewals') }}</button>
            </form>
        </div>
    </header>

    {{-- Operational KPIs --}}
    <section class="p3-kpis">
        <div class="p3-kpi-card">
            <span class="p3-kpi-label">{{ __('notify.billing.due_today') }}</span>
            <span class="p3-kpi-value is-danger">{{ $snapshot['due_today']->count() }}</span>
            <span class="p3-kpi-meta">{{ __('notify.billing.due_today_meta') }}</span>
        </div>
        <div class="p3-kpi-card">
            <span class="p3-kpi-label">{{ __('notify.billing.next_7_days') }}</span>
            <span class="p3-kpi-value is-warning">{{ $snapshot['due_soon_7']->count() }}</span>
            <span class="p3-kpi-meta">{{ __('notify.billing.next_7_days_meta') }}</span>
        </div>
        <div class="p3-kpi-card">
            <span class="p3-kpi-label">{{ __('notify.billing.next_30_days') }}</span>
            <span class="p3-kpi-value is-primary">{{ $snapshot['due_soon_30']->count() }}</span>
            <span class="p3-kpi-meta">{{ __('notify.billing.next_30_days_meta') }}</span>
        </div>
        <div class="p3-kpi-card">
            <span class="p3-kpi-label">{{ __('notify.billing.pending_cancellations') }}</span>
            <span class="p3-kpi-value">{{ $snapshot['pending_cancellation']->count() }}</span>
            <span class="p3-kpi-meta">{{ __('notify.billing.pending_cancellations_meta') }}</span>
        </div>
        <div class="p3-kpi-card">
            <span class="p3-kpi-label">{{ __('notify.billing.billing_review') }}</span>
            <span class="p3-kpi-value {{ $snapshot['review_items']->count() > 0 ? 'is-danger' : 'is-success' }}">{{ $snapshot['review_items']->count() }}</span>
            <span class="p3-kpi-meta">{{ __('notify.billing.billing_review_meta') }}</span>
        </div>
    </section>

    {{-- 1. Due Today & 2. Next 7 Days --}}
    <div class="p3-grid-2">
        {{-- Due Today --}}
        <x-notify.collapsible-section id="sec-due-today" :title="__('notify.billing.due_today')" :subtitle="__('notify.billing.due_today_meta')" :badge="$snapshot['due_today']->count()" :badgeVariant="$snapshot['due_today']->count() > 0 ? 'danger' : 'neutral'" :open="true">
            <div class="p3-list">
                @forelse($snapshot['due_today'] as $subscription)
                    <div class="p3-list-item">
                        <div class="p3-item-top">
                            <div>
                                <span class="p3-badge p3-badge-danger" style="margin-inline-end:6px">{{ __('notify.billing.due_today') }}</span>
                                <strong class="p3-item-title">{{ $subscription->client?->business_name }} · #{{ $subscription->id }}</strong>
                            </div>
                            <a class="p3-btn p3-btn-ghost p3-btn-sm" href="{{ route('clients.show', $subscription->client_id) }}">{{ __('notify.client_workspace.view_client') }}</a>
                        </div>
                        <div class="p3-item-meta">
                            {{ $subscription->plan_name_snapshot }} · {{ __('notify.billing.renewal_date') }}: {{ optional($subscription->next_billing_date)->format('Y-m-d') }}
                        </div>
                    </div>
                @empty
                    <div class="p3-empty">{{ __('notify.billing.no_due_today') }}</div>
                @endforelse
            </div>
        </x-notify.collapsible-section>

        {{-- Next 7 Days --}}
        <x-notify.collapsible-section id="sec-due-7" :title="__('notify.billing.next_7_days')" :subtitle="__('notify.billing.next_7_days_meta')" :badge="$snapshot['due_soon_7']->count()" :open="true">
            <div class="p3-list">
                @forelse($snapshot['due_soon_7'] as $subscription)
                    <div class="p3-list-item">
                        <div class="p3-item-top">
                            <div>
                                <span class="p3-badge p3-badge-warning" style="margin-inline-end:6px">{{ __('notify.billing.next_7_days') }}</span>
                                <strong class="p3-item-title">{{ $subscription->client?->business_name }} · #{{ $subscription->id }}</strong>
                            </div>
                            <a class="p3-btn p3-btn-ghost p3-btn-sm" href="{{ route('clients.show', $subscription->client_id) }}">{{ __('notify.client_workspace.view_client') }}</a>
                        </div>
                        <div class="p3-item-meta">
                            {{ $subscription->plan_name_snapshot }} · {{ __('notify.billing.renewal_date') }}: {{ optional($subscription->next_billing_date)->format('Y-m-d') }}
                        </div>
                    </div>
                @empty
                    <div class="p3-empty">{{ __('notify.billing.no_due_7') }}</div>
                @endforelse
            </div>
        </x-notify.collapsible-section>
    </div>

    {{-- 3. Next 30 Days & 4. Pending Cancellations --}}
    <div class="p3-grid-2">
        {{-- Next 30 Days --}}
        <x-notify.collapsible-section id="sec-due-30" :title="__('notify.billing.next_30_days')" :subtitle="__('notify.billing.next_30_days_meta')" :badge="$snapshot['due_soon_30']->count()" :open="false">
            <div class="p3-list">
                @forelse($snapshot['due_soon_30'] as $subscription)
                    <div class="p3-list-item">
                        <div class="p3-item-top">
                            <div>
                                <span class="p3-badge p3-badge-primary" style="margin-inline-end:6px">{{ __('notify.billing.next_30_days') }}</span>
                                <strong class="p3-item-title">{{ $subscription->client?->business_name }} · #{{ $subscription->id }}</strong>
                            </div>
                            <a class="p3-btn p3-btn-ghost p3-btn-sm" href="{{ route('clients.show', $subscription->client_id) }}">{{ __('notify.client_workspace.view_client') }}</a>
                        </div>
                        <div class="p3-item-meta">
                            {{ $subscription->plan_name_snapshot }} · {{ __('notify.billing.renewal_date') }}: {{ optional($subscription->next_billing_date)->format('Y-m-d') }}
                        </div>
                    </div>
                @empty
                    <div class="p3-empty">{{ __('notify.billing.no_due_30') }}</div>
                @endforelse
            </div>
        </x-notify.collapsible-section>

        {{-- Pending Cancellations --}}
        <x-notify.collapsible-section id="sec-pending-cancels" :title="__('notify.billing.pending_cancellations')" :subtitle="__('notify.billing.pending_cancellations_meta')" :badge="$snapshot['pending_cancellation']->count()" :open="false">
            <div class="p3-list">
                @forelse($snapshot['pending_cancellation'] as $subscription)
                    <div class="p3-list-item">
                        <div class="p3-item-top">
                            <div>
                                <span class="p3-badge p3-badge-danger" style="margin-inline-end:6px">{{ __('notify.billing.pending_cancellations') }}</span>
                                <strong class="p3-item-title">{{ $subscription->client?->business_name }} · #{{ $subscription->id }}</strong>
                            </div>
                            <a class="p3-btn p3-btn-ghost p3-btn-sm" href="{{ route('clients.show', $subscription->client_id) }}">{{ __('notify.client_workspace.view_client') }}</a>
                        </div>
                        <div class="p3-item-meta">
                            {{ $subscription->plan_name_snapshot }} · {{ optional($subscription->current_period_end)->format('Y-m-d') }}
                            @if($subscription->cancellation_reason)
                                · {{ $subscription->cancellation_reason }}
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="p3-empty">{{ __('notify.billing.no_pending_cancellations') }}</div>
                @endforelse
            </div>
        </x-notify.collapsible-section>
    </div>

    {{-- 5. Renewal Operations & 6. Billing Review --}}
    <div class="p3-grid-2">
        {{-- Renewal Operations (Generated Invoices) --}}
        <x-notify.collapsible-section id="sec-renewal-ops" :title="__('notify.billing.renewal_operations')" :subtitle="__('notify.billing.renewal_operations_meta')" :badge="$snapshot['generated_periods']->count()" :open="false">
            <div class="p3-list">
                @forelse($snapshot['generated_periods'] as $period)
                    <div class="p3-list-item">
                        <div class="p3-item-top">
                            <div>
                                <span class="p3-badge p3-badge-success" style="margin-inline-end:6px">{{ __('notify.statuses.issued') }}</span>
                                <strong class="p3-item-title">{{ $period->subscription?->client?->business_name }}</strong>
                            </div>
                            <strong class="ltr" style="direction:ltr;font-size:13px;color:#0055CC">{{ $period->invoice?->invoice_number }}</strong>
                        </div>
                        <div class="p3-item-meta">
                            {{ $period->period_start->format('Y-m-d') }} → {{ $period->period_end->format('Y-m-d') }}
                            @if($period->subscription?->client_id)
                                · <a class="p3-btn p3-btn-ghost p3-btn-sm" style="padding:2px 8px;font-size:11px" href="{{ route('clients.show', $period->subscription->client_id) }}">{{ __('notify.client_workspace.view_client') }}</a>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="p3-empty">{{ __('notify.billing.no_recent_renewals') }}</div>
                @endforelse
            </div>
        </x-notify.collapsible-section>

        {{-- Billing Review --}}
        <x-notify.collapsible-section id="sec-billing-review" :title="__('notify.billing.billing_review')" :subtitle="__('notify.billing.billing_review_meta')" :badge="$snapshot['review_items']->count()" :badgeVariant="$snapshot['review_items']->count() > 0 ? 'danger' : 'success'" :open="$snapshot['review_items']->count() > 0">
            <div style="display:flex;justify-content:flex-end;margin-bottom:12px">
                <form method="POST" action="{{ route('subscription-billing.backfill-periods') }}">
                    @csrf
                    <input type="hidden" name="dry_run" value="1">
                    <button class="p3-btn p3-btn-soft p3-btn-sm" type="submit">{{ __('notify.billing.dry_run_renewals') }}</button>
                </form>
            </div>
            <div class="p3-list">
                @forelse($snapshot['review_items'] as $item)
                    <div class="p3-list-item" style="border-right:3px solid #B42318">
                        <div class="p3-item-top">
                            <div>
                                <span class="p3-badge p3-badge-danger" style="margin-inline-end:6px">{{ __('notify.statuses.needs_review') }}</span>
                                <strong class="p3-item-title">{{ $item['client']?->business_name }} · #{{ $item['subscription']->id }}</strong>
                            </div>
                            <a class="p3-btn p3-btn-soft p3-btn-sm" href="{{ route('clients.show', $item['subscription']->client_id) }}">{{ __('notify.client_workspace.view_client') }}</a>
                        </div>
                        <div class="p3-item-meta" style="color:#B42318;font-weight:700">
                            {{ $item['reason'] }} · {{ $item['message'] }}
                        </div>
                        <div class="p3-item-meta">
                            {{ $item['resolution'] }}
                        </div>
                    </div>
                @empty
                    <div class="p3-empty">{{ __('notify.billing.billing_review_meta') }}</div>
                @endforelse
            </div>
        </x-notify.collapsible-section>
    </div>
</div>
@endsection
