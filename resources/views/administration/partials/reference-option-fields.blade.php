{{--
    Reference option fields (§15.1). Business types / lead sources: Arabic + English labels (English
    optional, falls back to Arabic). City/area: one suggestion text, typed in any language.
    Expects: $prefix, $listKey, $option (nullable), $old (FormState::oldFor closure).
--}}
@php
    $labelled = in_array($listKey, \App\Services\ReferenceDataService::LABELLED_LISTS, true);
@endphp
@if($labelled)
    <x-notify.form-field :label="__('notify.reference_data.fields.label_ar')" :for="$prefix.'-ar'" name="label_ar" :required="true">
        <input id="{{ $prefix }}-ar" class="notify-input" name="label_ar" value="{{ $old('label_ar', $option?->label_ar) }}" required maxlength="120" dir="rtl" lang="ar" @invalid('label_ar', $prefix.'-ar')>
    </x-notify.form-field>
    <x-notify.form-field :label="__('notify.reference_data.fields.label_en')" :for="$prefix.'-en'" name="label_en" :optional="true" :hint="__('notify.reference_data.fields.label_en_hint')">
        <input id="{{ $prefix }}-en" class="notify-input" name="label_en" value="{{ $old('label_en', $option?->label_en) }}" maxlength="120" dir="ltr" lang="en" @invalid('label_en', $prefix.'-en', 'default', true)>
    </x-notify.form-field>
@else
    <x-notify.form-field :label="__('notify.reference_data.fields.suggestion')" :for="$prefix.'-ar'" name="label_ar" :required="true" :hint="__('notify.reference_data.fields.suggestion_hint')">
        <input id="{{ $prefix }}-ar" class="notify-input" name="label_ar" value="{{ $old('label_ar', $option?->label_ar) }}" required maxlength="120" dir="auto" @invalid('label_ar', $prefix.'-ar', 'default', true)>
    </x-notify.form-field>
@endif
<x-notify.form-field :label="__('notify.reference_data.fields.sort_order')" :for="$prefix.'-order'" name="sort_order" :optional="true" :hint="__('notify.reference_data.fields.sort_order_hint')">
    <input id="{{ $prefix }}-order" class="notify-input" type="number" name="sort_order" min="0" max="100000" step="1" inputmode="numeric" value="{{ $old('sort_order', $option?->sort_order) }}" dir="ltr" @invalid('sort_order', $prefix.'-order', 'default', true)>
</x-notify.form-field>
