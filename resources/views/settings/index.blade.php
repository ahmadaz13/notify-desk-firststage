@extends('layouts.app')

@section('content')
<div class="notify-page-head"><div><div class="eyebrow">{{ __('notify.navigation.administration') }}</div><h1 class="page-title">{{ __('notify.navigation.settings') }}</h1><p>{{ __('notify.settings.subtitle') }}</p></div></div>
<form method="POST" action="{{ route('settings.update') }}" enctype="multipart/form-data" class="notify-settings-grid">@csrf @method('PUT')
    <section class="notify-settings-section"><h2>{{ __('notify.settings.company_documents') }}</h2><div class="notify-form-grid">
        @foreach(['company_name_ar','company_name_en','company_phone','company_email','registration_number','company_national_number','tax_number','authorized_signatory','contract_prefix','invoice_prefix'] as $key)<label class="notify-form-field"><span>{{ __('notify.settings.'.$key) }}</span><input @if($key === 'company_email') type="email" @endif name="{{ $key }}" value="{{ old($key, $settings[$key]) }}"></label>@endforeach
        <label class="notify-form-field"><span>{{ __('notify.settings.company_logo') }}</span><input type="file" name="company_logo" accept="image/*"></label>
        <label class="notify-form-field notify-form-field--wide"><span>{{ __('notify.settings.company_address') }}</span><textarea name="company_address">{{ old('company_address', $settings['company_address']) }}</textarea></label>
        <label class="notify-form-field notify-form-field--wide"><span>{{ __('notify.settings.default_contract_terms') }}</span><textarea name="default_contract_terms" rows="5">{{ old('default_contract_terms', $settings['default_contract_terms']) }}</textarea></label>
    </div></section>
    <section class="notify-settings-section"><h2>{{ __('notify.settings.operations') }}</h2><div class="notify-form-grid">
        <label class="notify-form-field"><span>{{ __('notify.settings.timezone') }}</span><select name="timezone"><option value="Asia/Amman">Asia/Amman</option></select></label>
        @foreach(['appointment_duration','free_installation_duration','post_install_followup_days'] as $key)<label class="notify-form-field"><span>{{ __('notify.settings.'.$key) }}</span><input type="number" name="{{ $key }}" value="{{ old($key, $settings[$key]) }}" required></label>@endforeach
        <label class="notify-form-field"><span>{{ __('notify.settings.workday_start') }}</span><input type="time" name="workday_start" value="{{ old('workday_start', $settings['workday_start']) }}" required></label><label class="notify-form-field"><span>{{ __('notify.settings.workday_end') }}</span><input type="time" name="workday_end" value="{{ old('workday_end', $settings['workday_end']) }}" required></label>
    </div></section>
    <section class="notify-settings-section"><h2>{{ __('notify.settings.subscriptions_contracts') }}</h2><div class="notify-form-grid">
        <label class="notify-form-field"><span>{{ __('notify.settings.currency') }}</span><select name="currency"><option value="JOD">JOD</option></select></label><label class="notify-form-field"><span>{{ __('notify.settings.default_billing_cycle') }}</span><select name="default_billing_cycle"><option value="monthly" @selected($settings['default_billing_cycle']==='monthly')>{{ __('notify.catalog.monthly') }}</option><option value="annual" @selected($settings['default_billing_cycle']==='annual')>{{ __('notify.catalog.annual') }}</option></select></label>
        @foreach(['auto_contract_on_paid_subscription','allow_monthly','allow_annual_installments'] as $key)<label class="notify-check"><input type="checkbox" name="{{ $key }}" value="1" @checked(old($key, $settings[$key]) == '1')><span>{{ __('notify.settings.'.$key) }}</span></label>@endforeach
        <div class="notify-form-field--wide"><small>{{ __('notify.settings.initial_contract_status') }}</small></div>
    </div></section>
    <section class="notify-settings-section"><h2>{{ __('notify.settings.optional_features') }}</h2><div class="notify-form-grid">
        <label class="notify-check"><input type="checkbox" name="feature_capital_financing" value="1" @checked(old('feature_capital_financing', $settings['feature_capital_financing']) == '1')><span>{{ __('notify.settings.feature_capital_financing') }}</span></label>
    </div></section>
    <div><button class="notify-button notify-button--primary" type="submit">{{ __('notify.actions.save') }}</button></div>
</form>
@endsection
