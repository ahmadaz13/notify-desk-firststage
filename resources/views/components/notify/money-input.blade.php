@props([
    'id',
    'name' => 'amount',
    'value' => null,
    'required' => false,
    'bag' => 'default',
    'hint' => false,
])

{{-- JOD amount input (P12): typed as a decimal string; the server converts to integer fils (Money). --}}
<div class="notify-money-input">
    <input id="{{ $id }}" name="{{ $name }}" value="{{ $value }}" inputmode="decimal" dir="ltr" autocomplete="off"
           placeholder="0.000" @if($required) required @endif
           {{ $attributes->merge(['class' => 'notify-input']) }}
           {!! \App\Support\FormState::attributes($errors ?? null, $name, $id, $bag, (bool) $hint) !!}>
    <span class="notify-money-input__unit" aria-hidden="true">{{ __('notify.common.currency_jod') }}</span>
</div>
