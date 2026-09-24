@props([
    'client',
])

<div class="notify-modal-backdrop" id="modal-close-client" hidden>
    <div class="notify-modal-card notify-action-sheet" role="dialog" aria-modal="true" aria-labelledby="modal-close-client-title">
        <div class="notify-modal-header">
            <div>
                <h3 id="modal-close-client-title">{{ __('notify.client_workspace.close_client') }}</h3>
            </div>
            <button type="button" class="notify-icon-button" data-close-action-modal aria-label="{{ __('notify.client_workspace.cancel') }}">×</button>
        </div>

        <form method="POST" action="{{ route('clients.close', $client->id) }}" class="notify-action-form" data-close-client-form>
            @csrf

            <div class="notify-review-notice" style="background:#fef2f2;border-color:#fecaca;color:#991b1b">
                <p><strong>{{ __('notify.client_workspace.close_notice_title') }}</strong>: {{ __('notify.client_workspace.close_notice_desc') }}</p>
            </div>

            <div class="notify-form-group notify-form-group--full" style="margin-top:12px">
                <label>{{ __('notify.client_workspace.fields.close_reason_code') }} *</label>
                <select name="closed_reason_code" required data-close-reason-select>
                    <option value="">-- {{ __('notify.client_workspace.fields.select_reason') }} --</option>
                    <option value="price">{{ __('notify.client_workspace.close_reasons.price') }}</option>
                    <option value="not_needed">{{ __('notify.client_workspace.close_reasons.not_needed') }}</option>
                    <option value="not_ready">{{ __('notify.client_workspace.close_reasons.not_ready') }}</option>
                    <option value="competitor">{{ __('notify.client_workspace.close_reasons.competitor') }}</option>
                    <option value="no_response">{{ __('notify.client_workspace.close_reasons.no_response') }}</option>
                    <option value="business_closed">{{ __('notify.client_workspace.close_reasons.business_closed') }}</option>
                    <option value="invalid_contact">{{ __('notify.client_workspace.close_reasons.invalid_contact') }}</option>
                    <option value="not_interested">{{ __('notify.client_workspace.close_reasons.not_interested') }}</option>
                    <option value="other">{{ __('notify.client_workspace.close_reasons.other') }}</option>
                </select>
            </div>

            <div class="notify-form-group notify-form-group--full" style="margin-top:10px">
                <label>
                    <span>{{ __('notify.client_workspace.fields.notes') }}</span>
                    <span data-close-note-required style="color:var(--nd-danger)" hidden>*</span>
                </label>
                <textarea name="closed_reason" rows="2" placeholder="{{ __('notify.client_workspace.fields.close_notes_placeholder') }}"></textarea>
            </div>

            <div class="notify-modal-footer">
                <button type="button" class="notify-button notify-button--soft" data-close-action-modal>{{ __('notify.client_workspace.cancel') }}</button>
                <button type="submit" class="notify-button notify-button--danger">{{ __('notify.client_workspace.confirm_close') }}</button>
            </div>
        </form>
    </div>
</div>
