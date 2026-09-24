@props([
    'client',
    'activeAppointment' => null,
])

@if($activeAppointment)
@php
    $sheetId = 'modal-appointment-result';
    $old = \App\Support\FormState::oldFor($sheetId);
    $decision = $old('first_decision');
    $choice = $old('attended_choice');
    $decisions = [
        'attended' => __('notify.client_workspace.outcomes.attended'),
        'no_show' => __('notify.client_workspace.outcomes.no_show'),
        'reschedule' => __('notify.client_workspace.outcomes.reschedule'),
        'cancelled' => __('notify.client_workspace.outcomes.cancelled'),
    ];
    $choices = [
        'installation' => __('notify.client_workspace.outcomes.schedule_installation'),
        'follow_up' => __('notify.client_workspace.outcomes.follow_up'),
        'start_subscription' => __('notify.client_workspace.outcomes.start_subscription'),
        'not_interested' => __('notify.client_workspace.outcomes.not_interested'),
    ];
    $when = trim(($activeAppointment->appointment_date?->toDateString() ?? '').' · '.substr((string) $activeAppointment->appointment_time, 0, 5), ' ·');
@endphp

@formscope($sheetId)
<x-notify.sheet :id="$sheetId" :title="__('notify.client_workspace.record_result')" :subtitle="$client->business_name.' · '.$when"
    :action="route('appointments.compact-outcome.store', $activeAppointment->id)" :form-attributes="['data-appointment-result-form' => true]">

    <x-notify.form-field :label="__('notify.client_workspace.decision')" name="first_decision" :required="true" :group="true">
        <div class="notify-choices">
            @foreach($decisions as $value => $label)
                <label class="notify-choice-chip">
                    <input type="radio" name="first_decision" value="{{ $value }}" @checked($decision === $value) @if($loop->first) required @endif>
                    <span>{{ $label }}</span>
                </label>
            @endforeach
        </div>
    </x-notify.form-field>

    {{-- Attended: choose the next step --}}
    <div class="notify-sheet__section notify-conditional-fields" data-for-decision="attended" hidden>
        <x-notify.form-field :label="__('notify.client_workspace.attended_next_step')" name="attended_choice" :required="true" :group="true">
            <div class="notify-choices">
                @foreach($choices as $value => $label)
                    <label class="notify-choice-chip">
                        <input type="radio" name="attended_choice" value="{{ $value }}" @checked($choice === $value)>
                        <span>{{ $label }}</span>
                    </label>
                @endforeach
            </div>
        </x-notify.form-field>

        <div class="notify-form-row" data-for-attended-choice="installation" hidden>
            <x-notify.form-field :label="__('notify.client_workspace.fields.installation_date')" for="result-installation-date" name="installation_date" :required="true">
                <input id="result-installation-date" class="notify-input" type="date" name="installation_date" value="{{ $old('installation_date', now()->addDay()->toDateString()) }}" @invalid('installation_date', 'result-installation-date')>
            </x-notify.form-field>
            <x-notify.form-field :label="__('notify.client_workspace.fields.installation_time')" for="result-installation-time" name="installation_time" :required="true">
                <input id="result-installation-time" class="notify-input" type="time" name="installation_time" value="{{ $old('installation_time', '11:00') }}" @invalid('installation_time', 'result-installation-time')>
            </x-notify.form-field>
        </div>

        <div data-for-attended-choice="follow_up" hidden>
            <x-notify.form-field :label="__('notify.client_workspace.fields.follow_up_date')" for="result-follow-up-date" name="follow_up_date" :required="true">
                <input id="result-follow-up-date" class="notify-input" type="date" name="follow_up_date" value="{{ $old('follow_up_date', now()->addDays(3)->toDateString()) }}" @invalid('follow_up_date', 'result-follow-up-date')>
            </x-notify.form-field>
        </div>

        <div class="notify-sheet__section" data-for-attended-choice="not_interested" hidden>
            <p class="notify-form-note">
                <strong>{{ __('notify.client_workspace.review_notice_title') }}</strong>: {{ __('notify.client_workspace.review_notice_desc') }}
            </p>
            <x-notify.form-field :label="__('notify.client_workspace.fields.reason')" for="result-reason" name="reason" :required="true">
                <input id="result-reason" class="notify-input" type="text" name="reason" value="{{ $old('reason') }}" placeholder="{{ __('notify.client_workspace.fields.not_interested_reason_placeholder') }}" @invalid('reason', 'result-reason')>
            </x-notify.form-field>
        </div>
    </div>

    {{-- Reschedule --}}
    <div class="notify-form-row notify-conditional-fields" data-for-decision="reschedule" hidden>
        <x-notify.form-field :label="__('notify.client_workspace.fields.reschedule_date')" for="result-reschedule-date" name="reschedule_date" :required="true">
            <input id="result-reschedule-date" class="notify-input" type="date" name="reschedule_date" value="{{ $old('reschedule_date', now()->addDay()->toDateString()) }}" @invalid('reschedule_date', 'result-reschedule-date')>
        </x-notify.form-field>
        <x-notify.form-field :label="__('notify.client_workspace.fields.reschedule_time')" for="result-reschedule-time" name="reschedule_time" :required="true">
            <input id="result-reschedule-time" class="notify-input" type="time" name="reschedule_time" value="{{ $old('reschedule_time', '11:00') }}" @invalid('reschedule_time', 'result-reschedule-time')>
        </x-notify.form-field>
    </div>

    <x-notify.form-field :label="__('notify.client_workspace.fields.notes')" for="result-note" name="note" :optional="true">
        <textarea id="result-note" class="notify-input" name="note" rows="2" placeholder="{{ __('notify.client_workspace.fields.notes_placeholder') }}" @invalid('note', 'result-note')>{{ $old('note') }}</textarea>
    </x-notify.form-field>

    <x-slot:footer>
        <button type="button" class="notify-button notify-button--ghost" data-sheet-close>{{ __('notify.client_workspace.cancel') }}</button>
        <button type="submit" class="notify-button notify-button--primary">{{ __('notify.client_workspace.save_result') }}</button>
    </x-slot:footer>
</x-notify.sheet>
@endformscope
@endif
