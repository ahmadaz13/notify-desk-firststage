{{--
    E. Money summary (§19.8, §9): an operational view, not a finance dashboard. Staff see the amount due,
    the latest confirmed payment and receipts awaiting confirmation; no company totals or balances.
--}}
@php($money = $workspace->money)
@if($money)
<section class="notify-card notify-ws-card" aria-labelledby="client-money-title" id="sec-amount-due" data-client-money>
    <header class="notify-ws-card__head">
        <h2 class="notify-ws-card__title" id="client-money-title"><x-notify.icon name="wallet" :size="18" /> {{ __('notify.client_hub.money.title') }}</h2>
    </header>

    <div class="notify-money-summary">
        @if($money['due_minor'] > 0)
            <p class="notify-money-summary__due {{ $money['overdue_minor'] > 0 ? 'is-overdue' : '' }}" data-amount-due>
                <span class="notify-money-summary__label">{{ __('notify.client_hub.money.due') }}</span>
                <x-notify.money :minor="$money['due_minor']" class="notify-money-summary__value" />
            </p>
            @if($money['overdue_minor'] > 0)
                <p class="notify-status notify-status--danger" data-amount-overdue><x-notify.icon name="alert-circle" :size="14" />{{ __('notify.client_hub.money.overdue') }} <x-notify.money :minor="$money['overdue_minor']" /></p>
            @endif
            @if($money['next_due'])
                <p class="notify-money-summary__meta">{{ __('notify.client_hub.money.next_due') }}: <span dir="ltr">{{ $money['next_due'] }}</span></p>
            @endif
        @else
            <p class="notify-status notify-status--success" data-amount-clear><x-notify.icon name="check" :size="14" />{{ __('notify.client_hub.money.all_paid') }}</p>
        @endif
        @if($money['credit_minor'] > 0)
            <p class="notify-money-summary__meta">{{ __('notify.client_hub.money.credit') }}: <x-notify.money :minor="$money['credit_minor']" /></p>
        @endif
    </div>

    <div class="notify-money-row">
        <span class="notify-money-row__label">{{ __('notify.client_hub.money.latest_payment') }}</span>
        @if($money['latest_payment'])
            <span><x-notify.money :minor="$money['latest_payment']['amount_minor']" /> · <span dir="ltr">{{ $money['latest_payment']['date'] }}</span>@if($money['latest_payment']['method']) · {{ $money['latest_payment']['method'] }}@endif@if($money['latest_payment']['reference']) · <span dir="ltr">{{ $money['latest_payment']['reference'] }}</span>@endif</span>
        @else
            <span class="notify-muted">{{ __('notify.client_hub.money.no_payments') }}</span>
        @endif
    </div>

    @if($money['pending']->isNotEmpty())
        <div class="notify-pending" id="sec-pending-receipts" data-client-pending-receipts>
            <p class="notify-pending__title"><x-notify.icon name="clipboard-list" :size="16" /> {{ __('notify.client_hub.money.pending_title') }}</p>
            <ul class="notify-plain-list">
                @foreach($money['pending'] as $receipt)
                    <li data-pending-receipt="{{ $receipt->id }}">
                        <div>
                            <strong>{{ $receipt->amountFormatted() }} {{ __('notify.common.currency_jod') }}</strong>
                            <span class="notify-status notify-status--warning">{{ __('notify.payment_receipts.status.pending') }}</span>
                            <small>{{ $receipt->methodLabel() }} · <span dir="ltr">{{ $receipt->received_at->format('Y-m-d H:i') }}</span>@if($receipt->submitter) · {{ __('notify.client_hub.money.by', ['name' => $receipt->submitter->name]) }}@endif</small>
                        </div>
                        @if((int) $receipt->submitted_by === (int) auth()->id())
                            <form method="POST" action="{{ route('payment-receipts.cancel', $receipt) }}">
                                @csrf
                                <button type="submit" class="notify-button notify-button--ghost notify-button--sm">{{ __('notify.payment_receipts.action_cancel') }}</button>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
            <p class="notify-sheet-hint">{{ __('notify.client_hub.money.pending_hint') }}</p>
        </div>
    @endif

    @if($money['details_url'])
        <a class="notify-ws-card__link" href="{{ $money['details_url'] }}" data-money-details>{{ __('notify.client_hub.money.details') }} <x-notify.icon name="chevron-left" :size="16" class="notify-icon--directional" /></a>
    @endif
</section>
@endif
