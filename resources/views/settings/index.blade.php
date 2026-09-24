@extends('layouts.app')

{{--
    Settings (§16, P13): four sections, one form, one Save. Every visible setting drives behaviour
    (see SettingsController). Timezone (Asia/Amman) and currency (JOD) are fixed in V1 and shown as
    information only. No theme setting (D-04).
--}}
@php
    $value = fn (string $key) => old($key, $settings[$key]);
    $checked = fn (string $key) => (string) old($key, $settings[$key]) === '1';
    $sections = ['company' => 'company_contracts', 'operations' => 'operations', 'subscriptions' => 'subscriptions_contracts', 'features' => 'optional_features'];
@endphp

@section('content')
<div class="notify-admin notify-admin--settings" data-admin-page="settings">
    <x-notify.page-header :title="__('notify.navigation.settings')" :description="__('notify.settings.subtitle')" />

    <div class="notify-settings-layout">
        <nav class="notify-settings-nav" aria-label="{{ __('notify.settings.sections_label') }}">
            @foreach($sections as $anchor => $key)
                <a href="#settings-{{ $anchor }}" class="notify-settings-nav__item" data-settings-nav="{{ $anchor }}">{{ __('notify.settings.'.$key) }}</a>
            @endforeach
        </nav>

        <form method="POST" action="{{ route('settings.update') }}" enctype="multipart/form-data" class="notify-settings-form" data-settings-form>
            @csrf
            @method('PUT')

            {{-- 1. Company & Contracts --}}
            <section class="notify-admin-card" id="settings-company" aria-labelledby="settings-company-title" data-settings-section="company">
                <div class="notify-admin-card__head">
                    <div>
                        <h2 id="settings-company-title" class="notify-admin-card__title">{{ __('notify.settings.company_contracts') }}</h2>
                        <p class="notify-admin-card__desc">{{ __('notify.settings.company_contracts_desc') }}</p>
                    </div>
                </div>

                <h3 class="notify-admin-subtitle">{{ __('notify.settings.groups.identity') }}</h3>
                <div class="notify-form-row">
                    <x-notify.form-field :label="__('notify.settings.company_name_ar')" for="set-name-ar" name="company_name_ar" :required="true">
                        <input id="set-name-ar" class="notify-input" name="company_name_ar" value="{{ $value('company_name_ar') }}" required maxlength="255" dir="rtl" lang="ar" autocomplete="organization" @invalid('company_name_ar', 'set-name-ar')>
                    </x-notify.form-field>
                    <x-notify.form-field :label="__('notify.settings.company_name_en')" for="set-name-en" name="company_name_en" :required="true">
                        <input id="set-name-en" class="notify-input" name="company_name_en" value="{{ $value('company_name_en') }}" required maxlength="255" dir="ltr" lang="en" @invalid('company_name_en', 'set-name-en')>
                    </x-notify.form-field>
                    <x-notify.form-field :label="__('notify.settings.company_phone')" for="set-phone" name="company_phone" :optional="true">
                        <input id="set-phone" class="notify-input" type="tel" name="company_phone" value="{{ $value('company_phone') }}" maxlength="50" inputmode="tel" dir="ltr" autocomplete="tel" @invalid('company_phone', 'set-phone')>
                    </x-notify.form-field>
                    <x-notify.form-field :label="__('notify.settings.company_email')" for="set-email" name="company_email" :optional="true">
                        <input id="set-email" class="notify-input" type="email" name="company_email" value="{{ $value('company_email') }}" maxlength="255" dir="ltr" autocomplete="email" @invalid('company_email', 'set-email')>
                    </x-notify.form-field>
                    <x-notify.form-field :label="__('notify.settings.company_address')" for="set-address" name="company_address" :optional="true" class="notify-form-row__full">
                        <textarea id="set-address" class="notify-input" name="company_address" rows="2" maxlength="1000" @invalid('company_address', 'set-address')>{{ $value('company_address') }}</textarea>
                    </x-notify.form-field>
                    <x-notify.form-field :label="__('notify.settings.company_logo')" for="set-logo" name="company_logo" :optional="true" :hint="__('notify.settings.company_logo_hint')" class="notify-form-row__full">
                        <div class="notify-settings-logo">
                            @if($logoUrl)
                                <img src="{{ $logoUrl }}" alt="{{ __('notify.settings.current_logo') }}" class="notify-settings-logo__img">
                            @endif
                            <input id="set-logo" class="notify-input notify-admin-file" type="file" name="company_logo" accept="image/png,image/jpeg,image/webp" @invalid('company_logo', 'set-logo', 'default', true)>
                        </div>
                        @if($logoUrl)
                            <label class="notify-check notify-admin-small">
                                <input type="checkbox" name="remove_company_logo" value="1">
                                <span>{{ __('notify.settings.remove_logo') }}</span>
                            </label>
                        @endif
                    </x-notify.form-field>
                </div>

                <h3 class="notify-admin-subtitle">{{ __('notify.settings.groups.legal') }}</h3>
                <p class="notify-admin-muted notify-admin-small">{{ __('notify.settings.legal_hint') }}</p>
                <div class="notify-form-row">
                    @foreach(['registration_number' => 'set-reg', 'company_national_number' => 'set-national', 'tax_number' => 'set-tax', 'authorized_signatory' => 'set-signatory'] as $key => $id)
                        <x-notify.form-field :label="__('notify.settings.'.$key)" :for="$id" :name="$key" :optional="true">
                            <input id="{{ $id }}" class="notify-input" name="{{ $key }}" value="{{ $value($key) }}" maxlength="{{ $key === 'authorized_signatory' ? 255 : 100 }}" @if($key !== 'authorized_signatory') dir="ltr" @endif @invalid($key, $id)>
                        </x-notify.form-field>
                    @endforeach
                </div>

                <h3 class="notify-admin-subtitle">{{ __('notify.settings.groups.numbering') }}</h3>
                <div class="notify-form-row">
                    <x-notify.form-field :label="__('notify.settings.contract_prefix')" for="set-contract-prefix" name="contract_prefix" :required="true" :hint="__('notify.settings.contract_prefix_hint')">
                        <input id="set-contract-prefix" class="notify-input" name="contract_prefix" value="{{ $value('contract_prefix') }}" required maxlength="12" pattern="[A-Za-z0-9_]+" dir="ltr" autocapitalize="characters" @invalid('contract_prefix', 'set-contract-prefix', 'default', true)>
                    </x-notify.form-field>
                    <x-notify.form-field :label="__('notify.settings.invoice_prefix')" for="set-invoice-prefix" name="invoice_prefix" :required="true" :hint="__('notify.settings.invoice_prefix_hint')">
                        <input id="set-invoice-prefix" class="notify-input" name="invoice_prefix" value="{{ $value('invoice_prefix') }}" required maxlength="12" pattern="[A-Za-z0-9_]+" dir="ltr" autocapitalize="characters" @invalid('invoice_prefix', 'set-invoice-prefix', 'default', true)>
                    </x-notify.form-field>
                </div>

                <h3 class="notify-admin-subtitle">{{ __('notify.settings.groups.terms') }}</h3>
                <x-notify.form-field :label="__('notify.settings.default_contract_terms')" for="set-terms" name="default_contract_terms" :optional="true" :hint="__('notify.settings.default_contract_terms_hint')">
                    <textarea id="set-terms" class="notify-input" name="default_contract_terms" rows="5" maxlength="10000" @invalid('default_contract_terms', 'set-terms', 'default', true)>{{ $value('default_contract_terms') }}</textarea>
                </x-notify.form-field>
                <p class="notify-form-note">{{ __('notify.settings.default_contract_terms_pagination') }}</p>
            </section>

            {{-- 2. Operations --}}
            <section class="notify-admin-card" id="settings-operations" aria-labelledby="settings-operations-title" data-settings-section="operations">
                <div class="notify-admin-card__head">
                    <div>
                        <h2 id="settings-operations-title" class="notify-admin-card__title">{{ __('notify.settings.operations') }}</h2>
                        <p class="notify-admin-card__desc">{{ __('notify.settings.operations_desc') }}</p>
                    </div>
                </div>
                <div class="notify-form-row">
                    <x-notify.form-field :label="__('notify.settings.workday_start')" for="set-day-start" name="workday_start" :required="true">
                        <input id="set-day-start" class="notify-input" type="time" name="workday_start" value="{{ $value('workday_start') }}" required dir="ltr" @invalid('workday_start', 'set-day-start')>
                    </x-notify.form-field>
                    <x-notify.form-field :label="__('notify.settings.workday_end')" for="set-day-end" name="workday_end" :required="true">
                        <input id="set-day-end" class="notify-input" type="time" name="workday_end" value="{{ $value('workday_end') }}" required dir="ltr" @invalid('workday_end', 'set-day-end')>
                    </x-notify.form-field>
                    <p class="notify-form-row__full notify-form-field__hint">{{ __('notify.settings.workday_hint') }}</p>
                    <x-notify.form-field :label="__('notify.settings.appointment_duration')" for="set-appointment" name="appointment_duration" :required="true" :hint="__('notify.settings.appointment_duration_hint')">
                        <input id="set-appointment" class="notify-input" type="number" name="appointment_duration" value="{{ $value('appointment_duration') }}" required min="{{ \App\Support\OperationalSettings::MIN_DURATION }}" max="{{ \App\Support\OperationalSettings::MAX_DURATION }}" step="5" inputmode="numeric" dir="ltr" @invalid('appointment_duration', 'set-appointment', 'default', true)>
                    </x-notify.form-field>
                    <x-notify.form-field :label="__('notify.settings.free_installation_duration')" for="set-installation" name="free_installation_duration" :required="true" :hint="__('notify.settings.free_installation_duration_hint')">
                        <input id="set-installation" class="notify-input" type="number" name="free_installation_duration" value="{{ $value('free_installation_duration') }}" required min="{{ \App\Support\OperationalSettings::MIN_DURATION }}" max="{{ \App\Support\OperationalSettings::MAX_DURATION }}" step="5" inputmode="numeric" dir="ltr" @invalid('free_installation_duration', 'set-installation', 'default', true)>
                    </x-notify.form-field>
                    <x-notify.form-field :label="__('notify.settings.post_install_followup_days')" for="set-followup" name="post_install_followup_days" :required="true" :hint="__('notify.settings.post_install_followup_days_hint')" class="notify-form-row__full">
                        <input id="set-followup" class="notify-input notify-settings-short" type="number" name="post_install_followup_days" value="{{ $value('post_install_followup_days') }}" required min="{{ \App\Support\OperationalSettings::MIN_FOLLOW_UP_DAYS }}" max="{{ \App\Support\OperationalSettings::MAX_FOLLOW_UP_DAYS }}" step="1" inputmode="numeric" dir="ltr" @invalid('post_install_followup_days', 'set-followup', 'default', true)>
                    </x-notify.form-field>
                </div>
                <p class="notify-admin-fixed"><x-notify.icon name="lock" :size="14" />{{ __('notify.settings.timezone_fixed') }}</p>
            </section>

            {{-- 3. Subscriptions & Contracts --}}
            <section class="notify-admin-card" id="settings-subscriptions" aria-labelledby="settings-subscriptions-title" data-settings-section="subscriptions">
                <div class="notify-admin-card__head">
                    <div>
                        <h2 id="settings-subscriptions-title" class="notify-admin-card__title">{{ __('notify.settings.subscriptions_contracts') }}</h2>
                        <p class="notify-admin-card__desc">{{ __('notify.settings.subscriptions_contracts_desc') }}</p>
                    </div>
                </div>
                <x-notify.form-field :label="__('notify.settings.default_billing_cycle')" name="default_billing_cycle" :required="true" :group="true">
                    <div class="notify-choices notify-choices--segmented">
                        @foreach(['monthly', 'annual'] as $cycle)
                            <label class="notify-choice-chip">
                                <input type="radio" name="default_billing_cycle" value="{{ $cycle }}" required @checked($value('default_billing_cycle') === $cycle)>
                                <span>{{ __('notify.catalog.'.$cycle) }}</span>
                            </label>
                        @endforeach
                    </div>
                </x-notify.form-field>
                <div class="notify-settings-switches">
                    @foreach(['allow_monthly', 'allow_annual_installments', 'auto_contract_on_paid_subscription'] as $key)
                        <input type="hidden" name="{{ $key }}" value="0">
                        <label class="notify-settings-switch" data-setting="{{ $key }}">
                            <input type="checkbox" name="{{ $key }}" value="1" @checked($checked($key)) aria-describedby="set-{{ $key }}-hint">
                            <span class="notify-settings-switch__text">
                                <span class="notify-settings-switch__label">{{ __('notify.settings.'.$key) }}</span>
                                <span class="notify-settings-switch__hint" id="set-{{ $key }}-hint">{{ __('notify.settings.'.$key.'_hint') }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
                <p class="notify-admin-fixed"><x-notify.icon name="lock" :size="14" />{{ __('notify.settings.currency_fixed') }}</p>
            </section>

            {{-- 4. Optional Features --}}
            <section class="notify-admin-card" id="settings-features" aria-labelledby="settings-features-title" data-settings-section="features">
                <div class="notify-admin-card__head">
                    <div>
                        <h2 id="settings-features-title" class="notify-admin-card__title">{{ __('notify.settings.optional_features') }}</h2>
                        <p class="notify-admin-card__desc">{{ __('notify.settings.optional_features_desc') }}</p>
                    </div>
                </div>
                <div class="notify-settings-switches">
                    <input type="hidden" name="feature_capital_financing" value="0">
                    <label class="notify-settings-switch" data-setting="feature_capital_financing">
                        <input type="checkbox" name="feature_capital_financing" value="1" @checked($checked('feature_capital_financing')) aria-describedby="set-capital-hint">
                        <span class="notify-settings-switch__text">
                            <span class="notify-settings-switch__label">{{ __('notify.settings.feature_capital_financing') }}</span>
                            <span class="notify-settings-switch__hint" id="set-capital-hint">{{ __('notify.settings.feature_capital_financing_hint') }}</span>
                        </span>
                    </label>
                </div>
            </section>

            <div class="notify-settings-savebar">
                <span class="notify-admin-muted notify-admin-small">{{ __('notify.settings.save_hint') }}</span>
                <button class="notify-button notify-button--primary" type="submit" data-settings-save>{{ __('notify.settings.save') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
