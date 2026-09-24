@props([
    'client',
])

@php
    $sheetId = 'modal-reopen-client';
    $old = \App\Support\FormState::oldFor($sheetId);
    $stages = ['prospect', 'contacting', 'appointment', 'decision_pending'];
@endphp

@formscope($sheetId)
<x-notify.sheet :id="$sheetId" :title="__('notify.client_workspace.action_reopen')" :subtitle="$client->business_name"
    :action="route('clients.reopen', $client->id)" :form-attributes="['data-reopen-client-form' => true]">

    <x-notify.form-field :label="__('notify.client_workspace.fields.reason')" for="reopen-reason" name="reason" :required="true">
        <textarea id="reopen-reason" class="notify-input" name="reason" required rows="2" maxlength="1000" placeholder="{{ __('notify.client_workspace.reopen_notes_placeholder') }}" @invalid('reason', 'reopen-reason')>{{ $old('reason') }}</textarea>
    </x-notify.form-field>

    <x-notify.form-field :label="__('notify.client_workspace.fields.target_stage')" for="reopen-stage" name="stage">
        <select id="reopen-stage" class="notify-input" name="stage" @invalid('stage', 'reopen-stage')>
            @foreach($stages as $stage)
                <option value="{{ $stage }}" @selected($old('stage') === $stage)>{{ __('notify.lifecycle.'.$stage) }}</option>
            @endforeach
        </select>
    </x-notify.form-field>

    <x-slot:footer>
        <button type="button" class="notify-button notify-button--ghost" data-sheet-close>{{ __('notify.client_workspace.cancel') }}</button>
        <button type="submit" class="notify-button notify-button--primary">{{ __('notify.client_workspace.action_reopen') }}</button>
    </x-slot:footer>
</x-notify.sheet>
@endformscope
