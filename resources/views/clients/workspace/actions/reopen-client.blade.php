@props([
    'client',
])

<div class="notify-modal-backdrop" id="modal-reopen-client" hidden>
    <div class="notify-modal-card notify-action-sheet" role="dialog" aria-modal="true" aria-labelledby="modal-reopen-client-title">
        <div class="notify-modal-header">
            <div>
                <h3 id="modal-reopen-client-title">{{ __('notify.client_workspace.action_reopen') }}</h3>
                <small style="color:var(--nd-muted)">{{ $client->business_name }}</small>
            </div>
            <button type="button" class="notify-icon-button" data-close-action-modal aria-label="{{ __('notify.client_workspace.cancel') }}">×</button>
        </div>

        <form method="POST" action="{{ route('clients.reopen', $client->id) }}" class="notify-action-form" data-reopen-client-form>
            @csrf

            <div class="notify-form-group notify-form-group--full">
                <label class="notify-field-label"><strong>{{ __('notify.client_workspace.fields.reason') }} *</strong></label>
                <textarea name="reason" class="notify-form-textarea" required rows="2" placeholder="{{ __('notify.client_workspace.reopen_notes_placeholder') ?? 'سبب إعادة فتح الملف...' }}"></textarea>
            </div>

            <div class="notify-form-group notify-form-group--full" style="margin-top:10px">
                <label class="notify-field-label"><strong>{{ __('notify.client_workspace.fields.target_stage') ?? 'المرحلة المستهدفة' }}</strong></label>
                <select name="stage" class="notify-form-select">
                    <option value="prospect">{{ __('notify.lifecycle.prospect') }}</option>
                    <option value="contacting">{{ __('notify.lifecycle.contacting') }}</option>
                    <option value="appointment">{{ __('notify.lifecycle.appointment') }}</option>
                    <option value="decision_pending">{{ __('notify.lifecycle.decision_pending') }}</option>
                </select>
            </div>

            <div class="notify-modal-footer">
                <button type="button" class="notify-button notify-button--soft" data-close-action-modal>{{ __('notify.client_workspace.cancel') }}</button>
                <button type="submit" class="notify-button notify-button--primary">{{ __('notify.client_workspace.action_reopen') }}</button>
            </div>
        </form>
    </div>
</div>
