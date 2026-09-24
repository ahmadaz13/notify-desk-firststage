@extends('layouts.app')

{{-- Staff operational list (§9.7, D-07): per-client amounts only. No company totals, balances or reports. --}}
@section('content')
@php
    $col = fn (string $key) => __('notify.finance_hub.collections.columns.'.$key);
@endphp
<div class="notify-fin" data-finance-page="collections-due">
    <header class="notify-fin-header">
        <div class="notify-fin-header__text">
            <h1 class="notify-fin-title">{{ __('notify.finance_hub.collections_due.title') }}</h1>
            <p class="notify-fin-subtitle">{{ __('notify.finance_hub.collections_due.subtitle') }}</p>
        </div>
    </header>

    @if($myPendingReceipts->isNotEmpty())
        <section class="notify-fin-card" aria-labelledby="due-pending-title" data-my-pending-receipts>
            <div class="notify-fin-card__head">
                <h2 id="due-pending-title" class="notify-fin-card__title">{{ __('notify.finance_hub.collections_due.my_pending') }}</h2>
            </div>
            <x-notify.list :label="__('notify.finance_hub.collections_due.my_pending')"
                :columns="[$col('client'), ['label' => $col('amount'), 'class' => 'is-num'], $col('details'), $col('status')]">
                @foreach($myPendingReceipts as $receipt)
                    <tr>
                        <td class="notify-list__primary"><a class="notify-list__title" href="{{ route('clients.show', $receipt->client_id) }}">{{ $receipt->client?->business_name }}</a></td>
                        <td class="notify-list__end notify-list__amount"><x-notify.money :minor="$receipt->amount_minor" /></td>
                        <td>{{ $receipt->methodLabel() }} · <span dir="ltr">{{ $receipt->received_at?->format('Y-m-d H:i') }}</span></td>
                        <td><span class="notify-status notify-status--warning">{{ __('notify.finance_hub.collections_due.awaiting_confirmation') }}</span></td>
                    </tr>
                @endforeach
            </x-notify.list>
        </section>
    @endif

    <section aria-label="{{ __('notify.finance_hub.collections_due.title') }}" data-collections-due>
        <x-notify.list :label="__('notify.finance_hub.collections_due.title')" :empty="$rows->isEmpty()"
            :columns="[$col('client'), ['label' => $col('amount'), 'class' => 'is-num'], $col('due'), $col('status'), ['label' => $col('actions'), 'hidden' => true, 'class' => 'is-actions']]">
            @foreach($rows as $row)
                <tr @class(['is-overdue' => $row['is_overdue']]) data-due-client="{{ $row['client']?->id }}">
                    <td class="notify-list__primary"><a class="notify-list__title" href="{{ route('clients.show', $row['client']) }}">{{ $row['client']?->business_name }}</a></td>
                    <td class="notify-list__end notify-list__amount"><x-notify.money :minor="$row['due_minor']" /></td>
                    <td data-label="{{ __('notify.finance_hub.collections_due.next_due') }}"><span dir="ltr">{{ $row['next_due_date']?->format('Y-m-d') }}</span></td>
                    <td>
                        @if($row['is_overdue'])
                            <span class="notify-status notify-status--danger"><x-notify.icon name="alert-circle" :size="14" />{{ __('notify.statuses.overdue') }}</span>
                        @endif
                        @if($pendingByClient->has($row['client']?->id))
                            <span class="notify-status notify-status--warning">{{ __('notify.finance_hub.collections_due.awaiting_confirmation') }}</span>
                        @endif
                    </td>
                    <td class="notify-list__actions">
                        <a class="notify-button notify-button--primary notify-button--sm" href="{{ route('clients.show', ['client' => $row['client'], 'open' => 'record-payment']) }}">{{ __('notify.payment_receipts.action_payment_received') }}</a>
                    </td>
                </tr>
            @endforeach
            <x-slot:emptyState>
                <x-notify.empty-state :compact="true" icon="check-circle" :title="__('notify.finance_hub.collections_due.empty')" />
            </x-slot:emptyState>
        </x-notify.list>
    </section>
</div>
@endsection
