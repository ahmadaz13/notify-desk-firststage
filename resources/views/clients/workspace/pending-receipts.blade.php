@php
    $canApproveReceipts = \App\Support\FinancialPermissions::allows(auth()->user(), \App\Support\FinancialPermissions::APPROVE_PAYMENT_RECEIPTS);
@endphp

@if($pendingPaymentReceipts->isNotEmpty())
    <div id="sec-pending-receipts" style="margin-top:14px;padding-top:12px;border-top:1px solid var(--notify-border)">
        <small class="notify-amount-due-box__label">{{ __('notify.payment_receipts.pending_title') }}</small>
        @foreach($pendingPaymentReceipts as $receipt)
            <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;margin-top:8px">
                <div>
                    <strong>{{ $receipt->amountFormatted() }} {{ __('notify.common.currency_jod') }}</strong>
                    <span class="notify-badge notify-badge--warning">{{ __('notify.payment_receipts.status.pending') }}</span>
                    <div><small class="notify-muted">{{ $receipt->methodLabel() }} · <span dir="ltr">{{ $receipt->received_at->format('Y-m-d H:i') }}</span> · {{ $receipt->submitter?->name }}</small></div>
                </div>
                <div style="display:flex;gap:6px">
                    @if((int) $receipt->submitted_by === (int) auth()->id())
                        <form method="POST" action="{{ route('payment-receipts.cancel', $receipt) }}">
                            @csrf
                            <button type="submit" class="notify-button notify-button--ghost notify-button--sm">{{ __('notify.payment_receipts.action_cancel') }}</button>
                        </form>
                    @endif
                    @if($canApproveReceipts)
                        <a href="{{ route('collections.index', ['client_id' => $receipt->client_id]) }}#sec-pending-receipts" class="notify-button notify-button--soft notify-button--sm">{{ __('notify.payment_receipts.action_review') }}</a>
                    @endif
                </div>
            </div>
        @endforeach
        <p class="notify-muted" style="font-size:11px;margin-top:6px">{{ __('notify.payment_receipts.pending_not_deducted') }}</p>
    </div>
@endif
