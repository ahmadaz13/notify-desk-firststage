@props(['client', 'sellableProducts' => collect()])

@if(\App\Support\Permissions::allows(auth()->user(), \App\Support\Permissions::START_PAID_SUBSCRIPTION))
@php
    $sheetId = 'modal-start-subscription';
    $old = \App\Support\FormState::oldFor($sheetId);
    $selectedSystems = array_map('intval', (array) $old('system_ids', []));
    $customId = $sellableProducts->firstWhere('code', \App\Models\Product::CODE_CUSTOM_SYSTEM)?->id;
    $state = [
        'cycle' => $old('billing_interval', \App\Models\Setting::get('default_billing_cycle', 'monthly')),
        'terms' => $old('payment_terms', 'full'),
        'custom' => $customId !== null && in_array($customId, $selectedSystems, true),
    ];
    $allowMonthly = \App\Models\Setting::get('allow_monthly', '1') === '1';
    $allowInstallments = \App\Models\Setting::get('allow_annual_installments', '1') === '1';
    if (! $allowMonthly) {
        $state['cycle'] = 'annual';
    }
@endphp

@formscope($sheetId)
<x-notify.sheet :id="$sheetId" size="lg" :title="__('notify.subscriptions.convert_to_subscriber')" :subtitle="__('notify.subscriptions.simple_terms_help')"
    :action="route('clients.guided-subscription.store', $client)"
    :form-attributes="['x-data' => '{ cycle: '.\Illuminate\Support\Js::from($state['cycle']).', terms: '.\Illuminate\Support\Js::from($state['terms']).', custom: '.\Illuminate\Support\Js::from($state['custom']).' }', 'data-start-subscription-form' => true]">
    <input type="hidden" name="_idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">

    <x-notify.form-field :label="__('notify.subscriptions.selected_systems')" name="system_ids" :required="true" :group="true">
        <div class="notify-choices notify-choices--stack">
            @foreach($sellableProducts as $system)
                <label class="notify-choice-chip">
                    <input type="checkbox" name="system_ids[]" value="{{ $system->id }}" @checked(in_array($system->id, $selectedSystems, true))
                        @if($system->id === $customId) @change="custom = $event.target.checked" @endif>
                    <span>{{ $system->name_ar }}@if($system->name_en) · {{ $system->name_en }}@endif</span>
                </label>
            @endforeach
        </div>
    </x-notify.form-field>

    {{-- Custom System: short project title/description for the contract (P8) --}}
    <div class="notify-form-row" x-show="custom" x-cloak data-custom-system-fields>
        <x-notify.form-field :label="__('notify.subscriptions.custom_system_title')" for="custom-system-title" name="custom_system_title">
            <input id="custom-system-title" class="notify-input" name="custom_system_title" value="{{ $old('custom_system_title') }}" maxlength="160" @invalid('custom_system_title', 'custom-system-title')>
        </x-notify.form-field>
        <x-notify.form-field :label="__('notify.subscriptions.custom_system_description')" for="custom-system-description" name="custom_system_description" :optional="true">
            <input id="custom-system-description" class="notify-input" name="custom_system_description" value="{{ $old('custom_system_description') }}" maxlength="300" @invalid('custom_system_description', 'custom-system-description')>
        </x-notify.form-field>
    </div>

    <div class="notify-form-row">
        <x-notify.form-field :label="__('notify.subscriptions.billing_cycle')" name="billing_interval" :required="true" :group="true">
            <div class="notify-choices notify-choices--segmented">
                @if($allowMonthly)
                    <label class="notify-choice-chip">
                        <input type="radio" name="billing_interval" value="monthly" x-model="cycle" required>
                        <span>{{ __('notify.subscriptions.monthly') }}</span>
                    </label>
                @endif
                <label class="notify-choice-chip">
                    <input type="radio" name="billing_interval" value="annual" x-model="cycle" required>
                    <span>{{ __('notify.subscriptions.annual') }}</span>
                </label>
            </div>
        </x-notify.form-field>
        <x-notify.form-field :label="__('notify.subscriptions.agreed_value_jod')" for="agreed-value" name="agreed_value_jod" :required="true">
            <x-notify.money-input id="agreed-value" name="agreed_value_jod" :required="true" :value="$old('agreed_value_jod')" />
        </x-notify.form-field>
        <x-notify.form-field :label="__('notify.subscriptions.start_date')" for="subscription-start" name="start_date">
            <input id="subscription-start" class="notify-input" type="date" name="start_date" value="{{ $old('start_date', now('Asia/Amman')->toDateString()) }}" @invalid('start_date', 'subscription-start')>
        </x-notify.form-field>
    </div>

    <div class="notify-form-row" x-show="cycle === 'annual'" x-cloak>
        <x-notify.form-field :label="__('notify.subscriptions.payment_terms')" name="payment_terms" :group="true" class="notify-form-row__full">
            <div class="notify-choices notify-choices--segmented">
                <label class="notify-choice-chip">
                    <input type="radio" name="payment_terms" value="full" x-model="terms">
                    <span>{{ __('notify.subscriptions.full_payment') }}</span>
                </label>
                @if($allowInstallments)
                    <label class="notify-choice-chip">
                        <input type="radio" name="payment_terms" value="installments" x-model="terms">
                        <span>{{ __('notify.subscriptions.installments') }}</span>
                    </label>
                @endif
            </div>
        </x-notify.form-field>
        <template x-if="terms === 'installments'">
            <div class="notify-form-row notify-form-row__full">
                <x-notify.form-field :label="__('notify.subscriptions.installment_count')" for="installments-count" name="installments_count">
                    <input id="installments-count" class="notify-input" type="number" name="installments_count" min="2" max="12" inputmode="numeric" dir="ltr" value="{{ $old('installments_count', 4) }}" @invalid('installments_count', 'installments-count')>
                </x-notify.form-field>
                <x-notify.form-field :label="__('notify.subscriptions.due_day')" for="installment-due-day" name="installment_due_day">
                    <input id="installment-due-day" class="notify-input" type="number" name="installment_due_day" min="1" max="31" inputmode="numeric" dir="ltr" value="{{ $old('installment_due_day', 1) }}" @invalid('installment_due_day', 'installment-due-day')>
                </x-notify.form-field>
            </div>
        </template>
    </div>

    <x-slot:footer>
        <button type="button" class="notify-button notify-button--ghost" data-sheet-close>{{ __('notify.actions.cancel') }}</button>
        <button type="submit" class="notify-button notify-button--primary">{{ __('notify.subscriptions.convert_to_subscriber') }}</button>
    </x-slot:footer>
</x-notify.sheet>
@endformscope
@endif
