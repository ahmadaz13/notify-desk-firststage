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

    @php
        $col = fn (string $key) => __('notify.finance_hub.collections.columns.'.$key);
        $actionsColumn = ['label' => $col('actions'), 'hidden' => true, 'class' => 'is-actions'];
        $amountColumn = ['label' => $col('amount'), 'class' => 'is-num'];
        $columns = [
            'pending' => [$col('client'), $amountColumn, $col('details'), $col('status'), $actionsColumn],
            'due' => [$col('client'), $amountColumn, $col('due'), $col('details'), $actionsColumn],
            'credits' => [$col('client'), $amountColumn, $col('details'), $actionsColumn],
            'payments' => [$col('client'), $amountColumn, $col('details'), $col('status'), $actionsColumn],
        ];
        $dueRows = $tab === 'partial' ? $partialItems : $dueItems;
    @endphp

    <section class="notify-fin-list" data-collections-panel="{{ $tab }}">
        @if($tab === 'pending')
            <x-notify.list :columns="$columns['pending']" :label="__('notify.finance_hub.collections.tabs.pending')" :empty="$pendingReceipts->isEmpty()">
                @foreach($pendingReceipts as $receipt)
                    <tr data-pending-receipt="{{ $receipt->id }}">
                        <td class="notify-list__primary">
                            <a class="notify-list__title" href="{{ route('clients.show', $receipt->client_id) }}">{{ $receipt->client?->business_name }}</a>
                            @if($receipt->note)<span class="notify-list__sub">{{ $receipt->note }}</span>@endif
                        </td>
                        <td class="notify-list__end notify-list__amount"><x-notify.money :minor="$receipt->amount_minor" /></td>
                        <td>
                            {{ $receipt->methodLabel() }} · <span dir="ltr">{{ $receipt->received_at?->format('Y-m-d H:i') }}</span>
                            <span class="notify-list__sub">{{ __('notify.payment_receipts.submitted_by') }}: {{ $receipt->submitter?->name }}@if($receipt->reference) · <span dir="ltr">#{{ $receipt->reference }}</span>@endif</span>
                        </td>
                        <td><span class="notify-status notify-status--warning">{{ __('notify.finance_hub.collections.waiting', ['age' => $receipt->created_at?->diffForHumans()]) }}</span></td>
                        <td class="notify-list__actions">
                            @if($canApproveReceipts)
                                <span class="notify-list__actions-inner">
                                    <form method="POST" action="{{ route('payment-receipts.approve', $receipt) }}">
                                        @csrf
                                        <input type="hidden" name="_idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                        <button type="submit" class="notify-button notify-button--primary notify-button--sm">{{ __('notify.payment_receipts.action_approve') }}</button>
                                    </form>
                                    <x-notify.menu>
                                        <x-slot:danger>
                                            <button type="button" class="notify-menu__item notify-menu__item--danger" role="menuitem" data-open-sheet="reject-receipt-{{ $receipt->id }}" aria-haspopup="dialog">
                                                <x-notify.icon name="x" :size="18" /><span>{{ __('notify.payment_receipts.action_reject') }}</span>
                                            </button>
                                        </x-slot:danger>
                                    </x-notify.menu>
                                </span>
                            @endif
                        </td>
                    </tr>
                @endforeach
                <x-slot:emptyState>
                    <x-notify.empty-state :compact="true" icon="check-circle" :title="__('notify.payment_receipts.pending_empty')" />
                </x-slot:emptyState>
            </x-notify.list>

            @if($canApproveReceipts)
                @foreach($pendingReceipts as $receipt)
                    @include('finance.partials.reject-receipt-sheet', ['receipt' => $receipt])
                @endforeach
            @endif

        @elseif($tab === 'due' || $tab === 'partial')
            <x-notify.list :columns="$columns['due']" :label="__('notify.finance_hub.collections.tabs.'.$tab)" :empty="$dueRows->isEmpty()">
                @foreach($dueRows as $item)
                    <tr @class(['is-overdue' => $item['projection']['is_overdue']]) data-due-invoice="{{ $item['invoice']->id }}">
                        <td class="notify-list__primary">
                            <a class="notify-list__title" href="{{ route('clients.show', $item['invoice']->client_id) }}">{{ $item['invoice']->client?->business_name }}</a>
                        </td>
                        <td class="notify-list__end notify-list__amount"><x-notify.money :minor="$item['projection']['outstanding_minor']" /></td>
                        <td>
                            @if($item['projection']['is_overdue'])
                                <span class="notify-status notify-status--danger"><x-notify.icon name="alert-circle" :size="14" />{{ __('notify.finance_hub.collections.overdue_days', ['days' => (int) $item['invoice']->due_date?->diffInDays(today())]) }}</span>
                            @elseif($item['projection']['settlement_status'] === 'partially_paid')
                                <span class="notify-status notify-status--warning">{{ __('notify.statuses.partially_paid') }}</span>
                            @else
                                <span class="notify-status notify-status--neutral">{{ __('notify.finance_hub.collections.not_due_yet') }}</span>
                            @endif
                            <span class="notify-list__sub">{{ __('notify.finance_hub.collections.due_on') }} <span dir="ltr">{{ $item['invoice']->due_date?->format('Y-m-d') }}</span></span>
                        </td>
                        <td data-label="{{ $col('details') }}">
                            <span dir="ltr">{{ $item['invoice']->invoice_number }}</span>
                            @if($item['projection']['settled_minor'] > 0)
                                <span class="notify-list__sub">{{ __('notify.finance_hub.collections.paid_of') }} <x-notify.money :minor="$item['projection']['settled_minor']" /> / <x-notify.money :minor="$item['invoice']->total_minor" /></span>
                            @endif
                        </td>
                        <td class="notify-list__actions">
                            <span class="notify-list__actions-inner">
                                @if($canRecordPayment)
                                    <a class="notify-button notify-button--primary notify-button--sm" href="{{ $recordPaymentUrl($item['invoice']->client_id) }}">{{ __('notify.client_workspace.action_record_payment') }}</a>
                                @endif
                                <x-notify.menu>
                                    <a class="notify-menu__item" role="menuitem" href="{{ route('clients.show', $item['invoice']->client_id) }}#collapsible-management">
                                        <x-notify.icon name="settings" :size="18" /><span>{{ __('notify.finance_hub.collections.corrections') }}</span>
                                    </a>
                                </x-notify.menu>
                            </span>
                        </td>
                    </tr>
                @endforeach
                <x-slot:emptyState>
                    <x-notify.empty-state :compact="true" icon="check-circle" :title="__('notify.finance_hub.collections.empty_due')" />
                </x-slot:emptyState>
            </x-notify.list>

        @elseif($tab === 'credits')
            <x-notify.list :columns="$columns['credits']" :label="__('notify.finance_hub.collections.tabs.credits')" :empty="$credits->isEmpty()">
                @foreach($credits as $item)
                    <tr data-customer-credit>
                        <td class="notify-list__primary">
                            <a class="notify-list__title" href="{{ route('clients.show', $item['source']->client_id) }}">{{ $item['source']->client?->business_name }}</a>
                        </td>
                        <td class="notify-list__end notify-list__amount"><x-notify.money :minor="$item['available_minor']" /></td>
                        <td>
                            <span class="notify-status notify-status--success">{{ $item['source_type'] === 'payment' ? __('notify.client_workspace.payment_credit') : __('notify.client_workspace.credit_note_balance') }}</span>
                            <span class="notify-list__sub" dir="ltr">{{ $item['source_type'] === 'payment' ? $item['source']->received_at?->format('Y-m-d') : $item['source']->credit_note_number.' · '.$item['source']->issue_date?->format('Y-m-d') }}</span>
                        </td>
                        <td class="notify-list__actions">
                            <a class="notify-button notify-button--ghost notify-button--sm" href="{{ route('clients.show', $item['source']->client_id) }}#collapsible-management">{{ __('notify.finance_hub.collections.corrections') }}</a>
                        </td>
                    </tr>
                @endforeach
                <x-slot:emptyState>
                    <x-notify.empty-state :compact="true" icon="wallet" :title="__('notify.finance_hub.collections.empty_credits')" />
                </x-slot:emptyState>
            </x-notify.list>

        @else
            <x-notify.list :columns="$columns['payments']" :label="__('notify.finance_hub.collections.tabs.payments')" :empty="! $payments || $payments->isEmpty()">
                @foreach($payments ?? [] as $payment)
                    <tr @class(['is-muted' => $paymentProjections[$payment->id]['is_reversed'] ?? false]) data-recent-payment>
                        <td class="notify-list__primary">
                            <a class="notify-list__title" href="{{ route('clients.show', $payment->client_id) }}">{{ $payment->client?->business_name }}</a>
                        </td>
                        <td class="notify-list__end notify-list__amount"><x-notify.money :minor="$paymentProjections[$payment->id]['total_minor'] ?? (int) $payment->amount_minor" /></td>
                        <td>
                            {{ \App\Support\PaymentMethods::label($payment->payment_method) }} · <span dir="ltr">{{ $payment->received_at?->format('Y-m-d H:i') }}</span>
                            @if($payment->reference)<span class="notify-list__sub" dir="ltr">#{{ $payment->reference }}</span>@endif
                        </td>
                        <td>
                            @if($paymentProjections[$payment->id]['is_reversed'] ?? false)
                                <span class="notify-status notify-status--danger">{{ __('notify.finance_hub.collections.reversed') }}</span>
                            @elseif(($paymentProjections[$payment->id]['unallocated_minor'] ?? 0) > 0)
                                <span class="notify-status notify-status--success">{{ __('notify.finance_hub.collections.has_credit') }}</span>
                            @endif
                        </td>
                        <td class="notify-list__actions">
                            <a class="notify-button notify-button--ghost notify-button--sm" href="{{ route('clients.show', $payment->client_id) }}#collapsible-management">{{ __('notify.finance_hub.collections.corrections') }}</a>
                        </td>
                    </tr>
                @endforeach
                <x-slot:emptyState>
                    <x-notify.empty-state :compact="true" icon="receipt" :title="__('notify.finance_hub.collections.empty_payments')" />
                </x-slot:emptyState>
                @if($payments && $payments->hasPages())
                    <x-slot:footer>{{ $payments->links() }}</x-slot:footer>
                @endif
            </x-notify.list>
        @endif
    </section>
</div>
@endsection
