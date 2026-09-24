@props([
    'client',
    'catalogServices' => collect(),
    'eligibleInstallationAppointment' => null,
])

@php
    $sheetId = 'modal-complete-installation';
    $old = \App\Support\FormState::oldFor($sheetId);
    $selected = array_map('intval', (array) $old('service_ids', []));
    $when = $eligibleInstallationAppointment
        ? trim(($eligibleInstallationAppointment->appointment_date?->toDateString() ?? '').' · '.substr((string) $eligibleInstallationAppointment->appointment_time, 0, 5), ' ·')
        : null;
@endphp

@formscope($sheetId)
<x-notify.sheet :id="$sheetId" :title="__('notify.client_workspace.complete_installation')" :subtitle="collect([$client->business_name, $when])->filter()->join(' · ')"
    :action="route('clients.installations.complete', $client->id)" :form-attributes="['data-complete-installation-form' => true]">
    @if($eligibleInstallationAppointment)
        <input type="hidden" name="appointment_id" value="{{ $eligibleInstallationAppointment->id }}">
    @endif

    <x-notify.form-field :label="__('notify.client_workspace.installed_services')" name="service_ids" :group="true">
        <div class="notify-choices notify-choices--stack">
            @foreach($catalogServices as $service)
                <label class="notify-choice-chip">
                    <input type="checkbox" name="service_ids[]" value="{{ $service->id }}" @checked(in_array($service->id, $selected, true))>
                    <span>{{ $service->name_ar ?: $service->name_en }}</span>
                </label>
            @endforeach
        </div>
    </x-notify.form-field>

    <x-notify.form-field :label="__('notify.client_workspace.custom_items')" for="installation-custom-items" name="custom_item_names" :optional="true">
        <textarea id="installation-custom-items" class="notify-input" name="custom_item_names" rows="2" placeholder="{{ __('notify.client_workspace.custom_items_placeholder') }}" @invalid('custom_item_names', 'installation-custom-items')>{{ $old('custom_item_names') }}</textarea>
    </x-notify.form-field>

    <x-notify.form-field :label="__('notify.client_workspace.fields.notes')" for="installation-notes" name="notes" :optional="true">
        <textarea id="installation-notes" class="notify-input" name="notes" rows="2" placeholder="{{ __('notify.client_workspace.fields.notes_placeholder') }}" @invalid('notes', 'installation-notes')>{{ $old('notes') }}</textarea>
    </x-notify.form-field>

    <x-slot:footer>
        <button type="button" class="notify-button notify-button--ghost" data-sheet-close>{{ __('notify.client_workspace.cancel') }}</button>
        <button type="submit" class="notify-button notify-button--primary">{{ __('notify.client_workspace.complete_installation') }}</button>
    </x-slot:footer>
</x-notify.sheet>
@endformscope
