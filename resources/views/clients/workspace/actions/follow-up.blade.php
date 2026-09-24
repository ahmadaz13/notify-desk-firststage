@props([
    'client',
    'activeFollowUp' => null,
    'appointmentTypeLabels' => [],
])

@if($activeFollowUp)
<div class="notify-modal-backdrop" id="modal-follow-up" hidden>
    <div class="notify-modal-card notify-action-sheet" role="dialog" aria-modal="true" aria-labelledby="modal-follow-up-title">
        <div class="notify-modal-header">
            <div>
                <h3 id="modal-follow-up-title">{{ __('notify.client_workspace.record_follow_up') }}</h3>
                <small style="color:var(--nd-muted)">{{ $activeFollowUp->reason }} · {{ $activeFollowUp->follow_up_date_time }}</small>
            </div>
            <button type="button" class="notify-icon-button" data-close-action-modal aria-label="{{ __('notify.client_workspace.cancel') }}">×</button>
        </div>

        <form method="POST" action="{{ route('follow-ups.complete', $activeFollowUp->id) }}" class="notify-action-form" data-followup-outcome-form>
            @csrf

            <div class="notify-form-section">
                <label class="notify-field-label"><strong>{{ __('notify.client_workspace.what_happened') }} *</strong></label>
                <div class="notify-outcome-grid">
                    <label class="notify-outcome-chip">
                        <input type="radio" name="outcome" value="subscribe" required>
                        <span>{{ __('notify.client_workspace.outcomes.ready_to_subscribe') }}</span>
                    </label>
                    <label class="notify-outcome-chip">
                        <input type="radio" name="outcome" value="callback_later">
                        <span>{{ __('notify.client_workspace.outcomes.wait_call_later') }}</span>
                    </label>
                    <label class="notify-outcome-chip">
                        <input type="radio" name="outcome" value="appointment">
                        <span>{{ __('notify.client_workspace.outcomes.appointment') }}</span>
                    </label>
                    <label class="notify-outcome-chip">
                        <input type="radio" name="outcome" value="no_answer">
                        <span>{{ __('notify.client_workspace.outcomes.no_answer') }}</span>
                    </label>
                    <label class="notify-outcome-chip">
                        <input type="radio" name="outcome" value="not_interested">
                        <span>{{ __('notify.client_workspace.outcomes.not_interested') }}</span>
                    </label>
                </div>
            </div>

            {{-- Conditional: Callback Later inputs --}}
            <div class="notify-conditional-fields" data-for-followup-outcome="callback_later" hidden>
                <div class="notify-form-grid">
                    <div class="notify-form-group">
                        <label>{{ __('notify.client_workspace.fields.date') }} *</label>
                        <input type="date" name="next_follow_up_date" value="{{ now()->addDay()->toDateString() }}">
                    </div>
                    <div class="notify-form-group">
                        <label>{{ __('notify.client_workspace.fields.time') }} *</label>
                        <input type="time" name="next_follow_up_time" value="10:00">
                    </div>
                </div>
            </div>

            {{-- Conditional: Appointment inputs --}}
            <div class="notify-conditional-fields" data-for-followup-outcome="appointment" hidden>
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
            </div>

            {{-- Conditional: No Answer inputs (required callback date/time) --}}
            <div class="notify-conditional-fields" data-for-followup-outcome="no_answer" hidden>
                <div class="notify-form-grid">
                    <div class="notify-form-group notify-form-group--full">
                        <label>{{ __('notify.client_workspace.fields.callback_datetime') }} *</label>
                        <input type="datetime-local" name="follow_up_date_time" value="{{ now()->addDay()->setTime(10, 0)->format('Y-m-d\\TH:i') }}">
                    </div>
                </div>
            </div>

            {{-- Conditional: Not Interested inputs --}}
            <div class="notify-conditional-fields" data-for-followup-outcome="not_interested" hidden>
                <div class="notify-review-notice">
                    <p><strong>{{ __('notify.client_workspace.review_notice_title') }}</strong>: {{ __('notify.client_workspace.review_notice_desc') }}</p>
                </div>
                <div class="notify-form-group notify-form-group--full" style="margin-top:8px">
                    <label>{{ __('notify.client_workspace.fields.reason') }} *</label>
                    <input type="text" name="reason" placeholder="{{ __('notify.client_workspace.fields.not_interested_reason_placeholder') }}">
                </div>
            </div>

            {{-- General Note --}}
            <div class="notify-form-group notify-form-group--full" style="margin-top:10px">
                <label>{{ __('notify.client_workspace.fields.notes') }}</label>
                <textarea name="notes" rows="2" placeholder="{{ __('notify.client_workspace.fields.notes_placeholder') }}"></textarea>
            </div>

            <div class="notify-modal-footer">
                <button type="button" class="notify-button notify-button--soft" data-close-action-modal>{{ __('notify.client_workspace.cancel') }}</button>
                <button type="submit" class="notify-button notify-button--primary">{{ __('notify.client_workspace.save_result') }}</button>
            </div>
        </form>
    </div>
</div>
@endif
