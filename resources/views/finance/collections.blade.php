@extends('layouts.app')

@php
    $tabQuery = fn (string $key) => array_filter(['tab' => $key, 'client_id' => $clientId]);
    $tabs = [
        'pending' => $counts['pending'],
        'due' => $counts['due'],
        'partial' => $counts['partial'],
        'credits' => null,
        'payments' => null,
    ];
    $recordPaymentUrl = fn (int $clientId) => route('clients.show', ['client' => $clientId, 'open' => 'record-payment']);
@endphp

@section('content')
<div class="notify-fin" data-finance-page="collections">
    @include('finance.partials.header', [
        'active' => 'collections',
        'title' => __('notify.finance_hub.sections.collections'),
        'subtitle' => __('notify.finance_hub.collections.subtitle'),
    ])

    @if($client)
        <p class="notify-fin-filter">
            {{ __('notify.finance_hub.collections.filtered_by', ['client' => $client->business_name]) }}
            <a href="{{ route('finance.collections', ['tab' => $tab]) }}">{{ __('notify.finance_hub.collections.clear_filter') }}</a>
        </p>
    @endif

    <nav class="notify-fin-tabs" aria-label="{{ __('notify.finance_hub.sections.collections') }}">
        @foreach($tabs as $key => $count)
            <a href="{{ route('finance.collections', $tabQuery($key)) }}" class="notify-fin-tabs__item @if($tab === $key) is-active @endif" data-collections-tab="{{ $key }}" @if($tab === $key) aria-current="page" @endif>
                {{ __('notify.finance_hub.collections.tabs.'.$key) }}
                @if($count !== null)<span class="notify-fin-tabs__count @if($key === 'pending' && $count > 0) is-warning @endif" dir="ltr">{{ $count }}</span>@endif
            </a>
        @endforeach
    </nav>

    <section class="notify-fin-list" data-collections-panel="{{ $tab }}">
        @if($tab === 'pending')
            @forelse($pendingReceipts as $receipt)
                <article class="notify-fin-item" data-pending-receipt="{{ $receipt->id }}">
                    <div class="notify-fin-item__main">
                        <div class="notify-fin-item__title">
                            <a href="{{ route('clients.show', $receipt->client_id) }}">{{ $receipt->client?->business_name }}</a>
                            <span class="notify-fin-chip notify-fin-chip--warning">{{ __('notify.finance_hub.collections.waiting', ['age' => $receipt->created_at?->diffForHumans()]) }}</span>
                        </div>
                        <x-notify.money class="notify-fin-item__amount" :minor="$receipt->amount_minor" />
                        <p class="notify-fin-item__meta">
                            {{ $receipt->methodLabel() }} · <span dir="ltr">{{ $receipt->received_at?->format('Y-m-d H:i') }}</span>
                            · {{ __('notify.payment_receipts.submitted_by') }}: {{ $receipt->submitter?->name }}
                            @if($receipt->reference) · <span dir="ltr">#{{ $receipt->reference }}</span>@endif
                        </p>
                        @if($receipt->note)<p class="notify-fin-item__meta">{{ $receipt->note }}</p>@endif
                    </div>
                    @if($canApproveReceipts)
                        <div class="notify-fin-item__actions">
                            <form method="POST" action="{{ route('payment-receipts.approve', $receipt) }}">
                                @csrf
                                <input type="hidden" name="_idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                <button type="submit" class="notify-button notify-button--primary">{{ __('notify.payment_receipts.action_approve') }}</button>
                            </form>
                            <details class="notify-fin-more">
                                <summary>{{ __('notify.payment_receipts.action_reject') }}</summary>
                                <form method="POST" action="{{ route('payment-receipts.reject', $receipt) }}" class="notify-fin-inline-form">
                                    @csrf
                                    <input type="hidden" name="_idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                    <label for="reject-{{ $receipt->id }}">{{ __('notify.payment_receipts.rejection_reason') }}</label>
                                    <input id="reject-{{ $receipt->id }}" name="rejection_reason" required maxlength="1000" placeholder="{{ __('notify.payment_receipts.rejection_reason_placeholder') }}">
                                    <button type="submit" class="notify-button notify-button--ghost">{{ __('notify.payment_receipts.action_reject') }}</button>
                                </form>
                            </details>
                        </div>
                    @endif
                </article>
            @empty
                <p class="notify-fin-empty">{{ __('notify.payment_receipts.pending_empty') }}</p>
            @endforelse

        @elseif($tab === 'due' || $tab === 'partial')
            @forelse($tab === 'due' ? $dueItems : $partialItems as $item)
                @php($invoice = $item['invoice'])
                @php($projection = $item['projection'])
                <article class="notify-fin-item @if($projection['is_overdue']) is-overdue @endif" data-due-invoice="{{ $invoice->id }}">
                    <div class="notify-fin-item__main">
                        <div class="notify-fin-item__title">
                            <a href="{{ route('clients.show', $invoice->client_id) }}">{{ $invoice->client?->business_name }}</a>
                            @if($projection['is_overdue'])
                                <span class="notify-fin-chip notify-fin-chip--danger">{{ __('notify.finance_hub.collections.overdue_days', ['days' => (int) $invoice->due_date?->diffInDays(today())]) }}</span>
                            @elseif($projection['settlement_status'] === 'partially_paid')
                                <span class="notify-fin-chip notify-fin-chip--warning">{{ __('notify.statuses.partially_paid') }}</span>
                            @else
                                <span class="notify-fin-chip">{{ __('notify.finance_hub.collections.not_due_yet') }}</span>
                            @endif
                        </div>
                        <x-notify.money class="notify-fin-item__amount" :minor="$projection['outstanding_minor']" />
                        <p class="notify-fin-item__meta">
                            {{ __('notify.finance_hub.collections.due_on') }} <span dir="ltr">{{ $invoice->due_date?->format('Y-m-d') }}</span>
                            · <span dir="ltr">{{ $invoice->invoice_number }}</span>
                            @if($projection['settled_minor'] > 0)
                                · {{ __('notify.finance_hub.collections.paid_of') }} <x-notify.money :minor="$projection['settled_minor']" /> / <x-notify.money :minor="$invoice->total_minor" />
                            @endif
                        </p>
                    </div>
                    <div class="notify-fin-item__actions">
                        @if($canRecordPayment)
                            <a class="notify-button notify-button--primary" href="{{ $recordPaymentUrl($invoice->client_id) }}">{{ __('notify.client_workspace.action_record_payment') }}</a>
                        @endif
                        <a class="notify-button notify-button--ghost" href="{{ route('clients.show', $invoice->client_id) }}#collapsible-management">{{ __('notify.finance_hub.collections.corrections') }}</a>
                    </div>
                </article>
            @empty
                <p class="notify-fin-empty">{{ __('notify.finance_hub.collections.empty_due') }}</p>
            @endforelse

        @elseif($tab === 'credits')
            @forelse($credits as $item)
                @php($source = $item['source'])
                <article class="notify-fin-item" data-customer-credit>
                    <div class="notify-fin-item__main">
                        <div class="notify-fin-item__title">
                            <a href="{{ route('clients.show', $source->client_id) }}">{{ $source->client?->business_name }}</a>
                            <span class="notify-fin-chip notify-fin-chip--success">{{ $item['source_type'] === 'payment' ? __('notify.client_workspace.payment_credit') : __('notify.client_workspace.credit_note_balance') }}</span>
                        </div>
                        <x-notify.money class="notify-fin-item__amount" :minor="$item['available_minor']" />
                        <p class="notify-fin-item__meta" dir="auto">
                            @if($item['source_type'] === 'payment')
                                <span dir="ltr">{{ $source->received_at?->format('Y-m-d') }}</span>
                            @else
                                <span dir="ltr">{{ $source->credit_note_number }} · {{ $source->issue_date?->format('Y-m-d') }}</span>
                            @endif
                        </p>
                    </div>
                    <div class="notify-fin-item__actions">
                        <a class="notify-button notify-button--ghost" href="{{ route('clients.show', $source->client_id) }}#collapsible-management">{{ __('notify.finance_hub.collections.corrections') }}</a>
                    </div>
                </article>
            @empty
                <p class="notify-fin-empty">{{ __('notify.finance_hub.collections.empty_credits') }}</p>
            @endforelse

        @else
            @forelse($payments as $payment)
                @php($projection = $paymentProjections[$payment->id] ?? null)
                <article class="notify-fin-item @if($projection['is_reversed'] ?? false) is-muted @endif" data-recent-payment>
                    <div class="notify-fin-item__main">
                        <div class="notify-fin-item__title">
                            <a href="{{ route('clients.show', $payment->client_id) }}">{{ $payment->client?->business_name }}</a>
                            @if($projection['is_reversed'] ?? false)
                                <span class="notify-fin-chip notify-fin-chip--danger">{{ __('notify.finance_hub.collections.reversed') }}</span>
                            @elseif(($projection['unallocated_minor'] ?? 0) > 0)
                                <span class="notify-fin-chip notify-fin-chip--success">{{ __('notify.finance_hub.collections.has_credit') }}</span>
                            @endif
                        </div>
                        <x-notify.money class="notify-fin-item__amount" :minor="$projection['total_minor'] ?? (int) $payment->amount_minor" />
                        <p class="notify-fin-item__meta">
                            {{ \App\Support\PaymentMethods::label($payment->payment_method) }} · <span dir="ltr">{{ $payment->received_at?->format('Y-m-d H:i') }}</span>
                            @if($payment->reference) · <span dir="ltr">#{{ $payment->reference }}</span>@endif
                        </p>
                    </div>
                    <div class="notify-fin-item__actions">
                        <a class="notify-button notify-button--ghost" href="{{ route('clients.show', $payment->client_id) }}#collapsible-management">{{ __('notify.finance_hub.collections.corrections') }}</a>
                    </div>
                </article>
            @empty
                <p class="notify-fin-empty">{{ __('notify.finance_hub.collections.empty_payments') }}</p>
            @endforelse
            @if($payments && $payments->hasPages())
                <div class="notify-fin-pagination">{{ $payments->links() }}</div>
            @endif
        @endif
    </section>
</div>
@endsection
