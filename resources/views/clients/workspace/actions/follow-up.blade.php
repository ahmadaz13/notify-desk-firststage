@props([
    'client',
    'activeFollowUp' => null,
    'appointmentTypeLabels' => [],
])

@if($activeFollowUp)
@php
    $sheetId = 'modal-follow-up';
    $old = \App\Support\FormState::oldFor($sheetId);
    $outcome = $old('outcome');
    $outcomes = [
        'subscribe' => __('notify.client_workspace.outcomes.ready_to_subscribe'),
        'callback_later' => __('notify.client_workspace.outcomes.wait_call_later'),
        'appointment' => __('notify.client_workspace.outcomes.appointment'),
        'no_answer' => __('notify.client_workspace.outcomes.no_answer'),
        'not_interested' => __('notify.client_workspace.outcomes.not_interested'),
    ];
    $due = $activeFollowUp->follow_up_date_time ? substr((string) $activeFollowUp->follow_up_date_time, 0, 16) : null;
@endphp

@formscope($sheetId)
<x-notify.sheet :id="$sheetId" :title="__('notify.client_workspace.record_follow_up')" :subtitle="collect([$client->business_name, $activeFollowUp->reason, $due])->filter()->join(' · ')"
    :action="route('follow-ups.complete', $activeFollowUp->id)" :form-attributes="['data-followup-outcome-form' => true]">

    <x-notify.form-field :label="__('notify.client_workspace.what_happened')" name="outcome" :required="true" :group="true">
        <div class="notify-choices">
            @foreach($outcomes as $value => $label)
                <label class="notify-choice-chip">
                    <input type="radio" name="outcome" value="{{ $value }}" @checked($outcome === $value) @if($loop->first) required @endif>
                    <span>{{ $label }}</span>
                </label>
            @endforeach
        </div>
    </x-notify.form-field>

    {{-- Call back later --}}
    <div class="notify-form-row notify-conditional-fields" data-for-followup-outcome="callback_later" hidden>
        <x-notify.form-field :label="__('notify.client_workspace.fields.date')" for="follow-up-next-date" name="next_follow_up_date" :required="true">
            <input id="follow-up-next-date" class="notify-input" type="date" name="next_follow_up_date" value="{{ $old('next_follow_up_date', now()->addDay()->toDateString()) }}" @invalid('next_follow_up_date', 'follow-up-next-date')>
        </x-notify.form-field>
        <x-notify.form-field :label="__('notify.client_workspace.fields.time')" for="follow-up-next-time" name="next_follow_up_time" :required="true">
            <input id="follow-up-next-time" class="notify-input" type="time" name="next_follow_up_time" value="{{ $old('next_follow_up_time', '10:00') }}" @invalid('next_follow_up_time', 'follow-up-next-time')>
        </x-notify.form-field>
    </div>

    {{-- Appointment --}}
    <div class="notify-form-row notify-conditional-fields" data-for-followup-outcome="appointment" hidden>
        <x-notify.form-field :label="__('notify.client_workspace.fields.date')" for="follow-up-appointment-date" name="appointment_date" :required="true">
            <input id="follow-up-appointment-date" class="notify-input" type="date" name="appointment_date" value="{{ $old('appointment_date', now()->addDay()->toDateString()) }}" @invalid('appointment_date', 'follow-up-appointment-date')>
        </x-notify.form-field>
        <x-notify.form-field :label="__('notify.client_workspace.fields.time')" for="follow-up-appointment-time" name="appointment_time" :required="true">
            <input id="follow-up-appointment-time" class="notify-input" type="time" name="appointment_time" value="{{ $old('appointment_time', '11:00') }}" @invalid('appointment_time', 'follow-up-appointment-time')>
        </x-notify.form-field>
        <x-notify.form-field :label="__('notify.client_workspace.fields.type')" for="follow-up-appointment-type" name="appointment_type" class="notify-form-row__full">
            <select id="follow-up-appointment-type" class="notify-input" name="appointment_type" @invalid('appointment_type', 'follow-up-appointment-type')>
                @foreach($appointmentTypeLabels as $typeKey => $typeLabel)
                    <option value="{{ $typeKey }}" @selected($old('appointment_type') === $typeKey)>{{ $typeLabel }}</option>
                @endforeach
            </select>
        </x-notify.form-field>
    </div>

    {{-- No answer: when to call again --}}
    <div class="notify-conditional-fields" data-for-followup-outcome="no_answer" hidden>
        <x-notify.form-field :label="__('notify.client_workspace.fields.callback_datetime')" for="follow-up-callback-at" name="follow_up_date_time" :required="true">
            <input id="follow-up-callback-at" class="notify-input" type="datetime-local" name="follow_up_date_time" value="{{ $old('follow_up_date_time', now()->addDay()->setTime(10, 0)->format('Y-m-d\\TH:i')) }}" @invalid('follow_up_date_time', 'follow-up-callback-at')>
        </x-notify.form-field>
    </div>

    {{-- Not interested --}}
    <div class="notify-sheet__section notify-conditional-fields" data-for-followup-outcome="not_interested" hidden>
        <p class="notify-form-note">
            <strong>{{ __('notify.client_workspace.review_notice_title') }}</strong>: {{ __('notify.client_workspace.review_notice_desc') }}
        </p>
        <x-notify.form-field :label="__('notify.client_workspace.fields.reason')" for="follow-up-reason" name="reason" :required="true">
            <input id="follow-up-reason" class="notify-input" type="text" name="reason" value="{{ $old('reason') }}" placeholder="{{ __('notify.client_workspace.fields.not_interested_reason_placeholder') }}" @invalid('reason', 'follow-up-reason')>
        </x-notify.form-field>
    </div>

    <x-notify.form-field :label="__('notify.client_workspace.fields.notes')" for="follow-up-notes" name="notes" :optional="true">
        <textarea id="follow-up-notes" class="notify-input" name="notes" rows="2" placeholder="{{ __('notify.client_workspace.fields.notes_placeholder') }}" @invalid('notes', 'follow-up-notes')>{{ $old('notes') }}</textarea>
    </x-notify.form-field>

    <x-slot:footer>
        <button type="button" class="notify-button notify-button--ghost" data-sheet-close>{{ __('notify.client_workspace.cancel') }}</button>
        <button type="submit" class="notify-button notify-button--primary">{{ __('notify.client_workspace.save_result') }}</button>
    </x-slot:footer>
</x-notify.sheet>
@endformscope
@endif
