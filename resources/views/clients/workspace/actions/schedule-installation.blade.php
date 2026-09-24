@props([
    'client',
    'teamUsers' => collect(),
])

<div class="notify-modal-backdrop" id="modal-schedule-installation" hidden>
    <div class="notify-modal-card notify-action-sheet" role="dialog" aria-modal="true" aria-labelledby="modal-schedule-installation-title">
        <div class="notify-modal-header">
            <div>
                <h3 id="modal-schedule-installation-title">{{ __('notify.client_workspace.action_schedule_installation') }}</h3>
                <small style="color:var(--nd-muted)">{{ $client->business_name }} · {{ $client->city_area }}</small>
            </div>
            <button type="button" class="notify-icon-button" data-close-action-modal aria-label="{{ __('notify.client_workspace.cancel') }}">×</button>
        </div>

        <form method="POST" action="{{ route('clients.installations.schedule', $client->id) }}" class="notify-action-form" data-schedule-installation-form>
            @csrf

            <div class="notify-form-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                <div class="notify-form-group">
                    <label class="notify-field-label"><strong>{{ __('notify.client_workspace.fields.date') }} *</strong></label>
                    <input type="date" name="appointment_date" class="notify-form-input" required value="{{ date('Y-m-d') }}">
                </div>
                <div class="notify-form-group">
                    <label class="notify-field-label"><strong>{{ __('notify.client_workspace.fields.time') }} *</strong></label>
                    <input type="time" name="appointment_time" class="notify-form-input" required value="10:00">
                </div>
            </div>

            <div class="notify-form-group notify-form-group--full" style="margin-top:10px">
                <label class="notify-field-label">{{ __('notify.client_workspace.fields.assigned_staff') }}</label>
                <select name="attendees[]" class="notify-form-select">
                    <option value="">-- {{ __('notify.client_workspace.fields.assigned_staff') }} --</option>
                    @foreach($teamUsers as $user)
                        <option value="{{ $user->id }}" @if($user->id === auth()->id()) selected @endif>{{ $user->name }} ({{ $user->role ?: 'staff' }})</option>
                    @endforeach
                </select>
            </div>

            <div class="notify-form-group notify-form-group--full" style="margin-top:10px">
                <label class="notify-field-label">{{ __('notify.client_workspace.fields.location') }}</label>
                <input type="text" name="location" class="notify-form-input" value="{{ $client->city_area ?: $client->city }}" placeholder="{{ __('notify.client_workspace.fields.location') }}">
            </div>

            <div class="notify-form-group notify-form-group--full" style="margin-top:10px">
                <label class="notify-field-label">{{ __('notify.client_workspace.fields.branch') }}</label>
                <input type="text" name="branch_name" class="notify-form-input" placeholder="{{ __('notify.client_workspace.fields.branch_placeholder') }}">
            </div>

            <div class="notify-form-group notify-form-group--full" style="margin-top:10px">
                <label class="notify-field-label">{{ __('notify.client_workspace.fields.notes') }}</label>
                <textarea name="notes" class="notify-form-textarea" rows="2" placeholder="{{ __('notify.client_workspace.fields.notes_placeholder') }}"></textarea>
            </div>

            <div class="notify-modal-footer">
                <button type="button" class="notify-button notify-button--soft" data-close-action-modal>{{ __('notify.client_workspace.cancel') }}</button>
                <button type="submit" class="notify-button notify-button--primary">{{ __('notify.client_workspace.action_schedule_installation') }}</button>
            </div>
        </form>
    </div>
</div>
