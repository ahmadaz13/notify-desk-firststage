@props([
    'client',
    'catalogServices' => collect(),
    'eligibleInstallationAppointment' => null,
])

<div class="notify-modal-backdrop" id="modal-complete-installation" hidden>
    <div class="notify-modal-card notify-action-sheet" role="dialog" aria-modal="true" aria-labelledby="modal-complete-installation-title">
        <div class="notify-modal-header">
            <div>
                <h3 id="modal-complete-installation-title">{{ __('notify.client_workspace.complete_installation') }}</h3>
                @if($eligibleInstallationAppointment)
                    <small class="notify-sheet-hint" dir="ltr">{{ $eligibleInstallationAppointment->appointment_date?->toDateString() }} · {{ $eligibleInstallationAppointment->appointment_time }}</small>
                @endif
            </div>
            <button type="button" class="notify-icon-button" data-close-action-modal aria-label="{{ __('notify.client_workspace.cancel') }}">×</button>
        </div>

        <form method="POST" action="{{ route('clients.installations.complete', $client->id) }}" class="notify-action-form" data-complete-installation-form>
            @csrf
            @if($eligibleInstallationAppointment)
                <input type="hidden" name="appointment_id" value="{{ $eligibleInstallationAppointment->id }}">
            @endif

            <div class="notify-form-section">
                <label class="notify-field-label"><strong>{{ __('notify.client_workspace.installed_services') }} *</strong></label>
                <div class="notify-checkbox-grid">
                    @foreach($catalogServices as $service)
                        <label class="notify-checkbox-chip">
                            <input type="checkbox" name="service_ids[]" value="{{ $service->id }}">
                            <span>{{ $service->name_ar ?: $service->name_en }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="notify-form-group notify-form-group--full" style="margin-top:10px">
                <label>{{ __('notify.client_workspace.custom_items') }}</label>
                <textarea name="custom_item_names" rows="2" placeholder="{{ __('notify.client_workspace.custom_items_placeholder') }}"></textarea>
            </div>

            <div class="notify-form-group notify-form-group--full">
                <label>{{ __('notify.client_workspace.fields.notes') }}</label>
                <textarea name="notes" rows="2" placeholder="{{ __('notify.client_workspace.fields.notes_placeholder') }}"></textarea>
            </div>

            <div class="notify-modal-footer">
                <button type="button" class="notify-button notify-button--soft" data-close-action-modal>{{ __('notify.client_workspace.cancel') }}</button>
                <button type="submit" class="notify-button notify-button--primary">{{ __('notify.client_workspace.complete_installation') }}</button>
            </div>
        </form>
    </div>
</div>
