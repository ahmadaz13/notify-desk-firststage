@extends('layouts.app')

@section('content')
<div class="p3-wrap">
    {{-- Page Header --}}
    <header class="p3-header">
        <div class="p3-header-main">
            <div class="p3-eyebrow">{{ __('notify.navigation.finance') }} / {{ __('notify.collections.title') }}</div>
            <h1 class="p3-title">{{ __('notify.collections.title') }}</h1>
            <p class="p3-subtitle">{{ __('notify.collections.subtitle') }}</p>
        </div>
    </header>

    {{-- Compact Filter Bar --}}
    <div class="p3-filter-card">
        <form method="GET" action="{{ route('collections.index') }}" class="p3-filter-grid">
            <div class="p3-field">
                <label for="f_client_id">{{ __('notify.collections.filter_client') }}</label>
                <select id="f_client_id" name="client_id" class="p3-select">
                    <option value="">{{ __('notify.collections.filter_all_clients') }}</option>
                    @foreach($clients as $client)
                        <option value="{{ $client->id }}" @selected(($filters['client_id'] ?? null) == $client->id)>{{ $client->business_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="p3-field">
                <label for="f_due_state">{{ __('notify.collections.filter_due_state') }}</label>
                <select id="f_due_state" name="due_state" class="p3-select">
                    <option value="all" @selected(empty($filters['due_state']))>{{ __('notify.common.all') }}</option>
                    <option value="overdue" @selected(($filters['due_state'] ?? null) === 'overdue')>{{ __('notify.statuses.overdue') }}</option>
                    <option value="not_due" @selected(($filters['due_state'] ?? null) === 'not_due')>{{ __('notify.collections.not_due_yet') }}</option>
                </select>
            </div>
            <div class="p3-field">
                <label for="f_settlement_state">{{ __('notify.collections.filter_settlement_state') }}</label>
                <select id="f_settlement_state" name="settlement_state" class="p3-select">
                    <option value="">{{ __('notify.common.all') }}</option>
                    <option value="unpaid" @selected(($filters['settlement_state'] ?? null) === 'unpaid')>{{ __('notify.statuses.unpaid') }}</option>
                    <option value="partially_paid" @selected(($filters['settlement_state'] ?? null) === 'partially_paid')>{{ __('notify.statuses.partially_paid') }}</option>
                    <option value="paid" @selected(($filters['settlement_state'] ?? null) === 'paid')>{{ __('notify.statuses.paid') }}</option>
                </select>
            </div>
            <div class="p3-field">
                <label for="f_date_from">{{ __('notify.collections.filter_date_from') }}</label>
                <input id="f_date_from" type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="p3-input">
            </div>
            <div class="p3-field">
                <label for="f_date_to">{{ __('notify.collections.filter_date_to') }}</label>
                <input id="f_date_to" type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="p3-input">
            </div>
            <div class="p3-field" style="justify-content:flex-end">
                <button class="p3-btn p3-btn-primary" type="submit" style="width:100%">{{ __('notify.collections.apply_filter') }}</button>
            </div>
        </form>
    </div>

    {{-- Collections KPIs --}}
    <section class="p3-kpis">
        <div class="p3-kpi-card">
            <span class="p3-kpi-label">{{ __('notify.collections.all_outstanding') }}</span>
            <span class="p3-kpi-value is-primary">{{ $outstandingInvoices->count() }}</span>
            <span class="p3-kpi-meta">{{ __('notify.collections.total_invoices_meta') }}</span>
        </div>
        <div class="p3-kpi-card">
            <span class="p3-kpi-label">{{ __('notify.statuses.overdue') }}</span>
            <span class="p3-kpi-value is-danger">{{ $overdueInvoices->count() }}</span>
            <span class="p3-kpi-meta">{{ __('notify.collections.overdue_meta') }}</span>
        </div>
        <div class="p3-kpi-card">
            <span class="p3-kpi-label">{{ __('notify.statuses.partially_paid') }}</span>
            <span class="p3-kpi-value is-warning">{{ $partiallyPaidInvoices->count() }}</span>
            <span class="p3-kpi-meta">{{ __('notify.collections.partially_paid_meta') }}</span>
        </div>
        <div class="p3-kpi-card">
            <span class="p3-kpi-label">{{ __('notify.collections.customer_credits') }}</span>
            <span class="p3-kpi-value is-success">{{ $availableCustomerCredits->count() }}</span>
            <span class="p3-kpi-meta">{{ __('notify.collections.customer_credits_meta') }}</span>
        </div>
    </section>

    {{-- Invoices & Credits Grid --}}
    <div class="p3-grid-2">
        {{-- Outstanding Invoices --}}
        <x-notify.collapsible-section id="sec-outstanding" :title="__('notify.collections.outstanding_invoices')" :subtitle="__('notify.collections.outstanding_invoices_meta')" :badge="$outstandingInvoices->count()" :open="true">
            <div class="p3-list">
                @forelse($outstandingInvoices as $item)
                    @php
                        $invoice = $item['invoice'];
                        $projection = $item['projection'];
                    @endphp
                    <div class="p3-list-item">
                        <div class="p3-item-top">
                            <div>
                                <span class="p3-badge {{ $projection['is_overdue'] ? 'p3-badge-danger' : 'p3-badge-warning' }}" style="margin-inline-end:6px">
                                    {{ $projection['settlement_status'] }}
                                </span>
                                <strong class="ltr p3-item-title" style="direction:ltr">{{ $invoice->invoice_number }}</strong>
                            </div>
                            <span class="p3-item-amount">{{ $projection['outstanding'] }} {{ __('notify.common.currency_jod') }} {{ __('notify.collections.remaining') }}</span>
                        </div>
                        <div class="p3-item-meta">
                            <strong>{{ $invoice->client->business_name }}</strong> · {{ __('notify.collections.due_date') }}: {{ optional($invoice->due_date)->format('Y-m-d') }}
                        </div>
                        <div class="p3-item-meta" style="font-size:11px;color:#64748B">
                            {{ __('notify.collections.total') }}: {{ \App\Support\Money::fromMinorUnits($invoice->total_minor)->format() }} {{ __('notify.common.currency_jod') }}
                            · {{ __('notify.collections.payments') }}: {{ $projection['allocated'] }} {{ __('notify.common.currency_jod') }}
                            · {{ __('notify.collections.credit_balance') }}: {{ $projection['credit_applied'] }} {{ __('notify.common.currency_jod') }}
                        </div>
                        <div class="p3-item-actions">
                            <a class="p3-btn p3-btn-ghost p3-btn-sm" href="{{ route('clients.show', $invoice->client_id) }}">{{ __('notify.collections.open_client_billing') }}</a>
                        </div>
                    </div>
                @empty
                    <div class="p3-empty">{{ __('notify.collections.no_outstanding_filtered') }}</div>
                @endforelse
            </div>
        </x-notify.collapsible-section>

        {{-- Side Column: Overdue, Partially Paid & Credits --}}
        <div style="display:flex;flex-direction:column;gap:16px">
            {{-- Overdue Invoices --}}
            <x-notify.collapsible-section id="sec-overdue" :title="__('notify.collections.overdue_invoices')" :subtitle="__('notify.collections.overdue_invoices_meta')" :badge="$overdueInvoices->count()" :badgeVariant="$overdueInvoices->count() > 0 ? 'danger' : 'neutral'" :open="$overdueInvoices->count() > 0">
                <div class="p3-list">
                    @forelse($overdueInvoices as $item)
                        @php
                            $invoice = $item['invoice'];
                            $projection = $item['projection'];
                        @endphp
                        <div class="p3-list-item" style="border-right:3px solid #B42318">
                            <div class="p3-item-top">
                                <div>
                                    <span class="p3-badge p3-badge-danger" style="margin-inline-end:6px">{{ $projection['age_bucket'] }}</span>
                                    <strong class="ltr p3-item-title" style="direction:ltr">{{ $invoice->invoice_number }}</strong>
                                </div>
                                <span class="p3-item-amount" style="color:#B42318">{{ $projection['outstanding'] }} {{ __('notify.common.currency_jod') }}</span>
                            </div>
                            <div class="p3-item-meta">
                                {{ $invoice->client->business_name }}
                            </div>
                            <div class="p3-item-actions">
                                <a class="p3-btn p3-btn-ghost p3-btn-sm" href="{{ route('clients.show', $invoice->client_id) }}">{{ __('notify.client_workspace.view_client') }}</a>
                            </div>
                        </div>
                    @empty
                        <div class="p3-empty">{{ __('notify.billing.no_due_today') }}</div>
                    @endforelse
                </div>
            </x-notify.collapsible-section>

            {{-- Partially Paid Invoices --}}
            <x-notify.collapsible-section id="sec-partially-paid" :title="__('notify.collections.partially_paid')" :subtitle="__('notify.collections.partially_paid_meta')" :badge="$partiallyPaidInvoices->count()" :open="false">
                <div class="p3-list">
                    @forelse($partiallyPaidInvoices as $item)
                        @php
                            $invoice = $item['invoice'];
                            $projection = $item['projection'];
                        @endphp
                        <div class="p3-list-item">
                            <div class="p3-item-top">
                                <div>
                                    <span class="p3-badge p3-badge-warning" style="margin-inline-end:6px">{{ __('notify.statuses.partially_paid') }}</span>
                                    <strong class="ltr p3-item-title" style="direction:ltr">{{ $invoice->invoice_number }}</strong>
                                </div>
                                <span class="p3-item-amount" style="color:#D97706">{{ $projection['outstanding'] }} {{ __('notify.common.currency_jod') }} {{ __('notify.collections.remaining') }}</span>
                            </div>
                            <div class="p3-item-meta">
                                {{ $invoice->client->business_name }} · {{ __('notify.collections.payments') }}: {{ $projection['allocated'] }} {{ __('notify.common.currency_jod') }} · {{ __('notify.collections.credit_balance') }}: {{ $projection['credit_applied'] }} {{ __('notify.common.currency_jod') }}
                            </div>
                            <div class="p3-item-actions">
                                <a class="p3-btn p3-btn-ghost p3-btn-sm" href="{{ route('clients.show', $invoice->client_id) }}">{{ __('notify.client_workspace.view_client') }}</a>
                            </div>
                        </div>
                    @empty
                        <div class="p3-empty">{{ __('notify.collections.no_outstanding_filtered') }}</div>
                    @endforelse
                </div>
            </x-notify.collapsible-section>

            {{-- Customer Credits --}}
            <x-notify.collapsible-section id="sec-customer-credits" :title="__('notify.collections.customer_credits')" :subtitle="__('notify.collections.customer_credits_meta')" :badge="$availableCustomerCredits->count()" :open="false">
                <div class="p3-list">
                    @forelse($availableCustomerCredits as $item)
                        @php
                            $source = $item['source'];
                            $projection = $item['projection'];
                        @endphp
                        <div class="p3-list-item" style="border-right:3px solid #16A34A">
                            <div class="p3-item-top">
                                <div>
                                    <span class="p3-badge p3-badge-success" style="margin-inline-end:6px">
                                        {{ $item['source_type'] === 'payment' ? __('notify.client_workspace.payment_credit') : __('notify.client_workspace.credit_note_balance') }}
                                    </span>
                                    <strong class="p3-item-title">{{ $source->client->business_name }}</strong>
                                </div>
                                <span class="p3-item-amount" style="color:#16A34A">{{ $item['available'] }} {{ __('notify.common.currency_jod') }}</span>
                            </div>
                            <div class="p3-item-meta">
                                @if($item['source_type'] === 'payment')
                                    {{ $source->received_at?->format('Y-m-d H:i') }} · {{ $source->payment_method }}
                                @else
                                    {{ $source->credit_note_number }} · {{ $source->issue_date?->format('Y-m-d') }}
                                @endif
                            </div>
                            <div class="p3-item-actions">
                                <a class="p3-btn p3-btn-ghost p3-btn-sm" href="{{ route('clients.show', $source->client_id) }}">{{ __('notify.collections.open_client_billing') }}</a>
                            </div>
                        </div>
                    @empty
                        <div class="p3-empty">{{ __('notify.collections.no_outstanding_filtered') }}</div>
                    @endforelse
                </div>
            </x-notify.collapsible-section>
        </div>
    </div>
</div>
@endsection
