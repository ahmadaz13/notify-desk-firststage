@props([
    'client',
    'teamUsers' => collect(),
    'appointmentTypeLabels' => [],
])

<div class="notify-modal-backdrop" id="modal-create-appointment" hidden>
    <div class="notify-modal-card notify-action-sheet" role="dialog" aria-modal="true" aria-labelledby="modal-create-appointment-title">
        <div class="notify-modal-header">
            <div>
                <span class="notify-eyebrow">DAILY_APT_01</span>
                <h3 id="modal-create-appointment-title">{{ __('notify.client_workspace.create_appointment') }}</h3>
            </div>
            <button type="button" class="notify-icon-button" data-close-action-modal aria-label="{{ __('notify.client_workspace.cancel') }}">×</button>
        </div>

        <form method="POST" action="{{ route('appointments.store') }}" class="notify-action-form">
            @csrf
            <input type="hidden" name="client_id" value="{{ $client->id }}">

            {{-- 3 Normal Fields --}}
            <div class="notify-form-grid">
                <div class="notify-form-group">
                    <label>{{ __('notify.client_workspace.fields.date') }} *</label>
                    <input type="date" name="appointment_date" value="{{ now()->addDay()->toDateString() }}" required>
                </div>
                <div class="notify-form-group">
                    <label>{{ __('notify.client_workspace.fields.time') }} *</label>
                    <input type="time" name="appointment_time" value="11:00" required>
                </div>
                <div class="notify-form-group notify-form-group--full">
                    <label>{{ __('notify.client_workspace.fields.type') }} *</label>
                    <select name="appointment_type" required>
                        @foreach($appointmentTypeLabels as $typeKey => $typeLabel)
                            <option value="{{ $typeKey }}">{{ $typeLabel }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Collapsible More Options --}}
            <details class="notify-more-options" style="margin-top:12px">
                <summary style="font-size:13px;cursor:pointer;color:var(--nd-primary);font-weight:600">{{ __('notify.client_workspace.more_options') }}</summary>
                <div class="notify-form-grid" style="margin-top:10px">
                    <div class="notify-form-group">
                        <label>{{ __('notify.client_workspace.fields.location') }}</label>
                        <input type="text" name="location" value="{{ $client->location_text }}">
                    </div>
                    <div class="notify-form-group">
                        <label>{{ __('notify.client_workspace.fields.branch') }}</label>
                        <input type="text" name="branch_name" placeholder="{{ __('notify.client_workspace.fields.branch_placeholder') }}">
                    </div>
                    <div class="notify-form-group notify-form-group--full">
                        <label>{{ __('notify.client_workspace.fields.assigned_staff') }}</label>
                        <div class="notify-checkbox-grid">
                            @foreach($teamUsers as $user)
                                <label class="notify-checkbox-chip">
                                    <input type="checkbox" name="attendees[]" value="{{ $user->id }}" @checked($user->id === auth()->id())>
                                    <span>{{ $user->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <div class="notify-form-group notify-form-group--full">
                        <label>{{ __('notify.client_workspace.fields.notes') }}</label>
                        <textarea name="notes" rows="2" placeholder="{{ __('notify.client_workspace.fields.notes_placeholder') }}"></textarea>
                    </div>
                </div>
            </details>

            <div class="notify-modal-footer">
                <button type="button" class="notify-button notify-button--soft" data-close-action-modal>{{ __('notify.client_workspace.cancel') }}</button>
                <button type="submit" class="notify-button notify-button--primary">{{ __('notify.client_workspace.schedule') }}</button>
            </div>
        </form>
    </div>
</div>
