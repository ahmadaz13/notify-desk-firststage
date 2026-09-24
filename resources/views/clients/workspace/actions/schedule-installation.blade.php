@props([
    'client',
    'teamUsers' => collect(),
])

@php
    $sheetId = 'modal-schedule-installation';
    $old = \App\Support\FormState::oldFor($sheetId);
    $attendee = (int) (((array) $old('attendees', [auth()->id()]))[0] ?? 0);
@endphp

@formscope($sheetId)
<x-notify.sheet :id="$sheetId" :title="__('notify.client_workspace.action_schedule_installation')" :subtitle="collect([$client->business_name, $client->city_area])->filter()->join(' · ')"
    :action="route('clients.installations.schedule', $client->id)" :form-attributes="['data-schedule-installation-form' => true]">

    <div class="notify-form-row">
        <x-notify.form-field :label="__('notify.client_workspace.fields.date')" for="installation-schedule-date" name="appointment_date" :required="true">
            <input id="installation-schedule-date" class="notify-input" type="date" name="appointment_date" required value="{{ $old('appointment_date', now()->toDateString()) }}" @invalid('appointment_date', 'installation-schedule-date')>
        </x-notify.form-field>
        <x-notify.form-field :label="__('notify.client_workspace.fields.time')" for="installation-schedule-time" name="appointment_time" :required="true">
            <input id="installation-schedule-time" class="notify-input" type="time" name="appointment_time" required value="{{ $old('appointment_time', ($operations ?? \App\Support\OperationalSettings::current())->workdayStart()) }}" @invalid('appointment_time', 'installation-schedule-time')>
        </x-notify.form-field>
    </div>

    <x-notify.form-field :label="__('notify.client_workspace.fields.assigned_staff')" for="installation-schedule-staff" name="attendees">
        <select id="installation-schedule-staff" class="notify-input" name="attendees[]" @invalid('attendees', 'installation-schedule-staff')>
            <option value="">{{ __('notify.client_workspace.fields.assigned_staff') }}</option>
            @foreach($teamUsers as $user)
                <option value="{{ $user->id }}" @selected($user->id === $attendee)>{{ $user->name }}</option>
            @endforeach
        </select>
    </x-notify.form-field>

    <x-notify.form-field :label="__('notify.client_workspace.fields.location')" for="installation-schedule-location" name="location" :optional="true">
        <input id="installation-schedule-location" class="notify-input" type="text" name="location" value="{{ $old('location', $client->city_area ?: $client->city) }}" @invalid('location', 'installation-schedule-location')>
    </x-notify.form-field>

    <x-notify.form-field :label="__('notify.client_workspace.fields.branch')" for="installation-schedule-branch" name="branch_name" :optional="true">
        <input id="installation-schedule-branch" class="notify-input" type="text" name="branch_name" value="{{ $old('branch_name') }}" placeholder="{{ __('notify.client_workspace.fields.branch_placeholder') }}" @invalid('branch_name', 'installation-schedule-branch')>
    </x-notify.form-field>

    <x-notify.form-field :label="__('notify.client_workspace.fields.notes')" for="installation-schedule-notes" name="notes" :optional="true">
        <textarea id="installation-schedule-notes" class="notify-input" name="notes" rows="2" placeholder="{{ __('notify.client_workspace.fields.notes_placeholder') }}" @invalid('notes', 'installation-schedule-notes')>{{ $old('notes') }}</textarea>
    </x-notify.form-field>

    <x-slot:footer>
        <button type="button" class="notify-button notify-button--ghost" data-sheet-close>{{ __('notify.client_workspace.cancel') }}</button>
        <button type="submit" class="notify-button notify-button--primary">{{ __('notify.client_workspace.action_schedule_installation') }}</button>
    </x-slot:footer>
</x-notify.sheet>
@endformscope
