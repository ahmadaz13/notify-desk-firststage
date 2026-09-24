{{--
    System fields (§6, P13). Expects: $prefix, $system (nullable), $old (FormState::oldFor closure),
    $storedCredentials (saved client credentials for this System).
--}}
@php
    $jod = fn (?int $minor) => $minor === null ? '' : \App\Support\Money::fromMinorUnits($minor)->format();
    $requires = (bool) $old('requires_credentials', $system?->requires_credentials ?? false);
    $locked = $system && $system->requires_credentials && $storedCredentials > 0;
@endphp
<div class="notify-form-row">
    <x-notify.form-field :label="__('notify.systems.name_ar')" :for="$prefix.'-name-ar'" name="name_ar" :required="true">
        <input id="{{ $prefix }}-name-ar" class="notify-input" name="name_ar" value="{{ $old('name_ar', $system?->name_ar) }}" required maxlength="255" dir="rtl" lang="ar" @invalid('name_ar', $prefix.'-name-ar')>
    </x-notify.form-field>
    <x-notify.form-field :label="__('notify.systems.name_en')" :for="$prefix.'-name-en'" name="name_en" :required="true">
        <input id="{{ $prefix }}-name-en" class="notify-input" name="name_en" value="{{ $old('name_en', $system?->name_en) }}" required maxlength="255" dir="ltr" lang="en" @invalid('name_en', $prefix.'-name-en')>
    </x-notify.form-field>
    <x-notify.form-field :label="__('notify.systems.description_ar')" :for="$prefix.'-desc-ar'" name="description_ar" :optional="true" class="notify-form-row__full">
        <textarea id="{{ $prefix }}-desc-ar" class="notify-input" name="description_ar" rows="2" maxlength="500" dir="rtl" lang="ar" @invalid('description_ar', $prefix.'-desc-ar')>{{ $old('description_ar', $system?->description_ar) }}</textarea>
    </x-notify.form-field>
    <x-notify.form-field :label="__('notify.systems.description_en')" :for="$prefix.'-desc-en'" name="description_en" :optional="true" class="notify-form-row__full">
        <textarea id="{{ $prefix }}-desc-en" class="notify-input" name="description_en" rows="2" maxlength="500" dir="ltr" lang="en" @invalid('description_en', $prefix.'-desc-en')>{{ $old('description_en', $system?->description_en) }}</textarea>
    </x-notify.form-field>
    <x-notify.form-field :label="__('notify.systems.default_monthly')" :for="$prefix.'-monthly'" name="default_monthly_price_jod" :optional="true">
        <x-notify.money-input :id="$prefix.'-monthly'" name="default_monthly_price_jod" :value="$old('default_monthly_price_jod', $jod($system?->default_monthly_price_minor))" />
    </x-notify.form-field>
    <x-notify.form-field :label="__('notify.systems.default_annual')" :for="$prefix.'-annual'" name="default_annual_price_jod" :optional="true">
        <x-notify.money-input :id="$prefix.'-annual'" name="default_annual_price_jod" :value="$old('default_annual_price_jod', $jod($system?->default_annual_price_minor))" />
    </x-notify.form-field>
    <p class="notify-form-row__full notify-admin-muted notify-admin-small">{{ __('notify.systems.price_hint') }}</p>

    {{-- Credential capability (§18.1). --}}
    <x-notify.form-field :label="__('notify.systems.credentials_label')" name="requires_credentials" :group="true" class="notify-form-row__full"
        :hint="$locked ? trans_choice('notify.systems.credentials_locked', $storedCredentials, ['count' => $storedCredentials]) : __('notify.systems.credentials_hint')">
        @if($locked)
            <input type="hidden" name="requires_credentials" value="1">
            <span class="notify-admin-readonly"><x-notify.badge variant="info" icon="lock">{{ __('notify.systems.credentials_required') }}</x-notify.badge></span>
        @else
            <input type="hidden" name="requires_credentials" value="0">
            <label class="notify-choice-chip notify-admin-switch">
                <input type="checkbox" name="requires_credentials" value="1" @checked($requires) data-requires-credentials-toggle>
                <span>{{ __('notify.systems.credentials_toggle') }}</span>
            </label>
        @endif
    </x-notify.form-field>

    @if($system)
        <div class="notify-form-row__full">
            <label class="notify-choice-chip notify-admin-switch">
                <input type="checkbox" name="is_active" value="1" @checked((bool) $old('is_active', $system->is_active))>
                <span>{{ __('notify.systems.active_toggle') }}</span>
            </label>
            <p class="notify-form-field__hint">{{ __('notify.systems.active_hint') }}</p>
        </div>
        <p class="notify-form-row__full notify-admin-code">{{ __('notify.systems.reference_code') }} <code dir="ltr">{{ $system->code }}</code></p>
    @endif
</div>
