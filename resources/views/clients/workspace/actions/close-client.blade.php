@props([
    'client',
])

@php
    $sheetId = 'modal-close-client';
    $old = \App\Support\FormState::oldFor($sheetId);
    $reasons = ['price', 'not_needed', 'not_ready', 'competitor', 'no_response', 'business_closed', 'invalid_contact', 'not_interested', 'other'];
@endphp

{{-- Destructive: explicit consequence, reason required, danger submit (P12 confirmation rules). --}}
@formscope($sheetId)
<x-notify.sheet :id="$sheetId" :title="__('notify.client_workspace.close_client')" :subtitle="$client->business_name"
    :action="route('clients.close', $client->id)" :form-attributes="['data-close-client-form' => true]">

    <p class="notify-form-note notify-form-note--danger">
        <strong>{{ __('notify.client_workspace.close_notice_title') }}</strong>: {{ __('notify.client_workspace.close_notice_desc') }}
    </p>

    <x-notify.form-field :label="__('notify.client_workspace.fields.close_reason_code')" for="close-reason-code" name="closed_reason_code" :required="true">
        <select id="close-reason-code" class="notify-input" name="closed_reason_code" required data-close-reason-select @invalid('closed_reason_code', 'close-reason-code')>
            <option value="">{{ __('notify.client_workspace.fields.select_reason') }}</option>
            @foreach($reasons as $reason)
                <option value="{{ $reason }}" @selected($old('closed_reason_code') === $reason)>{{ __('notify.client_workspace.close_reasons.'.$reason) }}</option>
            @endforeach
        </select>
    </x-notify.form-field>

    <x-notify.form-field :label="__('notify.client_workspace.fields.notes')" for="close-reason-note" name="closed_reason">
        <textarea id="close-reason-note" class="notify-input" name="closed_reason" rows="2" placeholder="{{ __('notify.client_workspace.fields.close_notes_placeholder') }}" @invalid('closed_reason', 'close-reason-note')>{{ $old('closed_reason') }}</textarea>
        <p class="notify-form-field__hint" data-close-note-required hidden>{{ __('notify.ui.required') }}</p>
    </x-notify.form-field>

    <x-slot:footer>
        <button type="button" class="notify-button notify-button--ghost" data-sheet-close>{{ __('notify.client_workspace.cancel') }}</button>
        <button type="submit" class="notify-button notify-button--danger">{{ __('notify.client_workspace.confirm_close') }}</button>
    </x-slot:footer>
</x-notify.sheet>
@endformscope
