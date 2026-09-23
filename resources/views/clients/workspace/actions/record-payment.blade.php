@props([
    'client',
    'amountDue',
    'paymentMethodOptions',
])

@php
    // Owner-level users record a confirmed payment directly (§9.4); Staff submit a receipt for approval (§9.2).
    $canRecordPayment = \App\Support\FinancialPermissions::allows(auth()->user(), \App\Support\FinancialPermissions::RECORD_PAYMENT);
    $canSubmitReceipt = ! $canRecordPayment && \App\Support\FinancialPermissions::allows(auth()->user(), \App\Support\FinancialPermissions::SUBMIT_PAYMENT_RECEIPT);
    $amountDueMinor = (int) ($amountDue['total_minor'] ?? 0);
    $amountDueValue = \App\Support\Money::fromMinorUnits(max(0, $amountDueMinor))->format();
    $sheetTitle = $canRecordPayment
        ? __('notify.client_workspace.action_record_payment')
        : __('notify.payment_receipts.action_payment_received');
    $formAction = $canRecordPayment
        ? route('clients.payments.normal.store', $client)
        : route('clients.payment-receipts.store', $client);
    $noteField = $canRecordPayment ? 'notes' : 'note';
    $receivedAtMin = $canSubmitReceipt
        ? now()->subDays(\App\Models\PaymentReceiptConfirmation::STAFF_MAX_BACKDATE_DAYS)->format('Y-m-d\TH:i')
        : null;
@endphp

@if($canRecordPayment || $canSubmitReceipt)
<div class="notify-modal-backdrop" id="modal-record-payment" hidden>
    <div class="notify-modal-card notify-action-sheet" role="dialog" aria-modal="true" aria-labelledby="modal-record-payment-title" style="max-width:560px">
        <div class="notify-modal-header">
            <div>
                <span class="notify-eyebrow">{{ $canRecordPayment ? 'PAYMENT_NORMAL_01' : 'PAYMENT_RECEIPT_01' }}</span>
                <h3 id="modal-record-payment-title">{{ $sheetTitle }}</h3>
            </div>
            <button type="button" class="notify-icon-button" data-close-action-modal aria-label="{{ __('notify.client_workspace.cancel') }}">×</button>
        </div>

        <form method="POST" action="{{ $formAction }}" class="notify-action-form">
            @csrf
            <input type="hidden" name="_idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">

            <div class="notify-form-group">
                <label class="notify-field-label">{{ __('notify.client_workspace.amount_due') }}</label>
                <div class="notify-amount-due-box" style="padding:10px 12px">
                    <strong class="@if($amountDueMinor > 0) notify-text-danger @else notify-text-success @endif">
                        {{ $amountDue['total_formatted'] ?? $amountDueValue }} {{ $amountDue['currency'] ?? __('notify.common.currency_jod') }}
                    </strong>
                </div>
            </div>

            @if($canSubmitReceipt)
                <p class="notify-muted" style="margin-top:8px;font-size:12px">{{ __('notify.payment_receipts.staff_hint') }}</p>
            @endif

            <div class="notify-form-grid" style="grid-template-columns:1fr 1fr;gap:12px;margin-top:12px">
                <div class="notify-form-group">
                    <label class="notify-field-label" for="normal-payment-amount"><strong>{{ __('notify.payment_receipts.field_amount') }}</strong></label>
                    <input
                        id="normal-payment-amount"
                        name="amount"
                        inputmode="decimal"
                        required
                        value="{{ old('amount', $amountDueMinor > 0 ? $amountDueValue : '') }}"
                        placeholder="0.000"
                        class="notify-form-input">
                    @error('amount')
                        <small class="notify-text-danger">{{ $message }}</small>
                    @enderror
                </div>

                <div class="notify-form-group">
                    <label class="notify-field-label" for="normal-payment-method"><strong>{{ __('notify.payment_receipts.field_method') }}</strong></label>
                    <select id="normal-payment-method" name="payment_method" required class="notify-form-select">
                        @foreach($paymentMethodOptions as $methodValue => $methodLabel)
                            <option value="{{ $methodValue }}" @selected(old('payment_method', \App\Support\PaymentMethods::CASH) === $methodValue)>{{ $methodLabel }}</option>
                        @endforeach
                    </select>
                    @error('payment_method')
                        <small class="notify-text-danger">{{ $message }}</small>
                    @enderror
                </div>
            </div>

            <details style="margin-top:12px" @if($errors->has('received_at')) open @endif>
                <summary style="cursor:pointer;font-size:12px;font-weight:700;color:var(--nd-muted)">{{ __('notify.payment_receipts.more_options') }}</summary>
                <div class="notify-form-grid" style="grid-template-columns:1fr 1fr;gap:12px;margin-top:10px">
                    <div class="notify-form-group">
                        <label class="notify-field-label" for="normal-payment-received-at">{{ __('notify.payment_receipts.field_received_at') }}</label>
                        <input id="normal-payment-received-at" type="datetime-local" name="received_at"
                               value="{{ old('received_at', now()->format('Y-m-d\\TH:i')) }}"
                               max="{{ now()->format('Y-m-d\\TH:i') }}"
                               @if($receivedAtMin) min="{{ $receivedAtMin }}" @endif
                               class="notify-form-input">
                        @error('received_at')
                            <small class="notify-text-danger">{{ $message }}</small>
                        @enderror
                    </div>
                    <div class="notify-form-group">
                        <label class="notify-field-label" for="normal-payment-reference">{{ __('notify.payment_receipts.field_reference') }}</label>
                        <input id="normal-payment-reference" name="reference" value="{{ old('reference') }}" class="notify-form-input">
                    </div>
                </div>
                <div class="notify-form-group" style="margin-top:10px">
                    <label class="notify-field-label" for="normal-payment-notes">{{ __('notify.payment_receipts.field_note') }}</label>
                    <textarea id="normal-payment-notes" name="{{ $noteField }}" rows="2" class="notify-form-textarea">{{ old($noteField) }}</textarea>
                </div>
            </details>

            <div class="notify-modal-footer" style="margin-top:16px">
                <button type="button" class="notify-button-secondary" data-close-action-modal>{{ __('notify.client_workspace.cancel') }}</button>
                <button type="submit" class="notify-button-primary">{{ $canRecordPayment ? __('notify.client_workspace.action_record_payment') : __('notify.payment_receipts.action_submit') }}</button>
            </div>
        </form>
    </div>
</div>
@endif
