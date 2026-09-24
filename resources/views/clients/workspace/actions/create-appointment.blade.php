@props([
    'client',
    'teamUsers' => collect(),
    'appointmentTypeLabels' => [],
])

@php
    $sheetId = 'modal-create-appointment';
    $old = \App\Support\FormState::oldFor($sheetId);
    $attendees = array_map('intval', (array) $old('attendees', [auth()->id()]));
    $moreOpen = \App\Support\FormState::active($sheetId) && $errors->hasAny(['location', 'branch_name', 'attendees', 'notes']);
@endphp

@formscope($sheetId)
<x-notify.sheet :id="$sheetId" :title="__('notify.client_workspace.create_appointment')" :subtitle="$client->business_name"
    :action="route('appointments.store')">
    <input type="hidden" name="client_id" value="{{ $client->id }}">

    <div class="notify-form-row">
        <x-notify.form-field :label="__('notify.client_workspace.fields.date')" for="appointment-date" name="appointment_date" :required="true">
            <input id="appointment-date" class="notify-input" type="date" name="appointment_date" value="{{ $old('appointment_date', now()->addDay()->toDateString()) }}" required @invalid('appointment_date', 'appointment-date')>
        </x-notify.form-field>
        <x-notify.form-field :label="__('notify.client_workspace.fields.time')" for="appointment-time" name="appointment_time" :required="true">
            <input id="appointment-time" class="notify-input" type="time" name="appointment_time" value="{{ $old('appointment_time', '11:00') }}" required @invalid('appointment_time', 'appointment-time')>
        </x-notify.form-field>
        <x-notify.form-field :label="__('notify.client_workspace.fields.type')" for="appointment-type" name="appointment_type" :required="true" class="notify-form-row__full">
            <select id="appointment-type" class="notify-input" name="appointment_type" required @invalid('appointment_type', 'appointment-type')>
                @foreach($appointmentTypeLabels as $typeKey => $typeLabel)
                    <option value="{{ $typeKey }}" @selected($old('appointment_type') === $typeKey)>{{ $typeLabel }}</option>
                @endforeach
            </select>
        </x-notify.form-field>
    </div>

    <details class="notify-sheet__more notify-more-options" @if($moreOpen) open @endif>
        <summary>{{ __('notify.client_workspace.more_options') }}</summary>
        <div class="notify-form-row">
            <x-notify.form-field :label="__('notify.client_workspace.fields.location')" for="appointment-location" name="location" :optional="true">
                <input id="appointment-location" class="notify-input" type="text" name="location" value="{{ $old('location', $client->location_text) }}" @invalid('location', 'appointment-location')>
            </x-notify.form-field>
            <x-notify.form-field :label="__('notify.client_workspace.fields.branch')" for="appointment-branch" name="branch_name" :optional="true">
                <input id="appointment-branch" class="notify-input" type="text" name="branch_name" value="{{ $old('branch_name') }}" placeholder="{{ __('notify.client_workspace.fields.branch_placeholder') }}" @invalid('branch_name', 'appointment-branch')>
            </x-notify.form-field>
            <x-notify.form-field :label="__('notify.client_workspace.fields.assigned_staff')" name="attendees" :group="true" class="notify-form-row__full">
                <div class="notify-choices">
                    @foreach($teamUsers as $user)
                        <label class="notify-choice-chip">
                            <input type="checkbox" name="attendees[]" value="{{ $user->id }}" @checked(in_array($user->id, $attendees, true))>
                            <span>{{ $user->name }}</span>
                        </label>
                    @endforeach
                </div>
            </x-notify.form-field>
            <x-notify.form-field :label="__('notify.client_workspace.fields.notes')" for="appointment-notes" name="notes" :optional="true" class="notify-form-row__full">
                <textarea id="appointment-notes" class="notify-input" name="notes" rows="2" placeholder="{{ __('notify.client_workspace.fields.notes_placeholder') }}" @invalid('notes', 'appointment-notes')>{{ $old('notes') }}</textarea>
            </x-notify.form-field>
        </div>
    </details>

    <x-slot:footer>
        <button type="button" class="notify-button notify-button--ghost" data-sheet-close>{{ __('notify.client_workspace.cancel') }}</button>
        <button type="submit" class="notify-button notify-button--primary">{{ __('notify.client_workspace.schedule') }}</button>
    </x-slot:footer>
</x-notify.sheet>
@endformscope
