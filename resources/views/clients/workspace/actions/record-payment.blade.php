@props([
    'client',
    'amountDue',
    'paymentMethodOptions',
])

@php
    $canRecordPayment = \App\Support\FinancialPermissions::allows(auth()->user(), \App\Support\FinancialPermissions::RECORD_PAYMENT);
    $amountDueMinor = (int) ($amountDue['total_minor'] ?? 0);
    $amountDueValue = \App\Support\Money::fromMinorUnits(max(0, $amountDueMinor))->format();
@endphp

@if($canRecordPayment)
<div class="notify-modal-backdrop" id="modal-record-payment" hidden>
    <div class="notify-modal-card notify-action-sheet" role="dialog" aria-modal="true" aria-labelledby="modal-record-payment-title" style="max-width:560px">
        <div class="notify-modal-header">
            <div>
                <span class="notify-eyebrow">PAYMENT_NORMAL_01</span>
                <h3 id="modal-record-payment-title">{{ __('notify.client_workspace.action_record_payment') }}</h3>
            </div>
            <button type="button" class="notify-icon-button" data-close-action-modal aria-label="{{ __('notify.client_workspace.cancel') }}">×</button>
        </div>

        <form method="POST" action="{{ route('clients.payments.normal.store', $client) }}" class="notify-action-form">
            @csrf

            <div class="notify-form-group">
                <label class="notify-field-label">{{ __('notify.client_workspace.amount_due') }}</label>
                <div class="notify-amount-due-box" style="padding:10px 12px">
                    <strong class="@if($amountDueMinor > 0) notify-text-danger @else notify-text-success @endif">
                        {{ $amountDue['total_formatted'] ?? $amountDueValue }} {{ $amountDue['currency'] ?? __('notify.common.currency_jod') }}
                    </strong>
                </div>
            </div>

            <div class="notify-form-grid" style="grid-template-columns:1fr 1fr;gap:12px;margin-top:12px">
                <div class="notify-form-group">
                    <label class="notify-field-label" for="normal-payment-amount"><strong>المبلغ المستلم</strong></label>
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
                    <label class="notify-field-label" for="normal-payment-method"><strong>طريقة الدفع</strong></label>
                    <select id="normal-payment-method" name="payment_method" required class="notify-form-select">
                        <option value="">اختر طريقة الدفع...</option>
                        @foreach($paymentMethodOptions as $methodValue => $methodLabel)
                            <option value="{{ $methodValue }}" @selected(old('payment_method') === $methodValue)>{{ $methodLabel }}</option>
                        @endforeach
                    </select>
                    @error('payment_method')
                        <small class="notify-text-danger">{{ $message }}</small>
                    @enderror
                </div>
            </div>

            <details style="margin-top:12px">
                <summary style="cursor:pointer;font-size:12px;font-weight:700;color:var(--nd-muted)">خيارات إضافية</summary>
                <div class="notify-form-grid" style="grid-template-columns:1fr 1fr;gap:12px;margin-top:10px">
                    <div class="notify-form-group">
                        <label class="notify-field-label" for="normal-payment-received-at">وقت الاستلام</label>
                        <input id="normal-payment-received-at" type="datetime-local" name="received_at" value="{{ old('received_at', now()->format('Y-m-d\\TH:i')) }}" class="notify-form-input">
                    </div>
                    <div class="notify-form-group">
                        <label class="notify-field-label" for="normal-payment-reference">مرجع الدفعة</label>
                        <input id="normal-payment-reference" name="reference" value="{{ old('reference') }}" class="notify-form-input">
                    </div>
                </div>
                <div class="notify-form-group" style="margin-top:10px">
                    <label class="notify-field-label" for="normal-payment-notes">ملاحظات</label>
                    <textarea id="normal-payment-notes" name="notes" rows="2" class="notify-form-textarea">{{ old('notes') }}</textarea>
                </div>
            </details>

            <div class="notify-modal-footer" style="margin-top:16px">
                <button type="button" class="notify-button-secondary" data-close-action-modal>{{ __('notify.client_workspace.cancel') }}</button>
                <button type="submit" class="notify-button-primary">{{ __('notify.client_workspace.action_record_payment') }}</button>
            </div>
        </form>
    </div>
</div>
@endif
