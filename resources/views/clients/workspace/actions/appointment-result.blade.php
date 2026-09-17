@props([
    'client',
    'activeAppointment' => null,
])

@if($activeAppointment)
<div class="notify-modal-backdrop" id="modal-appointment-result" hidden>
    <div class="notify-modal-card notify-action-sheet" role="dialog" aria-modal="true" aria-labelledby="modal-appointment-result-title">
        <div class="notify-modal-header">
            <div>
                <span class="notify-eyebrow">DAILY_RESULT_01</span>
                <h3 id="modal-appointment-result-title">{{ __('notify.client_workspace.record_result') }}</h3>
                <small style="color:var(--nd-muted)">#{{ $activeAppointment->id }} · {{ $activeAppointment->appointment_date?->toDateString() }} · {{ $activeAppointment->appointment_time }}</small>
            </div>
            <button type="button" class="notify-icon-button" data-close-action-modal aria-label="{{ __('notify.client_workspace.cancel') }}">×</button>
        </div>

        <form method="POST" action="{{ route('appointments.compact-outcome.store', $activeAppointment->id) }}" class="notify-action-form" data-appointment-result-form>
            @csrf

            <div class="notify-form-section">
                <label class="notify-field-label"><strong>{{ __('notify.client_workspace.decision') }} *</strong></label>
                <div class="notify-outcome-grid">
                    <label class="notify-outcome-chip">
                        <input type="radio" name="first_decision" value="attended" required>
                        <span>{{ __('notify.client_workspace.outcomes.attended') }}</span>
                    </label>
                    <label class="notify-outcome-chip">
                        <input type="radio" name="first_decision" value="no_show">
                        <span>{{ __('notify.client_workspace.outcomes.no_show') }}</span>
                    </label>
                    <label class="notify-outcome-chip">
                        <input type="radio" name="first_decision" value="reschedule">
                        <span>{{ __('notify.client_workspace.outcomes.reschedule') }}</span>
                    </label>
                    <label class="notify-outcome-chip">
                        <input type="radio" name="first_decision" value="cancelled">
                        <span>{{ __('notify.client_workspace.outcomes.cancelled') }}</span>
                    </label>
                </div>
            </div>

            {{-- Conditional: Attended choices --}}
            <div class="notify-conditional-fields" data-for-decision="attended" hidden>
                <label class="notify-field-label"><strong>{{ __('notify.client_workspace.attended_next_step') }} *</strong></label>
                <div class="notify-outcome-grid">
                    <label class="notify-outcome-chip">
                        <input type="radio" name="attended_choice" value="installation">
                        <span>{{ __('notify.client_workspace.outcomes.schedule_installation') }}</span>
                    </label>
                    <label class="notify-outcome-chip">
                        <input type="radio" name="attended_choice" value="follow_up">
                        <span>{{ __('notify.client_workspace.outcomes.follow_up') }}</span>
                    </label>
                    <label class="notify-outcome-chip">
                        <input type="radio" name="attended_choice" value="start_subscription">
                        <span>{{ __('notify.client_workspace.outcomes.start_subscription') }}</span>
                    </label>
                    <label class="notify-outcome-chip">
                        <input type="radio" name="attended_choice" value="not_interested">
                        <span>{{ __('notify.client_workspace.outcomes.not_interested') }}</span>
                    </label>
                </div>

                {{-- Choice: Installation inputs --}}
                <div class="notify-form-grid" data-for-attended-choice="installation" style="margin-top:10px" hidden>
                    <div class="notify-form-group">
                        <label>{{ __('notify.client_workspace.fields.installation_date') }} *</label>
                        <input type="date" name="installation_date" value="{{ now()->addDay()->toDateString() }}">
                    </div>
                    <div class="notify-form-group">
                        <label>{{ __('notify.client_workspace.fields.installation_time') }} *</label>
                        <input type="time" name="installation_time" value="11:00">
                    </div>
                </div>

                {{-- Choice: Follow-up inputs --}}
                <div class="notify-form-grid" data-for-attended-choice="follow_up" style="margin-top:10px" hidden>
                    <div class="notify-form-group notify-form-group--full">
                        <label>{{ __('notify.client_workspace.fields.follow_up_date') }} *</label>
                        <input type="date" name="follow_up_date" value="{{ now()->addDays(3)->toDateString() }}">
                    </div>
                </div>

                {{-- Choice: Not Interested inputs --}}
                <div class="notify-form-group notify-form-group--full" data-for-attended-choice="not_interested" style="margin-top:10px" hidden>
                    <div class="notify-review-notice">
                        <p><strong>{{ __('notify.client_workspace.review_notice_title') }}</strong>: {{ __('notify.client_workspace.review_notice_desc') }}</p>
                    </div>
                    <label>{{ __('notify.client_workspace.fields.reason') }} *</label>
                    <input type="text" name="reason" placeholder="{{ __('notify.client_workspace.fields.not_interested_reason_placeholder') }}">
                </div>
            </div>

            {{-- Conditional: Reschedule inputs --}}
            <div class="notify-conditional-fields" data-for-decision="reschedule" hidden>
                <div class="notify-form-grid">
                    <div class="notify-form-group">
                        <label>{{ __('notify.client_workspace.fields.reschedule_date') }} *</label>
                        <input type="date" name="reschedule_date" value="{{ now()->addDay()->toDateString() }}">
                    </div>
                    <div class="notify-form-group">
                        <label>{{ __('notify.client_workspace.fields.reschedule_time') }} *</label>
                        <input type="time" name="reschedule_time" value="11:00">
                    </div>
                </div>
            </div>

            {{-- General Note --}}
            <div class="notify-form-group notify-form-group--full" style="margin-top:10px">
                <label>{{ __('notify.client_workspace.fields.notes') }}</label>
                <textarea name="note" rows="2" placeholder="{{ __('notify.client_workspace.fields.notes_placeholder') }}"></textarea>
            </div>

            <div class="notify-modal-footer">
                <button type="button" class="notify-button notify-button--soft" data-close-action-modal>{{ __('notify.client_workspace.cancel') }}</button>
                <button type="submit" class="notify-button notify-button--primary">{{ __('notify.client_workspace.save_result') }}</button>
            </div>
        </form>
    </div>
</div>
@endif
