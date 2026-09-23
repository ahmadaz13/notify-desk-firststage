@props([
    'client',
    'teamUsers' => collect(),
    'appointmentTypeLabels' => [],
])

<div class="notify-modal-backdrop" id="modal-record-call" hidden>
    <div class="notify-modal-card notify-action-sheet" role="dialog" aria-modal="true" aria-labelledby="modal-record-call-title">
        <div class="notify-modal-header">
            <div>
                <span class="notify-eyebrow">DAILY_CALL_01</span>
                <h3 id="modal-record-call-title">{{ __('notify.client_workspace.record_call') }}</h3>
            </div>
            <button type="button" class="notify-icon-button" data-close-action-modal aria-label="{{ __('notify.client_workspace.cancel') }}">×</button>
        </div>

        <form method="POST" action="{{ route('clients.contact-attempts.store', $client->id) }}" class="notify-action-form" data-call-outcome-form>
            @csrf
            <input type="hidden" name="method" value="phone">

            <div class="notify-form-section">
                <label class="notify-field-label"><strong>{{ __('notify.client_workspace.what_happened') }} *</strong></label>
                <div class="notify-outcome-grid" data-outcome-selector>
                    <label class="notify-outcome-chip">
                        <input type="radio" name="result" value="no_answer_busy" required>
                        <span>{{ __('notify.client_workspace.outcomes.no_answer') }}</span>
                    </label>
                    <label class="notify-outcome-chip">
                        <input type="radio" name="result" value="answered">
                        <span>{{ __('notify.client_workspace.outcomes.interested') }}</span>
                    </label>
                    <label class="notify-outcome-chip">
                        <input type="radio" name="result" value="appointment">
                        <span>{{ __('notify.client_workspace.outcomes.appointment') }}</span>
                    </label>
                    <label class="notify-outcome-chip">
                        <input type="radio" name="result" value="callback_later">
                        <span>{{ __('notify.client_workspace.outcomes.call_later') }}</span>
                    </label>
                    <label class="notify-outcome-chip">
                        <input type="radio" name="result" value="not_interested">
                        <span>{{ __('notify.client_workspace.outcomes.not_interested') }}</span>
                    </label>
                </div>

                <details class="notify-more-outcomes" style="margin-top:8px">
                    <summary style="font-size:12px;color:var(--nd-muted);cursor:pointer">{{ __('notify.client_workspace.more_options') }}</summary>
                    <div style="margin-top:6px">
                        <label class="notify-outcome-chip">
                            <input type="radio" name="result" value="wrong_invalid">
                            <span>{{ __('notify.client_workspace.outcomes.wrong_invalid') }}</span>
                        </label>
                    </div>
                </details>
            </div>

            {{-- Conditional: Appointment --}}
            <div class="notify-conditional-fields" data-for-outcome="appointment" hidden>
                <div class="notify-form-grid">
                    <div class="notify-form-group">
                        <label>{{ __('notify.client_workspace.fields.date') }} *</label>
                        <input type="date" name="appointment_date" value="{{ now()->addDay()->toDateString() }}">
                    </div>
                    <div class="notify-form-group">
                        <label>{{ __('notify.client_workspace.fields.time') }} *</label>
                        <input type="time" name="appointment_time" value="11:00">
                    </div>
                    <div class="notify-form-group notify-form-group--full">
                        <label>{{ __('notify.client_workspace.fields.type') }}</label>
                        <select name="appointment_type">
                            @foreach($appointmentTypeLabels as $typeKey => $typeLabel)
                                <option value="{{ $typeKey }}">{{ $typeLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <details class="notify-more-options" style="margin-top:8px">
                    <summary style="font-size:12px;cursor:pointer;color:var(--nd-muted)">{{ __('notify.client_workspace.more_options') }}</summary>
                    <div class="notify-form-grid" style="margin-top:8px">
                        <div class="notify-form-group">
                            <label>{{ __('notify.client_workspace.fields.location') }}</label>
                            <input type="text" name="location" value="{{ $client->location_text }}">
                        </div>
                        <div class="notify-form-group">
                            <label>{{ __('notify.client_workspace.fields.branch') }}</label>
                            <input type="text" name="branch_name" placeholder="{{ __('notify.client_workspace.fields.branch_placeholder') }}">
                        </div>
                        <div class="notify-form-group notify-form-group--full">
                            <label>{{ __('notify.client_workspace.fields.notes') }}</label>
                            <textarea name="appointment_notes" rows="2"></textarea>
                        </div>
                    </div>
                </details>
            </div>

            {{-- Conditional: Call Later --}}
            <div class="notify-conditional-fields" data-for-outcome="callback_later" hidden>
                <div class="notify-form-grid">
                    <div class="notify-form-group notify-form-group--full">
                        <label>{{ __('notify.client_workspace.fields.callback_datetime') }} *</label>
                        <input type="datetime-local" name="follow_up_date_time" value="{{ now()->addDay()->setTime(10, 0)->format('Y-m-d\\TH:i') }}">
                    </div>
                </div>
            </div>

            {{-- Conditional: Not Interested / Wrong Invalid Notice --}}
            <div class="notify-conditional-fields notify-review-notice" data-for-outcome="not_interested" hidden>
                <p><strong>{{ __('notify.client_workspace.review_notice_title') }}</strong>: {{ __('notify.client_workspace.review_notice_desc') }}</p>
            </div>

            <div class="notify-conditional-fields notify-review-notice" data-for-outcome="wrong_invalid" hidden>
                <p><strong>{{ __('notify.client_workspace.wrong_notice_title') }}</strong>: {{ __('notify.client_workspace.wrong_notice_desc') }}</p>
            </div>

            {{-- Optional or Required Note --}}
            <div class="notify-form-group notify-form-group--full" data-note-container hidden>
                <label>
                    <span data-note-label>{{ __('notify.client_workspace.fields.notes') }}</span>
                    <span data-note-required style="color:var(--nd-danger)" hidden>*</span>
                </label>
                <textarea name="note" rows="2" placeholder="{{ __('notify.client_workspace.fields.notes_placeholder') }}"></textarea>
            </div>

            <div class="notify-modal-footer">
                <button type="button" class="notify-button notify-button--soft" data-close-action-modal>{{ __('notify.client_workspace.cancel') }}</button>
                <button type="submit" class="notify-button notify-button--primary">{{ __('notify.client_workspace.save_result') }}</button>
            </div>
        </form>
    </div>
</div>
