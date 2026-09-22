@props(['client', 'sellableProducts' => collect()])

@if(\App\Support\FinancialPermissions::allows(auth()->user(), \App\Support\FinancialPermissions::MANAGE_SUBSCRIPTION_BILLING))
<div class="notify-modal-backdrop" id="modal-start-subscription" hidden>
    <div class="notify-modal-card notify-action-sheet" role="dialog" aria-modal="true" aria-labelledby="modal-start-subscription-title" style="max-width:680px">
        <div class="notify-modal-header">
            <div>
                <h3 id="modal-start-subscription-title">{{ __('notify.subscriptions.convert_to_subscriber') }}</h3>
                <p>{{ __('notify.subscriptions.simple_terms_help') }}</p>
            </div>
            <button type="button" class="notify-icon-button" data-close-action-modal aria-label="{{ __('notify.actions.cancel') }}">×</button>
        </div>

        <form method="POST" action="{{ route('clients.guided-subscription.store', $client) }}" class="notify-action-form" x-data="{ cycle: @js(old('billing_interval', 'monthly')), terms: @js(old('payment_terms', 'full')) }">
            @csrf
            <input type="hidden" name="_idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">

            <fieldset class="notify-form-group">
                <legend class="notify-field-label"><strong>{{ __('notify.subscriptions.selected_systems') }}</strong></legend>
                @foreach($sellableProducts as $system)
                    <label style="display:flex;gap:8px;align-items:center;margin:8px 0">
                        <input type="checkbox" name="system_ids[]" value="{{ $system->id }}" @checked(in_array($system->id, old('system_ids', [])))>
                        <span>{{ $system->name_ar }}@if($system->name_en) · {{ $system->name_en }}@endif</span>
                    </label>
                @endforeach
                @error('system_ids') <span class="error">{{ $message }}</span> @enderror
            </fieldset>

            <div class="notify-form-grid">
                <div class="notify-form-group">
                    <label for="subscription-cycle">{{ __('notify.subscriptions.billing_cycle') }}</label>
                    <select id="subscription-cycle" name="billing_interval" x-model="cycle" required class="notify-form-select">
                        <option value="monthly">{{ __('notify.subscriptions.monthly') }}</option>
                        <option value="annual">{{ __('notify.subscriptions.annual') }}</option>
                    </select>
                </div>
                <div class="notify-form-group">
                    <label for="agreed-value">{{ __('notify.subscriptions.agreed_value_jod') }}</label>
                    <input id="agreed-value" name="agreed_value_jod" value="{{ old('agreed_value_jod') }}" inputmode="decimal" placeholder="0.000" required class="notify-form-input" dir="ltr">
                    @error('agreed_value_jod') <span class="error">{{ $message }}</span> @enderror
                </div>
                <div class="notify-form-group">
                    <label for="subscription-start">{{ __('notify.subscriptions.start_date') }}</label>
                    <input id="subscription-start" type="date" name="start_date" value="{{ old('start_date', now('Asia/Amman')->toDateString()) }}" class="notify-form-input">
                </div>
            </div>

            <div x-show="cycle === 'annual'" class="notify-form-grid" style="margin-top:12px">
                <div class="notify-form-group">
                    <label for="payment-terms">{{ __('notify.subscriptions.payment_terms') }}</label>
                    <select id="payment-terms" name="payment_terms" x-model="terms" class="notify-form-select">
                        <option value="full">{{ __('notify.subscriptions.full_payment') }}</option>
                        <option value="installments">{{ __('notify.subscriptions.installments') }}</option>
                    </select>
                </div>
                <template x-if="terms === 'installments'">
                    <div class="notify-form-grid">
                        <div class="notify-form-group">
                            <label>{{ __('notify.subscriptions.installment_count') }}</label>
                            <input type="number" name="installments_count" min="2" max="12" value="{{ old('installments_count', 4) }}" class="notify-form-input">
                        </div>
                        <div class="notify-form-group">
                            <label>{{ __('notify.subscriptions.due_day') }}</label>
                            <input type="number" name="installment_due_day" min="1" max="31" value="{{ old('installment_due_day', 1) }}" class="notify-form-input">
                        </div>
                    </div>
                </template>
            </div>

            <div class="notify-modal-footer">
                <button type="button" class="notify-button-secondary" data-close-action-modal>{{ __('notify.actions.cancel') }}</button>
                <button type="submit" class="notify-button-primary">{{ __('notify.subscriptions.convert_to_subscriber') }}</button>
            </div>
        </form>
    </div>
</div>
@endif
