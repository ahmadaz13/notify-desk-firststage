@props([
    'label',
    'for' => null,
    'name' => null,
    'required' => false,
    'optional' => false,
    'hint' => null,
    'bag' => 'default',
    'group' => false,
])

{{--
    Form field (P12): visible label, optional hint and the field's own error right under the control.
    Put the control in the slot and give it @invalid('{name}', '{for}') so aria-invalid /
    aria-describedby point at the hint/error ids rendered here. `group` renders a fieldset + legend
    for radio/checkbox choices.
--}}
@php
    $error = \App\Support\FormState::error($errors ?? null, $name, $bag);
    $tag = $group ? 'fieldset' : 'div';
@endphp

<{{ $tag }} {{ $attributes->class(['notify-form-field', 'notify-form-field--group' => $group, 'is-invalid' => $error]) }} data-form-field @if($error) data-invalid @endif>
    @if($group)
        <legend class="notify-form-field__label">
    @else
        <label class="notify-form-field__label" @if($for) for="{{ $for }}" @endif>
    @endif
        <span>{{ $label }}</span>
        @if($required)
            <span class="notify-form-field__required" aria-hidden="true">*</span><span class="notify-visually-hidden">({{ __('notify.ui.required') }})</span>
        @elseif($optional)
            <span class="notify-form-field__optional">({{ __('notify.ui.optional') }})</span>
        @endif
    @if($group)
        </legend>
    @else
        </label>
    @endif

    {{ $slot }}

    @if($hint)
        <p class="notify-form-field__hint" @if($for) id="{{ $for }}-hint" @endif>{{ $hint }}</p>
    @endif
    @if($error)
        <p class="notify-form-field__error" @if($for) id="{{ $for }}-error" @endif>
            <x-notify.icon name="alert-circle" :size="14" />
            <span>{{ $error }}</span>
        </p>
    @endif
</{{ $tag }}>
