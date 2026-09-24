@props([
    'client',
    'teamUsers' => collect(),
    'appointmentTypeLabels' => [],
])

@php
    $old = \App\Support\FormState::oldFor('modal-record-call');
    $result = $old('result');
    $outcomes = [
        'no_answer_busy' => __('notify.client_workspace.outcomes.no_answer'),
        'answered' => __('notify.client_workspace.outcomes.interested'),
        'appointment' => __('notify.client_workspace.outcomes.appointment'),
        'callback_later' => __('notify.client_workspace.outcomes.call_later'),
        'not_interested' => __('notify.client_workspace.outcomes.not_interested'),
    ];
@endphp

@formscope('modal-record-call')
<x-notify.sheet id="modal-record-call" :title="__('notify.client_workspace.record_call')" :subtitle="$client->business_name"
    :action="route('clients.contact-attempts.store', $client->id)" :form-attributes="['data-call-outcome-form' => true]">
    <input type="hidden" name="method" value="phone">

    <x-notify.form-field :label="__('notify.client_workspace.what_happened')" name="result" :required="true" :group="true">
        <div class="notify-choices" data-outcome-selector>
            @foreach($outcomes as $value => $label)
                <label class="notify-choice-chip">
                    <input type="radio" name="result" value="{{ $value }}" @checked($result === $value) @if($loop->first) required @endif>
                    <span>{{ $label }}</span>
                </label>
            @endforeach
        </div>
        <details class="notify-sheet__more notify-more-outcomes" @if($result === 'wrong_invalid') open @endif>
            <summary>{{ __('notify.client_workspace.more_options') }}</summary>
            <div class="notify-choices">
                <label class="notify-choice-chip">
                    <input type="radio" name="result" value="wrong_invalid" @checked($result === 'wrong_invalid')>
                    <span>{{ __('notify.client_workspace.outcomes.wrong_invalid') }}</span>
                </label>
            </div>
        </details>
    </x-notify.form-field>

    {{-- Conditional: Appointment --}}
    <div class="notify-sheet__section notify-conditional-fields" data-for-outcome="appointment" hidden>
        <div class="notify-form-row">
            <x-notify.form-field :label="__('notify.client_workspace.fields.date')" for="call-appointment-date" name="appointment_date" :required="true">
                <input id="call-appointment-date" class="notify-input" type="date" name="appointment_date" value="{{ $old('appointment_date', now()->addDay()->toDateString()) }}" @invalid('appointment_date', 'call-appointment-date')>
            </x-notify.form-field>
            <x-notify.form-field :label="__('notify.client_workspace.fields.time')" for="call-appointment-time" name="appointment_time" :required="true">
                <input id="call-appointment-time" class="notify-input" type="time" name="appointment_time" value="{{ $old('appointment_time', '11:00') }}" @invalid('appointment_time', 'call-appointment-time')>
            </x-notify.form-field>
            <x-notify.form-field :label="__('notify.client_workspace.fields.type')" for="call-appointment-type" name="appointment_type" class="notify-form-row__full">
                <select id="call-appointment-type" class="notify-input" name="appointment_type" @invalid('appointment_type', 'call-appointment-type')>
                    @foreach($appointmentTypeLabels as $typeKey => $typeLabel)
                        <option value="{{ $typeKey }}" @selected($old('appointment_type') === $typeKey)>{{ $typeLabel }}</option>
                    @endforeach
                </select>
            </x-notify.form-field>
        </div>

        <details class="notify-sheet__more notify-more-options" @if(\App\Support\FormState::active('modal-record-call') &&$errors->hasAny(['location', 'branch_name', 'appointment_notes'])) open @endif>
            <summary>{{ __('notify.client_workspace.more_options') }}</summary>
            <div class="notify-form-row">
                <x-notify.form-field :label="__('notify.client_workspace.fields.location')" for="call-location" name="location" :optional="true">
                    <input id="call-location" class="notify-input" type="text" name="location" value="{{ $old('location', $client->location_text) }}" @invalid('location', 'call-location')>
                </x-notify.form-field>
                <x-notify.form-field :label="__('notify.client_workspace.fields.branch')" for="call-branch" name="branch_name" :optional="true">
                    <input id="call-branch" class="notify-input" type="text" name="branch_name" value="{{ $old('branch_name') }}" placeholder="{{ __('notify.client_workspace.fields.branch_placeholder') }}" @invalid('branch_name', 'call-branch')>
                </x-notify.form-field>
                <x-notify.form-field :label="__('notify.client_workspace.fields.notes')" for="call-appointment-notes" name="appointment_notes" :optional="true" class="notify-form-row__full">
                    <textarea id="call-appointment-notes" class="notify-input" name="appointment_notes" rows="2" @invalid('appointment_notes', 'call-appointment-notes')>{{ $old('appointment_notes') }}</textarea>
                </x-notify.form-field>
            </div>
        </details>
    </div>

    {{-- Conditional: Call later --}}
    <div class="notify-sheet__section notify-conditional-fields" data-for-outcome="callback_later" hidden>
        <x-notify.form-field :label="__('notify.client_workspace.fields.callback_datetime')" for="call-callback-at" name="follow_up_date_time" :required="true">
            <input id="call-callback-at" class="notify-input" type="datetime-local" name="follow_up_date_time" value="{{ $old('follow_up_date_time', now()->addDay()->setTime(10, 0)->format('Y-m-d\\TH:i')) }}" @invalid('follow_up_date_time', 'call-callback-at')>
        </x-notify.form-field>
    </div>

    {{-- Conditional: review notices --}}
    <p class="notify-form-note notify-conditional-fields" data-for-outcome="not_interested" hidden>
        <strong>{{ __('notify.client_workspace.review_notice_title') }}</strong>: {{ __('notify.client_workspace.review_notice_desc') }}
    </p>
    <p class="notify-form-note notify-conditional-fields" data-for-outcome="wrong_invalid" hidden>
        <strong>{{ __('notify.client_workspace.wrong_notice_title') }}</strong>: {{ __('notify.client_workspace.wrong_notice_desc') }}
    </p>

    {{-- Note: optional, required for "not interested" --}}
    <div data-note-container hidden>
        <x-notify.form-field :label="__('notify.client_workspace.fields.notes')" for="call-note" name="note">
            <textarea id="call-note" class="notify-input" name="note" rows="2" placeholder="{{ __('notify.client_workspace.fields.notes_placeholder') }}" @invalid('note', 'call-note')>{{ $old('note') }}</textarea>
            <p class="notify-form-field__hint" data-note-required hidden>{{ __('notify.ui.required') }}</p>
        </x-notify.form-field>
    </div>

    <x-slot:footer>
        <button type="button" class="notify-button notify-button--ghost" data-sheet-close>{{ __('notify.client_workspace.cancel') }}</button>
        <button type="submit" class="notify-button notify-button--primary">{{ __('notify.client_workspace.save_result') }}</button>
    </x-slot:footer>
</x-notify.sheet>
@endformscope
