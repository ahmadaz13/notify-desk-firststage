@extends('layouts.app')

{{-- Staff operational list (§9.7, D-07): per-client amounts only. No company totals, balances or reports. --}}
@section('content')
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
            <div class="notify-fin-list">
                @foreach($myPendingReceipts as $receipt)
                    <article class="notify-fin-item">
                        <div class="notify-fin-item__main">
                            <div class="notify-fin-item__title">
                                <a href="{{ route('clients.show', $receipt->client_id) }}">{{ $receipt->client?->business_name }}</a>
                                <span class="notify-fin-chip notify-fin-chip--warning">{{ __('notify.finance_hub.collections_due.awaiting_confirmation') }}</span>
                            </div>
                            <x-notify.money class="notify-fin-item__amount" :minor="$receipt->amount_minor" />
                            <p class="notify-fin-item__meta">{{ $receipt->methodLabel() }} · <span dir="ltr">{{ $receipt->received_at?->format('Y-m-d H:i') }}</span></p>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    <section class="notify-fin-list" aria-label="{{ __('notify.finance_hub.collections_due.title') }}" data-collections-due>
        @forelse($rows as $row)
            <article class="notify-fin-item @if($row['is_overdue']) is-overdue @endif" data-due-client="{{ $row['client']?->id }}">
                <div class="notify-fin-item__main">
                    <div class="notify-fin-item__title">
                        <a href="{{ route('clients.show', $row['client']) }}">{{ $row['client']?->business_name }}</a>
                        @if($row['is_overdue'])
                            <span class="notify-fin-chip notify-fin-chip--danger">{{ __('notify.statuses.overdue') }}</span>
                        @endif
                        @if($pendingByClient->has($row['client']?->id))
                            <span class="notify-fin-chip notify-fin-chip--warning">{{ __('notify.finance_hub.collections_due.awaiting_confirmation') }}</span>
                        @endif
                    </div>
                    <x-notify.money class="notify-fin-item__amount" :minor="$row['due_minor']" />
                    <p class="notify-fin-item__meta">{{ __('notify.finance_hub.collections_due.next_due') }} <span dir="ltr">{{ $row['next_due_date']?->format('Y-m-d') }}</span></p>
                </div>
                <div class="notify-fin-item__actions">
                    <a class="notify-button notify-button--primary" href="{{ route('clients.show', ['client' => $row['client'], 'open' => 'record-payment']) }}">{{ __('notify.payment_receipts.action_payment_received') }}</a>
                </div>
            </article>
        @empty
            <p class="notify-fin-empty">{{ __('notify.finance_hub.collections_due.empty') }}</p>
        @endforelse
    </section>
</div>
@endsection
