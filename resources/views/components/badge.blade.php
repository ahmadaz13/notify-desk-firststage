@props([
    'variant' => 'default', // default, green, gold, red, blue
])

@php
    $variantStyle = match($variant) {
        'green' => 'background:#ecfdf5;color:var(--notify-success);',
        'gold' => 'background:#fffbeb;color:var(--notify-warning);',
        'red' => 'background:#fef2f2;color:var(--notify-danger);',
        'blue' => 'background:#eff6ff;color:var(--notify-primary);',
        default => 'background:#f1f5f9;color:var(--notify-charcoal);',
    };
@endphp

<span {{ $attributes->merge(['class' => "badge $variant", 'style' => "display:inline-flex;align-items:center;padding:5px 10px;border-radius:9px;font-size:12px;font-weight:800;$variantStyle"]) }}>
    {{ $slot }}
</span>
