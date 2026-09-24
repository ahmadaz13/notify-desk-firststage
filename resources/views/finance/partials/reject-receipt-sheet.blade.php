{{-- Reject a Staff receipt (P1 §9.2): reason required; rejection has no financial effect. --}}
@php
    $sheetId = 'reject-receipt-'.$receipt->id;
    $old = \App\Support\FormState::oldFor($sheetId);
@endphp
@formscope($sheetId)
<x-notify.sheet :id="$sheetId" size="sm" :title="__('notify.finance_hub.collections.reject_title')"
    :subtitle="$receipt->client?->business_name" :action="route('payment-receipts.reject', $receipt)">
    <input type="hidden" name="_idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
    <p class="notify-form-note">{{ __('notify.finance_hub.collections.reject_hint') }}</p>
    <x-notify.form-field :label="__('notify.payment_receipts.rejection_reason')" :for="$sheetId.'-reason'" name="rejection_reason" :required="true">
        <textarea id="{{ $sheetId }}-reason" class="notify-input" name="rejection_reason" required maxlength="1000" rows="2"
                  placeholder="{{ __('notify.payment_receipts.rejection_reason_placeholder') }}" @invalid('rejection_reason', $sheetId.'-reason')>{{ $old('rejection_reason') }}</textarea>
    </x-notify.form-field>
    <x-slot:footer>
        <button type="button" class="notify-button notify-button--ghost" data-sheet-close>{{ __('notify.ui.cancel') }}</button>
        <button type="submit" class="notify-button notify-button--danger">{{ __('notify.payment_receipts.action_reject') }}</button>
    </x-slot:footer>
</x-notify.sheet>
@endformscope
