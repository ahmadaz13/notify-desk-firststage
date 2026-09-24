@props([
    'client',
    'amountDue',
    'paymentMethodOptions',
])

@php
    // Owner-level users record a confirmed payment directly (§9.4); Staff submit a receipt for approval (§9.2).
    // Two distinct forms and routes; this sheet only presents them. Amounts are converted to fils server-side.
    $canRecordPayment = \App\Support\Permissions::allows(auth()->user(), \App\Support\Permissions::RECORD_PAYMENT);
    $canSubmitReceipt = ! $canRecordPayment && \App\Support\Permissions::allows(auth()->user(), \App\Support\Permissions::SUBMIT_PAYMENT_RECEIPT);
    $sheetId = 'modal-record-payment';
    $old = \App\Support\FormState::oldFor($sheetId);
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
    $method = $old('payment_method', \App\Support\PaymentMethods::CASH);
    $moreOpen = \App\Support\FormState::active($sheetId) && $errors->hasAny(['received_at', 'reference', $noteField]);
@endphp

@if($canRecordPayment || $canSubmitReceipt)
@formscope($sheetId)
<x-notify.sheet :id="$sheetId" :title="$sheetTitle" :subtitle="$client->business_name" :action="$formAction"
    :form-attributes="['data-payment-form' => $canRecordPayment ? 'owner' : 'staff-receipt']">
    <input type="hidden" name="_idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">

    <div class="notify-amount-due-box" data-amount-due>
        <span>{{ __('notify.client_workspace.amount_due') }}</span>
        <strong class="{{ $amountDueMinor > 0 ? 'notify-text-danger' : 'notify-text-success' }}">
            <x-notify.money :minor="max(0, $amountDueMinor)" />
        </strong>
    </div>

    @if($canSubmitReceipt)
        <p class="notify-form-note notify-form-note--info">{{ __('notify.payment_receipts.staff_hint') }}</p>
    @endif

    <x-notify.form-field :label="__('notify.payment_receipts.field_amount')" for="normal-payment-amount" name="amount" :required="true">
        <x-notify.money-input id="normal-payment-amount" name="amount" :required="true" :value="$old('amount', $amountDueMinor > 0 ? $amountDueValue : '')" />
    </x-notify.form-field>

    <x-notify.form-field :label="__('notify.payment_receipts.field_method')" name="payment_method" :required="true" :group="true">
        <div class="notify-choices notify-choices--segmented" data-payment-methods>
            @foreach($paymentMethodOptions as $methodValue => $methodLabel)
                <label class="notify-choice-chip">
                    <input type="radio" name="payment_method" value="{{ $methodValue }}" required @checked($method === $methodValue)>
                    <span>{{ $methodLabel }}</span>
                </label>
            @endforeach
        </div>
    </x-notify.form-field>

    <details class="notify-sheet__more" @if($moreOpen) open @endif>
        <summary>{{ __('notify.payment_receipts.more_options') }}</summary>
        <div class="notify-form-row">
            <x-notify.form-field :label="__('notify.payment_receipts.field_received_at')" for="normal-payment-received-at" name="received_at">
                <input id="normal-payment-received-at" class="notify-input" type="datetime-local" name="received_at"
                       value="{{ $old('received_at', now()->format('Y-m-d\\TH:i')) }}"
                       max="{{ now()->format('Y-m-d\\TH:i') }}"
                       @if($receivedAtMin) min="{{ $receivedAtMin }}" @endif
                       @invalid('received_at', 'normal-payment-received-at')>
            </x-notify.form-field>
            <x-notify.form-field :label="__('notify.payment_receipts.field_reference')" for="normal-payment-reference" name="reference" :optional="true">
                <input id="normal-payment-reference" class="notify-input" name="reference" value="{{ $old('reference') }}" dir="ltr" @invalid('reference', 'normal-payment-reference')>
            </x-notify.form-field>
            <x-notify.form-field :label="__('notify.payment_receipts.field_note')" for="normal-payment-notes" :name="$noteField" :optional="true" class="notify-form-row__full">
                <textarea id="normal-payment-notes" class="notify-input" name="{{ $noteField }}" rows="2" @invalid($noteField, 'normal-payment-notes')>{{ $old($noteField) }}</textarea>
            </x-notify.form-field>
        </div>
    </details>

    <x-slot:footer>
        <button type="button" class="notify-button notify-button--ghost" data-sheet-close>{{ __('notify.client_workspace.cancel') }}</button>
        <button type="submit" class="notify-button notify-button--primary">{{ $canRecordPayment ? __('notify.client_workspace.action_record_payment') : __('notify.payment_receipts.action_submit') }}</button>
    </x-slot:footer>
</x-notify.sheet>
@endformscope
@endif
