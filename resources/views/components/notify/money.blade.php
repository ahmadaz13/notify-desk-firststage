@props(['minor' => 0, 'signed' => false])
@php
    $value = (int) $minor;
    $sign = $signed && $value > 0 ? '+' : '';
@endphp
{{-- Money is integer fils; formatted once here, LTR-isolated inside RTL text (§25). --}}
<span {{ $attributes->class('notify-money') }} dir="ltr">{{ $sign }}{{ \App\Support\Money::fromMinorUnits($value)->format() }} {{ __('notify.common.currency_jod') }}</span>
